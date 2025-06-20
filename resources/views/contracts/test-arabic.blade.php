<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Arabic Font Test</title>
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
