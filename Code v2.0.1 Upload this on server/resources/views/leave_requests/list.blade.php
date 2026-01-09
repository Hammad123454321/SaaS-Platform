@extends('layout')
@section('title')
    <?= get_label('leave_requests', 'Leave requests') ?>
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
                                            <?= get_label('leave_requests', 'Leave requests') ?>
                                        </li>
                                    </ol>
                                </nav>
                            </div>
                            <div>
                                @php
        $meetingsDefaultView = getUserPreferences('leave_requests', 'default_view');
                                @endphp
                                @if ($meetingsDefaultView === 'list')
                                    <span class="badge bg-primary"><?= get_label('default_view', 'Default View') ?></span>
                                @else
                                    <a href="javascript:void(0);"><span class="badge bg-secondary" id="set-default-view"
                                            data-type="leave-requests"
                                            data-view="list"><?= get_label('set_as_default_view', 'Set as Default View') ?></span></a>
                                @endif
                            </div>
                            <div>
                                <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#create_leave_request_modal"><button
                                        type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="right"
                                        data-bs-original-title=" <?= get_label('create_leave_request', 'Create leave request') ?>"><i
                                            class="bx bx-plus"></i></button></a>
                                <a href="{{ route('leave-requests.calendar') }}"><button type="button" class="btn btn-sm btn-primary"
                                        data-bs-toggle="tooltip" data-bs-placement="left"
                                        data-bs-original-title="<?= get_label('calendar_view', 'Calendar view') ?>"><i
                                            class='bx bx-calendar'></i></button></a>
                            </div>
                        </div>
                        @php
        $isLeaveEditor = \App\Models\LeaveEditor::where('user_id', $auth_user->id)->exists();
                        @endphp
                        <div class="row">
                            @if ($auth_user->hasRole('admin'))
                                <form action="{{url('leave-requests/update-editors')}}" class="form-submit-event" method="POST">
                                    <input type="hidden" name="redirect_url" value="{{ url('leave-requests') }}">
                                    <div class="d-flex justify-content-center">
                                        <div class="col-8 mb-3 mx-auto">
                                            <label class="form-label"
                                                for="user_id"><?= get_label('select_leave_editors', 'Select leave editors') ?> <i
                                                    class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-offset="0,4"
                                                    data-bs-placement="top" title=""
                                                    data-bs-original-title="{{get_label('leave_editor_access_info', 'Like Admin, Selected Users Will Be Able to Update and Create Leaves for Other Members.')}}"></i></label>
                                            <select id="" class="form-control users_select" name="user_ids[]" multiple="multiple"
                                                data-placeholder="<?= get_label('type_to_search', 'Type to search') ?>"
                                                data-ignore-admins="true">
                                                @foreach($leaveEditors as $leaveEditor)
                                                    <option value="{{$leaveEditor->id}}" selected>{{$leaveEditor->first_name}}
                                                        {{$leaveEditor->last_name}}
                                                    </option>
                                                @endforeach
                                                    </select>
                                                    <div class="d-flex justify-content-center">
                                                        <button type="submit" id="submit_btn" class="btn btn-primary my-2"><?= get_label('update', 'Update') ?></button>
                                                    </div>
                                                    </div>
                                                    </div>
                                                    </form>
                            @endif
                                                    @if ($isLeaveEditor)
                                                        <div class="d-flex justify-content-center mb-3">
                                                            <span class="badge bg-primary"><?= get_label('leave_editor_info', 'You are leave editor') ?></span>
                                                        </div>
                                                    @endif
                                                    </div>
                                                    @if ($leave_requests > 0)
                                                                                                @php
                                                                                                    $visibleColumns = getUserPreferences('leave_requests');
                                                                                                @endphp
                                                                                                <div class="card">
                                                                                                    <div class="card-body">
                                                                                                        <div class="row">
                                                                                                            <div class="col-md-4 mb-3">
                                                                                                                <div class="input-group input-group-merge">
                                                                                                                    <input type="text" class="form-control" id="lr_date_between"
                                                                                                                        placeholder="<?= get_label('date_between', 'Date Between') ?>" autocomplete="off">
                                                                                                                </div>
                                                                                                            </div>
                                                                                                            <div class="col-md-4 mb-3">
                                                                                                                <div class="input-group input-group-merge">
                                                                                                                    <input type="text" id="lr_start_date_between" class="form-control"
                                                                                                                        placeholder="<?= get_label('from_date_between', 'From date between') ?>" autocomplete="off">
                                                                                                                </div>
                                                                                                            </div>
                                                                                                            <div class="col-md-4 mb-3">
                                                                                                                <div class="input-group input-group-merge">
                                                                                                                    <input type="text" id="lr_end_date_between" class="form-control"
                                                                                                                        placeholder="<?= get_label('to_date_between', 'To date between') ?>" autocomplete="off">
                                                                                                                </div>
                                                                                                            </div>
                                                                                                            @if (is_admin_or_leave_editor())
                                                                                                                <div class="col-md-4 mb-3">
                                                                                                                    <select class="form-select users_select" id="lr_user_filter" aria-label="Default select example"
                                                                                                                        data-placeholder="<?= get_label('select_users', 'Select Users') ?>" multiple>
                                                                                                                    </select>
                                                                                                                </div>
                                                                                                            @endif
                                                                                                            <div class="col-md-4 mb-3">
                                                                                                                <select class="form-select users_select" id="lr_action_by_filter" aria-label="Default select example"
                                                                                                                    data-placeholder="<?= get_label('select_actions_by', 'Select Actions By') ?>" multiple>
                                                                                                                </select>
                                                                                                            </div>
                                                                                                            <div class="col-md-4 mb-3">
                                                                                                                <select class="form-select js-example-basic-multiple" id="lr_status_filter"
                                                                                                                    aria-label="Default select example"
                                                                                                                    data-placeholder="<?= get_label('select_statuses', 'Select statuses') ?>" data-allow-clear="true"
                                                                                                                    multiple>
                                                                                                                    <option value="pending"><?= get_label('pending', 'Pending') ?></option>
                                                                                                                    <option value="approved"><?= get_label('approved', 'Approved') ?></option>
                                                                                                                    <option value="rejected"><?= get_label('rejected', 'Rejected') ?></option>
                                                                                                                </select>
                                                                                                            </div>
                                                                                                            <div class="col-md-4 mb-3">
                                                                                                                <select class="form-select js-example-basic-multiple" id="lr_type_filter"
                                                                                                                    aria-label="Default select example"
                                                                                                                    data-placeholder="<?= get_label('select_types', 'Select Types') ?>" data-allow-clear="true"
                                                                                                                    multiple>
                                                                                                                    <option value="full"><?= get_label('full', 'Full') ?></option>
                                                                                                                    <option value="partial"><?= get_label('partial', 'Partial') ?></option>
                                                                                                                </select>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                        <input type="hidden" id="lr_date_between_from">
                                                                                                        <input type="hidden" id="lr_date_between_to">
                                                                                                        <input type="hidden" name="start_date_from" id="lr_start_date_from">
                                                                                                        <input type="hidden" name="start_date_to" id="lr_start_date_to">
                                                                                                        <input type="hidden" name="end_date_from" id="lr_end_date_from">
                                                                                                        <input type="hidden" name="end_date_to" id="lr_end_date_to">
                                                                                                        <div class="table-responsive text-nowrap">
                                                                                                            <input type="hidden" id="data_type" value="leave-requests">
                                                                                                            <input type="hidden" id="data_table" value="lr_table">
                                                                                                            <input type="hidden" id="save_column_visibility">
                                                                                                            <input type="hidden" id="multi_select">
                                                                                                            <table id="lr_table" data-toggle="table" data-loading-template="loadingTemplate"
                                                                                                                data-url="{{ url('/leave-requests/list') }}" data-icons-prefix="bx" data-icons="icons"
                                                                                                                data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows"
                                                                                                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server"
                                                                                                                data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc"
                                                                                                                data-mobile-responsive="true" data-query-params="queryParamsLr">
                                                                                                                <thead>
                                                                                                                    <tr>
                                                                                                                        <th data-checkbox="true"></th>
                                                                                                                        <th data-field="id"
                                                                                                                            data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"
                                                                                                                            data-sortable="true"><?= get_label('id', 'ID') ?></th>
                                                                                                                        <th data-field="user_name"
                                                                                                                            data-visible="{{ (in_array('user_name', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"
                                                                                                                            data-sortable="false"><?= get_label('member', 'Member') ?></th>
                                                                                                                        <th data-field="from_date"
                                                                                                                            data-visible="{{ (in_array('from_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"
                                                                                                                            data-sortable="true"><?= get_label('from', 'From') ?></th>
                                                                                                                        <th data-field="to_date"
                                                                                                                            data-visible="{{ (in_array('to_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"
                                                                                                                            data-sortable="true"><?= get_label('to', 'To') ?></th>
                                                                                                                        <th data-field="type"
                                                                                                                            data-visible="{{ (in_array('type', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">
                                                                                                                            <?= get_label('type', 'Type') ?>
                                                                                                                        </th>
                                                                                                                        <th data-field="duration"
                                                                                                                            data-visible="{{ (in_array('duration', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"
                                                                                                                            data-sortable="false"><?= get_label('duration', 'Duration') ?></th>
                                                                                                                        <th data-field="reason"
                                                                                                                            data-visible="{{ (in_array('reason', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"
                                                                                                                            data-sortable="true"><?= get_label('reason', 'Reason') ?></th>
                                                                                                                        <th data-field="visible_to"
                                                                                                                            data-visible="{{ (in_array('visible_to', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">
                                                                                                                            <?= get_label('visible_to', 'Visible To') ?><i class='bx bx-info-circle text-primary'
                                                                                                                                title="{{get_label('leave_visible_to_info_1', 'Including the requestee, admin, and leave editors, users who will be able to know when the requestee is on leave (not applicable if visible to all).')}}"></i>
                                                                                                                        </th>
                                                                                                                        <th data-field="status"
                                                                                                                            data-visible="{{ (in_array('status', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"
                                                                                                                            data-sortable="true"><?= get_label('status', 'Status') ?></th>
                                                                                                                        <th data-field="action_by"
                                                                                                                            data-visible="{{ (in_array('action_by', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"
                                                                                                                            data-sortable="true"><?= get_label('action_by', 'Action by') ?></th>
                                                                                                                        @if (is_admin_or_leave_editor())
                                                                                                                            <th data-field="comment"
                                                                                                                                data-visible="{{ (in_array('comment', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">
                                                                                                                                <?= get_label('comment', 'Comment') ?>
                                                                                                                            </th>
                                                                                                                        @endif
                                                                                                    <th data-field="created_at"
                                                                                                        data-visible="{{ (in_array('created_at', $visibleColumns)) ? 'true' : 'false' }}"
                                                                                                        data-sortable="true"><?= get_label('created_at', 'Created at') ?></th>
                                                                                                    <th data-field="updated_at"
                                                                                                        data-visible="{{ (in_array('updated_at', $visibleColumns)) ? 'true' : 'false' }}"
                                                                                                        data-sortable="true"><?= get_label('updated_at', 'Updated at') ?></th>
                                                                                                    <th data-field="actions"
                                                                                                        data-visible="{{ (in_array('actions', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">
                                                                                                        <?= get_label('actions', 'Actions') ?>
                                                                                                    </th>
                                                                                                </tr>
                                                                                            </thead>
                                                                                        </table>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                    @else
                                <?php
            $type = 'Leave requests'; ?>
                                <x-empty-state-card :type="$type" />
                        @endif
                    </div>
                    <script>
                                                var label_update = '<?= get_label('update', 'Update') ?>';
                                                                        var label_delete = '<?= get_label('delete', 'Delete') ?>';
                                                                var isAdminOrLe = '<?= is_admin_or_leave_editor() ?>';
                    </script>
                    <script src="{{asset('assets/js/pages/leave-requests.js')}}">
                    </script>
    @endsection
