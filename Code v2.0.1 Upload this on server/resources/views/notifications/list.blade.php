@extends('layout')
@section('title')
<?= get_label('notifications', 'Notifications') ?>
@endsection

@php
$auth_user = getAuthenticatedUser();
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
                        <?= get_label('notifications', 'Notifications') ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>
    @if ($notifications_count > 0)
    @php
    $visibleColumns = getUserPreferences('notifications');
    @endphp
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="input-group input-group-merge">
                        <input type="text" id="notification_between_date" class="form-control" placeholder="<?= get_label('date_between', 'Date between') ?>" autocomplete="off">
                    </div>
                </div>
                @if(isAdminOrHasAllDataAccess())
                <div class="col-md-4 mb-3">
                    <select class="form-control users_select" id="user_filter" data-placeholder="<?= get_label('select_users', 'Select Users') ?>" multiple>
                        @if(getGuardName() == 'web')
                        <option value="{{ $auth_user->id }}" selected>{{ $auth_user->first_name }} {{ $auth_user->last_name }}</option>
                        @endif
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-control clients_select" id="client_filter" data-placeholder="<?= get_label('select_clients', 'Select Clients') ?>" multiple>
                        @if(getGuardName() == 'client')
                        <option value="{{ $auth_user->id }}" selected>{{ $auth_user->first_name }} {{ $auth_user->last_name }}</option>
                        @endif
                    </select>
                </div>
                @endif
                <div class="col-md-4 mb-3">
                    <select class="form-select js-example-basic-multiple" id="type_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_modules', 'Select Modules') ?>" data-allow-clear="true" multiple>
                        @foreach ($types as $type)
                        <option value="{{$type}}">{{ get_label($type, ucfirst(str_replace('_', ' ', $type))) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-select js-example-basic-multiple" id="status_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_statuses', 'Select statuses') ?>" data-allow-clear="true" multiple>
                        <option value="read"><?= get_label('read', 'Read') ?></option>
                        <option value="unread"><?= get_label('unread', 'Unread') ?></option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-select js-example-basic-multiple" id="noti_types_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_types', 'Select Types') ?>" data-allow-clear="true" multiple>
                        <option value="system" selected><?= get_label('system', 'system') ?></option>
                        <option value="push"><?= get_label('push_in_app', 'Push (In APP)') ?></option>
                    </select>
                </div>
            </div>
            <input type="hidden" id="notification_between_date_from">
            <input type="hidden" id="notification_between_date_to">
            <div class="table-responsive text-nowrap">
                <input type="hidden" id="data_type" value="notifications">
                <input type="hidden" id="save_column_visibility">
                <input type="hidden" id="multi_select">
                <table id="table" data-toggle="table" data-loading-template="loadingTemplate" data-url="{{ url('/notifications/list') }}" data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true" data-query-params="queryParams">
                    <thead>
                        <tr>
                            <th data-checkbox="true"></th>
                            <th data-field="id" data-sortable="true" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('id', 'ID') ?></th>
                            <th data-field="title" data-sortable="true" data-visible="{{ (in_array('title', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('title', 'Title') ?></th>
                            <th data-field="message" data-sortable="true" data-visible="{{ (in_array('message', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('message', 'Message') ?></th>
                            <th data-field="users" data-formatter="UserFormatter" data-visible="{{ (in_array('users', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('notification_users', 'Noti. users') ?></th>
                            <th data-field="clients" data-formatter="ClientFormatter" data-visible="{{ (in_array('clients', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('notification_clients', 'Noti. clients') ?></th>
                            <th data-field="type" data-sortable="true" data-visible="{{ (in_array('type', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('type', 'Type') ?></th>
                            <th data-field="notification_types" data-sortable="false" data-visible="{{ (in_array('notification_types', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('notification_types', 'Noti. Types') ?></th>
                            <th data-field="status" data-sortable="true" data-visible="{{ (in_array('status', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('status', 'Status') ?></th>
                            <th data-field="created_at" data-sortable="true" data-visible="{{ (in_array('created_at', $visibleColumns)) ? 'true' : 'false' }}"><?= get_label('created_at', 'Created at') ?></th>
                            <th data-field="updated_at" data-sortable="true" data-visible="{{ (in_array('updated_at', $visibleColumns)) ? 'true' : 'false' }}"><?= get_label('updated_at', 'Updated at') ?></th>
                            <th data-field="actions" data-sortable="false" data-visible="{{ (in_array('actions', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('actions', 'Actions') ?></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
    @else
    <?php
    $type = 'Notifications'; ?>
    <x-empty-state-card :type="$type" />
    @endif
</div>
<script src="{{asset('assets/js/pages/notifications.js')}}"></script>
@endsection