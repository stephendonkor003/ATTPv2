<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\Procurement;
use App\Support\EvaluationReportCharts;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Read-only management reporting; no scores, assignments or decisions are changed. */
class EvaluationManagementReportService
{
    public function forProcurement(Procurement $procurement, ?string $method = null, array $evaluatorColors = []): array
    {
        $assignments = EvaluationAssignment::with(['evaluation.sections.criteria', 'evaluator', 'technicalProposalRound'])
            ->where('procurement_id', $procurement->id)
            ->when($method, fn ($q) => $q->whereHas('evaluation', fn ($e) => $e->where('type', $method)))->get();
        $submissions = EvaluationSubmission::with(['evaluation.sections.criteria', 'assignment', 'technicalProposalCandidate', 'applicant.submitter', 'applicant.values', 'criteriaScores.criteria.section', 'sectionScores.section', 'evaluator'])
            ->where('procurement_id', $procurement->id)->whereNotNull('submitted_at')
            ->when($method, fn ($q) => $q->whereHas('evaluation', fn ($e) => $e->where('type', $method)))->get();
        // EOI reports retain the active panel boundary used by the qualification resolver.
        $submissions = $submissions->filter(fn ($s) => ! $s->evaluation?->isEoi() || $assignments->contains(fn ($a) => (string) $a->evaluation_id === (string) $s->evaluation_id && (string) $a->user_id === (string) $s->evaluator_id
            && (! $a->form_submission_id || (string) $a->form_submission_id === (string) $s->form_submission_id)));
        $management = $this->build($procurement, $submissions, $assignments, null, false, $evaluatorColors);
        if ($method === Evaluation::TYPE_EOI || ($method === null && $assignments->contains(fn ($a) => $a->evaluation?->isEoi()))) {
            $report = app(EoiQualificationService::class)->buildProcurementReport($procurement);
            $rows = collect($report['applicants'])->sortBy(fn ($r) => $r['qualification_rank'] ?? PHP_INT_MAX)->map(fn ($row) => [
                'rank' => $row['qualification_rank'] ?? null, 'name' => $row['applicant']->display_name,
                'code' => $row['applicant']->procurement_submission_code, 'score' => null, 'raw_score' => null, 'max_score' => null,
                'completed' => $row['completed_tasks'], 'expected' => $row['expected_tasks'],
                'status' => $row['outcome']['label'].' | '.($row['progression']['label'] ?? ($row['panel_complete'] ? 'Panel complete' : 'Awaiting panel')),
                'spread' => null,
            ])->values()->all();
            array_unshift($management['groups'], ['title' => 'Authoritative EOI qualification and shortlist', 'phase' => 'Current active panel', 'numeric' => false, 'qualification_ranking' => true, 'rankings' => $rows, 'charts' => []]);
            $management['methodology'][] = 'EOI qualification positions and shortlist progression come directly from the existing qualification resolver. These are categorical qualification positions, not numeric merit scores.';
        }

        return $management;
    }

    public function forSubmission(EvaluationSubmission $submission, bool $anonymised = false): array
    {
        $submission->loadMissing(['procurement', 'evaluation.sections.criteria', 'assignment', 'applicant.submitter', 'applicant.values', 'criteriaScores.criteria.section', 'sectionScores.section', 'evaluator']);
        $assignments = EvaluationAssignment::with(['evaluation.sections.criteria', 'evaluator', 'technicalProposalRound'])
            ->where('procurement_id', $submission->procurement_id)->where('evaluation_id', $submission->evaluation_id)
            ->where('user_id', $submission->evaluator_id)->get();
        $assignments = $assignments->filter(fn ($assignment) => $this->groupKey($assignment) === $this->groupKey($submission));
        $management = $this->build($submission->procurement, collect([$submission]), $assignments, [(string) $submission->form_submission_id], $anonymised);
        $management['methodology'][] = 'This is an individual evaluator record. Procurement-wide and section rankings are not assigned to an individual report.';
        foreach ($management['groups'] as &$group) {
            foreach ($group['rankings'] as &$row) {
                $row['rank'] = null;
            }
        }
        unset($group, $row);
        foreach ($management['sections'] as &$section) {
            foreach ($section['rankings'] as &$row) {
                $row['rank'] = null;
            }
        }
        unset($section, $row);
        if (! $anonymised) {
            return $management;
        }

        $private = array_filter([$submission->applicant?->display_name, $submission->applicant?->procurement_submission_code, $submission->applicant?->submitter?->email]);
        $redact = function ($value) use (&$redact, $private) {
            if (is_array($value)) {
                return array_map($redact, $value);
            }
            if (! is_string($value)) {
                return $value;
            }
            if (str_starts_with($value, 'data:image/svg+xml;base64,')) {
                $xml = base64_decode(substr($value, strlen('data:image/svg+xml;base64,')));
                foreach ($private as $identifier) {
                    $xml = str_replace([$identifier, htmlspecialchars($identifier, ENT_QUOTES | ENT_XML1, 'UTF-8')], 'Applicant XXX', $xml);
                }

                return 'data:image/svg+xml;base64,'.base64_encode($xml);
            }

            return str_replace($private, 'Applicant XXX', $value);
        };
        $management = $redact($management);
        foreach ($management['details'] as &$entry) {
            $entry['applicant'] = 'Applicant XXX';
            $entry['code'] = 'Redacted';
            $entry['comments'] = 'Withheld in anonymised report';
            foreach ($entry['criteria'] as &$criterion) {
                $criterion['comment'] = 'Withheld in anonymised report';
            }
            foreach ($entry['section_feedback'] as &$feedback) {
                $feedback['strengths'] = 'Withheld in anonymised report';
                $feedback['weaknesses'] = 'Withheld in anonymised report';
            }
            unset($feedback);
        }
        unset($entry, $criterion);
        $management['methodology'][] = 'ANONYMISED: applicant identity and narrative comments are withheld.';

        return $management;
    }

    public function consolidated(Collection $submissions): array
    {
        $procurements = $submissions->pluck('procurement')->filter()->unique('id');
        $people = EvaluationAssignment::with('evaluator')->whereIn('procurement_id', $procurements->pluck('id'))->get()->pluck('evaluator')->merge($submissions->pluck('evaluator'))->filter()->unique('id')->sortBy('name')->values();
        $evaluatorColors = $this->colorsFor($people);
        $parts = $procurements->map(fn ($procurement) => [
            'procurement' => $procurement, 'report' => $this->forProcurement($procurement, null, $evaluatorColors),
        ])->values();
        $result = ['generated_at' => now()->format('d M Y H:i').' '.config('app.timezone'), 'overview' => [], 'evaluators' => [], 'overview_charts' => [], 'groups' => [], 'sections' => [], 'details' => [], 'consistency' => [], 'insights' => [], 'methodology' => [], 'actions' => [], 'audit' => []];
        foreach ($parts as $part) {
            $report = $part['report'];
            $prefix = ($part['procurement']->reference_no ?: $part['procurement']->title).' · ';
            foreach (['groups', 'sections', 'details', 'consistency', 'audit'] as $key) {
                foreach ($report[$key] as $row) {
                    $field = $key === 'groups' ? 'title' : 'evaluation';
                    $row[$field] = $prefix.$row[$field];
                    $result[$key][] = $row;
                }
            }
            foreach ($report['insights'] as $insight) {
                $insight['title'] = $prefix.$insight['title'];
                $result['insights'][] = $insight;
            }
            $result['evaluators'] = array_merge($result['evaluators'], $report['evaluators']);
            $result['methodology'] = array_values(array_unique(array_merge($result['methodology'], $report['methodology'])));
            $result['actions'] = array_values(array_unique(array_merge($result['actions'], $report['actions'])));
        }
        $result['evaluators'] = collect($result['evaluators'])->groupBy('email')->map(function ($rows) {
            $row = $rows->first();
            $row['assigned'] = $rows->sum('assigned');
            $row['completed'] = $rows->sum('completed');
            $row['elapsed'] = 'See procurement timelines';
            foreach (['first_submission', 'last_submission'] as $field) {
                $dates = $rows->pluck($field)->filter(fn ($date) => $date !== 'Not recorded')->map(fn ($date) => Carbon::parse($date));
                $row[$field] = $this->date($field === 'first_submission' ? $dates->min() : $dates->max());
            }

            return $row;
        })->values()->all();
        foreach (['total_applicants', 'evaluated_applicants', 'submitted_reports', 'template_count'] as $key) {
            $result['overview'][$key] = $parts->sum(fn ($part) => $part['report']['overview'][$key]);
        }
        $result['overview'] += ['evaluator_count' => count($result['evaluators']), 'start' => 'See procurement timelines', 'end' => 'See procurement timelines', 'elapsed' => 'Separate procurement timelines', 'active_time' => 'Not recorded', 'timing_note' => 'Calendar windows across procurements may overlap. They are not summed as working time. Each form and procurement retains its own rankings.'];
        $result['overview_charts'] = [EvaluationReportCharts::pie('Applicant coverage across procurements', [['name' => 'With finalized evaluations', 'value' => $result['overview']['evaluated_applicants'], 'color' => '#009E73'], ['name' => 'Awaiting evaluations', 'value' => max(0, $result['overview']['total_applicants'] - $result['overview']['evaluated_applicants']), 'color' => '#0072B2']])];

        return $result;
    }

    public function build(Procurement $procurement, Collection $submissions, Collection $assignments, ?array $applicantScope = null, bool $anonymised = false, array $evaluatorColors = []): array
    {
        $submissions = new \Illuminate\Database\Eloquent\Collection($submissions->all());
        $assignments = new \Illuminate\Database\Eloquent\Collection($assignments->all());
        $submissions->loadMissing(['evaluation.sections.criteria', 'assignment', 'technicalProposalCandidate', 'applicant.submitter', 'applicant.values', 'criteriaScores.criteria.section', 'sectionScores.section', 'evaluator']);
        $assignments->loadMissing(['evaluation.sections.criteria', 'evaluator', 'technicalProposalRound']);
        $submissions = $submissions->filter(fn ($s) => $s->submitted_at && $s->evaluation)
            ->sortByDesc(fn ($s) => $s->submitted_at->format('Y-m-d H:i:s.u').'|'.str_pad((string) $s->revision_number, 8, '0', STR_PAD_LEFT).'|'.$s->id)
            ->unique(fn ($s) => $this->groupKey($s).'|'.$s->form_submission_id.'|'.$s->evaluator_id)->values();
        $palette = ['#0072B2', '#D55E00', '#009E73', '#CC79A7', '#6D4C41', '#7057A3', '#00838F', '#A46700', '#8E245D', '#455A64', '#4E7A16', '#6157D9', '#B53333', '#2D8076', '#926CBA', '#9A631B'];
        $people = $assignments->pluck('evaluator')->merge($submissions->pluck('evaluator'))->filter()->unique('id')->sortBy('name')->values();
        $colors = array_replace($this->colorsFor($people), $evaluatorColors);
        $contexts = [];
        foreach ($assignments as $assignment) {
            $contexts[$this->groupKey($assignment)] = $assignment->evaluation;
        }
        foreach ($submissions as $submission) {
            $contexts[$this->groupKey($submission)] = $submission->evaluation;
        }
        $targets = [];
        $unavailableTargetContexts = [];
        foreach ($assignments as $assignment) {
            try {
                $targets[(string) $assignment->id] = app(EvaluationAssignmentTargetResolver::class)->targetsForAssignment($assignment)->keyBy('id');
            } catch (ValidationException $exception) {
                // Historical reports must survive a round becoming unavailable
                // for new work. Its submitted records still form a report roster.
                $targets[(string) $assignment->id] = collect();
                $unavailableTargetContexts[$this->groupKey($assignment)] = true;
            }
        }
        $groups = $sections = $details = $consistency = $audit = [];
        $warnings = 0;
        $expectedTasks = [];
        foreach ($contexts as $key => $evaluation) {
            if (! $evaluation) {
                continue;
            }
            $entries = $submissions->filter(fn ($s) => $this->groupKey($s) === $key)->values();
            $panel = $assignments->filter(fn ($a) => $this->groupKey($a) === $key)->values();
            $roster = collect();
            foreach ($panel as $assignment) {
                $roster = $roster->merge($targets[(string) $assignment->id]->values());
            }
            $roster = $roster->merge($entries->pluck('applicant'))->filter()->unique('id')
                ->when($applicantScope !== null, fn ($rows) => $rows->whereIn('id', $applicantScope))
                ->sortBy(fn ($a) => $a->display_name)->values();
            $numeric = $evaluation->usesNumericScoring();
            $criteria = $evaluation->sections->flatMap->criteria->unique('id')->values();
            $maximum = (float) $criteria->sum('max_score');
            $phase = Str::headline($evaluation->evaluation_phase ?: 'Evaluation');
            $round = $panel->first()?->technicalProposalRound;
            if ($round) {
                $phase .= ' · Proposal round '.$round->round_number;
                if (! in_array($round->status, ['published', 'closed'], true)) {
                    $phase .= ' ('.Str::headline($round->status ?: 'Unavailable').')';
                }
            } elseif (str_contains($key, '|technical_proposal|')) {
                $phase .= ' · Technical proposal';
            }
            $title = $evaluation->name;
            $rankings = [];
            foreach ($roster as $applicant) {
                $records = $entries->where('form_submission_id', $applicant->id);
                $expected = $panel->filter(fn ($a) => ! $a->form_submission_id || (string) $a->form_submission_id === (string) $applicant->id)->pluck('user_id')->unique();
                foreach ($expected as $evaluatorId) {
                    $expectedTasks[$key.'|'.$applicant->id.'|'.$evaluatorId] = (string) $evaluatorId;
                }
                $scores = $records->map(fn ($s) => $this->score($s, $criteria))->filter(fn ($v) => $v !== null);
                $complete = $expected->isNotEmpty() && $expected->diff($records->pluck('evaluator_id'))->isEmpty();
                if (! $numeric) {
                    $complete = $complete && $criteria->isNotEmpty() && $records->every(fn ($record) => $this->hasCompleteDecisions($record, $criteria));
                }
                $valid = $numeric && $maximum > 0 && $scores->count() === $records->count() && $records->isNotEmpty();
                if ($numeric && $records->count() > $scores->count()) {
                    $warnings += $records->count() - $scores->count();
                }
                $mean = $valid ? round((float) $scores->avg(), 2) : null;
                $outcome = $numeric ? ($complete && $valid ? 'Panel complete' : ($records->isEmpty() ? 'Awaiting submitted evaluations' : ($valid ? 'Panel incomplete' : 'Score validation required'))) : ($complete ? '' : 'Panel incomplete | ').$this->decisions($records->flatMap->criteriaScores, $evaluation);
                $rankings[] = ['rank' => null, 'name' => $anonymised ? 'Applicant XXX' : $applicant->display_name, 'code' => $anonymised ? 'Redacted' : ($applicant->procurement_submission_code ?: 'Applicant'),
                    'score' => $mean !== null ? round($mean / $maximum * 100, 2) : null, 'raw_score' => $mean, 'max_score' => $numeric ? $maximum : null,
                    'completed' => $records->count(), 'expected' => $expected->count(), 'status' => $outcome, 'spread' => $scores->isNotEmpty() && $maximum > 0 ? round(((float) $scores->max() - (float) $scores->min()) / $maximum * 100, 2) : null,
                    '_eligible' => $complete && $valid, '_complete' => $complete && (! $numeric || $valid), '_id' => (string) $applicant->id];
                if ($valid) {
                    $percentages = $scores->map(fn ($v) => $v / $maximum * 100);
                    $avg = $percentages->avg();
                    $consistency[] = ['applicant' => $anonymised ? 'Applicant XXX' : $applicant->display_name, 'code' => $anonymised ? 'Redacted' : $applicant->procurement_submission_code,
                        'evaluation' => $title.' · '.$phase, 'evaluators' => $scores->count(), 'minimum' => round($percentages->min(), 2), 'maximum' => round($percentages->max(), 2),
                        'spread' => round($percentages->max() - $percentages->min(), 2), 'std_dev' => $scores->count() > 1 ? round(sqrt($percentages->sum(fn ($v) => ($v - $avg) ** 2) / $scores->count()), 2) : null];
                }
            }
            $rankings = $numeric ? $this->rank($rankings, 'score') : $rankings;
            $labels = array_column($rankings, 'name');
            $orderedIds = array_column($rankings, '_id');
            $series = [];
            foreach ($people as $person) {
                if (! $panel->contains('user_id', $person->id) && ! $entries->contains('evaluator_id', $person->id)) {
                    continue;
                }
                $values = array_map(function ($id) use ($entries, $person, $criteria, $maximum, $numeric) {
                    $entry = $entries->first(fn ($s) => (string) $s->form_submission_id === $id && (string) $s->evaluator_id === (string) $person->id);
                    $score = $entry && $numeric ? $this->score($entry, $criteria) : null;

                    return $score !== null && $maximum > 0 ? round($score / $maximum * 100, 2) : null;
                }, $orderedIds);
                $series[] = ['name' => $person->name, 'color' => $colors[(string) $person->id], 'values' => $values];
            }
            $charts = [];
            if ($numeric && collect($series)->flatMap(fn ($s) => $s['values'])->contains(fn ($v) => $v !== null)) {
                $charts = array_merge(EvaluationReportCharts::grouped('Evaluator scores by applicant', $labels, $series), EvaluationReportCharts::grouped('Evaluator score profiles by applicant', $labels, $series, 'line'));
            }
            $charts[] = EvaluationReportCharts::pie('Panel completion · '.$title, [
                ['name' => 'Panel complete', 'value' => collect($rankings)->where('_complete', true)->count(), 'color' => '#009E73'],
                ['name' => 'Panel incomplete', 'value' => count($rankings) - collect($rankings)->where('_complete', true)->count(), 'color' => '#0072B2'],
            ]);
            if (! $numeric && $entries->isNotEmpty()) {
                $decisionScores = $entries->flatMap->criteriaScores;
                $decisionLabels = $evaluation->isGoods() ? [1 => 'Yes', 0 => 'No'] : [2 => 'Qualified', 1 => 'Average qualified', 0 => 'Not qualified'];
                $slices = [];
                foreach ($decisionLabels as $value => $label) {
                    $slices[] = ['name' => $label, 'value' => $decisionScores->filter(fn ($s) => $s->decision !== null && (int) $s->decision === $value)->count(), 'color' => $palette[count($slices)]];
                }
                $charts[] = EvaluationReportCharts::pie('Recorded criterion decisions · '.$title, $slices);
            }
            $groups[] = ['title' => $title, 'phase' => $phase, 'numeric' => $numeric, 'rankings' => $rankings, 'charts' => $charts];

            foreach ($evaluation->sections->sortBy('sort_order') as $section) {
                // A parent section includes descendants once; leaf criteria keep their own identities.
                $sectionIds = collect([$section->id]);
                do {
                    $before = $sectionIds->count();
                    $sectionIds = $sectionIds->merge($evaluation->sections->whereIn('parent_section_id', $sectionIds)->pluck('id'))->unique()->values();
                } while ($before !== $sectionIds->count());
                $sectionCriteria = $criteria->whereIn('evaluation_section_id', $sectionIds);
                if ($sectionCriteria->isEmpty()) {
                    continue;
                }
                $sectionMaximum = (float) $sectionCriteria->sum('max_score');
                $sectionRows = [];
                $sectionSeries = [];
                foreach ($rankings as $row) {
                    $records = $entries->where('form_submission_id', $row['_id']);
                    $scores = $records->map(fn ($s) => $this->score($s, $sectionCriteria, false))->filter(fn ($v) => $v !== null);
                    $mean = $numeric && $scores->isNotEmpty() ? round($scores->avg(), 2) : null;
                    $sectionRows[] = ['name' => $row['name'], 'code' => $row['code'], 'rank' => null, 'score' => $mean,
                        'percentage' => $mean !== null && $sectionMaximum > 0 ? round($mean / $sectionMaximum * 100, 2) : null,
                        'count' => $numeric ? $scores->count() : $records->count(), 'expected' => $row['expected'],
                        'status' => $numeric ? ($scores->count() < $row['expected'] ? 'Incomplete panel' : $row['status']) : $this->decisions($records->flatMap->criteriaScores->whereIn('evaluation_criteria_id', $sectionCriteria->pluck('id')), $evaluation),
                        '_eligible' => $row['_eligible'] && $scores->count() === $records->count()];
                }
                if ($numeric) {
                    foreach ($people as $person) {
                        $values = array_map(function ($id) use ($entries, $person, $sectionCriteria) {
                            $entry = $entries->first(fn ($s) => (string) $s->form_submission_id === $id && (string) $s->evaluator_id === (string) $person->id);

                            return $entry ? $this->score($entry, $sectionCriteria, false) : null;
                        }, $orderedIds);
                        if (collect($values)->contains(fn ($v) => $v !== null)) {
                            $sectionSeries[] = ['name' => $person->name, 'color' => $colors[(string) $person->id], 'values' => $values];
                        }
                    }
                }
                $sections[] = ['title' => $section->name, 'evaluation' => $title.' · '.$phase, 'max_score' => $numeric ? $sectionMaximum : null,
                    'rankings' => $numeric ? $this->rank($sectionRows, 'score') : $sectionRows,
                    'charts' => $sectionSeries ? EvaluationReportCharts::grouped($section->name.' · evaluator comparison', $labels, $sectionSeries, 'bar', max(1, $sectionMaximum), 'Section score (points)') : []];
            }
            foreach ($entries as $entry) {
                $scoreRows = $entry->criteriaScores->keyBy('evaluation_criteria_id');
                $criterionRows = $criteria->map(function ($criterion) use ($scoreRows, $evaluation, $numeric) {
                    $score = $scoreRows->get($criterion->id);

                    return ['section' => $criterion->section?->name ?: 'Section', 'criterion' => $criterion->name,
                        'value' => $numeric ? $score?->score : $this->decision($score?->decision, $evaluation), 'max' => $numeric ? $criterion->max_score : null, 'comment' => $score?->comment ?: 'No comment recorded'];
                })->all();
                $value = $numeric ? $this->score($entry, $criteria) : null;
                $details[] = ['applicant' => $entry->applicant?->display_name ?: 'Applicant unavailable', 'code' => $entry->applicant?->procurement_submission_code,
                    'evaluation' => $title, 'phase' => $phase, 'evaluator' => $entry->evaluator?->name ?: 'Evaluator unavailable',
                    'result' => $numeric ? ($value !== null ? number_format($value, 2).' / '.number_format($maximum, 2) : 'Score validation required') : $this->decisions($entry->criteriaScores, $evaluation),
                    'submitted_at' => $this->date($entry->submitted_at), 'comments' => $entry->comments ?: 'No overall comment recorded', 'criteria' => $criterionRows,
                    'section_feedback' => $entry->sectionScores->map(fn ($row) => ['section' => $row->section?->name ?: 'Section', 'strengths' => $row->strengths ?: 'Not recorded', 'weaknesses' => $row->weaknesses ?: 'Not recorded'])->all()];
                $audit[] = ['applicant' => $entry->applicant?->display_name, 'code' => $entry->applicant?->procurement_submission_code,
                    'evaluator' => $entry->evaluator?->name, 'evaluation' => $title.' · '.$phase, 'submitted_at' => $this->date($entry->submitted_at), 'revision' => $entry->revision_number ?? 1, 'id' => $entry->id];
            }
        }
        $start = $assignments->pluck('assigned_at')->filter()->min();
        $last = $submissions->pluck('submitted_at')->filter()->max();
        $evaluators = $people->map(function ($person) use ($assignments, $submissions, $expectedTasks, $colors) {
            $records = $submissions->where('evaluator_id', $person->id);
            $firstAssignment = $assignments->where('user_id', $person->id)->pluck('assigned_at')->filter()->min();

            return ['name' => $person->name, 'email' => $person->email, 'assigned' => count(array_filter($expectedTasks, fn ($id) => $id === (string) $person->id)), 'completed' => $records->count(),
                'first_submission' => $this->date($records->min('submitted_at')), 'last_submission' => $this->date($records->max('submitted_at')),
                'elapsed' => $this->elapsed($firstAssignment, $records->max('submitted_at')), 'active_time' => 'Not recorded', 'color' => $colors[(string) $person->id]];
        })->all();
        $reviewed = $submissions->pluck('form_submission_id')->unique()->count();
        $total = $applicantScope !== null ? count($applicantScope) : $procurement->submissions()->count();
        $insights = [
            ['title' => 'Evaluation coverage', 'body' => $reviewed.' of '.$total.' procurement applicants have at least one finalized evaluation in this report. Assigned targets and submitted records determine each panel roster.', 'tone' => 'info'],
            ['title' => 'Timing evidence', 'body' => 'Active working time is not captured by the current system. Calendar turnaround includes waiting, breaks and concurrent assignments; it is not a measure of evaluator effort.', 'tone' => 'info'],
        ];
        if ($unavailableTargetContexts) {
            $insights[] = ['title' => 'Current eligibility unavailable', 'body' => count($unavailableTargetContexts).' evaluation contexts have no available current eligibility roster. Finalized historical records remain visible under their original form and proposal round; no current eligibility is inferred. Review the round status before assigning further evaluations.', 'tone' => 'warning'];
        }
        if ($warnings) {
            $insights[] = ['title' => 'Score validation required', 'body' => $warnings.' finalized records contain missing criteria, out-of-range values or totals that do not reconcile. They are displayed in detail and withheld from ranking until reviewed.', 'tone' => 'warning'];
        }
        $largest = collect($consistency)->sortByDesc('spread')->first();
        if ($largest && $largest['evaluators'] > 1) {
            $insights[] = ['title' => 'Largest recorded panel spread', 'body' => $largest['applicant'].' has a '.$largest['spread'].' percentage-point spread in '.$largest['evaluation'].'. Review the recorded rationale and criterion-level differences; spread alone does not establish an error or bias.', 'tone' => 'info'];
        }
        if ($submissions->isEmpty()) {
            $insights[] = ['title' => 'Results are not yet available', 'body' => 'No finalized evaluations exist for this report. Draft scores are excluded. Coverage charts show the current workflow; merit rankings and scoring graphs become available after submission.', 'tone' => 'warning'];
        }

        return ['generated_at' => now()->format('d M Y H:i').' '.config('app.timezone'),
            'overview' => ['total_applicants' => $total, 'evaluated_applicants' => $reviewed, 'evaluator_count' => $people->count(), 'submitted_reports' => $submissions->count(), 'template_count' => collect($contexts)->filter()->unique('id')->count(), 'start' => $this->date($start), 'end' => $this->date($last), 'elapsed' => $this->elapsed($start, $last), 'active_time' => 'Not recorded', 'timing_note' => 'Duration runs from earliest recorded assignment to latest finalized submission. It is elapsed calendar time, not active working time. An unfinished evaluation has no completion duration.'],
            'evaluators' => $evaluators, 'overview_charts' => [EvaluationReportCharts::pie('Procurement applicant coverage', [['name' => 'Evaluated in this report', 'value' => $reviewed, 'color' => '#009E73'], ['name' => 'No finalized evaluation in this report', 'value' => max(0, $total - $reviewed), 'color' => '#0072B2']])],
            'groups' => $groups, 'sections' => $sections, 'details' => $details, 'consistency' => $consistency, 'insights' => $insights, 'audit' => $audit,
            'methodology' => ['Only finalized submissions are included. The latest revision per applicant, evaluator, form, stage and proposal round is used.', 'Numeric scores use the configured criterion maxima. Panel means give equal weight to each evaluator; technical and financial forms and proposal rounds remain separate.', 'Rankings require all assigned evaluators and complete, reconciled numeric criterion records. Tied means share competition rank; incomplete applicants remain unranked.', 'Section ranks compare the same form section. Parent sections include their descendants once. No overall score is fabricated from categorical Yes/No or qualification decisions.', 'Bar and line charts place applicants on the horizontal axis and scores on the vertical axis. Lines connect applicant comparisons, not time trends. Missing values remain missing; colours identify evaluators.', 'Pie charts show applicant coverage, panel completion or criterion decision counts. They do not represent probability of award.', 'This is an internal management evaluation report. Applicable solicitation criteria and approved procurement procedures govern decisions; report formatting is not certification of regulatory compliance.'],
            'actions' => ['Complete outstanding assigned evaluations before relying on panel rankings.', 'Review missing scores, reconciliation warnings and material evaluator differences against the recorded comments and supporting evidence.', 'Document moderation and decision rationale through the authorized evaluation workflow; retain original evaluator records.', 'Confirm applicable technical thresholds, financial evaluation and required approvals before recommending an award.', 'Retain this dated report and its source submission references as part of the procurement audit record.']];
    }

    private function colorsFor(Collection $people): array
    {
        $palette = ['#0072B2', '#D55E00', '#009E73', '#CC79A7', '#6D4C41', '#7057A3', '#00838F', '#A46700', '#8E245D', '#455A64', '#4E7A16', '#6157D9', '#B53333', '#2D8076', '#926CBA', '#9A631B'];
        $colors = [];
        foreach ($people->values() as $index => $person) {
            $color = $palette[$index] ?? null;
            $salt = 0;
            while ($color === null || in_array($color, $colors, true)) {
                $hash = hash('sha256', $person->id.'|'.$salt++);
                $color = sprintf('#%02X%02X%02X', 40 + hexdec(substr($hash, 0, 2)) % 141, 40 + hexdec(substr($hash, 2, 2)) % 141, 40 + hexdec(substr($hash, 4, 2)) % 141);
            }
            $colors[(string) $person->id] = $color;
        }

        return $colors;
    }

    private function groupKey($row): string
    {
        if ($row instanceof EvaluationAssignment) {
            return $row->evaluation_id.'|'.$row->workflowStage().'|'.($row->technical_proposal_round_id ?: 'application');
        }

        return $row->evaluation_id.'|'.($row->isTechnicalProposalEvaluation() ? 'technical_proposal' : 'application').'|'.($row->assignment?->technical_proposal_round_id ?: $row->technicalProposalCandidate?->round_id ?: 'application');
    }

    private function hasCompleteDecisions(EvaluationSubmission $submission, Collection $criteria): bool
    {
        $scores = $submission->criteriaScores->keyBy('evaluation_criteria_id');
        $allowed = $submission->evaluation?->isGoods() ? [0, 1] : [0, 1, 2];

        return $criteria->every(function ($criterion) use ($scores, $allowed) {
            $value = $scores->get($criterion->id)?->decision;

            return $value !== null && in_array((int) $value, $allowed, true);
        });
    }

    private function score(EvaluationSubmission $submission, Collection $criteria, bool $checkTotal = true): ?float
    {
        if (! $submission->evaluation?->usesNumericScoring() || $criteria->isEmpty() || (float) $criteria->sum('max_score') <= 0) {
            return null;
        }
        $scores = $submission->criteriaScores->keyBy('evaluation_criteria_id');
        $sum = 0.0;
        foreach ($criteria as $criterion) {
            $value = $scores->get($criterion->id)?->score;
            if ($value === null || ! is_finite((float) $value) || $value < 0 || $value > (float) $criterion->max_score + 0.004) {
                return null;
            }
            $sum += (float) $value;
        }
        if ($checkTotal && ($submission->overall_score === null || abs($sum - $submission->overall_score) >= 0.005)) {
            return null;
        }

        return round($sum, 2);
    }

    private function rank(array $rows, string $metric): array
    {
        $rows = collect($rows)->sort(fn ($a, $b) => ($b['_eligible'] <=> $a['_eligible']) ?: (($b[$metric] ?? -1) <=> ($a[$metric] ?? -1)) ?: strcmp($a['name'], $b['name']))->values()->all();
        $position = $rank = 0;
        $previous = null;
        foreach ($rows as &$row) {
            if (! $row['_eligible'] || $row[$metric] === null) {
                continue;
            }
            $position++;
            if ($previous === null || abs($previous - $row[$metric]) >= 0.005) {
                $rank = $position;
            }
            $row['rank'] = $rank;
            $previous = $row[$metric];
        }

        return $rows;
    }

    private function decision($value, Evaluation $evaluation): string
    {
        if ($value === null) {
            return 'Not recorded';
        }

        return ($evaluation->isGoods() ? [1 => 'Yes', 0 => 'No'] : [2 => 'Qualified', 1 => 'Average qualified', 0 => 'Not qualified'])[(int) $value] ?? 'Not recorded';
    }

    private function decisions(Collection $scores, Evaluation $evaluation): string
    {
        $counts = $scores->filter(fn ($s) => $s->decision !== null)->countBy(fn ($s) => $this->decision($s->decision, $evaluation));

        return $counts->isEmpty() ? 'Awaiting submitted decisions' : $counts->map(fn ($count, $label) => $count.' '.$label)->implode(' / ');
    }

    private function date($date): string
    {
        return $date ? Carbon::parse($date)->format('d M Y H:i') : 'Not recorded';
    }

    private function elapsed($start, $end): string
    {
        if (! $start || ! $end) {
            return 'Not recorded';
        }
        $seconds = Carbon::parse($start)->diffInSeconds(Carbon::parse($end), false);
        if ($seconds < 0) {
            return 'Timestamp sequence requires review';
        }

        return (int) floor($seconds / 86400).'d '.(int) floor($seconds % 86400 / 3600).'h '.(int) floor($seconds % 3600 / 60).'m';
    }
}
