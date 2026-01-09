@extends('layout')
@section('title')
<?= $is_favorite == 1 ? get_label('favorite_projects', 'Favorite projects') : get_label('projects', 'Projects') ?> - <?= get_label('gantt_chart_view', 'Gantt Chart View') ?>
@endsection
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between mb-2 mt-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{url('home')}}">{{ get_label('home', 'Home') }}</a>
                    </li>
                    <li class="breadcrumb-item"><a href="{{url(getUserPreferences('projects', 'default_view'))}}"><?= get_label('projects', 'Projects') ?></a></li>
                    </li>
                    @if ($is_favorite==1)
                    <li class="breadcrumb-item"><?= get_label('favorite', 'Favorite') ?></li>
                    @endif
                    <li class="breadcrumb-item active">{{ get_label('gantt_chart_view', 'Gantt Chart View') }}</li>
                </ol>
            </nav>
        </div>
        <div>
            @php
            $projectDefaultView = getUserPreferences('projects', 'default_view');
            @endphp
            @if ($projectDefaultView === 'projects/gantt-chart')
            <span class="badge bg-primary"><?= get_label('default_view', 'Default View') ?></span>
            @else
            <a href="javascript:void(0);"><span class="badge bg-secondary" id="set-default-view" data-type="projects" data-view="gantt-chart"><?= get_label('set_as_default_view', 'Set as Default View') ?></span></a>
            @endif
        </div>
        <div>
            @php
            // Base URLs for different views
            $listUrl = $is_favorite == 1 ? url('projects/list/favorite') : url('projects/list');
            $gridUrl = $is_favorite == 1 ? url('projects/favorite') : url('projects');
            $kanbanUrl = $is_favorite == 1 ? route('projects.kanban_view', ['type' => 'favorite']) : route('projects.kanban_view');
            @endphp
            <a href="javascript:void(0);" data-bs-toggle="offcanvas" data-bs-target="#create_project_offcanvas">
                        <button type="button" class="btn btn-sm btn-primary action_create_projects" data-bs-toggle="tooltip"
                            data-bs-placement="left"
                            data-bs-original-title="<?= get_label('create_project', 'Create project') ?>">
                            <i class='bx bx-plus'></i>
                        </button>
                    </a>
            <a href="{{ $listUrl }}">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('list_view', 'List view') ?>">
                    <i class='bx bx-list-ul'></i>
                </button>
            </a>
            <a href="{{ $gridUrl }}">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('grid_view', 'Grid view') ?>">
                    <i class='bx bxs-grid-alt'></i>
                </button>
            </a>
            <a href="{{ $kanbanUrl }}">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('kanban_view', 'Kanban View') ?>">
                    <i class='bx bx-layout'></i>
                </button>
            </a>
            <a href="{{ route('projects.calendar_view') }}"><button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="<?= get_label('calendar_view', 'Calendar view') ?>"><i
                            class='bx bx-calendar'></i></button></a>
        </div>
    </div>
    @php
    // Get selected statuses and tags from the request
    $selectedStatuses = request()->input('statuses', []);
    $selectedTags = request()->input('tags', []);

    $filterStatuses = \App\Models\Status::whereIn('id', $selectedStatuses)->get();
    $filterTags = \App\Models\Tag::whereIn('id', $selectedTags)->get();
    @endphp
    <div class="row d-none">
        <div class="col-md-4 mb-3">
            <select class="form-select statuses_filter" id="selected_statuses" name="statuses[]" aria-label="Default select example" data-placeholder="<?= get_label('filter_by_statuses', 'Filter by statuses') ?>" data-allow-clear="true" multiple>
                @foreach($filterStatuses as $status)
                <option value="{{ $status->id }}" selected>{{ $status->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <select id="selected_tags" class="form-control tags_select" name="tag[]" multiple="multiple" data-placeholder="<?= get_label('filter_by_tags', 'Filter by tags') ?>" data-allow-clear="true" multiple>
                @foreach($filterTags as $tag)
                <option value="{{ $tag->id }}" selected>{{ $tag->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1">
            <div>
                <button type="button" id="filter" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('filter', 'Filter') ?>"><i class='bx bx-filter-alt'></i></button>
            </div>
        </div>
    </div>
    <input type="hidden" id="favorite" value="{{$is_favorite}}">
    <div class="alert alert-primary" role="alert">
        <i class="bx bx-info-circle"></i>
        {{ get_label('project_gantt_info', 'Double-click a project or task to view the detail page.') }}
    </div>
    <input type="hidden" id="is_favorites" value="{{$is_favorite??''}}">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <!-- Right Section: Views and Current Date -->
                <div class="d-flex align-items-center">
                    <div class="btn-group me-3">
                        <button id="day-view"
                            class="btn btn-light view-btns border btn-primary">{{ get_label('days', 'Days') }}</button>
                        <button id="week-view"
                            class="btn btn-light view-btns border">{{ get_label('weeks', 'Weeks') }}</button>
                        <button id="month-view"
                            class="btn btn-light view-btns border">{{ get_label('months', 'Months') }}</button>
                    </div>
                </div>
            </div>
            <!-- Gantt chart container -->
            <div id="gantt" class="rounded-3 border"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmUpdateDates" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="exampleModalLabel2"><?= get_label('confirm', 'Confirm!') ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><?= get_label('confirm_update_dates', 'Do you want to update the date(s)?') ?></p>
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
<script src="{{ asset('assets/js/pages/project-gantt-chart.js') }}"></script>
@endsection
