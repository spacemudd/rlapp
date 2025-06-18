<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt</title>
    <style>
        @page {
            margin: 0;
            size: A4;
        }
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #222;
            font-size: 13px;
        }
        .receipt-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            width: 100vw;
        }
        .receipt {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 36px 36px 28px 36px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            background: #fff;
            box-sizing: border-box;
            text-align: center;
        }
        .logo {
            max-width: 120px;
            margin-bottom: 12px;
        }
        .company-name {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 2px;
            color: #2c5282;
            letter-spacing: 1px;
        }
        .receipt-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: 1px;
            color: #444;
        }
        .receipt-number {
            font-size: 13px;
            color: #888;
            margin-bottom: 18px;
        }
        .divider {
            border-top: 1.5px solid #e0e0e0;
            margin: 20px 0 20px 0;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c5282;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 4px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        .info-table tr {
            height: 28px;
        }
        .info-table td {
            text-align: left;
            padding: 2px 0 2px 2px;
            color: #333;
        }
        .info-table .label {
            color: #888;
            width: 120px;
            font-weight: 500;
        }
        .amount-box {
            margin: 22px 0 22px 0;
            padding: 16px 0;
            border: 1.5px solid #2c5282;
            border-radius: 8px;
            background: #f7fafc;
        }
        .amount-label {
            font-size: 14px;
            color: #2c5282;
            font-weight: 500;
        }
        .amount {
            font-size: 28px;
            font-weight: bold;
            color: #2c5282;
            margin-top: 2px;
            letter-spacing: 1px;
        }
        .footer {
            margin-top: 22px;
            font-size: 12px;
            color: #888;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 32px;
        }
        .signature-box {
            width: 45%;
            font-size: 11px;
        }
        .signature-line {
            border-top: 1px solid #bbb;
            margin: 32px 0 6px 0;
        }
        .signature-left {
            text-align: left;
        }
        .signature-right {
            text-align: right;
        }
        @media (max-width: 700px) {
            .receipt {
                padding: 12px 2vw;
                max-width: 98vw;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt">
            <img src="{{ public_path('img/logo.png') }}" alt="Company Logo" class="logo">
            <div class="company-name">Luxuria Cars Rental</div>
            <div class="receipt-title">Payment Receipt</div>
            <div class="receipt-number">Receipt #{{ $payment->id }}</div>
            <div class="divider"></div>
            <div class="section-title">Payment Details</div>
            <table class="info-table">
                <tr><td class="label">Date:</td><td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y H:i') }}</td></tr>
                <tr><td class="label">Method:</td><td>{{ $payment->payment_method }}</td></tr>
                <tr><td class="label">Reference:</td><td>{{ $payment->reference_number ?? 'N/A' }}</td></tr>
                <tr><td class="label">Type:</td><td>{{ ucfirst($payment->transaction_type) }}</td></tr>
                <tr><td class="label">Status:</td><td>{{ ucfirst($payment->status) }}</td></tr>
            </table>
            <div class="amount-box">
                <div class="amount-label">Amount Paid</div>
                <div class="amount">{{ number_format($payment->amount, 2) }} {{ $invoice->currency }}</div>
            </div>
            <div class="section-title">Invoice Information</div>
            <table class="info-table">
                <tr><td class="label">Invoice #:</td><td>{{ $invoice->invoice_number }}</td></tr>
                <tr><td class="label">Customer:</td><td>{{ $customer->first_name }} {{ $customer->last_name }}</td></tr>
                <tr><td class="label">Email:</td><td>{{ $customer->email }}</td></tr>
                <tr><td class="label">Phone:</td><td>{{ $customer->phone_number ?? 'N/A' }}</td></tr>
                <tr><td class="label">Vehicle:</td><td>{{ $vehicle->make }} {{ $vehicle->model }} ({{ $vehicle->plate_number }})</td></tr>
            </table>
            @if($payment->notes)
            <div class="section-title">Notes</div>
            <div style="text-align:left; color:#444; margin-bottom:10px;">{{ $payment->notes }}</div>
            @endif
            <div class="signature-section">
                <div class="signature-box signature-left">
                    <div class="signature-line"></div>
                    <div>Authorized Signature</div>
                </div>
                <div class="signature-box signature-right">
                    <div class="signature-line"></div>
                    <div>Customer Signature</div>
                </div>
            </div>
            <div class="footer">
                Thank you for choosing Luxuria Cars Rental!<br>
                This is a computer-generated receipt and does not require a signature.<br>
                Luxuria Cars Rental, Dubai, UAE | +971 XX XXX XXXX | info@luxuriacars.com
            </div>
        </div>
    </div>
</body>
</html>
