<?php

namespace App\Support;

/**
 * Script-free report charts shared by the browser and DomPDF exports.
 * Missing observations remain missing; recorded zeroes remain visible.
 */
final class EvaluationReportCharts
{
    private const WIDTH = 1100;

    private const PALETTE = [
        '#176b87', '#b4552d', '#5f4b8b', '#23745c', '#a63f65', '#8a6b12',
        '#385ca8', '#9b443a', '#387e85', '#795b46', '#6e4f9b', '#3f743d',
        '#b26380', '#566578', '#ac712d', '#457ea8',
    ];

    /**
     * @param  array<int, string>  $labels  Applicant names in the intended display order.
     * @param  array<int, array{name:string, color?:string, values:array<int, int|float|null>}>  $series
     * @return array<int, array{title:string, src:string, alt:string, note:string, data:array}>
     */
    public static function grouped(
        string $title,
        array $labels,
        array $series,
        string $kind = 'bar',
        float $maximum = 100,
        string $axis = 'Score (%)'
    ): array {
        $labels = array_values(array_map(fn ($label) => self::plain((string) $label), $labels));
        $series = self::series($series);
        $kind = $kind === 'line' ? 'line' : 'bar';
        $finiteValues = array_merge([], ...array_map(fn ($row) => array_values(array_filter($row['values'], fn ($value) => $value !== null)), $series));
        $lower = min(0.0, ...($finiteValues ?: [0.0]));
        $upper = max(is_finite($maximum) && $maximum > 0 ? $maximum : 1.0, ...($finiteValues ?: [0.0]));
        // All chunks use the same scale so page breaks do not change comparisons.
        $step = self::niceStep(($upper - $lower) / 5);
        $lower = floor($lower / $step) * $step;
        $upper = ceil($upper / $step) * $step;
        $chunks = $labels === [] ? [[]] : array_chunk($labels, 8);
        $charts = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            $offset = $chunkIndex * 8;
            $chartTitle = count($chunks) > 1
                ? $title.' — applicants '.($offset + 1).'–'.($offset + count($chunk)).' of '.count($labels)
                : $title;
            $note = $kind === 'line'
                ? 'Lines compare applicants in display order, not time. Gaps indicate missing values; recorded zeroes are plotted.'
                : 'Each colour identifies an evaluator. Blank slots indicate missing values; short baseline marks show recorded zeroes.';
            $altRows = [];
            foreach ($chunk as $index => $label) {
                $values = [];
                foreach ($series as $row) {
                    $value = $row['values'][$offset + $index] ?? null;
                    $values[] = $row['name'].': '.($value === null ? 'not recorded' : self::number($value));
                }
                $altRows[] = $label.' ('.implode('; ', $values).')';
            }
            $alt = self::plain($chartTitle).'. '.$axis.'. '.($altRows ? implode('. ', $altRows) : 'No applicant data recorded.');
            $titleLines = self::wrap($chartTitle, 100);
            $titleBottom = 36 + count($titleLines) * 24;
            [$legend, $legendHeight] = self::legend($series, 80, $titleBottom, 980);
            $top = $titleBottom + $legendHeight + 30;
            $plotHeight = 260;
            $bottom = $top + $plotHeight;
            $labelRows = array_map(fn ($label) => self::wrap($label, 18), $chunk);
            $labelHeight = max(1, ...array_map('count', $labelRows ?: [[]])) * 16;
            $height = (int) ($bottom + $labelHeight + 75);
            $body = self::title($titleLines).$legend;
            $left = 80.0;
            $plotWidth = 980.0;
            $y = fn (float $value): float => $bottom - (($value - $lower) / ($upper - $lower)) * $plotHeight;

            for ($value = $lower; $value <= $upper + $step / 100; $value += $step) {
                $tickY = $y($value);
                $body .= self::line($left, $tickY, $left + $plotWidth, $tickY, '#dce5ed', 1);
                $body .= self::text($left - 12, $tickY + 4, self::number($value), 12, '#60738b', 'end');
            }
            $zeroY = $y(0);
            $body .= self::line($left, $zeroY, $left + $plotWidth, $zeroY, '#73869d', 1.4);
            $body .= '<text x="22" y="'.self::number($top + $plotHeight / 2).'" transform="rotate(-90 22 '.self::number($top + $plotHeight / 2).')" font-size="13" fill="#44576c" text-anchor="middle">'.self::escape($axis).'</text>';

            if ($chunk === [] || $series === []) {
                $body .= self::text(570, $top + 130, 'No recorded evaluation data for this chart', 19, '#60738b', 'middle');
            } else {
                $slot = $plotWidth / count($chunk);
                $chunkHasValues = false;
                foreach ($series as $seriesIndex => $row) {
                    $points = [];
                    $segments = [];
                    $segment = [];
                    foreach ($chunk as $index => $label) {
                        $value = $row['values'][$offset + $index] ?? null;
                        if ($value === null) {
                            if ($segment !== []) {
                                $segments[] = $segment;
                            }
                            $segment = [];

                            continue;
                        }
                        $chunkHasValues = true;
                        $x = $left + $slot * ($index + 0.5);
                        $valueY = $y($value);
                        if ($kind === 'bar') {
                            $groupWidth = $slot * 0.78;
                            $barSlot = $groupWidth / max(1, count($series));
                            $barWidth = max(0.6, $barSlot * 0.82);
                            $barX = $x - $groupWidth / 2 + $seriesIndex * $barSlot + ($barSlot - $barWidth) / 2;
                            $barY = min($valueY, $zeroY);
                            $barHeight = abs($valueY - $zeroY);
                            if ($barHeight < 1.8) {
                                $barY = $zeroY - 1.8;
                                $barHeight = 1.8;
                            }
                            $body .= '<rect x="'.self::number($barX).'" y="'.self::number($barY).'" width="'.self::number($barWidth).'" height="'.self::number($barHeight).'" fill="'.$row['color'].'"/>';
                            if ($barWidth >= 30) {
                                $body .= self::text($barX + $barWidth / 2, $value >= 0 ? $valueY - 7 : $valueY + 16, self::number($value), 11, '#33495f', 'middle');
                            }
                        } else {
                            $point = self::number($x).','.self::number($valueY);
                            $segment[] = $point;
                            $points[] = [$x, $valueY, $value];
                        }
                    }
                    if ($kind === 'line') {
                        if ($segment !== []) {
                            $segments[] = $segment;
                        }
                        foreach ($segments as $segmentPoints) {
                            if (count($segmentPoints) >= 2) {
                                $body .= '<polyline points="'.implode(' ', $segmentPoints).'" fill="none" stroke="'.$row['color'].'" stroke-width="2.6" stroke-linejoin="round"/>';
                            }
                        }
                        foreach ($points as [$pointX, $pointY, $value]) {
                            $body .= '<circle cx="'.self::number($pointX).'" cy="'.self::number($pointY).'" r="4.5" fill="'.$row['color'].'" stroke="#ffffff" stroke-width="1.4"/>';
                        }
                    }
                }
                if (! $chunkHasValues) {
                    $body .= self::text(570, $top + 130, 'Awaiting submitted evaluation results', 19, '#60738b', 'middle');
                }
                foreach ($chunk as $index => $label) {
                    $x = $left + $slot * ($index + 0.5);
                    foreach ($labelRows[$index] as $lineIndex => $line) {
                        $body .= self::text($x, $bottom + 24 + $lineIndex * 16, $line, 11, '#44576c', 'middle');
                    }
                }
            }
            $body .= self::text(570, $bottom + $labelHeight + 44, 'Applicants', 12, '#60738b', 'middle');
            $charts[] = self::chart($chartTitle, $body, $height, $alt, $note) + ['data' => [
                'kind' => $kind,
                'labels' => $chunk,
                'series' => array_map(fn ($row) => [
                    'name' => $row['name'], 'color' => $row['color'],
                    'values' => array_map(fn ($index) => $row['values'][$offset + $index] ?? null, array_keys($chunk)),
                ], $series),
                'minimum' => $lower, 'maximum' => $upper, 'axis' => $axis,
            ]];
        }

        return $charts;
    }

    /**
     * @param  array<int, array{name:string, value:int|float|null, color?:string}>  $slices
     * @return array{title:string, src:string, alt:string, note:string, data:array}
     */
    public static function pie(string $title, array $slices): array
    {
        $rows = self::series(array_map(fn ($slice) => [
            'name' => $slice['name'] ?? 'Unnamed category', 'color' => $slice['color'] ?? null,
            'values' => [$slice['value'] ?? null],
        ], array_values($slices)));
        $total = 0.0;
        foreach ($rows as &$row) {
            $value = $row['values'][0] ?? null;
            $row['value'] = $value !== null && $value >= 0 ? $value : null;
            $total += $row['value'] ?? 0;
        }
        unset($row);
        $titleLines = self::wrap($title, 100);
        $top = 44 + count($titleLines) * 24;
        $radius = 152;
        $centerX = 266;
        $centerY = $top + 177;
        $body = self::title($titleLines);
        $angle = -M_PI / 2;
        $positiveRows = array_values(array_filter($rows, fn ($row) => ($row['value'] ?? 0) > 0));
        if ($total > 0) {
            if (count($positiveRows) === 1) {
                $body .= '<circle cx="'.$centerX.'" cy="'.$centerY.'" r="'.$radius.'" fill="'.$positiveRows[0]['color'].'"/>';
            } else {
                foreach ($positiveRows as $row) {
                    $span = $row['value'] / $total * 2 * M_PI;
                    $nextAngle = $angle + $span;
                    // DomPDF's SVG backend can omit elliptical arc commands.
                    // Small polygon steps render the same sector in both targets.
                    $points = [$centerX.','.$centerY];
                    $steps = max(2, (int) ceil($span / (M_PI / 90)));
                    for ($stepIndex = 0; $stepIndex <= $steps; $stepIndex++) {
                        $pointAngle = $angle + $span * $stepIndex / $steps;
                        $points[] = self::number($centerX + $radius * cos($pointAngle)).','.self::number($centerY + $radius * sin($pointAngle));
                    }
                    $body .= '<polygon points="'.implode(' ', $points).'" fill="'.$row['color'].'" stroke="#ffffff" stroke-width="2"/>';
                    $angle = $nextAngle;
                }
            }
        } else {
            $body .= '<circle cx="'.$centerX.'" cy="'.$centerY.'" r="'.$radius.'" fill="#f6f8fb" stroke="#d8e2ec" stroke-width="2"/>';
            $body .= self::text($centerX, $centerY - 8, 'No recorded outcomes', 18, '#60738b', 'middle');
            $body .= self::text($centerX, $centerY + 18, 'No slices drawn', 13, '#60738b', 'middle');
        }
        $body .= self::text($centerX, $centerY + $radius + 34, self::number($total).' total recorded', 17, '#203c52', 'middle');
        $legendY = $top + 34;
        $altRows = [];
        foreach ($rows as $row) {
            $body .= '<rect x="525" y="'.($legendY - 11).'" width="14" height="14" rx="2" fill="'.$row['color'].'"/>';
            $wrapped = self::wrap($row['name'], 43);
            foreach ($wrapped as $index => $line) {
                $body .= self::text(552, $legendY + $index * 18, $line, 14, '#33495f');
            }
            $count = $row['value'] === null ? 'Not recorded' : self::number($row['value']);
            $share = $total > 0 && $row['value'] !== null ? number_format($row['value'] / $total * 100, 1).'%' : '—';
            $legendY += count($wrapped) * 18;
            $body .= self::text(552, $legendY + 2, $count.'  |  '.$share, 12, '#60738b');
            $legendY += 32;
            $altRows[] = $row['name'].': '.$count.($share !== '—' ? ' ('.$share.')' : '');
        }
        if ($rows === []) {
            $body .= self::text(552, $legendY, 'No categories have recorded results.', 15, '#60738b');
        }
        $height = (int) max($centerY + $radius + 74, $legendY + 24);
        $note = $total > 0
            ? 'Slice sizes reflect recorded counts. Zero-count categories have no slice; missing counts are not converted to zero.'
            : 'There are no positive recorded counts, so no outcome distribution is drawn.';
        $alt = self::plain($title).'. '.($altRows ? implode('; ', $altRows).'. ' : '').self::number($total).' total recorded.';

        return self::chart($title, $body, $height, $alt, $note) + ['data' => [
            'kind' => 'pie',
            'slices' => array_map(fn ($row) => [
                'name' => $row['name'], 'value' => $row['value'], 'color' => $row['color'],
            ], $rows),
        ]];
    }

    private static function series(array $series): array
    {
        $rows = [];
        $usedColors = [];
        foreach (array_values($series) as $index => $row) {
            $color = strtolower((string) ($row['color'] ?? ''));
            if (! preg_match('/^#[a-f0-9]{6}$/', $color) || in_array($color, $usedColors, true)) {
                $color = self::PALETTE[$index % count(self::PALETTE)];
                if (in_array($color, $usedColors, true)) {
                    foreach (self::PALETTE as $alternative) {
                        if (! in_array($alternative, $usedColors, true)) {
                            $color = $alternative;
                            break;
                        }
                    }
                    $attempt = 0;
                    while (in_array($color, $usedColors, true)) {
                        $color = '#'.substr(hash('sha256', 'evaluation-series-'.$index.'-'.$attempt++), 0, 6);
                    }
                }
            }
            $usedColors[] = $color;
            $rows[] = [
                'name' => self::plain((string) ($row['name'] ?? 'Evaluator '.($index + 1))), 'color' => $color,
                'values' => array_map(fn ($value) => is_numeric($value) && is_finite((float) $value) ? (float) $value : null, array_values($row['values'] ?? [])),
            ];
        }

        return $rows;
    }

    private static function legend(array $series, int $left, int $top, int $width): array
    {
        if ($series === []) {
            return ['', 0];
        }
        $columns = min(3, count($series));
        $columnWidth = $width / $columns;
        $body = '';
        $y = $top;
        foreach (array_chunk($series, $columns) as $legendRow) {
            $rowHeight = 0;
            foreach ($legendRow as $column => $seriesRow) {
                $x = $left + $column * $columnWidth;
                $lines = self::wrap($seriesRow['name'], max(18, (int) (($columnWidth - 38) / 7)));
                $body .= '<rect x="'.self::number($x).'" y="'.($y - 10).'" width="12" height="12" rx="2" fill="'.$seriesRow['color'].'"/>';
                foreach ($lines as $index => $line) {
                    $body .= self::text($x + 21, $y + $index * 17, $line, 12, '#44576c');
                }
                $rowHeight = max($rowHeight, count($lines) * 17 + 11);
            }
            $y += $rowHeight;
        }

        return [$body, $y - $top];
    }

    private static function title(array $lines): string
    {
        $body = '';
        foreach ($lines as $index => $line) {
            $body .= self::text(34, 36 + $index * 24, $line, 20, '#17384c');
        }

        return $body;
    }

    private static function chart(string $title, string $body, int $height, string $alt, string $note): array
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.self::WIDTH.'" height="'.$height.'" viewBox="0 0 '.self::WIDTH.' '.$height.'">'
            .'<title>'.self::escape($title).'</title><desc>'.self::escape($alt).'</desc>'
            .'<rect x="0" y="0" width="'.self::WIDTH.'" height="'.$height.'" fill="#ffffff"/>'
            .'<g font-family="DejaVu Sans, Arial, sans-serif">'.$body.'</g></svg>';

        return ['title' => self::plain($title), 'src' => 'data:image/svg+xml;base64,'.base64_encode($svg), 'alt' => $alt, 'note' => $note];
    }

    private static function line(float $x1, float $y1, float $x2, float $y2, string $color, float $width): string
    {
        return '<line x1="'.self::number($x1).'" y1="'.self::number($y1).'" x2="'.self::number($x2).'" y2="'.self::number($y2).'" stroke="'.$color.'" stroke-width="'.self::number($width).'"/>';
    }

    private static function text(float $x, float $y, string $text, int $size, string $color, string $anchor = 'start'): string
    {
        return '<text x="'.self::number($x).'" y="'.self::number($y).'" font-size="'.$size.'" fill="'.$color.'" text-anchor="'.$anchor.'">'.self::escape($text).'</text>';
    }

    private static function niceStep(float $step): float
    {
        if ($step <= 0 || ! is_finite($step)) {
            return 1;
        }
        $magnitude = 10 ** floor(log10($step));
        $normal = $step / $magnitude;
        $factor = match (true) {
            $normal <= 1 => 1, $normal <= 2 => 2, $normal <= 2.5 => 2.5, $normal <= 5 => 5, default => 10
        };

        return $factor * $magnitude;
    }

    private static function number(float $number): string
    {
        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.') ?: '0';
    }

    private static function plain(string $text): string
    {
        return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text));
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars(self::plain($text), ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Word wrapping also breaks long identifiers; no label text is discarded. */
    private static function wrap(string $text, int $length): array
    {
        $words = preg_split('/\s+/u', self::plain($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if ($line !== '' && mb_strlen($line.' '.$word) > $length) {
                $lines[] = $line;
                $line = '';
            }
            while (mb_strlen($word) > $length) {
                $lines[] = mb_substr($word, 0, $length);
                $word = mb_substr($word, $length);
            }
            if ($word !== '') {
                $line .= ($line === '' ? '' : ' ').$word;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [''];
    }
}
