@extends('layout')
@section('title')
{{ get_label('estimates_invoices_report', 'Estimates/Invoices Report') }}
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
                        {{ get_label('estimates_invoices', 'Estimates/Invoices') }}
                    </li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Summary Cards -->
    <div class="d-flex mb-4 flex-wrap gap-3">
        <div class="card flex-grow-1 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <i class="bx bx-receipt fs-2 text-primary me-3"></i>
                <div>
                    <h6 class="card-title mb-1">{{ get_label('total', 'Total') }}</h6>
                    <p class="card-text mb-0" id="total-invoices">{{ get_label('loading', 'Loading...') }}</p>
                </div>
            </div>
        </div>
        <div class="card flex-grow-1 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <i class="bx bx-money fs-2 text-success me-3"></i>
                <div>
                    <h6 class="card-title mb-1">{{ get_label('total_amount', 'Total Amount') }}</h6>
                    <p class="card-text mb-0" id="total-amount">{{ get_label('loading', 'Loading...') }}</p>
                </div>
            </div>
        </div>
        <div class="card flex-grow-1 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <i class="bx bx-purchase-tag fs-2 text-warning me-3"></i>
                <div>
                    <h6 class="card-title mb-1">{{ get_label('total_tax', 'Total Tax') }}</h6>
                    <p class="card-text mb-0" id="total-tax">{{ get_label('loading', 'Loading...') }}</p>
                </div>
            </div>
        </div>
        <div class="card flex-grow-1 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <i class="bx bx-money fs-2 text-info me-3"></i>
                <div>
                    <h6 class="card-title mb-1">{{ get_label('final_total', 'Final Total') }}</h6>
                    <p class="card-text mb-0" id="total-final">{{ get_label('loading', 'Loading...') }}</p>
                </div>
            </div>
        </div>
        <div class="card flex-grow-1 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <i class="bx bx-trending-up fs-2 text-danger me-3"></i>
                <div>
                    <h6 class="card-title mb-1">{{ get_label('average_value', 'Average Value') }}</h6>
                    <p class="card-text mb-0" id="average-invoice-value">{{ get_label('loading', 'Loading...') }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <!-- Filters Row -->
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="input-group input-group-merge">
                        <input type="text" class="form-control" id="filter_date_range" placeholder="<?= get_label('date_between', 'Date Between') ?>" autocomplete="off">
                    </div>
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
                    <select class="form-select js-example-basic-multiple" id="type_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_types', 'Select types') ?>" data-allow-clear="true" multiple>
                        <option value="estimate"><?= get_label('estimates', 'Estimates') ?></option>
                        <option value="invoice"><?= get_label('invoices', 'Invoices') ?></option>
                    </select>
                </div>
                @if (!isClient() || isAdminOrHasAllDataAccess())
                <div class="col-md-4 mb-3">
                    <select class="form-select clients_select" id="client_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_clients', 'Select Clients') ?>" multiple>
                    </select>
                </div>
                @endif
                @if(isAdminOrHasAllDataAccess())
                <div class="col-md-4 mb-3">
                    <select class="form-select users_select" id="user_creators_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_user_creators', 'Select User Creators') ?>" multiple>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-select clients_select" id="client_creators_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_client_creators', 'Select Client Creators') ?>" multiple>
                    </select>
                </div>
                @endif
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
                    <button class="btn btn-primary" id="export_button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="{{ get_label('export_report', 'Export Report') }}">
                        <i class="bx bx-export"></i>
                    </button>
                </div>
            </div>
            @php
            $visibleColumns = getUserPreferences('estimates_invoices_report');
            @endphp
            <!-- Table -->
            <div class="table-responsive text-nowrap">
                <input type="hidden" id="multi_select">
                <input type="hidden" id="data_type" value="report">
                <input type="hidden" id="save_column_visibility" data-type="estimates_invoices_report" data-table="invoices_report_table">
                <table id="invoices_report_table" data-toggle="table"
                    data-url="{{ route('reports.invoices-report-data') }}" data-loading-template="loadingTemplate"
                    data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total"
                    data-trim-on-search="false" data-data-field="invoices" data-page-list="[5, 10, 20, 50, 100, 200]"
                    data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true"
                    data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true"
                    data-query-params="invoices_report_query_params">
                    <thead>
                        <tr>
                            <th rowspan="2" data-field="id" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('id', 'ID') }}</th>
                            <th rowspan="2" data-field="type" data-visible="{{ (in_array('type', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('type', 'Type') }}</th>
                            <th rowspan="2" data-field="client" data-visible="{{ (in_array('client', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="false">{{ get_label('client', 'Client') }}</th>
                            <th colspan="3">{{ get_label('amount', 'Amount') }}</th>
                            <th colspan="2">{{ get_label('date_range', 'Date Range') }}</th>
                            <th rowspan="2" data-field="status" data-visible="{{ (in_array('status', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('status', 'Status') }}</th>
                            <th rowspan="2" data-field="created_by" data-visible="{{ (in_array('created_by', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="false">{{ get_label('created_by', 'Created By') }}</th>
                            <th colspan="2">{{ get_label('timestamps', 'Timestamps') }}</th>
                        </tr>
                        <tr>
                            <th data-field="total" data-visible="{{ (in_array('total', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('total', 'Total') }}</th>
                            <th data-field="tax_amount" data-visible="{{ (in_array('tax_amount', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('tax', 'Tax') }}</th>
                            <th data-field="final_total" data-visible="{{ (in_array('final_total', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('final_total', 'Final Total') }}</th>
                            <th data-field="from_date" data-visible="{{ (in_array('from_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('from', 'From') }}</th>
                            <th data-field="to_date" data-visible="{{ (in_array('to_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true">{{ get_label('to', 'To') }}</th>
                            <!-- Subheadings for Timestamps -->
                            <th data-field="created_at" data-visible="{{ (in_array('created_at', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">{{ get_label('created_at', 'Created At') }}</th>
                            <th data-field="updated_at" data-visible="{{ (in_array('updated_at', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">{{ get_label('updated_at', 'Updated At') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
    var invoices_report_export_url = "{{ route('reports.export-invoices-report') }}";
</script>
<script src="{{ asset('assets/js/pages/invoices-report.js') }}"></script>
@endsection