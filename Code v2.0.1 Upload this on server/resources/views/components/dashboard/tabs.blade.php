@php $auth_user = auth()->user(); @endphp

@if (!isClient() && ($auth_user->can('manage_users') &&
    (
        (!isset($general_settings['upcomingBirthdays']) || $general_settings['upcomingBirthdays'] == 1) ||
        (!isset($general_settings['upcomingWorkAnniversaries']) || $general_settings['upcomingWorkAnniversaries'] == 1) ||
        (!isset($general_settings['membersOnLeave']) || $general_settings['membersOnLeave'] == 1)
    )
))
<div class="nav-align-top">
    <ul class="nav nav-tabs" role="tablist">
        @if (!isset($general_settings['upcomingBirthdays']) || $general_settings['upcomingBirthdays'] == 1)
            <x-dashboard.tab-item
                :active="true"
                icon="bx bx-cake text-success"
                :label="get_label('upcoming_birthdays', 'Upcoming birthdays')"
                target="navs-top-upcoming-birthdays"
            />
        @endif
        @if (!isset($general_settings['upcomingWorkAnniversaries']) || $general_settings['upcomingWorkAnniversaries'] == 1)
            <x-dashboard.tab-item
                :active="!isset($general_settings['upcomingBirthdays']) || $general_settings['upcomingBirthdays'] != 1"
                icon="bx bx-star text-warning"
                :label="get_label('upcoming_work_anniversaries', 'Upcoming work anniversaries')"
                target="navs-top-upcoming-work-anniversaries"
            />
        @endif
        @if (!isset($general_settings['membersOnLeave']) || $general_settings['membersOnLeave'] == 1)
            <x-dashboard.tab-item
                :active="isset($general_settings['upcomingBirthdays']) && isset($general_settings['upcomingWorkAnniversaries']) &&
                        $general_settings['upcomingBirthdays'] != 1 && $general_settings['upcomingWorkAnniversaries'] != 1"
                icon="bx bx-home text-danger"
                :label="get_label('members_on_leave', 'Members on leave')"
                target="navs-top-members-on-leave"
            />
        @endif
    </ul>
    <div class="tab-content">
        @if (!isset($general_settings['upcomingBirthdays']) || $general_settings['upcomingBirthdays'] == 1)
            <x-dashboard.tab-content :active="true" id="navs-top-upcoming-birthdays">
                <x-dashboard.calendar-tab
                    :alert="!$auth_user->dob ? get_label('dob_not_set_alert', 'Your DOB is not set') : ''"
                    :alert-url="url('users/edit/' . $auth_user->id)"
                    :alert-action="get_label('click_here_to_set_it_now', 'Click here to set it now')"
                    calendar-id="upcomingBirthdaysCalendar"
                    list-component="upcoming-birthdays-card"
                    :data="$users"
                />
            </x-dashboard.tab-content>
        @endif
        @if (!isset($general_settings['upcomingWorkAnniversaries']) || $general_settings['upcomingWorkAnniversaries'] == 1)
            <x-dashboard.tab-content :active="!isset($general_settings['upcomingBirthdays']) || $general_settings['upcomingBirthdays'] != 1" id="navs-top-upcoming-work-anniversaries">
                <x-dashboard.calendar-tab
                    :alert="!$auth_user->doj ? get_label('doj_not_set_alert', 'Your DOJ is not set') : ''"
                    :alert-url="url('users/edit/' . $auth_user->id)"
                    :alert-action="get_label('click_here_to_set_it_now', 'Click here to set it now')"
                    calendar-id="upcomingWorkAnniversariesCalendar"
                    list-component="upcoming-work-anniversaries-card"
                    :data="$users"
                />
            </x-dashboard.tab-content>
        @endif
        @if (!isset($general_settings['membersOnLeave']) || $general_settings['membersOnLeave'] == 1)
            <x-dashboard.tab-content :active="isset($general_settings['upcomingBirthdays']) && isset($general_settings['upcomingWorkAnniversaries']) &&
                    $general_settings['upcomingBirthdays'] != 1 && $general_settings['upcomingWorkAnniversaries'] != 1" id="navs-top-members-on-leave">
                <x-dashboard.calendar-tab
                    calendar-id="membersOnLeaveCalendar"
                    list-component="members-on-leave-card"
                    :data="$users"
                />
            </x-dashboard.tab-content>
        @endif
    </div>
</div>
@endif

@if ($auth_user->can('manage_projects') || $auth_user->can('manage_tasks'))
<div class="nav-align-top {{ $auth_user->can('manage_users') && (
        (!isset($general_settings['upcomingBirthdays']) || $general_settings['upcomingBirthdays'] == 1) ||
        (!isset($general_settings['upcomingWorkAnniversaries']) || $general_settings['upcomingWorkAnniversaries'] == 1) ||
        (!isset($general_settings['membersOnLeave']) || $general_settings['membersOnLeave'] == 1)
    ) ? ' mt-4' : '' }}">
    <ul class="nav nav-tabs" role="tablist">
        @if ($auth_user->can('manage_projects'))
            <x-dashboard.tab-item
                :active="true"
                icon="bx bx-briefcase-alt-2 text-success"
                :label="get_label('projects', 'Projects')"
                target="navs-top-projects"
            />
        @endif
        @if ($auth_user->can('manage_tasks'))
            <x-dashboard.tab-item
                :active="!$auth_user->can('manage_projects')"
                icon="bx bx-task text-primary"
                :label="get_label('tasks', 'Tasks')"
                target="navs-top-tasks"
            />
        @endif
    </ul>
    <div class="tab-content">
        @if ($auth_user->can('manage_projects'))
            <x-dashboard.tab-content :active="true" id="navs-top-projects">
                <x-dashboard.section-header :title="$auth_user->first_name . '\'s ' . get_label('projects', 'Projects')" />
                @if (is_countable($projects) && count($projects) > 0)
                    <?php $type = isUser() ? 'user' : 'client'; $id = isAdminOrHasAllDataAccess() ? '' : $type . '_' . $auth_user->id; ?>
                    <x-projects-card :projects="$projects" :id="$id" />
                @else
                    <x-dashboard.empty-state-card :type="'Projects'" />
                @endif
            </x-dashboard.tab-content>
        @endif
        @if ($auth_user->can('manage_tasks'))
            <x-dashboard.tab-content :active="!$auth_user->can('manage_projects')" id="navs-top-tasks">
                <x-dashboard.section-header :title="$auth_user->first_name . '\'s ' . get_label('tasks', 'Tasks')" />
                @if ($tasks > 0)
                    <?php $type = isUser() ? 'user' : 'client'; $id = isAdminOrHasAllDataAccess() ? '' : $type . '_' . $auth_user->id; ?>
                    <x-tasks-card :tasks="$tasks" :id="$id" />
                @else
                    <x-dashboard.empty-state-card :type="'Tasks'" />
                @endif
            </x-dashboard.tab-content>
        @endif
    </div>
</div>
@endif
