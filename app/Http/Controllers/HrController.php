<?php

namespace App\Http\Controllers;

use App\Models\{
    HrPosition,
    HrVacancy,
    HrApplicant,
    HrEmployee,
    Resource,
    ResourceCategory
};
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrController extends Controller
{
    /* =====================================================
        GOVERNANCE SCOPING HELPERS
    ===================================================== */

    /**
     * Get governance node IDs the current user can access.
     * Returns null if user has permission to view all nodes OR is admin.
     * Returns empty array if user has no governance node assigned.
     * Returns array with user's governance node ID otherwise.
     */
    private function scopedNodeIds(): ?array
    {
        $currentUser = Auth::user();

        if (!$currentUser) {
            return [];
        }

        // Admin bypass
        if ($currentUser->isAdmin()) {
            return null;
        }

        // Special permission to view all governance nodes
        if ($currentUser->can('hr.view_all_nodes')) {
            return null;
        }

        // User without governance node
        if (!$currentUser->governance_node_id) {
            return [];
        }

        // User with governance node - scoped access
        return [$currentUser->governance_node_id];
    }

    /**
     * Check if user can view all nodes (admin or special permission)
     */
    private function canViewAllNodes(): bool
    {
        return $this->scopedNodeIds() === null;
    }

    /**
     * Assert that user has access to the given position.
     */
    private function assertPositionInScope(HrPosition $position): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$position->governance_node_id || !in_array($position->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this position.');
        }
    }

    /**
     * Assert that user has access to the given vacancy.
     */
    private function assertVacancyInScope(HrVacancy $vacancy): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$vacancy->governance_node_id || !in_array($vacancy->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this vacancy.');
        }
    }

    /**
     * Assert that user has access to the given applicant.
     */
    private function assertApplicantInScope(HrApplicant $applicant): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$applicant->governance_node_id || !in_array($applicant->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this applicant.');
        }
    }

    /* =====================================================
        POSITIONS
    ===================================================== */

    public function positions()
    {
        $scopedNodeIds = $this->scopedNodeIds();

        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to HR positions.');
        }

        $positions = HrPosition::with(['resource', 'governanceNode'])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Get HR-enabled resources filtered by governance
        $hrResources = Resource::where('is_human_resource', true)
            ->where('status', 'active')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->with('category')
            ->get();

        return view('hr.positions.index', compact('positions', 'hrResources'));
    }

    public function storePosition(Request $request)
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to create positions.');
        }

        $validated = $request->validate([
            'resource_id'     => 'required|exists:myb_resources,id',
            'title'           => 'required|string|max:255',
            'employment_type' => 'required|in:permanent,contract,temporary,consultant',
            'grade_level'     => 'nullable|string|max:50',
            'description'     => 'nullable|string',
        ]);

        // Enforce HR-only resources
        $resource = Resource::where('id', $validated['resource_id'])
            ->where('is_human_resource', 1)
            ->first();

        abort_unless($resource, 403, 'Selected resource is not HR-enabled.');

        // Validate resource is in scope
        if ($scopedNodeIds !== null && (!$resource->governance_node_id || !in_array($resource->governance_node_id, $scopedNodeIds, true))) {
            abort(403, 'You do not have access to this resource.');
        }

        HrPosition::create([
            ...$validated,
            'governance_node_id' => $resource->governance_node_id,
            'status'             => 'active',
            'created_by'         => Auth::id(),
        ]);

        return back()->with('success', 'Position created successfully.');
    }

    public function updatePosition(Request $request, HrPosition $position)
    {
        $this->assertPositionInScope($position);

        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'employment_type' => 'required|in:permanent,contract,temporary,consultant',
            'grade_level'     => 'nullable|string|max:50',
            'description'     => 'nullable|string',
            'status'          => 'required|in:active,inactive',
        ]);

        $position->update($validated);

        return back()->with('success', 'Position updated successfully.');
    }

    public function destroyPosition(HrPosition $position)
    {
        $this->assertPositionInScope($position);

        if ($position->vacancies()->exists()) {
            return back()->with('error', 'Cannot delete position with existing vacancies.');
        }

        if ($position->employees()->exists()) {
            return back()->with('error', 'Cannot delete position with existing employees.');
        }

        $position->delete();

        return back()->with('success', 'Position deleted successfully.');
    }

    /* =====================================================
        VACANCIES
    ===================================================== */

    public function vacancies()
    {
        $scopedNodeIds = $this->scopedNodeIds();

        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to HR vacancies.');
        }

        $vacancies = HrVacancy::with(['position.resource', 'governanceNode'])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Get positions filtered by governance
        $positions = HrPosition::where('status', 'active')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->get();

        return view('hr.vacancies.index', compact('vacancies', 'positions'));
    }

    public function storeVacancy(Request $request)
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to create vacancies.');
        }

        $validated = $request->validate([
            'position_id'         => 'required|exists:hr_positions,id',
            'open_date'           => 'required|date',
            'close_date'          => 'required|date|after:open_date',
            'number_of_positions' => 'required|integer|min:1',
            'is_public'           => 'nullable|boolean',
        ]);

        $position = HrPosition::findOrFail($validated['position_id']);
        $this->assertPositionInScope($position);

        HrVacancy::create([
            'governance_node_id'  => $position->governance_node_id,
            'position_id'         => $validated['position_id'],
            'vacancy_code'        => 'VAC-' . strtoupper(Str::random(6)),
            'open_date'           => $validated['open_date'],
            'close_date'          => $validated['close_date'],
            'number_of_positions' => $validated['number_of_positions'],
            'is_public'           => $request->boolean('is_public'),
            'status'              => 'draft',
            'created_by'          => Auth::id(),
        ]);

        return back()->with('success', 'Vacancy created as draft.');
    }

    public function submitVacancyForApproval(HrVacancy $vacancy)
    {
        $this->assertVacancyInScope($vacancy);

        abort_if($vacancy->status !== 'draft', 400, 'Only draft vacancies can be submitted.');

        $vacancy->update(['status' => 'submitted']);

        return back()->with('success', 'Vacancy submitted for approval.');
    }

    public function approveVacancy(HrVacancy $vacancy)
    {
        $this->assertVacancyInScope($vacancy);

        abort_if($vacancy->status !== 'submitted', 400, 'Vacancy must be submitted first.');

        $vacancy->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Vacancy approved.');
    }

    public function publishVacancy(HrVacancy $vacancy)
    {
        $this->assertVacancyInScope($vacancy);

        abort_if($vacancy->status !== 'approved', 400, 'Vacancy must be approved.');

        $vacancy->update(['status' => 'published']);

        return back()->with('success', 'Vacancy published publicly.');
    }

    public function closeVacancy(HrVacancy $vacancy)
    {
        $this->assertVacancyInScope($vacancy);

        abort_if(!in_array($vacancy->status, ['published','approved']), 400);

        $vacancy->update(['status' => 'closed']);

        return back()->with('success', 'Vacancy closed.');
    }

    /* =====================================================
        APPLICANTS
    ===================================================== */

    public function applicants(HrVacancy $vacancy)
    {
        $this->assertVacancyInScope($vacancy);

        $applicants = HrApplicant::with('shortlist')
            ->where('vacancy_id', $vacancy->id)
            ->orderBy('submitted_at', 'desc')
            ->get();

        return view('hr.applicants.index', compact('vacancy', 'applicants'));
    }

    public function showApplicant(HrApplicant $applicant)
    {
        $this->assertApplicantInScope($applicant);

        $applicant->load(['shortlist', 'vacancy']);
        $shortlist = $applicant->shortlist;

        return view('hr.applicants.show', compact('applicant', 'shortlist'));
    }

    public function downloadApplicantFile(Request $request, HrApplicant $applicant, string $which)
    {
        $this->assertApplicantInScope($applicant);

        $map = [
            'cv' => 'cv_path',
            'cover_letter' => 'cover_letter_path',
        ];

        abort_unless(isset($map[$which]), 404);

        $path = (string) ($applicant->getAttribute($map[$which]) ?? '');
        abort_if($path === '', 404, 'File not found.');

        $privateDisk = Storage::disk('local');

        if (! $privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            // Best-effort migration from public -> private.
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'File missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('download')) {
            return $privateDisk->download($path, basename($path), $headers);
        }

        return $privateDisk->response($path, null, $headers);
    }

    /* =====================================================
        AI APPLICANT SCORING
    ===================================================== */

    protected function aiScoreApplicant(HrApplicant $applicant): float
    {
        // Simple deterministic AI-style heuristic
        $score = 0;

        if ($applicant->cv_path) {
            $score += 40;
        }

        if ($applicant->cover_letter_path) {
            $score += 20;
        }

        if ($applicant->nationality) {
            $score += 10;
        }

        if ($applicant->phone) {
            $score += 10;
        }

        return min($score, 100);
    }

    public function scoreApplicantAI(HrApplicant $applicant)
    {
        $this->assertApplicantInScope($applicant);

        abort_if($applicant->status !== 'applied', 400, 'Applicant already processed.');

        DB::transaction(function () use ($applicant) {

            $score = $this->aiScoreApplicant($applicant);

            DB::table('hr_shortlists')->insert([
                'applicant_id'   => $applicant->id,
                'stage'          => 'screening',
                'score'          => $score,
                'remarks'        => 'AI auto-screening score',
                'shortlisted_by' => Auth::id(),
                'shortlisted_at' => now(),
            ]);

            $applicant->update(['status' => 'scored']);
        });

        return back()->with('success', 'Applicant scored by AI.');
    }

    public function shortlistApplicant(HrApplicant $applicant)
    {
        $this->assertApplicantInScope($applicant);

        abort_if($applicant->status !== 'scored', 400, 'Applicant must be scored first.');

        $applicant->update(['status' => 'shortlisted']);

        return back()->with('success', 'Applicant shortlisted.');
    }

    public function rejectApplicant(HrApplicant $applicant)
    {
        $this->assertApplicantInScope($applicant);

        $applicant->update(['status' => 'rejected']);

        return back()->with('success', 'Applicant rejected.');
    }

    /* =====================================================
        HIRING / EMPLOYEES
    ===================================================== */

    public function hireApplicant(HrApplicant $applicant)
    {
        $this->assertApplicantInScope($applicant);

        try {

            if ($applicant->status === 'hired') {
                return back()->with('error', 'This applicant has already been hired.');
            }

            if ($applicant->status !== 'shortlisted') {
                return back()->with('error', 'Only shortlisted applicants can be hired.');
            }

            DB::transaction(function () use ($applicant) {

                $user = User::where('email', $applicant->email)->first();

                $plainPassword = null;

                if (!$user) {
                    $plainPassword = Str::random(10);

                    $user = User::create([
                        'name'                 => $applicant->full_name,
                        'email'                => $applicant->email,
                        'password'             => Hash::make($plainPassword),
                        'user_type'            => 'employee',
                        'governance_node_id'   => $applicant->governance_node_id,
                        'must_change_password' => true,
                    ]);
                }

                HrEmployee::updateOrCreate(
                    ['applicant_id' => $applicant->id],
                    [
                        'governance_node_id'    => $applicant->governance_node_id,
                        'user_id'               => $user->id,
                        'position_id'           => $applicant->vacancy->position_id,
                        'employee_code'         => 'EMP-' . strtoupper(Str::random(6)),
                        'employment_start_date' => now(),
                        'contract_type'         => $applicant->vacancy->position->employment_type,
                        'status'                => 'active',
                    ]
                );

                $applicant->update(['status' => 'hired']);

                if ($plainPassword) {
                    Mail::send(
                        'emails.hr.employee-welcome',
                        [
                            'name'     => $user->name,
                            'email'    => $user->email,
                            'password' => $plainPassword,
                            'userType' => 'Employee',
                        ],
                        function ($message) use ($user) {
                            $message->to($user->email)
                                    ->subject('Congratulations! You Have Been Hired');
                        }
                    );
                }
            });

            return back()->with('success', 'Applicant hired successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function scheduleInterview(Request $request, HrApplicant $applicant)
    {
        $this->assertApplicantInScope($applicant);

        $request->validate([
            'interview_date' => 'required|date',
            'interview_mode' => 'required|in:physical,virtual',
            'interview_link' => 'nullable|string|max:255',
        ]);

        DB::table('hr_interviews')->insert([
            'applicant_id'   => $applicant->id,
            'interview_date' => $request->interview_date,
            'interview_mode' => $request->interview_mode,
            'interview_link' => $request->interview_link,
            'scheduled_by'   => Auth::id(),
            'created_at'     => now(),
        ]);

        $applicant->update(['status' => 'interviewed']);

        return back()->with('success', 'Interview scheduled.');
    }

    public function bulkScoreApplicants(HrVacancy $vacancy)
    {
        $this->assertVacancyInScope($vacancy);

        $applicants = HrApplicant::where('vacancy_id', $vacancy->id)
            ->where('status', 'applied')
            ->get();

        foreach ($applicants as $applicant) {
            $score = $this->aiScoreApplicant($applicant);

            DB::table('hr_shortlists')->insert([
                'applicant_id'   => $applicant->id,
                'stage'          => 'screening',
                'score'          => $score,
                'remarks'        => 'Bulk AI scoring',
                'shortlisted_by' => Auth::id(),
                'shortlisted_at' => now(),
            ]);

            $applicant->update(['status' => 'scored']);
        }

        return back()->with('success', 'All applicants scored successfully.');
    }

    /* =====================================================
        EMPLOYEES
    ===================================================== */

    public function employees()
    {
        $scopedNodeIds = $this->scopedNodeIds();

        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to HR employees.');
        }

        $employees = HrEmployee::with(['applicant', 'position', 'governanceNode'])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return view('hr.employees.index', compact('employees'));
    }

    /* =====================================================
        ANALYTICS
    ===================================================== */

    public function analytics()
    {
        $scopedNodeIds = $this->scopedNodeIds();

        // Build queries with governance filtering
        $applicantQuery = HrApplicant::query()
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            });

        $vacancyQuery = HrVacancy::query()
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            });

        $employeeQuery = HrEmployee::query()
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            });

        return view('hr.analytics.index', [
            'totalApplicants' => (clone $applicantQuery)->count(),
            'scored'          => (clone $applicantQuery)->where('status', 'scored')->count(),
            'shortlisted'     => (clone $applicantQuery)->where('status', 'shortlisted')->count(),
            'hired'           => (clone $applicantQuery)->where('status', 'hired')->count(),
            'rejected'        => (clone $applicantQuery)->where('status', 'rejected')->count(),
            'totalVacancies'  => (clone $vacancyQuery)->count(),
            'publishedVacancies' => (clone $vacancyQuery)->where('status', 'published')->count(),
            'totalEmployees'  => (clone $employeeQuery)->count(),
            'activeEmployees' => (clone $employeeQuery)->where('status', 'active')->count(),
            'canViewAllNodes' => $this->canViewAllNodes(),
        ]);
    }
}
