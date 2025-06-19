<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\FontMetrics;

class PdfFontService
{
    public static function registerArabicFonts(Dompdf $dompdf)
    {
        $fontMetrics = $dompdf->getFontMetrics();
        $fontDir = storage_path('fonts/');

        // Register Amiri font
        $amiriFont = $fontDir . 'Amiri-Regular.ttf';
        if (file_exists($amiriFont)) {
            $fontMetrics->registerFont([
                'family' => 'Amiri',
                'style' => 'normal',
                'weight' => 'normal'
            ], $amiriFont);
        }

        // Register Noto Sans Arabic font
        $notoFont = $fontDir . 'NotoSansArabic-Regular.ttf';
        if (file_exists($notoFont)) {
            $fontMetrics->registerFont([
                'family' => 'NotoSansArabic',
                'style' => 'normal',
                'weight' => 'normal'
            ], $notoFont);
        }
    }
} 