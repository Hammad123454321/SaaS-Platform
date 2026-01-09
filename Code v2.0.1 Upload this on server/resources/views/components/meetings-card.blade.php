<!-- meetings -->
@if (is_countable($meetings) && count($meetings) > 0)
@php
$visibleColumns = getUserPreferences('meetings');
$user = getAuthenticatedUser();
@endphp
<div class="card">
    <div class="card-body">
        {{$slot}}
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="input-group input-group-merge">
                    <input type="text" class="form-control" id="meeting_date_between" placeholder="<?= get_label('date_between', 'Date Between') ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="input-group input-group-merge">
                    <input type="text" id="meeting_start_date_between" class="form-control" placeholder="<?= get_label('start_date_between', 'Start date between') ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="input-group input-group-merge">
                    <input type="text" id="meeting_end_date_between" class="form-control" placeholder="<?= get_label('end_date_between', 'End date between') ?>" autocomplete="off">
                </div>
            </div>
            @if(isAdminOrHasAllDataAccess())
            <div class="col-md-4 mb-3">
                <select class="form-select users_select" id="meeting_user_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_users', 'Select Users') ?>" multiple>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <select class="form-select clients_select" id="meeting_client_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_clients', 'Select Clients') ?>" multiple>
                </select>
            </div>
            @endif
            <div class="col-md-4 mb-3">
                <select class="form-select js-example-basic-multiple" id="status_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_statuses', 'Select Statuses') ?>" data-allow-clear="true" multiple>
                    <option value="ongoing"><?= get_label('ongoing', 'Ongoing') ?></option>
                    <option value="yet_to_start"><?= get_label('yet_to_start', 'Yet to start') ?></option>
                    <option value="ended"><?= get_label('ended', 'Ended') ?></option>
                </select>
            </div>
        </div>
        <input type="hidden" id="meeting_date_between_from">
        <input type="hidden" id="meeting_date_between_to">
        <input type="hidden" id="meeting_start_date_from">
        <input type="hidden" id="meeting_start_date_to">
        <input type="hidden" id="meeting_end_date_from">
        <input type="hidden" id="meeting_end_date_to">
        <div class="table-responsive text-nowrap">
            <input type="hidden" id="data_type" value="meetings">
            <input type="hidden" id="data_table" value="meetings_table">
            <input type="hidden" id="save_column_visibility">
            <input type="hidden" id="multi_select">
            <table id="meetings_table" data-toggle="table" data-loading-template="loadingTemplate" data-url="{{ url('/meetings/list') }}" data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true" data-query-params="queryParams">
                <thead>
                    <tr>
                        <th data-checkbox="true"></th>
                        <th data-field="id" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('id', 'ID') ?></th>
                        <th data-field="title" data-visible="{{ (in_array('title', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('title', 'Title') ?></th>
                        <th data-field="users" data-visible="{{ (in_array('users', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('users', 'Users') ?></th>
                        <th data-field="clients" data-visible="{{ (in_array('clients', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('clients', 'Clients') ?></th>
                        <th data-field="start_date_time" data-visible="{{ (in_array('start_date_time', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('starts_at', 'Starts at') ?></th>
                        <th data-field="end_date_time" data-visible="{{ (in_array('end_date_time', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('ends_at', 'Ends at') ?></th>
                        <th data-field="status" data-visible="{{ (in_array('status', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('status', 'Status') ?></th>
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
$type = 'Meetings'; ?>
<x-empty-state-card :type="$type" />
@endif