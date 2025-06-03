{{-- filepath: resources/views/receipts/customer-receipt.blade.php --}}
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $booking->booking_reference }}</title>
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
            border-bottom: 2px solid #333;
            padding: 10px 0;
            margin-bottom: 15px;
        }

        .company {
            font-size: 16px;
            font-weight: bold;
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
            background: #f0f0f0;
            padding: 5px;
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
            background: #f0f0f0;
            font-weight: bold;
        }

        .total {
            background: #e8f4f8;
            font-weight: bold;
        }

        .status {
            text-align: center;
            margin: 10px 0;
        }

        .badge {
            background: #e8f4f8;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 9px;
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
    <div class="title">CUSTOMER RECEIPT</div>

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

    <!-- Customer & Service Info -->
    <div class="two-col">
        <div class="col">
            <strong>Customer:</strong><br>
            {{ $booking->customer_name }}<br>
            {{ $booking->customer_phone }}<br>
            {{ $booking->customer_email }}
        </div>
        <div class="col">
            <strong>Service:</strong><br>
            {{ str_replace('_', ' ', $booking->service_type) }}<br>
            {{ $service_details['service_name'] ?? 'N/A' }}<br>
            Date: {{ $booking->service_date?->format('d M Y') ?? 'TBD' }}
        </div>
    </div>

    <!-- Pricing -->
    <table>
        <tr>
            <th>Description</th>
            <th style="width: 80px; text-align: right;">Amount</th>
        </tr>
        <tr>
            <td>Service Price</td>
            <td style="text-align: right;">Tk {{ number_format($pricing['selling_price'] ?? 0, 2) }}</td>
        </tr>
        @if (($pricing['additional_charges'] ?? 0) > 0)
            <tr>
                <td>Additional Charges</td>
                <td style="text-align: right;">Tk {{ number_format($pricing['additional_charges'], 2) }}</td>
            </tr>
        @endif
        @if (($pricing['discount_amount'] ?? 0) > 0)
            <tr>
                <td>Discount</td>
                <td style="text-align: right;">-Tk {{ number_format($pricing['discount_amount'], 2) }}</td>
            </tr>
        @endif
        <tr class="total">
            <td><strong>Total Amount</strong></td>
            <td style="text-align: right;"><strong>Tk {{ number_format($pricing['final_amount'] ?? 0, 2) }}</strong>
            </td>
        </tr>
        <tr>
            <td>Paid</td>
            <td style="text-align: right;">Tk {{ number_format($pricing['paid_amount'] ?? 0, 2) }}</td>
        </tr>
        <tr class="{{ ($pricing['due_amount'] ?? 0) > 0 ? 'total' : '' }}">
            <td><strong>Due</strong></td>
            <td style="text-align: right;"><strong>Tk {{ number_format($pricing['due_amount'] ?? 0, 2) }}</strong></td>
        </tr>
    </table>

    <!-- Status -->
    <div class="status">
        <span class="badge">Status: {{ ucfirst($booking->payment_status ?? 'pending') }}</span>
    </div>

    @if ($booking->special_requirements)
        <div style="background: #fff9c4; padding: 5px; margin: 8px 0; font-size: 9px;">
            <strong>Note:</strong> {{ $booking->special_requirements }}
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <strong>Thank you for choosing {{ $company['name'] ?? 'Sunbit Travels' }}!</strong><br>
        Contact: {{ $company['phone'] ?? '+880 1234 567890' }}<br>
        Generated: {{ $receipt_date ?? date('d M Y, H:i') }}
    </div>
</body>

</html>
