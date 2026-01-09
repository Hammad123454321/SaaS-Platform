<!-- tasks -->
@php
    $flag =
        Request::segment(1) == 'home' ||
        Request::segment(1) == 'users' ||
        Request::segment(1) == 'clients' ||
        (isset($viewAssigned) && $viewAssigned == 1) ||
        (Request::segment(1) == 'projects' && Request::segment(2) == 'information' && Request::segment(3) != null)
            ? 0
            : 1;
    $visibleColumns = getUserPreferences('tasks');
    $auth_user = getAuthenticatedUser();
@endphp
@if ((isset($tasks) && $tasks > 0) || (isset($emptyState) && $emptyState == 0))
    <div class="<?= $flag == 1 ? 'card ' : '' ?>mt-2">
@endif
@if ($flag == 1 && ((isset($tasks) && $tasks > 0) || (isset($emptyState) && $emptyState == 0)))
    <div class="card-body">
@endif
{{ $slot }}
@if ((isset($tasks) && $tasks > 0) || (isset($emptyState) && $emptyState == 0))
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="input-group input-group-merge">
                <input type="text" class="form-control" id="task_date_between"
                    placeholder="<?= get_label('date_between', 'Date Between') ?>" autocomplete="off">
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="input-group input-group-merge">
                <input type="text" id="task_start_date_between" name="task_start_date_between" class="form-control"
                    placeholder="<?= get_label('start_date_between', 'Start date between') ?>" autocomplete="off">
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="input-group input-group-merge">
                <input type="text" id="task_end_date_between" name="task_end_date_between" class="form-control"
                    placeholder="<?= get_label('end_date_between', 'End date between') ?>" autocomplete="off">
            </div>
        </div>
        @if (getAuthenticatedUser()->can('manage_projects'))
            <div class="col-md-4 mb-3">
                <select class="form-control projects_select" id="task_project_filter" multiple="multiple"
                    data-placeholder="<?= get_label('select_projects', 'Select Projects') ?>">
                </select>
            </div>
        @endif
        @if (isAdminOrHasAllDataAccess() && !isset($viewAssigned))
            @if (explode('_', $id)[0] != 'client' && explode('_', $id)[0] != 'user')
                <div class="col-md-4 mb-3">
                    <select class="form-control users_select" id="task_user_filter" name="user_ids[]"
                        multiple="multiple" data-placeholder="<?= get_label('select_users', 'Select Users') ?>">
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-control clients_select" id="task_client_filter" name="client_ids[]"
                        multiple="multiple" data-placeholder="<?= get_label('select_clients', 'Select Clients') ?>">>
                    </select>
                </div>
            @endif
        @endif
        <div class="col-md-4 mb-3">
            <select class="form-control statuses_filter" id="task_status_filter" name="status_ids[]" multiple="multiple"
                data-placeholder="<?= get_label('select_statuses', 'Select Statuses') ?>">
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <select class="form-control priorities_filter" id="task_priority_filter" name="priority_ids[]"
                multiple="multiple" data-placeholder="<?= get_label('select_priorities', 'Select Priorities') ?>">
            </select>
        </div>
    </div>
    <input type="hidden" id="task_date_between_from">
    <input type="hidden" id="task_date_between_to">
    <input type="hidden" name="task_start_date_from" id="task_start_date_from">
    <input type="hidden" name="task_start_date_to" id="task_start_date_to">
    <input type="hidden" name="task_end_date_from" id="task_end_date_from">
    <input type="hidden" name="task_end_date_to" id="task_end_date_to">
    <input type="hidden" id="is_favorites" value="{{ $favorites ?? '' }}">
    <div class="table-responsive text-nowrap">
        <input type="hidden" id="data_type" value="tasks">
        <input type="hidden" id="data_table" value="task_table">
        <input type="hidden" id="data_reload"
            value="{{ request()->is('home') || request()->is('projects/information/*') ? '1' : '0' }}">
        <input type="hidden" id="save_column_visibility">
        <input type="hidden" id="multi_select">
        <table id="task_table" data-toggle="table" data-loading-template="loadingTemplate"
            data-url="{{ isset($viewAssigned) && $viewAssigned == 1 ? '' : (!empty($id) ? url('/tasks/list/' . $id . '?from_home=' . (request()->is('home') || request()->is('projects/information/*') ? '1' : '0')) : url('/tasks/list?from_home=' . (request()->is('home') || request()->is('projects/information/*') ? '1' : '0'))) }}"
            data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total"
            data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]"
            data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true"
            data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true"
            data-query-params="queryParamsTasks">
            <thead>
                <tr>
                    <th data-checkbox="true"></th>
                    <th data-field="id"
                        data-visible="{{ in_array('id', $visibleColumns) || empty($visibleColumns) ? 'true' : 'false' }}"
                        data-sortable="true">{{ get_label('id', 'ID') }}</th>
                    <th data-field="title"
                        data-visible="{{ in_array('title', $visibleColumns) || empty($visibleColumns) ? 'true' : 'false' }}"
                        data-sortable="true">{{ get_label('task', 'Task') }}</th>
                    <th data-field="project_id"
                        data-visible="{{ in_array('project_id', $visibleColumns) || empty($visibleColumns) ? 'true' : 'false' }}"
                        data-sortable="true">{{ get_label('project', 'Project') }}</th>
                    <th data-field="users"
                        data-visible="{{ in_array('users', $visibleColumns) || empty($visibleColumns) ? 'true' : 'false' }}">
                        {{ get_label('users', 'Users') }}</th>
                    <th data-field="clients"
                        data-visible="{{ in_array('clients', $visibleColumns) || empty($visibleColumns) ? 'true' : 'false' }}">
                        {{ get_label('clients', 'Clients') }}</th>
                    <th data-field="status_id" class="status-column"
                        data-visible="{{ in_array('status_id', $visibleColumns) || empty($visibleColumns) ? 'true' : 'false' }}"
                        data-sortable="true">{{ get_label('status', 'Status') }}</th>
                    <th data-field="priority_id" class="priority-column"
                        data-visible="{{ in_array('priority_id', $visibleColumns) || empty($visibleColumns) ? 'true' : 'false' }}"
                        data-sortable="true">{{ get_label('priority', 'Priority') }}</th>
                    <th data-field="start_date"
                        data-visible="{{ in_array('start_date', $visibleColumns) || empty($visibleColumns) ? 'true' : 'false' }}"
                        data-sortable="true">{{ get_label('starts_at', 'Starts at') }}</th>
                    <th data-field="due_date"
                        data-visible="{{ in_array('due_date', $visibleColumns) || empty($visibleColumns) ? 'true' : 'false' }}"
                        data-sortable="true">{{ get_label('ends_at', 'Ends at') }}</th>
                    <th data-field="created_at"
                        data-visible="{{ in_array('created_at', $visibleColumns) ? 'true' : 'false' }}"
                        data-sortable="true"><?= get_label('created_at', 'Created at') ?></th>
                    <th data-field="updated_at"
                        data-visible="{{ in_array('updated_at', $visibleColumns) ? 'true' : 'false' }}"
                        data-sortable="true"><?= get_label('updated_at', 'Updated at') ?></th>
                    @if (isset($customFields) && $customFields->isNotEmpty())

                        @foreach ($customFields as $customField)
                            {{-- @dd($customFields); --}}

                            @if ($customField->visibility !== null)
                                <th data-field="custom_field_{{ $customField->id }}"
                                    data-visible="{{ in_array('custom_field_' . $customField->id, $visibleColumns) || empty($visibleColumns) ? 'true' : 'false' }}"
                                    data-sortable="true"
                                    data-formatter="{{ $customField->field_type === 'checkbox' ? 'customFieldFormatter' : '' }}">
                                    {{ $customField->field_label }}
                                </th>
                            @endif
                        @endforeach
                    @endif
                    <th data-field="actions"
                        data-visible="{{ in_array('actions', $visibleColumns) || empty($visibleColumns) ? 'true' : 'false' }}">
                        {{ get_label('actions', 'Actions') }}</th>
                </tr>
            </thead>
        </table>
    </div>
@else
    @if (!isset($emptyState) || $emptyState != 0)
        <?php
        $type = 'Tasks';
        ?>
        <x-empty-state-card :type="$type" />
    @endif
@endif
@if ($flag == 1 && ((isset($tasks) && $tasks > 0) || (isset($emptyState) && $emptyState == 0)))
    </div>
@endif
@if ((isset($tasks) && $tasks > 0) || (isset($emptyState) && $emptyState == 0))
    </div>
@endif
<script>
    var label_update = '<?= get_label('update', 'Update') ?>';
    var label_delete = '<?= get_label('delete', 'Delete') ?>';
    var label_duplicate = '<?= get_label('duplicate', 'Duplicate') ?>';
    var label_not_assigned = '<?= get_label('not_assigned', 'Not assigned') ?>';
    var add_favorite = '<?= get_label('add_favorite', 'Click to mark as favorite') ?>';
    var remove_favorite = '<?= get_label('remove_favorite', 'Click to remove from favorite') ?>';
    var id = '<?= $id ?? '' ?>';
</script>
<script src="{{ asset('assets/js/pages/tasks.js') }}"></script>
