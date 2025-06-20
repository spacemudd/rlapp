<?php

namespace App\Services;

use ArPHP\I18N\Arabic;

class ArabicTextProcessor
{
    /**
     * Process HTML content to support Arabic text properly
     *
     * @param string $html
     * @param int $lineLength
     * @param bool $hindo
     * @param bool $forcertl
     * @return string
     */
    public static function processHtml(string $html, int $lineLength = 100, bool $hindo = false, bool $forcertl = false): string
    {
        $arabic = new Arabic();
        
        // First, try to fix Arabic text using the Glyphs class
        try {
            // Process Arabic text using ArPHP's utf8Glyphs method
            $processedHtml = $arabic->utf8Glyphs($html, $lineLength, $hindo, $forcertl);
            return $processedHtml;
        } catch (\Exception $e) {
            // If ArPHP processing fails, return original HTML
            error_log('ArPHP processing failed: ' . $e->getMessage());
            return $html;
        }
    }
} 