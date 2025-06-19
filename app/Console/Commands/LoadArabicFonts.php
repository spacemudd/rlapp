<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Dompdf\Dompdf;
use Dompdf\Options;

class LoadArabicFonts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fonts:load-arabic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load Arabic fonts for PDF generation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Loading Arabic fonts for DomPDF...');

        // Set up DomPDF options
        $options = new Options();
        $options->set('fontDir', storage_path('fonts/'));
        $options->set('fontCache', storage_path('fonts/'));
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        // Load fonts
        $fontDir = storage_path('fonts/');
        
        // Check if fonts exist
        $amiriFont = $fontDir . 'Amiri-Regular.ttf';
        $notoFont = $fontDir . 'NotoSansArabic-Regular.ttf';

        if (file_exists($amiriFont)) {
            $this->info('Found Amiri font: ' . $amiriFont);
            // Load Amiri font
            $dompdf->getFontMetrics()->registerFont([
                'family' => 'Amiri',
                'style' => 'normal',
                'weight' => 'normal'
            ], $amiriFont);
            $this->info('✓ Amiri font loaded successfully');
        } else {
            $this->warn('Amiri font not found at: ' . $amiriFont);
        }

        if (file_exists($notoFont)) {
            $this->info('Found Noto Sans Arabic font: ' . $notoFont);
            // Load Noto Sans Arabic font
            $dompdf->getFontMetrics()->registerFont([
                'family' => 'NotoSansArabic',
                'style' => 'normal',
                'weight' => 'normal'
            ], $notoFont);
            $this->info('✓ Noto Sans Arabic font loaded successfully');
        } else {
            $this->warn('Noto Sans Arabic font not found at: ' . $notoFont);
        }

        // Create a simple test HTML to verify fonts work
        $testHtml = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                .amiri { font-family: "Amiri", serif; direction: rtl; }
                .noto { font-family: "NotoSansArabic", sans-serif; direction: rtl; }
            </style>
        </head>
        <body>
            <div class="amiri">مرحبا بكم في تأجير السيارات - Amiri Font</div>
            <div class="noto">مرحبا بكم في تأجير السيارات - Noto Sans Arabic Font</div>
        </body>
        </html>';

        try {
            $dompdf->loadHtml($testHtml);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            // Save test PDF
            $testPdfPath = storage_path('fonts/arabic-font-test.pdf');
            file_put_contents($testPdfPath, $dompdf->output());
            
            $this->info('✓ Test PDF created successfully at: ' . $testPdfPath);
            $this->info('Arabic fonts are now ready for use in PDF generation!');
            
        } catch (\Exception $e) {
            $this->error('Error creating test PDF: ' . $e->getMessage());
        }

        return 0;
    }
}
