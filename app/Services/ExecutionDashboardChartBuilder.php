<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ExecutionDashboardChartBuilder
{
    private const WIDTH = 760;
    private const HEIGHT = 300;

    public function build(
        Collection|array $rows,
        array $totals,
        array $radarMetrics,
        string $currency = 'USD'
    ): array {
        return $this->buildFromDataset(
            $this->dataset($rows, $totals, $radarMetrics),
            $currency
        );
    }

    /**
     * Build the single canonical chart dataset consumed by both Chart.js on
     * the webpage and the server-rendered SVG charts in the PDF.
     */
    public function dataset(
        Collection|array $rows,
        array $totals,
        array $radarMetrics,
        Collection|array $componentBreakdownRows = []
    ): array {
        $rows = collect($rows)->values();
        $componentBreakdownRows = collect($componentBreakdownRows)
            ->map(fn ($row) => [
                'component_id' => $row['component_id'] ?? null,
                'sub_component_id' => $row['sub_component_id'] ?? null,
                'level' => $row['level'] ?? 'component',
                'label' => (string) ($row['label'] ?? ''),
                'allocation' => round((float) ($row['allocation'] ?? 0), 2),
                'commitment' => round((float) ($row['commitment'] ?? 0), 2),
                'disbursement' => round((float) ($row['disbursement'] ?? 0), 2),
                'remaining' => round((float) ($row['remaining'] ?? 0), 2),
            ])
            ->values()
            ->all();
        $labels = $rows->pluck('year')->map(fn ($year) => (string) $year)->all();
        $allocations = $rows->pluck('allocation')->map(fn ($value) => (float) $value)->all();
        $commitments = $rows->pluck('commitment')->map(fn ($value) => (float) $value)->all();
        $disbursements = $rows->pluck('disbursement')->map(fn ($value) => (float) $value)->all();

        $cumulativeAllocation = $this->runningTotals($allocations);
        $cumulativeCommitment = $this->runningTotals($commitments);
        $cumulativeDisbursement = $this->runningTotals($disbursements);
        $cumulativeRemaining = array_map(
            fn ($allocation, $commitment) => round($allocation - $commitment, 2),
            $cumulativeAllocation,
            $cumulativeCommitment
        );
        $cumulativeExecutionRates = $this->rates($cumulativeCommitment, $cumulativeAllocation);
        $cumulativeDisbursementRates = $this->rates($cumulativeDisbursement, $cumulativeAllocation);

        $totalAllocation = (float) ($totals['allocation'] ?? 0);
        $totalCommitment = (float) ($totals['commitment'] ?? 0);
        $totalDisbursement = (float) ($totals['disbursement'] ?? 0);
        $mix = [
            [
                'label' => 'Disbursed',
                'value' => max($totalDisbursement, 0),
                'color' => '#168a5b',
            ],
            [
                'label' => 'Unpaid Commitments',
                'value' => max($totalCommitment - $totalDisbursement, 0),
                'color' => '#d65a31',
            ],
            [
                'label' => 'Remaining Global Commitments',
                'value' => max($totalAllocation - $totalCommitment, 0),
                'color' => '#2563eb',
            ],
        ];

        $qualityLabels = ['Commitment Rate', 'Timeliness', 'Consistency', 'Coverage', 'Risk Control'];
        $qualityValues = [
            (float) ($radarMetrics['budget_utilization'] ?? 0),
            (float) ($radarMetrics['timeliness'] ?? 0),
            (float) ($radarMetrics['consistency'] ?? 0),
            (float) ($radarMetrics['coverage'] ?? 0),
            (float) ($radarMetrics['risk_exposure'] ?? 0),
        ];

        $dataset = [
            'labels' => $labels,
            'allocations' => $allocations,
            'commitments' => $commitments,
            'disbursements' => $disbursements,
            'cumulative_allocation' => $cumulativeAllocation,
            'cumulative_commitment' => $cumulativeCommitment,
            'cumulative_disbursement' => $cumulativeDisbursement,
            'cumulative_remaining' => $cumulativeRemaining,
            'cumulative_execution_rates' => $cumulativeExecutionRates,
            'cumulative_disbursement_rates' => $cumulativeDisbursementRates,
            'mix' => $mix,
            'quality_labels' => $qualityLabels,
            'quality_values' => $qualityValues,
            'component_breakdown' => $componentBreakdownRows,
        ];
        $dataset['snapshot_hash'] = hash(
            'sha256',
            json_encode($dataset, JSON_PRESERVE_ZERO_FRACTION)
        );

        return $dataset;
    }

    public function buildFromDataset(array $dataset, string $currency = 'USD'): array
    {
        $labels = (array) ($dataset['labels'] ?? []);
        $cumulativeAllocation = (array) ($dataset['cumulative_allocation'] ?? []);
        $cumulativeCommitment = (array) ($dataset['cumulative_commitment'] ?? []);
        $cumulativeDisbursement = (array) ($dataset['cumulative_disbursement'] ?? []);
        $cumulativeRemaining = (array) ($dataset['cumulative_remaining'] ?? []);
        $cumulativeExecutionRates = (array) ($dataset['cumulative_execution_rates'] ?? []);
        $cumulativeDisbursementRates = (array) ($dataset['cumulative_disbursement_rates'] ?? []);
        $mix = (array) ($dataset['mix'] ?? []);
        $qualityLabels = (array) ($dataset['quality_labels'] ?? []);
        $qualityValues = (array) ($dataset['quality_values'] ?? []);

        return [
            'global_trend' => $this->dataUri($this->lineChart($labels, [
                ['label' => 'Global Commitments', 'values' => $cumulativeAllocation, 'color' => '#2563eb'],
                ['label' => 'Planned Commitments', 'values' => $cumulativeCommitment, 'color' => '#b7791f'],
                ['label' => 'Disbursements', 'values' => $cumulativeDisbursement, 'color' => '#168a5b'],
            ])),
            'execution_mix' => $this->dataUri($this->doughnutChart($mix, $currency)),
            'rate_movement' => $this->dataUri($this->lineChart($labels, [
                ['label' => 'Commitment Rate', 'values' => $cumulativeExecutionRates, 'color' => '#0f766e'],
                ['label' => 'Disbursement Rate', 'values' => $cumulativeDisbursementRates, 'color' => '#6d5bd0'],
            ], true)),
            'cumulative_momentum' => $this->dataUri($this->lineChart($labels, [
                ['label' => 'Global Commitments', 'values' => $cumulativeAllocation, 'color' => '#2563eb'],
                ['label' => 'Planned Commitments', 'values' => $cumulativeCommitment, 'color' => '#b7791f'],
                ['label' => 'Disbursements', 'values' => $cumulativeDisbursement, 'color' => '#168a5b'],
            ])),
            'financial_profile' => $this->dataUri($this->groupedBarChart($labels, [
                ['label' => 'Global Commitments', 'values' => $cumulativeAllocation, 'color' => '#2563eb'],
                ['label' => 'Planned Commitments', 'values' => $cumulativeCommitment, 'color' => '#b7791f'],
                ['label' => 'Disbursements', 'values' => $cumulativeDisbursement, 'color' => '#168a5b'],
            ])),
            'variance_control' => $this->dataUri(
                $this->horizontalBarChart($labels, $cumulativeRemaining)
            ),
            'quality_radar' => $this->dataUri(
                $this->radarChart($qualityLabels, $qualityValues)
            ),
            'exposure_concentration' => $this->dataUri($this->bubbleChart(
                $labels,
                $cumulativeExecutionRates,
                $cumulativeCommitment,
                $cumulativeRemaining
            )),
        ];
    }

    private function runningTotals(array $values): array
    {
        $total = 0.0;

        return array_map(function ($value) use (&$total) {
            $total += (float) $value;

            return round($total, 2);
        }, $values);
    }

    private function rates(array $numerators, array $denominators): array
    {
        return array_map(
            fn ($numerator, $denominator) => $denominator > 0
                ? round(((float) $numerator / (float) $denominator) * 100, 2)
                : 0.0,
            $numerators,
            $denominators
        );
    }

    private function lineChart(array $labels, array $series, bool $percent = false): string
    {
        $left = 66;
        $right = 20;
        $top = 48;
        $bottom = 42;
        $plotWidth = self::WIDTH - $left - $right;
        $plotHeight = self::HEIGHT - $top - $bottom;
        $allValues = collect($series)->flatMap(fn ($item) => $item['values']);
        $maxValue = $percent
            ? max(100, (float) $allValues->max())
            : $this->niceMaximum((float) $allValues->max());

        $svg = $this->svgStart();
        $svg .= $this->grid($left, $top, $plotWidth, $plotHeight, $maxValue, $percent);
        $svg .= $this->xLabels($labels, $left, $top + $plotHeight, $plotWidth);

        $legendX = $left;
        foreach ($series as $item) {
            $color = $item['color'];
            $label = $this->escape($item['label']);
            $svg .= "<circle cx=\"{$legendX}\" cy=\"18\" r=\"5\" fill=\"{$color}\"/>";
            $svg .= "<text x=\"" . ($legendX + 9) . "\" y=\"22\" class=\"legend\">{$label}</text>";
            $legendX += max(135, strlen($item['label']) * 6 + 28);

            $points = [];
            foreach ($item['values'] as $index => $value) {
                $x = $this->pointX($index, count($labels), $left, $plotWidth);
                $y = $top + $plotHeight - (((float) $value / $maxValue) * $plotHeight);
                $points[] = round($x, 2) . ',' . round($y, 2);
            }
            if ($points !== []) {
                $svg .= '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="' . $color . '" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>';
                foreach ($points as $point) {
                    [$x, $y] = explode(',', $point);
                    $svg .= "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"3.5\" fill=\"#ffffff\" stroke=\"{$color}\" stroke-width=\"2\"/>";
                }
            }
        }

        return $svg . $this->svgEnd();
    }

    private function groupedBarChart(array $labels, array $series): string
    {
        $left = 66;
        $right = 20;
        $top = 48;
        $bottom = 42;
        $plotWidth = self::WIDTH - $left - $right;
        $plotHeight = self::HEIGHT - $top - $bottom;
        $maxValue = $this->niceMaximum(
            (float) collect($series)->flatMap(fn ($item) => $item['values'])->max()
        );

        $svg = $this->svgStart();
        $svg .= $this->grid($left, $top, $plotWidth, $plotHeight, $maxValue, false);
        $svg .= $this->xLabels($labels, $left, $top + $plotHeight, $plotWidth);

        $legendX = $left;
        foreach ($series as $item) {
            $svg .= "<rect x=\"{$legendX}\" y=\"13\" width=\"10\" height=\"10\" rx=\"2\" fill=\"{$item['color']}\"/>";
            $svg .= '<text x="' . ($legendX + 15) . '" y="22" class="legend">' . $this->escape($item['label']) . '</text>';
            $legendX += max(135, strlen($item['label']) * 6 + 30);
        }

        $groupWidth = $plotWidth / max(1, count($labels));
        $barWidth = min(22, ($groupWidth * 0.72) / max(1, count($series)));
        foreach ($labels as $index => $label) {
            $groupStart = $left + ($index * $groupWidth) + (($groupWidth - ($barWidth * count($series))) / 2);
            foreach ($series as $seriesIndex => $item) {
                $value = (float) ($item['values'][$index] ?? 0);
                $height = ($value / $maxValue) * $plotHeight;
                $x = $groupStart + ($seriesIndex * $barWidth);
                $y = $top + $plotHeight - $height;
                $svg .= sprintf(
                    '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="2" fill="%s"/>',
                    $x,
                    $y,
                    max(2, $barWidth - 2),
                    max(0, $height),
                    $item['color']
                );
            }
        }

        return $svg . $this->svgEnd();
    }

    private function horizontalBarChart(array $labels, array $values): string
    {
        $left = 92;
        $right = 76;
        $top = 26;
        $bottom = 24;
        $plotWidth = self::WIDTH - $left - $right;
        $plotHeight = self::HEIGHT - $top - $bottom;
        $minimum = min(0, (float) collect($values)->min());
        $maximum = max(1, (float) collect($values)->max());
        $range = max(1, $maximum - $minimum);
        $zeroX = $left + ((0 - $minimum) / $range) * $plotWidth;
        $rowHeight = $plotHeight / max(1, count($labels));

        $svg = $this->svgStart();
        $svg .= "<line x1=\"{$zeroX}\" y1=\"{$top}\" x2=\"{$zeroX}\" y2=\"" . ($top + $plotHeight) . "\" stroke=\"#94a3b8\" stroke-width=\"1\"/>";

        foreach ($labels as $index => $label) {
            $value = (float) ($values[$index] ?? 0);
            $valueX = $left + (($value - $minimum) / $range) * $plotWidth;
            $y = $top + ($index * $rowHeight) + ($rowHeight * 0.2);
            $height = $rowHeight * 0.58;
            $x = min($zeroX, $valueX);
            $width = max(1, abs($valueX - $zeroX));
            $color = $value >= 0 ? '#0f766e' : '#d65a31';
            $svg .= '<text x="' . ($left - 10) . '" y="' . ($y + $height * 0.72) . '" text-anchor="end" class="axis">' . $this->escape($label) . '</text>';
            $svg .= sprintf(
                '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="5" fill="%s"/>',
                $x,
                $y,
                $width,
                $height,
                $color
            );
            $textX = $value >= 0
                ? min(self::WIDTH - 5, $valueX + 7)
                : max($left + 7, $valueX + 7);
            $anchor = 'start';
            $svg .= '<text x="' . $textX . '" y="' . ($y + $height * 0.72) . "\" text-anchor=\"{$anchor}\" class=\"value-label\">" . $this->escape($this->formatAxis($value)) . '</text>';
        }

        return $svg . $this->svgEnd();
    }

    private function doughnutChart(array $segments, string $currency): string
    {
        $cx = 180;
        $cy = 150;
        $radius = 82;
        $circumference = 2 * M_PI * $radius;
        $total = max(1, (float) collect($segments)->sum('value'));
        $offset = 0.0;
        $svg = $this->svgStart();
        $svg .= "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$radius}\" fill=\"none\" stroke=\"#e2e8f0\" stroke-width=\"34\"/>";

        foreach ($segments as $segment) {
            $length = ((float) $segment['value'] / $total) * $circumference;
            $gap = max(0, $circumference - $length);
            $svg .= sprintf(
                '<circle cx="%d" cy="%d" r="%d" fill="none" stroke="%s" stroke-width="34" stroke-dasharray="%.3f %.3f" stroke-dashoffset="%.3f" transform="rotate(-90 %d %d)"/>',
                $cx,
                $cy,
                $radius,
                $segment['color'],
                $length,
                $gap,
                -$offset,
                $cx,
                $cy
            );
            $offset += $length;
        }

        $svg .= '<text x="' . $cx . '" y="' . ($cy - 4) . '" text-anchor="middle" class="donut-label">' . $this->escape($currency) . '</text>';
        $svg .= '<text x="' . $cx . '" y="' . ($cy + 19) . '" text-anchor="middle" class="donut-value">' . $this->escape($this->formatAxis($total)) . '</text>';

        foreach ($segments as $index => $segment) {
            $y = 82 + ($index * 65);
            $share = ((float) $segment['value'] / $total) * 100;
            $svg .= "<rect x=\"350\" y=\"" . ($y - 12) . "\" width=\"13\" height=\"13\" rx=\"3\" fill=\"{$segment['color']}\"/>";
            $svg .= '<text x="374" y="' . $y . '" class="legend-strong">' . $this->escape($segment['label']) . '</text>';
            $svg .= '<text x="374" y="' . ($y + 19) . '" class="legend">' . $this->escape($currency . ' ' . number_format((float) $segment['value'], 2)) . ' · ' . number_format($share, 1) . '%</text>';
        }

        return $svg . $this->svgEnd();
    }

    private function radarChart(array $labels, array $values): string
    {
        $cx = 380;
        $cy = 154;
        $radius = 104;
        $count = max(1, count($labels));
        $svg = $this->svgStart();

        for ($ring = 1; $ring <= 5; $ring++) {
            $points = [];
            foreach ($labels as $index => $label) {
                $angle = deg2rad(-90 + ($index * (360 / $count)));
                $ringRadius = $radius * ($ring / 5);
                $points[] = round($cx + cos($angle) * $ringRadius, 2) . ',' . round($cy + sin($angle) * $ringRadius, 2);
            }
            $svg .= '<polygon points="' . implode(' ', $points) . '" fill="none" stroke="#dbe3ec" stroke-width="1"/>';
        }

        $valuePoints = [];
        foreach ($labels as $index => $label) {
            $angle = deg2rad(-90 + ($index * (360 / $count)));
            $outerX = $cx + cos($angle) * $radius;
            $outerY = $cy + sin($angle) * $radius;
            $valueRadius = $radius * (min(100, max(0, (float) ($values[$index] ?? 0))) / 100);
            $valuePoints[] = round($cx + cos($angle) * $valueRadius, 2) . ',' . round($cy + sin($angle) * $valueRadius, 2);
            $svg .= "<line x1=\"{$cx}\" y1=\"{$cy}\" x2=\"{$outerX}\" y2=\"{$outerY}\" stroke=\"#dbe3ec\" stroke-width=\"1\"/>";

            $labelRadius = $radius + 28;
            $labelX = $cx + cos($angle) * $labelRadius;
            $labelY = $cy + sin($angle) * $labelRadius;
            $anchor = abs(cos($angle)) < 0.2 ? 'middle' : (cos($angle) > 0 ? 'start' : 'end');
            $svg .= '<text x="' . round($labelX, 2) . '" y="' . round($labelY + 4, 2) . "\" text-anchor=\"{$anchor}\" class=\"axis\">" . $this->escape($label) . '</text>';
        }

        $svg .= '<polygon points="' . implode(' ', $valuePoints) . '" fill="#6d5bd0" fill-opacity=".22" stroke="#6d5bd0" stroke-width="3"/>';
        foreach ($valuePoints as $point) {
            [$x, $y] = explode(',', $point);
            $svg .= "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"4\" fill=\"#6d5bd0\"/>";
        }

        return $svg . $this->svgEnd();
    }

    private function bubbleChart(array $labels, array $rates, array $commitments, array $remaining): string
    {
        $left = 72;
        $right = 26;
        $top = 30;
        $bottom = 48;
        $plotWidth = self::WIDTH - $left - $right;
        $plotHeight = self::HEIGHT - $top - $bottom;
        $xMax = max(100, (float) collect($rates)->max());
        $yMax = $this->niceMaximum((float) collect($commitments)->max());
        $svg = $this->svgStart();
        $svg .= $this->grid($left, $top, $plotWidth, $plotHeight, $yMax, false);
        $svg .= "<text x=\"" . ($left + $plotWidth / 2) . "\" y=\"" . (self::HEIGHT - 8) . "\" text-anchor=\"middle\" class=\"axis\">Commitment Rate</text>";

        for ($tick = 0; $tick <= 5; $tick++) {
            $value = ($xMax / 5) * $tick;
            $x = $left + ($plotWidth / 5) * $tick;
            $svg .= '<text x="' . $x . '" y="' . ($top + $plotHeight + 18) . '" text-anchor="middle" class="axis">' . number_format($value, 0) . '%</text>';
        }

        foreach ($labels as $index => $label) {
            $rate = (float) ($rates[$index] ?? 0);
            $commitment = (float) ($commitments[$index] ?? 0);
            $variance = abs((float) ($remaining[$index] ?? 0));
            $radius = max(7, min(24, sqrt(max($variance, 1)) / 850));
            $rawX = $left + (($rate / $xMax) * $plotWidth);
            $rawY = $top + $plotHeight - (($commitment / $yMax) * $plotHeight);
            $x = min($left + $plotWidth - $radius, max($left + $radius, $rawX));
            $y = min($top + $plotHeight - $radius, max($top + $radius, $rawY));
            $svg .= sprintf(
                '<circle cx="%.2f" cy="%.2f" r="%.2f" fill="#d65a31" fill-opacity=".42" stroke="#d65a31" stroke-width="2"/>',
                $x,
                $y,
                $radius
            );

            $labelOffsets = [
                [0, -$radius - 5, 'middle'],
                [$radius + 8, 3, 'start'],
                [$radius + 8, $radius + 12, 'start'],
                [0, -$radius - 8, 'middle'],
                [$radius + 8, $radius + 28, 'start'],
            ];
            [$offsetX, $offsetY, $anchor] = $labelOffsets[$index % count($labelOffsets)];
            $labelX = $x + $offsetX;
            $labelY = $y + $offsetY;
            $labelX = min(self::WIDTH - 20, max(20, $labelX));
            $labelY = min(self::HEIGHT - 12, max(12, $labelY));
            $svg .= '<text x="' . round($labelX, 2) . '" y="' . round($labelY, 2) . "\" text-anchor=\"{$anchor}\" class=\"bubble-label\">" . $this->escape($label) . '</text>';
        }

        return $svg . $this->svgEnd();
    }

    private function grid(
        float $left,
        float $top,
        float $plotWidth,
        float $plotHeight,
        float $maxValue,
        bool $percent
    ): string {
        $svg = '';
        for ($tick = 0; $tick <= 5; $tick++) {
            $y = $top + $plotHeight - (($plotHeight / 5) * $tick);
            $value = ($maxValue / 5) * $tick;
            $label = $percent ? number_format($value, 0) . '%' : $this->formatAxis($value);
            $svg .= "<line x1=\"{$left}\" y1=\"{$y}\" x2=\"" . ($left + $plotWidth) . "\" y2=\"{$y}\" stroke=\"#e5eaf0\" stroke-width=\"1\"/>";
            $svg .= '<text x="' . ($left - 9) . '" y="' . ($y + 4) . '" text-anchor="end" class="axis">' . $this->escape($label) . '</text>';
        }

        return $svg;
    }

    private function xLabels(array $labels, float $left, float $axisY, float $plotWidth): string
    {
        $svg = '';
        foreach ($labels as $index => $label) {
            $x = $this->pointX($index, count($labels), $left, $plotWidth);
            $svg .= '<text x="' . $x . '" y="' . ($axisY + 20) . '" text-anchor="middle" class="axis">' . $this->escape($label) . '</text>';
        }

        return $svg;
    }

    private function pointX(int $index, int $count, float $left, float $plotWidth): float
    {
        if ($count <= 1) {
            return $left + ($plotWidth / 2);
        }

        return $left + (($plotWidth / ($count - 1)) * $index);
    }

    private function niceMaximum(float $value): float
    {
        if ($value <= 0) {
            return 1.0;
        }

        $magnitude = 10 ** floor(log10($value));
        $normalised = $value / $magnitude;
        $nice = match (true) {
            $normalised <= 1 => 1,
            $normalised <= 2 => 2,
            $normalised <= 5 => 5,
            default => 10,
        };

        return $nice * $magnitude;
    }

    private function formatAxis(float $value): string
    {
        $absolute = abs($value);
        if ($absolute >= 1_000_000_000) {
            return number_format($value / 1_000_000_000, 1) . 'B';
        }
        if ($absolute >= 1_000_000) {
            return number_format($value / 1_000_000, 1) . 'M';
        }
        if ($absolute >= 1_000) {
            return number_format($value / 1_000, 1) . 'K';
        }

        return number_format($value, 0);
    }

    private function svgStart(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . self::WIDTH . '" height="' . self::HEIGHT . '" viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '">'
            . '<style>'
            . '.axis{font:11px DejaVu Sans,Arial,sans-serif;fill:#64748b}'
            . '.legend{font:10px DejaVu Sans,Arial,sans-serif;fill:#475569}'
            . '.legend-strong{font:bold 11px DejaVu Sans,Arial,sans-serif;fill:#0f172a}'
            . '.value-label{font:bold 10px DejaVu Sans,Arial,sans-serif;fill:#334155}'
            . '.donut-label{font:10px DejaVu Sans,Arial,sans-serif;fill:#64748b}'
            . '.donut-value{font:bold 17px DejaVu Sans,Arial,sans-serif;fill:#0f172a}'
            . '.bubble-label{font:bold 9px DejaVu Sans,Arial,sans-serif;fill:#7c2d12}'
            . '</style><rect width="760" height="300" fill="#ffffff"/>';
    }

    private function svgEnd(): string
    {
        return '</svg>';
    }

    private function dataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
