<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Header Styles */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            border-bottom: 2px solid #ddd;
        }

        .company-info {
            display: flex;
            align-items: center;
        }

        .company-logo {
            width: 400px;
            height: auto;
            margin-right: 10px;
        }

        .company-details h1 {
            text-align: center;
            margin: 0;
            color: #333;
        }

        .company-details p {
            margin: 5px 0;
            color: #666;
        }

        .report-info {
            text-align: right;
        }

        .report-info h2 {
            margin: 0;
            color: #333;
        }

        .report-info p {
            margin: 5px 0;
            color: #666;
        }

        /* Content Styles */
        .report-content {
            padding: 20px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            padding: 8px 12px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #f4f4f4;
        }

        .avatar {
            display: inline-block;
            margin-right: 5px;
        }

        .avatar img {
            border-radius: 50%;
            width: 30px;
            height: 30px;
        }

        /* Utility Classes */
        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .mb-10 {
            margin-bottom: 10px;
        }

        .mt-20 {
            margin-top: 20px;
        }

        .font-bold {
            font-weight: bold;
        }

        .text-large {
            font-size: 16px;
        }

        .text-small {
            font-size: 12px;
        }

        .text-muted {
            color: #777;
        }

        /* Status Badge Styles */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }

        .bg-label-secondary {
            background-color: #ebeef0;
            color: #8592a3;
        }

        .bg-label-success {
            background-color: #e8fadf;
            color: #71dd37;
        }

        .bg-label-info {
            background-color: #d7f5fc;
            color: #03c3ec;
        }

        .bg-label-warning {
            background-color: #fff2d6;
            color: #ffab00;
        }

        .bg-label-danger {
            background-color: #ffe0db;
            color: #ff3e1d;
        }

        .bg-label-primary {
            background-color: #e7e7ff;
            color: #696cff;
        }

        .bg-primary {
            background-color: #696cff;
            color: white;
        }

        .bg-secondary {
            background-color: #8592a3;
            color: white;
        }

        .bg-success {
            background-color: #71dd37;
            color: white;
        }

        .bg-danger {
            background-color: #ff3e1d;
            color: white;
        }

        .bg-warning {
            background-color: #ffab00;
            color: #000;
        }

        .bg-info {
            background-color: #03c3ec;
            color: white;
        }

        .bg-light {
            background-color: #fcfdfd;
            color: #000;
        }

        .bg-dark {
            background-color: #233446;
            color: white;
        }

        .bg-gray {
            background-color: #f5f5f9;
            color: #000;
        }

        /* Avatar Styles */
        .avatar-container {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e0e0e0;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .summary-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
        }

        .summary-label {
            font-size: 14px;
            color: #666;
        }

        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .summary-item {
            width: 20%;
            /* Adjust to fit your needs */
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        /* Print Styles */
        @media print {
            body {
                font-size: 12px;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="report-header">
            <div class="company-info">
                <img src="{{ asset($general_settings['full_logo']) }}" alt="{{ $general_settings['company_title'] }}"
                    class="company-logo">
                <div class="company-details">
                    <h1>{{ $general_settings['company_title'] }}</h1>
                </div>
            </div>
            <div class="report-info">
                <h2>{{ $title }}</h2>
                <p>{{ get_label('date', 'Date') }}: {{ date('F d, Y h:m:s') }}</p>
                @php
                $authUser = getAuthenticatedUser();
                @endphp
                <p>{{ get_label('generated_by', 'Generated By') }}: {{ ucfirst($authUser->first_name) }} {{ ucfirst($authUser->last_name) }}</p>
            </div>
        </div>
    </header>
    <main>
        <div class="report-content">
            <table class="summary-table">
                <tr>
                    <td class="summary-item">
                        <div class="summary-label">{{ get_label('total', 'Total') }}</div>
                        <div class="summary-value">{{ $summary->total_invoices }}</div>
                    </td>
                    <td class="summary-item">
                        <div class="summary-label">{{ get_label('total_amount', 'Total Amount') }}</div>
                        <div class="summary-value">{{ $summary->total_amount }}</div>
                    </td>
                    <td class="summary-item">
                        <div class="summary-label">{{ get_label('total_tax', 'Total Tax') }}</div>
                        <div class="summary-value">{{ $summary->total_tax }}</div>
                    </td>
                    <td class="summary-item">
                        <div class="summary-label">{{ get_label('final_total', 'Final Total') }}</div>
                        <div class="summary-value">{{ $summary->total_final }}</div>
                    </td>
                    <td class="summary-item">
                        <div class="summary-label">{{ get_label('average_value', 'Average Value') }}</div>
                        <div class="summary-value">{{ $summary->average_invoice_value }}</div>
                    </td>
                </tr>
            </table>
            <div class="section mt-20">
                <h2 class="section-title">{{ $type }} {{ get_label('details', 'Details') }}</h2>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">{{ get_label('id', 'ID') }}</th>
                            <th rowspan="2">{{ get_label('type', 'Type') }}</th>
                            <th rowspan="2">{{ get_label('client', 'Client') }}</th>
                            <th colspan="3">{{ get_label('amount', 'Amount') }}</th>
                            <th colspan="2">{{ get_label('date_range', 'Date Range') }}</th>
                            <th rowspan="2">{{ get_label('status', 'Status') }}</th>
                            <th rowspan="2">{{ get_label('created_by', 'Created By') }}</th>
                            <th colspan="2">{{ get_label('timestamps', 'Timestamps') }}</th>
                        </tr>
                        <tr>
                            <th>{{ get_label('total', 'Total') }}</th>
                            <th>{{ get_label('tax', 'Tax') }}</th>
                            <th>{{ get_label('final_total', 'Final Total') }}</th>
                            <th>{{ get_label('from', 'From') }}</th>
                            <th>{{ get_label('to', 'To') }}</th>
                            <th>{{ get_label('created_at', 'Created At') }}</th>
                            <th>{{ get_label('updated_at', 'Updated At') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (empty($invoices))
                        <tr>
                            <td colspan="12" class="text-center">{{get_label('no_data_available','No data available')}}</td>
                        </tr>
                        @else
                        @foreach ($invoices as $invoice)
                        <tr>
                            <td>{{ strip_tags($invoice->id) }}</td>
                            <td>{{ $invoice->type }}</td>
                            <td>{!! $invoice->client !!}</td>
                            <td>{{ $invoice->total }}</td>
                            <td>{{ $invoice->tax_amount }}</td>
                            <td>{{ $invoice->final_total }}</td>
                            <td>{{ $invoice->from_date }}</td>
                            <td>{{ $invoice->to_date }}</td>
                            <td>{!! $invoice->status !!}</td>
                            <td>{!! $invoice->created_by !!}</td>
                            <td>{{ $invoice->created_at }}</td>
                            <td>{{ $invoice->updated_at }}</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>

            </div>
            <div class="section mt-20">
                <h2 class="section-title">{{ get_label('addi_info', 'Additional Information') }}</h2>
                <p class="text-muted">{{ get_label('report_footer', 'This report was generated automatically. For any questions or concerns, please contact admin for support.') }}</p>
            </div>
        </div>
    </main>
</body>

</html>