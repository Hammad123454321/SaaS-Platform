@extends('layout')
@section('title')
    <?= get_label('projects', 'Projects') ?> - <?= get_label('list_view', 'List view') ?>
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-2 mt-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1">
                        <li class="breadcrumb-item">
                            <a href="{{ url('home') }}"><?= get_label('home', 'Home') ?></a>
                        </li>
                        <li class="breadcrumb-item">
                            <a
                                href="{{ url(getUserPreferences('projects', 'default_view')) }}"><?= get_label('projects', 'Projects') ?></a>
                        </li>
                        @if ($is_favorites == 1)
                            <li class="breadcrumb-item"><?= get_label('favorite', 'Favorite') ?></li>
                        @endif
                        <li class="breadcrumb-item active"><?= get_label('list', 'List') ?></li>
                    </ol>
                </nav>
            </div>
            <div>
                @php
                    $projectDefaultView = getUserPreferences('projects', 'default_view');
                @endphp
                @if ($projectDefaultView === 'projects/list')
                    <span class="badge bg-primary"><?= get_label('default_view', 'Default View') ?></span>
                @else
                    <a href="javascript:void(0);"><span class="badge bg-secondary" id="set-default-view"
                            data-type="projects"
                            data-view="list"><?= get_label('set_as_default_view', 'Set as Default View') ?></span></a>
                @endif
            </div>
            <div>
                @php
                    // Base URLs for different views
                    $gridUrl = $is_favorites == 1 ? url('projects/favorite') : url('projects');
                    $kanbanUrl =
                        $is_favorites == 1
                            ? route('projects.kanban_view', ['type' => 'favorite'])
                            : route('projects.kanban_view');
                    $ganttChartUrl =
                        $is_favorites == 1
                            ? route('projects.gantt_chart', ['type' => 'favorite'])
                            : route('projects.gantt_chart');

                    // Get the statuses and tags from the request, if they exist
                    $selectedStatuses = request()->has('statuses')
                        ? 'statuses[]=' . implode('&statuses[]=', request()->input('statuses'))
                        : '';
                    $selectedTags = request()->has('tags')
                        ? 'tags[]=' . implode('&tags[]=', request()->input('tags'))
                        : '';

                    // Build the query string by concatenating statuses and tags if they exist
                    $queryParams = '';
                    if ($selectedStatuses || $selectedTags) {
                        $queryParams = '?' . trim($selectedStatuses . '&' . $selectedTags, '&');
                    }

                    // Final URLs with filters
                    $finalGridUrl = url($gridUrl . $queryParams);
                    $finalKanbanUrl = $kanbanUrl . $queryParams;
                @endphp

                <a href="javascript:void(0);" data-bs-toggle="offcanvas" data-bs-target="#create_project_offcanvas">
                    <button type="button" class="btn btn-sm btn-primary action_create_projects" data-bs-toggle="tooltip"
                        data-bs-placement="left"
                        data-bs-original-title="<?= get_label('create_project', 'Create project') ?>">
                        <i class='bx bx-plus'></i>
                    </button>
                </a>
                <a href="{{ $finalGridUrl }}">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="<?= get_label('grid_view', 'Grid view') ?>">
                        <i class='bx bxs-grid-alt'></i>
                    </button>
                </a>
                <a href="{{ $finalKanbanUrl }}">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="<?= get_label('kanban_view', 'Kanban View') ?>">
                        <i class='bx bx-layout'></i>
                    </button>
                </a>

                <a href="{{ $ganttChartUrl }}"><button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="<?= get_label('gantt_chart_view', 'Gantt Chart View') ?>"><i
                            class='bx bx-bar-chart'></i></button></a>

                <a href="{{ route('projects.calendar_view') }}"><button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="<?= get_label('calendar_view', 'Calendar view') ?>"><i
                            class='bx bx-calendar'></i></button></a>
            </div>
        </div>
        <x-projects-card :projects="$projects" :favorites="$is_favorites" :customFields="$projectCustomFields" />
    </div>
@endsection
