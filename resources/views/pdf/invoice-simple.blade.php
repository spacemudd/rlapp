<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #222;
            background: #fff;
        }
        .header {
            border-bottom: 2px solid #222;
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .company-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }
        .logo {
            max-width: 200px;
            height: auto;
        }
        .company {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 2px;
            text-align: center;
            flex: 1;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .invoice-number {
            font-size: 16px;
            color: #666;
        }
        .info-table, .customer-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .info-table td, .customer-table td {
            padding: 4px 8px;
            border: none;
        }
        .info-table td.label, .customer-table td.label {
            color: #555;
            font-weight: bold;
            width: 120px;
        }
        .section-title {
            font-size: 15px;
            font-weight: bold;
            margin: 18px 0 8px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .items-table th, .items-table td {
            border: 1px solid #bbb;
            padding: 8px 6px;
            text-align: left;
        }
        .items-table th {
            background: #f7f7f7;
            font-size: 13px;
            font-weight: bold;
        }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .totals-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
        }
        .totals-table .label {
            text-align: left;
            color: #555;
            font-weight: bold;
            width: 50%;
        }
        .totals-table .value {
            text-align: right;
            font-weight: bold;
            font-size: 14px;
        }
        .totals-table .total-row {
            background-color: #f8f9fa;
        }
        .totals-table .total-row td {
            border-top: 2px solid #333;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #888;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <img src="{{ public_path('img/logo.png') }}" alt="Luxuria Cars Logo" class="logo">
        </div>
        <div class="invoice-info">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-number">#{{ $invoice->invoice_number }}</div>
        </div>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Invoice #</td>
            <td>{{ $invoice->invoice_number }}</td>
            <td class="label">Date</td>
            <td>{{ $invoice->invoice_date }}</td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td>{{ ucfirst($invoice->payment_status) }}</td>
            <td class="label">Due Date</td>
            <td>{{ $invoice->due_date }}</td>
        </tr>
        <tr>
            <td class="label">Currency</td>
            <td>AED</td>
            <td class="label">Order</td>
            <td>#{{ $invoice->order_number ?? $invoice->invoice_number }}</td>
        </tr>
        <tr>
            <td class="label">Vehicle</td>
            <td>{{ $vehicle->name ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">From</td>
            <td>{{ \Carbon\Carbon::parse($invoice->start_datetime)->format('d M Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">To</td>
            <td>{{ \Carbon\Carbon::parse($invoice->end_datetime)->format('d M Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Total Days</td>
            <td>{{ $invoice->total_days }} days</td>
        </tr>
    </table>

    <table class="customer-table">
        <tr>
            <td class="label">Customer</td>
            <td>{{ $customer->first_name }} {{ $customer->last_name }}</td>
        </tr>
        <tr>
            <td class="label">Address</td>
            <td>{{ $customer->address ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Nationality</td>
            <td>{{ $customer->country ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Mobile</td>
            <td>{{ $customer->phone ?? '' }}</td>
        </tr>
    </table>

    <div class="section-title">Items</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Amount</th>
                <th class="right">Discount</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="right">{{ number_format($item->amount, 2) }}</td>
                <td class="right">{{ number_format($item->discount, 2) }}</td>
                <td class="right">{{ number_format($item->amount - $item->discount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td class="label">Invoice Amount</td>
            <td class="value">{{ number_format($invoice->sub_total, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Paid Amount</td>
            <td class="value">{{ number_format($invoice->paid_amount, 2) }}</td>
        </tr>
        <tr class="total-row">
            <td class="label">Amount Due</td>
            <td class="value">{{ number_format(isset($amountDue) ? $amountDue : ($invoice->total_amount - $invoice->paid_amount), 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        Luxuria Cars Rental &mdash; Abu Dhabi, UAE &mdash; info@luxuriacars.com &mdash; +971 50 123 4567
    </div>
</body>
</html>
