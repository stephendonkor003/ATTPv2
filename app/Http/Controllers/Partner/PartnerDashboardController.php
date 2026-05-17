<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\BudgetCommitment;
use App\Models\Consortium;
use App\Models\ConsortiumExpenseReport;
use App\Models\ConsortiumThinkTank;
use App\Models\Funder;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\ProgramFundingDocument;
use App\Models\PartnerActivityLog;
use App\Models\Project;
use App\Models\Activity;
use App\Models\ProcurementDisbursement;
use App\Models\SubActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PartnerDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:partner.dashboard.access']);
    }

    /**
     * Display the partner dashboard with statistics and recent programs
     */
    public function index()
    {
        $funder = $this->getPartnerFunder();
        $user = Auth::user();

        // Check if welcome modal should be shown (when welcome_shown_at is null)
        // This will continue to show until they complete the modal
        if ($funder->welcome_shown_at === null) {
            session()->flash('first_time_partner_login', true);
        }

        // Log activity
        $this->logActivity($funder, 'view_dashboard');

        // Get funded programs with eager loading
        $fundings = ProgramFunding::with(['program', 'governanceNode', 'documents'])
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->latest()
            ->get();

        $reportingOverview = $this->buildReportingOverview($funder, $fundings);

        // Calculate statistics
        $stats = [
            'total_programs'    => $fundings->count(),
            'total_funding'     => $fundings->sum('approved_amount'),
            'active_programs'   => $fundings->filter(fn($f) => !$f->isExpired())->count(),
            'pending_requests'  => $funder->informationRequests()->where('status', 'pending')->count(),
            'funds_remaining'   => $reportingOverview['funds_remaining'],
            'think_tanks'       => $reportingOverview['think_tank_count'],
        ];

        return view('partner.dashboard', compact('funder', 'fundings', 'stats', 'reportingOverview'));
    }

    /**
     * Display funding and think tank performance reports for this partner.
     */
    public function reports()
    {
        $funder = $this->getPartnerFunder();
        $this->logActivity($funder, 'view_partner_reports');

        $fundings = ProgramFunding::with(['program', 'governanceNode'])
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->latest()
            ->get();

        $reportingOverview = $this->buildReportingOverview($funder, $fundings);

        return view('partner.reports.index', compact('funder', 'fundings', 'reportingOverview'));
    }

    /**
     * Display a full financial position report by program hierarchy.
     */
    public function financialPosition(Request $request)
    {
        $funder = $this->getPartnerFunder();
        $this->logActivity($funder, 'view_financial_position');

        $fundings = ProgramFunding::with('program')
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->latest()
            ->get();

        $programs = $fundings
            ->map(fn (ProgramFunding $funding) => $this->resolveProgramForFunding($funding))
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $selectedProgramId = $request->input('program_id') ?: $programs->first()?->id;
        $program = null;
        $position = null;

        if ($selectedProgramId) {
            $program = Program::with([
                'projects.allocations',
                'projects.activities.allocations',
                'projects.activities.subActivities.allocations',
            ])->findOrFail($selectedProgramId);

            $programFundingIds = $fundings
                ->filter(function (ProgramFunding $funding) use ($program) {
                    return (string) $funding->program_id === (string) $program->id
                        || ($funding->program_name && $funding->program_name === $program->name);
                })
                ->pluck('id')
                ->all();

            abort_if(empty($programFundingIds), 403, 'This program is not funded by your partner account.');

            $position = $this->buildFinancialPosition($program, $programFundingIds);
        }

        return view('partner.reports.financial-position', compact(
            'funder',
            'programs',
            'program',
            'position',
            'selectedProgramId'
        ));
    }

    /**
     * Search one partner-funded think tank and show its full delivery record.
     */
    public function thinkTankDeepSearch(Request $request)
    {
        $funder = $this->getPartnerFunder();
        $this->logActivity($funder, 'view_think_tank_deep_search');

        $fundings = ProgramFunding::query()
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->get(['id', 'currency']);

        $fundingIds = $fundings->pluck('id')->filter()->values();

        $thinkTanks = ConsortiumThinkTank::with('consortium')
            ->whereHas('consortium', function ($query) use ($funder, $fundingIds) {
                $query->where('funder_id', $funder->id);

                if ($fundingIds->isNotEmpty()) {
                    $query->orWhereIn('program_funding_id', $fundingIds->all());
                }
            })
            ->orderBy('name')
            ->get();

        $selectedThinkTank = null;
        $deepSearch = null;

        if ($request->filled('think_tank_id')) {
            $selectedThinkTank = ConsortiumThinkTank::with([
                'consortium.workplans.reports',
                'fundAllocations.disbursementRequests',
                'disbursementRequests.allocation',
                'reports.workplan',
            ])
                ->whereKey($request->input('think_tank_id'))
                ->whereHas('consortium', function ($query) use ($funder, $fundingIds) {
                    $query->where('funder_id', $funder->id);

                    if ($fundingIds->isNotEmpty()) {
                        $query->orWhereIn('program_funding_id', $fundingIds->all());
                    }
                })
                ->firstOrFail();

            $expenses = ConsortiumExpenseReport::query()
                ->where('think_tank_member_id', $selectedThinkTank->id)
                ->latest('submitted_at')
                ->get();

            $allocations = $selectedThinkTank->fundAllocations;
            $disbursements = $selectedThinkTank->disbursementRequests;
            $reports = $selectedThinkTank->reports;
            $workplans = $selectedThinkTank->consortium?->workplans ?? collect();
            $actualPayments = ProcurementDisbursement::query()
                ->with('purchaseOrder')
                ->where('think_tank_member_id', $selectedThinkTank->id)
                ->orderByDesc('paid_at')
                ->orderByDesc('created_at')
                ->get();

            $allocated = (float) $allocations->sum('amount_allocated');
            if ($allocated <= 0) {
                $allocated = (float) ($selectedThinkTank->budget_allocated ?? 0);
            }

            $disbursed = (float) $actualPayments->sum('amount');
            if ($disbursed <= 0) {
                $disbursed = (float) $disbursements->sum('amount_approved');
            }
            if ($disbursed <= 0) {
                $disbursed = (float) $allocations->sum('amount_disbursed');
            }

            $spent = (float) $expenses->sum('amount');
            if ($spent <= 0) {
                $spent = (float) $allocations->sum('amount_spent');
            }

            $submittedReports = $reports->filter(function ($report) {
                return $report->submitted_at !== null
                    || in_array($report->status, ['submitted', 'approved', 'revisions_requested'], true);
            });

            $deepSearch = [
                'currency' => $allocations->first()?->currency
                    ?? $disbursements->first()?->currency
                    ?? $fundings->first()?->currency
                    ?? $funder->currency
                    ?? 'USD',
                'allocated' => $allocated,
                'disbursed' => $disbursed,
                'spent' => $spent,
                'remaining' => max($allocated - $disbursed, 0),
                'custody_remaining' => max($disbursed - $spent, 0),
                'disbursement_count' => $disbursements->count(),
                'payment_count' => $actualPayments->count(),
                'reports_submitted' => $submittedReports->count(),
                'average_progress' => round((float) $reports->avg('progress_percent'), 1),
                'allocations' => $allocations,
                'disbursements' => $disbursements->sortByDesc('requested_at')->values(),
                'actual_payments' => $actualPayments,
                'expenses' => $expenses,
                'workplans' => $workplans->sortByDesc('starts_on')->values(),
                'reports' => $reports->sortByDesc('submitted_at')->values(),
            ];
        }

        return view('partner.think-tanks.deep-search', compact(
            'funder',
            'thinkTanks',
            'selectedThinkTank',
            'deepSearch'
        ));
    }

    /**
     * Mark the welcome modal as seen
     */
    public function markWelcomeSeen()
    {
        $funder = $this->getPartnerFunder();

        // Update the welcome_shown_at timestamp
        $funder->update([
            'welcome_shown_at' => now()
        ]);

        // Log activity
        $this->logActivity($funder, 'completed_welcome');

        return response()->json(['success' => true]);
    }

    /**
     * Display a list of all funded programs
     */
    public function programs()
    {
        $funder = $this->getPartnerFunder();

        $this->logActivity($funder, 'view_programs');

        $fundings = ProgramFunding::with([
            'program',
            'governanceNode.level',
            'commitments',
            'documents'
        ])
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->latest()
            ->get();

        return view('partner.programs.index', compact('funder', 'fundings'));
    }

    /**
     * Display program insights with filters for drilling down
     */
    public function insights(Request $request)
    {
        $funder = $this->getPartnerFunder();
        $this->logActivity($funder, 'view_insights');

        // Get all funded programs for this funder
        $query = ProgramFunding::with([
            'program.sector',
            'program.department',
            'governanceNode.level',
        ])
            ->where('funder_id', $funder->id)
            ->where('status', 'approved');

        $selectedFunding = null;
        if ($request->filled('funding')) {
            $selectedFunding = ProgramFunding::with('program')
                ->where('funder_id', $funder->id)
                ->where('status', 'approved')
                ->find($request->funding);

            abort_unless($selectedFunding, 404, 'Program funding not found.');

            $query->where('id', $selectedFunding->id);
        }

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('program_name', 'like', "%{$search}%")
                  ->orWhereHas('program', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('year')) {
            $query->where(function($q) use ($request) {
                $q->where('start_year', '<=', $request->year)
                  ->where('end_year', '>=', $request->year);
            });
        }

        if ($request->filled('governance_node')) {
            $query->where('governance_node_id', $request->governance_node);
        }

        if ($request->filled('sector')) {
            $query->whereHas('program', function($q) use ($request) {
                $q->where('sector_id', $request->sector);
            });
        }

        $fundings = $query->latest()->get();

        // Load projects separately for each funding to ensure they're loaded
        $fundings->each(function($funding) {
            // First try to find program by program_id (foreign key)
            if ($funding->program_id) {
                $funding->setRelation('projects', Project::where('program_id', $funding->program_id)
                    ->with(['activities.subActivities', 'activities.governanceNode', 'governanceNode'])
                    ->get()
                );
            }
            // If no program_id, try to find program by matching name
            elseif ($funding->program_name) {
                // Find the program by name
                $program = \App\Models\Program::where('name', $funding->program_name)->first();

                if ($program) {
                    // Update the funding's program relationship
                    $funding->setRelation('program', $program);

                    // Load projects for this program
                    $funding->setRelation('projects', Project::where('program_id', $program->id)
                        ->with(['activities.subActivities', 'activities.governanceNode', 'governanceNode'])
                        ->get()
                    );
                } else {
                    $funding->setRelation('projects', collect([]));
                }
            } else {
                $funding->setRelation('projects', collect([]));
            }
        });

        // Get filter options
        $years = range(date('Y') + 2, date('Y') - 10);
        $governanceNodes = \App\Models\GovernanceNode::orderBy('name')->get();
        $sectors = \App\Models\Sector::orderBy('name')->get();

        return view('partner.insights', compact('funder', 'fundings', 'years', 'governanceNodes', 'sectors', 'selectedFunding'));
    }

    /**
     * Show detailed view of a specific funded program
     */
    public function showProgram($fundingId)
    {
        $funder = $this->getPartnerFunder();

        $funding = ProgramFunding::with([
            'program',
            'governanceNode.level',
            'documents',
            'commitments.resource',
            'commitments.resourceCategory',
            'creator',
            'approver'
        ])
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->findOrFail($fundingId);

        $program = $this->resolveProgramForFunding($funding);
        $programLinked = $program !== null;

        if ($program && (! $funding->relationLoaded('program') || ! $funding->program)) {
            $funding->setRelation('program', $program);
        }

        // Get projects (with activities and sub-activities) under this program
        $projects = $program
            ? Project::where('program_id', $program->id)
                ->with([
                    'governanceNode.level',
                    'activities.governanceNode.level',
                    'activities.subActivities.governanceNode',
                ])
                ->orderBy('name')
                ->get()
            : collect();

        $this->logActivity($funder, 'view_program', ['funding_id' => $fundingId]);

        return view('partner.programs.show', compact('funder', 'funding', 'projects', 'programLinked'));
    }

    /**
     * Show detailed view of a specific project
     */
    public function showProject($projectId)
    {
        $funder = $this->getPartnerFunder();

        // Get the project with relationships
        $project = Project::with([
            'program',
            'governanceNode.level',
            'activities.subActivities',
            'activities.governanceNode',
        ])->findOrFail($projectId);

        // Verify this project belongs to a program funded by this partner
        $funding = $this->resolveFundingForProgram(
            $funder,
            $project->program_id,
            $project->program->name ?? null
        );

        $this->logActivity($funder, 'view_project', ['project_id' => $projectId]);

        return view('partner.projects.show', compact('funder', 'project', 'funding'));
    }

    /**
     * Show detailed view of a specific activity
     */
    public function showActivity($activityId)
    {
        $funder = $this->getPartnerFunder();

        // Get the activity with relationships
        $activity = Activity::with([
            'project.program',
            'governanceNode.level',
            'subActivities.governanceNode',
        ])->findOrFail($activityId);

        // Verify this activity belongs to a project in a program funded by this partner
        $funding = $this->resolveFundingForProgram(
            $funder,
            $activity->project->program_id,
            $activity->project->program->name ?? null
        );

        $this->logActivity($funder, 'view_activity', ['activity_id' => $activityId]);

        return view('partner.activities.show', compact('funder', 'activity', 'funding'));
    }

    /**
     * Show program report for a specific funded program
     */
    public function programReport($fundingId)
    {
        $funder = $this->getPartnerFunder();

        $funding = ProgramFunding::with(['program', 'governanceNode.level'])
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->findOrFail($fundingId);

        $program = $this->resolveProgramForFunding($funding, [
            'projects.allocations',
            'projects.activities.subActivities',
        ]);

        $programLinked = $program !== null;

        if ($program && (! $funding->relationLoaded('program') || ! $funding->program)) {
            $funding->setRelation('program', $program);
        }

        $this->logActivity($funder, 'view_program_report', ['funding_id' => $fundingId]);

        return view('partner.programs.report', compact('funder', 'funding', 'program', 'programLinked'));
    }

    /**
     * Download a document for a funded program
     */
    public function downloadDocument($documentId)
    {
        $funder = $this->getPartnerFunder();

        // Get the document and verify the partner has access to it
        $document = ProgramFundingDocument::with('programFunding')
            ->findOrFail($documentId);

        // Verify this document belongs to a program funded by this partner
        if ($document->programFunding->funder_id !== $funder->id) {
            abort(403, 'You do not have permission to access this document.');
        }

        // Log the download activity
        $this->logActivity($funder, 'download_document', [
            'document_id' => $documentId,
            'file_name' => $document->file_name,
        ]);

        $privateDisk = Storage::disk('local');
        $path = $document->file_path;

        // The project previously stored some files on the public disk. Migrate on first access.
        if (! $privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        // Check if file exists on the private disk
        abort_unless($privateDisk->exists($path), 404, 'Document file not found.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        // Return the file for download
        return $privateDisk->download($path, $document->file_name, $headers);
    }

    /**
     * Get the authenticated partner's funder record
     */
    protected function getPartnerFunder(): Funder
    {
        $funder = Funder::where('user_id', Auth::id())->first();

        if (!$funder) {
            abort(403, 'No funder account associated with this user.');
        }

        if (!$funder->hasPortalAccess()) {
            abort(403, 'Portal access is not enabled for this account.');
        }

        return $funder;
    }

    /**
     * Log partner activity
     */
    protected function logActivity(Funder $funder, string $action, array $metadata = []): void
    {
        PartnerActivityLog::logActivity(
            funderId: $funder->id,
            userId: Auth::id(),
            action: $action,
            metadata: $metadata
        );
    }

    /**
     * Resolve a program instance for the given funding (by ID or name).
     */
    protected function resolveProgramForFunding(ProgramFunding $funding, array $with = []): ?Program
    {
        $program = $funding->program;

        if ($program) {
            if (!empty($with)) {
                $program->loadMissing($with);
            }
            return $program;
        }

        if ($funding->program_id) {
            return Program::with($with)->find($funding->program_id);
        }

        if ($funding->program_name) {
            return Program::with($with)->where('name', $funding->program_name)->first();
        }

        return null;
    }

    /**
     * Resolve funding for a program by ID or name (for this partner).
     */
    protected function resolveFundingForProgram(Funder $funder, ?string $programId, ?string $programName): ProgramFunding
    {
        $baseQuery = ProgramFunding::where('funder_id', $funder->id)
            ->where('status', 'approved');

        if ($programId) {
            $funding = (clone $baseQuery)->where('program_id', $programId)->first();
            if ($funding) {
                return $funding;
            }
        }

        if ($programName) {
            $funding = (clone $baseQuery)->where('program_name', $programName)->first();
            if ($funding) {
                return $funding;
            }
        }

        abort(404, 'Program funding not found.');
    }

    /**
     * Build program financial position from budget, commitments, and actual disbursements.
     */
    protected function buildFinancialPosition(Program $program, array $programFundingIds): array
    {
        $subActivityIds = $program->projects
            ->flatMap(fn (Project $project) => $project->activities)
            ->flatMap(fn (Activity $activity) => $activity->subActivities)
            ->pluck('id')
            ->all();

        $commitments = BudgetCommitment::query()
            ->whereIn('program_funding_id', $programFundingIds)
            ->where('status', BudgetCommitment::STATUS_APPROVED)
            ->get();

        $directCommitments = [
            'project' => $commitments->where('allocation_level', 'project')->groupBy('allocation_id'),
            'activity' => $commitments->where('allocation_level', 'activity')->groupBy('allocation_id'),
            'sub_activity' => $commitments->where('allocation_level', 'sub_activity')->groupBy('allocation_id'),
        ];

        $disbursements = empty($subActivityIds)
            ? collect()
            : ProcurementDisbursement::query()
                ->whereIn('sub_activity_id', $subActivityIds)
                ->whereNotIn('status', ['cancelled', 'void'])
                ->get();

        $disbursementBySub = $disbursements->groupBy('sub_activity_id')
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $projectRows = collect();
        $totals = $this->emptyFinancialTotals();

        foreach ($program->projects->sortBy('name') as $project) {
            $projectDirectBudget = (float) (($project->total_budget ?? 0) ?: $project->allocations->sum('amount'));
            $projectDirectCommitment = (float) ($directCommitments['project']->get($project->id)?->sum('commitment_amount') ?? 0);
            $activityRows = collect();
            $projectChildren = $this->emptyFinancialTotals();

            foreach ($project->activities->sortBy('name') as $activity) {
                $activityDirectBudget = (float) $activity->allocations->sum('amount');
                $activityDirectCommitment = (float) ($directCommitments['activity']->get($activity->id)?->sum('commitment_amount') ?? 0);
                $subRows = collect();
                $activityChildren = $this->emptyFinancialTotals();

                foreach ($activity->subActivities->sortBy('name') as $subActivity) {
                    $budget = (float) $subActivity->allocations->sum('amount');
                    $committed = (float) ($directCommitments['sub_activity']->get($subActivity->id)?->sum('commitment_amount') ?? 0);
                    $disbursed = (float) ($disbursementBySub[$subActivity->id] ?? 0);
                    $row = $this->financialNode($subActivity->name, 'sub_activity', $budget, $committed, $disbursed);

                    $subRows->push($row);
                    $activityChildren = $this->addFinancialTotals($activityChildren, $row);
                }

                $activityBudget = max($activityDirectBudget, $activityChildren['budget']);
                $activityCommitted = $activityDirectCommitment + $activityChildren['committed'];
                $activityDisbursed = $activityChildren['disbursed'];
                $activityRow = $this->financialNode($activity->name, 'activity', $activityBudget, $activityCommitted, $activityDisbursed);
                $activityRow['children'] = $subRows;

                $activityRows->push($activityRow);
                $projectChildren = $this->addFinancialTotals($projectChildren, $activityRow);
            }

            $projectBudget = max($projectDirectBudget, $projectChildren['budget']);
            $projectCommitted = $projectDirectCommitment + $projectChildren['committed'];
            $projectDisbursed = $projectChildren['disbursed'];
            $projectRow = $this->financialNode($project->name, 'project', $projectBudget, $projectCommitted, $projectDisbursed);
            $projectRow['children'] = $activityRows;

            $projectRows->push($projectRow);
            $totals = $this->addFinancialTotals($totals, $projectRow);
        }

        $approvedFunding = (float) ProgramFunding::whereIn('id', $programFundingIds)->sum('approved_amount');
        $totals['approved_funding'] = $approvedFunding;
        $totals['funding_remaining'] = max($approvedFunding - $totals['disbursed'], 0);

        return [
            'currency' => ProgramFunding::whereIn('id', $programFundingIds)->value('currency') ?? $program->currency ?? 'USD',
            'rows' => $projectRows,
            'totals' => $totals,
        ];
    }

    protected function emptyFinancialTotals(): array
    {
        return [
            'budget' => 0.0,
            'committed' => 0.0,
            'disbursed' => 0.0,
            'remaining_budget' => 0.0,
        ];
    }

    protected function financialNode(string $label, string $level, float $budget, float $committed, float $disbursed): array
    {
        return [
            'label' => $label,
            'level' => $level,
            'budget' => $budget,
            'committed' => $committed,
            'disbursed' => $disbursed,
            'remaining_budget' => max($budget - $committed, 0),
            'remaining_to_disburse' => max($committed - $disbursed, 0),
            'commitment_rate' => $budget > 0 ? round(($committed / $budget) * 100, 1) : 0,
            'disbursement_rate' => $committed > 0 ? round(($disbursed / $committed) * 100, 1) : 0,
            'children' => collect(),
        ];
    }

    protected function addFinancialTotals(array $totals, array $row): array
    {
        $totals['budget'] += (float) $row['budget'];
        $totals['committed'] += (float) $row['committed'];
        $totals['disbursed'] += (float) $row['disbursed'];
        $totals['remaining_budget'] += (float) $row['remaining_budget'];

        return $totals;
    }

    /**
     * Build fund balance and think tank reporting performance for a funding partner.
     */
    protected function buildReportingOverview(Funder $funder, $fundings): array
    {
        $fundingIds = $fundings->pluck('id')->filter()->values();

        $consortia = Consortium::with([
            'members.consortium',
            'members.fundAllocations',
            'members.disbursementRequests',
            'members.reports',
            'fundAllocations',
            'activityReports',
        ])
            ->where(function ($query) use ($funder, $fundingIds) {
                $query->where('funder_id', $funder->id);

                if ($fundingIds->isNotEmpty()) {
                    $query->orWhereIn('program_funding_id', $fundingIds->all());
                }
            })
            ->orderBy('name')
            ->get();

        $allocations = $consortia->flatMap->fundAllocations;
        $members = $consortia->flatMap->members->unique('id')->values();
        $reports = $consortia->flatMap->activityReports;
        $memberIds = $members->pluck('id')->all();
        $actualPayments = empty($memberIds)
            ? collect()
            : ProcurementDisbursement::query()
                ->whereIn('think_tank_member_id', $memberIds)
                ->get();

        $totalApproved = (float) $fundings->sum('approved_amount');
        $totalAllocated = (float) $allocations->sum('amount_allocated');
        $totalCommitted = (float) $allocations->sum('amount_committed');
        $totalDisbursed = (float) $actualPayments->sum('amount');
        if ($totalDisbursed <= 0) {
            $totalDisbursed = (float) $members->flatMap->disbursementRequests->sum('amount_approved');
        }
        if ($totalDisbursed <= 0) {
            $totalDisbursed = (float) $allocations->sum('amount_disbursed');
        }
        $totalSpent = empty($memberIds)
            ? 0.0
            : (float) ConsortiumExpenseReport::query()
                ->whereIn('think_tank_member_id', $memberIds)
                ->sum('amount');
        if ($totalSpent <= 0) {
            $totalSpent = (float) $allocations->sum('amount_spent');
        }

        $submittedStatuses = ['submitted', 'approved', 'revisions_requested'];

        $thinkTankPerformance = $members->map(function ($member) use ($submittedStatuses, $actualPayments) {
            $memberReports = $member->reports;
            $submittedReports = $memberReports->filter(function ($report) use ($submittedStatuses) {
                return $report->submitted_at !== null || in_array($report->status, $submittedStatuses, true);
            });

            $allocated = (float) $member->fundAllocations->sum('amount_allocated');
            $disbursed = (float) $actualPayments
                ->where('think_tank_member_id', $member->id)
                ->sum('amount');
            if ($disbursed <= 0) {
                $disbursed = (float) $member->disbursementRequests->sum('amount_approved');
            }
            if ($disbursed <= 0) {
                $disbursed = (float) $member->fundAllocations->sum('amount_disbursed');
            }
            $spent = (float) $member->fundAllocations->sum('amount_spent');
            $submittedCount = $submittedReports->count();
            $totalReports = $memberReports->count();
            $allocationBase = $allocated > 0 ? $allocated : (float) ($member->budget_allocated ?? 0);

            return [
                'name' => $member->name,
                'country' => $member->country,
                'consortium' => $member->consortium?->name,
                'allocated' => $allocationBase,
                'disbursed' => $disbursed,
                'spent' => $spent,
                'remaining' => max($allocationBase - $disbursed, 0),
                'custody_remaining' => max($disbursed - $spent, 0),
                'total_reports' => $totalReports,
                'submitted_reports' => $submittedCount,
                'approved_reports' => $memberReports->where('status', 'approved')->count(),
                'revision_reports' => $memberReports->where('status', 'revisions_requested')->count(),
                'pending_secretariat_reports' => $submittedReports->where('status', 'submitted')->count(),
                'average_progress' => round((float) $memberReports->avg('progress_percent'), 1),
                'last_submitted_at' => $submittedReports->sortByDesc('submitted_at')->first()?->submitted_at,
                'submission_rate' => $totalReports > 0 ? round(($submittedCount / $totalReports) * 100, 1) : 0,
            ];
        })->sortByDesc('submitted_reports')->values();

        return [
            'currency' => $fundings->first()?->currency ?? $funder->currency ?? 'USD',
            'total_approved' => $totalApproved,
            'total_allocated' => $totalAllocated,
            'total_committed' => $totalCommitted,
            'total_disbursed' => $totalDisbursed,
            'total_spent' => $totalSpent,
            'funds_remaining' => max($totalApproved - $totalDisbursed, 0),
            'unallocated_balance' => max($totalApproved - $totalAllocated, 0),
            'think_tank_count' => $members->count(),
            'consortium_count' => $consortia->count(),
            'reports_submitted' => $thinkTankPerformance->sum('submitted_reports'),
            'reports_pending_secretariat' => $thinkTankPerformance->sum('pending_secretariat_reports'),
            'average_progress' => round((float) $reports->avg('progress_percent'), 1),
            'think_tank_performance' => $thinkTankPerformance,
        ];
    }
}
