<!-- Navbar -->
<?php

use App\Models\Language;
use App\Models\Notification;

$authenticatedUser = getAuthenticatedUser();
$current_language = Language::where('code', app()->getLocale())->get(['name', 'code']);
$default_language = $authenticatedUser->lang;
$unreadNotificationsCount = $authenticatedUser->notifications()
    ->wherePivot('read_at', null)
    ->wherePivot('is_system', 1)
    ->count();
$unreadNotifications = $authenticatedUser->notifications()
    ->wherePivot('read_at', null)
    ->wherePivot('is_system', 1)
    ->getQuery()
    ->orderBy('id', 'desc')
    ->take(10)
    ->get();


// Calculate the remaining unread notifications count
$remainingUnreadNotificationsCount = $unreadNotificationsCount - 10;

// Ensure the remaining count is not negative
if ($remainingUnreadNotificationsCount < 0) {
    $remainingUnreadNotificationsCount = 0;
}
?>
@authBoth
<div id="section-not-to-print">
    <nav class="layout-navbar container-fluid navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                <i class="bx bx-menu bx-sm"></i>
            </a>
        </div>
        <div class="navbar-nav-right d-flex  align-items-center" id="navbar-collapse">
           <div class="nav-item">
    <i class="bx bx-search"></i>
    <span class="cursor-pointer mx-2" id="global-search">
        {{ get_label('search','Search') }}
        <span class="d-none d-sm-inline">[CTRL + K]</span>
    </span>
</div>

            <ul class="navbar-nav flex-row align-items-center ms-auto">
                  @if (getAuthenticatedUser()->can('manage_system_notifications'))
                <li class="nav-item navbar-dropdown dropdown">
                    <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                        <i class='bx bx-bell bx-sm'></i>
                        <span id="unreadNotificationsCount" class="badge rounded-pill badge-center h-px-20 w-px-20 bg-danger {{ $unreadNotificationsCount > 0 ? '' : 'd-none' }}">{{ $unreadNotificationsCount }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-0">
                        <li class="dropdown-header dropdown-header-highlighted fixed-header">
                            <i class="bx bx-bell bx-md me-2"></i>{{ get_label('notifications','Notifications') }}
                        </li>
                        <div id="unreadNotificationsContainer" class="scrollable-dropdown">
                            @if ($unreadNotificationsCount > 0)
                            @foreach ($unreadNotifications as $notification)
                            <li>
                                <a class="dropdown-item update-notification-status" data-id="{{$notification->id}}" href="javascript:void(0);">
                                    <div class="d-flex align-items-center">
                                        <div class="me-auto fw-semibold">{{ $notification->title }} <small class="text-muted mx-2">{{ $notification->created_at->diffForHumans() }}</small></div>
                                        <i class="bx bx-bell me-2"></i>
                                    </div>
                                    <div class="mt-2">
                                        {{ strlen(strip_tags($notification->message)) > 50 ? substr(strip_tags($notification->message), 0, 50) . '...' : strip_tags($notification->message) }}
                                    </div>

                                </a>
                            </li>
                            <li>
                                <div class="dropdown-divider"></div>
                            </li>
                            @endforeach
                            @else
                            <li class="p-5 d-flex align-items-center justify-content-center">
                                <span>{{ get_label('no_unread_notifications', 'No unread notifications') }}</span>
                            </li>
                            <li>
                                <div class="dropdown-divider"></div>
                            </li>
                            @endif
                        </div>
                        <li class="d-flex justify-content-between fixed-footer">
                            <a href="{{ url('notifications') }}" class="p-3">
                                <b>{{ get_label('view_all', 'View all') }}</b>
                                @if($remainingUnreadNotificationsCount > 0)
                                <span class="badge bg-primary">+{{ $remainingUnreadNotificationsCount }}</span>
                                @endif
                            </a>
                            <a href="#" class="p-3 text-end" id="mark-all-notifications-as-read"><b>{{ get_label('mark_all_as_read', 'Mark all as read') }}</b></a>
                        </li>
                    </ul>
                </li>

                @endif
                <li class="nav-item navbar-dropdown dropdown ml-1">
                    <div class="btn-group dropend px-1">
                        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="icon-only"><i class='bx bx-globe'></i></span> <span class="language-name"><?= $current_language[0]['name'] ?? '' ?></span>
                        </button>
                        <ul class="dropdown-menu language-dropdown" id="languageDropdown">
                            @foreach ($languages as $language)
                            <?php $checked = $language->code == app()->getLocale() ? "<i class='menu-icon tf-icons bx bx-check-square text-primary'></i>" : "<i class='menu-icon tf-icons bx bx-square text-solid'></i>" ?>
                            <li class="dropdown-item">
                                <a href="{{ url('/settings/languages/switch/' . $language->code) }}">
                                    <?= $checked ?>
                                    {{ $language->name }}
                                </a>
                            </li>
                            @endforeach
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            @if (!$current_language->isEmpty() && $current_language[0]['code'] == $default_language)
                            <li>
                                <span class="badge bg-primary mx-5 mb-1 mt-1" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('current_language_is_your_primary_language', 'Current language is your primary language') ?>">
                                    <?= get_label('primary', 'Primary') ?>
                                </span>
                            </li>
                            @else
                            <a href="javascript:void(0);">
                                <span class="badge bg-secondary mx-5 mb-1 mt-1" id="set-as-default" data-lang="{{ app()->getLocale() }}" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('set_current_language_as_your_primary_language', 'Set current language as your primary language') ?>">
                                    <?= get_label('set_as_primary', 'Set as primary') ?>
                                </span>
                            </a>
                            @endif
                        </ul>
                    </div>
                    </button>
                </li>
                <li class="nav-item navbar-dropdown dropdown mt-3 mx-2">
                    <p class="nav-item">
                        <span class="nav-mobile-hidden"><?= get_label('hi', 'Hi') ?>👋</span>
                        <span class="nav-mobile-hidden">{{Str::limit($authenticatedUser->first_name,7)}}</span>
                    </p>
                </li>
                <!-- User -->
                <li class="nav-item navbar-dropdown dropdown">
                    <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                        <div class="avatar avatar-online">
                            <img src="{{$authenticatedUser->photo ? asset('storage/' . $authenticatedUser->photo) : asset('storage/photos/no-image.jpg')}}" alt class="rounded-circle" />
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <div class="dropdown-item">
                                <div class="d-flex">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="avatar avatar-online avatar-nav-dropdown">
                                            <img src="{{$authenticatedUser->photo ? asset('storage/' . $authenticatedUser->photo) : asset('storage/photos/no-image.jpg')}}" alt class="rounded-circle" />
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <span class="fw-semibold d-block">{{ Str::limit($authenticatedUser->first_name . ' ' . $authenticatedUser->last_name, 16) }}</span>
                                        <small class="text-muted text-capitalize">
                                            {{ucfirst($authenticatedUser->getRoleNames()->first())}}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="dropdown-divider"></div>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ url('/account/' . $authenticatedUser->id) }}">
                                <i class="bx bx-user me-2"></i>
                                <span class="align-middle"><?= get_label('my_profile', 'My Profile') ?></span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ url('preferences') }}">
                                <i class='bx bx-cog me-2'></i>
                                <span class="align-middle"><?= get_label('preferences', 'Preferences') ?></span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ url('clear-cache') }}">
                                <i class="bx bx-refresh"></i>
                                <span class="align-middle">{{ get_label('clear_system_cache', 'Clear System Cache') }}</span>
                            </a>
                        </li>


                        <li>
                            <div class="dropdown-divider"></div>
                        </li>
                        <li>
                            <form action="{{url('logout')}}" method="POST" class="dropdown-item">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bx bx-log-out-circle"></i> <?= get_label('logout', 'Logout') ?></button>
                            </form>
                        </li>
                    </ul>
                </li>
                <!--/ User -->
            </ul>
        </div>
    </nav>
</div>

@else
@endauth
<script>
    var label_search = '<?= get_label('search', 'Search') ?>';
</script>
<script src="{{asset('assets/js/pages/navbar.js')}}"></script>
<!-- / Navbar -->
