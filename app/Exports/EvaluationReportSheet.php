<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Axis;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\GridLines;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EvaluationReportSheet implements FromArray, WithCharts, WithColumnWidths, WithEvents, WithStrictNullComparison, WithStyles, WithTitle
{
    private ?array $preparedRows = null;

    private array $chartLayouts = [];

    public function __construct(
        private readonly string $title,
        private readonly array $rows,
        private readonly array $reportCharts = [],
    ) {}

    public function array(): array
    {
        $this->prepareCharts();

        return $this->preparedRows;
    }

    /** Native, editable charts use the same observations as the web/PDF SVGs. */
    public function charts(): array
    {
        $this->prepareCharts();
        $charts = [];
        foreach ($this->chartLayouts as $index => $placement) {
            if (! $placement['has_values']) {
                continue;
            }

            $data = $placement['data'];
            $pie = $data['kind'] === 'pie';
            $line = $data['kind'] === 'line';
            $first = $placement['source_header'] + 1;
            $last = $first + count($data['labels']) - 1;
            $category = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
                $this->sourceRange('A', $first, $last), null, count($data['labels']), $data['labels']);
            $labels = $values = [];
            foreach ($data['series'] as $seriesIndex => $series) {
                $column = Coordinate::stringFromColumnIndex($seriesIndex + 2);
                $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
                    $this->sourceRange($column, $placement['source_header'], $placement['source_header']), null, 1, [$series['name']]);
                // Excel's numeric cache must omit missing observations, retaining
                // their original indices. An empty numeric value is not a zero.
                $numeric = new class(DataSeriesValues::DATASERIES_TYPE_NUMBER, $this->sourceRange($column, $first, $last), $pie ? '0' : '0.00', count($data['labels']), $series['values'], $line ? 'circle' : null, $pie ? $data['colors'] : ltrim($series['color'], '#')) extends DataSeriesValues
                {
                    public function getDataValues()
                    {
                        return array_filter(parent::getDataValues(), static fn ($value) => $value !== null && $value !== '');
                    }
                };
                if ($line) {
                    $numeric->setLineColorProperties(ltrim($series['color'], '#'));
                    $numeric->setLineWidth(2);
                }
                $values[] = $numeric;
            }
            $type = $pie ? DataSeries::TYPE_PIECHART : ($line ? DataSeries::TYPE_LINECHART : DataSeries::TYPE_BARCHART);
            $grouping = $pie ? null : ($line ? DataSeries::GROUPING_STANDARD : DataSeries::GROUPING_CLUSTERED);
            $series = new DataSeries($type, $grouping, array_keys($values), $labels, [$category], $values);
            if (! $pie && ! $line) {
                $series->setPlotDirection(DataSeries::DIRECTION_COL);
            }
            $layout = new Layout;
            if ($pie) {
                $layout->setShowPercent(true)->setShowVal(true);
            }
            $axis = new Axis;
            if (! $pie) {
                $axis->setAxisOption('minimum', (string) ($data['minimum'] ?? 0));
                $axis->setAxisOption('maximum', (string) ($data['maximum'] ?? 100));
                $axis->setAxisNumberProperties('0.##', true);
                $grid = new GridLines;
                $grid->setLineColorProperties('DCE5ED');
                $axis->setMajorGridlines($grid);
            }
            $chart = new Chart('evaluation_'.substr(hash('sha256', $this->title), 0, 10).'_'.$index,
                new Title($placement['title']), new Legend(Legend::POSITION_BOTTOM), new PlotArea($layout, [$series]),
                true, DataSeries::EMPTY_AS_GAP,
                $pie ? null : new Title('Applicants (submission codes)'),
                $pie ? null : new Title($data['axis'] ?? 'Score (%)'), null, $axis);
            $chart->setTopLeftPosition('A'.$placement['chart_top']);
            $chart->setBottomRightPosition('I'.($placement['chart_top'] + 22));
            $charts[] = $chart;
        }

        return $charts;
    }

    /** Keep chart source tables visible and numeric, immediately below each chart. */
    private function prepareCharts(): void
    {
        if ($this->preparedRows !== null) {
            return;
        }
        $rows = array_values($this->rows);
        foreach ($this->reportCharts as $chart) {
            $data = $chart['data'] ?? [];
            if (! in_array($data['kind'] ?? null, ['bar', 'line', 'pie'], true)) {
                continue;
            }
            if ($data['kind'] === 'pie') {
                $slices = array_values($data['slices'] ?? []);
                $data['labels'] = array_column($slices, 'name');
                $data['colors'] = array_map(fn ($slice) => ltrim($slice['color'] ?? '#176b87', '#'), $slices);
                $data['series'] = [['name' => 'Recorded count', 'color' => '#176b87', 'values' => array_column($slices, 'value')]];
            }
            $data['labels'] = array_values($data['labels'] ?? []);
            $data['series'] = array_values($data['series'] ?? []);
            $hasValues = false;
            foreach ($data['series'] as &$series) {
                $series['values'] = array_map(function ($index) use ($series): ?float {
                    $value = $series['values'][$index] ?? null;

                    return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
                }, array_keys($data['labels']));
                foreach ($series['values'] as $value) {
                    if ($value !== null && ($data['kind'] !== 'pie' || $value > 0)) {
                        $hasValues = true;
                    }
                }
            }
            unset($series);
            $rows[] = [];
            $rows[] = [];
            $titleRow = count($rows) + 1;
            $rows[] = [self::literal($chart['title'] ?? 'Evaluation chart')];
            $chartTop = count($rows) + 1;
            if ($hasValues) {
                for ($row = 0; $row < 23; $row++) {
                    $rows[] = [];
                }
            }
            $noteRow = count($rows) + 1;
            $rows[] = [self::literal(($hasValues ? '' : 'No recorded data to plot. ').($chart['note'] ?? ''))];
            $rows[] = [];
            $sourceHeader = count($rows) + 1;
            $rows[] = array_map([self::class, 'literal'], array_merge(
                [$data['kind'] === 'pie' ? 'Category' : 'Applicant code'], array_column($data['series'], 'name')));
            foreach ($data['labels'] as $labelIndex => $label) {
                $rows[] = array_merge([self::literal($label)], array_map(fn ($series) => $series['values'][$labelIndex], $data['series']));
            }
            $this->chartLayouts[] = [
                'title' => $chart['title'] ?? 'Evaluation chart', 'title_row' => $titleRow, 'chart_top' => $chartTop,
                'note_row' => $noteRow, 'source_header' => $sourceHeader, 'data' => $data, 'has_values' => $hasValues,
            ];
        }
        $this->preparedRows = $rows;
    }

    private function sourceRange(string $column, int $first, int $last): string
    {
        return "'".str_replace("'", "''", $this->title())."'!\${$column}\${$first}:\${$column}\${$last}";
    }

    private static function literal(mixed $value): string
    {
        $text = (string) $value;

        return preg_match('/^[\s]*[=+@\-]/u', $text) ? "'".$text : $text;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $reportColumn = Coordinate::stringFromColumnIndex(max(1, ...array_map('count', $this->rows)));
        $sheet->setAutoFilter('A1:'.$reportColumn.max(1, count($this->rows)));

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '0B2138'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $columnCount = max($this->reportCharts ? 8 : 1, ...array_map('count', $this->array()));
        $widths = [];

        for ($index = 1; $index <= $columnCount; $index++) {
            $widths[Coordinate::stringFromColumnIndex($index)] = match (true) {
                $this->title === 'Overview' && $index === 1 => 34,
                $this->title === 'Overview' && $index === 2 => 78,
                $index === 1 => 20,
                $index <= 3 => 28,
                $index <= 8 => 20,
                default => 24,
            };
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = max(1, $sheet->getHighestRow());

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageMargins()
                    ->setTop(0.35)
                    ->setBottom(0.35)
                    ->setLeft(0.25)
                    ->setRight(0.25);
                $sheet->getPageSetup()->setHorizontalCentered(true);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
                $sheet->getSheetView()->setZoomScale(90);
                $sheet->getDefaultRowDimension()->setRowHeight(18);
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);
                $sheet->getStyle("A1:{$lastColumn}1")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
                foreach ($this->chartLayouts as $placement) {
                    foreach (['title_row', 'note_row'] as $rowKey) {
                        $row = $placement[$rowKey];
                        $sheet->mergeCells('A'.$row.':H'.$row);
                        $sheet->getRowDimension($row)->setRowHeight($rowKey === 'note_row' ? 36 : 30);
                    }
                    $sheet->getStyle('A'.$placement['title_row'])->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('17384C');
                    $sheet->getStyle('A'.$placement['note_row'])->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB('60738B');
                    $header = $placement['source_header'];
                    $endColumn = Coordinate::stringFromColumnIndex(count($placement['data']['series']) + 1);
                    $sheet->getStyle('A'.$header.':'.$endColumn.$header)->getFont()->setBold(true);
                    $sheet->getRowDimension($header)->setRowHeight(32);
                    $lastDataRow = $header + count($placement['data']['labels']);
                    if ($lastDataRow > $header && $endColumn !== 'A') {
                        $sheet->getStyle('B'.($header + 1).':'.$endColumn.$lastDataRow)->getNumberFormat()
                            ->setFormatCode($placement['data']['kind'] === 'pie' ? '0' : '0.00');
                    }
                }
            },
        ];
    }
}
