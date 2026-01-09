@extends('layout')
@section('title')
<?= get_label('clients', 'Clients') ?>
@endsection
@php
$visibleColumns = getUserPreferences('clients');
@endphp
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
                        <?= get_label('clients', 'Clients') ?>
                    </li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{url('clients/create')}}"><button type="button" class="btn btn-sm btn-primary action_create_clients" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('create_client', 'Create client') ?>"><i class='bx bx-plus'></i></button></a>
            <a href="{{ route('clients.showBulkUploadForm') }}">
                <button type="button" class="btn btn-sm btn-primary action_create_clients" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('bulk_upload', 'Bulk Upload') ?>">
                    <i class='bx bx-upload'></i>
                </button>
            </a>
        </div>
    </div>
    @if (is_countable($clients) && count($clients) > 0)
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <select class="form-select js-example-basic-multiple" id="client_status_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_statuses', 'Select statuses') ?>" data-allow-clear="true" multiple>
                        <option value="1">{{get_label('active','Active')}}</option>
                        <option value="0">{{get_label('deactive','Deactive')}}</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-select js-example-basic-multiple" id="client_internal_purpose_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_types', 'Select types') ?>" data-allow-clear="true" multiple>
                        <option value="0">{{get_label('normal','Normal')}}</option>
                        <option value="1">{{get_label('internal_purpose','Internal Purpose')}}</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-select js-example-basic-multiple" id="client_ev_status_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_ev_statuses', 'Select Email Verification Statuses') ?>" data-allow-clear="true" multiple>
                        <option value="1">{{get_label('verified','Verified')}}</option>
                        <option value="0">{{get_label('not_verified','Not Verified')}}</option>
                        <option value="2">{{get_label('not_applicable','Not Applicable')}}</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive text-nowrap">
                <input type="hidden" id="data_type" value="clients">
                <input type="hidden" id="save_column_visibility">
                <input type="hidden" id="multi_select">
                <table id="table" data-toggle="table" data-loading-template="loadingTemplate" data-url="{{ url('/clients/list') }}" data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true" data-query-params="queryParams">
                    <thead>
                        <tr>
                            <th data-checkbox="true"></th>
                            <th data-field="id" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('id', 'ID') ?></th>
                            <th data-field="profile" data-visible="{{ (in_array('profile', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('clients', 'Clients') ?></th>
                            <th data-field="company" data-visible="{{ (in_array('company', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('company', 'Company') ?></th>
                            <th data-field="phone" data-visible="{{ (in_array('phone', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('phone_number', 'Phone number') ?></th>
                            <th data-field="assigned" data-visible="{{ (in_array('assigned', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('assigned', 'Assigned') ?></th>
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
    $type = 'Clients'; ?>
    <x-empty-state-card :type="$type" />
    @endif
</div>
<script>
    var label_update = '<?= get_label('update', 'Update') ?>';
    var label_delete = '<?= get_label('delete', 'Delete') ?>';
    var label_projects = '<?= get_label('projects', 'Projects') ?>';
    var label_tasks = '<?= get_label('tasks', 'Tasks') ?>';
</script>
<script src="{{asset('assets/js/pages/clients.js')}}">
</script>
@endsection