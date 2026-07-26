<?php

namespace App\Services;

use Barryvdh\DomPDF\PDF;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

class BiAnnualSiteVisitPdfService
{
    public function stampPageNumbers(
        PDF $pdf,
        float $rightMargin = 34,
        float $bottomOffset = 20
    ): PDF {
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();

        $canvas->page_script(function (
            int $pageNumber,
            int $pageCount,
            Canvas $pageCanvas,
            FontMetrics $fontMetrics
        ) use ($rightMargin, $bottomOffset): void {
            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $fontSize = 6.3;
            $text = "Page {$pageNumber} of {$pageCount}";
            $textWidth = $fontMetrics->getTextWidth($text, $font, $fontSize);

            $pageCanvas->text(
                $pageCanvas->get_width() - $rightMargin - $textWidth,
                $pageCanvas->get_height() - $bottomOffset,
                $text,
                $font,
                $fontSize,
                [0.13, 0.22, 0.20]
            );
        });

        return $pdf;
    }
}
