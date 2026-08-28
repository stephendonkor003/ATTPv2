<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

final class PdfPageNumbering
{
    public static function stamp(
        PDF $pdf,
        float $rightMargin = 30,
        float $bottomOffset = 19
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
            $fontSize = 7;
            $text = "Page {$pageNumber} of {$pageCount}";
            $textWidth = $fontMetrics->getTextWidth($text, $font, $fontSize);

            $pageCanvas->text(
                $pageCanvas->get_width() - $rightMargin - $textWidth,
                $pageCanvas->get_height() - $bottomOffset,
                $text,
                $font,
                $fontSize,
                [0.28, 0.35, 0.43]
            );
        });

        return $pdf;
    }
}
