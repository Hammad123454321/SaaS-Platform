@extends('layout')
@section('title')
    <?= $is_favorite == 1 ? get_label('favorite_projects', 'Favorite projects') : get_label('projects', 'Projects') ?> -
    <?= get_label('grid_view', 'Grid view') ?>
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
                        <li class="breadcrumb-item"><a
                                href="{{ url(getUserPreferences('projects', 'default_view')) }}"><?= get_label('projects', 'Projects') ?></a>
                        </li>
                        @if ($is_favorite == 1)
                            <li class="breadcrumb-item"><?= get_label('favorite', 'Favorite') ?></li>
                        @endif
                        <li class="breadcrumb-item active"><?= get_label('grid', 'Grid') ?></li>
                    </ol>
                </nav>
            </div>
            <div>
                @php
                    $projectDefaultView = getUserPreferences('projects', 'default_view');
                @endphp
                @if (!$projectDefaultView || $projectDefaultView === 'projects')
                    <span class="badge bg-primary"><?= get_label('default_view', 'Default View') ?></span>
                @else
                    <a href="javascript:void(0);"><span class="badge bg-secondary" id="set-default-view"
                            data-type="projects"
                            data-view="grid"><?= get_label('set_as_default_view', 'Set as Default View') ?></span></a>
                @endif
            </div>
            <div>
                @php
                    // Base URLs for different views
                    $listUrl = $is_favorite == 1 ? url('projects/list/favorite') : url('projects/list');
                    $kanbanUrl =
                        $is_favorite == 1
                            ? route('projects.kanban_view', ['type' => 'favorite'])
                            : route('projects.kanban_view');
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
                    $finalKanbanUrl = $kanbanUrl . $queryParams;
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
            <div class="d-flex row mt-4">
                @foreach ($projects as $project)
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body card-body-project-grid">
                                @if ($project->tags->isNotEmpty())
                                    <div class="mb-3">
                                        @foreach ($project->tags as $tag)
                                            <span class="badge bg-{{ $tag->color }} mt-1">{{ $tag->title }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="d-flex justify-content-between">
                                    <h4 class="card-title"><a
                                            href="{{ url('projects/information/' . $project->id) }}"><strong>{{ $project->title }}</strong></a>
                                    </h4>
                                    <div class="d-flex align-items-center justify-content-center">
                                        <a href="javascript:void(0);" class="quick-view" data-id="{{ $project->id }}"
                                            data-type="project">
                                            <i class='bx bx bx-info-circle text-info' data-bs-toggle="tooltip"
                                                data-bs-placement="right"
                                                data-bs-original-title="{{ get_label('quick_view', 'Quick View') }}"></i>
                                        </a>
                                        <a href="javascript:void(0);" class="mx-2">
                                            <i class='bx {{ getFavoriteStatus($project->id) ? 'bxs' : 'bx' }}-star favorite-icon text-warning'
                                                data-id="{{ $project->id }}" data-bs-toggle="tooltip"
                                                data-bs-placement="right"
                                                data-bs-original-title="{{ getFavoriteStatus($project->id) ? get_label('remove_favorite', 'Click to remove from favorite') : get_label('add_favorite', 'Click to mark as favorite') }}"
                                                data-favorite="{{ getFavoriteStatus($project->id) }}"></i>
                                        </a>
                                        <a href="javascript:void(0);">
                                            <i class='bx {{ getPinnedStatus($project->id) ? 'bxs' : 'bx' }}-pin pinned-icon text-success'
                                                data-id="{{ $project->id }}" data-bs-toggle="tooltip"
                                                data-bs-placement="right"
                                                data-bs-original-title="{{ getPinnedStatus($project->id) ? get_label('click_unpin', 'Click to Unpin') : get_label('click_pin', 'Click to Pin') }}"
                                                data-pinned="{{ getPinnedStatus($project->id) }}"></i>
                                        </a>
                                        @if ($webGuard || $project->client_can_discuss)
                                            <a href="{{ route('projects.info', ['id' => $project->id]) }}#navs-top-discussions"
                                                class="ms-2">
                                                <i class='bx bx-message-rounded-dots text-danger' data-bs-toggle="tooltip"
                                                    data-bs-placement="right"
                                                    data-bs-original-title="{{ get_label('discussions', 'Discussions') }}"></i>
                                            </a>
                                        @endif
                                        <a href="{{ url('projects/mind-map/' . $project->id) }}"
                                            class="@if ($showSettings) mx-2 @else ms-2 @endif">
                                            <i class="bx bx-sitemap text-primary" data-bs-toggle="tooltip"
                                                data-bs-placement="right"
                                                data-bs-original-title="<?= get_label('mind_map', 'Mind Map') ?>"></i>
                                        </a>
                                        @if ($showSettings)
                                            <a href="javascript:void(0);" class="mr-2" data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                <i class='bx bx-cog' id="settings-icon"></i>
                                            </a>
                                            <ul class="dropdown-menu">
                                                @if ($canEditProjects)
                                                    <a href="javascript:void(0);" class="edit-project" data-offcanvas="true"
                                                        data-id="{{ $project->id }}">
                                                        <li class="dropdown-item">
                                                            <i
                                                                class='menu-icon tf-icons bx bx-edit text-primary'></i><?= get_label('update', 'Update') ?>
                                                        </li>
                                                    </a>
                                                @endif
                                                @if ($canDeleteProjects)
                                                    <a href="javascript:void(0);" class="delete" data-reload="true"
                                                        data-type="projects" data-id="{{ $project->id }}">
                                                        <li class="dropdown-item">
                                                            <i
                                                                class='menu-icon tf-icons bx bx-trash text-danger'></i><?= get_label('delete', 'Delete') ?>
                                                        </li>
                                                    </a>
                                                @endif
                                                @if ($canDuplicateProjects)
                                                    <a href="javascript:void(0);" class="duplicate" data-type="projects"
                                                        data-id="{{ $project->id }}" data-title="{{ $project->title }}"
                                                        data-reload="true">
                                                        <li class="dropdown-item">
                                                            <i
                                                                class='menu-icon tf-icons bx bx-copy text-warning'></i><?= get_label('duplicate', 'Duplicate') ?>
                                                        </li>
                                                    </a>
                                                @endif
                                            </ul>
                                        @endif
                                    </div>
                                </div>
                                @if ($project->budget != '')
                                    <span class='badge bg-label-primary me-1'>
                                        {{ format_currency($project->budget) }}</span>
                                @endif
                                <div class="my-{{ $project->budget != '' ? '3' : '2' }}">
                                    <div class="row align-items-center">
                                        <!-- Status Select Column -->
                                        <div class="col-md-{{ $project->note ? '7' : '6' }}">
                                            <label for="statusSelect"
                                                class="form-label"><?= get_label('status', 'Status') ?></label>
                                            <div class="d-flex align-items-center">
                                                <select
                                                    class="form-select form-select-sm select-bg-label-{{ $project->status->color }}"
                                                    id="statusSelect" data-id="{{ $project->id }}"
                                                    data-original-status-id="{{ $project->status->id }}"
                                                    data-original-color-class="select-bg-label-{{ $project->status->color }}">
                                                    @foreach ($statuses as $status)
                                                        @php
                                                            $disabled = canSetStatus($status) ? '' : 'disabled';
                                                        @endphp
                                                        <option value="{{ $status->id }}"
                                                            class="badge bg-label-{{ $status->color }}"
                                                            {{ $project->status->id == $status->id ? 'selected' : '' }}
                                                            {{ $disabled }}>
                                                            {{ $status->title }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @if ($project->note)
                                                    <i class="bx bx-notepad text-primary ms-1" data-bs-toggle="tooltip"
                                                        data-bs-offset="0,4" data-bs-placement="top"
                                                        data-bs-original-title="{{ $project->note }}"></i>
                                                @endif
                                            </div>
                                        </div>
                                        <!-- Priority Select Column -->
                                        <div class="col-md-{{ $project->note ? '5' : '6' }}">
                                            <label for="prioritySelect"
                                                class="form-label"><?= get_label('priority', 'Priority') ?></label>
                                            <select
                                                class="form-select form-select-sm select-bg-label-{{ $project->priority ? $project->priority->color : 'secondary' }}"
                                                id="prioritySelect" data-id="{{ $project->id }}"
                                                data-original-priority-id="{{ $project->priority ? $project->priority->id : '' }}"
                                                data-original-color-class="select-bg-label-{{ $project->priority ? $project->priority->color : 'secondary' }}">
                                                <option value="" class="badge bg-label-secondary">-</option>
                                                @foreach ($priorities as $priority)
                                                    <option value="{{ $priority->id }}"
                                                        class="badge bg-label-{{ $priority->color }}"
                                                        {{ $project->priority && $project->priority->id == $priority->id ? 'selected' : '' }}>
                                                        {{ $priority->title }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between my-4">
                                    <span><i class='bx bx-task text-primary'></i>
                                        <b><?= isAdminOrHasAllDataAccess() ? count($project->tasks) : $auth_user->project_tasks($project->id)->count() ?></b>
                                        <?= get_label('tasks', 'Tasks') ?></span>
                                    <a href="{{ url('projects/tasks/draggable/' . $project->id) }}"><button
                                            type="button"
                                            class="btn btn-sm rounded-pill btn-outline-primary"><?= get_label('tasks', 'Tasks') ?></button></a>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <p class="card-text">
                                            <?= get_label('users', 'Users') ?>:
                                        <ul class="list-unstyled users-list avatar-group d-flex align-items-center m-0">
                                            <?php
                                                                            $users = $project->users;
                                                                            $count = count($users);
                                                                            $displayed = 0;
                                                                            if ($count > 0) {
                                                                                // Case 1: Users are less than or equal to 10
                                                                                foreach ($users as $user) {
                                                                                    if ($displayed < 10) { ?>
                                            <li class="avatar avatar-sm pull-up"
                                                title="<?= $user->first_name ?> <?= $user->last_name ?>">
                                                <a href="{{ url('/users/profile/' . $user->id) }}">
                                                    <img src="<?= $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg') ?>"
                                                        class="rounded-circle"
                                                        alt="<?= $user->first_name ?> <?= $user->last_name ?>">
                                                </a>
                                            </li>
                                            <?php
                                                                                        $displayed++;
                                                                                    } else {
                                                                                        // Case 2: Users are greater than 10
                                                                                        $remaining = $count - $displayed;
                                                                                        echo '<span class="badge badge-center rounded-pill bg-primary mx-1">+' . $remaining . '</span>';
                                                                                        break;
                                                                                    }
                                                                                }
                                                                                // Add edit option at the end
                                                                                echo '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="' . $project->id . '"><span class="bx bx-edit"></span></a>';
                                                                            } else {
                                                                                // Case 3: Not assigned
                                                                                echo '<span class="badge bg-primary">' . get_label('not_assigned', 'Not assigned') . '</span>';
                                                                                // Add edit option at the end
                                                                                echo '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-id="' . $project->id . '"><span class="bx bx-edit"></span></a>';
                                                                            }
                                                                            ?>
                                        </ul>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="card-text">
                                            <?= get_label('clients', 'Clients') ?>:
                                        <ul class="list-unstyled users-list avatar-group d-flex align-items-center m-0">
                                            <?php
                                                                            $clients = $project->clients;
                                                                            $count = $clients->count();
                                                                            $displayed = 0;
                                                                            if ($count > 0) {
                                                                                foreach ($clients as $client) {
                                                                                    if ($displayed < 10) { ?>
                                            <li class="avatar avatar-sm pull-up"
                                                title="<?= $client->first_name ?> <?= $client->last_name ?>">
                                                <a href="{{ url('/clients/profile/' . $client->id) }}">
                                                    <img src="<?= $client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg') ?>"
                                                        class="rounded-circle"
                                                        alt="<?= $client->first_name ?> <?= $client->last_name ?>">
                                                </a>
                                            </li>
                                            <?php
                                                                                        $displayed++;
                                                                                    } else {
                                                                                        $remaining = $count - $displayed;
                                                                                        echo '<span class="badge badge-center rounded-pill bg-primary mx-1">+' . $remaining . '</span>';
                                                                                        break;
                                                                                    }
                                                                                }
                                                                                // Add edit option at the end
                                                                                echo '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="' . $project->id . '"><span class="bx bx-edit"></span></a>';
                                                                            } else {
                                                                                // Display "Not assigned" badge
                                                                                echo '<span class="badge bg-primary">' . get_label('not_assigned', 'Not assigned') . '</span>';
                                                                                // Add edit option at the end
                                                                                echo '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="' . $project->id . '"><span class="bx bx-edit"></span></a>';
                                                                            }
                                                                            ?>
                                        </ul>
                                        </p>
                                    </div>
                                </div>
                                @if ($project->start_date || $project->end_date)
                                    <div class="row mt-2">
                                        <div class="col-md-6 text-start">
                                            @if ($project->start_date)
                                                <i
                                                    class='bx bx-calendar text-success'></i><?= get_label('starts_at', 'Starts at') ?>
                                                : {{ format_date($project->start_date) }}
                                            @endif
                                        </div>

                                        @if ($project->end_date)
                                            <div class="col-md-6 text-end">
                                                <i
                                                    class='bx bx-calendar text-danger'></i><?= get_label('ends_at', 'Ends at') ?>
                                                : {{ format_date($project->end_date) }}
                                            </div>
                                        @endif
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>
                @endforeach
                <div>
                    {{ $projects->links() }}
                </div>
            </div>
            <!-- delete project modal -->
        @else
            <?php $type = 'projects'; ?>
            <x-empty-state-card :type="$type" />
        @endif
    </div>
    <script>
        var add_favorite = '<?= get_label('add_favorite', 'Click to mark as favorite') ?>';
        var remove_favorite = '<?= get_label('remove_favorite', 'Click to remove from favorite') ?>';
    </script>
    <script src="{{ asset('assets/js/pages/project-grid.js') }}"></script>
@endsection
