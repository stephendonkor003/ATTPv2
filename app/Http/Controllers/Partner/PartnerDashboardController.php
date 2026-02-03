<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Funder;
use App\Models\ProgramFunding;
use App\Models\ProgramFundingDocument;
use App\Models\PartnerActivityLog;
use App\Models\Project;
use App\Models\Activity;
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

        // Calculate statistics
        $stats = [
            'total_programs'    => $fundings->count(),
            'total_funding'     => $fundings->sum('approved_amount'),
            'active_programs'   => $fundings->filter(fn($f) => !$f->isExpired())->count(),
            'pending_requests'  => $funder->informationRequests()->where('status', 'pending')->count(),
        ];

        return view('partner.dashboard', compact('funder', 'fundings', 'stats'));
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

        return view('partner.insights', compact('funder', 'fundings', 'years', 'governanceNodes', 'sectors'));
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

        // Get projects under this program
        $projects = Project::where('program_id', $funding->program_id)
            ->with(['activities', 'governanceNode'])
            ->get();

        // Get activities across all projects
        $activities = Activity::whereIn('project_id', $projects->pluck('id'))
            ->with(['project', 'subActivities'])
            ->get();

        $this->logActivity($funder, 'view_program', ['funding_id' => $fundingId]);

        return view('partner.programs.show', compact('funder', 'funding', 'projects', 'activities'));
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
        $funding = ProgramFunding::where('program_id', $project->program_id)
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->firstOrFail();

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
        $funding = ProgramFunding::where('program_id', $activity->project->program_id)
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->firstOrFail();

        $this->logActivity($funder, 'view_activity', ['activity_id' => $activityId]);

        return view('partner.activities.show', compact('funder', 'activity', 'funding'));
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
}
