<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="{{ asset('assets/') }}" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>@yield('title') - {{ $general_settings['company_title'] ?? 'Taskify' }}</title>
    <meta name="description" content="" />
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon"
        href="{{ asset($general_settings['favicon'] ?? 'storage/logos/default_favicon.png') }}" />
    <!-- Fonts -->
    <link rel="stylesheet" href="{{ asset('assets/css/google-fonts.css') }}" />
    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}" />
    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" class="template-customizer-core-css" />

    <link rel="stylesheet" href="{{ asset('assets/vendor/css/theme-default.css') }}"
        class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}" />
    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/apex-charts.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/lightbox/lightbox.min.css') }}" />
    <!-- Page CSS -->
    <!-- Helpers -->
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <!-- Date picker -->
    <link rel="stylesheet" href="{{ asset('assets/css/daterangepicker.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-datetimepicker.min.css') }}" />
    <link href="{{ asset('assets/css/select2.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/bootstrap-table.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/dragula.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/toastr.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/dropzone.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/fullcalendar/core/main.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/fullcalendar/daygrid/main.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/fullcalendar/list/main.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/js-draw.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.3.4/build/css/intlTelInput.css">

    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.3.4/build/js/intlTelInput.min.js"></script>


    @if (Request::is('projects/kanban') || Request::is('leads/kanban-view'))
        <link
            href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;600;700&family=Source+Sans+Pro:wght@400;600;700&family=Nunito:wght@400;700&family=Raleway:wght@400;600;700&family=Ubuntu:wght@400;700&family=Work+Sans:wght@400;700&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('assets/css/project-kanban.css') }}" />
    @endif
    @if (Request::is('projects/mind-map/*'))
        <link rel="stylesheet" href="{{ asset('assets/css/mind-map.css') }}" />
    @endif
    @if (Request::is('projects/gantt-chart') || Request::is('projects/gantt-chart/favorite'))
        <link rel="stylesheet" href="{{ asset('assets/css/frappe-gantt.css') }}" />
    @endif
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{ asset('assets/js/config.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <!-- Charts -->
    <script src="{{ asset('assets/js/apexcharts.js') }}"></script>
    <script>
        var baseUrl = "{{ url('/') }}";
        var currencySymbol = "{{ $general_settings['currency_symbol'] ?? '' }}";
    </script>

    <!-- PWA Manifest -->
    @laravelPWA
</head>

<body>

    <!-- Layout wrapper -->
    @if (
    Request::is('forgot-password') ||
    Request::is('/') ||
    Request::segment(1) == 'reset-password' ||
    Request::is('install') ||
    //  Request::is('system-health') ||
    Request::is('signup') ||
    Request::is('privacy-policy')
)
        @yield('content')
        @include('labels')
    @else
        <div class="layout-wrapper layout-content-navbar">
            <div class="layout-container">
                <!-- Menu -->
                @authBoth
                <x-menu />
                <!-- Layout container -->
                <div class="layout-page">
                    @include('partials._navbar')
                    <!-- Content wrapper -->
                    <div class="content-wrapper">
                        @include('labels')
                        @yield('content')
                        @if (!isset($unauthorized))
                            @include('modals')
                            @include('offcanvas')
                        @endif
                    </div>
                    <!-- Content wrapper -->
                    <!-- footer -->
                    <x-footer />
                    <!-- / footer -->
                @else
                    <div class="container-fluid container-p-y">
                        <div class="misc-wrapper d-flex flex-column align-items-center justif-content-center">
                            <h2 class="mx-2 mb-2"><?= get_label('session_expired', 'Session expired') ?>!!!</h2>
                            <div class="my-5">
                                <img src="../assets/img/illustrations/page-misc-error-light.png"
                                    alt="page-misc-error-light" width="500" class="img-fluid"
                                    data-app-dark-img="illustrations/page-misc-error-dark.png"
                                    data-app-light-img="illustrations/page-misc-error-light.png" />
                            </div>
                            <a href="{{ url('') }}"
                                class="btn btn-primary"><?= get_label('log_in', 'Log in') ?></a>
                        </div>
                    </div>
                @endauth
            </div>
            <!-- / Layout page -->
        </div>
        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <script>
        var manage_notifications = '<?= getAuthenticatedUser()->can('manage_system_notifications') ? 'true' : 'false' ?>';
    </script>
@endif
<!-- / Layout wrapper -->
@if (Auth::guard('web')->check())
    <div onclick="toggleChatIframe()">
        <span>
            <a href="javascript:void(0);">
                <img src="{{ asset('storage/chat-icon.png') }}" class="chat-img" alt="Chat">
            </a>
        </span>
    </div>
    <div id="chatIframeContainer" class="chat-iframe-container">
        <iframe src="{{ url('chat') }}" frameborder="0" class="chat-iframe"></iframe>
    </div>
@endif
<script src="{{ asset('assets/js/time-tracker.js') }}"></script>
@if (getAuthenticatedUser() && getAuthenticatedUser()->can('create_timesheet'))
    <!-- Timer image -->
    <div onclick="open_timer_section()">
        <span>
            <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="left"
                data-bs-original-title="<?= get_label('time_tracker', 'Time tracker') ?>">
                <img src="{{ asset('storage/94150-clock.png') }}" class="timer-img" id="timer-image"
                    alt="Timer" data-bs-toggle="modal" data-bs-target="#timerModal">
            </a>
        </span>
    </div>
@endif


@if(config('constants.ALLOW_MODIFICATION') == '0')
<!-- Floating Buy Now Button -->
   @include('components.taskify-ecosystem-drawer')

@endif


<!-- Core JS -->
<!-- build:js assets/vendor/js/core.js -->
<script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
<script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
<script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
<!-- endbuild -->
<!-- Main JS -->
<script src="{{ asset('assets/js/main.js') }}"></script>
<script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
<!-- Place this tag in your head or just before your close body tag. -->
<script async defer src="{{ asset('assets/js/buttons.js') }}"></script>
<!-- select 2 js !-->
<script src="{{ asset('assets/js/select2.min.js') }}"></script>
<script>
    $(document).ready(function() {
        // Initialize Select2 on elements with the class 'js-example-basic-multiple'
        $('.js-example-basic-multiple').each(function() {
            var $this = $(this);
            var $parentModal = $this.closest('.modal');

            // Determine the allowClear setting
            var allowClear = $this.data('allow-clear') === "true";

            var isMultiple = $this.prop('multiple');

            var select2Options = {
                allowClear: allowClear,
                closeOnSelect: !isMultiple,
                language: {
                    noResults: function() {
                        return label_no_results_found;
                    },
                    searching: function() {
                        return label_searching;
                    }
                }
            };

            if ($parentModal.length) {
                // If inside a modal, set dropdownParent to the closest modal
                select2Options.dropdownParent = $parentModal;
            }

            // Initialize Select2 with the options
            $this.select2(select2Options);
            $('.cancel-button').on('click', function() {
                $this.select2('close'); // Close the dropdown
            });

        });
    });
</script>
<!-- Bootstrap-table -->
<script src="{{ asset('assets/js/bootstrap-table/bootstrap-table.min.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap-table/bootstrap-table-export.min.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap-table/tableExport.min.js') }}"></script>
<!-- Dragula -->
<script src="{{ asset('assets/js/dragula.min.js') }}"></script>
<script src="{{ asset('assets/js/popper.js') }}"></script>
<!-- Toastr -->
<script src="{{ asset('assets/js/toastr.min.js') }}"></script>
<script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
<script src="{{ asset('assets/js/ui-popover.js') }}"></script>
<script src="{{ asset('assets/js/tinymce.min.js') }}"></script>
<script src="{{ asset('assets/js/tinymce-jquery.min.js') }}"></script>
<!-- Date picker -->
<script src="{{ asset('assets/js/moment.min.js') }}"></script>
<script src="{{ asset('assets/js/daterangepicker.js') }}"></script>
<script src="{{ asset('assets/lightbox/lightbox.min.js') }}"></script>
<script src="{{ asset('assets/js/dropzone.min.js') }}"></script>
<script src="{{ asset('assets/js/extended-ui-perfect-scrollbar.js') }}"></script>
<script src="{{ asset('assets/js/fullcalendar/core/main.js') }}"></script>
<script src="{{ asset('assets/js/fullcalendar/interaction/main.js') }}"></script>
<script src="{{ asset('assets/js/fullcalendar/daygrid/main.js') }}"></script>
<script src="{{ asset('assets/js/fullcalendar/list/main.js') }}"></script>
<script src="{{ asset('assets/js/fullcalendar/google-calendar/main.js') }}"></script>

<!-- Js Draw -->
<script src="{{ asset('assets/js/js-draw.js') }}"></script>

@if (Request::is('projects/mind-map/*'))
    <script src="{{ asset('assets/js/mind-map.js') }}"></script>
    <script src="{{ asset('assets/js/dom-to-image.js') }}"></script>

    <!-- screenshot plugin -->
    <script src="{{ asset('assets/js/mindmap-screenshot.js') }}"></script>
@endif
@if (Request::is('preferences'))
    <script src="{{ asset('assets/js/Sortable.min.js') }}"></script>
@endif

@if (Request::is('projects/gantt-chart') || Request::is('projects/gantt-chart/favorite'))
    <script src="{{ asset('assets/js/frappe-gantt.js') }}"></script>
@endif
<script src="{{ asset('assets/js/tribute.js') }}"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script>

<script>
    // const firebaseConfig = {
    //     apiKey: "AIzaSyACyBtmMz5PO_yQ_V3GTCRB9mlrP99ctN8",
    //     authDomain: "taskify-210db.firebaseapp.com",
    //     databaseURL: "https://taskify-210db-default-rtdb.firebaseio.com",
    //     projectId: "taskify-210db",
    //     storageBucket: "taskify-210db.appspot.com",
    //     messagingSenderId: "208242375433",
    //     appId: "1:208242375433:web:cfaaeb51e3417c03e131d8",
    //     measurementId: "G-TN9S72E85S"
    // };

    // // Initialize Firebase
    // firebase.initializeApp(firebaseConfig);
    // const messaging = firebase.messaging();

    // messaging.getToken().then((currentToken) => {
    //     if (currentToken) {
    //         console.log('FCM Token:', currentToken);
    //     } else {
    //         console.log('No registration token available. Request permission to generate one.');
    //     }
    // }).catch((err) => {
    //     console.log('An error occurred while retrieving token. ', err);
    // });
</script>

@authBoth
<script>
    var authUserId = '<?= getAuthenticatedUser()->id ?>';
    var isAdminOrHasAllDataAccess = '<?= isAdminOrHasAllDataAccess() ?>';
    var canManageUsers = '<?= checkPermission('manage_users') ?>';
    var canManageClients = '<?= checkPermission('manage_clients') ?>';
    var guard = '<?= getGuardName() ?>';
</script>
@endauth
@php
    $user = getAuthenticatedUser();
    $permissions = $user ? $user->getPermissionsViaRoles() : collect();
@endphp
<script>
    var permissions = @json($permissions->pluck('name'));
</script>
<!-- Custom js -->
<script>
    var csrf_token = '{{ csrf_token() }}';
    var js_date_format = '{{ $js_date_format ?? 'YYYY-MM-DD' }}';
</script>
@role('admin')
<script>
    var isAdmin = 1;
</script>
@endrole
@php
    $allowedMaxUploadSize = isset($general_settings['allowed_max_upload_size'])
        ? $general_settings['allowed_max_upload_size']
        : 512;
    $maxFilesAllowed = isset($general_settings['max_files_allowed']) ? $general_settings['max_files_allowed'] : 10;
    $allowedFileTypes = isset($general_settings['allowed_file_types'])
        ? $general_settings['allowed_file_types']
        : '.png,.jpg,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt';
    $allowedFileTypes = str_replace(' ', '', $allowedFileTypes);
@endphp
<script>
    var toastTimeOut = <?= isset($general_settings['toast_time_out']) ? $general_settings['toast_time_out'] : 5 ?>;
    var toastPosition =
        <?= isset($general_settings['toast_position']) ? "'" . $general_settings['toast_position'] . "'" : "'toast-top-right'" ?>;
    var allowedMaxFilesize = <?= $allowedMaxUploadSize ?>;
    var allowedMaxFilesizeFormatted = '<?= formatSize(1024 * 1024 * $allowedMaxUploadSize) ?>';
    var maxFilesAllowed = <?= $maxFilesAllowed ?>;
    var allowedFileTypes = '<?= $allowedFileTypes ?>';
</script>

<script src="{{ asset('assets/js/custom.js') }}"></script>
@if (session()->has('message'))
<script>
    toastr.options = {
        "positionClass": toastPosition,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": parseFloat(toastTimeOut) * 1000,
        "progressBar": true,
        "extendedTimeOut": "1000",
        "closeButton": true
    };
    toastr.success('{{ session('message') }}', 'Success');
</script>
@elseif(session()->has('error'))
<script>
    toastr.options = {
        "positionClass": toastPosition,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": parseFloat(toastTimeOut) * 1000,
        "progressBar": true,
        "extendedTimeOut": "1000",
        "closeButton": true
    };
    toastr.error('{{ session('error') }}', 'Error');
</script>
@elseif(session()->has('info'))
<script>
    toastr.options = {
        "positionClass": toastPosition,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": parseFloat(toastTimeOut) * 1000,
        "progressBar": true,
        "extendedTimeOut": "1000",
        "closeButton": true
    };
    toastr.info('{{ session('info') }}', 'Info');
</script>
@endif

</body>

</html>
