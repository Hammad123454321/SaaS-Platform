@extends('layout')
@section('title')
<?= get_label('project_details', 'Project details') ?>
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
                    <li class="breadcrumb-item">
                        <a href="{{url(getUserPreferences('projects', 'default_view'))}}"><?= get_label('projects', 'Projects') ?></a>
                    </li>
                    <li class="breadcrumb-item active">
                        {{$project->title}}
                    </li>
                </ol>
            </nav>
        </div>
        @php
        $taskDefaultView = getUserPreferences('tasks', 'default_view')=='tasks'?'tasks/list':'tasks/draggable';
        @endphp
        <div>
            <a href="{{url('projects/mind-map/'.$project->id)}}">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('mind_map', 'Mind Map') ?>">
                    <i class="bx bx-sitemap"></i>
                </button>
            </a>
            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#create_task_modal"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title=" <?= get_label('create_task', 'Create task') ?>"><i class="bx bx-plus"></i></button></a>
            <a href="{{url('projects/'.$taskDefaultView.'/' . $project->id)}}"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('tasks', 'Tasks') ?>"><i class="bx bx-task"></i></button></a>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            @if ($projectTags->isNotEmpty())
                            <div class="mb-3">
                                @foreach ($projectTags as $tag)
                                <span class="badge bg-{{ $tag->color }}">{{ $tag->title }}</span>
                                @endforeach
                            </div>
                            @endif
                            <h2 class="fw-bold">{{ $project->title }}
                                <a href="javascript:void(0);" class="mx-2">
                                    <i class='bx {{getFavoriteStatus($project->id) ? "bxs" : "bx"}}-star favorite-icon text-warning' data-id="{{$project->id}}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="{{getFavoriteStatus($project->id) ? get_label('remove_favorite', 'Click to remove from favorite') : get_label('add_favorite', 'Click to mark as favorite')}}" data-favorite="{{getFavoriteStatus($project->id) ? 1 : 0}}"></i>
                                </a>
                                <a href="javascript:void(0);">
                                    <i class='bx {{getPinnedStatus($project->id) ? "bxs" : "bx"}}-pin pinned-icon text-success' data-id="{{$project->id}}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="{{getPinnedStatus($project->id) ? get_label('click_unpin', 'Click to Unpin') : get_label('click_pin', 'Click to Pin')}}" data-pinned="{{getPinnedStatus($project->id)}}" data-require_reload="0"></i>
                                </a>
                            </h2>
                            <div class="row">
                                <div class="col-md-6 mt-3 mb-3">
                                    <label class="form-label" for="start_date"><?= get_label('users', 'Users') ?></label>
                                    <?php
                                    $users = $project->users;
                                    if (count($users) > 0) { ?>
                                        <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center flex-wrap">
                                            @foreach($users as $user)
                                            <li class="avatar avatar-sm pull-up" title="{{ $user->first_name }} {{ $user->last_name }}"><a href="{{ url('/users/profile/' . $user->id) }}">
                                                    <img src="{{$user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')}}" class="rounded-circle" alt="{{$user->first_name}} {{$user->last_name}}">
                                                </a></li>
                                            @endforeach
                                            <a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="{{$project->id}}"><span class="bx bx-edit"></span></a>
                                        </ul>
                                    <?php } else { ?>
                                        <p><span class="badge bg-primary"><?= get_label('not_assigned', 'Not assigned') ?></span><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="{{$project->id}}"><span class="bx bx-edit"></span></a></p>
                                    <?php } ?>
                                </div>
                                <div class="col-md-6  mt-3 mb-3">
                                    <label class="form-label" for="end_date"><?= get_label('clients', 'Clients') ?></label>
                                    <?php
                                    $clients = $project->clients;
                                    if (count($clients) > 0) { ?>
                                        <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center flex-wrap">
                                            @foreach($clients as $client)
                                            <li class="avatar avatar-sm pull-up" title="{{ $client->first_name }} {{ $client->last_name }}"><a href="{{ url('/clients/profile/' . $client->id) }}">
                                                    <img src="{{$client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')}}" class="rounded-circle" alt="{{$client->first_name}} {{$client->last_name}}">
                                                </a></li>
                                            @endforeach
                                            <a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="{{$project->id}}"><span class="bx bx-edit"></span></a>
                                        </ul>
                                    <?php } else { ?>
                                        <p><span class="badge bg-primary"><?= get_label('not_assigned', 'Not assigned') ?></span><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="{{$project->id}}"><span class="bx bx-edit"></span></a></p>
                                    <?php } ?>
                                </div>
                                <div class="col-md-{{$project->note ? '7' : '6'}} mb-3">
                                    <label class="form-label"><?= get_label('status', 'Status') ?></label>
                                    <div class="d-flex align-items-center">
                                        <select class="form-select form-select-sm select-bg-label-{{$project->status->color}}" id="statusSelect" data-id="{{ $project->id }}" data-original-status-id="{{$project->status->id}}" data-original-color-class="select-bg-label-{{$project->status->color}}">
                                            @foreach($statuses as $status)
                                            @php
                                            $disabled = canSetStatus($status) ? '' : 'disabled';
                                            @endphp
                                            <option value="{{ $status->id }}" class="badge bg-label-{{ $status->color }}" {{ $project->status->id == $status->id ? 'selected' : '' }} {{ $disabled }}>
                                                {{ $status->title }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @if($project->note)
                                        <i class="bx bx-notepad ms-1 text-primary" data-bs-toggle="tooltip" data-bs-offset="0,4" data-bs-placement="top" data-bs-original-title="{{$project->note}}"></i>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-{{$project->note ? '5' : '6'}} mb-3">
                                    <label for="prioritySelect" class="form-label"><?= get_label('priority', 'Priority') ?></label>
                                    <select class="form-select form-select-sm select-bg-label-{{$project->priority?$project->priority->color:'secondary'}}" id="prioritySelect" data-id="{{ $project->id }}" data-original-priority-id="{{$project->priority ? $project->priority->id : ''}}" data-original-color-class="select-bg-label-{{$project->priority?$project->priority->color:'secondary'}}">
                                        <option value="" class="badge bg-label-secondary">-</option>
                                        @foreach($priorities as $priority)
                                        <option value="{{$priority->id}}" class="badge bg-label-{{$priority->color}}" {{ $project->priority && $project->priority->id == $priority->id ? 'selected' : '' }}>
                                            {{$priority->title}}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="my-0" />
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-4">
                            <div class="card overflow-hidden mb-4 statisticsDiv">
                                <div class="card-header pt-3 pb-1">
                                    <div class="card-title mb-0">
                                        <h5 class="m-0 me-2"><?= get_label('task_statistics', 'Task statistics') ?></h5>
                                    </div>
                                    <div class="my-3">
                                        <div id="taskStatisticsChart"></div>
                                    </div>
                                </div>
                                <div class="card-body" id="task-statistics">
                                    <?php
                                    // Calculate status counts
                                    $statusCounts = [];
                                    $total_tasks_count = 0;
                                    foreach ($statuses as $status) {
                                        $statusCount = 0;
                                        if (isAdminOrHasAllDataAccess()) {
                                            $statusCount = $project->tasks->where('status_id', $status->id)->count();
                                        } else {
                                            if (isClient()) {
                                                $statusCount = $project->tasks()
                                                    ->whereIn('project_id', getAuthenticatedUser()->projects->pluck('id'))
                                                    ->where('status_id', $status->id)
                                                    ->count();
                                            } else {
                                                $statusCount = $project->tasks()
                                                    ->whereIn('id', getAuthenticatedUser()->tasks->pluck('id'))
                                                    ->where('status_id', $status->id)
                                                    ->count();
                                            }
                                        }
                                        $statusCounts[$status->id] = $statusCount;
                                        $total_tasks_count += $statusCount;
                                    }
                                    // Sort statuses by count in descending order
                                    arsort($statusCounts);
                                    ?>
                                    <ul class="p-0 m-0">
                                        @foreach ($statusCounts as $statusId => $count)
                                        <?php $status = $statuses->where('id', $statusId)->first(); ?>
                                        <li class="d-flex mb-4 pb-1">
                                            <div class="avatar flex-shrink-0 me-3">
                                                <span class="avatar-initial rounded bg-label-{{$status->color}}"><i class="bx bx-task"></i></span>
                                            </div>
                                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="me-2">
                                                    <a href="{{ url(getUserPreferences('tasks', 'default_view')) }}?project={{ $project->id }}&status={{ $status->id }}">
                                                        <h6 class="mb-0">{{ $status->title }}</h6>
                                                    </a>
                                                </div>
                                                <div class="user-progress">
                                                    <div class="status-count">
                                                        <small class="fw-semibold">{{$count}}</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                        @endforeach
                                    </ul>
                                    <li class="d-flex mb-4 pb-1">
                                        <div class="avatar flex-shrink-0 me-3">
                                            <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-menu"></i></span>
                                        </div>
                                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                            <div class="me-2">
                                                <h5 class="mb-0"><?= get_label('total', 'Total') ?></h5>
                                            </div>
                                            <div class="user-progress">
                                                <div class="status-count">
                                                    <h5 class="mb-0">{{$total_tasks_count}}</h5>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-12 col-6 mb-4">
                            <!-- "Starts at" card -->
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="avatar flex-shrink-0">
                                            <i class="menu-icon tf-iconsbx bx bx-calendar-check bx-md text-success"></i>
                                        </div>
                                    </div>
                                    <span class="fw-semibold d-block mb-1"><?= get_label('starts_at', 'Starts at') ?></span>
                                    <h3 class="card-title mb-2">{{ format_date($project->start_date) }}</h3>
                                </div>
                            </div>
                            @php
                            use Carbon\Carbon;

                            $fromDate = $project->start_date ? Carbon::parse($project->start_date) : null;
                            $toDate = $project->end_date ? Carbon::parse($project->end_date) : null;

                            if ($fromDate && $toDate) {
                            $duration = $fromDate->diffInDays($toDate) + 1;
                            $durationText = $duration . ' day' . ($duration > 1 ? 's' : '');
                            } else {
                            $durationText = '-';
                            }
                            @endphp
                            <div class="card mt-4">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="avatar flex-shrink-0">
                                            <i class="menu-icon tf-iconsbx bx bx-time bx-md text-primary"></i>
                                        </div>
                                    </div>
                                    <span class="fw-semibold d-block mb-1"><?= get_label('duration', 'Duration') ?></span>
                                    <h3 class="card-title mb-2">{{ $durationText }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-12 col-6 mb-4">
                            <!-- "Ends at" card -->
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="avatar flex-shrink-0">
                                            <i class="menu-icon tf-icons bx bx-calendar-x bx-md text-danger"></i>
                                        </div>
                                    </div>
                                    <span class="fw-semibold d-block mb-1"><?= get_label('ends_at', 'Ends at') ?></span>
                                    <h3 class="card-title mb-2">{{ format_date($project->end_date) }}</h3>
                                </div>
                            </div>
                            <div class="card mt-4">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="avatar flex-shrink-0">
                                            <i class="menu-icon tf-icons bx bx-purchase-tag-alt bx-md text-warning"></i>
                                        </div>
                                    </div>
                                    <span class="fw-semibold d-block mb-1"><?= get_label('budget', 'Budget') ?></span>
                                    <h3 class="card-title mb-2">{{ !empty($project->budget) ? format_currency($project->budget) : '-' }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title">
                                        <h5><?= get_label('description', 'Description') ?></h5>
                                    </div>
                                    <p>
                                        <!-- Add your project description here -->
                                        <?= (filled($project->description)) ? $project->description : '-' ?>
                                    </p>
                                </div>
                            </div>
                        </div>


                        <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">{{ get_label('custom_fields', 'Custom Fields') }}</h5>
                        </div>
                        <div class="card-body">

                            {{-- @dd($customFields); --}}
                            @php
                                $hasValues = false;
                                foreach ($customFields as $field) {
                                    if ($project->getCustomFieldValue($field->id)) {
                                        $hasValues = true;
                                        break;
                                    }
                                }
                            @endphp

                            @if ($customFields->isNotEmpty())
                                @if ($hasValues)
                                    <div class="row">
                                        @foreach ($customFields as $field)
                                            @php
                                                $fieldValue = $project->getCustomFieldValue($field->id);
                                            @endphp
                                            @if ($fieldValue)
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">{{ $field->field_label }}</label>

                                                    @switch($field->field_type)
                                                        @case('text')
                                                        @case('number')

                                                        @case('password')
                                                        @case('email')
                                                            <input class="form-control" value="{{ $fieldValue }}" readonly>
                                                        @break

                                                        @case('textarea')
                                                            <textarea class="form-control" readonly>{{ $fieldValue }}</textarea>
                                                        @break

                                                        @case('select')
                                                        @case('radio')
                                                            @php
                                                                $options = json_decode($field->options, true);
                                                                $currentValue = $fieldValue;
                                                            @endphp
                                                            @if ($field->field_type == 'select')
                                                                <select class="form-control" disabled>
                                                                    <option value="">Select an option</option>
                                                                    @foreach ($options as $option)
                                                                        <option value="{{ $option }}"
                                                                            {{ $currentValue == $option ? 'selected' : '' }}>
                                                                            {{ $option }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @else
                                                                <div>
                                                                    @foreach ($options as $option)
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="radio"
                                                                                value="{{ $option }}"
                                                                                {{ $currentValue == $option ? 'checked' : '' }}
                                                                                disabled>
                                                                            <label class="form-check-label">
                                                                                {{ $option }}
                                                                            </label>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        @break

                                                        @case('date')
                                                            <input type="text" class="form-control"
                                                                value="{{ $fieldValue ? $fieldValue : '' }}" readonly>
                                                        @break

                                                        @case('checkbox')
                                                            @php
                                                                $options = json_decode($field->options, true) ?? [];
                                                                $currentValues = $fieldValue
                                                                    ? json_decode($fieldValue, true)
                                                                    : [];
                                                                if (!is_array($currentValues)) {
                                                                    $currentValues = [$currentValues];
                                                                }
                                                            @endphp
                                                            <div>
                                                                @foreach ($options as $option)
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox"
                                                                            value="{{ $option }}"
                                                                            {{ in_array($option, $currentValues) ? 'checked' : '' }}
                                                                            disabled>
                                                                        <label class="form-check-label">
                                                                            {{ $option }}
                                                                        </label>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @break

                                                        @case('file')
                                                            @if ($fieldValue)
                                                                <div>
                                                                    <a href="{{ asset('storage/' . $fieldValue) }}" target="_blank"
                                                                        class="btn btn-sm btn-outline-primary">
                                                                        <i class='bx bx-file'></i> View File
                                                                    </a>
                                                                </div>
                                                            @else
                                                                <p class="text-muted mb-0">No file uploaded</p>
                                                            @endif
                                                        @break

                                                        @case('color')
                                                            <div class="d-flex align-items-center">
                                                                <div class="me-2"
                                                                    style="width: 25px; height: 25px; background-color: {{ $fieldValue ?? '#FFFFFF' }}; border: 1px solid #ddd; border-radius: 4px;">
                                                                </div>
                                                                <input type="text" class="form-control"
                                                                    value="{{ $fieldValue }}" readonly>
                                                            </div>
                                                        @break
                                                    @endswitch

                                                    @if ($field->guide_text)
                                                        <small
                                                            class="form-text text-muted">{{ $field->guide_text }}</small>
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted mb-0">
                                        <i class='bx bx-info-circle me-1'></i>
                                        {{ get_label('no_custom_fields', 'No custom fields for this project') }}
                                    </p>
                                @endif
                            @else
                                <p class="text-muted mb-0">
                                    <i class='bx bx-info-circle me-1'></i>
                                    {{ get_label('no_custom_fields', 'No custom fields for this project') }}
                                </p>
                            @endif


                        </div>

                    </div>


                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" id="media_type_id" value="{{$project->id}}">
        @if($auth_user->can('manage_tasks') || Auth::guard('web')->check() || $project->client_can_discuss || $auth_user->can('manage_milestones') || $auth_user->can('manage_media') || $auth_user->can('manage_activity_log'))
        <!-- Tabs -->
        <div class="nav-align-top mt-2">
            <ul class="nav nav-tabs" role="tablist">
                @php
                $activeTab = '';
                @endphp
                @if ($auth_user->can('manage_tasks'))
                <li class="nav-item">
                    <button type="button" class="nav-link {{ empty($activeTab) ? 'active' : '' }}" role="tab"
                        data-bs-toggle="tab" data-bs-target="#navs-top-tasks" aria-controls="navs-top-tasks">
                        <i class="menu-icon tf-icons bx bx-task text-primary"></i><?= get_label('tasks', 'Tasks') ?>
                    </button>
                </li>
                @php
                if (empty($activeTab)) {
                $activeTab = 'tasks';
                }
                @endphp
                @endif

                @if (Auth::guard('web')->check() || $project->client_can_discuss)
                <li class="nav-item">
                    <button type="button" class="nav-link {{ $activeTab == 'discussions' ? 'active' : '' }}" role="tab"
                        data-bs-toggle="tab" data-bs-target="#navs-top-discussions" aria-controls="navs-top-discussions">
                        <i class="menu-icon tf-icons bx bxs-chat text-danger"></i><?= get_label('discussions', 'Discussions') ?>
                    </button>
                </li>
                @php
                if (empty($activeTab)) {
                $activeTab = 'discussions';
                }
                @endphp
                @endif

                @if ($auth_user->can('manage_milestones'))
                <li class="nav-item">
                    <button type="button" class="nav-link {{ $activeTab == 'milestones' ? 'active' : '' }}" role="tab"
                        data-bs-toggle="tab" data-bs-target="#navs-top-milestones" aria-controls="navs-top-milestones">
                        <i class="menu-icon tf-icons bx bx-list-check text-warning"></i><?= get_label('milestones', 'Milestones') ?>
                    </button>
                </li>
                @php
                if (empty($activeTab)) {
                $activeTab = 'milestones';
                }
                @endphp
                @endif

                @if ($auth_user->can('manage_media'))
                <li class="nav-item">
                    <button type="button" class="nav-link {{ empty($activeTab) ? 'active' : '' }}" role="tab"
                        data-bs-toggle="tab" data-bs-target="#navs-top-media" aria-controls="navs-top-media">
                        <i class="menu-icon tf-icons bx bx-image-alt text-success"></i><?= get_label('media', 'Media') ?>
                    </button>
                </li>
                @php
                if (empty($activeTab)) {
                $activeTab = 'media';
                }
                @endphp
                @endif

                <li class="nav-item">
                    <button type="button" class="nav-link {{ empty($activeTab) ? 'active' : '' }}" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-status_timeline" aria-controls="navs-top-status_timeline">
                        <i class="menu-icon tf-icons bx bx-align-justify text-dark"></i><?= get_label('status_timeline', 'Status Timeline') ?>
                    </button>
                </li>
                @php
                if (empty($activeTab)) {
                $activeTab = 'status_timeline';
                }
                @endphp

                @if ($auth_user->can('manage_activity_log'))
                <li class="nav-item">
                    <button type="button" class="nav-link {{ $activeTab == 'activity_log' ? 'active' : '' }}" role="tab"
                        data-bs-toggle="tab" data-bs-target="#navs-top-activity-log" aria-controls="navs-top-activity-log">
                        <i class="menu-icon tf-icons bx bx-line-chart text-info"></i><?= get_label('activity_log', 'Activity log') ?>
                    </button>
                </li>
                @php
                if (empty($activeTab)) {
                $activeTab = 'activity_log';
                }
                @endphp
                @endif
            </ul>

            <div class="tab-content">
                @if ($auth_user->can('manage_tasks'))
                <div class="tab-pane fade {{ $activeTab == 'tasks' ? 'active show' : '' }}" id="navs-top-tasks" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div></div>
                        <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#create_task_modal">
                            <button type="button" class="btn btn-sm btn-primary action_create_tasks" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('create_task', 'Create Task') ?>">
                                <i class="bx bx-plus"></i>
                            </button>
                        </a>
                    </div>
                    <?php
                    $id = 'project_' . $project->id;
                    $tasks = $project->tasks->count();
                    $users = $project->users;
                    $clients = $project->clients;
                    ?>
                    <x-tasks-card :tasks="$tasks" :id="$id" :users="$users" :clients="$clients" :emptyState="0" />
                </div>
                @endif
                @if (Auth::guard('web')->check() || $project->client_can_discuss)
                <div class="tab-pane fade {{ $activeTab == 'discussions' ? 'active show' : '' }}" id="navs-top-discussions"
                    role="tabpanel">
                    <!-- Discussions content -->
                    <x-discussions-card :project="$project" />
                </div>
                @endif
                @if ($auth_user->can('manage_milestones'))
                @php
                $visibleColumns = getUserPreferences('milestones');
                @endphp
                <div class="tab-pane fade {{ $activeTab == 'milestones' ? 'active show' : '' }}" id="navs-top-milestones" role="tabpanel">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div></div>
                            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#create_milestone_modal">
                                <button type="button" class="btn btn-sm btn-primary action_create_milestones" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('create_milestone', 'Create milestone') ?>">
                                    <i class="bx bx-plus"></i>
                                </button>
                            </a>
                        </div>
                        <div class="row mt-4">
                            <div class="col-md-4 mb-3">
                                <div class="input-group input-group-merge">
                                    <input type="text" class="form-control" id="ms_date_between" placeholder="<?= get_label('date_between', 'Date Between') ?>" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="input-group input-group-merge">
                                    <input type="text" id="start_date_between" name="start_date_between" class="form-control" placeholder="<?= get_label('start_date_between', 'Start date between') ?>" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="input-group input-group-merge">
                                    <input type="text" id="end_date_between" name="end_date_between" class="form-control" placeholder="<?= get_label('end_date_between', 'End date between') ?>" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <select class="form-select js-example-basic-multiple" id="status_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_statuses', 'Select statuses') ?>" data-allow-clear="true" multiple>
                                    <option value="incomplete"><?= get_label('incomplete', 'Incomplete') ?></option>
                                    <option value="complete"><?= get_label('complete', 'Complete') ?></option>
                                </select>
                            </div>
                        </div>
                        <input type="hidden" id="ms_date_between_from">
                        <input type="hidden" id="ms_date_between_to">
                        <input type="hidden" name="start_date_from" id="start_date_from">
                        <input type="hidden" name="start_date_to" id="start_date_to">
                        <input type="hidden" name="end_date_from" id="end_date_from">
                        <input type="hidden" name="end_date_to" id="end_date_to">
                        <div class="table-responsive text-nowrap">
                            <input type="hidden" id="data_type" value="milestones">
                            <input type="hidden" id="data_table" value="project_milestones_table">
                            <input type="hidden" id="save_column_visibility">
                            <input type="hidden" id="multi_select">
                            <table id="project_milestones_table" data-toggle="table" data-loading-template="loadingTemplate" data-url="{{ url('/projects/get-milestones/' . $project->id) }}" data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true" data-query-params="queryParamsProjectMilestones">
                                <thead>
                                    <tr>
                                        <th data-checkbox="true"></th>
                                        <th data-field="id" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('id', 'ID') ?></th>
                                        <th data-field="title" data-visible="{{ (in_array('title', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('title', 'Title') ?></th>
                                        <th data-field="start_date" data-visible="{{ (in_array('start_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('start_date', 'Start date') ?></th>
                                        <th data-field="end_date" data-visible="{{ (in_array('end_date', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('end_date', 'End date') ?></th>
                                        <th data-field="cost" data-visible="{{ (in_array('cost', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('cost', 'Cost') ?></th>
                                        <th data-field="progress" data-visible="{{ (in_array('progress', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('progress', 'Progress') ?></th>
                                        <th data-field="status" data-visible="{{ (in_array('status', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('status', 'Status') ?></th>
                                        <th data-field="description" data-sortable="true" data-visible="{{ (in_array('description', $visibleColumns)) ? 'true' : 'false' }}">{{ get_label('description', 'Description') }}</th>
                                        <th data-field="created_by" data-sortable="true" data-visible="{{ (in_array('created_by', $visibleColumns)) ? 'true' : 'false' }}">{{ get_label('created_by', 'Created by') }}</th>
                                        <th data-field="created_at" data-sortable="true" data-visible="{{ (in_array('created_at', $visibleColumns)) ? 'true' : 'false' }}">{{ get_label('created_at', 'Created at') }}</th>
                                        <th data-field="updated_at" data-sortable="true" data-visible="{{ (in_array('updated_at', $visibleColumns)) ? 'true' : 'false' }}">{{ get_label('updated_at', 'Updated at') }}</th>
                                        <th data-field="actions" data-visible="{{ (in_array('actions', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}">{{ get_label('actions', 'Actions') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
                @if ($auth_user->can('manage_media'))
                <div class="tab-pane fade {{ $activeTab == 'media' ? 'active show' : '' }}" id="navs-top-media" role="tabpanel">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div></div>
                            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#add_media_modal">
                                <button type="button" class="btn btn-sm btn-primary action_create_media" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('add_media', 'Add Media') ?>">
                                    <i class="bx bx-plus"></i>
                                </button>
                            </a>
                        </div>
                        @php
                        $visibleColumns = getUserPreferences('project_media');
                        @endphp
                        <div class="table-responsive text-nowrap">
                            <input type="hidden" id="data_type" value="project-media">
                            <input type="hidden" id="data_table" value="project_media_table">
                            <input type="hidden" id="save_column_visibility">
                            <table id="project_media_table" data-toggle="table" data-loading-template="loadingTemplate" data-url="{{ url('/projects/get-media/' . $project->id) }}" data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true" data-query-params="queryParamsProjectMedia">
                                <thead>
                                    <tr>
                                        <th data-checkbox="true"></th>
                                        <th data-field="id" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('id', 'ID') ?></th>
                                        <th data-field="file" data-visible="{{ (in_array('file', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('file', 'File') ?></th>
                                        <th data-field="file_name" data-sortable="true" data-visible="{{ (in_array('file_name', $visibleColumns)) ? 'true' : 'false' }}">{{ get_label('file_name', 'File name') }}</th>
                                        <th data-field="file_size" data-visible="{{ (in_array('file_size', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('file_size', 'File size') ?></th>
                                        <th data-field="created_at" data-sortable="true" data-visible="{{ (in_array('created_at', $visibleColumns)) ? 'true' : 'false' }}">{{ get_label('created_at', 'Created at') }}</th>
                                        <th data-field="updated_at" data-sortable="true" data-visible="{{ (in_array('updated_at', $visibleColumns)) ? 'true' : 'false' }}">{{ get_label('updated_at', 'Updated at') }}</th>
                                        <th data-field="actions" data-visible="{{ (in_array('actions', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="false">{{ get_label('actions', 'Actions') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
                 <div class="tab-pane fade {{ $activeTab == 'status_timeline' ? 'active show' : '' }}" id="navs-top-status_timeline" role="tabpanel">
                  <!-- Status timeline content -->
                  <x-status-timeline :timelines="$project->statusTimelines->sortByDesc('changed_at')" />
              </div>
                @if ($auth_user->can('manage_activity_log'))
                <div class="tab-pane fade {{ $activeTab == 'activity_log' ? 'active show' : '' }}" id="navs-top-activity-log" role="tabpanel">
                    <div class="col-12">
                        <div class="row mt-4">
                            <div class="mb-3 col-md-4">
                                <div class="input-group input-group-merge">
                                    <input type="text" id="activity_log_between_date" class="form-control" placeholder="<?= get_label('date_between', 'Date between') ?>" autocomplete="off">
                                </div>
                            </div>
                            @if ($auth_user->can('manage_users'))
                            <div class="col-md-4 mb-3">
                                <select class="form-select users_select" id="user_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_actioned_by_users', 'Select Actioned By Users') ?>" multiple>
                                </select>
                            </div>
                            @endif
                            @if ($auth_user->can('manage_clients'))
                            <div class="col-md-4 mb-3">
                                <select class="form-select clients_select" id="client_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_actioned_by_clients', 'Select Actioned By Clients') ?>" multiple>
                                </select>
                            </div>
                            @endif
                            <div class="col-md-4 mb-3">
                                <select class="form-select js-example-basic-multiple" id="activity_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_activities', 'Select Activities') ?>" data-allow-clear="true" multiple>
                                    <option value="created"><?= get_label('created', 'Created') ?></option>
                                    <option value="updated"><?= get_label('updated', 'Updated') ?></option>
                                    <option value="duplicated"><?= get_label('duplicated', 'Duplicated') ?></option>
                                    <option value="uploaded"><?= get_label('uploaded', 'Uploaded') ?></option>
                                    <option value="deleted"><?= get_label('deleted', 'Deleted') ?></option>
                                    <option value="updated_status"><?= get_label('updated_status', 'Updated status') ?></option>
                                    <option value="updated_priority"><?= get_label('updated_priority', 'Updated priority') ?></option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <select class="form-select js-example-basic-multiple" id="type_filter" aria-label="Default select example" data-placeholder="<?= get_label('select_types', 'Select types') ?>" data-allow-clear="true" multiple>
                                    <option value="task">{{ get_label('project', 'Project') }}</option>
                                    <option value="task">{{ get_label('task', 'Task') }}</option>
                                    <option value="milestone">{{ get_label('milestone', 'Milestone') }}</option>
                                    <option value="media">{{ get_label('media', 'Media') }}</option>
                                </select>
                            </div>
                        </div>
                        @php
                        $visibleColumns = getUserPreferences('activity_log');
                        @endphp
                        <div class="table-responsive text-nowrap">
                            <input type="hidden" id="activity_log_between_date_from">
                            <input type="hidden" id="activity_log_between_date_to">
                            <input type="hidden" id="data_type" value="activity-log">
                            <input type="hidden" id="data_table" value="activity_log_table">
                            <input type="hidden" id="type_id" value="{{$project->id}}">
                            <input type="hidden" id="save_column_visibility">
                            <input type="hidden" id="multi_select">
                            <table id="activity_log_table" data-toggle="table" data-loading-template="loadingTemplate" data-url="{{ url('/activity-log/list') }}" data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true" data-query-params="queryParams">
                                <thead>
                                    <tr>
                                        <th data-checkbox="true"></th>
                                        <th data-field="id" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('id', 'ID') ?></th>
                                        <th data-field="actor_id" data-visible="{{ (in_array('actor_id', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('actioned_by_id', 'Actioned By ID') ?></th>
                                        <th data-field="actor_name" data-visible="{{ (in_array('actor_name', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('actioned_by', 'Actioned By') ?></th>
                                        <th data-field="actor_type" data-visible="{{ (in_array('actor_type', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('actioned_by_type', 'Actioned By Type') ?></th>
                                        <th data-field="type_id" data-visible="{{ (in_array('type_id', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('type_id', 'Type ID') ?></th>
                                        <th data-field="parent_type_id" data-visible="{{ (in_array('parent_type_id', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('parent_type_id', 'Parent type ID') ?></th>
                                        <th data-field="activity" data-visible="{{ (in_array('activity', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('activity', 'Activity') ?></th>
                                        <th data-field="type" data-visible="{{ (in_array('type', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('type', 'Type') ?></th>
                                        <th data-field="parent_type" data-visible="{{ (in_array('parent_type', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('parent_type', 'Parent type') ?></th>
                                        <th data-field="type_title" data-visible="{{ (in_array('type_title', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('type_title', 'Type title') ?></th>
                                        <th data-field="parent_type_title" data-visible="{{ (in_array('parent_type_title', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('parent_type_title', 'Parent type title') ?></th>
                                        <th data-field="message" data-visible="{{ (in_array('message', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('message', 'Message') ?></th>
                                        <th data-field="created_at" data-visible="{{ (in_array('created_at', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('created_at', 'Created at') ?></th>
                                        <th data-field="updated_at" data-visible="{{ (in_array('updated_at', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('updated_at', 'Updated at') ?></th>
                                        <th data-field="actions" data-visible="{{ (in_array('actions', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('actions', 'Actions') ?></th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
        <div class="modal fade" id="create_milestone_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form class="modal-content form-submit-event" action="{{url('projects/store-milestone')}}" method="POST">
                    <input type="hidden" name="project_id" value="{{$project->id}}">
                    <input type="hidden" name="dnr">
                    <input type="hidden" name="table" value="project_milestones_table">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel1"><?= get_label('create_milestone', 'Create milestone') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="nameBasic" class="form-label"><?= get_label('title', 'Title') ?> <span class="asterisk">*</span></label>
                                <input type="text" name="title" class="form-control" placeholder="<?= get_label('please_enter_title', 'Please enter title') ?>">
                            </div>
                            <div class="col-6 mb-3">
                                <label for="nameBasic" class="form-label"><?= get_label('starts_at', 'Starts at') ?></label>
                                <input type="text" id="start_date" name="start_date" class="form-control" placeholder="{{get_label('please_select','Please Select')}}" autocomplete="off">
                            </div>
                            <div class="col-6 mb-3">
                                <label for="nameBasic" class="form-label"><?= get_label('ends_at', 'Ends at') ?></label>
                                <input type="text" id="end_date" name="end_date" class="form-control" placeholder="{{get_label('please_select','Please Select')}}" autocomplete="off">
                            </div>
                            <div class="col-6 mb-3">
                                <label for="nameBasic" class="form-label"><?= get_label('status', 'Status') ?> <span class="asterisk">*</span></label>
                                <select class="form-select" name="status">
                                    <option value="incomplete"><?= get_label('incomplete', 'Incomplete') ?></option>
                                    <option value="complete"><?= get_label('complete', 'Complete') ?></option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label for="nameBasic" class="form-label"><?= get_label('cost', 'Cost') ?> <span class="asterisk">*</span></label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text">{{$general_settings['currency_symbol']}}</span>
                                    <input type="text" name="cost" class="form-control currency" placeholder="<?= get_label('please_enter_cost', 'Please enter cost') ?>">
                                </div>
                            </div>
                        </div>
                        <label for="description" class="form-label"><?= get_label('description', 'Description') ?></label>
                        <textarea class="form-control description" name="description" placeholder="<?= get_label('please_enter_description', 'Please enter description') ?>"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <?= get_label('close', 'Close') ?>
                        </button>
                        <button type="submit" id="submit_btn" class="btn btn-primary"><?= get_label('create', 'Create') ?></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal fade" id="edit_milestone_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form class="modal-content form-submit-event" action="{{url('projects/update-milestone')}}" method="POST">
                    <input type="hidden" name="id" id="milestone_id">
                    <input type="hidden" name="project_id" value="{{$project->id}}">
                    <input type="hidden" name="dnr">
                    <input type="hidden" name="table" value="project_milestones_table">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel1"><?= get_label('update_milestone', 'Update milestone') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="nameBasic" class="form-label"><?= get_label('title', 'Title') ?> <span class="asterisk">*</span></label>
                                <input type="text" name="title" id="milestone_title" class="form-control" placeholder="<?= get_label('please_enter_title', 'Please enter title') ?>">
                            </div>
                            <div class="col-6 mb-3">
                                <label for="nameBasic" class="form-label"><?= get_label('starts_at', 'Starts at') ?></label>
                                <input type="text" id="update_milestone_start_date" name="start_date" class="form-control" placeholder="{{get_label('please_select','Please Select')}}" autocomplete="off">
                            </div>
                            <div class="col-6 mb-3">
                                <label for="nameBasic" class="form-label"><?= get_label('ends_at', 'Ends at') ?></label>
                                <input type="text" id="update_milestone_end_date" name="end_date" class="form-control" placeholder="{{get_label('please_select','Please Select')}}" autocomplete="off">
                            </div>
                            <div class="col-6 mb-3">
                                <label for="nameBasic" class="form-label"><?= get_label('status', 'Status') ?> <span class="asterisk">*</span></label>
                                <select class="form-select" id="milestone_status" name="status">
                                    <option value="incomplete"><?= get_label('incomplete', 'Incomplete') ?></option>
                                    <option value="complete"><?= get_label('complete', 'Complete') ?></option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label for="nameBasic" class="form-label"><?= get_label('cost', 'Cost') ?> <span class="asterisk">*</span></label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text">{{$general_settings['currency_symbol']}}</span>
                                    <input type="text" name="cost" id="milestone_cost" class="form-control currency" placeholder="<?= get_label('please_enter_cost', 'Please enter cost') ?>">
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="nameBasic" class="form-label"><?= get_label('progress', 'Progress') ?></label>
                                <input type="range" name="progress" id="milestone_progress" class="form-range">
                                <h6 class="mt-2 milestone-progress"></h6>
                            </div>
                        </div>
                        <label for="description" class="form-label"><?= get_label('description', 'Description') ?></label>
                        <textarea class="form-control description" name="description" id="milestone_description" placeholder="<?= get_label('please_enter_description', 'Please enter description') ?>"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <?= get_label('close', 'Close') ?>
                        </button>
                        <button type="submit" id="submit_btn" class="btn btn-primary"><?= get_label('update', 'Update') ?></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal fade" id="add_media_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form class="modal-content form-horizontal" id="media-upload" action="{{url('projects/upload-media')}}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel1"><?= get_label('add_media', 'Add Media') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-primary alert-dismissible" role="alert"><?= $media_storage_settings['media_storage_type'] == 's3' ? get_label('storage_type_set_as_aws_s3', 'Storage type is set as AWS S3 storage') : get_label('storage_type_set_as_local', 'Storage type is set as local storage') ?>, <a href="{{ url('settings/media-storage') }}"><?= get_label('click_here_to_change', 'Click here to change.') ?></a></div>
                        <div class="alert alert-info">
                            <?= get_label('allowed_file_types', 'Allowed file types') ?>: <strong><?= str_replace(',', ', ', $general_settings['allowed_file_types']) ?></strong>
                        </div>
                        <div class="dropzone dz-clickable" id="media-upload-dropzone">
                        </div>
                        <div class="form-group mt-4 text-center">
                            <button class="btn btn-primary" id="upload_media_btn"><?= get_label('upload', 'Upload') ?></button>
                        </div>
                        <div class="d-flex justify-content-center">
                            <div class="form-group" id="error_box">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <?= get_label('close', 'Close') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$titles = [];
$task_counts = [];
$bg_colors = [];
$total_tasks = 0;
$ran = array(
    '#63ed7a',
    '#ffa426',
    '#fc544b',
    '#6777ef',
    '#FF00FF',
    '#53ff1a',
    '#ff3300',
    '#0000ff',
    '#00ffff',
    '#99ff33',
    '#003366',
    '#cc3300',
    '#ffcc00',
    '#ff9900',
    '#3333cc',
    '#ffff00',
    '#FF5733',
    '#33FF57',
    '#5733FF',
    '#FFFF33',
    '#A6A6A6',
    '#FF99FF',
    '#6699FF',
    '#666666',
    '#FF6600',
    '#9900CC',
    '#FF99CC',
    '#FFCC99',
    '#99CCFF',
    '#33CCCC',
    '#CCFFCC',
    '#99CC99',
    '#669999',
    '#CCCCFF',
    '#6666FF',
    '#FF6666',
    '#99CCCC',
    '#993366',
    '#339966',
    '#99CC00',
    '#CC6666',
    '#660033',
    '#CC99CC',
    '#CC3300',
    '#FFCCCC',
    '#6600CC',
    '#FFCC33',
    '#9933FF',
    '#33FF33',
    '#FFFF66',
    '#9933CC',
    '#3300FF',
    '#9999CC',
    '#0066FF',
    '#339900',
    '#666633',
    '#330033',
    '#FF9999',
    '#66FF33',
    '#6600FF',
    '#FF0033',
    '#009999',
    '#CC0000',
    '#999999',
    '#CC0000',
    '#CCCC00',
    '#00FF33',
    '#0066CC',
    '#66FF66',
    '#FF33FF',
    '#CC33CC',
    '#660099',
    '#663366',
    '#996666',
    '#6699CC',
    '#663399',
    '#9966CC',
    '#66CC66',
    '#0099CC',
    '#339999',
    '#00CCCC',
    '#CCCC99',
    '#FF9966',
    '#99FF00',
    '#66FF99',
    '#336666',
    '#00FF66',
    '#3366CC',
    '#CC00CC',
    '#00FF99',
    '#FF0000',
    '#00CCFF',
    '#000000',
    '#FFFFFF'
);
$task_counts = [];
$titles = [];
$bg_colors = [];
$total_tasks = 0;
foreach ($statuses as $status) {
    $statusCount = 0;
    if (isAdminOrHasAllDataAccess()) {
        $statusCount = $project->tasks->where('status_id', $status->id)->count();
    } else {
        if (isClient()) {
            $statusCount = $project->tasks()
                ->whereIn('project_id', getAuthenticatedUser()->projects->pluck('id'))
                ->where('status_id', $status->id)
                ->count();
        } else {
            $statusCount = $project->tasks()
                ->whereIn('id', getAuthenticatedUser()->tasks->pluck('id'))
                ->where('status_id', $status->id)
                ->count();
        }
    }
    $task_counts[] = $statusCount;
    $titles[] = "'" . $status->title . "'";
    $v = array_shift($ran);
    array_push($bg_colors, "'" . $v . "'");
    $total_tasks += $statusCount;
}
$titles = implode(",", $titles);
$task_counts = implode(",", $task_counts);
$bg_colors = implode(",", $bg_colors);
?>
<script>
    var labels = [<?= $titles ?>];
    var task_data = [<?= $task_counts ?>];
    var bg_colors = [<?= $bg_colors ?>];
    var total_tasks = [<?= $total_tasks ?>];
    //labels
    var total = '<?= get_label('total', 'Total') ?>';
    var label_delete = '<?= get_label('delete', 'Delete') ?>';
    var label_download = '<?= get_label('download', 'Download') ?>';
</script>
<script src="{{asset('assets/js/apexcharts.js')}}"></script>
<script src="{{asset('assets/js/pages/project-information.js')}}"></script>
@endsection
