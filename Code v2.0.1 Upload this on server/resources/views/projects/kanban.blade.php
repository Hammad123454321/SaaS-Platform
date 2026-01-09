@extends('layout')
@section('title')
    <?= $is_favorite == 1 ? get_label('favorite_projects', 'Favorite projects') : get_label('projects', 'Projects') ?> -
    <?= get_label('kanban_view', 'Kanban View') ?>
@endsection
@php
    $user = getAuthenticatedUser();
@endphp
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
                        @if ($is_favorite == 1)
                            <li class="breadcrumb-item"><?= get_label('favorite', 'Favorite') ?></li>
                        @endif
                        <li class="breadcrumb-item active"><?= get_label('kanban', 'Kanban') ?></li>
                    </ol>
                </nav>
            </div>
            <div>
                @php
                    $projectDefaultView = getUserPreferences('projects', 'default_view');
                @endphp
                @if ($projectDefaultView && $projectDefaultView === 'projects/kanban')
                    <span class="badge bg-primary"><?= get_label('default_view', 'Default View') ?></span>
                @else
                    <a href="javascript:void(0);"><span class="badge bg-secondary" id="set-default-view"
                            data-type="projects"
                            data-view="kanban"><?= get_label('set_as_default_view', 'Set as Default View') ?></span></a>
                @endif
            </div>
            <div>
                @php
                    // Base URLs for different views
                    $listUrl = $is_favorite == 1 ? url('projects/list/favorite') : url('projects/list');
                    $gridUrl = $is_favorite == 1 ? url('projects/favorite') : url('projects');
                    $ganttChartUrl =
                        $is_favorite == 1
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
                    $finalListUrl = url($listUrl . $queryParams);
                    $finalGridUrl = $gridUrl . $queryParams;
                @endphp

                <a href="javascript:void(0);" data-bs-toggle="offcanvas" data-bs-target="#create_project_offcanvas">
                    <button type="button" class="btn btn-sm btn-primary action_create_projects" data-bs-toggle="tooltip"
                        data-bs-placement="left"
                        data-bs-original-title="<?= get_label('create_project', 'Create project') ?>">
                        <i class='bx bx-plus'></i>
                    </button>
                </a>
                <a href="{{ $finalListUrl }}">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="<?= get_label('list_view', 'List view') ?>">
                        <i class='bx bx-list-ul'></i>
                    </button>
                </a>

                <a href="{{ $finalGridUrl }}">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="<?= get_label('grid_view', 'Grid view') ?>">
                        <i class='bx bxs-grid-alt'></i>
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
        <div class="row">
            <div class="col-md-3 mb-3">
                <select class="form-select js-example-basic-multiple" id="sort" aria-label="Default select example"
                    data-placeholder="<?= get_label('select_sort_by', 'Select Sort By') ?>" data-allow-clear="true">
                    <option></option>
                    <option value="newest" <?= request()->sort && request()->sort == 'newest' ? 'selected' : '' ?>>
                        <?= get_label('newest', 'Newest') ?></option>
                    <option value="oldest" <?= request()->sort && request()->sort == 'oldest' ? 'selected' : '' ?>>
                        <?= get_label('oldest', 'Oldest') ?></option>
                    <option value="recently-updated"
                        <?= request()->sort && request()->sort == 'recently-updated' ? 'selected' : '' ?>>
                        <?= get_label('most_recently_updated', 'Most recently updated') ?></option>
                    <option value="earliest-updated"
                        <?= request()->sort && request()->sort == 'earliest-updated' ? 'selected' : '' ?>>
                        <?= get_label('least_recently_updated', 'Least recently updated') ?></option>
                </select>
            </div>
            @php
                // Get selected statuses and tags from the request
                $selectedStatuses = request()->input('statuses', []);
                $selectedTags = request()->input('tags', []);

                $filterStatuses = \App\Models\Status::whereIn('id', $selectedStatuses)->get();
                $filterTags = \App\Models\Tag::whereIn('id', $selectedTags)->get();
            @endphp
            <div class="col-md-4 mb-3">
                <select class="form-select statuses_filter" id="selected_statuses" name="statuses[]"
                    aria-label="Default select example"
                    data-placeholder="<?= get_label('filter_by_statuses', 'Filter by statuses') ?>" data-allow-clear="true"
                    multiple>
                    @foreach ($filterStatuses as $status)
                        <option value="{{ $status->id }}" selected>{{ $status->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <select id="selected_tags" class="form-control tags_select" name="tag[]" multiple="multiple"
                    data-placeholder="<?= get_label('filter_by_tags', 'Filter by tags') ?>" data-allow-clear="true"
                    multiple>
                    @foreach ($filterTags as $tag)
                        <option value="{{ $tag->id }}" selected>{{ $tag->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <div>
                    <button type="button" id="filter" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                        data-bs-placement="left" data-bs-original-title="<?= get_label('filter', 'Filter') ?>"><i
                            class='bx bx-filter-alt'></i></button>
                </div>
            </div>
        </div>
        @if (is_countable($projects) && count($projects) > 0)
            @php
                $showSettings =
                    $user->can('edit_projects') || $user->can('delete_projects') || $user->can('create_projects');
                $canEditProjects = $user->can('edit_projects');
                $canDeleteProjects = $user->can('delete_projects');
                $canDuplicateProjects = $user->can('create_projects');
                $webGuard = Auth::guard('web')->check();
            @endphp
            <x-projects-kanban-card :projects="$projects" :statuses="$statuses" :showSettings="$showSettings" :canEditProjects="$canEditProjects"
                :canDeleteProjects="$canDeleteProjects" :canDuplicateProjects="$canDuplicateProjects" :webGuard="$webGuard" :customFields="$projectCustomFields" />
        @else
            <?php $type = 'projects'; ?>
            <x-empty-state-card :type="$type" />
        @endif
    </div>
    <script>
        var add_favorite = '<?= get_label('add_favorite', 'Click to mark as favorite') ?>';
        var remove_favorite = '<?= get_label('remove_favorite', 'Click to remove from favorite') ?>';
    </script>
    <script src="{{ asset('assets/js/pages/project-kanban.js') }}"></script>
@endsection
