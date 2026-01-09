@php
    use App\Models\Workspace;
    use Spatie\Permission\Models\Role;

    $auth_user = getAuthenticatedUser();
    $roles = Role::where('name', '!=', 'admin')->get();
    $isAdminOrHasAllDataAccess = isAdminOrHasAllDataAccess();
    $guard = getGuardName();
@endphp
@if (
    (Request::is('projects*') && !Request::is('projects/information/*')) ||
        Request::is('home') ||
        Request::is('users/profile/*') ||
        Request::is('clients/profile/*'))

    <x-ui.offcanvas id="create_project_offcanvas" title="{{ get_label('create_project', 'Create Project') }}"
        size="offcanvas-responsive" icon="bx bx-plus" :form-id="'create_project_form'" :form-action="url('projects/store')" form-method="POST"
        :submit-label="get_label('create', 'Create')" submit-icon="bx bx-check" :close-label="get_label('close', 'Close')" close-icon="bx bx-x">
        @if (
            !Request::is('projects') &&
                !Request::is('projects/kanban') &&
                !Request::is('projects/favorite') &&
                !Request::is('projects/kanban/favorite') &&
                !Request::is('projects/gantt-chart') &&
                !Request::is('projects/gantt-chart/favorite'))
            <input type="hidden" name="dnr">
            <input type="hidden" name="table" value="projects_table">
        @endif
        <input type="hidden" name="is_favorite" id="is_favorite" value="0">

        <div class="ai-wrapper">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="title" class="form-label"><?= get_label('title', 'Title') ?> <span
                            class="asterisk">*</span></label>
                    <input class="form-control ai-title" type="text" name="title"
                        placeholder="<?= get_label('please_enter_title', 'Please enter title') ?>"
                        value="{{ old('title') }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="status"><?= get_label('status', 'Status') ?> <span
                            class="asterisk">*</span></label>
                    <select class="form-control statusDropdown" name="status_id">
                        @isset($statuses)
                            @foreach ($statuses as $status)
                                @if (canSetStatus($status))
                                    <option value="{{ $status->id }}" data-color="{{ $status->color }}"
                                        {{ old('status') == $status->id ? 'selected' : '' }}>
                                        {{ $status->title }}</option>
                                @endif
                            @endforeach
                        @endisset
                    </select>
                    <div class="mt-2">
                        <a href="javascript:void(0);" class="openCreateStatusModal"><button type="button"
                                class="btn btn-sm btn-primary action_create_statuses" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title=" <?= get_label('create_status', 'Create status') ?>"><i
                                    class="bx bx-plus"></i></button></a>
                        <a href="{{ url('status/manage') }}"><button type="button"
                                class="btn btn-sm btn-primary action_manage_statuses" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title="<?= get_label('manage_statuses', 'Manage statuses') ?>"><i
                                    class="bx bx-list-ul"></i></button></a>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= get_label('priority', 'Priority') ?></label>
                    <select class="form-select priorityDropdown" name="priority_id"
                        data-placeholder="<?= get_label('please_select', 'Please select') ?>">
                        <option></option>
                        @isset($priorities)
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->id }}" data-color="{{ $priority->color }}">
                                    {{ $priority->title }}
                                </option>
                            @endforeach
                        @endisset
                    </select>
                    <div class="mt-2">
                        <a href="javascript:void(0);" class="openCreatePriorityModal"><button type="button"
                                class="btn btn-sm btn-primary action_create_priorities" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title=" <?= get_label('create_priority', 'Create Priority') ?>"><i
                                    class="bx bx-plus"></i></button></a>
                        <a href="{{ url('priority/manage') }}"><button type="button"
                                class="btn btn-sm btn-primary action_manage_priorities" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title="<?= get_label('manage_priorities', 'Manage Priorities') ?>"><i
                                    class="bx bx-list-ul"></i></button></a>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="budget" class="form-label"><?= get_label('budget', 'Budget') ?></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text">{{ $general_settings['currency_symbol'] }}</span>
                        <input class="form-control currency" type="text" id="budget" name="budget"
                            placeholder="<?= get_label('please_enter_budget', 'Please enter budget') ?>"
                            value="{{ old('budget') }}">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="start_date"><?= get_label('starts_at', 'Starts at') ?></label>
                    <input type="text" id="start_date" name="start_date" class="form-control"
                        placeholder="{{ get_label('please_select', 'Please Select') }}" autocomplete="off">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="due_date"><?= get_label('ends_at', 'Ends at') ?></label>
                    <input type="text" id="end_date" name="end_date" class="form-control"
                        placeholder="{{ get_label('please_select', 'Please Select') }}" autocomplete="off">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="">
                        <?= get_label('task_accessibility', 'Task Accessibility') ?>
                        <i class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-offset="0,4"
                            data-bs-placement="top" data-bs-html="true" title=""
                            data-bs-original-title="<b>{{ get_label('assigned_users', 'Assigned Users') }}:</b> {{ get_label('assigned_users_info', 'You Will Need to Manually Select Task Users When Creating Tasks Under This Project.') }} <br><b>{{ get_label('project_users', 'Project Users') }}:</b> {{ get_label('project_users_info', 'When Creating Tasks Under This Project, the Task Users Selection Will Be Automatically Filled With Project Users.') }}"
                            data-bs-toggle="tooltip" data-bs-placement="top"></i>
                    </label>
                    <select class="form-select" name="task_accessibility">
                        <option value="assigned_users"><?= get_label('assigned_users', 'Assigned Users') ?>
                        </option>
                        <option value="project_users"><?= get_label('project_users', 'Project Users') ?>
                        </option>
                    </select>
                </div>
                @if ($isAdminOrHasAllDataAccess)
                    <div class="col-md-6 mb-3">
                        <label class="form-check-label"
                            for="clientCanDiscussProject">{{ get_label('client_can_discuss', 'Client Can Discuss') }}?</label>
                        <i class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-placement="top"
                            title="{{ get_label('client_can_discuss_info', 'Allows the client to participate in project discussions.') }}"></i>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="clientCanDiscussProject"
                                name="clientCanDiscuss">
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="tasksTimeEntriesSwitch">
                            <?= get_label('tasks_time_entries', 'Tasks Time Entries') ?>
                            <i class="bx bx-info-circle text-primary" data-bs-toggle="tooltip" data-bs-offset="0,4"
                                data-bs-placement="top" data-bs-html="true" title=""
                                data-bs-original-title="<b>{{ get_label('tasks_time_entries', 'Tasks Time Entries') }}:</b> {{ get_label('tasks_time_entries_info', 'To use Time Entries in tasks, you need to enable this option. It allows time tracking and entry management for tasks under this project.') }}">
                            </i>
                        </label>
                        <div class="form-check form-switch">
                            <input type="hidden" name="enable_tasks_time_entries" value="0">
                            <input class="form-check-input" type="checkbox" name="enable_tasks_time_entries"
                                id="edit_tasks_time_entries" value="1">
                            <label class="form-check-label" for="tasksTimeEntriesSwitch">
                                <?= get_label('enable', 'Enable') ?>
                            </label>
                        </div>
                    </div>
                @endif
            </div>
            <div class="row">
                <div class="mb-3">
                    <label class="form-label" for="user_id"><?= get_label('select_users', 'Select users') ?></label>
                    <select class="form-control users_select" name="user_id[]" multiple="multiple"
                        data-placeholder="<?= get_label('type_to_search', 'Type to search') ?>">
                        @if ($guard == 'web')
                            <option value="{{ $auth_user->id }}" selected>{{ $auth_user->first_name }}
                                {{ $auth_user->last_name }}</option>
                        @endif
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="mb-3">
                    <label class="form-label"
                        for="client_id"><?= get_label('select_clients', 'Select clients') ?></label>
                    <select class="form-control clients_select" name="client_id[]" multiple="multiple"
                        data-placeholder="<?= get_label('type_to_search', 'Type to search') ?>">
                        @if ($guard == 'client')
                            <option value="{{ $auth_user->id }}" selected>{{ $auth_user->first_name }}
                                {{ $auth_user->last_name }}</option>
                        @endif
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label" for=""><?= get_label('select_tags', 'Select tags') ?></label>
                    <select class="form-control tags_select" name="tag_ids[]" multiple="multiple"
                        data-placeholder="<?= get_label('type_to_search', 'Type to search') ?>">
                    </select>
                    <div class="mt-2">
                        <a href="javascript:void(0);" class="openCreateTagModal"><button type="button"
                                class="btn btn-sm btn-primary action_create_tags" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title=" <?= get_label('create_tag', 'Create tag') ?>"><i
                                    class="bx bx-plus"></i></button></a>
                        <a href="{{ url('tags/manage') }}"><button type="button"
                                class="btn btn-sm btn-primary action_manage_tags" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title="<?= get_label('manage_tags', 'Manage tags') ?>"><i
                                    class="bx bx-list-ul"></i></button></a>
                    </div>
                </div>
            </div>
            <div class="row align-items-center mb-2">
                <!-- Description Label -->
                <div class="col-md-6">
                    <label for="description" class="form-label mb-0">
                        <?= get_label('description', 'Description') ?>
                    </label>
                </div>

                <!-- Custom Prompt Switch + Generate Button -->
                <div class="col-md-6 text-md-end mt-md-0 mt-2">
                    <div class="d-inline-flex align-items-center">
                        <div class="form-check form-switch me-3">
                            <input class="form-check-input enableCustomPrompt" type="checkbox">
                            <label class="form-check-label" for="enableCustomPrompt">
                                <?= get_label('use_custom_prompt', 'Use Custom Prompt') ?>
                            </label>
                        </div>

                        <button type="button" class="btn btn-outline-primary generate-ai btn-sm">
                            <i class="fas fa-magic me-1"></i>
                            <?= get_label('generate_with_ai', 'Generate with AI') ?>
                        </button>

                        <i class="bx bx-info-circle text-primary ms-2" data-bs-toggle="tooltip" data-bs-offset="0,4"
                            data-bs-placement="top" data-bs-html="true" title=""
                            data-bs-original-title="<b>{{ get_label('generate_with_ai', 'Generate with AI') }}:</b> {{ get_label('generate_with_ai_info', 'Enable custom prompt to write your own AI prompt. If disabled, the AI will use the title to generate the description. Max 255 characters will be used.') }}">
                        </i>

                        <div class="spinner-border text-primary ai-loader d-none w-px-20 h-px-20 ms-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Prompt Input (initially hidden) -->
            <div class="customPromptContainer d-none mb-2 mt-2">
                <textarea class="form-control ai-custom-prompt" rows="2"
                    placeholder="<?= get_label('enter_custom_prompt', 'Enter custom prompt for AI generation') ?>"></textarea>
            </div>

            <!-- Description Textarea -->
            <div class="mb-3">
                <textarea class="form-control description ai-output" rows="5" name="description"
                    placeholder="<?= get_label('please_enter_description', 'Please enter description') ?>"><?= old('description') ?></textarea>
            </div>
            <div class="row">
                <div class="mb-3">
                    <label class="form-label"><?= get_label('note', 'Note') ?></label>
                    <textarea class="form-control" name="note" rows="3"
                        placeholder="<?= get_label('optional_note', 'Optional Note') ?>"></textarea>
                </div>
            </div>
            @php
                $isEdit = true;
            @endphp
            <!-- Custom Fields Section -->
            <x-custom-fields :isEdit="$isEdit" :fields="$projectCustomFields" />

            @if (!$isAdminOrHasAllDataAccess)
                <div class="alert alert-primary" role="alert">
                    <?= get_label('you_will_be_project_participant_automatically', 'You will be project participant automatically.') ?>
                </div>
            @endif
        </div>
    </x-ui.offcanvas>
@endif

@if (Request::is('projects*') ||
        Request::is('home') ||
        Request::is('users/profile/*') ||
        Request::is('clients/profile/*') ||
        Request::is('users') ||
        Request::is('clients'))

    <x-ui.offcanvas id="edit_project_offcanvas" title="{{ get_label('update_project', 'Update Project') }}"
        size="offcanvas-responsive" icon="bx bx-edit" :form-id="'edit_project_form'" :form-action="url('projects/update')" form-method="POST"
        :submit-label="get_label('update', 'Update')" submit-icon="bx bx-check" :close-label="get_label('close', 'Close')" close-icon="bx bx-x">

        <input type="hidden" name="id" id="project_id">
        @if (
            !Request::is([
                'projects',
                'projects/information/*',
                'projects/kanban',
                'projects/favorite',
                'projects/kanban/favorite',
                'projects/gantt-chart/favorite',
            ]))
            <input type="hidden" name="dnr">
            <input type="hidden" name="table" value="projects_table">
        @endif

        @csrf
        <div class="ai-wrapper">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="title" class="form-label"><?= get_label('title', 'Title') ?> <span
                            class="asterisk">*</span></label>
                    <input class="form-control ai-title" type="text" name="title" id="project_title"
                        placeholder="<?= get_label('please_enter_title', 'Please enter title') ?>"
                        value="{{ old('title') }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="status"><?= get_label('status', 'Status') ?> <span
                            class="asterisk">*</span></label>
                    <select class="form-control statusDropdown" name="status_id" id="project_status_id">
                        @isset($statuses)
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" data-color="{{ $status->color }}"
                                    {{ old('status') == $status->id ? 'selected' : '' }}>{{ $status->title }}
                                </option>
                            @endforeach
                        @endisset
                    </select>
                    <div class="mt-2">
                        <a href="javascript:void(0);" class="openCreateStatusModal"><button type="button"
                                class="btn btn-sm btn-primary action_create_statuses" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title=" <?= get_label('create_status', 'Create status') ?>"><i
                                    class="bx bx-plus"></i></button></a>
                        <a href="{{ url('status/manage') }}"><button type="button"
                                class="btn btn-sm btn-primary action_manage_statuses" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title="<?= get_label('manage_statuses', 'Manage statuses') ?>"><i
                                    class="bx bx-list-ul"></i></button></a>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= get_label('priority', 'Priority') ?></label>
                    <select class="form-select priorityDropdown" name="priority_id" id="project_priority_id"
                        data-placeholder="<?= get_label('please_select', 'Please select') ?>">
                        <option></option>
                        @isset($priorities)
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->id }}" data-color="{{ $priority->color }}">
                                    {{ $priority->title }}</option>
                            @endforeach
                        @endisset
                    </select>
                    <div class="mt-2">
                        <a href="javascript:void(0);" class="openCreatePriorityModal"><button type="button"
                                class="btn btn-sm btn-primary action_create_priorities" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title=" <?= get_label('create_priority', 'Create Priority') ?>"><i
                                    class="bx bx-plus"></i></button></a>
                        <a href="{{ url('priority/manage') }}"><button type="button"
                                class="btn btn-sm btn-primary action_manage_priorities" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title="<?= get_label('manage_priorities', 'Manage Priorities') ?>"><i
                                    class="bx bx-list-ul"></i></button></a>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="budget" class="form-label"><?= get_label('budget', 'Budget') ?></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text">{{ $general_settings['currency_symbol'] }}</span>
                        <input class="form-control currency" type="text" id="project_budget" name="budget"
                            placeholder="<?= get_label('please_enter_budget', 'Please enter budget') ?>"
                            value="{{ old('budget') }}">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="start_date"><?= get_label('starts_at', 'Starts at') ?></label>
                    <input type="text" id="update_start_date" name="start_date" class="form-control"
                        placeholder="{{ get_label('please_select', 'Please Select') }}" autocomplete="off">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="due_date"><?= get_label('ends_at', 'Ends at') ?></label>
                    <input type="text" id="update_end_date" name="end_date" class="form-control"
                        placeholder="{{ get_label('please_select', 'Please Select') }}" autocomplete="off">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="">
                        <?= get_label('task_accessibility', 'Task Accessibility') ?>
                        <i class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-offset="0,4"
                            data-bs-placement="top" data-bs-html="true" title=""
                            data-bs-original-title="<b>{{ get_label('assigned_users', 'Assigned Users') }}:</b> {{ get_label('assigned_users_info', 'You Will Need to Manually Select Task Users When Creating Tasks Under This Project.') }}<br><b>{{ get_label('project_users', 'Project Users') }}:</b> {{ get_label('project_users_info', 'When Creating Tasks Under This Project, the Task Users Selection Will Be Automatically Filled With Project Users.') }}"
                            data-bs-toggle="tooltip" data-bs-placement="top"></i>
                    </label>
                    <select class="form-select" name="task_accessibility" id="task_accessibility">
                        <option value="assigned_users"><?= get_label('assigned_users', 'Assigned Users') ?>
                        </option>
                        <option value="project_users"><?= get_label('project_users', 'Project Users') ?>
                        </option>
                    </select>
                </div>
                @if ($isAdminOrHasAllDataAccess)
                    <div class="col-md-6 mb-3">
                        <label class="form-check-label"
                            for="updateClientCanDiscussProject">{{ get_label('client_can_discuss', 'Client Can Discuss') }}?</label>
                        <i class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-placement="top"
                            title="{{ get_label('client_can_discuss_info', 'Allows the client to participate in project discussions.') }}"></i>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="updateClientCanDiscussProject"
                                name="clientCanDiscuss">
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="tasksTimeEntriesSwitch">
                            <?= get_label('tasks_time_entries', 'Tasks Time Entries') ?>
                            <i class="bx bx-info-circle text-primary" data-bs-toggle="tooltip" data-bs-offset="0,4"
                                data-bs-placement="top" data-bs-html="true" title=""
                                data-bs-original-title="<b>{{ get_label('tasks_time_entries', 'Tasks Time Entries') }}:</b> {{ get_label('tasks_time_entries_info', 'To use Time Entries in tasks, you need to enable this option. It allows time tracking and entry management for tasks under this project.') }}">
                            </i>
                        </label>
                        <div class="form-check form-switch">
                            <input type="hidden" name="enable_tasks_time_entries" value="0">
                            <input class="form-check-input" type="checkbox" name="enable_tasks_time_entries"
                                id="tasks_time_entries" value="1"
                                {{ old('tasks_time_entries') ? 'checked' : '' }}>
                            <label class="form-check-label" for="tasksTimeEntriesSwitch">
                                {{ get_label('enable', 'Enable') }}
                            </label>
                        </div>
                    </div>
                @endif
            </div>
            <div class="row">
                <div class="mb-3">
                    <label class="form-label" for="user_id"><?= get_label('select_users', 'Select users') ?></label>
                    <select class="form-control users_select" name="user_id[]" multiple="multiple"
                        data-placeholder="<?= get_label('type_to_search', 'Type to search') ?>">
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="mb-3">
                    <label class="form-label"
                        for="client_id"><?= get_label('select_clients', 'Select clients') ?></label>
                    <select class="form-control clients_select" name="client_id[]" multiple="multiple"
                        data-placeholder="<?= get_label('type_to_search', 'Type to search') ?>">
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label" for=""><?= get_label('select_tags', 'Select tags') ?></label>
                    <select class="form-control tags_select" name="tag_ids[]" multiple="multiple"
                        data-placeholder="<?= get_label('type_to_search', 'Type to search') ?>">
                    </select>
                    <div class="mt-2">
                        <a href="javascript:void(0);" class="openCreateTagModal"><button type="button"
                                class="btn btn-sm btn-primary action_create_tags" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title=" <?= get_label('create_tag', 'Create tag') ?>"><i
                                    class="bx bx-plus"></i></button></a>
                        <a href="{{ url('tags/manage') }}"><button type="button"
                                class="btn btn-sm btn-primary action_manage_tags" data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-original-title="<?= get_label('manage_tags', 'Manage tags') ?>"><i
                                    class="bx bx-list-ul"></i></button></a>
                    </div>
                </div>
            </div>
            <div class="row align-items-center mb-2">
                <!-- Description Label -->
                <div class="col-md-6">
                    <label for="description" class="form-label mb-0">
                        <?= get_label('description', 'Description') ?>
                    </label>
                </div>

                <!-- Custom Prompt Switch + Generate Button -->
                <div class="col-md-6 text-md-end mt-md-0 mt-2">
                    <div class="d-inline-flex align-items-center">
                        <div class="form-check form-switch me-3">
                            <input class="form-check-input enableCustomPrompt" type="checkbox">
                            <label class="form-check-label" for="enableCustomPrompt">
                                <?= get_label('use_custom_prompt', 'Use Custom Prompt') ?>
                            </label>
                        </div>
                        <button type="button" class="btn btn-outline-primary generate-ai btn-sm">
                            <i class="fas fa-magic me-1"></i>
                            <?= get_label('generate_with_ai', 'Generate with AI') ?>
                        </button>

                        <i class="bx bx-info-circle text-primary ms-2" data-bs-toggle="tooltip" data-bs-offset="0,4"
                            data-bs-placement="top" data-bs-html="true" title=""
                            data-bs-original-title="<b>{{ get_label('generate_with_ai', 'Generate with AI') }}:</b> {{ get_label('generate_with_ai_info', 'Enable custom prompt to write your own AI prompt. If disabled, the AI will use the title to generate the description. Max 255 characters will be used.') }}">
                        </i>

                        <div class="spinner-border text-primary ai-loader d-none w-px-20 h-px-20 ms-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Prompt Input (initially hidden) -->
            <div class="customPromptContainer d-none mb-2 mt-2">
                <textarea class="form-control ai-custom-prompt" rows="2"
                    placeholder="<?= get_label('enter_custom_prompt', 'Enter custom prompt for AI generation') ?>"></textarea>
            </div>

            <!-- Description Textarea -->
            <div class="mb-3">
                <textarea class="form-control description ai-output" rows="5" name="description" id="project_description"
                    placeholder="<?= get_label('please_enter_description', 'Please enter description') ?>"><?= old('description') ?></textarea>
            </div>
            <div class="row">
                <div class="mb-3">
                    <label class="form-label"><?= get_label('note', 'Note') ?></label>
                    <textarea class="form-control" name="note" id="projectNote" rows="3"
                        placeholder="<?= get_label('optional_note', 'Optional Note') ?>"></textarea>
                </div>
            </div>
            @php
                $isEdit = true;
            @endphp
            <!-- Custom Fields Section -->
            <x-custom-fields :isEdit="$isEdit" :fields="$projectCustomFields" />
        </div>
    </x-ui.offcanvas>
@endif
