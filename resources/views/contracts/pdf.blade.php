<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contract {{ $contract->contract_number }}</title>
        <style>
        @font-face {
            font-family: 'ArialArabic';
            src: url({{ storage_path('fonts/arial-ar.ttf') }}) format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        * {
            font-family: 'ArialArabic', Arial !important;
        }

        @page {
            margin: 0mm 5mm 5mm 5mm;
            size: A4;
        }

        body {
            font-family: 'ArialArabic', Arial, sans-serif;
            font-size: 9px;
            line-height: 1.2;
            margin: 0;
            padding: 0;
            color: #000;
        }

        .arabic-text {
            direction: rtl;
            text-align: right;
            word-wrap: break-word;
            word-break: normal;
            white-space: normal;
            line-height: 1.4;
        }

        .arabic-inline {
            direction: rtl;
            display: inline-block;
        }

        .arabic-terms {
            direction: rtl;
            text-align: right;
            word-wrap: break-word;
            word-break: normal;
            white-space: normal;
            line-height: 1.4;
            hyphens: none;
            -webkit-hyphens: none;
            -moz-hyphens: none;
            -ms-hyphens: none;
        }

        .arabic-terms p {
            margin: 5px 0;
            word-wrap: break-word;
            word-break: normal;
            white-space: normal;
            line-height: 1.4;
            hyphens: none;
            -webkit-hyphens: none;
            -moz-hyphens: none;
            -ms-hyphens: none;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 3px;
            margin-bottom: 2px;
        }

        .company-subtitle {
            font-size: 10px;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }

        .header {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .header-left {
            display: table-cell;
            width: 30%;
            vertical-align: middle;
        }

        .header-center {
            display: table-cell;
            width: 40%;
            text-align: center;
            vertical-align: middle;
        }

        .header-right {
            display: table-cell;
            width: 30%;
            text-align: right;
            vertical-align: middle;
        }

        .logo {
            max-height: 60px;
            max-width: 150px;
        }

        .contract-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .contract-code {
            font-size: 10px;
        }

        .barcode-section {
            float: right;
            text-align: right;
            margin-bottom: 10px;
        }

        .order-info {
            float: right;
            text-align: right;
            font-size: 8px;
            margin-bottom: 15px;
        }

                 .arabic-title {
             font-size: 10px;
             direction: rtl;
             text-align: right;
         }

        .main-content {
            clear: both;
        }

        .main-content-left-side {
            width: 48%;
            float: left;
            margin-right: 10px;
        }

        .main-content-right-side {
            width: 48%;
            float: right;
            margin-left: 10px;
            direction: rtl;
            text-align: right;
        }

                .left-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
            font-size: 8px;
        }

        .left-table td {
            padding: 1px 2px;
            vertical-align: top;
        }

        .right-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
            font-size: 8px;
        }

        .right-table td {
            padding: 1px 2px;
            vertical-align: top;
        }

        .arabic-cell {
            width: 30%;
            text-align: right;
            direction: rtl;
        }

        .english-cell {
            width: 70%;
            text-align: left;
        }

        .field-label {
            font-weight: bold;
            display: inline-block;
            /*width: 80px;*/
        }

        .field-value {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 120px;
            padding-bottom: 1px;
        }

        .arabic-label {
            font-weight: bold;
        }

        .arabic-label {
            font-weight: bold;
            display: inline-block;
            /*width: 80px;*/
            text-align: right;
        }

        .arabic-value {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 120px;
            padding-bottom: 1px;
            direction: ltr;
            text-align: left;
        }

        .financial-section {
            margin-top: 15px;
            border: 1px solid #000;
            padding: 8px;
        }

        .financial-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 8px;
            text-align: center;
        }

        .financial-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .financial-table td {
            width: 33.33%;
            font-size: 8px;
            padding: 2px;
            vertical-align: top;
        }

        .financial-right {
            text-align: right;
            direction: rtl;
        }

        .vehicle-status {
            margin-top: 15px;
            text-align: center;
            border: 1px solid #000;
        }

        .vehicle-diagrams {
            display: table;
            width: 100%;
        }

        .diagram-section {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: top;
        }

        .diagram-title {
            font-size: 8px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .car-diagram {
            position: relative;
            background: #fff;
        }
        .car-diagram img {
            /*max-width: 100%;*/
            height: 125px;
        }

        .terms-section {
            margin-top: 20px;
            font-size: 7px;
            line-height: 1.3;
        }

        .terms-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 8px;
            text-align: center;
        }

        .terms-content {
            display: table;
            width: 100%;
        }

        .terms-left, .terms-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 0 5px;
        }

        .terms-right {
             direction: rtl;
             text-align: right;
         }

        .signature-section {
            margin-top: 20px;
            display: table;
            width: 100%;
        }

        .signature-left, .signature-right {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 10px;
        }

        .signature-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 5px;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            width: 200px;
            height: 40px;
            margin: 10px auto;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }

        .contact-info {
            display: table;
            width: 100%;
            margin-top: 10px;
        }

        .contact-item {
            display: table-cell;
            text-align: center;
            width: 33.33%;
        }

        .clear {
            clear: both;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <img src="{{ public_path('img/logo.png') }}" alt="Luxuria Logo" class="logo">
        </div>
        <div class="header-center">
            <div class="contract-title">LUXURIA Cars Rental LLC - Vehicle Rental Contract</div>
        </div>
        <div class="header-right">
            <div class="contract-code">Contract ID: {{ $contract->contract_number }}</div>
        </div>
    </div>

    <!-- Order Info and Barcode -->
{{--    <div class="barcode-section">--}}
{{--        <div style="font-size: 20px;">||||| |||| |||||</div>--}}
{{--    </div>--}}

    <div class="clear"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="main-content-left-side">
            <!-- Date and Lessee Info -->
            <table class="left-table">
                <tr>
                    <td style="width:33.33%;">Vehicle Information</td>
                    <td style="width:33.33%; text-decoration: underline"></td>
                    <td style="width:33.33%;text-align: right;">معلومات المركبة</td>
                </tr>
            </table>
        </div>

        <div class="main-content-right-side">
            <table class="right-table">
                <tr>
                    <td style="width:33.33%;">Client Information</td>
                    <td style="width:33.33%; text-decoration: underline"></td>
                    <td style="width:33.33%;text-align: right;">معلومات العميل</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Vehicle Information -->
    <div class="main-content">
        <div class="main-content-left-side">
            <table class="left-table">
                <tr>
                    <td style="width:33.33%">Date:</td>
                    <td style="width:33.33%;text-decoration: underline">{{ $contract->created_at->format('Y-m-d H:i:s') }}</td>
                    <td style="width:33.33%;text-align: right;" dir="rtl">التاريخ:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Make:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $vehicle->make }}</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">الصنع:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Model:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $vehicle->model }}</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">الطراز:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Color:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $vehicle->color }}</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">اللون:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Plate Number:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $vehicle->plate_number }}</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">رقم اللوحة:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Departure Km:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->mileage_limit ?? '0' }}</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">كم المغادرة:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Daily Km Limit:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->mileage_limit ?? '250' }}</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">كم اليومي المسموح:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Over KM Limit Charge:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->excess_mileage_rate ?? '1' }}/km</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">تجاوز حد كم المسموح:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Departure Petrol:</td>
                    <td style="width:33.33%; text-decoration: underline">50</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">الوقود عند المغادرة:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Order Type:</td>
                    <td style="width:33.33%; text-decoration: underline">يومي</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">نوع الطلب:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Rate:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ number_format($contract->daily_rate, 2) }} AED /Weekly</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">السعر:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Departure Date:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->start_date->format('Y-m-d H:i:s') }}</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">تاريخ المغادرة:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Agreed Return:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->end_date->format('Y-m-d H:i:s') }}</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">الموعد المتفق عليه للعودة:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Return Date:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->end_date->format('Y-m-d H:i:s') }}</td>
                    <td style="width:33.33%; text-align: right;" dir="rtl">تاريخ العودة:</td>
                </tr>
            </table>
        </div>
        <div class="main-content-right-side">
                <table class="right-table">
                    <tr>
                        <td style="width:33.33%; text-align: right;">الاسم:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;direction:auto;">{{ $customer->first_name }} {{ $customer->last_name }}</td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">Name:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%; text-align: right;">الجنسية:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;">{{ $customer->nationality ?? 'UAE' }}</td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">Nationality:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%; text-align: right;">تاريخ الميلاد:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;">{{ $customer->date_of_birth ?? '1978-10-15' }}</td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">Date of Birth:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%; text-align: right;">الهاتف:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;">{{ $customer->phone }}</td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">Phone:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%; text-align: right;">الجوال:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;">{{ $customer->phone }}</td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">Mobile:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%; text-align: right;">البريد الإلكتروني:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;">{{ $customer->email ?? '' }}</td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">Email:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%; text-align: right;">رقم الرخصة:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;">{{ $customer->drivers_license_number ?? '202826' }}</td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">License Number:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%; text-align: right;">مصدر الرخصة:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;">Ras Al Khaimah</td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">License Issued by:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%; text-align: right;">تاريخ إصدار الرخصة:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;">{{ $customer->drivers_license_expiry ? \Carbon\Carbon::parse($customer->drivers_license_expiry)->format('Y-m-d') : '2018-02-08' }}</td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">License Issued date:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%; text-align: right;">تاريخ انتهاء الرخصة:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;">{{ $customer->drivers_license_expiry ? \Carbon\Carbon::parse($customer->drivers_license_expiry)->format('Y-m-d') : '2026-03-28' }}</td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">License Expiry Date:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%; text-align: right;">عنوان المنزل:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;">{{ $customer->country ?? 'Dubai' }}</td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">Home address:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%; text-align: right;">رقم التأشيرة:</td>
                        <td style="width:33.33%; text-decoration: underline; text-align: left;"></td>
                        <td style="width:33.33%; text-align: left; direction: ltr;">Visa Number:</td>
                    </tr>
                </table>
        </div>
    </div>

    <!-- Customer Information -->

    <div class="clear"></div>

    <!-- Financial Information and Vehicle Status Side by Side -->
    <div class="main-content">
        <div class="main-content-left-side">
            <!-- Financial Information -->
            <div class="financial-section">
                <div class="financial-title">Financial Information &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">المعلومات المالية</span></div>

                <table class="financial-table" style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                    <tr style="height: 5px;">
                        <td style="width:33.33%;">Downpayment:</td>
                        <td style="width:33.33%;">_____________</td>
                        <td class="arabic-text" dir="rtl" style="width:33.33%;">دفعة مقدمة</td>
                    </tr>
                    <tr style="height: 5px;">
                        <td style="width:33.33%;">Rental Charges | Days:</td>
                        <td style="width:33.33%;">_____________</td>
                        <td class="arabic-text" dir="rtl" style="width:33.33%;">رسوم الايجار | الأيام</td>
                    </tr>
                    <tr style="height: 5px;">
                        <td style="width:33.33%;">Extra km /1km:</td>
                        <td style="width:33.33%;">_____________</td>
                        <td class="arabic-text" dir="rtl" style="width:33.33%;">الكيلومترات الاضافية</td>
                    </tr>
                    <tr style="height: 5px;">
                        <td style="width:33.33%;">Damages:</td>
                        <td style="width:33.33%;">_____________</td>
                        <td class="arabic-text" dir="rtl" style="width:33.33%;">بدل أضرار</td>
                    </tr>
                    <tr style="height: 5px;">
                        <td style="width:33.33%;">Salik:</td>
                        <td style="width:33.33%;">_____________</td>
                        <td class="arabic-text" dir="rtl" style="width:33.33%;">ساليك</td>
                    </tr>
                    <tr style="height: 5px;">
                        <td style="width:33.33%;">Traffic Fines:</td>
                        <td style="width:33.33%;">_____________</td>
                        <td class="arabic-text" dir="rtl" style="width:33.33%;">مخالفات مرورية</td>
                    </tr>
                    <tr style="height: 5px;">
                        <td style="width:33.33%;">Total Amount:</td>
                        <td style="width:33.33%;">{{ number_format($contract->total_amount, 2) }} AED</td>
                        <td class="arabic-text" dir="rtl" style="width:33.33%; text-align:right;">المبلغ الاجمالي</td>
                    </tr>
                    <tr style="height: 5px;">
                        <td style="width:33.33%;">Paid Amount:</td>
                        <td style="width:33.33%;">_____________</td>
                        <td class="arabic-text" dir="rtl" style="width:33.33%; text-align:right;">المبلغ المدفوع</td>
                    </tr>
                    <tr style="height: 5px;">
                        <td style="width:33.33%;">Remaining Amount:</td>
                        <td style="width:33.33%;">_____________</td>
                        <td class="arabic-text" dir="rtl" style="width:33.33%; text-align:right;">المبلغ المتبقي</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="main-content-right-side">
            <!-- Vehicle Status -->
            <div class="vehicle-status">
                <table style="width: 100%; font-weight: bold; margin-bottom: 2px;">
                    <tr>
                        <td>Vehicle Status</td>
                        <td dir="rtl" style="text-align: right;">حالة السيارة</td>
                    </tr>
                </table>

                <div class="vehicle-diagrams" style="padding-bottom: 5px;">
                    <div class="diagram-section">
                        <span>Return - العودة</span>
                        <div class="car-diagram">
                            <!-- Simple car outline -->
                            <img src="{{ public_path('img/carscheme.png') }}" style="max-width: 100%;" />
                        </div>
                    </div>

                    <div class="diagram-section">
                        <span>Departure - المغادرة</span>
                        <div class="car-diagram">
                            <!-- Simple car outline -->
                            <img src="{{ public_path('img/carscheme.png') }}" style="max-width: 100%;" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="clear"></div>

                 <!-- Terms and Conditions -->
         <div class="terms-section">
            <!-- Arabic Terms -->
            <div class="arabic-terms" style="direction: rtl; text-align: right; margin-bottom: 20px;">
                <h4 style="font-weight: bold; margin-bottom: 10px; font-size: 8px;">الشروط والأحكام:</h4>
                <div style="padding-right: 20px; line-height: 1.4; font-size: 7px;">
                    <p style="margin-bottom: 5px;">١. يجب ان يكون المستأجر او السائق لديه رخصة قيادة سيارة المفعول لدى إدارة و الترخيص بدولة الامارات العربية المتحدة.</p>
                    <p style="margin-bottom: 5px;">٢. يعتبر الكفيل في هذا العقد ملزم عن تنفيذ جميع البنود هذا العقد.</p>
                    <p style="margin-bottom: 5px;">٣. يتحمل المستأجر مسوؤلية صحة كافة البيانات و المعلومات المتعلقة به الواردة في العقد دون أدنى مسؤولية على المكتب او المالك.</p>
                    <p style="margin-bottom: 5px;">٤. يكون المستأجر مسؤول عن سداد جميع المستحقات عن هذا العقد و جميع المخالفات المرورية المترتبة على السيارة بتاريخ العقد ويلتزم تجاه المكتب بسداد قيمة جميع المستحقات.</p>
                    <p style="margin-bottom: 5px;">٥. يتحمل المستأجر جميع النفقات المتعلقة ببوابات التعرفة المرورية (سالك) و (درب).</p>
                    <p style="margin-bottom: 5px;">٦. لا يتم استلام السيارة الا بعد دفع جميع المستحقات الخاصة بالعقد.</p>
                    <p style="margin-bottom: 5px;">٧. مدة الايجار اليومي 24 ساعة تبدأ من وقت سريان العقد والمسافة اليومية المسموح بها هي 300 كيلومتر باليوم، ويحتسب 0.50 فلس عن كل كيلومتر زائد عن المسافة المسموح بها.</p>
                    <p style="margin-bottom: 5px;">٨. اذا كان العقد السيارة شهري او سنوي ويرغب المستأجر بإرجاع السيارة قبل اكتمال مدة العقد المتفق عليها سواء شهري او سنوي يتم حساب سعر الايجار اليومي للسيارة.</p>
                    <p style="margin-bottom: 5px;">٩. لا يحق للمستأجر تأجير السيارة او تسليمها لأي شخص غيره، كما لا يحق له رقن او بيع السيارة وفي حالة مخالفة الشرط يتحمل المستأجر و الكفيل كافة المسؤولية تجاه أي ضرر ولا يحق له مطالبة التأمين بأي تعويض.</p>
                    <p style="margin-bottom: 5px;">١٠. لا يحق المستأجر إضافة او إزالة أي جزء من أجزاء السيارة داخليا او خارجيا وعليه أداة السيارة بالحالة التي كانت عليها من قبل و يتحمل المستأجر كل النفقات نتيجة حدوث أي اضرار او اعطال.</p>
                    <p style="margin-bottom: 5px;">١١. في حالة مصادرة السيارة من قبل أي جهة لأي سبب كان او وقوع حادث او في حالة كان السائق تحت تأثير المشروبات الكحولية او أي مخدر أخر يترتب عليه دفع تعويض شامل عن أي ضرر للسيارة او الغير و يكون مسؤول مسؤولية كاملة امام القانون.</p>
                    <p style="margin-bottom: 5px;">١٢. على المستأجر ارجاع السيارة نظيفة او دفع 30 درهم بدل غسيل.</p>
                    <p style="margin-bottom: 5px;">١٣. يتحمل المستأجر كامل المسؤولية عن الاضرار التي قد تحدث للسيارة، وفي حال وقوع حادث سواء كان متسبب او متضرر فإنه ملزم بإصلاح السيارة على نفقته الشخصية ولا يحق له مطالبة من شركة التأمين او أي جهة ويلتزم بدفع ايجارالسيارة لحين خروجها من التصيلح.</p>
                    <p style="margin-bottom: 5px;">١٤. يتم حجز مبلغ مالي بقيمة 1500 درهم تأمين مخالفات يتم ارجاعه بعد خمسة عشر يوماً 15 يوم من تاريخ تسليم السيارة للمؤجر ولا يحق للمستأجر المطالبة بالمبلغ قبل الموعد المحدد واوافق بتحويل المخالفات على رخصتي بدون الرجوع الي.</p>
                    <p style="margin-bottom: 5px;">١٥. في حالة مشاركة السيارة في أي حادث:</p>
                    <p style="margin-bottom: 3px; margin-right: 15px;">أ. يجب على السيائق عدم مغادرة الحادث حتى تحضر الشرطة ويحصل على تقرير الحادث.</p>
                    <p style="margin-bottom: 3px; margin-right: 15px;">ب. على المستأجر إبلاغ المكتب عن الحادث والاضرار الناتجه عنه وأخذ موافقة خطية لتصليح السيارة.</p>
                    <p style="margin-bottom: 3px; margin-right: 15px;">ج. يتحمل المستأجر مبلغ 1000 درهم بالإضافة الى نسبة تحمل 15% من قيمة التصليح اذا كان المستأجر متسبب في الحادث.</p>
                    <p style="margin-bottom: 5px; margin-right: 15px;">د. يتحمل المستأجر (سواء كان مستبب او متضرر) قيمة الايجار كاملة وذلك حتى تسليم السيارة بعد إصلاحها واعادتها بالحالة التي كانت عليها قبل الحادث.</p>
                    <p style="margin-bottom: 5px;">١٦. في حال شطب السيارة من التأمين يتم دفع 35% من قيمة السيارة بغض النظر عن تعويض التأمين للمكتب.</p>
                    <p style="margin-bottom: 5px;">١٧. يحق للمؤجر إيقاف المركبة أو سحب السيارة في أي وقت دون الرجوع للمستأجر في حالة تأخره عن سداد المستحقات الخاصة بهذا العقد دون تحمل المؤجر أي مسؤولية عن أي متعلقات للمستأجر داخل السيارة.</p>
                    <p style="margin-bottom: 5px;">١٨. يتحمل المستأجر مبلغ 5000 درهم مصاريف فتح بلاغ اتعاب المحاماة.</p>
                    <p style="margin-bottom: 5px;">١٩. لا يتم استلام السيارة يوم الخميس و الجمعة و ما قبل العطلات الرسمية بيوم و العطلات الرسمية في الدولة، وعلى المستأجر مراعاة مواعيد العمل الخاصة بالمكتب.</p>
                    <p style="margin-bottom: 5px;">٢٠. اقر بأنني قد قرأت جميع الشروط و البنود الموجودة بهذا العقد و أوافق عليها.</p>
                </div>
            </div>

            <!-- English Terms -->
            <div style="margin-bottom: 20px;">
                <h4 style="font-weight: bold; margin-bottom: 10px; font-size: 8px;">Terms and Conditions:</h4>
                <ol style="padding-left: 20px; line-height: 1.4; font-size: 7px;">
                    <li style="margin-bottom: 5px;">The lessee or the driver shall have a valid Driver's License with the UAE Traffic and Licensing Department.</li>
                    <li style="margin-bottom: 5px;">The Guarantor shall be liable for implementing all terms and conditions of this Agreement.</li>
                    <li style="margin-bottom: 5px;">The lessee shall assume the responsibility for the correctness of all his data and information contained in this Agreement without any liability to the office or the owner.</li>
                    <li style="margin-bottom: 5px;">The lessee shall assume the responsibility for paying all fees required for the Agreement and for all traffic fines imposed on the vehicle from the date of this Agreement, and he shall also bear the fees owed to the office.</li>
                    <li style="margin-bottom: 5px;">The lessee shall bear all costs relating to the toll gates [Salik] and [Darb].</li>
                    <li style="margin-bottom: 5px;">The vehicle may only be received after paying all fees required for the Agreement.</li>
                    <li style="margin-bottom: 5px;">The duration of the daily rental is 24 hours starting from the Agreement validity time. The permitted distance is 300 km per day, each extra kilometre costs 0.50.</li>
                    <li style="margin-bottom: 5px;">If the vehicle Agreement is monthly or annually and the lessee desires to return the vehicle before the expiry date agreed upon, whether monthly or annually, the daily rental price of the vehicle shall be calculated.</li>
                    <li style="margin-bottom: 5px;">The lessee may neither lease nor hand over the vehicle to anyone else, he may further not mortgage or sell the vehicle. In case of violating this condition, the lessee and guarantor shall assume the entire responsibility for any damage and may not claim any compensation from the insurance company.</li>
                    <li style="margin-bottom: 5px;">The lessee may not add or remove any of the vehicle parts internally or externally, therefore, he shall return the vehicle to its previous state. The lessee shall bear the costs incurred for damages or malfunction.</li>
                    <li style="margin-bottom: 5px;">In case the vehicle seized by any entity for whatever reason or accident or in case the driver is drunk because of alcoholic beverages or any other drug, he shall pay comprehensive compensation for any damage that occurred to the vehicle or third parties and assume the whole responsibility before the law.</li>
                    <li style="margin-bottom: 5px;">The lessee shall return the vehicle clean or pay 30 dirhams as washing allowance.</li>
                    <li style="margin-bottom: 5px;">The lessee bears full responsibility for the damages that may occur to the car, and in the event of an accident, whether he was caused or aggrieved, he is obligated to repair the car at his personal expense, and he is not entitled to claim from the insurance company or any entity, and he is obligated to pay the car rent to complete the repair.</li>
                    <li style="margin-bottom: 5px;">An amount of 1,500 dirhams is reserved to ensure violations, which will be returned after fifteen days (15 days) from the date of delivery of the car to the lessor.</li>
                    <li style="margin-bottom: 5px;">In case the vehicle gets into an accident:</li>
                    <ul style="padding-left: 20px; line-height: 1.4; font-size: 7px;">
                        <li style="margin-bottom: 3px;">The driver shall not leave the accident place until the police come and draw up a report on the accident.</li>
                        <li style="margin-bottom: 3px;">The lessee shall notify the office of the accident and its damages and obtain the written approval so that he can repair the vehicle.</li>
                        <li style="margin-bottom: 3px;">The lessee shall pay an amount of 1000 dirhams as well as 10% of the repair fees in case he is the one who caused the accident.</li>
                        <li style="margin-bottom: 5px;">The lessee shall bear, whether caused or damaged, the rental fees until the vehicle is handed over, repaired and returned on its previous date before the accident.</li>
                    </ul>
                    <li style="margin-bottom: 5px;">In case the vehicle is deleted from the insurance, the rate of 35% of the vehicle value shall be paid to the office regardless of the compensation of the insurance.</li>
                    <li style="margin-bottom: 5px;">The lessor may suspend or withdraw the vehicle at any time without the consent of the lessee in case the latter is late in paying the dues related to this Agreement, and the lessor shall not assume any responsibility for any belongings of the lessee inside the vehicle.</li>
                    <li style="margin-bottom: 5px;">The lessee shall pay an amount of 5000 dirhams as expenses for filing a case other than the fees of the attorney.</li>
                    <li style="margin-bottom: 5px;">The vehicle shall not be received on Thursdays, Fridays, the days followed by official holidays and official holidays in the country, and the lessee shall respect the working hours of the office.</li>
                    <li style="margin-bottom: 5px;">I hereby acknowledge that I have read and accepted all terms and conditions set forth in this Agreement.</li>
                </ol>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
                         <div class="signature-left">
                 <div class="signature-title">Office In-Charge &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">مسؤول المكتب</span></div>
                 <div style="font-size: 8px; margin-bottom: 5px;">Employee Name: &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">اسم الموظف</span></div>
                <div style="font-size: 8px; margin-bottom: 10px;">Luxuria</div>
                <div class="signature-line"></div>

                <!-- QR Code placeholder -->
                <div style="margin-top: 10px;">
                    <svg width="50" height="50" style="border: 1px solid #000;">
                        <rect x="5" y="5" width="5" height="5" fill="#000"/>
                        <rect x="15" y="5" width="5" height="5" fill="#000"/>
                        <rect x="25" y="5" width="5" height="5" fill="#000"/>
                        <rect x="35" y="5" width="5" height="5" fill="#000"/>
                        <rect x="5" y="15" width="5" height="5" fill="#000"/>
                        <rect x="25" y="15" width="5" height="5" fill="#000"/>
                        <rect x="5" y="25" width="5" height="5" fill="#000"/>
                        <rect x="15" y="25" width="5" height="5" fill="#000"/>
                        <rect x="35" y="25" width="5" height="5" fill="#000"/>
                        <rect x="5" y="35" width="5" height="5" fill="#000"/>
                        <rect x="25" y="35" width="5" height="5" fill="#000"/>
                        <rect x="35" y="35" width="5" height="5" fill="#000"/>
                    </svg>
                </div>
            </div>

                         <div class="signature-right">
                 <div class="signature-title">Lessee Signature &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">توقيع المستأجر</span></div>
                 <div style="font-size: 8px; margin-bottom: 5px;">Name: &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">الاسم</span></div>
                <div style="font-size: 8px; margin-bottom: 10px;">{{ $customer->first_name }} {{ $customer->last_name }}</div>
                <div class="signature-line"></div>
            </div>
        </div>

    <!-- Footer -->
    <div class="footer">
        <div class="contact-info">
            <div class="contact-item">📞 +971 54 270 0030</div>
            <div class="contact-item">📍 United Arab Of Emirates</div>
            <div class="contact-item">✉ info@rentluxuria.com</div>
        </div>
    </div>
</body>
</html>
