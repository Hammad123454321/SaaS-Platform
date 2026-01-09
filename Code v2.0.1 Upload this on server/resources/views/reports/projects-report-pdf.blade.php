<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ get_label('projects_report', 'Projects Report') }}</title>
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

        .bg-label-dark {
            background-color: #dcdfe1 !important;
            color: #233446 !important;
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

        .avatar-container * {
            text-decoration: none;
            border: none;
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

        .word-wrap {
            word-wrap: break-word;
            /* Allows long words to break to the next line */
            white-space: normal;
            /* Ensures content wraps to the next line */
            max-width: 200px;
            /* Set a maximum width to control the wrapping (adjust as needed) */
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
        <div class="company-info">
            <img src="{{ asset($general_settings['full_logo']) }}" alt="{{ $general_settings['company_title'] }}"
                class="company-logo">
            <div class="company-details">
                <h1>{{ $general_settings['company_title'] }}</h1>
            </div>
        </div>
        <div class="report-info text-right">
            <h2>{{ get_label('projects_report', 'Projects Report') }}</h2>
            <?php
            $timezone = config('app.timezone');
            $currentTime = now()->tz($timezone);
            ?>
            <p>{{ get_label('date', 'Date') }}: {{ $currentTime->format($php_date_format . ' H:i:s') }}</p>
            @php $authUser = getAuthenticatedUser(); @endphp
            <p>{{ get_label('generated_by', 'Generated By') }}: {{ ucfirst($authUser->first_name) }} {{ ucfirst($authUser->last_name) }}</p>
        </div>
    </header>
    <main>
        <div class="report-content">
            <table class="summary-table">
                <tr>
                    <td class="summary-item">
                        <div class="summary-label">{{ get_label('total_projects', 'Total Projects') }}</div>
                        <div class="summary-value">{{ $summary->total_projects }}</div>
                    </td>
                    <td class="summary-item">
                        <div class="summary-label">{{ get_label('total_tasks', 'Total Tasks') }}</div>
                        <div class="summary-value">{{ $summary->total_tasks }}</div>
                    </td>
                    <td class="summary-item">
                        <div class="summary-label">{{ get_label('total_team_members', 'Total Team Members') }}</div>
                        <div class="summary-value">{{ $summary->total_team_members }}</div>
                    </td>
                    <td class="summary-item">
                        <div class="summary-label">Avg. Overdue Days/Project</div>
                        <div class="summary-value">{{ $summary->average_overdue_days_per_project }}</div>
                    </td>
                    <td class="summary-item">
                        <div class="summary-label">{{ get_label('due_projects', 'Due Projects') }}</div>
                        <div class="summary-value">
                            {{ $summary->due_projects ?? 0 }} ({{ number_format($summary->due_projects_percentage ?? 0, 2) }}%)
                        </div>
                    </td>
                    <td class="summary-item">
                        <div class="summary-label">{{ get_label('overdue_projects', 'Overdue Projects') }}</div>
                        <div class="summary-value">
                            {{ $summary->overdue_projects ?? 0 }} ({{ number_format($summary->overdue_projects_percentage ?? 0, 2) }}%)
                        </div>
                    </td>
                    <td class="summary-item">
                        <div class="summary-label">{{ get_label('total_overdue_days', 'Total Overdue Days') }}</div>
                        <div class="summary-value">{{ $summary->total_overdue_days }}</div>
                    </td>
                </tr>
            </table>
            <section>
                <h2 class="section-title">{{ get_label('project_details', 'Projects Details') }}</h2>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">{{ get_label('id', 'ID') }}</th>
                            <th rowspan="2">{{ get_label('title', 'Title') }}</th>
                            <th rowspan="2">{{ get_label('description', 'Description') }}</th>
                            <th colspan="2">{{ get_label('dates', 'Dates') }}</th>
                            <th rowspan="2">{{ get_label('status', 'Status') }}</th>
                            <th rowspan="2">{{ get_label('priority', 'Priority') }}</th>
                            <th rowspan="2">{{ get_label('budget', 'Budget') }}</th>
                            <th colspan="4">{{ get_label('duration', 'Duration') }}</th>
                            <th colspan="4">{{ get_label('tasks', 'Tasks') }}</th>
                            <th colspan="2">{{ get_label('team', 'Team') }}</th>
                            <th colspan="2">{{ get_label('clients', 'Clients') }}</th>
                            <th rowspan="2">{{ get_label('tags', 'Tags') }}</th>
                            <th rowspan="2">{{ get_label('favorite', 'Favorite') }}</th>
                            <th rowspan="2">{{ get_label('created_at', 'Created At') }}</th>
                            <th rowspan="2">{{ get_label('updated_at', 'Updated At') }}</th>
                        </tr>
                        <tr>
                            <th>{{ get_label('start_date', 'Start Date') }}</th>
                            <th>{{ get_label('end_date', 'End Date') }}</th>
                            <th>{{ get_label('total_days', 'Total Days') }}</th>
                            <th>{{ get_label('days_elapsed', 'Days Elapsed') }}</th>
                            <th>{{ get_label('days_remaining', 'Days Remaining') }}</th>
                            <th>{{ get_label('overdue_days', 'Overdue Days') }}</th>
                            <th>{{ get_label('total', 'Total') }}</th>
                            <th>{{ get_label('due', 'Due') }}</th>
                            <th>{{ get_label('overdue', 'Overdue') }}</th>
                            <th>{{ get_label('overdue_days', 'Overdue Days') }}</th>
                            <th>{{ get_label('members', 'Members') }}</th>
                            <th>{{ get_label('total', 'Total') }}</th>
                            <th>{{ get_label('clients', 'Clients') }}</th>
                            <th>{{ get_label('total', 'Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (empty($projects))
                        <tr>
                            <td colspan="24" class="text-center">{{get_label('no_data_available','No data available')}}</td>
                        </tr>
                        @else
                        @foreach ($projects as $project)
                        <tr>
                            <td>{{ $project->id }}</td>
                            <td class="word-wrap">{{ strip_tags($project->title) }}</td>
                            <td class="word-wrap">{!! $project->description ?? '-' !!}</td>
                            <td>{{ $project->start_date }}</td>
                            <td>{{ $project->end_date }}</td>
                            <td>{!! $project->status !!}</td>
                            <td>{!! $project->priority !!}</td>
                            <td>{{ $project->budget->total }}</td>
                            <td>{{ $project->time->total_days }}</td>
                            <td>{{ $project->time->days_elapsed }}</td>
                            <td>{{ $project->time->days_remaining }}</td>
                            <td>{{ $project->time->overdue_days }}</td>
                            <td>{{ $project->tasks->total }}</td>
                            <td>{{ $project->tasks->due }}</td>
                            <td>{{ $project->tasks->overdue }}</td>
                            <td>{{ $project->tasks->overdue_days }}</td>
                            <td>{!! $project->users !!}</td>
                            <td>{{ $project->team->total_members }}</td>
                            <td>{!! $project->clients !!}</td>
                            <td>{{ $project->total_clients }}</td>
                            <td>{{ implode(', ', $project->tags) }}</td>
                            <td>{{ $project->is_favorite ? get_label('yes', 'Yes') : get_label('no', 'No') }}</td>
                            <td>{{ $project->created_at }}</td>
                            <td>{{ $project->updated_at }}</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>

                </table>
            </section>
            <div class="section mt-20">
                <h2 class="section-title">{{ get_label('addi_info', 'Additional Information') }}</h2>
                <p class="text-muted">{{ get_label('report_footer', 'This report was generated automatically. For any questions or concerns, please contact admin for support.') }}</p>
            </div>
        </div>

    </main>
</body>

</html>