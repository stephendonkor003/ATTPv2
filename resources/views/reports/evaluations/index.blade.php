@extends('layouts.app')

@section('title', 'Evaluation Reports')

@section('content')
    @php
        $reportGroups = collect($procurementReportGroups ?? []);
        $reportMethodStats = collect($methodReportStats ?? []);
        $totalProcurements = $reportGroups->count();
        $totalReports = $reportGroups->sum(fn (array $group): int => (int) ($group['report_count'] ?? 0));
        $totalApplicants = $reportGroups->sum(fn (array $group): int => (int) ($group['applicant_count'] ?? 0));
        $totalMethodPanels = $reportGroups->sum(
            fn (array $group): int => collect($group['methods'] ?? [])->count()
        );
        $latestReportAt = $reportGroups
            ->pluck('latest_at')
            ->filter()
            ->sortByDesc(fn ($date): int => $date?->getTimestamp() ?? 0)
            ->first();
    @endphp

    <main class="nxl-container evaluation-report-library">
        <header class="erl-hero">
            <div class="erl-hero__copy">
                <span class="erl-eyebrow">Evaluation reporting</span>
                <h1>Procurement Evaluation Reports</h1>
                <p>Review consolidated outcomes, procurement summaries, and every submitted evaluator report from one structured library.</p>
            </div>
            <div class="erl-actions" aria-label="Consolidated report actions">
                <a href="{{ route('reports.evaluations.consolidated') }}" class="erl-button erl-button--light">
                    <i class="feather-file-text" aria-hidden="true"></i> View consolidated
                </a>
                <a href="{{ route('reports.evaluations.consolidated.pdf') }}" class="erl-button erl-button--primary">
                    <i class="feather-download" aria-hidden="true"></i> Download PDF
                </a>
            </div>
        </header>

        <section class="erl-overview" aria-label="Report portfolio overview">
            @foreach ([
                ['procurements', 'feather-briefcase', $totalProcurements, 'Procurements'],
                ['reports', 'feather-file-text', $totalReports, 'Submitted reports'],
                ['applicants', 'feather-users', $totalApplicants, 'Applicants represented'],
                ['methods', 'feather-layers', $totalMethodPanels, 'Evaluation workflows'],
            ] as [$tone, $icon, $value, $label])
                <article class="erl-kpi erl-kpi--{{ $tone }}">
                    <span class="erl-kpi__icon"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                    <div><strong>{{ number_format($value) }}</strong><span>{{ $label }}</span></div>
                </article>
            @endforeach
        </section>

        <section class="erl-library" aria-labelledby="reportLibraryHeading">
            <div class="erl-library__heading">
                <div>
                    <span class="erl-eyebrow">Portfolio report library</span>
                    <h2 id="reportLibraryHeading">Reports grouped by procurement</h2>
                    <p>Procurement selection methods and categories are shown separately from applicant evaluation methods.</p>
                </div>
                @if ($latestReportAt)
                    <div class="erl-latest">
                        <i class="feather-clock" aria-hidden="true"></i>
                        <span>Latest submission</span>
                        <strong>{{ $latestReportAt->format('d M Y') }}</strong>
                    </div>
                @endif
            </div>

            @if ($reportMethodStats->isNotEmpty())
                <div class="erl-method-summary" aria-label="Evaluation method report totals">
                    @foreach ($reportMethodStats as $methodStat)
                        <span>
                            <strong>{{ $methodStat['label'] }}</strong>
                            {{ number_format((int) $methodStat['procurements']) }} {{ Str::plural('procurement', (int) $methodStat['procurements']) }}
                            <b aria-hidden="true">&middot;</b>
                            {{ number_format((int) $methodStat['reports']) }} {{ Str::plural('report', (int) $methodStat['reports']) }}
                        </span>
                    @endforeach
                </div>
            @endif

            @if ($reportGroups->isNotEmpty())
                <div class="erl-toolbar" role="search" aria-label="Filter evaluation reports">
                    <label class="erl-field erl-field--search" for="evaluationReportSearch">
                        <span>Search reports</span>
                        <span class="erl-input-wrap">
                            <i class="feather-search" aria-hidden="true"></i>
                            <input id="evaluationReportSearch" type="search" autocomplete="off" placeholder="Title, reference, applicant or evaluator">
                        </span>
                    </label>
                    <label class="erl-field" for="evaluationMethodFilter">
                        <span>Evaluation method</span>
                        <select id="evaluationMethodFilter">
                            <option value="all">All methods</option>
                            @foreach ($reportMethodStats as $methodStat)
                                <option value="{{ $methodStat['type'] }}">{{ $methodStat['label'] }} ({{ number_format((int) $methodStat['procurements']) }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erl-field" for="evaluationReportSort">
                        <span>Sort procurements</span>
                        <select id="evaluationReportSort">
                            <option value="recent">Most recent</option>
                            <option value="title">Title A&ndash;Z</option>
                            <option value="reports">Most reports</option>
                        </select>
                    </label>
                    <button id="evaluationReportReset" class="erl-reset" type="button">
                        <i class="feather-rotate-ccw" aria-hidden="true"></i> Reset
                    </button>
                </div>

                <div class="erl-results-bar">
                    <p id="evaluationReportResultCount" aria-live="polite">
                        Showing {{ number_format($totalProcurements) }} {{ Str::plural('procurement', $totalProcurements) }} and
                        {{ number_format($totalMethodPanels) }} evaluation {{ Str::plural('method', $totalMethodPanels) }}
                    </p>
                    <span>Only reports in your assigned portfolio are shown.</span>
                </div>

                <div id="evaluationReportCards" class="erl-procurement-list">
                    @foreach ($reportGroups as $group)
                        @php
                            $procurement = $group['procurement'];
                            $procurementTitle = $procurement->title ?: 'Untitled procurement';
                            $methods = collect($group['methods'] ?? []);
                            $procurementMethod = trim((string) ($group['procurement_method'] ?? ''));
                            $procurementCategory = trim((string) ($group['procurement_category'] ?? ''));
                            $procurementMethodLabel = $procurementMethod !== '' ? Str::headline($procurementMethod) : 'Not specified';
                            $procurementCategoryLabel = $procurementCategory !== '' ? Str::headline($procurementCategory) : 'Not specified';
                            $status = Str::lower(trim((string) ($procurement->status ?: 'not specified')));
                            $statusTone = match (true) {
                                in_array($status, ['approved', 'awarded', 'published', 'active', 'open'], true) => 'positive',
                                in_array($status, ['submitted', 'pending', 'under review'], true) => 'warning',
                                in_array($status, ['rejected', 'cancelled', 'canceled'], true) => 'danger',
                                in_array($status, ['closed', 'archived'], true) => 'dark',
                                default => 'neutral',
                            };
                            $procurementSearch = Str::lower(collect([
                                $procurementTitle,
                                $procurement->reference_no,
                                $procurementMethod,
                                $procurementCategory,
                                $status,
                            ])->filter()->implode(' '));
                            $latestTimestamp = ($group['latest_at'] ?? null)?->getTimestamp() ?? 0;
                        @endphp

                        <article class="erl-procurement-card"
                            data-report-procurement
                            data-procurement-search="{{ $procurementSearch }}"
                            data-title="{{ Str::lower($procurementTitle) }}"
                            data-reports="{{ (int) ($group['report_count'] ?? 0) }}"
                            data-latest="{{ $latestTimestamp }}">
                            <header class="erl-procurement-head">
                                <div class="erl-procurement-head__main">
                                    <div class="erl-procurement-kicker">
                                        <span class="erl-reference"><i class="feather-hash" aria-hidden="true"></i>{{ $procurement->reference_no ?: 'Reference not provided' }}</span>
                                        <span class="erl-status erl-status--{{ $statusTone }}"><span aria-hidden="true"></span>{{ Str::headline($status) }}</span>
                                    </div>
                                    <h3><a href="{{ route('reports.evaluations.procurement', $procurement) }}">{{ $procurementTitle }}</a></h3>
                                    <dl class="erl-procurement-meta">
                                        <div><dt>Procurement method</dt><dd>{{ $procurementMethodLabel }}</dd></div>
                                        <div><dt>Procurement category</dt><dd>{{ $procurementCategoryLabel }}</dd></div>
                                    </dl>
                                </div>
                                <div class="erl-actions erl-procurement-actions" aria-label="{{ $procurementTitle }} report actions">
                                    <a href="{{ route('reports.evaluations.procurement', $procurement) }}" class="erl-button erl-button--outline">
                                        <i class="feather-eye" aria-hidden="true"></i> View report
                                    </a>
                                    <a href="{{ route('reports.evaluations.procurement.pdf', $procurement) }}" class="erl-button erl-button--soft">
                                        <i class="feather-download" aria-hidden="true"></i> PDF
                                    </a>
                                </div>
                            </header>

                            <div class="erl-procurement-stats" aria-label="Procurement report totals">
                                <div><span>Evaluation methods</span><strong>{{ number_format($methods->count()) }}</strong></div>
                                <div><span>Submitted reports</span><strong>{{ number_format((int) ($group['report_count'] ?? 0)) }}</strong></div>
                                <div>
                                    <span>Applicants evaluated</span><strong>{{ number_format((int) ($group['applicant_count'] ?? 0)) }}</strong>
                                    @if ((int) ($group['total_applicants'] ?? 0) > 0)<small>of {{ number_format((int) $group['total_applicants']) }} received</small>@endif
                                </div>
                                <div><span>Evaluators</span><strong>{{ number_format((int) ($group['evaluator_count'] ?? 0)) }}</strong></div>
                                <div><span>Latest report</span><strong class="erl-stat-date">{{ ($group['latest_at'] ?? null)?->format('d M Y') ?? 'Not submitted' }}</strong></div>
                            </div>

                            <div class="erl-methods">
                                <div class="erl-methods__heading">
                                    <div><span class="erl-eyebrow">Evaluation methods</span><p>Applicant assessments are separated by workflow.</p></div>
                                    <span>{{ number_format($methods->count()) }} configured</span>
                                </div>

                                @forelse ($methods as $method)
                                    @php
                                        $methodType = (string) ($method['type'] ?? 'unclassified');
                                        $methodTone = match ($methodType) {
                                            'eoi' => 'violet',
                                            'services' => 'blue',
                                            'goods' => 'amber',
                                            default => 'slate',
                                        };
                                        $methodIcon = match ($methodType) {
                                            'eoi' => 'feather-shield',
                                            'services' => 'feather-bar-chart-2',
                                            'goods' => 'feather-check-square',
                                            default => 'feather-layers',
                                        };
                                        $methodSubmissions = collect($method['submissions'] ?? []);
                                        $methodTemplates = collect($method['templates'] ?? [])->filter()->values();
                                        $methodPhases = collect($method['phases'] ?? [])->filter()->values();
                                        $isEoi = (bool) ($method['is_eoi'] ?? false);
                                        $eoiStats = collect($method['eoi_stats'] ?? []);
                                        $methodStatus = (string) ($method['status'] ?? 'awaiting');
                                        $methodStatusLabel = match ($methodStatus) {
                                            'ready' => 'Report ready',
                                            'in_progress' => 'In progress',
                                            default => 'Awaiting reports',
                                        };
                                        $methodViewUrl = $isEoi
                                            ? route('reports.evaluations.eoi.procurement', $procurement)
                                            : route('reports.evaluations.procurement', $procurement);
                                        $methodPdfUrl = $isEoi
                                            ? route('reports.evaluations.eoi.procurement.pdf', $procurement)
                                            : route('reports.evaluations.procurement.pdf', $procurement);
                                        $methodSearch = Str::lower(collect([
                                            $methodType,
                                            $method['label'] ?? null,
                                            $method['mode'] ?? null,
                                            $method['description'] ?? null,
                                            $methodStatusLabel,
                                        ])->merge($methodTemplates)
                                            ->merge($methodPhases)
                                            ->merge($methodSubmissions->flatMap(fn ($submission) => [
                                                $submission->applicant?->display_name,
                                                $submission->applicant?->procurement_submission_code,
                                                $submission->evaluator?->name,
                                                $submission->evaluator?->email,
                                            ]))->filter()->implode(' '));
                                    @endphp

                                    <section class="erl-method erl-method--{{ $methodTone }}"
                                        data-report-method
                                        data-method-type="{{ $methodType }}"
                                        data-method-search="{{ $methodSearch }}"
                                        aria-labelledby="method-{{ $procurement->getKey() }}-{{ Str::slug($methodType) }}">
                                        <div class="erl-method__head">
                                            <div class="erl-method-title">
                                                <span class="erl-method-icon"><i class="{{ $methodIcon }}" aria-hidden="true"></i></span>
                                                <div>
                                                    <div class="erl-method-labels">
                                                        <h4 id="method-{{ $procurement->getKey() }}-{{ Str::slug($methodType) }}">{{ $method['label'] }}</h4>
                                                        <span>{{ $method['mode'] }}</span>
                                                        <span class="erl-workflow-status erl-workflow-status--{{ $methodStatus }}">{{ $methodStatusLabel }}</span>
                                                    </div>
                                                    <p>{{ $method['description'] }}</p>
                                                </div>
                                            </div>
                                            <div class="erl-actions erl-method-actions" aria-label="{{ $method['label'] }} report actions">
                                                <a href="{{ $methodViewUrl }}" class="erl-button erl-button--outline">
                                                    <i class="feather-eye" aria-hidden="true"></i>{{ $isEoi ? 'Qualification report' : 'View report' }}
                                                </a>
                                                <a href="{{ $methodPdfUrl }}" class="erl-button erl-button--soft">
                                                    <i class="feather-download" aria-hidden="true"></i>PDF
                                                </a>
                                            </div>
                                        </div>

                                        <div class="erl-method-metrics" aria-label="{{ $method['label'] }} reporting progress">
                                            @if ($isEoi)
                                                <div><span>Total applicants</span><strong>{{ number_format((int) ($eoiStats->get('applicants') ?? $group['total_applicants'] ?? 0)) }}</strong></div>
                                                <div><span>Applicants evaluated</span><strong>{{ number_format((int) ($eoiStats->get('evaluated_applicants') ?? $method['applicant_count'] ?? 0)) }}</strong></div>
                                                <div><span>Reports filed</span><strong>{{ number_format((int) ($eoiStats->get('completed_reports') ?? $method['report_count'] ?? 0)) }}</strong></div>
                                                <div><span>Panel members</span><strong>{{ number_format((int) ($eoiStats->get('panel_members') ?? $method['evaluator_count'] ?? 0)) }}</strong></div>
                                            @else
                                                <div><span>Submitted reports</span><strong>{{ number_format((int) ($method['report_count'] ?? 0)) }}</strong></div>
                                                <div><span>Applicants</span><strong>{{ number_format((int) ($method['applicant_count'] ?? 0)) }}</strong></div>
                                                <div><span>Evaluators</span><strong>{{ number_format((int) ($method['evaluator_count'] ?? 0)) }}</strong></div>
                                                <div><span>Latest report</span><strong class="erl-stat-date">{{ ($method['latest_at'] ?? null)?->format('d M Y') ?? 'Not submitted' }}</strong></div>
                                            @endif
                                        </div>

                                        <div class="erl-method-context">
                                            <div>
                                                <span>Evaluation templates</span>
                                                <div class="erl-tag-list">
                                                    @forelse ($methodTemplates as $templateName)<span>{{ $templateName }}</span>@empty<em>No named template linked.</em>@endforelse
                                                </div>
                                            </div>
                                            @if ($methodPhases->isNotEmpty())
                                                <div>
                                                    <span>Evaluation phases</span>
                                                    <div class="erl-tag-list erl-tag-list--phase">@foreach ($methodPhases as $phase)<span>{{ $phase }}</span>@endforeach</div>
                                                </div>
                                            @endif
                                            @if ((int) ($method['assignment_count'] ?? 0) > 0)
                                                <div>
                                                    <span>Panel progress</span>
                                                    <div class="erl-assignment-progress">
                                                        <span><b>{{ number_format((int) ($method['completed_assignment_count'] ?? 0)) }}</b> of {{ number_format((int) $method['assignment_count']) }} assignments submitted</span>
                                                        <span class="erl-progress-track" aria-hidden="true"><span style="width: {{ min(100, round(((int) ($method['completed_assignment_count'] ?? 0) / max(1, (int) $method['assignment_count'])) * 100)) }}%"></span></span>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        @if ($methodSubmissions->isNotEmpty())
                                            <details class="erl-submissions">
                                                <summary>
                                                    <span><i class="feather-list" aria-hidden="true"></i>Individual submitted reports</span>
                                                    <span class="erl-submission-count">{{ number_format($methodSubmissions->count()) }} {{ Str::plural('report', $methodSubmissions->count()) }}</span>
                                                    <i class="feather-chevron-down erl-summary-chevron" aria-hidden="true"></i>
                                                </summary>
                                                <div class="erl-table-wrap">
                                                    <table class="erl-submission-table">
                                                        <caption class="visually-hidden">{{ $method['label'] }} reports for {{ $procurementTitle }}</caption>
                                                        <thead><tr><th scope="col">Applicant</th><th scope="col">Evaluator</th><th scope="col">Submitted</th><th scope="col">Result</th><th scope="col" class="erl-table-actions">Actions</th></tr></thead>
                                                        <tbody>
                                                            @foreach ($methodSubmissions as $submission)
                                                                @php
                                                                    $applicantName = $submission->applicant?->display_name
                                                                        ?? $submission->applicant?->submitter?->name
                                                                        ?? 'Applicant';
                                                                    $submissionCode = $submission->applicant?->procurement_submission_code ?? 'No submission code';
                                                                    $evaluatorName = $submission->evaluator?->name ?? 'Evaluator not available';
                                                                    $usesNumericScoring = $submission->evaluation?->usesNumericScoring() ?? false;
                                                                    $resultLabel = $usesNumericScoring
                                                                        ? ($submission->overall_score !== null ? number_format((float) $submission->overall_score, 2).' points' : 'Score not recorded')
                                                                        : ($isEoi ? 'Qualification submitted' : 'Decision submitted');
                                                                @endphp
                                                                <tr>
                                                                    <td><strong>{{ $applicantName }}</strong><span>{{ $submissionCode }}</span></td>
                                                                    <td><strong>{{ $evaluatorName }}</strong>@if ($submission->evaluator?->email)<span>{{ $submission->evaluator->email }}</span>@endif</td>
                                                                    <td><strong>{{ $submission->submitted_at?->format('d M Y') ?? 'Date unavailable' }}</strong>@if ($submission->submitted_at)<span>{{ $submission->submitted_at->format('H:i') }}</span>@endif</td>
                                                                    <td><span class="erl-result-pill erl-result-pill--{{ $usesNumericScoring ? 'score' : 'decision' }}">{{ $resultLabel }}</span></td>
                                                                    <td class="erl-table-actions">
                                                                        <a href="{{ route('reports.evaluations.submission', $submission) }}" aria-label="View report for {{ $applicantName }}"><i class="feather-eye" aria-hidden="true"></i>View</a>
                                                                        <a href="{{ route('reports.evaluations.submission.pdf', $submission) }}" aria-label="Download PDF report for {{ $applicantName }}"><i class="feather-download" aria-hidden="true"></i>PDF</a>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </details>
                                        @else
                                            <div class="erl-method-empty">
                                                <i class="feather-inbox" aria-hidden="true"></i>
                                                <div><strong>No individual reports submitted yet</strong><span>This configured evaluation method is ready for future submissions.</span></div>
                                            </div>
                                        @endif
                                    </section>
                                @empty
                                    <div class="erl-procurement-empty">
                                        <span><i class="feather-layers" aria-hidden="true"></i></span>
                                        <div><strong>No evaluation method configured</strong><p>The overall procurement report remains available, but no applicant evaluation workflow has been linked yet.</p></div>
                                    </div>
                                @endforelse
                            </div>
                        </article>
                    @endforeach
                </div>

                <div id="evaluationReportNoResults" class="erl-empty-state" hidden>
                    <span><i class="feather-search" aria-hidden="true"></i></span>
                    <h3>No reports match these filters</h3>
                    <p>Try a different keyword, choose another evaluation method, or reset the filters.</p>
                    <button type="button" data-reset-report-filters>Reset filters</button>
                </div>
            @else
                <div class="erl-empty-state erl-empty-state--initial">
                    <span><i class="feather-folder" aria-hidden="true"></i></span>
                    <h2>No procurements are available for reporting</h2>
                    <p>Procurements in your assigned portfolio will appear here as soon as they are available.</p>
                </div>
            @endif
        </section>
    </main>
@endsection

@push('styles')
    <style>
        .evaluation-report-library {
            --erl-navy: #14233b;
            --erl-ink: #182230;
            --erl-muted: #667085;
            --erl-line: #dbe3ec;
            --erl-blue: #1769aa;
            --erl-green: #087a62;
            color: var(--erl-ink);
            padding-bottom: 36px;
        }

        .erl-hero {
            align-items: center;
            background: linear-gradient(120deg, #101d31 0%, #183653 62%, #135b62 100%);
            border-radius: 14px;
            box-shadow: 0 16px 40px rgba(16, 29, 49, .14);
            display: flex;
            gap: 24px;
            justify-content: space-between;
            margin-bottom: 14px;
            overflow: hidden;
            padding: 24px 26px;
            position: relative;
        }

        .erl-hero::after {
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 50%;
            content: '';
            height: 190px;
            pointer-events: none;
            position: absolute;
            right: 22%;
            top: -115px;
            width: 190px;
        }

        .erl-hero__copy, .erl-actions { position: relative; z-index: 1; }
        .erl-eyebrow { color: #667085; display: block; font-size: 10px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
        .erl-hero .erl-eyebrow { color: #9ee9dc; }
        .erl-hero h1 { color: #fff; font-size: clamp(22px, 2.3vw, 30px); font-weight: 800; letter-spacing: -.025em; margin: 5px 0 6px; }
        .erl-hero p { color: #d5e4ec; line-height: 1.55; margin: 0; max-width: 720px; }
        .erl-actions { display: flex; flex-wrap: wrap; gap: 8px; }

        .erl-button {
            align-items: center;
            border: 1px solid transparent;
            border-radius: 8px;
            display: inline-flex;
            font-size: 11px;
            font-weight: 800;
            gap: 7px;
            justify-content: center;
            min-height: 37px;
            padding: 8px 11px;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .erl-button:hover { transform: translateY(-1px); }
        .erl-button--light { background: rgba(255, 255, 255, .11); border-color: rgba(255, 255, 255, .25); color: #fff; }
        .erl-button--light:hover { background: rgba(255, 255, 255, .18); color: #fff; }
        .erl-button--primary { background: #fff; color: #134e4a; }
        .erl-button--primary:hover { background: #ecfdf9; color: #134e4a; }
        .erl-button--outline { background: #fff; border-color: #cbd7e3; color: #344054; }
        .erl-button--outline:hover { border-color: var(--erl-blue); color: var(--erl-blue); }
        .erl-button--soft { background: #eaf7f3; border-color: #ccece2; color: var(--erl-green); }
        .erl-button--soft:hover { background: #dff3ed; color: #05604e; }

        .erl-overview { display: grid; gap: 10px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 14px; }
        .erl-kpi { align-items: center; background: #fff; border: 1px solid var(--erl-line); border-radius: 11px; box-shadow: 0 7px 18px rgba(16, 24, 40, .035); display: flex; gap: 12px; padding: 14px 15px; }
        .erl-kpi__icon { align-items: center; background: #edf4fb; border-radius: 9px; color: var(--erl-blue); display: inline-flex; flex: 0 0 40px; height: 40px; justify-content: center; }
        .erl-kpi--reports .erl-kpi__icon { background: #e9f7f2; color: var(--erl-green); }
        .erl-kpi--applicants .erl-kpi__icon { background: #f1edfd; color: #6941c6; }
        .erl-kpi--methods .erl-kpi__icon { background: #fff4e8; color: #b54708; }
        .erl-kpi strong { display: block; font-size: 22px; line-height: 1; }
        .erl-kpi div > span { color: var(--erl-muted); display: block; font-size: 11px; font-weight: 700; margin-top: 5px; }

        .erl-library { background: #fff; border: 1px solid var(--erl-line); border-radius: 14px; box-shadow: 0 12px 32px rgba(16, 24, 40, .045); padding: 20px; }
        .erl-library__heading { align-items: flex-start; display: flex; gap: 20px; justify-content: space-between; }
        .erl-library__heading h2 { font-size: 20px; font-weight: 800; letter-spacing: -.015em; margin: 4px 0; }
        .erl-library__heading p { color: var(--erl-muted); font-size: 13px; margin: 0; }
        .erl-latest { align-items: center; background: #f6f8fa; border: 1px solid #e3e8ef; border-radius: 9px; color: var(--erl-muted); display: grid; flex: 0 0 auto; gap: 0 8px; grid-template-columns: auto auto; padding: 9px 11px; }
        .erl-latest i { color: var(--erl-green); grid-row: 1 / 3; }
        .erl-latest span { font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .erl-latest strong { color: var(--erl-ink); font-size: 12px; }
        .erl-method-summary { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 14px; }
        .erl-method-summary > span { background: #f7f9fb; border: 1px solid #e2e8f0; border-radius: 999px; color: #667085; font-size: 10px; padding: 6px 9px; }
        .erl-method-summary strong { color: #344054; margin-right: 3px; }
        .erl-method-summary b { color: #b3beca; font-weight: 400; margin: 0 3px; }

        .erl-toolbar { align-items: end; background: #f5f8fa; border: 1px solid #dce5ed; border-radius: 11px; display: grid; gap: 10px; grid-template-columns: minmax(230px, 1.7fr) minmax(175px, .8fr) minmax(155px, .7fr) auto; margin-top: 16px; padding: 12px; }
        .erl-field > span:first-child { color: #475467; display: block; font-size: 9px; font-weight: 800; letter-spacing: .04em; margin-bottom: 5px; text-transform: uppercase; }
        .erl-field input, .erl-field select { background: #fff; border: 1px solid #cbd7e3; border-radius: 7px; color: var(--erl-ink); font-size: 12px; height: 40px; outline: 0; padding: 0 10px; width: 100%; }
        .erl-field input { padding-left: 34px; }
        .erl-input-wrap { display: block; position: relative; }
        .erl-input-wrap i { color: #7b8794; left: 11px; position: absolute; top: 50%; transform: translateY(-50%); }
        .erl-reset { align-items: center; background: #fff; border: 1px solid #cbd7e3; border-radius: 7px; color: #475467; display: inline-flex; font-size: 10px; font-weight: 800; gap: 6px; height: 40px; justify-content: center; padding: 0 13px; }
        .erl-results-bar { align-items: center; display: flex; gap: 16px; justify-content: space-between; padding: 11px 2px 9px; }
        .erl-results-bar p, .erl-results-bar > span { color: var(--erl-muted); font-size: 10px; margin: 0; }
        .erl-results-bar p { color: #344054; font-weight: 800; }

        .erl-field input:focus, .erl-field select:focus, .erl-reset:focus-visible, .erl-button:focus-visible,
        .erl-submissions summary:focus-visible, .erl-empty-state button:focus-visible, .erl-table-actions a:focus-visible {
            box-shadow: 0 0 0 3px rgba(23, 105, 170, .16);
            outline: 2px solid transparent;
        }

        .erl-procurement-list { display: grid; gap: 15px; }
        .erl-procurement-card { border: 1px solid #d4dee8; border-radius: 12px; box-shadow: 0 6px 18px rgba(15, 23, 42, .035); min-width: 0; overflow: hidden; }
        .erl-procurement-card[hidden], .erl-method[hidden] { display: none !important; }
        .erl-procurement-head { align-items: flex-start; display: flex; gap: 20px; justify-content: space-between; padding: 17px 18px; }
        .erl-procurement-head__main { min-width: 0; }
        .erl-procurement-kicker { align-items: center; display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 7px; }
        .erl-reference { align-items: center; color: #526273; display: inline-flex; font-size: 9px; font-weight: 800; gap: 4px; letter-spacing: .035em; text-transform: uppercase; }
        .erl-status { align-items: center; border: 1px solid #dbe3ec; border-radius: 999px; color: #526273; display: inline-flex; font-size: 8px; font-weight: 800; gap: 5px; padding: 3px 7px; text-transform: uppercase; }
        .erl-status > span { background: #98a2b3; border-radius: 50%; height: 5px; width: 5px; }
        .erl-status--positive { background: #ecfdf3; border-color: #b7ebcc; color: #087443; }
        .erl-status--positive > span { background: #12a467; }
        .erl-status--warning { background: #fffaeb; border-color: #f6df9e; color: #9b5d05; }
        .erl-status--warning > span { background: #e9a008; }
        .erl-status--danger { background: #fff1f0; border-color: #ffc9c5; color: #b42318; }
        .erl-status--danger > span { background: #d92d20; }
        .erl-status--dark { background: #eff1f4; border-color: #cdd5df; color: #344054; }
        .erl-status--dark > span { background: #475467; }
        .erl-procurement-head h3 { font-size: 17px; font-weight: 800; line-height: 1.38; margin: 0; max-width: 900px; }
        .erl-procurement-head h3 a { color: var(--erl-ink); }
        .erl-procurement-head h3 a:hover { color: var(--erl-blue); }
        .erl-procurement-meta { display: flex; flex-wrap: wrap; gap: 12px 24px; margin: 11px 0 0; }
        .erl-procurement-meta div { display: grid; gap: 2px; }
        .erl-procurement-meta dt { color: #7a8795; font-size: 8px; font-weight: 800; letter-spacing: .045em; text-transform: uppercase; }
        .erl-procurement-meta dd { color: #344054; font-size: 11px; font-weight: 700; margin: 0; }
        .erl-procurement-actions { flex: 0 0 auto; }

        .erl-procurement-stats { background: #f7f9fb; border-block: 1px solid #dce4ec; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); }
        .erl-procurement-stats > div { min-width: 0; padding: 11px 14px; }
        .erl-procurement-stats > div + div { border-left: 1px solid #dce4ec; }
        .erl-procurement-stats span, .erl-method-metrics span { color: #718096; display: block; font-size: 8px; font-weight: 800; letter-spacing: .035em; text-transform: uppercase; }
        .erl-procurement-stats strong, .erl-method-metrics strong { display: block; font-size: 16px; line-height: 1.25; margin-top: 3px; }
        .erl-procurement-stats small { color: #7a8795; display: block; font-size: 8px; }
        .erl-stat-date { font-size: 11px !important; }

        .erl-methods { background: #f4f6f9; display: grid; gap: 10px; padding: 14px; }
        .erl-methods__heading { align-items: end; display: flex; gap: 16px; justify-content: space-between; padding: 0 2px 1px; }
        .erl-methods__heading p { color: var(--erl-muted); font-size: 10px; margin: 3px 0 0; }
        .erl-methods__heading > span { color: #7a8795; font-size: 9px; font-weight: 700; }
        .erl-method { --method-accent: #64748b; --method-soft: #f1f5f9; background: #fff; border: 1px solid #dbe3ec; border-left: 3px solid var(--method-accent); border-radius: 9px; overflow: hidden; }
        .erl-method--violet { --method-accent: #6941c6; --method-soft: #f2edff; }
        .erl-method--blue { --method-accent: #1769aa; --method-soft: #eaf3fb; }
        .erl-method--amber { --method-accent: #b54708; --method-soft: #fff3e6; }
        .erl-method__head { align-items: flex-start; display: flex; gap: 18px; justify-content: space-between; padding: 14px; }
        .erl-method-title { align-items: flex-start; display: flex; gap: 10px; min-width: 0; }
        .erl-method-icon { align-items: center; background: var(--method-soft); border-radius: 8px; color: var(--method-accent); display: inline-flex; flex: 0 0 36px; height: 36px; justify-content: center; }
        .erl-method-labels { align-items: center; display: flex; flex-wrap: wrap; gap: 6px; }
        .erl-method-labels h4 { font-size: 14px; font-weight: 800; margin: 0; }
        .erl-method-labels > span { background: var(--method-soft); border-radius: 999px; color: var(--method-accent); font-size: 8px; font-weight: 800; padding: 4px 7px; text-transform: uppercase; }
        .erl-method-labels .erl-workflow-status { background: #f2f4f7; color: #667085; }
        .erl-method-labels .erl-workflow-status--ready { background: #eaf8f2; color: #087443; }
        .erl-method-labels .erl-workflow-status--in_progress { background: #fff5e8; color: #9b5d05; }
        .erl-method-title p { color: var(--erl-muted); font-size: 10px; line-height: 1.45; margin: 4px 0 0; }
        .erl-method-actions { flex: 0 0 auto; }
        .erl-method-actions .erl-button { min-height: 33px; padding: 7px 10px; }

        .erl-method-metrics { background: #fafbfc; border-block: 1px solid #e4e9ef; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .erl-method-metrics > div { padding: 9px 13px; }
        .erl-method-metrics > div + div { border-left: 1px solid #e4e9ef; }
        .erl-method-metrics strong { font-size: 14px; }
        .erl-method-context { display: grid; gap: 9px; padding: 11px 14px; }
        .erl-method-context > div { align-items: flex-start; display: grid; gap: 8px; grid-template-columns: 125px 1fr; }
        .erl-method-context > div > span { color: #667085; font-size: 8px; font-weight: 800; letter-spacing: .035em; padding-top: 4px; text-transform: uppercase; }
        .erl-tag-list { display: flex; flex-wrap: wrap; gap: 5px; }
        .erl-tag-list span { background: #eef3f7; border: 1px solid #dce5ed; border-radius: 5px; color: #344054; font-size: 9px; font-weight: 700; padding: 3px 6px; }
        .erl-tag-list--phase span { background: #eef8f5; border-color: #d4ece5; color: #08705b; }
        .erl-tag-list em { color: #8a96a3; font-size: 9px; padding-top: 3px; }
        .erl-assignment-progress { align-items: center; display: grid; gap: 8px; grid-template-columns: auto minmax(80px, 1fr); max-width: 440px; }
        .erl-assignment-progress > span:first-child { color: #667085; font-size: 9px; }
        .erl-assignment-progress b { color: #344054; }
        .erl-progress-track { background: #e6ebf0; border-radius: 999px; height: 5px; overflow: hidden; }
        .erl-progress-track > span { background: var(--method-accent); display: block; height: 100%; }

        .erl-submissions { border-top: 1px solid #e1e7ed; }
        .erl-submissions summary { align-items: center; color: #344054; cursor: pointer; display: grid; font-size: 10px; font-weight: 800; gap: 9px; grid-template-columns: 1fr auto auto; list-style: none; padding: 11px 14px; user-select: none; }
        .erl-submissions summary::-webkit-details-marker { display: none; }
        .erl-submissions summary > span:first-child { align-items: center; display: inline-flex; gap: 7px; }
        .erl-submission-count { background: var(--method-soft); border-radius: 999px; color: var(--method-accent); font-size: 8px; padding: 4px 7px; }
        .erl-summary-chevron { transition: transform .18s ease; }
        .erl-submissions[open] .erl-summary-chevron { transform: rotate(180deg); }
        .erl-table-wrap { border-top: 1px solid #e4e9ef; max-width: 100%; overflow-x: auto; }
        .erl-submission-table { border-collapse: collapse; min-width: 760px; width: 100%; }
        .erl-submission-table th { background: #f7f9fb; color: #667085; font-size: 8px; font-weight: 800; letter-spacing: .04em; padding: 8px 12px; text-align: left; text-transform: uppercase; }
        .erl-submission-table td { border-top: 1px solid #e7ecf1; color: #475467; font-size: 9px; padding: 10px 12px; vertical-align: middle; }
        .erl-submission-table tbody tr:first-child td { border-top: 0; }
        .erl-submission-table tbody tr:hover { background: #fbfcfd; }
        .erl-submission-table td strong { color: #263548; display: block; font-size: 10px; line-height: 1.35; }
        .erl-submission-table td > span:not(.erl-result-pill) { color: #7b8794; display: block; font-size: 8px; margin-top: 2px; }
        .erl-result-pill { border: 1px solid #d7e3ed; border-radius: 999px; display: inline-flex; font-size: 8px; font-weight: 800; padding: 4px 7px; white-space: nowrap; }
        .erl-result-pill--score { background: #eef6fd; border-color: #cce2f5; color: #1769aa; }
        .erl-result-pill--decision { background: #eef8f5; border-color: #cfe9e1; color: #08705b; }
        .erl-table-actions { text-align: right !important; white-space: nowrap; }
        .erl-table-actions a { align-items: center; color: var(--erl-blue); display: inline-flex; font-size: 9px; font-weight: 800; gap: 4px; margin-left: 9px; }
        .erl-table-actions a:last-child { color: var(--erl-green); }
        .erl-method-empty { align-items: center; background: #fbfcfd; border-top: 1px solid #e4e9ef; color: #8a96a3; display: flex; gap: 9px; padding: 10px 14px; }
        .erl-method-empty strong, .erl-method-empty span { display: block; }
        .erl-method-empty strong { color: #526273; font-size: 9px; }
        .erl-method-empty span { font-size: 8px; margin-top: 1px; }

        .erl-procurement-empty { align-items: center; background: #fff; border: 1px dashed #cdd8e3; border-radius: 9px; display: flex; gap: 11px; padding: 15px; }
        .erl-procurement-empty > span { align-items: center; background: #eef2f6; border-radius: 8px; color: #667085; display: inline-flex; flex: 0 0 38px; height: 38px; justify-content: center; }
        .erl-procurement-empty strong { color: #344054; font-size: 10px; }
        .erl-procurement-empty p { color: #7a8795; font-size: 9px; margin: 2px 0 0; }
        .erl-empty-state { align-items: center; background: #f7f9fb; border: 1px dashed #cad5e0; border-radius: 11px; display: flex; flex-direction: column; margin-top: 5px; padding: 34px 20px; text-align: center; }
        .erl-empty-state[hidden] { display: none !important; }
        .erl-empty-state > span { align-items: center; background: #eaf1f7; border-radius: 10px; color: var(--erl-blue); display: inline-flex; height: 44px; justify-content: center; margin-bottom: 10px; width: 44px; }
        .erl-empty-state h2, .erl-empty-state h3 { font-size: 15px; font-weight: 800; margin: 0; }
        .erl-empty-state p { color: var(--erl-muted); font-size: 10px; margin: 5px 0 0; }
        .erl-empty-state button { background: #fff; border: 1px solid #cbd7e3; border-radius: 7px; color: #344054; font-size: 9px; font-weight: 800; margin-top: 12px; padding: 7px 11px; }
        .erl-empty-state--initial { margin-top: 18px; }

        @media (max-width: 1100px) {
            .erl-overview { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .erl-toolbar { grid-template-columns: 1fr 1fr 1fr; }
            .erl-field--search { grid-column: 1 / -1; }
            .erl-reset { justify-self: start; }
            .erl-procurement-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .erl-procurement-stats > div + div { border-left: 0; }
            .erl-procurement-stats > div:not(:nth-child(3n + 1)) { border-left: 1px solid #dce4ec; }
            .erl-procurement-stats > div:nth-child(n + 4) { border-top: 1px solid #dce4ec; }
        }

        @media (max-width: 820px) {
            .erl-hero, .erl-library__heading, .erl-procurement-head, .erl-method__head { align-items: stretch; flex-direction: column; }
            .erl-procurement-actions .erl-button, .erl-method-actions .erl-button { flex: 1 1 auto; }
            .erl-method-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .erl-method-metrics > div + div { border-left: 0; }
            .erl-method-metrics > div:nth-child(even) { border-left: 1px solid #e4e9ef; }
            .erl-method-metrics > div:nth-child(n + 3) { border-top: 1px solid #e4e9ef; }
        }

        @media (max-width: 620px) {
            .erl-hero { padding: 20px; }
            .erl-hero > .erl-actions .erl-button { flex: 1 1 100%; }
            .erl-overview { grid-template-columns: 1fr 1fr; }
            .erl-library { padding: 14px; }
            .erl-toolbar { grid-template-columns: 1fr; }
            .erl-field--search { grid-column: auto; }
            .erl-reset { justify-self: stretch; }
            .erl-results-bar { align-items: flex-start; flex-direction: column; gap: 3px; }
            .erl-procurement-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .erl-procurement-stats > div:not(:nth-child(3n + 1)) { border-left: 0; }
            .erl-procurement-stats > div:nth-child(even) { border-left: 1px solid #dce4ec; }
            .erl-procurement-stats > div:nth-child(n + 3) { border-top: 1px solid #dce4ec; }
            .erl-methods { padding: 10px; }
            .erl-method-context > div { grid-template-columns: 1fr; gap: 3px; }
            .erl-methods__heading { align-items: flex-start; flex-direction: column; gap: 4px; }
        }

        @media (max-width: 430px) {
            .erl-overview { grid-template-columns: 1fr; }
            .erl-procurement-actions .erl-button, .erl-method-actions .erl-button { flex: 1 1 100%; }
            .erl-method-metrics { grid-template-columns: 1fr; }
            .erl-method-metrics > div:nth-child(even) { border-left: 0; }
            .erl-method-metrics > div + div { border-top: 1px solid #e4e9ef; }
            .erl-submissions summary { grid-template-columns: 1fr auto; }
            .erl-summary-chevron { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .erl-button, .erl-summary-chevron { transition: none; }
            .erl-button:hover { transform: none; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const library = document.getElementById('evaluationReportCards');
            const searchInput = document.getElementById('evaluationReportSearch');
            const methodFilter = document.getElementById('evaluationMethodFilter');
            const sortSelect = document.getElementById('evaluationReportSort');
            const resetButton = document.getElementById('evaluationReportReset');
            const emptyState = document.getElementById('evaluationReportNoResults');
            const resultCount = document.getElementById('evaluationReportResultCount');

            if (!library || !searchInput || !methodFilter || !sortSelect || !resultCount) return;

            const cards = Array.from(library.querySelectorAll('[data-report-procurement]'));
            const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

            const sortCards = () => {
                cards.sort((left, right) => {
                    if (sortSelect.value === 'title') return (left.dataset.title || '').localeCompare(right.dataset.title || '');
                    if (sortSelect.value === 'reports') {
                        return Number(right.dataset.reports || 0) - Number(left.dataset.reports || 0)
                            || (left.dataset.title || '').localeCompare(right.dataset.title || '');
                    }
                    return Number(right.dataset.latest || 0) - Number(left.dataset.latest || 0)
                        || (left.dataset.title || '').localeCompare(right.dataset.title || '');
                });
                cards.forEach((card) => library.appendChild(card));
            };

            const applyFilters = () => {
                const term = normalize(searchInput.value);
                const selectedMethod = methodFilter.value;
                let visibleProcurements = 0;
                let visibleMethods = 0;

                cards.forEach((card) => {
                    const procurementMatches = !term || normalize(card.dataset.procurementSearch).includes(term);
                    const methods = Array.from(card.querySelectorAll('[data-report-method]'));
                    let cardMethodMatches = 0;

                    methods.forEach((method) => {
                        const matchesType = selectedMethod === 'all' || method.dataset.methodType === selectedMethod;
                        const matchesTerm = !term || procurementMatches || normalize(method.dataset.methodSearch).includes(term);
                        const isVisible = matchesType && matchesTerm;
                        method.hidden = !isVisible;
                        cardMethodMatches += isVisible ? 1 : 0;
                    });

                    const cardMatches = methods.length
                        ? cardMethodMatches > 0
                        : selectedMethod === 'all' && procurementMatches;
                    card.hidden = !cardMatches;
                    visibleProcurements += cardMatches ? 1 : 0;
                    visibleMethods += cardMatches ? cardMethodMatches : 0;
                });

                resultCount.textContent = `Showing ${visibleProcurements} ${visibleProcurements === 1 ? 'procurement' : 'procurements'} and ${visibleMethods} evaluation ${visibleMethods === 1 ? 'method' : 'methods'}`;
                if (emptyState) emptyState.hidden = visibleProcurements !== 0;
            };

            const resetFilters = () => {
                searchInput.value = '';
                methodFilter.value = 'all';
                sortSelect.value = 'recent';
                sortCards();
                applyFilters();
                searchInput.focus();
            };

            searchInput.addEventListener('input', applyFilters);
            methodFilter.addEventListener('change', applyFilters);
            sortSelect.addEventListener('change', () => { sortCards(); applyFilters(); });
            resetButton?.addEventListener('click', resetFilters);
            document.querySelectorAll('[data-reset-report-filters]').forEach((button) => button.addEventListener('click', resetFilters));
            sortCards();
            applyFilters();
        });
    </script>
@endpush
