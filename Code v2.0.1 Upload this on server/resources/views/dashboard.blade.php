@extends('layout')
@section('title')
    <?= get_label('dashboard', 'Dashboard') ?>
@endsection
@section('content')
    @authBoth
    <div class="container-fluid">
        <!-- Alert for Reset Warning -->
        @if (config('constants.ALLOW_MODIFICATION') === 0)
            <x-dashboard.alert type="warning" classes="container mb-0 mt-4" icon="bx bx-timer"
                message="Important: Data automatically resets every 24 hours!" dismissible="true" />
        @endif
        @php
                $tiles = [
                    'manage_projects' => [
                        'permission' => 'manage_projects',
                        'icon' => 'bx bx-briefcase-alt-2 text-success',
                        'icon-bg' => 'bg-label-success',
                        'label' => get_label('total_projects', 'Total projects'),
                        'count' => is_countable($projects) && count($projects) > 0 ? count($projects) : 0,
                        'url' => url(getUserPreferences('projects', 'default_view')),
                        'link_color' => 'text-success',
                    ],
                    'manage_tasks' => [
                        'permission' => 'manage_tasks',
                        'icon' => 'bx bx-task text-primary',
                        'icon-bg' => 'bg-label-primary',
                        'label' => get_label('total_tasks', 'Total tasks'),
                        'count' => $tasks,
                        'url' => url(getUserPreferences('tasks', 'default_view')),
                        'link_color' => 'text-primary',
                    ],
                    'manage_users' => [
                        'permission' => 'manage_users',
                        'icon' => 'bx bxs-user-detail text-warning',
                        'icon-bg' => 'bg-label-warning',
                        'label' => get_label('total_users', 'Total users'),
                        'count' => is_countable($users) && count($users) > 0 ? count($users) : 0,
                        'url' => url('users'),
                        'link_color' => 'text-warning',
                    ],
                    'manage_clients' => [
                        'permission' => 'manage_clients',
                        'icon' => 'bx bxs-user-detail text-info',
                        'icon-bg' => 'bg-label-info',
                        'label' => get_label('total_clients', 'Total clients'),
                        'count' => is_countable($clients) && count($clients) > 0 ? count($clients) : 0,
                        'url' => url('clients'),
                        'link_color' => 'text-info',
                    ],
                    'manage_meetings' => [
                        'permission' => 'manage_meetings',
                        'icon' => 'bx bx-shape-polygon text-warning',
                        'icon-bg' => 'bg-label-warning',
                        'label' => get_label('total_meetings', 'Total meetings'),
                        'count' => is_countable($meetings) && count($meetings) > 0 ? count($meetings) : 0,
                        'url' => url('meetings'),
                        'link_color' => 'text-warning',
                    ],
                    'total_todos' => [
                        'permission' => null, // No specific permission required
                        'icon' => 'bx bx-list-check text-info',
                        'icon-bg' => 'bg-label-info',
                        'label' => get_label('total_todos', 'Total todos'),
                        'count' => is_countable($total_todos) && count($total_todos) > 0 ? count($total_todos) : 0,
                        'url' => url('todos'),
                        'link_color' => 'text-info',
                    ],
                ];
                // Filter tiles based on user permissions
                $filteredTiles = array_filter($tiles, function ($tile) use ($auth_user) {
                    return !$tile['permission'] || $auth_user->can($tile['permission']);
                });
                // Get the first 4 tiles
                $filteredTiles = array_slice($filteredTiles, 0, 4);
            @endphp


        <!-- Tiles Section -->
        <div class="col-lg-12 col-md-12 order-1">

            <div class="row mt-4">
                @foreach ($filteredTiles as $tile)
                    <x-dashboard.tile :label="$tile['label']" :count="$tile['count']" :url="$tile['url']" :linkColor="$tile['link_color']"
                        :icon="$tile['icon']" :iconBg="$tile['icon-bg']" />
                @endforeach
            </div>
            <!-- Statistics Section -->

            <div class="row">
                <x-dashboard.statistics :todos="$todos" :activities="$activities" />
            </div>
            <!-- Tabs Section -->
            @if (
                !isClient() &&
                    ($auth_user->can('manage_users') || $auth_user->can('manage_projects') || $auth_user->can('manage_tasks')))
                <x-dashboard.tabs :users="$users" :projects="$projects" :tasks="$tasks" />
            @endif
        </div>
        <!-- ------------------------------------------- -->
        @php
            $titles = [];
            $project_counts = [];
            $task_counts = [];
            $bg_colors = [];
            $total_projects = 0;
            $total_tasks = 0;
            $total_todos = count($todos);
            $done_todos = 0;
            $pending_todos = 0;
            $todo_counts = [];
            $ran = [
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
                '#FFFFFF',
            ];
            foreach ($statuses as $status) {
                $project_count = isAdminOrHasAllDataAccess()
                    ? count($status->projects)
                    : $auth_user->status_projects($status->id)->count();
                array_push($project_counts, $project_count);
                $task_count = isAdminOrHasAllDataAccess()
                    ? count($status->tasks)
                    : $auth_user->status_tasks($status->id)->count();
                array_push($task_counts, $task_count);
                array_push($titles, "'" . $status->title . "'");
                $v = array_shift($ran);
                array_push($bg_colors, "'" . $v . "'");
                $total_projects += $project_count;
                $total_tasks += $task_count;
            }
            $titles = implode(',', $titles);
            $project_counts = implode(',', $project_counts);
            $task_counts = implode(',', $task_counts);
            $bg_colors = implode(',', $bg_colors);
            foreach ($todos as $todo) {
                $todo->is_completed ? ($done_todos += 1) : ($pending_todos += 1);
            }
            array_push($todo_counts, $done_todos);
            array_push($todo_counts, $pending_todos);
            $todo_counts = implode(',', $todo_counts);
        @endphp
        <script>
            var labels = [<?= $titles ?>];
            var project_data = [<?= $project_counts ?>];
            var task_data = [<?= $task_counts ?>];
            var bg_colors = [<?= $bg_colors ?>];
            var total_projects = [<?= $total_projects ?>];
            var total_tasks = [<?= $total_tasks ?>];
            var total_todos = [<?= $total_todos ?>];
            var todo_data = [<?= $todo_counts ?>];
            //labels
            var done = '<?= get_label('done', 'Done') ?>';
            var pending = '<?= get_label('pending', 'Pending') ?>';
            var total = '<?= get_label('total', 'Total') ?>';
        </script>
        <script src="{{ asset('assets/js/apexcharts.js') }}"></script>
        <script src="{{ asset('assets/js/Sortable.min.js') }}"></script>

        <script src="{{ asset('assets/js/pages/dashboard.js') }}"></script>
    @else
        <div class="w-100 h-100 d-flex align-items-center justify-content-center">
            <span>You must <a href="{{ url('login') }}">Log in</a> or <a href="{{ url('register') }}">Register</a> to
                access
                {{ $general_settings['company_title'] }}!</span>
        </div>
    @endauth
@endsection
