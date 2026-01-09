@extends('layout')
@section('title')
<?= get_label('etimates_invoices', 'Estimates/Invoices') ?>
@endsection
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between mb-2 mt-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{url('home')}}"><?= get_label('home', 'Home') ?></a>
                    </li>
                    <li class="breadcrumb-item active">
                        <?= get_label('etimates_invoices', 'Estimates/Invoices') ?>
                    </li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{url('estimates-invoices/create')}}"><button type="button" class="btn btn-sm btn-primary action_create_estimates_invoices" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title=" <?= get_label('create_estimate_invoice', 'Create estimate/invoice') ?>"><i class="bx bx-plus"></i></button></a>
        </div>
    </div>
    @if ($estimates_invoices > 0)
    @php
    $visibleColumns = getUserPreferences('estimates_invoices');
    @endphp
    <div class="card mt-4">
        <div class="card-body">
            <div class="row mb-3">
                <!-- Button with Badges for Estimates -->
                <div class="col-12">
                    <small class="text-light fw-semibold"><?= get_label('estimates', 'Estimates') ?></small>
                    <div class="demo-inline-spacing">
                        @php
                        $possibleStatuses = ['sent', 'accepted', 'draft', 'declined', 'expired', 'not_specified'];
                        $totalEstimates = array_sum(array_map(fn($status) => getStatusCount($status, 'estimate'), $possibleStatuses)); // Total estimates
                        @endphp
                        <button type="button" class="btn btn-outline-success status-badge" data-status="" data-type="estimate">
                            {{ get_label('all','All') }}
                            <span class="badge bg-white text-success">{{ getStatusCount('', 'estimate') }}</span>
                        </button>
                        @foreach($possibleStatuses as $status)
                        @php
                        $count = getStatusCount($status, 'estimate');
                        $percentage = $totalEstimates > 0 ? round(($count / $totalEstimates) * 100, 2) : 0; // Calculate percentage
                        @endphp
                        <button type="button" class="btn btn-outline-{{ getStatusColor($status) }} status-badge" data-status="{{ $status }}" data-type="estimate">
                            {{ get_label($status, ucfirst(str_replace('_', ' ', $status))) }}
                            <span class="badge bg-white text-{{ getStatusColor($status) }}">
                                {{ $count }} ({{ $percentage }}%)
                            </span>
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="row mb-5">
                <!-- Button with Badges for Invoices -->
                <div class="col-12">
                    <small class="text-light fw-semibold"><?= get_label('invoices', 'Invoices') ?></small>
                    <div class="demo-inline-spacing">
                        @php
                        $possibleStatuses = ['partially_paid', 'fully_paid', 'draft', 'cancelled', 'due', 'not_specified'];
                        $totalInvoices = array_sum(array_map(fn($status) => getStatusCount($status, 'invoice'), $possibleStatuses)); // Total invoices
                        @endphp
                        <button type="button" class="btn btn-outline-success status-badge" data-status="" data-type="invoice">
                            {{ get_label('all','All') }}
                            <span class="badge bg-white text-success">{{ getStatusCount('', 'invoice') }}</span>
                        </button>
                        @foreach($possibleStatuses as $status)
                        @php
                        $count = getStatusCount($status, 'invoice');
                        $percentage = $totalInvoices > 0 ? round(($count / $totalInvoices) * 100, 2) : 0; // Calculate percentage
                        @endphp
                        <button type="button" class="btn btn-outline-{{ getStatusColor($status) }} status-badge" data-status="{{ $status }}" data-type="invoice">
                            {{ get_label($status, ucfirst(str_replace('_', ' ', $status))) }}
                            <span class="badge bg-white text-{{ getStatusColor($status) }}">
                                {{ $count }} ({{ $percentage }}%)
                            </span>
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="input-group input-group-merge">
                        <input type="text" class="form-control" id="ie_date_between" placeholder="<?= get_label('date_between', 'Date Between') ?>" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="input-group input-group-merge">
                        <input type="text" id="start_date_between" class="form-control" placeholder="<?= get_label('from_date_between', 'From date between') ?>" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="input-group input-group-merge">
                        <input type="text" id="end_date_between" class="form-control" placeholder="<?= get_label('to_date_between', 'To date between') ?>" autocomplete="off">
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
            <input type="hidden" id="date_between_from">
            <input type="hidden" id="date_between_to">
            <input type="hidden" id="start_date_from">
            <input type="hidden" id="start_date_to">
            <input type="hidden" id="end_date_from">
            <input type="hidden" id="end_date_to">
            <input type="hidden" id="hidden_status">
            <div class="table-responsive text-nowrap">
                <input type="hidden" id="data_type" value="estimates-invoices">
                <input type="hidden" id="save_column_visibility">
                <input type="hidden" id="multi_select">
                <input type="hidden" id="data_reload" value="1">
                <table id="table" data-toggle="table" data-loading-template="loadingTemplate" data-url="{{ url('/estimates-invoices/list') }}" data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true" data-query-params="queryParams">
                    <thead>
                        <tr>
                            <th data-checkbox="true"></th>
                            <th data-field="id" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true" data-formatter="idFormatter"><?= get_label('id', 'ID') ?></th>
                            <th data-field="type" data-visible="{{ (in_array('type', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('type', 'Type') ?></th>
                            <th data-field="client" data-visible="{{ (in_array('client', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="false"><?= get_label('client', 'Client') ?></th>
                            <th data-field="from_date" data-visible="{{ (in_array('from_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('from_date', 'From date') ?></th>
                            <th data-field="to_date" data-visible="{{ (in_array('to_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('to_date', 'To date') ?></th>
                            <th data-field="total" data-visible="{{ (in_array('total', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('sub_total', 'Sub total') ?></th>
                            <th data-field="tax_amount" data-visible="{{ (in_array('tax_amount', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('tax', 'Tax') ?></th>
                            <th data-field="final_total" data-visible="{{ (in_array('final_total', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('final_total', 'Final total') ?></th>
                            <th data-field="status" data-visible="{{ (in_array('status', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('status', 'Status') ?></th>
                            <th data-field="created_by" data-visible="{{ (in_array('created_by', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="false"><?= get_label('created_by', 'Created by') ?></th>
                            <th data-field="created_at" data-visible="{{ (in_array('created_at', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('created_at', 'Created at') ?></th>
                            <th data-field="updated_at" data-visible="{{ (in_array('updated_at', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('updated_at', 'Updated at') ?></th>
                            <th data-field="actions" data-visible="{{ (in_array('actions', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('actions', 'Actions') ?></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
    @else
    <?php
    $type = 'Estimates/Invoices';
    $link = 'estimates-invoices/create';
    ?>
    <x-empty-state-card :type="$type" :link="$link" />
    @endif
</div>
<script>
    var label_update = '<?= get_label('update', 'Update') ?>';
    var label_delete = '<?= get_label('delete', 'Delete') ?>';
    var label_duplicate = '<?= get_label('duplicate', 'Duplicate') ?>';
    var label_estimate_id_prefix = '<?= get_label('estimate_id_prefix', 'ESTMT-') ?>';
    var label_invoice_id_prefix = '<?= get_label('invoice_id_prefix', 'INVC-') ?>';
    var label_sent = '<?= get_label('sent', 'Sent') ?>';
    var label_accepted = '<?= get_label('accepted', 'Accepted') ?>';
    var label_partially_paid = '<?= get_label('partially_paid', 'Partially paid') ?>';
    var label_fully_paid = '<?= get_label('fully_paid', 'Fully paid') ?>';
    var label_draft = '<?= get_label('draft', 'Draft') ?>';
    var label_declined = '<?= get_label('declined', 'Declined') ?>';
    var label_expired = '<?= get_label('expired', 'Expired') ?>';
    var label_cancelled = '<?= get_label('cancelled', 'Cancelled') ?>';
    var label_due = '<?= get_label('due', 'Due') ?>';
</script>
<script src="{{asset('assets/js/pages/estimates-invoices.js')}}">
</script>
@endsection