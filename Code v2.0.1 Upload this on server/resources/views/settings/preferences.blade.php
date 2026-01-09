@extends('layout')
@section('title')
    <?= get_label('preferences', 'Preferences') ?>
@endsection
@php
    $enabledNotifications = getUserPreferences(
        'notification_preference',
        'enabled_notifications',
        getAuthenticatedUser(true, true),
    );
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
                        <li class="breadcrumb-item active">
                            <?= get_label('preferences', 'Preferences') ?>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="list-group list-group-horizontal-md text-md-center mb-4">
                            <a class="list-group-item list-group-item-action active" data-bs-toggle="list"
                                href="#notification-preferences">{{ get_label('notification_preferences', 'Notification Preferences') }}</a>
                            <a class="list-group-item list-group-item-action" data-bs-toggle="list"
                                href="#customize-menu-order">{{ get_label('customize_menu_order', 'Customize Menu Order') }}</a>
                        </div>
                        <div class="tab-content px-0">
                            <div class="tab-pane fade show active" id="notification-preferences">
                                <form action="{{ url('save-notification-preferences') }}" class="form-submit-event"
                                    method="POST">
                                    <input type="hidden" name="dnr">
                                    <div class="table-responsive">
                                        <table class="table-striped table-borderless border-bottom table">
                                            <thead>
                                                <tr>
                                                    <th>
                                                        <div class="form-check">
                                                            <input type="checkbox" id="selectAllPreferences"
                                                                class="form-check-input">
                                                            <label class="form-check-label"
                                                                for="selectAllPreferences"><?= get_label('select_all', 'Select all') ?></label>
                                                        </div>
                                                    </th>
                                                </tr>
                                                <tr>
                                                    <th class="text-nowrap">{{ get_label('type', 'Type') }}</th>
                                                    <th class="text-nowrap text-center">{{ get_label('email', 'Email') }}
                                                    </th>
                                                    <th class="text-nowrap text-center">{{ get_label('sms', 'SMS') }}</th>
                                                    <th class="text-nowrap text-center">
                                                        {{ get_label('whatsapp', 'WhatsApp') }}
                                                    </th>
                                                    <th class="text-nowrap text-center">{{ get_label('system', 'System') }}
                                                    </th>
                                                    <th class="text-nowrap text-center">{{ get_label('slack', 'Slack') }}
                                                    </th>
                                                    <th class="text-nowrap text-center">
                                                        {{ get_label('push_in_app', 'Push (In APP)') }}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Notification types -->
                                                @php
                                                    $notificationTypes = [
                                                        'project_assignment' => 'Project Assignment',
                                                        'project_status_updation' => 'Project Status Updation',
                                                        // 'project_issue_assignment' => 'Project Issue Assignment',
                                                        'task_assignment' => 'Task Assignment',
                                                        'task_status_updation' => 'Task Status Updation',
                                                        'workspace_assignment' => 'Workspace Assignment',
                                                        'meeting_assignment' => 'Meeting Assignment',
                                                        // 'announcement' => 'Announcement',
                                                        'leave_request_creation' => 'Leave Request Creation',
                                                        'leave_request_status_updation' =>
                                                            'Leave Request Status Updation',
                                                        'team_member_on_leave_alert' => 'Team Member on Leave Alert',
                                                        'birthday_wish' => 'Birthday Wish',
                                                        'work_anniversary_wish' => 'Work Anni. Wish',

                                                        'task_reminder' => 'Task Reminder',
                                                        'recurring_task' => 'Recurring Task',
                                                        'todo_reminder' => 'ToDo Reminder',
                                                    ];

                                                    $channels = [
                                                        'email' => 'Email',
                                                        'sms' => 'SMS',
                                                        'whatsapp' => 'WhatsApp',
                                                        'system' => 'System',
                                                        'slack' => 'Slack',
                                                        'push' => 'Push',
                                                    ];
                                                @endphp

                                                @foreach ($notificationTypes as $typeKey => $typeLabel)
                                                    <tr>
                                                        <td class="text-nowrap">{{ get_label($typeKey, $typeLabel) }}</td>

                                                        @foreach ($channels as $channelKey => $channelLabel)
                                                            @php
                                                                $notificationKey = "{$channelKey}_{$typeKey}";
                                                                $isChecked =
                                                                    is_array($enabledNotifications) &&
                                                                    (in_array(
                                                                        $notificationKey,
                                                                        $enabledNotifications,
                                                                    ) ||
                                                                        empty($enabledNotifications));
                                                            @endphp

                                                            <td>

                                                                <div class="form-check d-flex justify-content-center">
                                                                    <input class="form-check-input" type="checkbox"
                                                                        id="defaultCheck{{ $typeKey }}"
                                                                        name="enabled_notifications[]"
                                                                        value="{{ $notificationKey }}"
                                                                        {{ $isChecked ? 'checked' : '' }} />
                                                                </div>
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary me-2"
                                            id="submit_btn"><?= get_label('update', 'Update') ?></button>
                                    </div>
                                </form>
                            </div>
                            <div class="tab-pane fade show" id="customize-menu-order">
                                <form id="menu-order-form" method="POST">
                                    <ul id="sortable-menu" class="category-list">
                                        {{-- @dd($groupedMenus) --}}

                                        @php
                                            // Filter out categories with no visible menus
                                            $filteredCategories = collect($groupedMenus)->filter(function ($menus) {
                                                return collect($menus)->contains(function ($menu) {
                                                    return !isset($menu['show']) || $menu['show'] == 1;
                                                });
                                            });
                                        @endphp

                                        @foreach ($filteredCategories as $categoryKey => $menus)
                                            @php
                                                // Filter menus inside this category
                                                $filteredMenus = collect($menus)->filter(function ($menu) {
                                                    return !isset($menu['show']) || $menu['show'] == 1;
                                                });
                                            @endphp

                                            @if ($filteredMenus->isNotEmpty())
                                                <!-- Level 1: Category -->
                                                <li class="category-item" data-category="{{ $categoryKey }}">
                                                    <div class="category-header handle">
                                                        <span class="category-icon bx bx-folder"></span>
                                                        <strong>{{ get_label($categoryKey, ucfirst(str_replace('_', ' ', $categoryKey))) }}</strong>
                                                    </div>

                                                    <!-- Level 2: Menus within this category -->
                                                    <ul class="menu-list">
                                                        @foreach ($filteredMenus as $menu)
                                                            <li class="menu-item" data-id="{{ $menu['id'] }}">
                                                                <div class="menu-header">
                                                                    <span class="handle bx bx-menu"></span>
                                                                    <span>{{ $menu['label'] }}</span>
                                                                </div>

                                                                <!-- Level 3: Submenus within this menu -->
                                                                @if (!empty($menu['submenus']))
                                                                    <ul class="submenu-list">
                                                                        @foreach ($menu['submenus'] as $submenu)
                                                                            @if (!isset($submenu['show']) || $submenu['show'] === 1)
                                                                                <li class="submenu-item"
                                                                                    data-id="{{ $submenu['id'] }}">
                                                                                    <span class="handle bx bx-menu"></span>
                                                                                    <span>{{ $submenu['label'] }}</span>
                                                                                </li>
                                                                            @endif
                                                                        @endforeach
                                                                    </ul>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>


                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary me-2"
                                            id="btnSaveMenuOrder"><?= get_label('update', 'Update') ?></button>
                                        <button type="button" class="btn btn-warning"
                                            id="btnResetDefaultMenuOrder"><?= get_label('reset_to_default', 'Reset to default') ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="confirmResetDefaultMenuOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="exampleModalLabel2"><?= get_label('confirm', 'Confirm!') ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?= get_label('confirm_reset_default_menu', 'Are you sure you want to reset the menu order to the default?') ?>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <?= get_label('close', 'Close') ?>
                    </button>
                    <button type="submit" class="btn btn-primary"
                        id="btnconfirmResetDefaultMenuOrder"><?= get_label('yes', 'Yes') ?></button>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('assets/js/pages/preferences.js') }}"></script>
@endsection
