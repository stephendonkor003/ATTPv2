<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ConsortiumActivityReport;
use App\Models\ConsortiumThinkTank;
use App\Models\ConsortiumWorkplan;
use App\Models\ThinkTankResearchOutput;
use App\Models\User;
use App\Models\VendorDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VendorThinkTankController extends Controller
{
    public function workPlan(Request $request)
    {
        $member = $this->member($request);
        $member->loadMissing('consortium');

        $workplans = ConsortiumWorkplan::with([
                'reports' => fn ($query) => $query
                    ->where('think_tank_member_id', $member->id)
                    ->latest('submitted_at')
                    ->latest(),
            ])
            ->where('consortium_id', $member->consortium_id)
            ->latest('starts_on')
            ->latest()
            ->get();

        $reports = ConsortiumActivityReport::with('workplan')
            ->where('think_tank_member_id', $member->id)
            ->latest('submitted_at')
            ->latest()
            ->get();

        $stats = [
            'workplans' => $workplans->count(),
            'approved' => $workplans->where('status', 'approved')->count(),
            'planned_budget' => (float) $workplans->sum(fn (ConsortiumWorkplan $workplan) => (float) $workplan->planned_budget),
            'reports' => $reports->count(),
            'average_progress' => round((float) $reports->avg('progress_percent'), 1),
            'funds_spent' => (float) $reports->sum(fn (ConsortiumActivityReport $report) => (float) $report->funds_spent),
        ];

        return view('vendor.think-tank.work-plan', compact('member', 'workplans', 'reports', 'stats'));
    }

    public function researchReport(Request $request)
    {
        $member = $this->member($request);
        $member->loadMissing('consortium');

        $status = trim((string) $request->input('status'));
        $type = trim((string) $request->input('output_type'));
        $search = trim((string) $request->input('q'));

        $outputs = ThinkTankResearchOutput::query()
            ->where('think_tank_member_id', $member->id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('output_type', $type))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('abstract', 'like', "%{$search}%")
                        ->orWhere('external_url', 'like', "%{$search}%");
                });
            })
            ->latest('submitted_at')
            ->latest()
            ->get();

        $stats = [
            'total' => $outputs->count(),
            'submitted' => $outputs->where('status', 'submitted')->count(),
            'approved' => $outputs->where('status', 'approved')->count(),
            'revisions' => $outputs->where('status', 'revisions_requested')->count(),
            'with_files' => $outputs->whereNotNull('file_path')->count(),
        ];

        $statusOptions = ThinkTankResearchOutput::where('think_tank_member_id', $member->id)
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $typeOptions = ThinkTankResearchOutput::where('think_tank_member_id', $member->id)
            ->whereNotNull('output_type')
            ->distinct()
            ->orderBy('output_type')
            ->pluck('output_type');

        return view('vendor.think-tank.research-report', compact(
            'member',
            'outputs',
            'stats',
            'status',
            'type',
            'search',
            'statusOptions',
            'typeOptions'
        ));
    }

    public function storeResearchReport(Request $request)
    {
        $member = $this->member($request);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'output_type' => ['required', Rule::in(['research', 'policy_brief', 'working_paper', 'article', 'dataset', 'report', 'other'])],
            'published_on' => 'nullable|date',
            'abstract' => 'required|string|max:8000',
            'external_url' => 'nullable|url|max:2000',
            'document' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip|max:20480',
        ]);

        $path = $request->hasFile('document')
            ? $request->file('document')->store("think-tank-research/{$member->id}", 'local')
            : null;

        $output = ThinkTankResearchOutput::create([
            'consortium_id' => $member->consortium_id,
            'think_tank_member_id' => $member->id,
            'title' => $data['title'],
            'output_type' => $data['output_type'],
            'published_on' => $data['published_on'] ?? null,
            'status' => 'submitted',
            'abstract' => $data['abstract'],
            'file_path' => $path,
            'external_url' => $data['external_url'] ?? null,
            'submitted_by' => $request->user()?->id,
            'submitted_at' => now(),
        ]);

        if ($path && $request->hasFile('document')) {
            $file = $request->file('document');

            VendorDocument::create([
                'user_id' => $request->user()->id,
                'uploaded_by' => $request->user()->id,
                'source_type' => 'think_tank_research',
                'source_id' => $output->id,
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'document_type' => 'Research Report',
                'description' => $output->title,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size_bytes' => $file->getSize(),
                'tags' => ['think_tank', 'research_report', $output->output_type],
            ]);
        }

        return redirect()
            ->route('vendor.research-report.index')
            ->with('success', 'Research report submitted to ATTP.');
    }

    public function downloadResearchReport(Request $request, ThinkTankResearchOutput $output)
    {
        $member = $this->member($request);

        abort_unless((string) $output->think_tank_member_id === (string) $member->id, 403);
        abort_unless($output->file_path && Storage::disk('local')->exists($output->file_path), 404);

        return Storage::disk('local')->download($output->file_path, basename($output->file_path));
    }

    private function member(Request $request): ConsortiumThinkTank
    {
        $user = $request->user();
        abort_unless($user, 403);
        $this->assertUsableAccount($user);

        $member = ConsortiumThinkTank::with('consortium')
            ->where(function ($query) use ($user) {
                $query->where('vendor_user_id', $user->id)
                    ->orWhere('portal_user_id', $user->id);
            })
            ->first();

        abort_unless($member, 403, 'This account is not linked to a think tank profile.');

        return $member;
    }

    private function assertUsableAccount(User $user): void
    {
        if ($user->is_blacklisted) {
            abort(403, 'Your account has been blacklisted. Please contact the administrator.');
        }

        if ($user->is_disabled) {
            abort(403, 'Your account has been disabled. Please contact the administrator.');
        }
    }
}
