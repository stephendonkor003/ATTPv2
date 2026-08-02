<?php

namespace App\Http\Controllers;

use App\Models\MeIndicatorAchievement;
use App\Models\MeIndicatorAchievementDisaggregation;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportIndicatorResult;
use App\Models\MeRepositoryDocumentLink;
use App\Models\MeRepositoryDocumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeIndicatorAchievementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.data_entry.manage|me.configuration.manage|think_tank.me.reports.manage');
    }

    public function store(
        Request $request,
        MePerformanceReport $report,
        MePerformanceReportIndicatorResult $reportResult
    ): RedirectResponse {
        $this->assertEditableReport($request, $report);
        $this->assertReportResult($report, $reportResult);
        $validated = $this->validateAchievement($request, $report);

        $achievement = DB::transaction(function () use ($request, $report, $reportResult, $validated): MeIndicatorAchievement {
            return MeIndicatorAchievement::query()->create([
                'report_id' => $report->id,
                'report_indicator_result_id' => $reportResult->id,
                'indicator_id' => $reportResult->indicator_id,
                'indicator_result_id' => $reportResult->indicator_result_id,
                'achievement_code' => $this->nextAchievementCode($report),
                ...$this->achievementAttributes($validated, $request),
                'lead_think_tank_member_id' => $validated['lead_think_tank_member_id']
                    ?? $report->think_tank_member_id,
                'verification_status' => 'draft',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()
            ->with('success', 'Achievement '.$achievement->achievement_code.' added. Add beneficiary combinations and supporting evidence below.');
    }

    public function update(
        Request $request,
        MePerformanceReport $report,
        MeIndicatorAchievement $achievement
    ): RedirectResponse {
        $this->assertEditableReport($request, $report);
        $this->assertAchievement($report, $achievement);
        $validated = $this->validateAchievement($request, $report);

        $achievement->update([
            ...$this->achievementAttributes($validated, $request),
            'lead_think_tank_member_id' => $validated['lead_think_tank_member_id']
                ?? $report->think_tank_member_id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Achievement details updated.');
    }

    public function destroy(
        Request $request,
        MePerformanceReport $report,
        MeIndicatorAchievement $achievement
    ): RedirectResponse {
        $this->assertEditableReport($request, $report);
        $this->assertAchievement($report, $achievement);

        $achievement->documentLinks()->delete();
        $achievement->delete();

        return back()->with('success', 'Achievement removed from the draft report. Repository documents were retained.');
    }

    public function storeBreakdown(
        Request $request,
        MePerformanceReport $report,
        MeIndicatorAchievement $achievement
    ): RedirectResponse {
        $this->assertEditableReport($request, $report);
        $this->assertAchievement($report, $achievement);

        $validated = $request->validate([
            'geographic_scope' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::GEOGRAPHIC_SCOPES))],
            'country' => ['nullable', 'string', 'max:120'],
            'rec' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::RECS))],
            'implementing_institution_type' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::INSTITUTION_TYPES))],
            'implementing_institution' => ['nullable', 'string', 'max:255'],
            'priority_theme' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::PRIORITY_THEMES))],
            'gender' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::GENDERS))],
            'age_group' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::AGE_GROUPS))],
            'stakeholder_category' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::STAKEHOLDER_CATEGORIES))],
            'beneficiary_count' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $this->assertRequiredDimensions($achievement, $validated);
        $hash = MeIndicatorAchievementDisaggregation::combinationHash($validated);
        if ($achievement->breakdowns()->where('combination_hash', $hash)->exists()) {
            throw ValidationException::withMessages([
                'beneficiary_count' => 'This exact disaggregation combination already exists. Edit the reporting design or remove the duplicate row.',
            ]);
        }

        DB::transaction(function () use ($achievement, $validated, $hash): void {
            $achievement->breakdowns()->create($validated + ['combination_hash' => $hash]);
            $achievement->recalculateBeneficiaries();
        });

        return back()->with('success', 'Beneficiary combination added and the achievement total recalculated.');
    }

    public function destroyBreakdown(
        Request $request,
        MePerformanceReport $report,
        MeIndicatorAchievement $achievement,
        MeIndicatorAchievementDisaggregation $breakdown
    ): RedirectResponse {
        $this->assertEditableReport($request, $report);
        $this->assertAchievement($report, $achievement);
        abort_unless((string) $breakdown->achievement_id === (string) $achievement->id, 404);

        DB::transaction(function () use ($achievement, $breakdown): void {
            $breakdown->delete();
            $achievement->recalculateBeneficiaries();
        });

        return back()->with('success', 'Beneficiary combination removed and the total recalculated.');
    }

    public function storeDocument(
        Request $request,
        MePerformanceReport $report,
        MeIndicatorAchievement $achievement
    ): RedirectResponse {
        $this->assertEditableReport($request, $report);
        $this->assertAchievement($report, $achievement);
        $validated = $request->validate([
            'document_title' => ['required', 'string', 'max:255'],
            'document_description' => ['nullable', 'string', 'max:5000'],
            'evidence_file' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,zip',
            ],
        ]);

        $file = $request->file('evidence_file');
        $checksum = hash_file('sha256', $file->getRealPath());
        $existing = MeKnowledgeEvidenceItem::query()
            ->where('portfolio_id', $report->portfolio_id)
            ->where('checksum_sha256', $checksum)
            ->whereNull('retired_at')
            ->first();

        if ($existing) {
            $this->linkDocument($existing, $achievement, (string) $request->user()->id);

            return back()->with('success', 'An identical file already existed. The existing repository document was linked without creating a duplicate.');
        }

        $path = $file->store('me/knowledge-evidence/achievements/'.$achievement->id, 'local');
        try {
            DB::transaction(function () use ($request, $report, $achievement, $validated, $file, $path, $checksum): void {
                $item = MeKnowledgeEvidenceItem::query()->create([
                    'portfolio_id' => $report->portfolio_id,
                    'title' => trim((string) $validated['document_title']),
                    'document_type' => 'supporting_evidence',
                    'repository_category' => 'evidence',
                    'description' => $validated['document_description'] ?? null,
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'checksum_sha256' => $checksum,
                    'version_number' => 1,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
                MeRepositoryDocumentVersion::query()->create([
                    'repository_item_id' => $item->id,
                    'version_number' => 1,
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'checksum_sha256' => $checksum,
                    'change_notes' => 'Initial evidence upload from '.$achievement->achievement_code.'.',
                    'uploaded_by' => $request->user()->id,
                ]);
                $this->linkDocument($item, $achievement, (string) $request->user()->id);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Evidence uploaded once and synchronized with the Evidence Repository.');
    }

    public function unlinkDocument(
        Request $request,
        MePerformanceReport $report,
        MeIndicatorAchievement $achievement,
        MeRepositoryDocumentLink $link
    ): RedirectResponse {
        $this->assertEditableReport($request, $report);
        $this->assertAchievement($report, $achievement);
        abort_unless(
            (string) $link->linkable_id === (string) $achievement->id
                && $link->linkable_type === MeIndicatorAchievement::class,
            404
        );
        $link->delete();

        return back()->with('success', 'Evidence unlinked from this achievement. The repository copy and audit history were retained.');
    }

    /** @return array<string, mixed> */
    private function validateAchievement(Request $request, MePerformanceReport $report): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'achieved_on' => ['required', 'date'],
            'geographic_scope' => ['required', Rule::in(array_keys(MeIndicatorAchievement::GEOGRAPHIC_SCOPES))],
            'country' => ['nullable', 'string', 'max:120'],
            'rec' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::RECS))],
            'location' => ['nullable', 'string', 'max:255'],
            'lead_think_tank_member_id' => ['nullable', 'uuid', Rule::exists('attp_consortium_think_tanks', 'id')->where('status', 'active')],
            'collaborating_institutions' => ['nullable', 'string', 'max:5000'],
            'priority_themes' => ['required', 'array', 'min:1'],
            'priority_themes.*' => ['required', 'distinct', Rule::in(array_keys(MeIndicatorAchievement::PRIORITY_THEMES))],
        ]);

        $achievedOn = Carbon::parse($validated['achieved_on'])->startOfDay();
        $period = $report->reportingPeriod;
        if ($period && (! $achievedOn->betweenIncluded($period->period_start, $period->period_end))) {
            throw ValidationException::withMessages([
                'achieved_on' => 'Achievement date must fall inside '.$report->periodLabel().'.',
            ]);
        }
        if (in_array($validated['geographic_scope'], ['country', 'national'], true) && blank($validated['country'] ?? null)) {
            throw ValidationException::withMessages(['country' => 'Select or enter the country for this geographic scope.']);
        }
        if ($validated['geographic_scope'] === 'rec' && blank($validated['rec'] ?? null)) {
            throw ValidationException::withMessages(['rec' => 'Select the Regional Economic Community.']);
        }

        return $validated;
    }

    /** @param array<string, mixed> $validated
     *  @return array<string, mixed>
     */
    private function achievementAttributes(array $validated, Request $request): array
    {
        $collaborators = preg_split('/[\r\n,;]+/', (string) ($validated['collaborating_institutions'] ?? '')) ?: [];

        return [
            'title' => trim((string) $validated['title']),
            'description' => trim((string) $validated['description']),
            'achieved_on' => $validated['achieved_on'],
            'geographic_scope' => $validated['geographic_scope'],
            'country' => filled($validated['country'] ?? null) ? trim((string) $validated['country']) : null,
            'rec' => $validated['rec'] ?? null,
            'location' => filled($validated['location'] ?? null) ? trim((string) $validated['location']) : null,
            'collaborating_institutions' => collect($collaborators)->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all(),
            'priority_themes' => array_values($validated['priority_themes']),
        ];
    }

    /** @param array<string, mixed> $values */
    private function assertRequiredDimensions(MeIndicatorAchievement $achievement, array $values): void
    {
        $requirements = $achievement->indicator
            ->disaggregationRequirements()
            ->with('dimension:id,code,name')
            ->where('is_required', true)
            ->get();
        $fieldMap = [
            'geographic_scope' => 'geographic_scope',
            'country' => 'country',
            'rec' => 'rec',
            'implementing_institution_type' => 'implementing_institution_type',
            'priority_theme' => 'priority_theme',
            'gender' => 'gender',
            'age_group' => 'age_group',
            'stakeholder_category' => 'stakeholder_category',
        ];
        $errors = [];
        foreach ($requirements as $requirement) {
            $field = $fieldMap[$requirement->dimension?->code] ?? null;
            if ($field && blank($values[$field] ?? null)) {
                $errors[$field] = $requirement->dimension->name.' is required for this indicator.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function nextAchievementCode(MePerformanceReport $report): string
    {
        $prefix = 'ACH-'.$report->reporting_year.'-'.Str::upper((string) ($report->reporting_period_label ?: $report->reporting_quarter));
        do {
            $code = $prefix.'-'.Str::upper(Str::random(6));
        } while (MeIndicatorAchievement::query()->where('achievement_code', $code)->exists());

        return $code;
    }

    private function linkDocument(MeKnowledgeEvidenceItem $item, MeIndicatorAchievement $achievement, string $userId): void
    {
        MeRepositoryDocumentLink::query()->firstOrCreate([
            'repository_item_id' => $item->id,
            'linkable_type' => MeIndicatorAchievement::class,
            'linkable_id' => $achievement->id,
            'purpose' => 'supporting_evidence',
        ], ['linked_by' => $userId]);
    }

    private function assertEditableReport(Request $request, MePerformanceReport $report): void
    {
        abort_unless($report->isEditable(), 422, 'Only draft reports can be changed.');
        $user = $request->user();
        $allowed = $user->isAdmin() || $user->isSuperAdmin();
        if ($report->think_tank_member_id) {
            $allowed = $allowed || ($user->isThinkTankUser()
                && (string) $user->resolvedThinkTankMembership()?->id === (string) $report->think_tank_member_id
                && $user->can('think_tank.me.reports.manage'));
        } else {
            $allowed = $allowed || ((string) $report->created_by === (string) $user->id
                && ($user->can('me.data_entry.manage') || $user->can('me.configuration.manage')));
        }
        abort_unless($allowed, 403, 'You are not allowed to change this report.');
    }

    private function assertReportResult(MePerformanceReport $report, MePerformanceReportIndicatorResult $reportResult): void
    {
        abort_unless((string) $reportResult->report_id === (string) $report->id, 404);
    }

    private function assertAchievement(MePerformanceReport $report, MeIndicatorAchievement $achievement): void
    {
        abort_unless((string) $achievement->report_id === (string) $report->id, 404);
    }
}
