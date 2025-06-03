{{-- filepath: resources/views/receipts/agent-receipt.blade.php --}}
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Agent Receipt - {{ $booking->booking_reference }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial;
            font-size: 10px;
            line-height: 1.2;
            padding: 10px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #059669;
            padding: 10px 0;
            margin-bottom: 15px;
        }

        .company {
            font-size: 16px;
            font-weight: bold;
            color: #059669;
        }

        .contact {
            font-size: 8px;
            margin-top: 3px;
        }

        .title {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
            background: #e8f5e8;
            padding: 5px;
            color: #059669;
        }

        .info-box {
            background: #f8f8f8;
            padding: 8px;
            margin: 8px 0;
            border-radius: 3px;
        }

        .row {
            display: flex;
            margin: 3px 0;
        }

        .label {
            width: 35%;
            font-weight: bold;
        }

        .value {
            width: 65%;
        }

        .two-col {
            display: flex;
            gap: 10px;
        }

        .col {
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 9px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 4px;
            text-align: left;
        }

        th {
            background: #059669;
            color: white;
            font-weight: bold;
        }

        .commission {
            background: #e8f5e8;
            font-weight: bold;
            color: #059669;
        }

        .highlight {
            background: #e8f5e8;
            border: 2px solid #059669;
            padding: 15px;
            margin: 10px 0;
            text-align: center;
            border-radius: 5px;
        }

        .big-amount {
            font-size: 16px;
            font-weight: bold;
            color: #059669;
        }

        .footer {
            border-top: 1px solid #ddd;
            padding-top: 8px;
            margin-top: 15px;
            text-align: center;
            font-size: 8px;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <div class="company">{{ $company['name'] ?? 'Sunbit Travels' }}</div>
        <div class="contact">{{ $company['phone'] ?? '+880 1234 567890' }} |
            {{ $company['email'] ?? 'info@sunbittravels.com' }}</div>
    </div>

    <!-- Title -->
    <div class="title">AGENT COMMISSION RECEIPT</div>

    <!-- Basic Info -->
    <div class="info-box">
        <div class="row">
            <div class="label">Receipt #:</div>
            <div class="value">{{ $receipt_number ?? 'N/A' }}</div>
        </div>
        <div class="row">
            <div class="label">Booking Ref:</div>
            <div class="value">{{ $booking->booking_reference }}</div>
        </div>
        <div class="row">
            <div class="label">Date:</div>
            <div class="value">{{ $receipt_date ?? date('d M Y') }}</div>
        </div>
    </div>

    <!-- Agent & Booking Info -->
    <div class="two-col">
        <div class="col">
            <strong>Agent:</strong><br>
            {{ $booking->agent?->name ?? 'Direct Booking' }}<br>
            {{ $booking->agent?->phone ?? 'N/A' }}<br>
            {{ $booking->agent?->email ?? 'N/A' }}
        </div>
        <div class="col">
            <strong>Booking:</strong><br>
            Customer: {{ $booking->customer_name }}<br>
            Service: {{ $service_details['service_name'] ?? 'N/A' }}<br>
            Date: {{ $booking->service_date?->format('d M Y') ?? 'TBD' }}
        </div>
    </div>

    <!-- Commission Breakdown -->
    <table>
        <tr>
            <th>Description</th>
            <th style="width: 80px; text-align: right;">Amount</th>
        </tr>
        <tr>
            <td>Original Price</td>
            <td style="text-align: right;">Tk {{ number_format($pricing['original_price'] ?? 0, 2) }}</td>
        </tr>
        @if (isset($pricing['agent_commission_percent']) && $pricing['agent_commission_percent'] > 0)
            <tr class="commission">
                <td>Commission ({{ $pricing['agent_commission_percent'] }}%)</td>
                <td style="text-align: right;">Tk {{ number_format($pricing['commission_amount'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Your Cost</td>
                <td style="text-align: right;">Tk {{ number_format($pricing['agent_cost_price'] ?? 0, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td>Customer Payment</td>
            <td style="text-align: right;">Tk {{ number_format($pricing['final_amount'] ?? 0, 2) }}</td>
        </tr>
    </table>

    <!-- Commission Highlight -->
    @if (isset($pricing['commission_amount']) && $pricing['commission_amount'] > 0)
        <div class="highlight">
            <div><strong>Your Commission</strong></div>
            <div class="big-amount">Tk {{ number_format($pricing['commission_amount'] ?? 0, 2) }}</div>
            <div style="font-size: 9px; margin-top: 5px;">
                Rate: {{ $pricing['agent_commission_percent'] ?? 0 }}% | Status:
                {{ ucfirst($booking->payment_status ?? 'pending') }}
            </div>
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <strong>Thank you for your partnership!</strong><br>
        Agent Support: {{ $company['phone'] ?? '+880 1234 567890' }}<br>
        Generated: {{ $receipt_date ?? date('d M Y, H:i') }}
    </div>
</body>

</html>
