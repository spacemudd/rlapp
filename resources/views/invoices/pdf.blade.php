<!DOCTYPE html>
<html>
<head>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Roboto', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            font-size: 9px; /* Slightly smaller base font size */
        }

        .invoice-box {
            max-width: 780px; /* Slightly narrower to gain vertical space */
            margin: 15px auto; /* Reduced top/bottom margin */
            padding: 25px; /* Reduced padding */
            border: 1px solid #e0e0e0;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06); /* Lighter shadow */
            font-size: 13px; /* Slightly smaller main font size */
            line-height: 18px;
            color: #555;
            background: #fff;
        }

        .invoice-box table {
            width: 100%;
            line-height: inherit;
            text-align: left;
            border-collapse: collapse;
        }

        .invoice-box table td {
            padding: 6px 5px; /* Reduced padding */
            vertical-align: top;
        }

        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }

        .invoice-box table tr.top table td {
            padding-bottom: 20px; /* Reduced padding */
        }

        .invoice-box table tr.top table td.title {
            font-size: 32px; /* Smaller title font size */
            line-height: 1;
            color: #333;
            padding: 0;
        }

        .invoice-box table tr.information table td {
            padding-bottom: 20px; /* Reduced padding */
        }

        .invoice-box table tr.heading td {
            background: #f8f8f8;
            border-bottom: 1px solid #e0e0e0;
            border-top: 1px solid #e0e0e0;
            font-weight: bold;
            padding: 8px 6px; /* Reduced padding */
            text-transform: uppercase;
            font-size: 10px; /* Smaller heading font size */
        }

        .invoice-box table tr.details td {
            padding-bottom: 15px; /* Reduced padding */
        }

        .invoice-box table tr.item td {
            border-bottom: 1px solid #f0f0f0;
            padding: 8px 6px; /* Reduced padding */
        }

        .invoice-box table tr.item.last td {
            border-bottom: none;
        }

        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #e0e0e0;
            font-weight: bold;
        }

        .item-table {
            margin-bottom: 15px; /* Reduced margin */
        }

        .item-table th, .item-table td {
            border: 1px solid #e0e0e0;
            padding: 8px 6px; /* Reduced padding */
            text-align: left;
            font-size: 11px; /* Slightly smaller item font size */
        }

        .item-table th {
            background-color: #f8f8f8;
            font-weight: bold;
            text-transform: uppercase;
        }

        .text-bold {
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .text-center {
            text-align: center;
        }

        .invoice-status-unpaid {
            color: #e74c3c;
            font-weight: bold;
        }
        .invoice-status-paid {
            color: #27ae60;
            font-weight: bold;
        }
        .invoice-status-partial {
            color: #f39c12;
            font-weight: bold;
        }

        .total-summary td {
            padding: 8px 6px; /* Reduced padding */
            border: 1px solid #e0e0e0;
        }
        .total-summary .label {
            font-weight: bold;
            text-align: left;
            background-color: #f8f8f8;
        }
        .total-summary .value {
            text-align: right;
            font-weight: bold;
        }

        .invoice-to-box {
            border: 1px solid #e0e0e0;
            padding: 12px; /* Reduced padding */
            width: 48%;
            float: right;
            margin-top: -5px; /* Adjusted margin */
            font-size: 11px; /* Smaller font size */
            background-color: #fcfcfc;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .header-section td {
            padding-bottom: 8px; /* Reduced padding */
        }
        .customer-info-box {
            border: 1px solid #e0e0e0;
            padding: 12px;
            margin-top: 10px; /* Reduced margin */
            background-color: #fcfcfc;
            font-size: 11px;
            width: 48%;
            float: left;
        }
        .vehicle-info-box {
            border: 1px solid #e0e0e0;
            padding: 12px;
            margin-top: 10px; /* Reduced margin */
            background-color: #fcfcfc;
            font-size: 11px;
            width: 48%;
            float: right;
        }
        .details-group {
            margin-bottom: 6px; /* Reduced margin */
        }
        .details-group span {
            display: block;
            margin-bottom: 2px; /* Reduced margin */
        }
        .details-group span.label {
            font-weight: normal;
            color: #777;
            font-size: 9px; /* Smaller label font size */
        }
        .invoice-main-details {
            width: 50%;
            float: left;
        }
        .customer-vehicle-row {
            width: 100%;
            display: inline-block;
            margin-top: 15px; /* Reduced margin */
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <table>
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title" style="width: 50%;">
                                <img src="data:image/png;base64,{{ $luxuriaLogo }}" style="width:100%; max-width:130px; margin-bottom: 5px;"> <!-- Adjusted max-width -->
                                <div style="font-size: 9px; margin-top: 2px; letter-spacing: 1.5px;">C A R S &nbsp; R E N T A L</div> <!-- Adjusted font size and letter spacing -->
                            </td>
                            <td class="text-right" style="width: 50%; vertical-align: top;">
                                <div style="font-size: 20px; font-weight: bold; color: #333; margin-bottom: 3px;">INVOICE</div> <!-- Adjusted font size -->
                                <div style="font-size: 14px; color: #666;">#{{ $invoice->invoice_number }}</div> <!-- Adjusted font size -->
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="information">
                <td colspan="2">
                    <table style="width:100%;">
                        <tr>
                            <td class="text-left" style="width: 50%; vertical-align: top;">
                                Invoice: <span class="text-bold">#{{ $invoice->invoice_number }}</span><br>
                                Invoice Date: <span class="text-bold">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d H:i') }}</span><br>
                                Due Date: <span class="text-bold">{{ \Carbon\Carbon::parse($invoice->due_date)->format('Y-m-d H:i') }}</span><br>
                                Invoice Status: <span class="text-bold invoice-status-{{ $invoice->payment_status }}">{{ ucfirst($invoice->payment_status) }}</span><br>
                                Currency: <span class="text-bold">AED</span><br>
                                Number of Days: <span class="text-bold">{{ $invoice->total_days }} Days</span><br>
                                -- from {{ \Carbon\Carbon::parse($invoice->start_datetime)->format('Y-m-d H:i') }} to {{ \Carbon\Carbon::parse($invoice->end_datetime)->format('Y-m-d H:i') }}<br>
                                Order: <span class="text-bold">#{{ $invoice->invoice_number }}</span><br>
                                Order Status: <span class="text-bold">Completed</span><br>
                                Vehicle: <span class="text-bold">#{{ $invoice->vehicle->plate_number }} {{ $invoice->vehicle->make }} {{ $invoice->vehicle->model }}</span><br>
                                <span class="text-bold">{{ $invoice->vehicle->model }} {{ $invoice->vehicle->year }} {{ $invoice->vehicle->color }} (F) {{ $invoice->vehicle->plate_number }}</span>
                            </td>
                            <td class="text-right" style="width: 50%; vertical-align: top;">
                                <div class="invoice-to-box">
                                    <div style="font-size: 12px; margin-bottom: 5px; color: #444;">INVOICE TO</div> <!-- Adjusted font size and margin -->
                                    <span class="text-bold">{{ $invoice->customer->first_name }} {{ $invoice->customer->last_name }}</span><br>
                                    @if($invoice->customer->address)
                                        {{ $invoice->customer->address }}<br>
                                    @endif
                                    @if($invoice->customer->nationality)
                                        Nationality: {{ $invoice->customer->nationality }}<br>
                                    @endif
                                    @if($invoice->customer->emirates)
                                        Emirates: {{ $invoice->customer->emirates }}<br>
                                    @endif
                                    @if($invoice->customer->phone_number)
                                        Mobile: {{ $invoice->customer->phone_number }}
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="clearfix"></div>

        <h3 style="margin-top: 15px; margin-bottom: 8px; font-size: 13px; color: #333;">INVOICE ITEMS</h3> <!-- Adjusted margin and font size -->
        <table class="item-table" style="width: 100%;">
            <thead>
                <tr class="heading">
                    <th class="text-left">Description</th>
                    <th class="text-right">Amount (AED)</th>
                    <th class="text-right">Discount (AED)</th>
                    <th class="text-right">Total (AED)</th>
                    <th class="text-right">Balance (AED)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoiceItems as $item)
                <tr class="item">
                    <td class="text-left">{{ $item['description'] }}</td>
                    <td class="text-right">{{ number_format($item['amount'], 2) }}</td>
                    <td class="text-right">{{ number_format($item['discount'], 2) }}</td>
                    <td class="text-right">{{ number_format($item['total'], 2) }}</td>
                    <td class="text-right text-bold">{{ number_format($item['balance'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table class="total-summary" style="width: 100%; margin-top: 15px;"> <!-- Adjusted margin -->
            <tr>
                <td class="label" style="width: 70%;">Sub Total</td>
                <td class="value">{{ number_format($invoice->sub_total, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Total Discount</td>
                <td class="value">{{ number_format($invoice->total_discount, 2) }}</td>
            </tr>
            <tr style="background-color: #f2f2f2;">
                <td class="label">TOTAL AMOUNT</td>
                <td class="value">{{ number_format($invoice->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Paid Amount</td>
                <td class="value">{{ number_format($invoice->paid_amount, 2) }}</td>
            </tr>
            <tr style="background-color: #f2f2f2;">
                <td class="label">REMAINING AMOUNT</td>
                <td class="value">{{ number_format($invoice->remaining_amount, 2) }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
