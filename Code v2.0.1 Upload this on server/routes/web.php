<?php

use App\Models\Project;
use App\Models\ActivityLog;
use App\Http\Middleware\Authorize;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\TagsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ItemsController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\TasksController;
use App\Http\Controllers\TaxesController;
use App\Http\Controllers\TodosController;
use App\Http\Controllers\UnitsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\UpdaterController;
use App\Http\Controllers\ExpensesController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LeadFormController;
use App\Http\Controllers\MeetingsController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PayslipsController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskListController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\ContractsController;
use App\Http\Controllers\EmailSendController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\LeadStageController;
use App\Http\Middleware\CustomRoleMiddleware;
use App\Http\Controllers\AllowancesController;
use App\Http\Controllers\DeductionsController;
use App\Http\Controllers\LeadImportController;
use App\Http\Controllers\LeadSourceController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\WorkspacesController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\SignUpController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\PwaManifestController;
use App\Http\Controllers\PwaSettingsController;
use App\Http\Controllers\TimeTrackerController;
use App\Http\Controllers\LeadFollowUpController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PluginManagerController;
use App\Http\Controllers\TaskTimeEntryController;
use Spatie\Permission\Middlewares\RoleMiddleware;
use App\Http\Controllers\PaymentMethodsController;
use App\Http\Controllers\CandidateStatusController;
use App\Http\Controllers\PluginInstallerController;
use App\Http\Controllers\EstimatesInvoicesController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use Spatie\Permission\Middlewares\PermissionMiddleware;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


//---------------------------------------------------------------
/*
Route::get('/update-test', function () {
    $updatePath = Config::get('constants.UPDATE_PATH');
    $sub_directory = (file_exists($updatePath . "plugin/package.json")) ? "plugin/" : "";
    $package_data = file_get_contents($updatePath . $sub_directory . "package.json");
    $package_data = json_decode($package_data, true);
});
*/

Route::get('/test-calendar', function () {
    return view('calendars.index');
});
Route::get('/phpinfo', function () {
    phpinfo();
});


Route::get('/generate-api-doc', function () {
    Artisan::call('scribe:generate');
    return response()->json(['message' => 'API Documentation generated successfully!']);
});
Route::get('/migrate', function () {
    Artisan::call('migrate', ['--path' => 'database/migrations/2025_06_13_102308_add_custom_fields_to_leads_table.php']);
    return redirect('/home')->with('message', 'Database Migrated Successfully.');
});


Route::get('/clear-cache', function () {
    Artisan::call('optimize:clear');
    return redirect('/home')->with('message', 'System Cache Cleared Successfully.');
});
Route::get('/privacy-policy', function () {
    return view('settings.privacy_policy_open');
});

Route::get('/create-symlink', function () {
    if (config('constants.ALLOW_MODIFICATION') === 1) {
        $storageLinkPath = public_path('storage');
        if (is_dir($storageLinkPath)) {
            File::deleteDirectory($storageLinkPath);
        }
        Artisan::call('storage:link');

        return redirect('/home')->with('message', 'Symbolik link created successfully.');
    } else {
        return redirect('/home')->with('message', 'This operation is not allowed in demo mode.');
    }
});


Route::get('/install', [InstallerController::class, 'index'])->middleware('guest');

Route::post('/installer/config-db', [InstallerController::class, 'config_db'])->middleware('guest')->name('installer.config-db');

Route::post('/installer/install', [InstallerController::class, 'install'])->middleware('guest')->name('installer.install');

Route::get('/meetings/join/web-view/{id}', [MeetingsController::class, 'joinWebView']);




Route::middleware(['CheckInstallation'])->group(function () {

    Route::get('/', [UserController::class, 'login'])->name('login')->middleware('guest');

    Route::post('/users/authenticate', [UserController::class, 'authenticate'])->middleware('customThrottle');

    Route::get('/signup', [SignUpController::class, 'index'])->middleware(['guest', 'checkSignupEnabled']);

    Route::post('/create-account', [SignUpController::class, 'create_account'])->middleware(['guest', 'checkSignupEnabled']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->middleware('guest');

    Route::post('/forgot-password-mail', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('guest');

    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetPasswordForm'])->middleware('guest')->name('password.reset');

    Route::post('/reset-password', [ForgotPasswordController::class, 'ResetPassword'])->middleware('guest')->name('password.update');

    Route::get('/email/verify', [UserController::class, 'email_verification'])->name('verification.notice')->middleware(['auth:web,client']);

    Route::get('/email/verify/{id}/{hash}', [ClientController::class, 'verify_email'])->middleware(['auth:web,client', 'custom.signature'])->name('verification.verify');

    Route::get('/email/verification-notification', [UserController::class, 'resend_verification_link'])->middleware(['auth:web,client', 'throttle:6,1'])->name('verification.send');

    Route::post('/logout', [UserController::class, 'logout'])->middleware(['multiguard']);

    Route::get("settings/languages/switch/{code}", [LanguageController::class, 'switch'])->middleware(['multiguard']);

    Route::prefix('forms')->group(function () {
        Route::get('{slug}', [PublicFormController::class, 'show'])->name('public.form');
        Route::post('{slug}', [PublicFormController::class, 'submit'])->name('public.form.submit');
        Route::get('{slug}/json', [PublicFormController::class, 'json'])->name('public.form.json');
        Route::get('/form/submitted', function () {
            return view('lead_form.submitted');
        })->name('lead_form.submitted');
    });
    Route::middleware(['auth'])->group(function () {
        Route::get('/system-health', [App\Http\Controllers\SystemHealthController::class, 'healthCheck'])->name('system.health');
        Route::post('/system-health/validate', [App\Http\Controllers\SystemHealthController::class, 'validateHealth'])->name('system.validate');
        Route::get('/system-check/{key}', [App\Http\Controllers\SystemHealthController::class, 'checkPurchaseCode'])
            ->name('system.purchase.check');
    });
    // ,'custom-verified'
    Route::middleware(['multiguard', 'custom-verified', 'system.check'])->group(function () {


        Route::get('/home', [HomeController::class, 'index'])->name('home.index');

        Route::get('/home/upcoming-birthdays', [HomeController::class, 'upcoming_birthdays']);

        Route::get('/home/upcoming-work-anniversaries', [HomeController::class, 'upcoming_work_anniversaries']);

        Route::get('/home/members-on-leave', [HomeController::class, 'members_on_leave']);

        Route::get('/home/upcoming-birthdays-calendar', [HomeController::class, 'upcoming_birthdays_calendar']);

        Route::get('/home/upcoming-work-anniversaries-calendar', [HomeController::class, 'upcoming_work_anniversaries_calendar']);

        Route::get('/home/members-on-leave-calendar', [HomeController::class, 'members_on_leave_calendar']);

        //Projects--------------------------------------------------------

        Route::middleware(['has_workspace'])->group(function () {
            Route::middleware(['customcan:manage_projects'])->group(function () {

                Route::get('/projects/{type?}', [ProjectsController::class, 'index'])->where('type', 'favorite')->name('projects.index');

                Route::get('/projects/list/{type?}', [ProjectsController::class, 'list_view'])->where('type', 'favorite')->name('projects.list');

                Route::get('/projects/kanban/{type?}', [ProjectsController::class, 'kanban_view'])->where('type', 'favorite')->name('projects.kanban_view');

                Route::get('/projects/information/{id}', [ProjectsController::class, 'show'])->middleware(['checkAccess:App\Models\Project,projects,id,projects'])->name('projects.info');

                Route::get('/projects/mind-map/{id}', [ProjectsController::class, 'mind_map'])->name('projects.mind_map');

                Route::any('/mind-map/export-mindmap/{id}', [ProjectsController::class, 'export_mindmap'])->name('projects.export_mindmap');

                Route::get('/projects/gantt-chart/{type?}', [ProjectsController::class, 'ganttChartView'])->where('type', 'favorite')->name('projects.gantt_chart');

                Route::get('/projects/fetch-gantt-data', [ProjectsController::class, 'ganttProjectsTasks']);

                Route::post('projects/gantt-chart-view/update-module-dates', [ProjectsController::class, 'update_module_dates'])->name('projects.update_module_dates');

                Route::post('/projects/store', [ProjectsController::class, 'store'])->middleware(['customcan:create_projects', 'log.activity'])->name('projects.store');

                Route::get('/projects/bulk-upload', [ProjectsController::class, 'showBulkUploadForm'])->middleware(['customcan:create_projects'])->name('projects.showBulkUploadForm');

                Route::post('/projects/process-bulk-upload', [ProjectsController::class, 'importBulkProjects'])->middleware(['customcan:create_projects'])->name('projects.bulkUpload');

                Route::get('/projects/get/{id}', [ProjectsController::class, 'get'])->middleware(['checkAccess:App\Models\Project,projects,id,projects'])->name('project.get');

                Route::post('/projects/update', [ProjectsController::class, 'update'])
                    ->middleware(['customcan:edit_projects', 'log.activity']);

                Route::delete('/projects/destroy/{id}', [ProjectsController::class, 'destroy'])
                    ->middleware(['customcan:delete_projects', 'demo_restriction', 'checkAccess:App\Models\Project,projects,id,projects', 'log.activity']);

                Route::post('/projects/destroy_multiple', [ProjectsController::class, 'destroy_multiple'])
                    ->middleware(['customcan:delete_projects', 'demo_restriction', 'log.activity']);

                Route::get('/projects/listing/{id?}', [ProjectsController::class, 'list']);

                Route::patch('/projects/update-favorite/{id}', [ProjectsController::class, 'update_favorite']);

                Route::patch('/projects/update-pinned/{id}', [ProjectsController::class, 'update_pinned']);

                Route::get('/projects/duplicate/{id}', [ProjectsController::class, 'duplicate'])
                    ->middleware(['customcan:create_projects', 'checkAccess:App\Models\Project,projects,id,projects', 'log.activity']);

                Route::get('/projects/tasks/list/{id}', [TasksController::class, 'index'])
                    ->middleware(['customcan:manage_tasks', 'checkAccess:App\Models\Project,projects,id,projects'])->name('projects.tasks.index');

                Route::get('/projects/tasks/draggable/{id}', [TasksController::class, 'dragula'])
                    ->middleware(['customcan:manage_tasks', 'checkAccess:App\Models\Project,projects,id,projects'])->name('projects.tasks.draggable');

                Route::get('/projects/tasks/calendar/{id}', [TasksController::class, 'calendar'])
                    ->middleware(['customcan:manage_tasks', 'checkAccess:App\Models\Project,projects,id,projects']);

                Route::post('update-project-status', [ProjectsController::class, 'update_status'])
                    ->middleware(['customcan:edit_projects', 'log.activity']);

                Route::post('update-project-priority', [ProjectsController::class, 'update_priority'])
                    ->middleware(['customcan:edit_projects', 'log.activity']);
                Route::put('/save-projects-view-preference', [ProjectsController::class, 'saveViewPreference']);

                Route::middleware(['customcan:manage_media'])->group(function () {

                    Route::post('/projects/upload-media', [ProjectsController::class, 'upload_media'])
                        ->middleware(['customcan:create_media', 'log.activity', 'validate.upload.media']);

                    Route::get('/projects/get-media/{id}', [ProjectsController::class, 'get_media'])->name('projects.get_media');

                    Route::delete('/projects/delete-media/{id}', [ProjectsController::class, 'delete_media'])
                        ->middleware(['customcan:delete_media', 'log.activity']);

                    Route::post('/projects/delete-multiple-media', [ProjectsController::class, 'delete_multiple_media'])
                        ->middleware(['customcan:delete_media', 'log.activity']);
                });
                Route::post('/projects/information/{id}/comments', [ProjectsController::class, 'comments'])->name('comments.store');

                Route::get('/projects/comments/get/{id}', [ProjectsController::class, 'get_comment'])->name('comments.get');

                Route::post('/projects/comments/update', [ProjectsController::class, 'update_comment'])->name('comments.update');

                Route::delete('/projects/comments/destroy', [ProjectsController::class, 'destroy_comment'])->name('comments.destroy');

                Route::any('/projects/comments/destroy-attachment/{id}', [ProjectsController::class, 'destroy_comment_attachment'])->name('comments.destroy_attachment');

                Route::get('/reports/projects', [ReportsController::class, 'showProjectReport'])->name('reports.projects');
                Route::get('/reports/projects-report-data', [ReportsController::class, 'getProjectReportData'])->name('reports.project-report-data');
                Route::get('/reports/export-projects-report', [ReportsController::class, 'exportProjectReport'])->name('reports.export-projects-report');


                // Calendar View For Projects
                Route::get('/projects/calendar-view', [ProjectsController::class, 'calendar_view'])->name('projects.calendar_view');
                Route::get('/projects/get-calendar-data', [ProjectsController::class, 'get_calendar_data'])->name('projects.get_calendar_data');
                Route::patch('/projects/update-dates', [ProjectsController::class, 'updateProjectDates']);

                // Get Status and Priority

                Route::get('/projects/get-statuses', [ProjectsController::class, 'getStatuses'])->name('projects.getStatusesAjax');
                Route::get('/projects/get-priorities', [ProjectsController::class, 'getPriorities'])->name('projects.getPrioritiesAjax');
            });

            Route::middleware(['customcan:manage_tags'])->group(function () {
                Route::get('/tags/manage', [TagsController::class, 'index']);
                Route::post('/tags/store', [TagsController::class, 'store'])->middleware(['customcan:create_tags', 'log.activity']);
                Route::get('/tags/list', [TagsController::class, 'list']);
                Route::get('/tags/get/{id}', [TagsController::class, 'get']);
                Route::post('/tags/update', [TagsController::class, 'update'])->middleware(['customcan:edit_tags', 'log.activity']);
                Route::delete('/tags/destroy/{id}', [TagsController::class, 'destroy'])->middleware(['customcan:delete_tags', 'demo_restriction', 'log.activity']);
                Route::post('/tags/destroy_multiple', [TagsController::class, 'destroy_multiple'])->middleware(['customcan:delete_tags', 'demo_restriction', 'log.activity']);
            });

            Route::middleware(['customcan:manage_statuses'])->group(function () {
                Route::get('/status/manage', [StatusController::class, 'index']);
                Route::post('/status/store', [StatusController::class, 'store'])->middleware(['customcan:create_statuses', 'demo_restriction', 'log.activity']);
                Route::get('/status/list', [StatusController::class, 'list']);
                Route::post('/status/update', [StatusController::class, 'update'])->middleware(['customcan:edit_statuses', 'demo_restriction', 'log.activity']);
                Route::get('/status/get/{id}', [StatusController::class, 'get']);
                Route::delete('/status/destroy/{id}', [StatusController::class, 'destroy'])->middleware(['customcan:delete_statuses', 'demo_restriction', 'log.activity']);
                Route::post('/status/destroy_multiple', [StatusController::class, 'destroy_multiple'])->middleware(['customcan:delete_statuses', 'demo_restriction', 'log.activity']);
            });
            Route::middleware(['customcan:manage_priorities'])->group(function () {
                Route::get('/priority/manage', [PriorityController::class, 'index']);
                Route::post('/priority/store', [PriorityController::class, 'store'])->middleware(['customcan:create_priorities', 'demo_restriction', 'log.activity']);
                Route::get('/priority/list', [PriorityController::class, 'list']);
                Route::post('/priority/update', [PriorityController::class, 'update'])->middleware(['customcan:edit_priorities', 'demo_restriction', 'log.activity']);
                Route::get('/priority/get/{id}', [PriorityController::class, 'get']);
                Route::delete('/priority/destroy/{id}', [PriorityController::class, 'destroy'])->middleware(['customcan:delete_priorities', 'demo_restriction', 'log.activity']);
                Route::post('/priority/destroy_multiple', [PriorityController::class, 'destroy_multiple'])->middleware(['customcan:delete_priorities', 'demo_restriction', 'log.activity']);
            });

            Route::middleware(['customcan:manage_milestones'])->group(function () {
                Route::post('/projects/store-milestone', [ProjectsController::class, 'store_milestone'])->middleware(['customcan:create_milestones', 'log.activity']);
                Route::get('/projects/get-milestones/{id}', [ProjectsController::class, 'get_milestones'])->name('projects.get_milestones');
                Route::get('/projects/get-milestone/{id}', [ProjectsController::class, 'get_milestone']);
                Route::post('/projects/update-milestone', [ProjectsController::class, 'update_milestone'])->middleware(['customcan:edit_milestones', 'log.activity']);
                Route::delete('/projects/delete-milestone/{id}', [ProjectsController::class, 'delete_milestone'])->middleware(['customcan:edit_milestones', 'demo_restriction', 'log.activity']);
                Route::post('/projects/delete-multiple-milestone', [ProjectsController::class, 'delete_multiple_milestone'])->middleware(['customcan:edit_milestones', 'demo_restriction', 'log.activity']);
            });

            Route::prefix('/task-lists')->group(function () {
                Route::get('/', [TaskListController::class, 'index'])->name('task_lists.index');
                Route::get('/list', [TaskListController::class, 'list'])->name('task_lists.list');
                Route::post('/store', [TaskListController::class, 'store'])->name('task_lists.store');
                Route::get('/get/{id}', [TaskListController::class, 'get'])->name('task_lists.get');
                Route::put('/update', [TaskListController::class, 'update'])->name('task_lists.update');
                Route::delete('/destroy/{id}', [TaskListController::class, 'destroy'])->name('task_lists.destroy');
                Route::delete('/destroy_multiple', [TaskListController::class, 'destroy_multiple'])->name('task_lists.destroy_multiple');
                Route::get('/search', [TaskListController::class, 'searchTaskLists'])->name('task-lists.search');
            });
            //Tasks-------------------------------------------------------------

            Route::middleware(['customcan:manage_tasks'])->group(function () {

                Route::get('/tasks', [TasksController::class, 'index'])->name('tasks.index');
                Route::get('/tasks/group-by-task-list', [TasksController::class, 'group_by_task_list'])->name('tasks.groupByTaskList');;

                Route::get('/tasks/information/{id}', [TasksController::class, 'show'])
                    ->middleware(['checkAccess:App\Models\Task,tasks,id,tasks'])->name('tasks.info');

                Route::post('/tasks/information/{id}/comments', [TasksController::class, 'comments'])->name('tasks.comments.store');

                Route::get('/tasks/comments/get/{id}', [TasksController::class, 'get_comment'])->name('tasks.comments.get');

                Route::post('/tasks/comments/update', [TasksController::class, 'update_comment'])->name('tasks.comments.update');

                Route::delete('/tasks/comments/destroy', [TasksController::class, 'destroy_comment'])->name('tasks.comments.destroy');

                Route::post('/tasks/store', [TasksController::class, 'store'])
                    ->middleware(['customcan:create_tasks', 'log.activity']);

                Route::get('/tasks/bulk-upload', [TasksController::class, 'showBulkUploadForm'])->middleware(['customcan:create_tasks'])->name('tasks.showBulkUploadForm');

                Route::post('/tasks/process-bulk-upload', [TasksController::class, 'importBulkTasks'])->middleware(['customcan:create_tasks'])->name('tasks.bulkUpload');

                Route::get('/tasks/duplicate/{id}', [TasksController::class, 'duplicate'])
                    ->middleware(['customcan:create_tasks', 'checkAccess:App\Models\Task,tasks,id,tasks', 'log.activity']);

                Route::get('/tasks/get/{id}', [TasksController::class, 'get'])->middleware(['checkAccess:App\Models\Task,tasks,id,tasks'])->name('task.get');

                Route::post('/tasks/update', [TasksController::class, 'update'])
                    ->middleware(['customcan:edit_tasks', 'log.activity']);

                Route::delete('/tasks/destroy/{id}', [TasksController::class, 'destroy'])
                    ->middleware(['customcan:delete_tasks', 'demo_restriction', 'checkAccess:App\Models\Task,tasks,id,tasks', 'log.activity']);

                Route::post('/tasks/destroy_multiple', [TasksController::class, 'destroy_multiple'])->middleware(['customcan:delete_tasks', 'demo_restriction', 'log.activity']);

                Route::get('/tasks/list/{id?}', [TasksController::class, 'list']);

                Route::patch('/tasks/update-favorite/{id}', [TasksController::class, 'update_favorite']);

                Route::patch('/tasks/update-pinned/{id}', [TasksController::class, 'update_pinned']);

                Route::get('/tasks/draggable', [TasksController::class, 'dragula'])->name('tasks.draggable');

                Route::get('/tasks/calendar', [TasksController::class, 'calendar'])->name('tasks.calendar_view');

                Route::get('tasks/get-calendar-data', [TasksController::class, 'get_calendar_data'])->name('tasks.get_calendar_data');

                Route::patch('/tasks/update-dates', [TasksController::class, 'updateTaskDates']);

                Route::put('/tasks/{id}/update-status/{status}', [TasksController::class, 'updateStatus'])->middleware(['customcan:edit_tasks', 'log.activity']);

                Route::post('update-task-status', [TasksController::class, 'update_status'])
                    ->middleware(['customcan:edit_tasks', 'log.activity']);

                Route::post('update-task-priority', [TasksController::class, 'update_priority'])
                    ->middleware(['customcan:edit_tasks', 'log.activity']);

                Route::put('/save-tasks-view-preference', [TasksController::class, 'saveViewPreference']);

                Route::middleware(['customcan:manage_media'])->group(function () {

                    Route::post('/tasks/upload-media', [TasksController::class, 'upload_media'])
                        ->middleware(['customcan:create_media', 'log.activity', 'validate.upload.media']);

                    Route::get('/tasks/get-media/{id}', [TasksController::class, 'get_media']);

                    Route::delete('/tasks/delete-media/{id}', [TasksController::class, 'delete_media'])
                        ->middleware(['customcan:delete_media', 'log.activity']);

                    Route::post('/tasks/delete-multiple-media', [TasksController::class, 'delete_multiple_media'])
                        ->middleware(['customcan:delete_media', 'log.activity']);
                });
                Route::get('/reports/tasks', [ReportsController::class, 'showTaskReport'])->name('reports.tasks');
                Route::get('/reports/tasks-report-data', [ReportsController::class, 'getTaskReportData'])->name('reports.tasks-report-data');
                Route::get('/reports/export-tasks-report', [ReportsController::class, 'exportTaskReport'])->name('reports.export-tasks-report');

                //Tasks Time Entries
                Route::get('/tasks/time-entries/list/{id}', [TaskTimeEntryController::class, 'list'])->name('tasks.time_entries.list');
                Route::post('/tasks/time-entries/store', [TaskTimeEntryController::class, 'store'])->name('tasks.time_entries.store');
                Route::any('/tasks/time-entries/destroy/{id}', [TaskTimeEntryController::class, 'destroy'])->name('tasks.time_entries.destroy');
                Route::any('/tasks/time-entries/destroy_multiple', [TaskTimeEntryController::class, 'destroy_multiple'])->name('tasks.time_entries.destroy_multiple');
            });


            //Meetings-------------------------------------------------------------
            Route::middleware(['customcan:manage_meetings'])->group(function () {

                Route::get('/meetings', [MeetingsController::class, 'index'])->name('meetings.index');

                Route::post('/meetings/store', [MeetingsController::class, 'store'])->middleware(['customcan:create_meetings', 'log.activity']);

                Route::get('/meetings/list', [MeetingsController::class, 'list']);

                Route::get('/meetings/get/{id}', [MeetingsController::class, 'get'])->middleware(['checkAccess:App\Models\Meeting,meetings,id,meetings'])->name('meeting.get');

                Route::post('/meetings/update', [MeetingsController::class, 'update'])
                    ->middleware(['customcan:edit_meetings', 'log.activity']);

                Route::delete('/meetings/destroy/{id}', [MeetingsController::class, 'destroy'])
                    ->middleware(['customcan:delete_meetings', 'demo_restriction', 'checkAccess:App\Models\Meeting,meetings,id,meetings', 'log.activity']);

                Route::post('/meetings/destroy_multiple', [MeetingsController::class, 'destroy_multiple'])
                    ->middleware(['customcan:delete_meetings', 'demo_restriction', 'log.activity']);

                Route::get('/meetings/join/{id}', [MeetingsController::class, 'join'])
                    ->middleware(['checkAccess:App\Models\Meeting,meetings,id,meetings'])->name('meetings.join');

                Route::get('/meetings/duplicate/{id}', [MeetingsController::class, 'duplicate'])
                    ->middleware(['customcan:create_meetings', 'checkAccess:App\Models\Meeting,meetings,id,meetings', 'log.activity']);

                Route::get('/meetings/calendar-view', [MeetingsController::class, 'calendar_view'])->name('meetings.calendar-view');

                Route::get('/meetings/get-calendar-data', [MeetingsController::class, 'get_calendar_data'])->name('meetings.get_calendar_data');
                Route::put('/save-meetings-view-preference', [MeetingsController::class, 'saveViewPreference']);
            });

            //Workspaces-------------------------------------------------------------
            Route::middleware(['customcan:manage_workspaces'])->group(function () {

                Route::get('/workspaces', [WorkspacesController::class, 'index'])->name('workspaces.index');

                Route::post('/workspaces/store', [WorkspacesController::class, 'store'])->middleware(['customcan:create_workspaces', 'log.activity']);

                Route::get('/workspaces/duplicate/{id}', [WorkspacesController::class, 'duplicate'])
                    ->middleware(['customcan:create_workspaces', 'checkAccess:App\Models\Workspace,workspaces,id,workspaces', 'log.activity']);

                Route::get('/workspaces/list', [WorkspacesController::class, 'list']);

                Route::get('/workspaces/get/{id}', [WorkspacesController::class, 'get'])->middleware(['checkAccess:App\Models\Workspace,workspaces,id,workspaces'])->name('workspace.get');

                Route::post('/workspaces/update', [WorkspacesController::class, 'update'])
                    ->middleware(['customcan:edit_workspaces', 'demo_restriction', 'log.activity']);

                Route::delete('/workspaces/destroy/{id}', [WorkspacesController::class, 'destroy'])
                    ->middleware(['customcan:delete_workspaces', 'demo_restriction', 'checkAccess:App\Models\Workspace,workspaces,id,workspaces', 'log.activity']);

                Route::post('/workspaces/destroy_multiple', [WorkspacesController::class, 'destroy_multiple'])
                    ->middleware(['customcan:delete_workspaces', 'demo_restriction', 'log.activity']);

                Route::get('/workspaces/switch/{id}', [WorkspacesController::class, 'switch'])
                    ->middleware(['checkAccess:App\Models\Workspace,workspaces,id,workspaces']);
            });
            Route::patch('workspaces/{id}/default', [WorkspacesController::class, 'setDefaultWorkspace'])->middleware(['demo_restriction']);

            Route::get('/workspaces/remove_participant', [WorkspacesController::class, 'remove_participant'])->middleware(['demo_restriction']);

            //Todos-------------------------------------------------------------
            Route::get('/todos', [TodosController::class, 'index']);

            Route::post('/todos/store', [TodosController::class, 'store'])->middleware(['log.activity']);

            Route::post('/todos/update', [TodosController::class, 'update'])->name('todos.update')->middleware(['log.activity']);

            Route::put('/todos/update_status', [TodosController::class, 'update_status'])->middleware(['log.activity']);

            Route::delete('/todos/destroy/{id}', [TodosController::class, 'destroy'])->middleware(['demo_restriction', 'log.activity']);

            Route::post('/todos/destroy_multiple', [TodosController::class, 'destroy_multiple'])->middleware(['demo_restriction', 'log.activity']);

            Route::get('/todos/get/{id}', [TodosController::class, 'get']);


            Route::get('/notes', [NotesController::class, 'index']);

            Route::post('/notes/store', [NotesController::class, 'store'])->middleware('log.activity');

            Route::post('/notes/update', [NotesController::class, 'update'])->middleware('log.activity');

            Route::get('/notes/get/{id}', [NotesController::class, 'get']);

            Route::delete('/notes/destroy/{id}', [NotesController::class, 'destroy'])->middleware(['demo_restriction', 'log.activity']);

            Route::post('/notes/destroy_multiple', [NotesController::class, 'destroy_multiple'])
                ->middleware(['demo_restriction', 'log.activity']);

            //Users-------------------------------------------------------------

            Route::get('account/{user}', [ProfileController::class, 'show'])->name('profile.show');

            Route::put('/profile/update_photo', [ProfileController::class, 'update_photo'])->middleware(['demo_restriction']);

            Route::put('profile/update/{userOrClient}', [ProfileController::class, 'update'])->name('profile.update')->middleware(['demo_restriction']);

            Route::delete('/account/destroy', [ProfileController::class, 'destroy'])->middleware(['demo_restriction']);

            Route::middleware(['has_workspace', 'customcan:manage_users'])->group(function () {

                Route::get('/users', [UserController::class, 'index']);

                Route::get('/users/create', [UserController::class, 'create'])->middleware(['customcan:create_users']);

                Route::post('/users/store', [UserController::class, 'store'])->middleware(['customcan:create_users', 'log.activity']);

                Route::get('/users/bulk-upload', [UserController::class, 'showBulkUploadForm'])->middleware(['customcan:create_users'])->name('users.showBulkUploadForm');

                Route::post('/users/process-bulk-upload', [UserController::class, 'importBulkusers'])->middleware(['customcan:create_users'])->name('users.bulkUpload');

                Route::get('/users/profile/{id}', [UserController::class, 'show'])->name('users.profile');

                Route::get('/users/edit/{id}', [UserController::class, 'edit_user'])->middleware(['customcan:edit_users']);

                Route::put('/users/update_user/{user}', [UserController::class, 'update_user'])->middleware(['customcan:edit_users', 'demo_restriction', 'log.activity']);

                Route::delete('/users/delete_user/{user}', [UserController::class, 'delete_user'])->middleware(['customcan:delete_users', 'demo_restriction', 'log.activity']);

                Route::post('/users/delete_multiple_user', [UserController::class, 'delete_multiple_user'])->middleware(['customcan:delete_users', 'demo_restriction', 'log.activity']);

                Route::get('/users/list', [UserController::class, 'list']);
            });
            Route::get('/users/get-mentions', [UserController::class, 'get_mentions']);

            //Clients-------------------------------------------------------------

            Route::middleware(['has_workspace', 'customcan:manage_clients'])->group(function () {

                Route::get('/clients', [ClientController::class, 'index']);

                Route::get('/clients/profile/{id}', [ClientController::class, 'show'])->name('clients.profile');

                Route::get('/clients/create', [ClientController::class, 'create'])->middleware(['customcan:create_clients']);

                Route::post('/clients/store', [ClientController::class, 'store'])->middleware(['customcan:create_clients', 'log.activity']);

                Route::get('/clients/bulk-upload', [ClientController::class, 'showBulkUploadForm'])->middleware(['customcan:create_clients'])->name('clients.showBulkUploadForm');

                Route::post('/clients/process-bulk-upload', [ClientController::class, 'importBulkClients'])->middleware(['customcan:create_clients'])->name('clients.bulkUpload');


                Route::get('/clients/get/{id}', [ClientController::class, 'get']);

                Route::get('/clients/edit/{id}', [ClientController::class, 'edit'])->middleware(['customcan:edit_clients']);

                Route::put('/clients/update/{id}', [ClientController::class, 'update'])->middleware(['customcan:edit_clients', 'demo_restriction', 'log.activity']);

                Route::delete('/clients/destroy/{id}', [ClientController::class, 'destroy'])->middleware(['customcan:delete_clients', 'demo_restriction', 'log.activity']);

                Route::post('/clients/destroy_multiple', [ClientController::class, 'destroy_multiple'])->middleware(['customcan:delete_clients', 'demo_restriction', 'log.activity']);

                Route::get('/clients/list', [ClientController::class, 'list']);
            });
        });

        //Settings-------------------------------------------------------------

        Route::put("settings/languages/set-default", [LanguageController::class, 'set_default'])->middleware(['demo_restriction']);

        Route::middleware(['customRole:admin'])->group(function () {

            Route::get('/settings/permission/create', [RolesController::class, 'create_permission']);

            Route::get('/settings/permission', [RolesController::class, 'index']);

            Route::delete('/roles/destroy/{id}', [RolesController::class, 'destroy'])->middleware(['demo_restriction']);

            Route::get('/roles/create', [RolesController::class, 'create']);

            Route::post('/roles/store', [RolesController::class, 'store']);

            Route::get('/roles/edit/{id}', [RolesController::class, 'edit']);

            Route::put('/roles/update/{id}', [RolesController::class, 'update']);

            Route::get('/settings/general', [SettingsController::class, 'index']);

            Route::put('/settings/store_general', [SettingsController::class, 'store_general_settings'])->middleware(['demo_restriction']);

            Route::get('/settings/security', [SettingsController::class, 'security']);

            Route::put('/settings/store_security', [SettingsController::class, 'store_security_settings'])->middleware(['demo_restriction']);

            Route::get('/settings/languages', [LanguageController::class, 'index']);

            Route::post('/settings/languages/store', [LanguageController::class, 'store']);

            Route::get("settings/languages/change/{code}", [LanguageController::class, 'change']);

            Route::put("/settings/languages/save_labels", [LanguageController::class, 'save_labels'])->middleware(['demo_restriction'])->name('languages.save_labels');

            Route::get("/settings/languages/manage", [LanguageController::class, 'manage']);

            Route::get('/settings/languages/get/{id}', [LanguageController::class, 'get']);

            Route::post('/settings/languages/update', [LanguageController::class, 'update'])->middleware(['demo_restriction']);

            Route::get("/settings/languages/list", [LanguageController::class, 'list']);

            Route::delete("/settings/languages/destroy/{id}", [LanguageController::class, 'destroy'])->middleware(['demo_restriction']);

            Route::post("/settings/languages/destroy_multiple", [LanguageController::class, 'destroy_multiple'])->middleware(['demo_restriction']);

            Route::get('/settings/email', [SettingsController::class, 'email']);

            Route::put('/settings/store_email', [SettingsController::class, 'store_email_settings'])->middleware(['demo_restriction']);

            Route::get('/settings/ai-model-settings', [SettingsController::class, 'ai_model_settings'])->middleware(['demo_restriction'])->name('settings.ai_models_setting');
            Route::put('/settings/store-ai-models-settings', [SettingsController::class, 'store_ai_model_settings'])->middleware(['demo_restriction'])->name('settings.store_ai_models');
            Route::get('/settings/sms-gateway', [SettingsController::class, 'sms_gateway']);

            Route::put('/settings/store_sms_gateway', [SettingsController::class, 'store_sms_gateway_settings'])->middleware(['demo_restriction']);

            Route::put('/settings/store_whatsapp', [SettingsController::class, 'store_whatsapp_settings'])->middleware(['demo_restriction']);

            Route::put('/settings/store_slack', [SettingsController::class, 'store_slack_settings'])->middleware(['demo_restriction'])->name('slack_settings.store');

            Route::get('/settings/pusher', [SettingsController::class, 'pusher']);

            Route::put('/settings/store_pusher', [SettingsController::class, 'store_pusher_settings'])->middleware(['demo_restriction']);

            Route::get('/settings/media-storage', [SettingsController::class, 'media_storage']);

            Route::put('/settings/store_media_storage', [SettingsController::class, 'store_media_storage_settings'])->middleware(['demo_restriction']);

            Route::get('/settings/templates', [SettingsController::class, 'templates']);

            Route::put('/settings/store_template', [SettingsController::class, 'store_template'])->middleware(['demo_restriction'])->name('templates.store');

            Route::post('/settings/get-default-template', [SettingsController::class, 'get_default_template']);

            Route::get('/settings/system-updater', [UpdaterController::class, 'index']);

            Route::post('/settings/update-system', [UpdaterController::class, 'update'])->middleware(['demo_restriction']);

            Route::get('/settings/terms-privacy-about', [SettingsController::class, 'terms_privacy_about'])->name('terms_privacy_about.index');

            Route::put('/settings/terms-privacy-about/store', [SettingsController::class, 'store_terms_privacy_about'])->name('terms_privacy_about.store')->middleware('demo_restriction');

            Route::post('/settings/notifications/test', [SettingsController::class, 'testNotificationSettings'])->middleware(['demo_restriction']);

            Route::get('/settings/company-info', [SettingsController::class, 'companyInfo']);

            Route::put('/settings/store_company_info', [SettingsController::class, 'store_company_info'])->middleware(['demo_restriction']);

            Route::get('/settings/google-calendar', [SettingsController::class, 'google_calendar'])->name('google_calendar.index');
            Route::put('/settings/store_google_calendar_settings', [SettingsController::class, 'store_google_calendar_settings'])->name('google_calendar.store')->middleware(['demo_restriction']);

            Route::prefix('/settings')->group(function () {
                Route::get('/custom-fields', [CustomFieldController::class, 'index'])->name('custom_fields.index');
                Route::post('/custom-fields', [CustomFieldController::class, 'store'])->name('custom_fields.store');
                Route::get('/custom-fields/list', [CustomFieldController::class, 'list'])->name('custom_fields.list');

                Route::get('/custom-fields/{id}/edit', [CustomFieldController::class, 'edit'])->name('custom_fields.edit');
                Route::post('/custom-fields/update/{id}', [CustomFieldController::class, 'update'])->name('custom_fields.update');
                Route::delete('/custom-fields/destroy/{id}', [CustomFieldController::class, 'destroy'])->name('custom_fields.destroy');
                Route::post('/custom-fields/destroy_multiple', [CustomFieldController::class, 'destroy_multiple'])->name('custom_fields.destroy_multiple');
            });
            Route::prefix('/settings')->group(function () {
                Route::get('/pwa-settings', [PwaSettingsController::class, 'index'])->name('pwa-settings.index');
                Route::post('/pwa-settings/update', [PwaSettingsController::class, 'update'])->middleware('auth')->name('pwa-settings.update');

                Route::prefix('/plugins')->group(function () {
                    Route::get('/', [PluginManagerController::class, 'index'])->name('plugins.index');
                    Route::get('/install', [PluginInstallerController::class, 'showForm'])->name('plugin.upload');
                    Route::post('/install', [PluginInstallerController::class, 'install'])->name('plugin.install')->middleware('demo_restriction');
                    Route::post('/enable/{slug}', [PluginManagerController::class, 'enable'])->name('plugins.enable')->middleware('demo_restriction');
                    Route::post('/disable/{slug}', [PluginManagerController::class, 'disable'])->name('plugins.disable')->middleware('demo_restriction');
                    Route::post('/uninstall/{slug}', [PluginManagerController::class, 'uninstall'])->name('plugins.uninstall')->middleware('demo_restriction');
                });
            });
        });



        Route::middleware(['customRole:admin'])->group(function () {
            Route::get('/reports/income-vs-expense', [ReportsController::class, 'showIncomeVsExpenseReport'])->name('reports.income-vs-expense');
            Route::get('/reports/income-vs-expense-report-data', [ReportsController::class, 'getIncomeVsExpenseReportData'])->name('reports.income-vs-expense-report-data');
            Route::get('/reports/export-income-vs-expense-report', [ReportsController::class, 'exportIncomeVsExpenseReport'])->name('reports.export-income-vs-expense-report');
        });


        Route::middleware(['has_workspace'])->group(function () {
            Route::get('/search', [SearchController::class, 'search']);

            Route::middleware(['admin_or_user'])->group(function () {
                Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave_requests.index');
                Route::post('/leave-requests/store', [LeaveRequestController::class, 'store'])->middleware('log.activity');
                Route::get('/leave-requests/list', [LeaveRequestController::class, 'list']);
                Route::get('/leave-requests/get/{id}', [LeaveRequestController::class, 'get']);
                Route::post('/leave-requests/update', [LeaveRequestController::class, 'update'])->middleware(['log.activity']);
                Route::post('/leave-requests/update-editors', [LeaveRequestController::class, 'update_editors'])->middleware(['customRole:admin']);
                Route::delete('/leave-requests/destroy/{id}', [LeaveRequestController::class, 'destroy'])->middleware(['admin_or_leave_editor', 'demo_restriction', 'log.activity']);
                Route::post('/leave-requests/destroy_multiple', [LeaveRequestController::class, 'destroy_multiple'])->middleware(['admin_or_leave_editor', 'demo_restriction', 'log.activity']);
                Route::put('/save-leave-requests-view-preference', [LeaveRequestController::class, 'saveViewPreference'])->name('leave_requests.save_view_preference');
                Route::get('/leave-requests/calendar-view', [LeaveRequestController::class, 'calendar_view'])->name('leave-requests.calendar');
                Route::get('/leave-requests/get-calendar-data', [LeaveRequestController::class, 'get_calendar_data'])->name('leave-requests.get_calendar_data');
                Route::get('/reports/leaves', [ReportsController::class, 'showLeavesReport'])->name('reports.leaves');
                Route::get('/reports/leaves-report-data', [ReportsController::class, 'getLeavesReportData'])->name('reports.leaves-report-data');
                Route::get('/reports/export-leaves-report', [ReportsController::class, 'exportLeavesReport'])->name('reports.export-leaves-report');
            });
            Route::middleware(['customcan:manage_contracts'])->group(function () {
                Route::get('/contracts', [ContractsController::class, 'index'])->name('contracts.index');
                Route::post('/contracts/store', [ContractsController::class, 'store'])->middleware(['customcan:create_contracts', 'log.activity']);
                Route::get('/contracts/list', [ContractsController::class, 'list']);
                Route::get('/contracts/get/{id}', [ContractsController::class, 'get'])->middleware(['checkAccess:App\Models\Contract,contracts,id']);
                Route::post('/contracts/update', [ContractsController::class, 'update'])->middleware(['customcan:edit_contracts', 'log.activity']);
                Route::get('/contracts/sign/{id}', [ContractsController::class, 'sign'])->middleware(['checkAccess:App\Models\Contract,contracts,id,contracts', 'log.activity']);
                Route::post('/contracts/create-sign', [ContractsController::class, 'create_sign'])->middleware('log.activity');
                Route::get('/contracts/duplicate/{id}', [ContractsController::class, 'duplicate'])->middleware(['customcan:create_contracts', 'checkAccess:App\Models\Contract,contracts,id,contracts', 'log.activity']);
                Route::delete('/contracts/destroy/{id}', [ContractsController::class, 'destroy'])->middleware(['customcan:delete_contracts', 'demo_restriction', 'checkAccess:App\Models\Contract,contracts,id,contracts', 'log.activity']);
                Route::post('/contracts/destroy_multiple', [ContractsController::class, 'destroy_multiple'])->middleware(['customcan:delete_contracts', 'demo_restriction', 'log.activity']);
                Route::delete('/contracts/delete-sign/{id}', [ContractsController::class, 'delete_sign'])->middleware('log.activity');
            });
            Route::middleware(['customcan:manage_contract_types'])->group(function () {
                Route::get('/contracts/contract-types', [ContractsController::class, 'contract_types']);
                Route::post('/contracts/store-contract-type', [ContractsController::class, 'store_contract_type'])->middleware(['customcan:create_contract_types', 'log.activity']);
                Route::get('/contracts/contract-types-list', [ContractsController::class, 'contract_types_list']);
                Route::get('/contracts/get-contract-type/{id}', [ContractsController::class, 'get_contract_type']);
                Route::post('/contracts/update-contract-type', [ContractsController::class, 'update_contract_type'])->middleware(['customcan:edit_contract_types', 'log.activity']);
                Route::delete('/contracts/delete-contract-type/{id}', [ContractsController::class, 'delete_contract_type'])->middleware(['customcan:delete_contract_types', 'demo_restriction', 'log.activity']);
                Route::post('/contracts/delete-multiple-contract-type', [ContractsController::class, 'delete_multiple_contract_type'])->middleware(['customcan:delete_contract_types', 'demo_restriction', 'log.activity']);
            });
            Route::middleware(['customcan:manage_payslips'])->group(function () {
                Route::get('/payslips', [PayslipsController::class, 'index']);
                Route::get('/payslips/create', [PayslipsController::class, 'create'])->middleware(['customcan:create_payslips']);
                Route::post('/payslips/store', [PayslipsController::class, 'store'])->middleware(['customcan:create_payslips', 'log.activity']);
                Route::get('/payslips/list', [PayslipsController::class, 'list']);
                Route::delete('/payslips/destroy/{id}', [PayslipsController::class, 'destroy'])->middleware(['demo_restriction', 'customcan:delete_payslips', 'checkAccess:App\Models\Payslip,payslips,id,payslips', 'log.activity']);
                Route::post('/payslips/destroy_multiple', [PayslipsController::class, 'destroy_multiple'])->middleware(['demo_restriction', 'customcan:delete_payslips', 'log.activity']);
                Route::get('/payslips/duplicate/{id}', [PayslipsController::class, 'duplicate'])->middleware(['customcan:create_payslips', 'checkAccess:App\Models\Payslip,payslips,id,payslips', 'log.activity']);
                Route::get('/payslips/edit/{id}', [PayslipsController::class, 'edit'])->middleware(['customcan:edit_payslips', 'checkAccess:App\Models\Payslip,payslips,id,payslips']);
                Route::post('/payslips/update', [PayslipsController::class, 'update'])->middleware(['customcan:edit_payslips', 'log.activity']);
                Route::get('/payslips/view/{id}', [PayslipsController::class, 'view'])->middleware(['checkAccess:App\Models\Payslip,payslips,id,payslips']);
            });
            Route::middleware(['customcan:manage_allowances'])->group(function () {
                Route::get('/allowances', [AllowancesController::class, 'index'])->name('allowances.index');
                Route::post('/allowances/store', [AllowancesController::class, 'store'])->middleware(['customcan:create_allowances', 'log.activity']);
                Route::get('/allowances/list', [AllowancesController::class, 'list']);
                Route::get('/allowances/get/{id}', [AllowancesController::class, 'get']);
                Route::post('/allowances/update', [AllowancesController::class, 'update'])->middleware(['customcan:edit_allowances', 'log.activity']);
                Route::delete('/allowances/destroy/{id}', [AllowancesController::class, 'destroy'])->middleware(['customcan:delete_allowances', 'demo_restriction', 'log.activity']);
                Route::post('/allowances/destroy_multiple', [AllowancesController::class, 'destroy_multiple'])->middleware(['customcan:delete_allowances', 'demo_restriction', 'log.activity']);
            });
            Route::middleware(['customcan:manage_deductions'])->group(function () {
                Route::get('/deductions', [DeductionsController::class, 'index']);
                Route::post('/deductions/store', [DeductionsController::class, 'store'])->middleware(['customcan:create_deductions', 'log.activity']);
                Route::get('/deductions/get/{id}', [DeductionsController::class, 'get']);
                Route::get('/deductions/list', [DeductionsController::class, 'list']);
                Route::post('/deductions/update', [DeductionsController::class, 'update'])->middleware(['customcan:edit_deductions', 'log.activity']);
                Route::delete('/deductions/destroy/{id}', [DeductionsController::class, 'destroy'])->middleware(['customcan:delete_deductions', 'demo_restriction', 'log.activity']);
                Route::post('/deductions/destroy_multiple', [DeductionsController::class, 'destroy_multiple'])->middleware(['customcan:delete_deductions', 'demo_restriction', 'log.activity']);
            });
            Route::get('/time-tracker', [TimeTrackerController::class, 'index'])->middleware(['customcan:manage_timesheet']);
            Route::post('/time-tracker/store', [TimeTrackerController::class, 'store'])->middleware(['customcan:create_timesheet', 'log.activity']);
            Route::post('/time-tracker/update', [TimeTrackerController::class, 'update'])->middleware('log.activity');
            Route::get('/time-tracker/list', [TimeTrackerController::class, 'list'])->middleware(['customcan:manage_timesheet']);
            Route::delete('/time-tracker/destroy/{id}', [TimeTrackerController::class, 'destroy'])->middleware(['customcan:delete_timesheet', 'log.activity']);
            Route::post('/time-tracker/destroy_multiple', [TimeTrackerController::class, 'destroy_multiple'])->middleware(['customcan:delete_timesheet', 'log.activity']);

            Route::middleware(['customcan:manage_activity_log'])->group(function () {
                Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity_log.index');
                Route::get('/activity-log/list', [ActivityLogController::class, 'list'])->name('activity_log.list');
                Route::delete('/activity-log/destroy/{id}', [ActivityLogController::class, 'destroy'])->middleware(['demo_restriction', 'customcan:delete_activity_log']);
                Route::post('/activity-log/destroy_multiple', [ActivityLogController::class, 'destroy_multiple'])->middleware(['demo_restriction', 'customcan:delete_activity_log']);
                Route::get('/activity-log/calendar-view', [ActivityLogController::class, 'calendar_view'])->name('activity_log.calendar_view');
                Route::get('/activity-log/get-calendar-data', [ActivityLogController::class, 'get_calendar_data'])->name('activity_log.get_calendar_data');
                Route::put('/save-activity-log-view-preference', [ActivityLogController::class, 'saveViewPreference'])->name('activity_log.save_view_preference');
            });

            Route::middleware(['customcan:manage_estimates_invoices'])->group(function () {

                Route::get('/estimates-invoices', [EstimatesInvoicesController::class, 'index']);
                Route::get('/estimates-invoices/create', [EstimatesInvoicesController::class, 'create'])->middleware(['customcan:create_estimates_invoices']);;
                Route::post('/estimates-invoices/store', [EstimatesInvoicesController::class, 'store'])->middleware(['customcan:create_estimates_invoices', 'log.activity']);
                Route::get('/estimates-invoices/list', [EstimatesInvoicesController::class, 'list']);
                Route::get('/estimates-invoices/edit/{id}', [EstimatesInvoicesController::class, 'edit'])->middleware(['customcan:edit_estimates_invoices', 'checkAccess:App\Models\EstimatesInvoice,estimates_invoices,id,estimates_invoices']);
                Route::get('/estimates-invoices/view/{id}', [EstimatesInvoicesController::class, 'view'])->middleware(['checkAccess:App\Models\EstimatesInvoice,estimates_invoices,id,estimates_invoices'])->name('estimates-invoices.view');
                Route::get('/estimates-invoices/pdf/{id}', [EstimatesInvoicesController::class, 'pdf'])->middleware(['checkAccess:App\Models\EstimatesInvoice,estimates_invoices,id,estimates_invoices']);
                Route::post('/estimates-invoices/update', [EstimatesInvoicesController::class, 'update'])->middleware(['customcan:edit_estimates_invoices', 'log.activity']);
                Route::get('/estimates-invoices/duplicate/{id}', [EstimatesInvoicesController::class, 'duplicate'])->middleware(['customcan:create_estimates_invoices', 'checkAccess:App\Models\EstimatesInvoice,EstimatesInvoice,id,estimates_invoices', 'log.activity']);
                Route::delete('/estimates-invoices/destroy/{id}', [EstimatesInvoicesController::class, 'destroy'])->middleware(['demo_restriction', 'customcan:delete_estimates_invoices', 'checkAccess:App\Models\EstimatesInvoice,estimates_invoices,id,estimates_invoices', 'log.activity']);
                Route::post('/estimates-invoices/destroy_multiple', [EstimatesInvoicesController::class, 'destroy_multiple'])->middleware(['demo_restriction', 'customcan:delete_estimates_invoices', 'log.activity']);

                Route::get('/reports/estimates-invoices', [ReportsController::class, 'showInvoicesReport'])->name('reports.invoices-report');
                Route::get('/reports/invoices-report-data', [ReportsController::class, 'getInvoicesReportData'])->name('reports.invoices-report-data');
                Route::get('/reports/export-invoices-report', [ReportsController::class, 'exportInvoicesReport'])->name('reports.export-invoices-report');
            });

            Route::middleware(['customcan:manage_payments'])->group(function () {

                Route::get('/payments', [PaymentsController::class, 'index']);
                Route::post('/payments/store', [PaymentsController::class, 'store'])->middleware(['customcan:create_payments', 'log.activity']);
                Route::get('/payments/list', [PaymentsController::class, 'list']);
                Route::get('/payments/get/{id}', [PaymentsController::class, 'get'])->middleware(['checkAccess:App\Models\Payment,payments,id']);
                Route::post('/payments/update', [PaymentsController::class, 'update'])->middleware(['customcan:edit_payments', 'log.activity']);
                Route::delete('/payments/destroy/{id}', [PaymentsController::class, 'destroy'])->middleware(['customcan:delete_payments', 'demo_restriction', 'checkAccess:App\Models\Payments,payments,id,payments', 'log.activity']);
                Route::post('/payments/destroy_multiple', [PaymentsController::class, 'destroy_multiple'])->middleware(['customcan:delete_payments', 'demo_restriction', 'log.activity']);
            });
            Route::middleware(['customcan:manage_taxes'])->group(function () {

                Route::get('/taxes', [TaxesController::class, 'index']);
                Route::post('/taxes/store', [TaxesController::class, 'store'])->middleware(['customcan:create_taxes', 'log.activity']);
                Route::get('/taxes/get/{id}', [TaxesController::class, 'get']);
                Route::get('/taxes/list', [TaxesController::class, 'list']);
                Route::post('/taxes/update', [TaxesController::class, 'update'])->middleware(['customcan:edit_taxes', 'log.activity']);
                Route::delete('/taxes/destroy/{id}', [TaxesController::class, 'destroy'])->middleware(['customcan:delete_taxes', 'demo_restriction', 'log.activity']);
                Route::post('/taxes/destroy_multiple', [TaxesController::class, 'destroy_multiple'])->middleware(['customcan:delete_taxes', 'demo_restriction', 'log.activity']);
            });
            Route::middleware(['customcan:manage_units'])->group(function () {

                Route::get('/units', [UnitsController::class, 'index']);
                Route::post('/units/store', [UnitsController::class, 'store'])->middleware(['customcan:create_units', 'log.activity']);
                Route::get('/units/get/{id}', [UnitsController::class, 'get']);
                Route::get('/units/list', [UnitsController::class, 'list']);
                Route::post('/units/update', [UnitsController::class, 'update'])->middleware(['customcan:edit_units', 'log.activity']);
                Route::delete('/units/destroy/{id}', [UnitsController::class, 'destroy'])->middleware(['customcan:delete_units', 'demo_restriction', 'log.activity']);
                Route::post('/units/destroy_multiple', [UnitsController::class, 'destroy_multiple'])->middleware(['customcan:delete_units', 'demo_restriction', 'log.activity']);
            });

            Route::middleware(['customcan:manage_items'])->group(function () {
                Route::get('/items', [ItemsController::class, 'index']);
                Route::post('/items/store', [ItemsController::class, 'store'])->middleware(['customcan:create_items', 'log.activity']);
                Route::get('/items/get/{id}', [ItemsController::class, 'get']);
                Route::get('/items/list', [ItemsController::class, 'list']);
                Route::post('/items/update', [ItemsController::class, 'update'])->middleware(['customcan:edit_items', 'log.activity']);
                Route::delete('/items/destroy/{id}', [ItemsController::class, 'destroy'])->middleware(['customcan:delete_items', 'demo_restriction', 'log.activity']);
                Route::post('/items/destroy_multiple', [ItemsController::class, 'destroy_multiple'])->middleware(['customcan:delete_items', 'demo_restriction', 'log.activity']);
            });

            Route::middleware(['customcan:manage_payment_methods'])->group(function () {
                Route::get('/payment-methods', [PaymentMethodsController::class, 'index']);
                Route::post('/payment-methods/store', [PaymentMethodsController::class, 'store'])->middleware(['customcan:create_payment_methods', 'log.activity']);
                Route::get('/payment-methods/list', [PaymentMethodsController::class, 'list']);
                Route::get('/payment-methods/get/{id}', [PaymentMethodsController::class, 'get']);
                Route::post('/payment-methods/update', [PaymentMethodsController::class, 'update'])->middleware(['customcan:edit_payment_methods', 'log.activity']);
                Route::delete('/payment-methods/destroy/{id}', [PaymentMethodsController::class, 'destroy'])->middleware(['customcan:delete_payment_methods', 'demo_restriction', 'log.activity']);
                Route::post('/payment-methods/destroy_multiple', [PaymentMethodsController::class, 'destroy_multiple'])->middleware(['customcan:delete_payment_methods', 'demo_restriction', 'log.activity']);
            });

            Route::middleware(['customcan:manage_expenses'])->group(function () {
                Route::get('/expenses', [ExpensesController::class, 'index']);
                Route::post('/expenses/store', [ExpensesController::class, 'store'])->middleware(['customcan:create_expenses', 'log.activity']);
                Route::get('/expenses/list', [ExpensesController::class, 'list']);
                Route::get('/expenses/get/{id}', [ExpensesController::class, 'get'])->middleware(['checkAccess:App\Models\Expense,expenses,id']);
                Route::post('/expenses/update', [ExpensesController::class, 'update'])->middleware(['customcan:edit_expenses', 'log.activity']);
                Route::get('/expenses/duplicate/{id}', [ExpensesController::class, 'duplicate'])->middleware(['customcan:create_expenses', 'checkAccess:App\Models\Expense,expenses,id,expenses', 'log.activity']);
                Route::delete('/expenses/destroy/{id}', [ExpensesController::class, 'destroy'])->middleware(['customcan:delete_expenses', 'demo_restriction', 'checkAccess:App\Models\Expense,expenses,id,expenses', 'log.activity']);
                Route::post('/expenses/destroy_multiple', [ExpensesController::class, 'destroy_multiple'])->middleware(['customcan:delete_expenses', 'demo_restriction', 'log.activity']);
            });

            Route::middleware(['customcan:manage_expense_types'])->group(function () {
                Route::get('/expenses/expense-types', [ExpensesController::class, 'expense_types']);
                Route::post('/expenses/store-expense-type', [ExpensesController::class, 'store_expense_type'])->middleware(['customcan:create_expense_types', 'log.activity']);
                Route::get('/expenses/expense-types-list', [ExpensesController::class, 'expense_types_list']);
                Route::get('/expenses/get-expense-type/{id}', [ExpensesController::class, 'get_expense_type']);
                Route::post('/expenses/update-expense-type', [ExpensesController::class, 'update_expense_type'])->middleware(['customcan:edit_expense_types', 'log.activity']);
                Route::delete('/expenses/delete-expense-type/{id}', [ExpensesController::class, 'delete_expense_type'])->middleware(['customcan:delete_expense_types', 'demo_restriction', 'log.activity']);
                Route::post('/expenses/delete-multiple-expense-type', [ExpensesController::class, 'delete_multiple_expense_type'])->middleware(['customcan:delete_expense_types', 'demo_restriction', 'log.activity']);
            });

            Route::middleware(['customcan:manage_system_notifications'])->group(function () {
                Route::put('/notifications/mark-all-as-read', [NotificationsController::class, 'mark_all_as_read']);
                Route::get('/notifications', [NotificationsController::class, 'index']);
                Route::get('/notifications/list', [NotificationsController::class, 'list']);
                Route::delete('/notifications/destroy/{id}', [NotificationsController::class, 'destroy'])->middleware(['customcan:delete_system_notifications', 'demo_restriction']);
                Route::post('/notifications/destroy_multiple', [NotificationsController::class, 'destroy_multiple'])->middleware(['customcan:delete_system_notifications', 'demo_restriction']);
                Route::put('/notifications/update-status', [NotificationsController::class, 'update_status']);
                Route::get('/notifications/get-unread-notifications', [NotificationsController::class, 'getUnreadNotifications'])->middleware(['customcan:manage_system_notifications']);
            });
            Route::get('preferences', [PreferenceController::class, 'index'])->name('preferences.index');
            Route::post('/save-notification-preferences', [PreferenceController::class, 'saveNotificationPreferences'])->name('preferences.saveNotifications');
            Route::post('/save-column-visibility', [PreferenceController::class, 'saveColumnVisibility']);
            Route::post('/save-menu-order', [PreferenceController::class, 'saveMenuOrder']);
            Route::delete('/reset-default-menu-order', [PreferenceController::class, 'resetDefaultMenuOrder']);

            Route::prefix('calendars')->group(function () {
                Route::get('/holiday-calendar', function () {
                    return view('calendars.index');
                })->name('calendars.holiday_calendar');
            });
        });


        // Lead Sources
        Route::prefix('lead-sources')->middleware(['customcan:manage_leads'])->group(function () {
            Route::get('/', [LeadSourceController::class, 'index'])->name('lead-sources.index');
            Route::post('/store', [LeadSourceController::class, 'store'])->name('lead-sources.store')->middleware(['customcan:create_leads', 'log.activity']);
            Route::get('/get/{id?}', [LeadSourceController::class, 'get'])->name('lead-sources.get');
            Route::get('/list', [LeadSourceController::class, 'list'])->name('lead-sources.list');
            Route::post('/update', [LeadSourceController::class, 'update'])->name('lead-sources.update')->middleware(['customcan:edit_leads', 'log.activity']);
            Route::delete('/destroy/{id}', [LeadSourceController::class, 'destroy'])->name('lead-sources.destroy')->middleware(['customcan:delete_leads', 'demo_restriction', 'log.activity']);
            Route::post('/destroy_multiple', [LeadSourceController::class, 'destroy_multiple'])->name('lead-sources.destroy_multiple')->middleware(['customcan:delete_leads', 'demo_restriction', 'log.activity']);
        });

        // Lead Stages
        Route::prefix('lead-stages')->middleware(['customcan:manage_leads'])->group(function () {
            Route::get('/', [LeadStageController::class, 'index'])->name('lead-stages.index');
            Route::post('/store', [LeadStageController::class, 'store'])->name('lead-stages.store')->middleware(['customcan:create_leads', 'log.activity']);
            Route::get('/get/{id?}', [LeadStageController::class, 'get'])->name('lead-stages.get');
            Route::get('/list', [LeadStageController::class, 'list'])->name('lead-stages.list');
            Route::post('/update', [LeadStageController::class, 'update'])->name('lead-stages.update')->middleware(['customcan:edit_leads', 'log.activity']);
            Route::delete('/destroy/{id}', [LeadStageController::class, 'destroy'])->name('lead-stages.destroy')->middleware(['customcan:delete_leads', 'demo_restriction', 'log.activity']);
            Route::post('/destroy_multiple', [LeadStageController::class, 'destroy_multiple'])->name('lead-stages.destroy_multiple')->middleware(['customcan:delete_leads', 'demo_restriction', 'log.activity']);
            Route::post('/reorder', [LeadStageController::class, 'reorder'])->name('lead-stages.reorder');
        });

        // Leads
        Route::prefix('leads')->middleware(['customcan:manage_leads'])->group(function () {
            Route::get('/', [LeadController::class, 'index'])->name('leads.index');
            Route::get('/create', [LeadController::class, 'create'])->name('leads.create')->middleware(['customcan:create_leads']);
            Route::post('/store', [LeadController::class, 'store'])->name('leads.store')->middleware(['customcan:create_leads', 'log.activity']);
            Route::get('/get/{id?}', [LeadController::class, 'get'])->name('leads.get');
            Route::get('/edit/{id}', [LeadController::class, 'edit'])->name('leads.edit')->middleware(['customcan:edit_leads']);
            Route::get('/show/{id}', [LeadController::class, 'show'])->name('leads.show')->middleware(['customcan:manage_leads']);
            Route::get('/list', [LeadController::class, 'list'])->name('leads.list');
            Route::post('/update/{id}', [LeadController::class, 'update'])->name('leads.update')->middleware(['customcan:edit_leads', 'log.activity']);
            Route::delete('/destroy/{id}', [LeadController::class, 'destroy'])->name('leads.destroy')->middleware(['customcan:delete_leads', 'demo_restriction', 'log.activity']);
            Route::post('/destroy_multiple', [LeadController::class, 'destroy_multiple'])->name('leads.destroy_multiple')->middleware(['customcan:delete_leads', 'demo_restriction', 'log.activity']);
            // Lead Follow Up
            Route::post('/follow-up/store', [LeadFollowUpController::class, 'store'])->name('lead_follow_up.store');
            Route::get('/follow-up/get/{id}', [LeadFollowUpController::class, 'edit'])->name('lead_follow_up.edit');
            Route::post('/follow-up/update', [LeadFollowUpController::class, 'update'])->name('lead_follow_up.update');
            Route::delete('/follow-up/destroy/{id}', [LeadFollowUpController::class, 'destroy'])->name('lead_follow_up.destroy');
            Route::get('/kanban-view', [LeadController::class, 'kanban'])->name('leads.kanban_view')->middleware(['customcan:manage_leads']);
            Route::post('/stage-change', [LeadController::class, 'stageChange'])->name('leads.stage_change')->middleware(['customcan:edit_leads', 'log.activity']);
            // Bulk Upload
            Route::get('/bulk-upload', [LeadImportController::class, 'index'])->name('leads.upload')->middleware(['customcan:create_leads']);
            Route::post('/bulk-upload/parse', [LeadImportController::class, 'parse'])->name('leads.parse')->middleware(['customcan:create_leads']);
            Route::post('/bulk-upload/import', [LeadImportController::class, 'import'])->name('leads.import')->middleware(['customcan:create_leads', 'log.activity']);
            Route::any('/bulk-upload/mapped-leads', [LeadImportController::class, 'previewMappedLeads'])->name('leads.previewMappedLeads');
            Route::post('/{lead}/convert-to-client', [LeadController::class, 'convertToClient'])->name('leads.convert_to_client');
        });
        Route::put('/save-leads-view-preference', [LeadController::class, 'saveViewPreference'])->name('leads.save_view_preference');


        //Email Templates
        Route::middleware(['customcan:manage_email_template'])->group(function () {
            Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email.templates');
            Route::post('/email-templates/store', [EmailTemplateController::class, 'store'])->name('email.templates.store')->middleware('customcan:create_email_template');
            Route::put('/email-templates/update/{id}', [EmailTemplateController::class, 'update'])->name('email.templates.update')->middleware('customcan:edit_email_template');
            Route::delete('/email-templates/destroy/{id}', [EmailTemplateController::class, 'destroy'])->name('email.templates.delete')->middleware('customcan:delete_email_template');
            Route::post('/email_templates/destroy_multiple', [EmailTemplateController::class, 'destroy_multiple'])->name('email.templates.delete_multiple')->middleware('customcan:delete_email_template');
            Route::get('/email-templates/list', [EmailTemplateController::class, 'list'])->name('email.templates.list');
        });

        // Email Sending Routes
        Route::prefix('emails')->middleware('customcan:send_email')->group(function () {
            Route::get('/create', [EmailSendController::class, 'create'])->name('emails.send');
            Route::post('/preview', [EmailSendController::class, 'preview'])->name('emails.preview');
            Route::post('/store', [EmailSendController::class, 'store'])->name('emails.store')->middleware('log.activity');
            Route::get('/template-data/{id}', [EmailSendController::class, 'getTemplateData']);
            Route::get('/', [EmailSendController::class, 'history'])->name('emails.sent_list');
            Route::get('/historyList', [EmailSendController::class, 'historyList'])->name('emails.historyList');
            Route::delete('/history/destroy/{id}', [EmailSendController::class, 'destroy'])->name('emails.history.destroy');
            Route::post('/history/destroy_multiple', [EmailSendController::class, 'destroy_multiple'])->name('emails.history.destroy_multiple');
        })->middleware(['auth:web']);



        // Routes for Candidates

        Route::prefix('candidate')->middleware('customcan:manage_candidate')->group(function () {
            Route::get('/index', [CandidateController::class, 'index'])->name('candidate.index');
            Route::post('/store', [CandidateController::class, 'store'])->name('candidate.store')->middleware('customcan:create_candidate');
            Route::put('/update/{id}', [CandidateController::class, 'update'])->name('candidate.update')->middleware('customcan:edit_candidate');
            Route::post('/{id}/update_status', [CandidateController::class, 'update_status'])->name('candidate.update.status')->middleware('customcan:edit_candidate');
            Route::delete('/destroy/{id}', [CandidateController::class, 'destroy'])->name('candidate.destroy')->middleware('customcan:delete_candidate');
            Route::post('/destroy_multiple', [CandidateController::class, 'destroy_multiple'])->name('candidate.destroy_multiple')->middleware('customcan:delete_candidate');
            Route::get('/kanban', [CandidateController::class, 'kanban_view'])->name('candidate.kanban_view');
            Route::get('/list', [CandidateController::class, 'list'])->name('candidate.list');
            Route::get('/{id}', [CandidateController::class, 'show'])->name('candidate.show');
            Route::get('/{id}/interviews', [CandidateController::class, 'getInterviewDetails'])->name('candidate.interviews.details');

            Route::post('/{id}/upload-attachment', [CandidateController::class, 'uploadAttachment'])
                ->name('candidate.upload-attachment');
            Route::delete('/candidate-media/destroy/{id}', [CandidateController::class, 'deleteAttachment'])
                ->name('candidate.delete-attachment');
            Route::get('/{id}/attachments/list', [CandidateController::class, 'attachmentsList'])->name('candidate.attachments.list');
            Route::get('/{candidateId}/attachment/{mediaId}/download', [CandidateController::class, 'downloadAttachment'])
                ->name('candidate.attachment.download');
            Route::get('/{candidateId}/attachment/{mediaId}/view', [CandidateController::class, 'viewAttachment'])
                ->name('candidate.attachment.view');
            Route::get('/{id}/quick-view', [CandidateController::class, 'getCandidate'])->name('candidate.quick-view');
        });

        Route::prefix('candidate_status')->middleware('customcan:manage_candidate_status')->group(function () {
            Route::get('/index', [CandidateStatusController::class, 'index'])->name('candidate.status.index');
            Route::post('/store', [CandidateStatusController::class, 'store'])->name('candidate.status.store')->middleware('customcan:create_candidate_status');
            Route::put('/update/{id}', [CandidateStatusController::class, 'update'])->name('candidate.status.update')->middleware('customcan:edit_candidate_status');
            Route::delete('/destroy/{id}', [CandidateStatusController::class, 'destroy'])->name('candidate.status.destroy')->middleware('customcan:delete_candidate_status');
            Route::post('/destroy_multiple', [CandidateStatusController::class, 'destroy_multiple'])->name('candidate.status.destroy_multiple')->middleware('customcan:delete_candidate_status');
            Route::post('/reorder', [CandidateStatusController::class, 'reorder'])->name('candidate.status.reorder');
            Route::get('/list', [CandidateStatusController::class, 'list'])->name('candidate.status.list');
        });


        Route::get('/interviews/index', [InterviewController::class, 'index'])->name('interviews.index')->middleware('customcan:manage_interview');
        Route::post('/interviews/store', [InterviewController::class, 'store'])->name('interviews.store')->middleware(['customcan:create_interview', 'log.activity']);
        Route::put('/interviews/update/{id}', [InterviewController::class, 'update'])->name('interviews.update')->middleware(['customcan:edit_interview', 'log.activity']);
        Route::delete('/interviews/destroy/{id}', [InterviewController::class, 'destroy'])->name('interviews.destroy')->middleware(['customcan:delete_interview', 'log.activity']);
        Route::post('/interviews/destroy_multiple', [InterviewController::class, 'destroy_multiple'])->name('interviews.destroy_multiple')->middleware(['customcan:delete_interview', 'log.activity']);
        Route::get('/interviews/list', [InterviewController::class, 'list'])->name('interviews.list')->middleware('customcan:manage_interview');

        Route::post('/ai/generate-description', [AIController::class, 'generateDescription'])
            ->name('generate.description');

        // Route::get('lead-forms', [LeadFormController::class, 'index'])->name('lead-forms.index');




        // List all lead forms
        Route::get('lead-forms', [LeadFormController::class, 'index'])->name('lead-forms.index');

        // Show create form
        Route::get('lead-forms/create', [LeadFormController::class, 'create'])->name('lead-forms.create');

        // Store new form
        Route::post('lead-forms/store', [LeadFormController::class, 'store'])->name('lead-forms.store');

        // Show a specific form
        Route::get('lead-forms/show/{id}', [LeadFormController::class, 'show'])->name('lead-forms.show');

        // Show edit form
        Route::get('lead-forms/edit/{id}', [LeadFormController::class, 'edit'])->name('lead-forms.edit');

        // Update a form
        Route::post('lead-forms/update/{id}', [LeadFormController::class, 'update'])->name('lead-forms.update');

        // Delete a form
        Route::delete('lead-forms/destroy/{id}', [LeadFormController::class, 'destroy'])->name('lead-forms.destroy');
        Route::post('lead-forms/destroy_multiple', [LeadFormController::class, 'destroy_multiple'])->name('lead-forms.destroy_multiple');
        Route::get('lead-forms/list', [LeadFormController::class, 'list'])->name('lead-forms.list');
        Route::post('lead-forms/{leadForm}/toggle', [LeadFormController::class, 'toggleStatus'])->name('lead-forms.toggle');
        // In your routes/web.php
        Route::get('lead-forms/{leadForm}/embed', [LeadFormController::class, 'embed'])->name('lead-forms.embed');
        Route::get('lead-forms/{id}/responses', [LeadFormController::class, 'responses'])->name('lead-forms.responses');
        Route::get('lead-forms/responses/list/{id}', [LeadFormController::class, 'responseList'])->name('lead-forms.responses.list');

        Route::post('/ai/generate-description', [AIController::class, 'generateDescription'])
            ->name('generate.description');

        Route::get('/file-manager', function () {
            return view('file-manager.index');
        })->name('file-manager.index')->middleware(['customRole:admin']);
    });
});
