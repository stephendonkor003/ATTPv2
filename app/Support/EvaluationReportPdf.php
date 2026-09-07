<?php

namespace App\Support;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/** Bounded resources for complete graphical evaluation exports. */
final class EvaluationReportPdf
{
    public static function download(array $data, string $filename)
    {
        return response(self::output($data), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str_replace(['"', "\r", "\n"], '', $filename).'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function output(array $data): string
    {
        self::prepare();
        if (count($data['management']['details'] ?? []) <= 12) {
            return PdfPageNumbering::stamp(self::document($data))->output();
        }

        // Keep each DOM bounded. Thousands of narrative blocks in one DOM
        // make the renderer repeatedly reflow the entire remaining report.
        $merged = new class('L', 'pt', 'A4') extends Fpdi
        {
            private array $sectionBookmarks = [];

            private ?int $bookmarkRoot = null;

            public function markSection(int $section): void
            {
                $this->sectionBookmarks[$section] ??= $this->PageNo();
            }

            public function Footer()
            {
                $this->SetFont('Helvetica', '', 7);
                $this->SetTextColor(71, 89, 110);
                $this->SetXY(30, -26);
                $this->Cell($this->GetPageWidth() - 60, 8, 'Page '.$this->PageNo().' of {nb}', 0, 0, 'R');
            }

            protected function _putresources()
            {
                // FPDF's documented outline extension (FPDF license):
                // https://www.fpdf.org/en/script/script1.php
                parent::_putresources();
                $titles = [1 => '1 Summary overview', 2 => '2 Evaluation results and rankings', 3 => '3 Detailed evaluator and section scores', 4 => '4 Panel consistency and management insights', 5 => '5 Governance, audit trail and next steps'];
                $first = $this->n + 1;
                $this->bookmarkRoot = $first + count($this->sectionBookmarks);
                $index = 0;
                foreach ($this->sectionBookmarks as $section => $page) {
                    $this->_newobj();
                    $this->_put('<< /Title '.$this->_textstring($titles[$section]).' /Parent '.$this->bookmarkRoot.' 0 R');
                    if ($index > 0) {
                        $this->_put('/Prev '.($first + $index - 1).' 0 R');
                    }
                    if ($index < count($this->sectionBookmarks) - 1) {
                        $this->_put('/Next '.($first + $index + 1).' 0 R');
                    }
                    $this->_put('/Dest ['.$this->PageInfo[$page]['n'].' 0 R /Fit] >>');
                    $this->_put('endobj');
                    $index++;
                }
                $this->_newobj();
                $this->_put('<< /Type /Outlines /First '.$first.' 0 R /Last '.($this->bookmarkRoot - 1).' 0 R /Count '.$index.' >>');
                $this->_put('endobj');
            }

            protected function _putcatalog()
            {
                parent::_putcatalog();
                $this->_put('/Outlines '.$this->bookmarkRoot.' 0 R /PageMode /UseOutlines');
            }
        };
        $merged->AliasNbPages();
        $merged->SetAutoPageBreak(false);
        $merged->SetTitle('Evaluation Management Report');

        foreach (self::parts($data) as $part) {
            $document = self::document($part);
            $bytes = $document->output();
            unset($document);
            gc_collect_cycles();

            $pages = $merged->setSourceFile(StreamReader::createByString($bytes));
            for ($page = 1; $page <= $pages; $page++) {
                $template = $merged->importPage($page);
                $size = $merged->getTemplateSize($template);
                $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $merged->useTemplate($template);
                if ($page === 1) {
                    $merged->markSection((int) $part['managementSections'][0]);
                }
            }
        }

        return $merged->Output('S');
    }

    private static function document(array $data)
    {
        return app('dompdf.wrapper')
            ->setOptions(['isFontSubsettingEnabled' => true])
            ->loadView('reports.evaluations.pdf.method-procurement', $data)
            ->setPaper('a4', 'landscape');
    }

    private static function parts(array $data): \Generator
    {
        foreach ([1, 2] as $section) {
            yield array_replace($data, ['managementSections' => [$section]]);
        }

        $management = $data['management'];
        foreach (array_chunk($management['sections'], 1) as $sections) {
            $part = array_replace($management, ['sections' => $sections, 'details' => []]);
            yield array_replace($data, ['management' => $part, 'managementSections' => [3], 'managementSection3Mode' => 'comparisons']);
        }
        foreach (array_chunk($management['details'], 6) as $index => $details) {
            $part = array_replace($management, ['sections' => [], 'details' => $details]);
            yield array_replace($data, ['management' => $part, 'managementSections' => [3], 'managementSection3Mode' => 'details', 'managementDetailContinuation' => $index > 0]);
        }

        foreach ([4, 5] as $section) {
            yield array_replace($data, ['managementSections' => [$section]]);
        }
    }

    public static function prepare(): void
    {
        $limit = trim((string) ini_get('memory_limit'));
        $bytes = (int) $limit;
        $bytes *= match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        // Preserve a host's higher or unlimited allowance.
        if ($bytes > 0 && $bytes < 512 * 1024 ** 2) {
            ini_set('memory_limit', '512M');
        }

        $seconds = (int) ini_get('max_execution_time');
        if ($seconds > 0 && $seconds < 180) {
            set_time_limit(180);
        }
    }
}
