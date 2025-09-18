<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>Arabic Font Test - Rubik Font Implementation</title>
    
    {{-- Include Rubik font --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.5;
        }

        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ccc;
        }

        .arabic-default {
            direction: rtl;
            text-align: right;
        }

        .arabic-rubik {
            font-family: 'Rubik', sans-serif;
            direction: rtl;
            text-align: right;
            font-optical-sizing: auto;
            font-style: normal;
        }

        .arabic-rubik-light {
            font-family: 'Rubik', sans-serif;
            font-weight: 300;
            direction: rtl;
            text-align: right;
            font-optical-sizing: auto;
            font-style: normal;
        }

        .arabic-rubik-medium {
            font-family: 'Rubik', sans-serif;
            font-weight: 500;
            direction: rtl;
            text-align: right;
            font-optical-sizing: auto;
            font-style: normal;
        }

        .arabic-rubik-bold {
            font-family: 'Rubik', sans-serif;
            font-weight: 700;
            direction: rtl;
            text-align: right;
            font-optical-sizing: auto;
            font-style: normal;
        }

        .arabic-dejavu {
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            text-align: right;
        }

        .arabic-times {
            font-family: 'Times New Roman', serif;
            direction: rtl;
            text-align: right;
        }

        .arabic-arial {
            font-family: 'Arial Unicode MS', Arial, sans-serif;
            direction: rtl;
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Arabic Font Rendering Test</h1>

    <div class="test-section">
        <h3>Default Font</h3>
        <div class="arabic-default">
            المركبة - حالة السيارة - الشروط والاحكام
        </div>
    </div>

    <div class="test-section">
        <h3>Rubik Font (Regular)</h3>
        <div class="arabic-rubik">
            المركبة - حالة السيارة - الشروط والاحكام
        </div>
    </div>

    <div class="test-section">
        <h3>Rubik Font (Light - 300)</h3>
        <div class="arabic-rubik-light">
            المركبة - حالة السيارة - الشروط والاحكام
        </div>
    </div>

    <div class="test-section">
        <h3>Rubik Font (Medium - 500)</h3>
        <div class="arabic-rubik-medium">
            المركبة - حالة السيارة - الشروط والاحكام
        </div>
    </div>

    <div class="test-section">
        <h3>Rubik Font (Bold - 700)</h3>
        <div class="arabic-rubik-bold">
            المركبة - حالة السيارة - الشروط والاحكام
        </div>
    </div>

    <div class="test-section">
        <h3>DejaVu Sans</h3>
        <div class="arabic-dejavu">
            المركبة - حالة السيارة - الشروط والاحكام
        </div>
    </div>

    <div class="test-section">
        <h3>Times New Roman</h3>
        <div class="arabic-times">
            المركبة - حالة السيارة - الشروط والاحكام
        </div>
    </div>

    <div class="test-section">
        <h3>Arial Unicode MS</h3>
        <div class="arabic-arial">
            المركبة - حالة السيارة - الشروط والاحكام
        </div>
    </div>

    <div class="test-section">
        <h3>English Text (for comparison)</h3>
        <div>Vehicle - Car Status - Terms and Conditions</div>
    </div>
</body>
</html>
