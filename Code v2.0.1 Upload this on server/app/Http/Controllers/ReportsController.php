<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Client;
use App\Models\Status;
use App\Models\Project;
use App\Models\Priority;
use App\Models\Workspace;
use Illuminate\Support\Str;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\EstimatesInvoice;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    protected $workspace;
    protected $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            // fetch session and use it in entire class with constructor
            $this->workspace = Workspace::find(getWorkspaceId());
            $this->user = getAuthenticatedUser();
            return $next($request);
        });
    }

    public function showProjectReport()
    {
        return view('reports.projects-report');
    }
    public function getProjectReportData(Request $request)
    {
        // Determine the base query based on user's access level
        $query = isAdminOrHasAllDataAccess() ? $this->workspace->projects() : $this->user->projects();
        // Apply filters only if they have values
        // Check for project_id filter
        if ($request->filled('project_ids')) {
            $query->whereIn('id', $request->project_ids);
        }

        // Check for user_id filter
        if ($request->filled('user_ids')) {
            $query->whereHas('users', function ($q) use ($request) {
                $q->whereIn('users.id', $request->user_ids);
            });
        }

        // Check for client_id filter
        if ($request->filled('client_ids')) {
            $query->whereHas('clients', function ($q) use ($request) {
                $q->whereIn('clients.id', $request->client_ids);
            });
        }

        // Handle date filters
        $dateFilterFrom = $request->filled('date_between_from') ? $request->date_between_from : null;
        // dd($dateFilterFrom);
        $dateFilterTo = $request->filled('date_between_to') ? $request->date_between_to : null;
        $startDateFilter = $request->filled('start_date_from') && $request->filled('start_date_to')
            ? [$request->start_date_from, $request->start_date_to]
            : null;
        $endDateFilter = $request->filled('end_date_from') && $request->filled('end_date_to')
            ? [$request->end_date_from, $request->end_date_to]
            : null;

        if ($dateFilterFrom && $dateFilterTo) {
            $query->where('start_date', '>=', $dateFilterFrom)
                ->where('end_date', '<=', $dateFilterTo);
        }

        if ($startDateFilter) {
            $query->whereBetween('start_date', $startDateFilter);
        }

        if ($endDateFilter) {
            $query->whereBetween('end_date', $endDateFilter);
        }

        // Check for status_id filter
        if ($request->filled('status_ids')) {
            $query->whereIn('status_id', $request->status_ids);
        }
        if ($request->filled('priority_ids')) {
            $query->whereIn('priority_id', $request->priority_ids);
        }
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm)
                    ->orWhereHas('status', function ($q) use ($searchTerm) {
                        $q->where('title', 'like', $searchTerm);
                    })
                    ->orWhereHas('priority', function ($q) use ($searchTerm) {
                        $q->where('title', 'like', $searchTerm);
                    })
                    ->orWhereHas('users', function ($q) use ($searchTerm) {
                        $q->where(function ($q) use ($searchTerm) {
                            $q->where('first_name', 'like', $searchTerm)
                                ->orWhere('last_name', 'like', $searchTerm)
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm]);
                        });
                    })
                    ->orWhereHas('clients', function ($q) use ($searchTerm) {
                        $q->where(function ($q) use ($searchTerm) {
                            $q->where('first_name', 'like', $searchTerm)
                                ->orWhere('last_name', 'like', $searchTerm)
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm]);
                        });
                    });
            });
        }
        // Global aggregations for all projects (ignoring pagination)
        $allProjectsQuery = clone $query; // Clone the query to avoid modifying it for pagination
        $allProjects = $allProjectsQuery->get();

        // Calculate totals and averages
        $totalProjects = $allProjects->count();
        $totalTasks = $allProjects->sum(function ($project) {
            return $project->tasks()->count();
        });
        $totalTeamMembers = $allProjects->sum(function ($project) {
            return $project->users()->count();
        });

        // Calculate overdue-related metrics
        $overdueProjects = $allProjects->filter(function ($project) {
            $endDate = $project->end_date ? Carbon::parse($project->end_date) : null;
            return $endDate && $endDate->isPast();
        });

        $totalOverdueDays = $overdueProjects->sum(function ($project) {
            $endDate = Carbon::parse($project->end_date);
            return now()->diffInDays($endDate);
        });

        $overdueProjectsPercentage = $totalProjects > 0
            ? ($overdueProjects->count() / $totalProjects) * 100
            : 0;

        // Average overdue days per project
        $avgOverdueDays = $overdueProjects->count() > 0
            ? $totalOverdueDays / $overdueProjects->count()
            : 0;

        $dueProjects = $allProjects->filter(function ($project) {
            $endDate = $project->end_date ? Carbon::parse($project->end_date) : null;
            return $endDate && $endDate->isToday();
        });

        // Calculate due projects percentage
        $dueProjectsPercentage = $totalProjects > 0
            ? ($dueProjects->count() / $totalProjects) * 100
            : 0;
        // Apply sorting
        $sort = $request->input('sort', 'id'); // Default sort column
        $order = $request->input('order', 'desc'); // Default sort order
        // Sorting logic
        switch ($sort) {
            case 'status':
                $query->join('statuses', 'projects.status_id', '=', 'statuses.id')
                    ->select('projects.*', 'statuses.title as status_title')
                    ->orderBy('status_title', $order);
                break;
            case 'priority':
                $query->join('priorities', 'projects.priority_id', '=', 'priorities.id')
                    ->select('projects.*', 'priorities.title as priority_title')
                    ->orderBy('priority_title', $order);
                break;
            case 'title':
            case 'start_date':
            case 'end_date':
                $query->orderBy($sort, $order);
                break;
            default:
                $query->orderBy('id', $order); // Default sort column
        }
        // Pagination setup
        $perPage = $request->input('limit', 10);
        $page = $request->input('offset', 0) / $perPage + 1;
        // Get the total count before pagination
        $total = $query->count();
        // Fetch paginated results with related models
        $projects = $query->with(['tasks', 'users', 'clients', 'status', 'priority', 'tags'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
        // Transform project data into the desired report format
        $canManageProjects = checkPermission('manage_projects');
        $report = $projects->map(function ($project) use ($canManageProjects) {
            $projectOverdueDays = '-';
            $now = now();
            $startDate = $project->start_date ? Carbon::parse($project->start_date) : null;
            $endDate = $project->end_date ? Carbon::parse($project->end_date) : null;
            $totalProjectDays = ($startDate && $endDate)
                ? $startDate->diffInDays($endDate) + 1
                : '-';
            $daysElapsed = $startDate
                ? $now->diffInDays($startDate)
                : '-';

            $daysRemaining = $endDate
                ? ($endDate->isPast() && !$endDate->isToday() ? 0 : $now->diffInDays($endDate) + ($endDate->isToday() ? 1 : 0))
                : '-';
            if ($endDate) {
                if ($endDate < now()->toDateString()) {
                    // If end date is in the past, calculate overdue days
                    $projectOverdueDays = now()->diffInDays(Carbon::parse($endDate));
                } else {
                    // If end date is today or in the future, set overdue days to 0
                    $projectOverdueDays = 0;
                }
            }


            $now = now()->toDateString(); // Get today's date without time
            $tasks = $project->tasks;

            // Initialize counters and total overdue days
            $totalTasks = $tasks->count();
            $dueTasks = 0;
            $overdueTasks = 0;
            $overdueDays = 0;

            // Iterate over the tasks once to calculate due, overdue, and overdue days
            foreach ($tasks as $task) {
                // Ensure due_date is not null
                if ($task->due_date) {
                    $dueDate = Carbon::parse($task->due_date)->toDateString();

                    // Count due tasks (tasks due today)
                    if ($dueDate === $now) {
                        $dueTasks++;
                    }

                    // Count overdue tasks (tasks overdue)
                    if ($dueDate < $now) {
                        $overdueTasks++;

                        // Calculate overdue days
                        $overdueDays += now()->diffInDays(Carbon::parse($task->due_date));
                    }
                }
            }

            $totalBudget = !empty($project->budget) && $project->budget !== null ? format_currency($project->budget) : '-';
            // Format clients' HTML
            // Format clients' HTML
            $clientHtml = $project->clients->isEmpty()
                ? '-'
                : "<ul class='list-unstyled users-list m-0 avatar-group d-flex align-items-center'>" .
                $project->clients->map(function ($client) {
                    return "<li class='avatar avatar-sm pull-up' title='" . e($client->first_name . " " . $client->last_name) . "'>
                    <a href='" . route('clients.profile', ['id' => $client->id]) . "' target='_blank'>
                        <img src='" . ($client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' />
                    </a>
                </li>";
                })->implode('') .
                '</ul>';

            // Format users' HTML
            $userHtml = $project->users->isEmpty()
                ? '-'
                : "<ul class='list-unstyled users-list m-0 avatar-group d-flex align-items-center'>" .
                $project->users->map(function ($user) {
                    return "<li class='avatar avatar-sm pull-up' title='" . e($user->first_name . " " . $user->last_name) . "'>
                    <a href='" . route('users.profile', ['id' => $user->id]) . "' target='_blank'>
                        <img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' class='rounded-circle' />
                    </a>
                </li>";
                })->implode('') .
                '</ul>';


            return [
                'id' => $project->id,
                'title' => $canManageProjects
                    ? "<a href='" . route('projects.info', ['id' => $project->id]) . "' target='_blank'>" . $project->title . "</a>"
                    : $project->title,
                'description' => $project->description,
                'start_date' => format_date($project->start_date),
                'end_date' => format_date($project->end_date),
                'status' => "<span class='badge bg-label-" . e($project->status->color) . "'>" . e($project->status->title) . "</span>",
                'priority' => $project->priority ? "<span class='badge bg-label-" . e($project->priority->color) . "'>" . e($project->priority->title) . "</span>" : '-',
                'budget' => [
                    'total' => $totalBudget,
                ],
                'time' => [
                    'total_days' => $totalProjectDays,
                    'days_elapsed' => $daysElapsed,
                    'days_remaining' => $daysRemaining,
                    'overdue_days' => $projectOverdueDays,
                ],
                'tasks' => [
                    'total' => $totalTasks,
                    'due' => $dueTasks,
                    'overdue' => $overdueTasks,
                    'overdue_days' => $overdueDays,
                ],
                'team' => [
                    'users' => $project->users->map(function ($user) use ($project) {
                        return [
                            'id' => $user->id,
                            'name' => $user->first_name . ' ' . $user->last_name,
                            'tasks_assigned' => $user->tasks()->where('project_id', $project->id)->count(),
                        ];
                    }),
                    'total_members' => $project->users->count()
                ],
                'users' => $userHtml,
                'clients' => $clientHtml,
                'total_clients' => $project->clients->count(),
                'tags' => $project->tags->pluck('title'),
                'is_favorite' => $project->is_favorite,
                'task_accessibility' => $project->task_accessibility,
                'created_at' => format_date($project->created_at),
                'updated_at' => format_date($project->updated_at),
            ];
        });
        // Generate summary data
        $summary = [
            'total_projects' => $totalProjects,
            'overdue_projects' => $overdueProjects->count(),
            'due_projects' => $dueProjects->count(),
            'on_time_projects' => $report->where('tasks.overdue', 0)->count(),
            'projects_with_due_tasks' => $report->where('tasks.due', '>', 0)->count(),
            'projects_with_overdue_tasks' => $report->where('tasks.overdue', '>', 0)->count(),
            'average_days_remaining' => round(
                $report->filter(function ($item) {
                    return is_numeric($item['time']['days_remaining']);
                })->avg('time.days_remaining'),
                2
            ),
            'average_task_progress' => round($report->avg(function ($project) {
                if ($project['tasks']['total'] > 0) {
                    return ($project['tasks']['total'] - $project['tasks']['overdue']) / $project['tasks']['total'] * 100;
                }
                return 0; // Return 0 if there are no tasks in the project
            }), 2),

            'average_overdue_days_per_project' => round($avgOverdueDays, 2),
            'total_team_members' => $totalTeamMembers,
            'overdue_projects_percentage' => $overdueProjectsPercentage,
            'due_projects_percentage' => $dueProjectsPercentage,
            'total_overdue_days' => $totalOverdueDays,
            'average_task_duration' => round($report->avg(function ($project) {
                // Ensure tasks are an array or collection
                $tasks = collect($project['tasks']);

                return $tasks->count() > 0 ? $tasks->avg(function ($task) {
                    // Ensure that start_date and due_date are accessible
                    return isset($task['start_date'], $task['due_date'])
                        ? Carbon::parse($task['start_date'])->diffInDays(Carbon::parse($task['due_date']))
                        : 0;
                }) : 0;
            }), 2),


            'total_tasks' => $totalTasks

        ];

        return response()->json([
            'projects' => $report,
            'total' => $total,
            'summary' => $summary,
        ]);
    }
    public function exportProjectReport(Request $request)
    {
        $projectsData = $this->getProjectReportData($request)->getData();
        // dd($projectsData);
        $pdf = Pdf::loadView('reports.projects-report-pdf', ['projects' => $projectsData->projects, 'summary' => $projectsData->summary])
            ->setPaper([0, 0, 2000, 900], 'mm');
        return $pdf->download('Projects Report.pdf');
    }

    public function showTaskReport()
    {
        return view('reports.tasks-report');
    }

    public function getTaskReportData(Request $request)
    {
        // Determine the base query based on user's access level
        $query = isAdminOrHasAllDataAccess() ? $this->workspace->tasks() : $this->user->tasks();

        // Apply filters
        if ($request->filled('project_ids')) {
            $query->whereIn('project_id', $request->project_ids);
        }

        if ($request->filled('user_ids')) {
            $query->whereHas('users', function ($q) use ($request) {
                $q->whereIn('users.id', $request->user_ids);
            });
        }

        if ($request->filled('client_ids')) {
            $query->whereHas('project.clients', function ($q) use ($request) {
                $q->whereIn('clients.id', $request->client_ids);
            });
        }

        if ($request->filled('status_ids')) {
            $query->whereIn('status_id', $request->status_ids);
        }

        if ($request->filled('priority_ids')) {
            $query->whereIn('priority_id', $request->priority_ids);
        }

        // Handle date filters
        $dateFilterFrom = $request->filled('date_between_from') ? $request->date_between_from : null;
        $dateFilterTo = $request->filled('date_between_to') ? $request->date_between_to : null;
        $startDateFilter = $request->filled('start_date_from') && $request->filled('start_date_to')
            ? [$request->start_date_from, $request->start_date_to]
            : null;
        $endDateFilter = $request->filled('end_date_from') && $request->filled('end_date_to')
            ? [$request->end_date_from, $request->end_date_to]
            : null;

        if ($dateFilterFrom && $dateFilterTo) {
            $query->where('start_date', '>=', $dateFilterFrom)
                ->where('due_date', '<=', $dateFilterTo);
        }

        if ($startDateFilter) {
            $query->whereBetween('start_date', $startDateFilter);
        }

        if ($endDateFilter) {
            $query->whereBetween('due_date', $endDateFilter);
        }

        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm)
                    ->orWhereHas('status', function ($q) use ($searchTerm) {
                        $q->where('title', 'like', $searchTerm);
                    })
                    ->orWhereHas('priority', function ($q) use ($searchTerm) {
                        $q->where('title', 'like', $searchTerm);
                    })
                    ->orWhereHas('users', function ($q) use ($searchTerm) {
                        $q->where(function ($q) use ($searchTerm) {
                            $q->where('first_name', 'like', $searchTerm)
                                ->orWhere('last_name', 'like', $searchTerm)
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm]);
                        });
                    })
                    ->orWhereHas('project.clients', function ($q) use ($searchTerm) {
                        $q->where(function ($q) use ($searchTerm) {
                            $q->where('first_name', 'like', $searchTerm)
                                ->orWhere('last_name', 'like', $searchTerm)
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm]);
                        });
                    });
            });
        }

        // Global aggregations for all tasks (ignoring pagination)
        $allTasksQuery = clone $query; // Clone the query to avoid modifying it for pagination
        $allTasks = $allTasksQuery->get();

        // Calculate totals and averages
        $totalTasks = $allTasks->count();

        // Calculate overdue-related metrics
        $overdueTasks = $allTasks->filter(function ($task) {
            $endDate = $task->due_date ? Carbon::parse($task->due_date) : null;
            return $endDate && $endDate->isPast();
        });

        $overdueTasksPercentage = $totalTasks > 0
            ? ($overdueTasks->count() / $totalTasks) * 100
            : 0;

        $dueTasks = $allTasks->filter(function ($task) {
            $endDate = $task->due_date ? Carbon::parse($task->due_date) : null;
            return $endDate && $endDate->isToday();
        });

        // Calculate due projects percentage
        $dueTasksPercentage = $totalTasks > 0
            ? ($dueTasks->count() / $totalTasks) * 100
            : 0;

        $averageTaskDuration = round(
            $allTasks->filter(function ($task) {
                return $task->start_date && $task->due_date;
            })->avg(function ($task) {
                // Calculate the duration and ensure it's at least 1 day
                $duration = Carbon::parse($task->start_date)->diffInDays(Carbon::parse($task->due_date));
                return max($duration, 1); // Ensure duration is at least 1
            }),
            2
        );

        $urgentTasks = $allTasks->filter(function ($task) {
            $dueDate = $task->due_date ? Carbon::parse($task->due_date) : null;
            return $dueDate && $dueDate->isPast() && $task->priority && $task->priority->color === 'danger';
        });

        // Count and calculate percentage
        $urgentTasksCount = $urgentTasks->count();

        $urgentTasksPercentage = $totalTasks > 0
            ? ($urgentTasksCount / $totalTasks) * 100
            : 0;

        // Apply sorting
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'desc');
        $query->orderBy($sort, $order);

        // Pagination setup
        $perPage = $request->input('limit', 10);
        $page = $request->input('offset', 0) / $perPage + 1;
        $total = $query->count();
        $tasks = $query->with(['project', 'status', 'priority', 'project.clients'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
        $canManageTasks = checkPermission('manage_tasks');
        $canManageProjects = checkPermission('manage_projects');
        // Transform task data into the desired report format
        $report = $tasks->map(function ($task) use ($canManageTasks, $canManageProjects) {
            $overdueDays = '-';
            $now = now();
            $startDate = $task->start_date ? Carbon::parse($task->start_date) : null;
            $endDate = $task->due_date ? Carbon::parse($task->due_date) : null;
            $totalTaskDays = ($startDate && $endDate)
                ? $startDate->diffInDays($endDate) + 1
                : '-';
            $daysElapsed = $startDate
                ? $now->diffInDays($startDate)
                : '-';

            $daysRemaining = $endDate
                ? ($endDate->isToday() ? 1 : ($endDate->isPast() ? 0 : $now->diffInDays($endDate)))
                : '-';

            if ($endDate) {
                if ($endDate < now()->toDateString()) {
                    // If end date is in the past, calculate overdue days
                    $overdueDays = now()->diffInDays(Carbon::parse($endDate));
                } else {
                    // If end date is today or in the future, set overdue days to 0
                    $overdueDays = 0;
                }
            }

            // Format clients' HTML
            $clientHtml = $task->project->clients->isEmpty()
                ? '-'
                : "<ul class='list-unstyled users-list m-0 avatar-group d-flex align-items-center'>" .
                $task->project->clients->map(function ($client) {
                    return "<li class='avatar avatar-sm pull-up' title='" . e($client->first_name . " " . $client->last_name) . "'>
                    <a href='" . route('clients.profile', ['id' => $client->id]) . "' target='_blank'>
                        <img src='" . ($client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' />
                    </a>
                </li>";
                })->implode('') .
                '</ul>';

            // Format users' HTML
            $userHtml = $task->users->isEmpty()
                ? '-'
                : "<ul class='list-unstyled users-list m-0 avatar-group d-flex align-items-center'>" .
                $task->users->map(function ($user) {
                    return "<li class='avatar avatar-sm pull-up' title='" . e($user->first_name . " " . $user->last_name) . "'>
                    <a href='" . route('users.profile', ['id' => $user->id]) . "' target='_blank'>
                        <img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' class='rounded-circle' />
                    </a>
                </li>";
                })->implode('') .
                '</ul>';


            return [
                'id' => $task->id,
                'title' => $canManageTasks
                    ? "<a href='" . route('tasks.info', ['id' => $task->id]) . "'>" . $task->title . "</a>"
                    : $task->title,
                'description' => $task->description,
                'start_date' => format_date($task->start_date),
                'due_date' => format_date($task->due_date),
                'status' => "<span class='badge bg-label-" . e($task->status->color) . "'>" . e($task->status->title) . "</span>",
                'priority' => $task->priority ? "<span class='badge bg-label-" . e($task->priority->color) . "'>" . e($task->priority->title) . "</span>" : '-',
                'project' => $canManageProjects
                    ? "<a href='" . route('projects.info', ['id' => $task->project->id]) . "' target='_blank'>" . $task->project->title . "</a>"
                    : $task->project,
                'assigned_to' => $task->assignedTo ? $task->assignedTo->first_name . ' ' . $task->assignedTo->last_name : '-',
                'time' => [
                    'total_days' => $totalTaskDays,
                    'days_elapsed' => $daysElapsed,
                    'days_remaining' => $daysRemaining,
                    'overdue_days' => $overdueDays,
                ],
                'users' => $userHtml,
                'total_users' => $task->users->count(),
                'clients' => $clientHtml,
                'total_clients' => $task->project->clients->count(),
                'is_urgent' => $task->priority && $task->priority->color === 'danger' && $endDate->isPast(),
                'created_at' => format_date($task->created_at),
                'updated_at' => format_date($task->updated_at),
            ];
        });

        // Generate summary data
        $summary = [
            'total_tasks' => $totalTasks,
            'due_tasks' => $dueTasks->count(),
            'due_tasks_percentage' => $dueTasksPercentage,
            'overdue_tasks' => $overdueTasks->count(),
            'overdue_tasks_percentage' => $overdueTasksPercentage,
            'urgent_tasks' => $urgentTasksCount,
            'urgent_tasks_percentage' => $urgentTasksPercentage,
            'average_task_duration' => $averageTaskDuration
        ];
        return response()->json([
            'tasks' => $report,
            'total' => $total,
            'summary' => $summary,
        ]);
    }


    public function exportTaskReport(Request $request)
    {
        $tasksData = $this->getTaskReportData($request)->getData();
        $pdf = Pdf::loadView('reports.tasks-report-pdf', ['tasks' => $tasksData->tasks, 'summary' => $tasksData->summary])
            ->setPaper([0, 0, 2000, 900], 'mm');
        return $pdf->download('Tasks report.pdf');
    }

    public function showIncomeVsExpenseReport(Request $request)
    {
        $reportData = $this->getIncomeVsExpenseReportData($request)->getData();

        // Pass data to view
        return view('reports.income-vs-expense-report', [
            'report' => $reportData,
        ]);
    }



    /**
     * Get the Income vs Expense Statistics.
     *
     * This endpoint provides the Income vs Expense Statistics.
     * The user must be authenticated to access this data.
     *
     * @authenticated
     * @group Income vs Expense
     *
     * @response 200 {
     *   "total_income": "$ 705.00",
     *   "total_expenses": "$ 20,000.00",
     *   "profit_or_loss": "$ -19,295.00",
     *   "invoices": [
     *     {
     *       "id": 3,
     *       "view_route": "http://localhost:8000/estimates-invoices/view/3",
     *       "amount": "$ 105.00",
     *       "to_date": "05-02-2025",
     *       "from_date": "05-02-2025"
     *     },
     *     {
     *       "id": 4,
     *       "view_route": "http://localhost:8000/estimates-invoices/view/4",
     *       "amount": "$ 600.00",
     *       "to_date": "05-02-2025",
     *       "from_date": "05-02-2025"
     *     }
     *   ],
     *   "expenses": [
     *     {
     *       "id": 1,
     *       "title": "Salary",
     *       "amount": "$ 500.00",
     *       "expense_date": "31-01-2025"
     *     },
     *     {
     *       "id": 2,
     *       "title": "January Rent Pay",
     *       "amount": "$ 5,000.00",
     *       "expense_date": "04-02-2025"
     *     },
     *     {
     *       "id": 3,
     *       "title": "Salary to Karen",
     *       "amount": "$ 5,000.00",
     *       "expense_date": "31-01-2025"
     *     },
     *     {
     *       "id": 4,
     *       "title": "Internet Bill Payment",
     *       "amount": "$ 300.00",
     *       "expense_date": "31-01-2025"
     *     },
     *     {
     *       "id": 5,
     *       "title": "Office Refreshment Items",
     *       "amount": "$ 1,000.00",
     *       "expense_date": "31-01-2025"
     *     },
     *     {
     *       "id": 9,
     *       "title": "Transportation Fuel",
     *       "amount": "$ 2,000.00",
     *       "expense_date": "08-02-2025"
     *     },
     *     {
     *       "id": 10,
     *       "title": "Corporate Tax",
     *       "amount": "$ 1,200.00",
     *       "expense_date": "08-02-2025"
     *     },
     *     {
     *       "id": 11,
     *       "title": "Event Sponsorships",
     *       "amount": "$ 5,000.00",
     *       "expense_date": "08-02-2025"
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Something went wrong."
     * }
     */

    public function getIncomeVsExpenseReportData(Request $request)
    {
        // dd($request);
        $isApi = request()->get('isApi', false);

        // Initialize the query for total income from invoices
        $invoicesQuery = EstimatesInvoice::query()
            ->select('id', 'final_total', 'from_date', 'to_date')
            ->whereIn('status', ['fully_paid', 'partially_paid'])
            ->where('type', 'invoice')
            ->where('workspace_id', $this->workspace->id);

        // Apply date filters only if both start_date and end_date are provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $invoicesQuery->where(function ($query) use ($request) {
                $query->whereBetween('to_date', [$request->start_date, $request->end_date])
                    ->orWhereBetween('from_date', [$request->start_date, $request->end_date]);
            });
        }

        // Get detailed income data
        $invoices = $invoicesQuery->get();
        $totalIncome = $invoices->sum('final_total');

        // Initialize the query for total expenses
        $expensesQuery = Expense::query()
            ->select('id', 'title', 'amount', 'expense_date')
            ->where('workspace_id', $this->workspace->id);

        // Apply date filters only if both start_date and end_date are provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $expensesQuery->whereBetween('expense_date', [$request->start_date, $request->end_date]);
        }

        // Get detailed expense data
        $expenses = $expensesQuery->get();
        $totalExpenses = $expenses->sum('amount');

        // Calculate profit or loss
        $profitOrLoss = $totalIncome - $totalExpenses;

        // Prepare detailed report data
        $report = [
            'total_income' => format_currency($totalIncome),
            'total_expenses' => format_currency($totalExpenses),
            'profit_or_loss' => format_currency($profitOrLoss),
            'invoices' => $invoices->map(function ($invoice) use ($isApi) {

                return [
                    'id' => $invoice->id,
                    'view_route' => route('estimates-invoices.view', ['id' => $invoice->id]),
                    'amount' => format_currency($invoice->final_total),
                    'to_date' => $isApi ? format_date($invoice->to_date, to_format: 'Y-m-d') : format_date($invoice->to_date),
                    'from_date' => $isApi ? format_date($invoice->from_date, to_format: 'Y-m-d') : format_date($invoice->from_date)
                ];
            }),
            'expenses' => $expenses->map(function ($expense) use ($isApi) {
                return [
                    'id' => $expense->id,
                    'title' => $expense->title,
                    'amount' => format_currency($expense->amount),
                    'expense_date' => $isApi ? format_date($expense->expense_date, to_format: 'Y-m-d') : format_date($expense->expense_date)
                ];
            }),
        ];

        return response()->json($report);
    }

    public function exportIncomeVsExpenseReport(Request $request)
    {
        $reportData = $this->getIncomeVsExpenseReportData($request)->getData();
        $pdf = Pdf::loadView('reports.income-vs-expense-report-pdf', ['report' => $reportData])
            ->setPaper([0, 0, 2000, 900], 'mm');

        return $pdf->download('Income vs. Expense Report.pdf');
    }

    public function showLeavesReport()
    {
        return view('reports.leaves-report');
    }

    public function getLeavesReportData(Request $request)
    {
        $search = $request->input('search', '');

        // Determine the users to fetch based on the user's role
        $users = is_admin_or_leave_editor() ? $this->workspace->users() : $this->user;

        // If the user is not an admin or leave editor, merge their ID into the user_ids in the request
        if (!is_admin_or_leave_editor()) {
            $request->merge(['user_ids' => [$this->user->id]]);
        }

        // Filter the users based on the user_ids in the request
        if ($request->filled('user_ids')) {
            $users = $users->whereIn('users.id', $request->user_ids);
        }

        $dateFilterFrom = $request->input('date_between_from');
        $dateFilterTo = $request->input('date_between_to');

        $users = $users->with([
            'leave_requests' => function ($query) use ($dateFilterFrom, $dateFilterTo) {
                if ($dateFilterFrom && $dateFilterTo) {
                    $query->where('from_date', '>=', $dateFilterFrom)
                        ->where('to_date', '<=', $dateFilterTo);
                }
            }
        ])->get();

        // Apply search filter if provided
        if ($search) {
            $users = $users->filter(function ($user) use ($search) {
                return Str::contains(strtolower($user->first_name . ' ' . $user->last_name), strtolower($search))
                    || Str::contains(strtolower($user->email), strtolower($search));
            });
        }

        $report = $users->map(function ($user) use ($request) {
            $leaveRequests = $user->leave_requests;

            // Apply status filter if provided
            if ($request->filled('statuses')) {
                $leaveRequests = $leaveRequests->whereIn('status', $request->statuses);
            }

            $fullLeaves = 0;
            $partialLeaves = 0;
            $approvedHours = 0;
            $approvedDays = 0;
            $pendingHours = 0;
            $pendingDays = 0;
            $rejectedHours = 0;
            $rejectedDays = 0;
            $partialHours = 0;

            foreach ($leaveRequests as $leave_request) {
                $fromDate = Carbon::parse($leave_request->from_date);
                $toDate = Carbon::parse($leave_request->to_date);

                if ($leave_request->from_time && $leave_request->to_time) {
                    // Handle partial leave requests
                    $partialLeaves++;

                    $fromDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leave_request->from_time);
                    $toDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leave_request->to_time);

                    $hours = $fromDateTime->diffInMinutes($toDateTime) / 60;

                    if ($leave_request->status === 'approved') {
                        $approvedHours += $hours;
                    } elseif ($leave_request->status === 'pending') {
                        $pendingHours += $hours;
                    } elseif ($leave_request->status === 'rejected') {
                        $rejectedHours += $hours;
                    }
                    $partialHours += $hours;
                } else {
                    // Handle full day leave requests
                    $days = $fromDate->diffInDays($toDate) + 1;

                    if ($leave_request->status === 'approved') {
                        $approvedDays += $days;
                    } elseif ($leave_request->status === 'pending') {
                        $pendingDays += $days;
                    } elseif ($leave_request->status === 'rejected') {
                        $rejectedDays += $days;
                    }

                    $fullLeaves++;
                }
            }

            return [
                'id' => $user->id,
                'user_name' => formatUserHtml($user),
                'total_leaves' => $leaveRequests->count(),
                'approved_leaves' => $leaveRequests->where('status', 'approved')->count(),
                'pending_leaves' => $leaveRequests->where('status', 'pending')->count(),
                'rejected_leaves' => $leaveRequests->where('status', 'rejected')->count(),
                'full_leaves' => $fullLeaves,
                'partial_leaves' => $partialLeaves,
                'approved_hours' => round($approvedHours, 2),
                'approved_days' => $approvedDays,
                'pending_hours' => round($pendingHours, 2),
                'pending_days' => $pendingDays,
                'rejected_hours' => round($rejectedHours, 2),
                'rejected_days' => $rejectedDays,
                'total_hours' => round($approvedHours + $pendingHours + $rejectedHours, 2),
                'total_days' => $approvedDays + $pendingDays + $rejectedDays,
                'total_partial_hours' => round($partialHours, 2),

                // User-wise formatted durations
                'formatted_total_leaves' => $this->formatLeaveDuration($leaveRequests->count(), $approvedDays + $pendingDays + $rejectedDays, round($approvedHours + $pendingHours + $rejectedHours, 2)),
                'formatted_partial_leaves' => $this->formatLeaveDuration($partialLeaves, '', round($partialHours, 2)),
                'formatted_approved_leaves' => $this->formatLeaveDuration($leaveRequests->where('status', 'approved')->count(), $approvedDays, $approvedHours),
                'formatted_pending_leaves' => $this->formatLeaveDuration($leaveRequests->where('status', 'pending')->count(), $pendingDays, $pendingHours),
                'formatted_rejected_leaves' => $this->formatLeaveDuration($leaveRequests->where('status', 'rejected')->count(), $rejectedDays, $rejectedHours)
            ];
        });

        $sort = $request->input('sort', 'user_name');
        $order = $request->input('order', 'asc');

        if ($sort === 'user_name') {
            $report = $report->sortBy(function ($item) {
                return strtolower($item['user_name']);
            }, SORT_NATURAL | SORT_FLAG_CASE);
        } else {
            $report = $report->sortBy($sort);
        }

        if ($order === 'desc') {
            $report = $report->reverse();
        }

        $perPage = $request->input('limit', 10);
        $page = ($request->input('offset', 0) / $perPage) + 1;
        $total = $report->count();

        $paginatedReport = $report->forPage($page, $perPage);

        $summary = [
            'total_leaves' => $report->sum('total_leaves'),
            'total_approved_leaves' => $report->sum('approved_leaves'),
            'total_pending_leaves' => $report->sum('pending_leaves'),
            'total_rejected_leaves' => $report->sum('rejected_leaves'),
            'total_full_leaves' => $report->sum('full_leaves'),
            'total_partial_leaves' => $report->sum('partial_leaves'),
            'total_approved_hours' => $report->sum('approved_hours'),
            'total_approved_days' => $report->sum('approved_days'),
            'total_pending_hours' => $report->sum('pending_hours'),
            'total_pending_days' => $report->sum('pending_days'),
            'total_rejected_hours' => $report->sum('rejected_hours'),
            'total_rejected_days' => $report->sum('rejected_days'),
            'total_hours' => round($report->sum('approved_hours') + $report->sum('pending_hours') + $report->sum('rejected_hours'), 2),
            'total_days' => $report->sum('approved_days') + $report->sum('pending_days') + $report->sum('rejected_days'),
        ];

        // Formatting the duration data before sending it
        $summary['formatted_total_leaves'] = $this->formatLeaveDuration($summary['total_leaves'], $summary['total_days'], $summary['total_hours']);
        $summary['formatted_partial_leaves'] = $this->formatLeaveDuration($summary['total_partial_leaves'], '', $summary['total_hours']);
        $summary['formatted_approved_leaves'] = $this->formatLeaveDuration($summary['total_approved_leaves'], $summary['total_approved_days'], $summary['total_approved_hours']);
        $summary['formatted_pending_leaves'] = $this->formatLeaveDuration($summary['total_pending_leaves'], $summary['total_pending_days'], $summary['total_pending_hours']);
        $summary['formatted_rejected_leaves'] = $this->formatLeaveDuration($summary['total_rejected_leaves'], $summary['total_rejected_days'], $summary['total_rejected_hours']);


        return response()->json([
            'users' => $paginatedReport->values(),
            'total' => $total,
            'summary' => $summary,
        ]);
    }

    public function formatLeaveDuration($totalLeaves, $days, $hours)
    {
        $dayLabel = get_label('day', 'Day');
        $daysLabel = get_label('days', 'Days');
        $hourLabel = get_label('hour', 'Hour');
        $hoursLabel = get_label('hours', 'Hours');

        // If there are no days or hours, return just the total leaves
        if ($days == 0 && $hours == 0) {
            return "{$totalLeaves}";
        }

        // Initialize the formatted string
        $formatted = "{$totalLeaves}";

        // Check if there are any days or hours to include
        $leaveDuration = [];

        // If there are days, format and add them
        if ($days > 0) {
            $leaveDuration[] = "{$days} " . ($days > 1 ? $daysLabel : $dayLabel);
        }

        // If there are hours, format and add them
        if ($hours > 0) {
            $leaveDuration[] = "{$hours} " . ($hours > 1 ? $hoursLabel : $hourLabel);
        }

        // If we have any leave duration to display, append it inside parentheses
        if (!empty($leaveDuration)) {
            $formatted .= " (" . implode(' and ', $leaveDuration) . ")";
        }

        return $formatted;
    }


    public function exportLeavesReport(Request $request)
    {
        $leavesData = $this->getLeavesReportData($request)->getData();
        $pdf = Pdf::loadView('reports.leaves-report-pdf', ['users' => $leavesData->users, 'summary' => $leavesData->summary])
            ->setPaper([0, 0, 2000, 900], 'mm');

        return $pdf->download('Leaves Report.pdf');
    }

    public function showInvoicesReport()
    {
        $clients = $this->workspace->clients;
        $invoice_statuses = [
            'sent' => get_label('sent', 'Sent'),
            'accepted' => get_label('accepted', 'Accepted'),
            'partially_paid' => get_label('partially_paid', 'Partially Paid'),
            'fully_paid' => get_label('fully_paid', 'Fully Paid'),
            'draft' => get_label('draft', 'Draft'),
            'declined' => get_label('declined', 'Declined'),
            'expired' => get_label('expired', 'Expired'),
            'not_specified' => get_label('not_specified', 'Not Specified'),
            'due' => get_label('due', 'Due')
        ];
        return view('reports.invoices-report', compact('clients', 'invoice_statuses', ));
    }

    public function getInvoicesReportData(Request $request)
    {
        $query = EstimatesInvoice::query()
            ->select(
                'estimates_invoices.*',
                DB::raw('CONCAT(clients.first_name, " ", clients.last_name) AS client_name')
            )
            ->leftJoin('clients', 'estimates_invoices.client_id', '=', 'clients.id')
            ->where('estimates_invoices.workspace_id', $this->workspace->id);

        if (!isAdminOrHasAllDataAccess()) {
            $query->where(function ($q) {
                $q->where('estimates_invoices.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('estimates_invoices.client_id', $this->user->id);
            });
        }
        // Apply filters
        if ($request->filled('types')) {
            $query->whereIn('type', $request->types);
        }
        if ($request->filled('client_ids')) {
            $query->whereIn('estimates_invoices.client_id', $request->client_ids);
        }
        if ($request->filled('created_by_user_ids')) {
            $query->whereIn('estimates_invoices.created_by', array_map(function ($id) {
                return 'u_' . $id;
            }, $request->created_by_user_ids));
        }
        if ($request->filled('created_by_client_ids')) {
            $query->whereIn('estimates_invoices.created_by', array_map(function ($id) {
                return 'c_' . $id;
            }, $request->created_by_client_ids));
        }
        if ($request->filled('statuses')) {
            $query->whereIn('estimates_invoices.status', $request->statuses);
        }

        $dateFilterFrom = $request->filled('date_between_from') ? $request->date_between_from : null;
        $dateFilterTo = $request->filled('date_between_to') ? $request->date_between_to : null;
        $startDateFilter = $request->filled('start_date_from') && $request->filled('start_date_to')
            ? [$request->start_date_from, $request->start_date_to]
            : null;
        $endDateFilter = $request->filled('end_date_from') && $request->filled('end_date_to')
            ? [$request->end_date_from, $request->end_date_to]
            : null;

        if ($dateFilterFrom && $dateFilterTo) {
            $query->where('estimates_invoices.from_date', '>=', $dateFilterFrom)
                ->where('estimates_invoices.to_date', '<=', $dateFilterTo);
        }

        if ($startDateFilter) {
            $query->whereBetween('estimates_invoices.from_date', $startDateFilter);
        }

        if ($endDateFilter) {
            $query->whereBetween('estimates_invoices.to_date', $endDateFilter);
        }
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $invoicePrefix = get_label('invoice_id_prefix', 'INVC-');
            $estimatePrefix = get_label('estimate_id_prefix', 'ESTMT-');

            $query->where(function ($q) use ($searchTerm, $invoicePrefix, $estimatePrefix) {
                $q->where('estimates_invoices.id', 'like', $searchTerm)
                    ->orWhere('estimates_invoices.name', 'like', $searchTerm)
                    ->orWhere(DB::raw('CONCAT("' . $invoicePrefix . '", estimates_invoices.id)'), 'like', $searchTerm)
                    ->orWhere(DB::raw('CONCAT("' . $estimatePrefix . '", estimates_invoices.id)'), 'like', $searchTerm);
            });
        }


        // Apply sorting
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $query->orderBy($sort, $order);

        // Pagination setup
        $perPage = $request->input('limit', 10);
        $page = $request->input('offset', 0) / $perPage + 1;
        $total = $query->count();

        // Calculate totals
        $totalAmount = $query->sum('total');
        $totalTax = $query->sum('tax_amount');
        $totalFinal = $query->sum('final_total');

        $invoices = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
        // Transform invoice data into the desired report format
        $report = $invoices->map(function ($invoice) {
            // Determine the prefix based on the invoice type
            $prefix = $invoice->type == 'invoice' ? get_label('invoice_id_prefix', 'INVC-') : ($invoice->type == 'estimate' ? get_label('estimate_id_prefix', 'ESTMT-') : '');

            return [
                'id' => '<a href="' . route('estimates-invoices.view', ['id' => $invoice->id]) . '">' . $prefix . $invoice->id . '</a>',
                'type' => ucfirst($invoice->type),
                'client' => formatClientHtml($invoice->client),
                'total' => format_currency($invoice->total),
                'tax_amount' => format_currency($invoice->tax_amount),
                'final_total' => format_currency($invoice->final_total),
                'from_date' => format_date($invoice->from_date),
                'to_date' => format_date($invoice->to_date),
                'status' => $this->getStatusBadge($invoice->status),
                'created_by' => strpos($invoice->created_by, 'u_') === 0 ? formatUserHtml(User::find(substr($invoice->created_by, 2))) : formatClientHtml(Client::find(substr($invoice->created_by, 2))),
                'created_at' => format_date($invoice->created_at),
                'updated_at' => format_date($invoice->updated_at),
            ];
        });


        // Generate summary data
        $summary = [
            'total_invoices' => $total,
            'total_amount' => format_currency($totalAmount),
            'total_tax' => format_currency($totalTax),
            'total_final' => format_currency($totalFinal),
            'average_invoice_value' => $total > 0 ? format_currency($totalFinal / $total) : format_currency(0),
        ];

        return response()->json([
            'invoices' => $report,
            'total' => $total,
            'summary' => $summary,
        ]);
    }

    private function getStatusBadge($status)
    {
        // Generate status badge HTML based on status
        $badges = [
            'sent' => 'bg-primary',
            'accepted' => 'bg-success',
            'partially_paid' => 'bg-warning',
            'fully_paid' => 'bg-success',
            'draft' => 'bg-secondary',
            'declined' => 'bg-danger',
            'expired' => 'bg-warning',
            'not_specified' => 'bg-secondary',
            'due' => 'bg-danger'
        ];

        return isset($badges[$status]) ? '<span class="badge ' . $badges[$status] . '">' . get_label($status, ucfirst(str_replace('_', ' ', $status))) . '</span>' : '';
    }

    public function exportInvoicesReport(Request $request)
    {
        $invoicesData = $this->getInvoicesReportData($request)->getData();

        // Determine file name based on request type
        $fileName = get_label('estimates_and_invoices_report', 'Estimates and Invoices Report') . '.pdf'; // Default if both 'estimate' and 'invoice'
        $title = get_label('estimates_and_invoices_report', 'Estimates and Invoices Report');
        $type = get_label('estimates_and_invoices', 'Estimates and Invoices');
        if ($request->has('types')) {
            $types = $request->input('types');
            if (in_array('estimate', $types) && !in_array('invoice', $types)) {
                $fileName = get_label('estimates_report', 'Estimates Report') . '.pdf';
                $title = get_label('estimates_report', 'Estimates Report');
                $type = get_label('estimates', 'Estimates');
            } elseif (in_array('invoice', $types) && !in_array('estimate', $types)) {
                $fileName = get_label('invoices_report', 'Invoices Report') . '.pdf';
                $title = get_label('invoices_report', 'Invoices Report');
                $type = get_label('invoices', 'Invoices');
            }
        }

        $pdf = Pdf::loadView('reports.invoices-report-pdf', ['invoices' => $invoicesData->invoices, 'summary' => $invoicesData->summary, 'title' => $title, 'type' => $type])
            ->setPaper([0, 0, 2000, 900], 'mm');

        return $pdf->download($fileName);
    }
}
