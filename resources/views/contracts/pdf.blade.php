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
            margin: 15mm;
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
        }

        .arabic-inline {
            direction: rtl;
            display: inline-block;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
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

        .contract-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
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
            margin-top: 20px;
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
            /*width: 100%;*/
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .financial-table td {
            width: 50%;
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
        }

        .vehicle-diagrams {
            display: table;
            width: 100%;
            margin-top: 10px;
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
            width: 120px;
            height: 80px;
            border: 2px solid #000;
            margin: 0 auto;
            position: relative;
            background: #fff;
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
        <div class="company-name">LUXURIA</div>
        <div class="company-subtitle">CARS RENTAL</div>
        <div class="contract-title">Vehicle Rental Contract</div>
    </div>

    <!-- Order Info and Barcode -->
    <div class="barcode-section">
        <div style="font-size: 20px;">||||| |||| |||||</div>
    </div>

         <div class="order-info">
         <div class="arabic-title" dir="rtl">عقد إيجار مركبة</div>
         <div>Order No: {{ $contract->contract_number }}</div>
         <div class="arabic-text" dir="rtl">رقم الطلب</div>
     </div>

    <div class="clear"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="main-content-left-side" style="background-color:purple;">
            <!-- Date and Lessee Info -->
            <table class="left-table">
                <tr>
                    <td style="width:33.33%;">Vehicle Information</td>
                    <td style="width:33.33%; text-decoration: underline"></td>
                    <td style="width:33.33%;text-align: right;">معلومات المركبة</td>
                </tr>
            </table>
        </div>

        <div class="main-content-right-side" style="background-color:orange;">
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
        <div class="main-content-left-side" style="background-color:red;">
            <table class="left-table">
                <tr>
                    <td style="width:33.33%">Date:</td>
                    <td style="width:33.33%;text-decoration: underline">{{ $contract->created_at->format('Y-m-d H:i:s') }}</td>
                    <td style="width:33.33%;text-align: right;">التاريخ:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Make:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $vehicle->make }}</td>
                    <td style="width:33.33%; text-align: right;">الصنع:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Model:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $vehicle->model }}</td>
                    <td style="width:33.33%; text-align: right;">الطراز:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Color:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $vehicle->color }}</td>
                    <td style="width:33.33%; text-align: right;">اللون:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Plate Number:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $vehicle->plate_number }}</td>
                    <td style="width:33.33%; text-align: right;">رقم اللوحة:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Departure Km:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->mileage_limit ?? '0' }}</td>
                    <td style="width:33.33%; text-align: right;">كم المغادرة:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Daily Km Limit:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->mileage_limit ?? '250' }}</td>
                    <td style="width:33.33%; text-align: right;">كم اليومي المسموح:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Over KM Limit Charge:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->excess_mileage_rate ?? '1' }}/km</td>
                    <td style="width:33.33%; text-align: right;">تجاوز حد كم المسموح:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Departure Petrol:</td>
                    <td style="width:33.33%; text-decoration: underline">50</td>
                    <td style="width:33.33%; text-align: right;">الوقود عند المغادرة:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Order Type:</td>
                    <td style="width:33.33%; text-decoration: underline">يومي</td>
                    <td style="width:33.33%; text-align: right;">نوع الطلب:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Rate:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ number_format($contract->daily_rate, 2) }} AED /Weekly</td>
                    <td style="width:33.33%; text-align: right;">السعر:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Departure Date:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->start_date->format('Y-m-d H:i:s') }}</td>
                    <td style="width:33.33%; text-align: right;">تاريخ المغادرة:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Agreed Return:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->end_date->format('Y-m-d H:i:s') }}</td>
                    <td style="width:33.33%; text-align: right;">الموعد المتفق عليه للعودة:</td>
                </tr>
                <tr>
                    <td style="width:33.33%">Return Date:</td>
                    <td style="width:33.33%; text-decoration: underline">{{ $contract->end_date->format('Y-m-d H:i:s') }}</td>
                    <td style="width:33.33%; text-align: right;">تاريخ العودة:</td>
                </tr>
            </table>
        </div>
        <div class="main-content-right-side" style="background-color:green;">
                <table class="right-table">
                    <tr>
                        <td style="width:33.33%">Name:</td>
                        <td style="width:33.33%; text-decoration: underline">{{ $customer->first_name }} {{ $customer->last_name }}</td>
                        <td style="width:33.33%; text-align: right;">الاسم:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%">Nationality:</td>
                        <td style="width:33.33%; text-decoration: underline">{{ $customer->nationality ?? 'UAE' }}</td>
                        <td style="width:33.33%; text-align: right;">الجنسية:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%">Date of Birth:</td>
                        <td style="width:33.33%; text-decoration: underline">{{ $customer->date_of_birth ?? '1978-10-15' }}</td>
                        <td style="width:33.33%; text-align: right;">تاريخ الميلاد:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%">Phone:</td>
                        <td style="width:33.33%; text-decoration: underline">{{ $customer->phone }}</td>
                        <td style="width:33.33%; text-align: right;">الهاتف:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%">Mobile:</td>
                        <td style="width:33.33%; text-decoration: underline">{{ $customer->phone }}</td>
                        <td style="width:33.33%; text-align: right;">الجوال:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%">Email:</td>
                        <td style="width:33.33%; text-decoration: underline">{{ $customer->email ?? '' }}</td>
                        <td style="width:33.33%; text-align: right;">البريد الإلكتروني:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%">License Number:</td>
                        <td style="width:33.33%; text-decoration: underline">{{ $customer->drivers_license_number ?? '202826' }}</td>
                        <td style="width:33.33%; text-align: right;">رقم الرخصة:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%">License Issued by:</td>
                        <td style="width:33.33%; text-decoration: underline">Ras Al Khaimah</td>
                        <td style="width:33.33%; text-align: right;">مصدر الرخصة:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%">License Issued date:</td>
                        <td style="width:33.33%; text-decoration: underline">{{ $customer->drivers_license_expiry ? \Carbon\Carbon::parse($customer->drivers_license_expiry)->format('Y-m-d') : '2018-02-08' }}</td>
                        <td style="width:33.33%; text-align: right;">تاريخ إصدار الرخصة:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%">License Expiry Date:</td>
                        <td style="width:33.33%; text-decoration: underline">{{ $customer->drivers_license_expiry ? \Carbon\Carbon::parse($customer->drivers_license_expiry)->format('Y-m-d') : '2026-03-28' }}</td>
                        <td style="width:33.33%; text-align: right;">تاريخ انتهاء الرخصة:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%">Home address:</td>
                        <td style="width:33.33%; text-decoration: underline">{{ $customer->country ?? 'Dubai' }}</td>
                        <td style="width:33.33%; text-align: right;">عنوان المنزل:</td>
                    </tr>
                    <tr>
                        <td style="width:33.33%">Visa Number:</td>
                        <td style="width:33.33%; text-decoration: underline"></td>
                        <td style="width:33.33%; text-align: right;">رقم التأشيرة:</td>
                    </tr>
                </table>
        </div>
    </div>

    <!-- Customer Information -->

    <div class="clear"></div>

                                  <!-- Financial Information -->
         <div class="financial-section">
             <div class="financial-title">Financial Information &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">المعلومات المالية</span></div>

            <table class="financial-table">
                <tr>
                    <td>
                        <div>Downpayment: _____________</div>
                        <div>Rental Charges | Days: _____________</div>
                        <div>Extra km /1km: _____________</div>
                        <div>Damages: _____________</div>
                        <div>Salik: _____________</div>
                        <div>Traffic Fines: _____________</div>
                        <div><strong>Total Amount: {{ number_format($contract->total_amount, 2) }} AED</strong></div>
                        <div><strong>Paid Amount: _____________</strong></div>
                        <div><strong>Remaining Amount: _____________</strong></div>
                    </td>
                    <td class="financial-right">
                        <div class="arabic-text" dir="rtl">دفعة مقدمة</div>
                        <div class="arabic-text" dir="rtl">رسوم الايجار | الأيام</div>
                        <div class="arabic-text" dir="rtl">الكيلومترات الاضافية</div>
                        <div class="arabic-text" dir="rtl">بدل أضرار</div>
                        <div class="arabic-text" dir="rtl">ساليك</div>
                        <div class="arabic-text" dir="rtl">مخالفات مرورية</div>
                        <div><strong class="arabic-text" dir="rtl">المبلغ الاجمالي</strong></div>
                        <div><strong class="arabic-text" dir="rtl">المبلغ المدفوع</strong></div>
                        <div><strong class="arabic-text" dir="rtl">المبلغ المتبقي</strong></div>
                    </td>
                </tr>
            </table>
        </div>

                 <!-- Vehicle Status -->
         <div class="vehicle-status">
             <div style="font-weight: bold; margin-bottom: 10px;">Vehicle Status &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">حالة السيارة</span></div>

            <div class="vehicle-diagrams">
                                 <div class="diagram-section">
                     <div class="diagram-title">Return &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">العودة</span></div>
                    <div class="car-diagram">
                        <!-- Simple car outline -->
                        <svg width="120" height="80" style="border: 1px solid #000;">
                            <rect x="20" y="15" width="80" height="50" fill="none" stroke="#000" stroke-width="1"/>
                            <rect x="10" y="25" width="15" height="30" fill="none" stroke="#000" stroke-width="1"/>
                            <rect x="95" y="25" width="15" height="30" fill="none" stroke="#000" stroke-width="1"/>
                            <circle cx="30" cy="70" r="8" fill="none" stroke="#000" stroke-width="1"/>
                            <circle cx="90" cy="70" r="8" fill="none" stroke="#000" stroke-width="1"/>
                        </svg>
                    </div>
                </div>

                                 <div class="diagram-section">
                     <div class="diagram-title">Departure &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">المغادرة</span></div>
                    <div class="car-diagram">
                        <!-- Simple car outline -->
                        <svg width="120" height="80" style="border: 1px solid #000;">
                            <rect x="20" y="15" width="80" height="50" fill="none" stroke="#000" stroke-width="1"/>
                            <rect x="10" y="25" width="15" height="30" fill="none" stroke="#000" stroke-width="1"/>
                            <rect x="95" y="25" width="15" height="30" fill="none" stroke="#000" stroke-width="1"/>
                            <circle cx="30" cy="70" r="8" fill="none" stroke="#000" stroke-width="1"/>
                            <circle cx="90" cy="70" r="8" fill="none" stroke="#000" stroke-width="1"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

                 <!-- Terms and Conditions -->
         <div class="terms-section">
             <div class="terms-title">Terms and Conditions &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">الشروط والاحكام</span></div>

            <div class="terms-content">
                <div class="terms-left">
                    <p>1. The lessee or the driver shall have a valid Driver's License from the UAE Traffic and Licensing Department.</p>
                    <p>2. The lessee shall be responsible for implementing all the conditions of this Agreement.</p>
                    <p>3. The lessee shall assume the responsibility for the correctness of all his data and information contained in this Agreement without any liability on the office or the owner.</p>
                    <p>4. The lessee shall be responsible for all the documents required for the Agreement and for all traffic fines imposed on the vehicle during the rental Agreement, and he shall also bear the fees owed to the office.</p>
                    <p>5. The lessee shall bear all costs relating to the toll gates fees (Salik).</p>
                    <p>6. The vehicle may only be received after paying all fees required for the Agreement.</p>
                    <p>7. The duration of the daily rental is 24 hours starting from the Agreement validity time. The permitted distance is 300 km per day, and each extra kilometre costs 0.50.</p>
                    <p>8. If the Vehicle Agreement is monthly or annually and the lessee exceeds the monthly distance, he shall pay for the agreed upon, whether monthly or annually, the daily rental rate of the vehicle is calculated.</p>
                    <p>9. The lessee may neither lease nor hand over the vehicle to anyone else, nor may he further lease or mortgage or sell the vehicle, and he may not use it for any commercial purposes. The guarantor shall assume the entire responsibility for any damage that may not be claimed from the insurance company.</p>
                    <p>10. The lessee may not remove or add any of the vehicle parts internally or externally, therefore, he shall return the vehicle in its original state. The lessee shall bear the cost of the spare parts or any modifications.</p>
                </div>

                                 <div class="terms-right">
                     <p class="arabic-text" dir="rtl">1. يجب أن يكون المستأجم أو السائق لديه رخصة قيادة سارية المفعول.</p>
                     <p class="arabic-text" dir="rtl">2. يتحر المستأجر في هذا الاتفاق عن تنفيذ جميع بنود هذا العقد.</p>
                     <p class="arabic-text" dir="rtl">3. يتحمل المستأجر المسؤولية عن صحة بياناته ومعلوماته الواردة في هذا الاتفاق دون أدنى مسؤولية على المكتب أو المالك.</p>
                     <p class="arabic-text" dir="rtl">4. يتحمل المستأجر مسؤولية عن جميع الوثائق المطلوبة للاتفاق وعن جميع المخالفات المرورية المترتبة على السيارة خلال فترة الاتفاق وعليه أيضاً تحمل الرسوم المستحقة للمكتب.</p>
                     <p class="arabic-text" dir="rtl">5. يتحمل المستأجر جميع التكاليف المتعلقة ببوابات التحصيل الضريبي (ساليك).</p>
                     <p class="arabic-text" dir="rtl">6. لا يتم استلام السيارة إلا بعد دفع جميع الرسوم المطلوبة للاتفاق.</p>
                     <p class="arabic-text" dir="rtl">7. مدة الايجار اليومي 24 ساعة تبدأ من وقت سريان الاتفاق والمسافة المسموحة يومياً 300 كيلومتر وكل كيلومتر زائد يكلف 0.50 فلس.</p>
                     <p class="arabic-text" dir="rtl">8. إذا كان عقد السيارة شهري أو سنوي وتجاوز المستأجر المسافة الشهرية فعليه دفع ما تم الاتفاق عليه سواء شهرياً أو سنوياً يحسب السعر اليومي للسيارة.</p>
                     <p class="arabic-text" dir="rtl">9. لا يحق له تأجير أو تسليم السيارة وفي حالة مخالفة هذا الشرط يتحمل الضامن كامل المسؤولية عن أي ضرر قد لا يطالب به من شركة التأمين.</p>
                     <p class="arabic-text" dir="rtl">10. لا يحق للمستأجر إزالة أو إضافة أي من قطع غيار السيارة داخلياً أو خارجياً وعليه إعادة السيارة على حالتها الأصلية ويتحمل المستأجر تكلفة قطع الغيار أو أي تعديلات.</p>
                 </div>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
                         <div class="signature-left">
                 <div class="signature-title">Office In-Charge &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">مسؤول المكتب</span></div>
                 <div style="font-size: 8px; margin-bottom: 5px;">Employee Name: &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">اسم الموظف</span></div>
                <div style="font-size: 8px; margin-bottom: 10px;">luxuria Dubai</div>
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
                 <div class="signature-title">lessee Signature &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline" dir="rtl">توقيع المستأجر</span></div>
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
