<!-- projects card -->
@php
$flag = (Request::segment(1) == 'home' || Request::segment(1) == 'users' || Request::segment(1) == 'clients' || isset($viewAssigned) && $viewAssigned == 1) || isset($projects) && !is_countable($projects) || count($projects) == 0 ? 0 : 1;
$visibleColumns = getUserPreferences('projects');
$auth_user = getAuthenticatedUser();
@endphp
<div class="<?= $flag == 1 ? 'card ' : '' ?>mt-2">
    @if($flag == 1)
    <div class="card-body">
        @endif
        {{$slot}}
        @if ((isset($projects) && is_countable($projects) && count($projects) > 0) || (isset($viewAssigned) && $viewAssigned == 1))
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="input-group input-group-merge">
                    <input type="text" class="form-control" id="project_date_between" placeholder="<?= get_label('date_between', 'Date Between') ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="input-group input-group-merge">
                    <input type="text" id="project_start_date_between" name="start_date_between" class="form-control" placeholder="<?= get_label('start_date_between', 'Start date between') ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="input-group input-group-merge">
                    <input type="text" id="project_end_date_between" name="project_end_date_between" class="form-control" placeholder="<?= get_label('end_date_between', 'End date between') ?>" autocomplete="off">
                </div>
            </div>
            @if(isAdminOrHasAllDataAccess() && !isset($viewAssigned))
            @if(!isset($id) || (explode('_',$id)[0] !='client' && explode('_',$id)[0] !='user'))
            <div class="col-md-4 mb-3">
                <select class="form-control users_select" id="project_user_filter" name="user_ids[]" multiple="multiple" data-placeholder="<?= get_label('select_users', 'Select Users') ?>">
                </select>
            </div>

            <div class="col-md-4 mb-3">
                <select class="form-control clients_select" id="project_client_filter" name="client_ids[]" multiple="multiple" data-placeholder="<?= get_label('select_clients', 'Select Clients') ?>">>
                </select>
            </div>
            @endif
            @endif
            @php
            // Get selected statuses and tags from the request
            $selectedStatuses = request()->input('statuses', []);
            $selectedTags = request()->input('tags', []);

            $statuses = \App\Models\Status::whereIn('id', $selectedStatuses)->get();
            $tags = \App\Models\Tag::whereIn('id', $selectedTags)->get();
            @endphp
            <div class="col-md-4 mb-3">
                <select class="form-control statuses_filter" id="project_status_filter" name="status_ids[]" multiple="multiple" data-placeholder="<?= get_label('select_statuses', 'Select Statuses') ?>">
                    @foreach($statuses as $status)
                    <option value="{{ $status->id }}" selected>{{ $status->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <select class="form-control priorities_filter" id="project_priority_filter" name="priority_ids[]" multiple="multiple" data-placeholder="<?= get_label('select_priorities', 'Select Priorities') ?>">
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <select class="form-control tags_select" id="project_tag_filter" name="tag_ids[]" multiple="multiple" data-placeholder="<?= get_label('select_tags', 'Select Tags') ?>">
                    @foreach($tags as $tag)
                    <option value="{{ $tag->id }}" selected>{{ $tag->title }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <input type="hidden" id="project_date_between_from">
        <input type="hidden" id="project_date_between_to">
        <input type="hidden" name="project_start_date_from" id="project_start_date_from">
        <input type="hidden" name="project_start_date_to" id="project_start_date_to">
        <input type="hidden" name="project_end_date_from" id="project_end_date_from">
        <input type="hidden" name="project_end_date_to" id="project_end_date_to">
        <input type="hidden" id="is_favorites" value="{{$favorites??''}}">
        <div class="table-responsive text-nowrap">
            <input type="hidden" id="data_type" value="projects">
            <input type="hidden" id="data_table" value="projects_table">
            <input type="hidden" id="data_reload" value="{{request()->is('home') ? '1' : '0'}}">
            <input type="hidden" id="save_column_visibility">
            <input type="hidden" id="multi_select">
            <table id="projects_table" data-toggle="table" data-url="{{ isset($viewAssigned) && $viewAssigned == 1 ? '' : (!empty($id) ? url('/projects/listing/' . $id . '?from_home=' . (request()->is('home') ? '1' : '0')) : url('/projects/listing?from_home=' . (request()->is('home') ? '1' : '0'))) }}" data-loading-template="loadingTemplate" data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true" data-query-params="queryParamsProjects">
                <thead>
                    <tr>
                        <th data-checkbox="true"></th>
                        <th data-field="id" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('id', 'ID') ?></th>
                        <th data-field="title" data-visible="{{ (in_array('title', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('title', 'Title') ?></th>
                        <th data-field="users" data-visible="{{ (in_array('users', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">{{ get_label('users', 'Users') }}</th>
                        <th data-field="clients" data-visible="{{ (in_array('clients', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">{{ get_label('clients', 'Clients') }}</th>
                        <th data-field="status_id" data-visible="{{ (in_array('status_id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true" class="status-column"><?= get_label('status', 'Status') ?></th>
                        <th data-field="priority_id" data-visible="{{ (in_array('priority_id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true" class="priority-column"><?= get_label('priority', 'Priority') ?></th>
                        <th data-field="start_date" data-visible="{{ (in_array('start_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('starts_at', 'Starts at') ?></th>
                        <th data-field="end_date" data-visible="{{ (in_array('end_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('ends_at', 'Ends at') ?></th>
                        <th data-field="budget" data-visible="{{ (in_array('budget', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('budget', 'Budget') ?></th>
                        <th data-field="tags" data-visible="{{ (in_array('tags', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('tags', 'Tags') ?></th>
                        <th data-field="created_at" data-visible="{{ (in_array('created_at', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('created_at', 'Created at') ?></th>
                        <th data-field="updated_at" data-visible="{{ (in_array('updated_at', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('updated_at', 'Updated at') ?></th>
                        @if(isset($customFields) && $customFields->isNotEmpty())
                        @foreach ($customFields as $customField)
                            {{-- @dd($customFields); --}}
                            @if($customField->visibility !== null)
                                <th data-field="custom_field_{{ $customField->id }}"
                                    data-visible="{{ (in_array('custom_field_' . $customField->id, $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"
                                    data-sortable="false"
                                    data-formatter="{{ $customField->field_type === 'checkbox' ? 'customFieldFormatter' : '' }}"
                                    class="truncate-col"
                                    title="{{ $customField->field_label }}">
                                    {{ $customField->field_label }}
                                </th>
                            @endif
                        @endforeach
                    @endif
                        <th data-field="actions" data-visible="{{ (in_array('actions', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">{{ get_label('actions', 'Actions') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
        @else
        <?php
        $type = 'Projects'; ?>
        <x-empty-state-card :type="$type" />
        @endif
        @if($flag == 1)
    </div>
    @endif
</div>
<script>
    var label_update = '<?= get_label('update', 'Update') ?>';
    var label_delete = '<?= get_label('delete', 'Delete') ?>';
    var label_not_assigned = '<?= get_label('not_assigned', 'Not assigned') ?>';
    var add_favorite = '<?= get_label('add_favorite', 'Click to mark as favorite') ?>';
    var remove_favorite = '<?= get_label('remove_favorite', 'Click to remove from favorite') ?>';
    var label_duplicate = '<?= get_label('duplicate', 'Duplicate') ?>';
</script>
<script src="{{asset('assets/js/pages/project-list.js')}}"></script>
