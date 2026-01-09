@extends('layout')
@section('title')
@section('title')
@if($is_favorites == 1) 
    {{ get_label('favorite', 'Favorite') }} 
@endif 
<?= get_label('tasks', 'Tasks') ?> - <?= get_label('draggable', 'Draggable') ?>
@endsection

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
                    @if (isset($project->id))
                    <li class="breadcrumb-item">
                        <a href="{{url(getUserPreferences('projects', 'default_view'))}}"><?= get_label('projects', 'Projects') ?></a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{url('projects/information/'.$project->id)}}">{{$project->title}}</a>
                    </li>
                    @endisset
                    <li class="breadcrumb-item"><?= get_label('tasks', 'Tasks') ?></li>
                    @if ($is_favorites==1)
                    <li class="breadcrumb-item"><?= get_label('favorite', 'Favorite') ?></li>
                    @endif
                    <li class="breadcrumb-item active">
                        <?= get_label('draggable', 'Draggable') ?>
                    </li>
                </ol>
            </nav>
        </div>
        <div>
            @php
            $taskDefaultView = getUserPreferences('tasks', 'default_view');
            @endphp
            @if ($taskDefaultView === 'tasks/draggable')
            <span class="badge bg-primary"><?= get_label('default_view', 'Default View') ?></span>
            @else
            <a href="javascript:void(0);"><span class="badge bg-secondary" id="set-default-view" data-type="tasks" data-view="draggable"><?= get_label('set_as_default_view', 'Set as Default View') ?></span></a>
            @endif
        </div>
        <div>
            @php
            // Determine the base URL
            $url = isset($project->id)
            ? url('/projects/tasks/list/' . $project->id)
            : url('/tasks');

            // Append query parameters
            $queryParams = [];
            if (request()->has('status')) {
            $queryParams['status'] = request('status');
            }
            if ($is_favorites) {
            $queryParams['favorite'] = 1;
            }

            // Build the final URL with query parameters
            if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
            }
            @endphp

            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#create_task_modal">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="<?= get_label('create_task', 'Create task') ?>">
                    <i class="bx bx-plus"></i>
                </button>
            </a>
            <a href="{{ $url }}">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('list_view', 'List view') ?>">
                    <i class="bx bx-list-ul"></i>
                </button>
            </a>
            @php
            $projectId = isset($project->id)
            ? $project->id
            : (request()->has('project') ? request('project') : '');

            // Determine the base URL based on $is_favorites
            $url = isset($project->id) || request()->has('project')
            ? url('/projects/tasks/calendar/' . $projectId)
            : url('/tasks/calendar');

            // Collect query parameters
            $queryParams = [];
            if (request()->has('status')) {
            $queryParams['status'] = request('status');
            }
            if ($is_favorites) {
            $queryParams['favorite'] = 1;
            }

            // Append the query parameters to the URL
            if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
            }
            @endphp
            <a href="{{ $url }}"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('calendar_view', 'Calendar View') ?>"><i class="bx bx-calendar"></i></button></a>
        </div>

    </div>
    @if ($total_tasks > 0)
    <div class="alert alert-primary alert-dismissible" role="alert">
        <?= get_label('drag_drop_update_task_status', 'Drag and drop to update task status') . ' !' ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <div class="kanban-container d-flex card flex-row">
        @foreach ($statuses as $status)
        <div class="my-4 kanban-column">
            <h4 class="fw-bold mx-4 my-2">{{$status->title}}</h4>
            <div class="row m-2 d-flex flex-column kanban-tasks" id="{{$status->slug}}" data-status="{{$status->id}}">
                @foreach ($tasks as $task)
                @if($task->status_id==$status->id)
                <x-kanban :task="$task" />
                @endif
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @else
    <?php
    $type = 'Tasks';
    ?>
    <x-empty-state-card :type="$type" />
    @endif
</div>
<script>
    var statusArray = <?php echo json_encode($statuses); ?>;
</script>
<script src="{{asset('assets/js/pages/task-board.js')}}"></script>
@endsection