@extends('layout')
@section('title')
@if($is_favorites == 1) 
    {{ get_label('favorite', 'Favorite') }} 
@endif 
<?= get_label('tasks', 'Tasks') ?> - <?= get_label('calendar_view', 'Calendar View') ?>
@endsection
@section('content')
@php
$routePrefix = Route::getCurrentRoute()->getPrefix();
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between mb-2 mt-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('home.index') }}"><?= get_label('home', 'Home') ?></a>
                    </li>
                    @isset($project->id)
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
                        <?= get_label('calendar', 'Calendar') ?>
                    </li>
                </ol>
            </nav>
        </div>
        <div>
            @php
            $taskDefaultView = getUserPreferences('tasks', 'default_view');
            @endphp
            @if ($taskDefaultView === 'tasks/calendar')
            <span class="badge bg-primary"><?= get_label('default_view', 'Default View') ?></span>
            @else
            <a href="javascript:void(0);"><span class="badge bg-secondary" id="set-default-view" data-type="tasks"
                    data-view="calendar"><?= get_label('set_as_default_view', 'Set as Default View') ?></span></a>
            @endif
        </div>
        <div>
            @php
            // Determine base URL based on project ID and favorites status
            if ($is_favorites) {
            $url = isset($project->id)
            ? route('projects.tasks.index', ['id' => $project->id, 'favorite' => true])
            : route('tasks.index', ['favorite' => true]);
            } else {
            $url = isset($project->id)
            ? route('projects.tasks.index', ['id' => $project->id])
            : route('tasks.index');
            }

            // Append status if present in the request
            if (request()->has('status')) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'status=' . request('status');
            }
            @endphp

            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#create_task_modal"><button
                    type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="right"
                    data-bs-original-title=" <?= get_label('create_task', 'Create task') ?>"><i
                        class="bx bx-plus"></i></button></a>
            <a href="{{ $url }}"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                    data-bs-placement="left" data-bs-original-title="<?= get_label('list_view', 'List view') ?>"><i
                        class="bx bx-list-ul"></i></button></a>
            <a href="{{route('tasks.groupByTaskList') }}"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                data-bs-placement="left"
                data-bs-original-title="<?= get_label('group_by_task_list', 'Group By Task List') ?>"><i
                    class="bx bx-align-middle"></i></button></a>
            @php
            $projectId = isset($project->id)
            ? $project->id
            : (request()->has('project') ? request('project') : '');

            // Determine the base URL
            $url = '';
            if ($is_favorites) {
            $url = isset($project->id) || request()->has('project')
            ? route('projects.tasks.draggable', ['id' => 'favorite'])
            : route('tasks.draggable', ['favorite' => true]);
            } else {
            $url = isset($project->id) || request()->has('project')
            ? route('projects.tasks.draggable', ['id' => $projectId])
            : route('tasks.draggable');
            }

            // Append status if present
            if (request()->has('status')) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'status=' . request('status');
            }
            @endphp

            <a href="{{ $url }}"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('draggable_view', 'Draggable View') ?>"><i class="bx bxs-dashboard"></i></button></a>
        </div>
    </div>
    <input type="hidden" id="is_favorites" value="{{$favorites??''}}">
    <div class="card mb-4">
        <div class="card-body">
            <div id="taskCalenderDiv"></div>
        </div>
    </div>
    <input type="hidden" id="projectId" value="{{ $projectId }}">
</div>
<div class="modal fade" id="confirmDragTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="exampleModalLabel2"><?= get_label('confirm', 'Confirm!') ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><?= get_label('confirm_update_task_dates', 'Are You Want to Update the Task Dates?') ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="cancel" data-bs-dismiss="modal">
                    <?= get_label('close', 'Close') ?>
                </button>
                <button type="submit" class="btn btn-primary" id="confirm"><?= get_label('yes', 'Yes') ?></button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="confirmResizeTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="exampleModalLabel2"><?= get_label('confirm', 'Confirm!') ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><?= get_label('confirm_update_task_end_date', 'Are You Want to Update the Task End Date?') ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="cancel" data-bs-dismiss="modal">
                    <?= get_label('close', 'Close') ?>
                </button>
                <button type="submit" class="btn btn-primary" id="confirm"><?= get_label('yes', 'Yes') ?></button>
            </div>
        </div>
    </div>
</div>
@endsection