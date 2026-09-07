<?php

namespace App\Support;

class EvaluationManagementExportData
{
    public static function sheets(array $management): array
    {
        $sheets = [];
        $sheets['1 Summary overview'] = [['Field', 'Value']];
        foreach ($management['overview'] as $key => $value) {
            $sheets['1 Summary overview'][] = [str_replace('_', ' ', $key), $value];
        }
        $sheets['1 Summary overview'][] = ['Generated at', $management['generated_at']];
        $sheets['1 Summary overview'][] = ['Evaluator', 'Email', 'Assigned tasks', 'Submitted reports', 'First submission', 'Last submission', 'Calendar elapsed', 'Active time'];
        foreach ($management['evaluators'] as $row) {
            $sheets['1 Summary overview'][] = array_map(fn ($key) => $row[$key] ?? null, ['name', 'email', 'assigned', 'completed', 'first_submission', 'last_submission', 'elapsed', 'active_time']);
        }
        $sheets['2 Results and rankings'] = [['Form', 'Phase / round', 'Rank', 'Applicant', 'Code', 'Panel score (%)', 'Raw mean', 'Maximum', 'Submitted', 'Expected', 'Status', 'Spread (pp)']];
        foreach ($management['groups'] as $group) {
            foreach ($group['rankings'] as $row) {
                $sheets['2 Results and rankings'][] = [$group['title'], $group['phase'], $row['rank'], $row['name'], $row['code'], $row['score'], $row['raw_score'], $row['max_score'], $row['completed'], $row['expected'], $row['status'], $row['spread']];
            }
        }
        $sheets['3 Detailed scoring'] = [['Form', 'Section', 'Rank', 'Applicant', 'Code', 'Section mean', 'Section maximum', 'Percentage', 'Evaluator count', 'Expected', 'Status']];
        foreach ($management['sections'] as $section) {
            foreach ($section['rankings'] as $row) {
                $sheets['3 Detailed scoring'][] = [$section['evaluation'], $section['title'], $row['rank'], $row['name'], $row['code'], $row['score'], $section['max_score'], $row['percentage'], $row['count'], $row['expected'], $row['status']];
            }
        }
        $sheets['3 Detailed scoring'][] = ['Form', 'Phase / round', 'Applicant', 'Code', 'Evaluator', 'Section', 'Criterion', 'Value', 'Maximum', 'Criterion rationale', 'Overall comment', 'Submitted at'];
        foreach ($management['details'] as $entry) {
            foreach ($entry['criteria'] as $criterion) {
                $sheets['3 Detailed scoring'][] = [$entry['evaluation'], $entry['phase'], $entry['applicant'], $entry['code'], $entry['evaluator'], $criterion['section'], $criterion['criterion'], $criterion['value'], $criterion['max'], $criterion['comment'], $entry['comments'], $entry['submitted_at']];
            }
        }
        $sheets['3 Detailed scoring'][] = ['Form', 'Phase / round', 'Applicant', 'Code', 'Evaluator', 'Section', 'Strengths', 'Weaknesses'];
        foreach ($management['details'] as $entry) {
            foreach ($entry['section_feedback'] ?? [] as $feedback) {
                $sheets['3 Detailed scoring'][] = [$entry['evaluation'], $entry['phase'], $entry['applicant'], $entry['code'], $entry['evaluator'], $feedback['section'], $feedback['strengths'], $feedback['weaknesses']];
            }
        }
        $sheets['4 Panel consistency'] = [['Form', 'Applicant', 'Code', 'Evaluators', 'Minimum (%)', 'Maximum (%)', 'Spread (pp)', 'Population standard deviation (pp)']];
        foreach ($management['consistency'] as $row) {
            $sheets['4 Panel consistency'][] = array_map(fn ($key) => $row[$key] ?? null, ['evaluation', 'applicant', 'code', 'evaluators', 'minimum', 'maximum', 'spread', 'std_dev']);
        }
        foreach ($management['insights'] as $row) {
            $sheets['4 Panel consistency'][] = [$row['title'], $row['body']];
        }
        $sheets['5 Governance and audit'] = [['Type', 'Statement']];
        foreach ($management['methodology'] as $value) {
            $sheets['5 Governance and audit'][] = ['Methodology', $value];
        }
        foreach ($management['actions'] as $value) {
            $sheets['5 Governance and audit'][] = ['Next step', $value];
        }
        $sheets['5 Governance and audit'][] = ['Submission ID', 'Form / phase', 'Applicant', 'Code', 'Evaluator', 'Submitted at', 'Revision'];
        foreach ($management['audit'] as $row) {
            $sheets['5 Governance and audit'][] = array_map(fn ($key) => $row[$key] ?? null, ['id', 'evaluation', 'applicant', 'code', 'evaluator', 'submitted_at', 'revision']);
        }

        return array_map(fn ($rows) => array_map(fn ($row) => array_map([self::class, 'safe'], $row), $rows), $sheets);
    }

    public static function charts(array $management): array
    {
        return [
            '1 Summary overview' => $management['overview_charts'],
            '2 Results and rankings' => array_merge([], ...array_column($management['groups'], 'charts')),
            '3 Detailed scoring' => array_merge([], ...array_column($management['sections'], 'charts')),
        ];
    }

    public static function csv(array $management): array
    {
        $rows = [];
        foreach (self::sheets($management) as $section => $sectionRows) {
            foreach ($sectionRows as $row) {
                $rows[] = array_pad([$section, ...$row], 13, '');
            }
        }

        return [['Section', 'Field 1', 'Field 2', 'Field 3', 'Field 4', 'Field 5', 'Field 6', 'Field 7', 'Field 8', 'Field 9', 'Field 10', 'Field 11', 'Field 12'], $rows];
    }

    public static function safe($value)
    {
        if (is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value)) {
            return "'".$value;
        }

        return $value ?? 'Not recorded';
    }
}
