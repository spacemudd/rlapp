<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contract {{ $contract->contract_number }}</title>
    <style>
        @page {
            margin: 15mm;
            size: A4;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            line-height: 1.2;
            margin: 0;
            padding: 0;
            color: #000;
        }
        
        .arabic-text {
            font-family: 'Amiri', 'NotoSansArabic', serif;
            direction: rtl;
            unicode-bidi: embed;
            text-align: right;
            writing-mode: horizontal-tb;
        }
        
        .arabic-inline {
            font-family: 'Amiri', 'NotoSansArabic', serif;
            direction: rtl;
            unicode-bidi: isolate;
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
             font-family: 'Amiri', 'NotoSansArabic', serif;
             unicode-bidi: embed;
         }
        
        .main-content {
            clear: both;
            margin-top: 20px;
        }
        
        .section-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        
        .left-section, .right-section {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        
                 .right-section {
             padding-left: 15px;
             direction: rtl;
             text-align: right;
             font-family: 'Amiri', 'NotoSansArabic', serif;
             unicode-bidi: embed;
         }
        
        .field-row {
            margin-bottom: 3px;
            font-size: 8px;
        }
        
        .field-label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
            vertical-align: top;
        }
        
        .field-value {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 120px;
            padding-bottom: 1px;
        }
        
        .arabic-label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
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
        
        .financial-row {
            display: table;
            width: 100%;
            margin-bottom: 4px;
        }
        
        .financial-left, .financial-right {
            display: table-cell;
            width: 50%;
            font-size: 8px;
        }
        
                 .financial-right {
             text-align: right;
             direction: rtl;
             font-family: 'Amiri', 'NotoSansArabic', serif;
             unicode-bidi: embed;
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
             font-family: 'Amiri', 'NotoSansArabic', serif;
             unicode-bidi: embed;
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
        <div style="font-family: 'Libre Barcode 128', monospace; font-size: 20px;">||||| |||| |||||</div>
    </div>
    
         <div class="order-info">
         <div class="arabic-title">عقد إيجار مركبة</div>
         <div>Order No: {{ $contract->contract_number }}</div>
         <div class="arabic-text">رقم الطلب</div>
     </div>
    
    <div class="clear"></div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Date and Lessee Info -->
        <div class="section-row">
            <div class="left-section">
                <div class="field-row">
                    <span class="field-label">Date:</span>
                    <span class="field-value">{{ $contract->created_at->format('Y-m-d H:i:s') }}</span>
                </div>
            </div>
                         <div class="right-section">
                 <div class="field-row">
                     <span class="arabic-label arabic-text">التاريخ</span>
                     <span class="arabic-value arabic-text">المستأجر</span>
                     <span class="arabic-label">lessee</span>
                 </div>
             </div>
        </div>

        <!-- Vehicle Information -->
        <div class="section-row">
            <div class="left-section">
                <div style="font-weight: bold; margin-bottom: 8px;">Vehicle</div>
                
                <div class="field-row">
                    <span class="field-label">Make:</span>
                    <span class="field-value">{{ $vehicle->make }}</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Model:</span>
                    <span class="field-value">{{ $vehicle->model }}</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Color:</span>
                    <span class="field-value">{{ $vehicle->color }}</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Plate Number:</span>
                    <span class="field-value">{{ $vehicle->plate_number }}</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Departure Km:</span>
                    <span class="field-value">{{ $contract->mileage_limit ?? '0' }}</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Daily Km Limit:</span>
                    <span class="field-value">{{ $contract->mileage_limit ?? '250' }}</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Over KM Limit Charge:</span>
                    <span class="field-value">{{ $contract->excess_mileage_rate ?? '1' }}/km</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Departure Petrol:</span>
                    <span class="field-value">50</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Order Type:</span>
                    <span class="field-value">يومي</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Rate:</span>
                    <span class="field-value">{{ number_format($contract->daily_rate, 2) }} AED /Weekly</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Departure Date:</span>
                    <span class="field-value">{{ $contract->start_date->format('Y-m-d H:i:s') }}</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Agreed Return:</span>
                    <span class="field-value">{{ $contract->end_date->format('Y-m-d H:i:s') }}</span>
                </div>
                
                <div class="field-row">
                    <span class="field-label">Return Date:</span>
                    <span class="field-value">{{ $contract->end_date->format('Y-m-d H:i:s') }}</span>
                </div>
            </div>
            
                         <div class="right-section">
                 <div style="font-weight: bold; margin-bottom: 8px;" class="arabic-text">المركبة</div>
                
                                 <div class="field-row">
                     <span class="arabic-label arabic-text">الصنع:</span>
                     <span class="arabic-value">{{ $customer->first_name }} {{ $customer->last_name }}</span>
                     <span class="arabic-label">Name (Arabic):</span>
                 </div>
                 
                 <div class="field-row">
                     <span class="arabic-label arabic-text">الطراز:</span>
                     <span class="arabic-value">{{ $customer->first_name }} {{ $customer->last_name }}</span>
                     <span class="arabic-label">Name:</span>
                 </div>
                 
                 <div class="field-row">
                     <span class="arabic-label arabic-text">اللون:</span>
                     <span class="arabic-value arabic-text">{{ $customer->nationality ?? 'مصر' }}</span>
                     <span class="arabic-label">Nationality:</span>
                 </div>
                
                <div class="field-row">
                    <span class="arabic-label">رقم اللوحة:</span>
                    <span class="arabic-value">{{ $customer->date_of_birth ?? '1978-10-15' }}</span>
                    <span class="arabic-label">Date of Birth:</span>
                </div>
                
                <div class="field-row">
                    <span class="arabic-label">كم المغادرة:</span>
                    <span class="arabic-value">{{ $customer->phone }}</span>
                    <span class="arabic-label">Phone:</span>
                </div>
                
                <div class="field-row">
                    <span class="arabic-label">كم اليومي المسموح:</span>
                    <span class="arabic-value">{{ $customer->phone }}</span>
                    <span class="arabic-label">Mobile:</span>
                </div>
                
                <div class="field-row">
                    <span class="arabic-label">تجاوز حد كم المسموح:</span>
                    <span class="arabic-value">{{ $customer->email ?? '' }}</span>
                    <span class="arabic-label">E-Mail:</span>
                </div>
                
                <div class="field-row">
                    <span class="arabic-label">الوقود عند المغادرة:</span>
                    <span class="arabic-value">{{ $customer->drivers_license_number ?? '202826' }}</span>
                    <span class="arabic-label">License Number:</span>
                </div>
                
                <div class="field-row">
                    <span class="arabic-label">نوع الطلب:</span>
                    <span class="arabic-value">Ras Al Khaimah</span>
                    <span class="arabic-label">License Issued by:</span>
                </div>
                
                <div class="field-row">
                    <span class="arabic-label">السعر:</span>
                    <span class="arabic-value">{{ $customer->drivers_license_expiry ? \Carbon\Carbon::parse($customer->drivers_license_expiry)->format('Y-m-d') : '2018-02-08' }}</span>
                    <span class="arabic-label">License Issued date:</span>
                </div>
                
                <div class="field-row">
                    <span class="arabic-label">تاريخ المغادرة:</span>
                    <span class="arabic-value">{{ $customer->drivers_license_expiry ? \Carbon\Carbon::parse($customer->drivers_license_expiry)->format('Y-m-d') : '2026-03-28' }}</span>
                    <span class="arabic-label">License Expiry Date:</span>
                </div>
                
                <div class="field-row">
                    <span class="arabic-label">الموعد المتفق عليه للعودة:</span>
                    <span class="arabic-value">{{ $customer->country ?? 'Dubai' }}</span>
                    <span class="arabic-label">Home address:</span>
                </div>
                
                <div class="field-row">
                    <span class="arabic-label">تاريخ العودة:</span>
                    <span class="arabic-value"></span>
                    <span class="arabic-label">Visa Number:</span>
                </div>
            </div>
        </div>

                 <!-- Financial Information -->
         <div class="financial-section">
             <div class="financial-title">Financial Information &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline">المعلومات المالية</span></div>
            
            <div class="financial-row">
                <div class="financial-left">
                    <div>Downpayment: _____________</div>
                    <div>Rental Charges | Days: _____________</div>
                    <div>Extra km /1km: _____________</div>
                    <div>Damages: _____________</div>
                    <div>Salik: _____________</div>
                    <div>Traffic Fines: _____________</div>
                    <div><strong>Total Amount: {{ number_format($contract->total_amount, 2) }} AED</strong></div>
                    <div><strong>Paid Amount: _____________</strong></div>
                    <div><strong>Remaining Amount: _____________</strong></div>
                </div>
                
                                 <div class="financial-right">
                     <div class="arabic-text">دفعة مقدمة</div>
                     <div class="arabic-text">رسوم الايجار | الأيام</div>
                     <div class="arabic-text">الكيلومترات الاضافية</div>
                     <div class="arabic-text">بدل أضرار</div>
                     <div class="arabic-text">ساليك</div>
                     <div class="arabic-text">مخالفات مرورية</div>
                     <div><strong class="arabic-text">المبلغ الاجمالي</strong></div>
                     <div><strong class="arabic-text">المبلغ المدفوع</strong></div>
                     <div><strong class="arabic-text">المبلغ المتبقي</strong></div>
                 </div>
            </div>
        </div>

                 <!-- Vehicle Status -->
         <div class="vehicle-status">
             <div style="font-weight: bold; margin-bottom: 10px;">Vehicle Status &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline">حالة السيارة</span></div>
            
            <div class="vehicle-diagrams">
                                 <div class="diagram-section">
                     <div class="diagram-title">Return &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline">العودة</span></div>
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
                     <div class="diagram-title">Departure &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline">المغادرة</span></div>
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
             <div class="terms-title">Terms and Conditions &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline">الشروط والاحكام</span></div>
            
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
                     <p class="arabic-text">1. يجب أن يكون المستأجم أو السائق لديه رخصة قيادة سارية المفعول.</p>
                     <p class="arabic-text">2. يتحر المستأجر في هذا الاتفاق عن تنفيذ جميع بنود هذا العقد.</p>
                     <p class="arabic-text">3. يتحمل المستأجر المسؤولية عن صحة بياناته ومعلوماته الواردة في هذا الاتفاق دون أدنى مسؤولية على المكتب أو المالك.</p>
                     <p class="arabic-text">4. يتحمل المستأجر مسؤولية عن جميع الوثائق المطلوبة للاتفاق وعن جميع المخالفات المرورية المترتبة على السيارة خلال فترة الاتفاق وعليه أيضاً تحمل الرسوم المستحقة للمكتب.</p>
                     <p class="arabic-text">5. يتحمل المستأجر جميع التكاليف المتعلقة ببوابات التحصيل الضريبي (ساليك).</p>
                     <p class="arabic-text">6. لا يتم استلام السيارة إلا بعد دفع جميع الرسوم المطلوبة للاتفاق.</p>
                     <p class="arabic-text">7. مدة الايجار اليومي 24 ساعة تبدأ من وقت سريان الاتفاق والمسافة المسموحة يومياً 300 كيلومتر وكل كيلومتر زائد يكلف 0.50 فلس.</p>
                     <p class="arabic-text">8. إذا كان عقد السيارة شهري أو سنوي وتجاوز المستأجر المسافة الشهرية فعليه دفع ما تم الاتفاق عليه سواء شهرياً أو سنوياً يحسب السعر اليومي للسيارة.</p>
                     <p class="arabic-text">9. لا يحق له تأجير أو تسليم السيارة وفي حالة مخالفة هذا الشرط يتحمل الضامن كامل المسؤولية عن أي ضرر قد لا يطالب به من شركة التأمين.</p>
                     <p class="arabic-text">10. لا يحق للمستأجر إزالة أو إضافة أي من قطع غيار السيارة داخلياً أو خارجياً وعليه إعادة السيارة على حالتها الأصلية ويتحمل المستأجر تكلفة قطع الغيار أو أي تعديلات.</p>
                 </div>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
                         <div class="signature-left">
                 <div class="signature-title">Office In-Charge &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline">مسؤول المكتب</span></div>
                 <div style="font-size: 8px; margin-bottom: 5px;">Employee Name: &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline">اسم الموظف</span></div>
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
                 <div class="signature-title">lessee Signature &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline">توقيع المستأجر</span></div>
                 <div style="font-size: 8px; margin-bottom: 5px;">Name: &nbsp;&nbsp;&nbsp;&nbsp; <span class="arabic-inline">الاسم</span></div>
                <div style="font-size: 8px; margin-bottom: 10px;">{{ $customer->first_name }} {{ $customer->last_name }}</div>
                <div class="signature-line"></div>
            </div>
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