@extends('layout')
@section('title')
    {{ get_label('projects', 'Projects') }} - {{ get_label('calendar_view', 'Calendar View') }}
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-2 mt-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1">
                        <li class="breadcrumb-item">
                            <a href="{{ url('home') }}">{{ get_label('home', 'Home') }}</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a
                                href="{{ url(getUserPreferences('projects', 'default_view')) }}">{{ get_label('projects', 'Projects') }}</a>
                        </li>
                        <li class="breadcrumb-item active">
                            {{ get_label('calendar_view', 'Calendar View') }}
                        </li>
                    </ol>
                </nav>
            </div>
            <div>
                @php
                    $projectsDefaultView = getUserPreferences('projects', 'default_view');
                @endphp
                @if ($projectsDefaultView === 'projects/calendar-view')
                    <span class="badge bg-primary"><?= get_label('default_view', 'Default View') ?></span>
                @else
                    <a href="javascript:void(0);"><span class="badge bg-secondary" id="set-default-view"
                            data-type="projects"
                            data-view="calendar"><?= get_label('set_as_default_view', 'Set as Default View') ?></span></a>
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
                <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#create_project_modal">
                    <button type="button" class="btn btn-sm btn-primary action_create_projects" data-bs-toggle="tooltip"
                        data-bs-placement="left"
                        data-bs-original-title="<?= get_label('create_project', 'Create project') ?>">
                        <i class='bx bx-plus'></i>
                    </button>
                </a>
                <a href="{{ route('projects.list') }}">
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
                <a href="{{ $finalKanbanUrl }}">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="<?= get_label('kanban_view', 'Kanban View') ?>">
                        <i class='bx bx-layout'></i>
                    </button>
                </a>
                <a href="{{ $ganttChartUrl }}">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="<?= get_label('gantt_chart_view', 'Gantt Chart View') ?>"><i
                            class='bx bx-bar-chart'></i>
                    </button>
                </a>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="calendar-wrapper">
                    <!-- Enhanced Sidebar -->
                    <div class="calendar-sidebar">
                        <!-- Date Range Picker for Navigation -->
                        <div class="mini-calendar">
                            <h6><i class="bx bx-calendar me-1"></i> Jump to Date</h6>
                            <input type="text" id="daterange-picker" class="form-control"
                                placeholder="Select date range..." readonly>
                        </div>

                        <!-- Status Filters (Dynamic) -->
                        <div class="filter-section">
                            <h6><i class="bx bx-flag me-1"></i> Project Status</h6>
                            <div id="status-filters-container">
                                <div class="skeleton-loader"></div>
                                <div class="skeleton-loader"></div>
                                <div class="skeleton-loader"></div>
                            </div>
                        </div>

                        <!-- Priority Filters (Dynamic) -->
                        <div class="filter-section">
                            <h6><i class="bx bx-star me-1"></i> Priority</h6>
                            <div id="priority-filters-container">
                                <div class="skeleton-loader"></div>
                                <div class="skeleton-loader"></div>
                                <div class="skeleton-loader"></div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="filter-section">
                            <h6><i class="bx bx-cog me-1"></i> Quick Actions</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" id="selectAllFilters">
                                <i class='bx bx-checkbox-checked me-1'></i> Select All
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 mb-2" id="clearAllFilters">
                                <i class='bx bx-checkbox me-1'></i> Clear All
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm w-100" id="refreshCalendar">
                                <i class="bx bx-refresh me-1"></i> Refresh
                            </button>
                        </div>

                        <!-- Calendar Statistics -->
                        <div class="filter-section">
                            <h6><i class="bx bx-bar-chart me-1"></i> Statistics</h6>
                            <div class="small text-muted">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Total Projects:</span>
                                    <span id="total-projects">0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Visible:</span>
                                    <span id="visible-projects">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Filtered:</span>
                                    <span id="filtered-projects">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar -->
                    <div class="calendar-main card p-3">
                        <div id="projectCalenderDiv"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="confirmDragProjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="exampleModalLabel2"><?= get_label('confirm', 'Confirm!') ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?= get_label('confirm_update_project_dates', 'Are You Want to Update the Project Dates?') ?></p>
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
    <div class="modal fade" id="confirmResizeProjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="exampleModalLabel2"><?= get_label('confirm', 'Confirm!') ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?= get_label('confirm_update_project_end_date', 'Are You Want to Update the Project End Date?') ?>
                    </p>
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
