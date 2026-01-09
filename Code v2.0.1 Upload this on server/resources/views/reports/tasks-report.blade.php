@extends('layout')
@section('title')
{{ get_label('tasks_report', 'Tasks Report') }}
@endsection
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between mt-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('home.index') }}">{{ get_label('home', 'Home') }}</a>
                    </li>
                    <li class="breadcrumb-item">
                        {{ get_label('reports', 'Reports') }}
                    </li>
                    <li class="breadcrumb-item active">
                        {{ get_label('tasks', 'Tasks') }}
                    </li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Summary Cards -->
    <div class="d-flex mb-4 flex-wrap gap-3">
        <div class="card flex-grow-1 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <i class="bx bx-task fs-2 text-primary me-3"></i>
                <div>
                    <h6 class="card-title mb-1">{{ get_label('total_tasks', 'Total Tasks') }}</h6>
                    <p class="card-text mb-0" id="total-tasks">{{ get_label('loading', 'Loading...') }}</p>
                </div>
            </div>
        </div>
        <div class="card flex-grow-1 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <i class="bx bx-calendar-exclamation fs-2 text-warning me-3"></i>
                <div>
                    <h6 class="card-title mb-1">{{ get_label('due_tasks', 'Due Tasks') }}</h6>
                    <i class="bx bx-info-circle text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ get_label('due_tasks_info', 'Tasks have deadline today.') }}"></i>
                    <p class="card-text mb-0" id="due-tasks">{{ get_label('loading', 'Loading...') }}</p>
                </div>
            </div>
        </div>
        <div class="card flex-grow-1 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <i class="bx bx-calendar-exclamation fs-2 text-danger me-3"></i>
                <div>
                    <h6 class="card-title mb-1">{{ get_label('overdue_tasks', 'Overdue Tasks') }}</h6>
                    <p class="card-text mb-0" id="overdue-tasks">{{ get_label('loading', 'Loading...') }}</p>
                </div>
            </div>
        </div>
        <div class="card flex-grow-1 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <i class="bx bx-time-five fs-2 text-warning me-3"></i>
                <div>
                    <h6 class="card-title mb-1">{{ get_label('average_task_completion_days', 'Avg. Task Completion Days') }}</h6>
                    <p class="card-text mb-0" id="average-task-completion-time">{{ get_label('loading', 'Loading...') }}</p>
                </div>
            </div>
        </div>
        <div class="card flex-grow-1 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <i class="bx bx-calendar fs-2 text-danger me-3"></i>
                <div>
                    <h6 class="card-title mb-1">
                        {{ get_label('urgent_tasks', 'Urgent Tasks') }}
                        <i class="bx bx-info-circle text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ get_label('urgent_tasks_info', 'Tasks with a \'Danger\' priority color that have passed their due date are considered urgent.') }}"></i>
                    </h6>
                    <p class="card-text mb-0" id="urgent-tasks">{{ get_label('loading', 'Loading...') }}</p>
                </div>
            </div>
        </div>
    </div>


    <div class="card">
        <div class="card-body">
            <div class="row">
                <!-- Date Range Filter -->
                <div class="col-md-4 mb-3">
                    <input type="text" id="filter_date_range" class="form-control" placeholder="<?= get_label('date_between', 'Date Between') ?>" autocomplete="off">
                </div>
                <div class="col-md-4 mb-3">
                    <div class="input-group input-group-merge">
                        <input type="text" id="report_start_date_between" class="form-control" placeholder="<?= get_label('from_date_between', 'From date between') ?>" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="input-group input-group-merge">
                        <input type="text" id="report_end_date_between" class="form-control" placeholder="<?= get_label('to_date_between', 'To date between') ?>" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-control projects_select" id="project_filter" multiple="multiple" data-placeholder="<?= get_label('select_projects', 'Select Projects') ?>">
                    </select>
                </div>
                @if(isAdminOrHasAllDataAccess())
                <!-- User Filter -->
                <div class="col-md-4 mb-3">
                    <select class="form-control users_select" id="user_filter" multiple="multiple" data-placeholder="<?= get_label('select_users', 'Select Users') ?>">
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-control clients_select" id="client_filter" multiple="multiple" data-placeholder="<?= get_label('select_clients', 'Select Clients') ?>">
                    </select>
                </div>
                @endif
                <!-- Status Filter -->
                <div class="col-md-4 mb-3">
                    <select class="form-control statuses_filter" id="status_filter" multiple="multiple" data-placeholder="<?= get_label('select_statuses', 'Select Statuses') ?>">
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-control priorities_filter" id="priority_filter" multiple="multiple" data-placeholder="<?= get_label('select_priorities', 'Select Priorities') ?>">
                    </select>
                </div>
            </div>
            <input type="hidden" id="filter_date_range_from">
            <input type="hidden" id="filter_date_range_to">
            <input type="hidden" id="filter_start_date_from">
            <input type="hidden" id="filter_start_date_to">
            <input type="hidden" id="filter_end_date_from">
            <input type="hidden" id="filter_end_date_to">
            <div class="row mb-2">
                <!-- Export Button -->
                <div class="col-md-12 col-lg-12 d-flex align-items-center justify-content-md-end mb-md-0 mb-2">
                    <button class="btn btn-primary" id="export_button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="{{ get_label('export_tasks_report', 'Export Tasks Report') }}">
                        <i class="bx bx-export"></i>
                    </button>
                </div>
            </div>
            @php
            $visibleColumns = getUserPreferences('tasks_report');
            @endphp
            <!-- Table -->
            <div class="table-responsive text-nowrap">
                <input type="hidden" id="multi_select">
                <input type="hidden" id="data_type" value="report">
                <input type="hidden" id="save_column_visibility" data-type="tasks_report" data-table="tasks_report_table">
                <table id="tasks_report_table" class="table table-striped table-bordered"
                    data-toggle="table"
                    data-url="{{ route('reports.tasks-report-data') }}"
                    data-loading-template="loadingTemplate"
                    data-icons-prefix="bx"
                    data-icons="icons"
                    data-show-refresh="true"
                    data-total-field="total"
                    data-trim-on-search="false"
                    data-data-field="tasks"
                    data-page-list="[5, 10, 20, 50, 100, 200]"
                    data-search="true"
                    data-side-pagination="server"
                    data-show-columns="true"
                    data-pagination="true"
                    data-sort-name="id"
                    data-sort-order="desc"
                    data-mobile-responsive="true"
                    data-query-params="tasks_report_query_params">
                    <thead>
                        <tr>
                            <th rowspan="2" data-field="id" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('id', 'ID') }}</th>
                            <th rowspan="2" data-field="title" data-visible="{{ (in_array('title', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('title', 'Title') }}</th>
                            <th rowspan="2" data-field="description" data-visible="{{ (in_array('description', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('description', 'Description') }}</th>
                            <th rowspan="2" data-field="project" data-visible="{{ (in_array('project', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('project', 'Project') }}</th>
                            <th colspan="2">{{ get_label('dates', 'Dates') }}</th>
                            <th rowspan="2" data-field="status" data-visible="{{ (in_array('status', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('status', 'Status') }}</th>
                            <th rowspan="2" data-field="priority" data-visible="{{ (in_array('priority', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('priority', 'Priority') }}</th>
                            <th colspan="4">{{ get_label('duration', 'Duration') }}</th>
                            <th colspan="2">{{ get_label('team', 'Team') }}</th>
                            <th colspan="2">{{ get_label('clients', 'Clients') }}</th>
                        </tr>
                        <tr>
                            <th data-field="start_date" data-visible="{{ (in_array('start_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('start_date', 'Start Date') }}</th>
                            <th data-field="due_date" data-visible="{{ (in_array('due_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('due_date', 'Due Date') }}</th>
                            <th data-field="time.total_days" data-visible="{{ (in_array('total_days', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('total_days', 'Total Days') }}</th>
                            <th data-field="time.days_elapsed" data-visible="{{ (in_array('days_elapsed', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('days_elapsed', 'Days Elapsed') }}</th>
                            <th data-field="time.days_remaining" data-visible="{{ (in_array('days_remaining', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('days_remaining', 'Days Remaining') }}</th>
                            <th data-field="time.overdue_days" data-visible="{{ (in_array('overdue_days', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('overdue_days', 'Overdue Days') }}</th>
                            <th data-field="users" data-visible="{{ (in_array('members', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">{{ get_label('members', 'Members') }}</th>
                            <th data-field="total_users" data-visible="{{ (in_array('total_users', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('total', 'Total') }}</th>
                            <th data-field="clients" data-visible="{{ (in_array('clients', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">{{ get_label('clients', 'Clients') }}</th>
                            <th data-field="total_clients" data-visible="{{ (in_array('total_clients', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">{{ get_label('total', 'Total') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
    var tasks_report_export_url = "{{ route('reports.export-tasks-report') }}";
</script>
<script src="{{ asset('assets/js/pages/tasks-report.js') }}"></script>
@endsection