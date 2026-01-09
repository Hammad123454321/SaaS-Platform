<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Item;
use App\Models\Note;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Models\Client;
use App\Models\Status;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Priority;
use App\Models\Allowance;
use App\Models\Candidate;
use App\Models\Deduction;
use App\Models\LeadStage;
use App\Models\Workspace;
use App\Models\LeadSource;
use App\Models\ExpenseType;
use App\Models\ContractType;
use Illuminate\Http\Request;
use App\Models\CandidateStatus;
use App\Models\EstimatesInvoice;
use Illuminate\Support\Facades\DB;
use ProtoneMedia\LaravelCrossEloquentSearch\Search;

class SearchController extends Controller
{

    public function search(Request $request)
    {
        $query = $request->input('q');
        $type = $request->input('type');
        $considerWorkspace = $request->input('considerWorkspace');
        $leaveVisibleToUsers = $request->input('leaveVisibleToUsers');
        $ignoreAdmins = $request->input('ignoreAdmins');
        $workspace_id = session('workspace_id');
        $authUser = getAuthenticatedUser();
        $results = [];

        if ($type) {
            // Handle single type search
            switch ($type) {
                case 'projects':
                    $projects = Project::where('title', 'like', '%' . $query . '%')
                        ->where('workspace_id', $workspace_id)
                        ->get();
                    foreach ($projects as $project) {
                        if (isAdminOrHasAllDataAccess() || $this->hasAccess($authUser, 'projects', Project::class, $project->id)) {
                            $results[] = [
                                'id' => $project->id,
                                'text' => $project->title
                            ];
                        }
                    }
                    break;
                case 'statuses':
                    $statuses = Status::where('title', 'like', '%' . $query . '%')->get();
                    foreach ($statuses as $status) {
                        $results[] = [
                            'id' => $status->id,
                            'text' => $status->title
                        ];
                    }
                    break;
                case 'priorities':
                    $priorities = Priority::where('title', 'like', '%' . $query . '%')
                        ->get();
                    foreach ($priorities as $priority) {
                        $results[] = [
                            'id' => $priority->id,
                            'text' => $priority->title
                        ];
                    }
                    break;


                case 'users':
                    $queryBuilder = User::query();
                    if ($considerWorkspace == "true") {
                        $queryBuilder->whereHas('workspaces', function ($queryBuilder) use ($workspace_id, $query) {
                            $queryBuilder->where('workspace_id', $workspace_id)
                                ->where(function ($subQuery) use ($query) {
                                    $subQuery->where('first_name', 'like', '%' . $query . '%')
                                        ->orWhere('last_name', 'like', '%' . $query . '%')
                                        ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $query . '%');
                                });
                        });
                    } else {
                        $queryBuilder->where(function ($subQuery) use ($query) {
                            $subQuery->where('first_name', 'like', '%' . $query . '%')
                                ->orWhere('last_name', 'like', '%' . $query . '%')
                                ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $query . '%');
                        });
                    }
                    $users = $queryBuilder->get();
                    foreach ($users as $user) {
                        // If leaveVisibleToUsers is true, include user only if not an admin or leave editor
                        if ($leaveVisibleToUsers == "true") {
                            if (!is_admin_or_leave_editor($user) && $authUser->id !== $user->id) {
                                $results[] = [
                                    'id' => $user->id,
                                    'text' => $user->first_name . ' ' . $user->last_name,
                                    'email' => $user->email,
                                ];
                            }
                        } elseif ($ignoreAdmins == "true") {
                            if ($user->hasRole('admin') == false) {
                                $results[] = [
                                    'id' => $user->id,
                                    'text' => $user->first_name . ' ' . $user->last_name,
                                    'email' => $user->email,
                                ];
                            }
                        } else {
                            // Include user as is if leaveVisibleToUsers is not true
                            $results[] = [
                                'id' => $user->id,
                                'text' => $user->first_name . ' ' . $user->last_name,
                                'email' => $user->email,
                            ];
                        }
                    }
                    break;

                case 'clients':
                    $queryBuilder = Client::query();
                    if ($considerWorkspace == "true") {
                        $queryBuilder->whereHas('workspaces', function ($queryBuilder) use ($workspace_id, $query) {
                            $queryBuilder->where('workspace_id', $workspace_id)
                                ->where(function ($subQuery) use ($query) {
                                    $subQuery->where('first_name', 'like', '%' . $query . '%')
                                        ->orWhere('last_name', 'like', '%' . $query . '%')
                                        ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $query . '%');
                                });
                        });
                    } else {
                        $queryBuilder->where(function ($subQuery) use ($query) {
                            $subQuery->where('first_name', 'like', '%' . $query . '%')
                                ->orWhere('last_name', 'like', '%' . $query . '%')
                                ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $query . '%');
                        });
                    }
                    $clients = $queryBuilder->get();
                    foreach ($clients as $client) {
                        $results[] = [
                            'id' => $client->id,
                            'text' => $client->first_name . ' ' . $client->last_name
                        ];
                    }
                    break;

                case 'contract_types':
                    $contract_types = ContractType::where('type', 'like', '%' . $query . '%')
                        ->where(function ($query) use ($workspace_id) {
                            $query->where('workspace_id', $workspace_id)
                                ->orWhere('workspace_id', 0)
                                ->orWhereNull('workspace_id'); // Add this line to handle null workspace_id
                        })
                        ->get();
                    foreach ($contract_types as $contract_type) {
                        $results[] = [
                            'id' => $contract_type->id,
                            'text' => $contract_type->type
                        ];
                    }
                    break;

                case 'expense_types':
                    $expense_types = ExpenseType::where('title', 'like', '%' . $query . '%')
                        ->where(function ($query) use ($workspace_id) {
                            $query->where('workspace_id', $workspace_id)
                                ->orWhere('workspace_id', 0)
                                ->orWhereNull('workspace_id'); // Add this line to handle null workspace_id
                        })
                        ->get();
                    foreach ($expense_types as $expense_type) {
                        $results[] = [
                            'id' => $expense_type->id,
                            'text' => $expense_type->title
                        ];
                    }
                    break;

                case 'tags':
                    $tags = Tag::where('title', 'like', '%' . $query . '%')->get();
                    foreach ($tags as $tag) {
                        $results[] = [
                            'id' => $tag->id,
                            'text' => $tag->title,
                            'color' => $tag->color // Include the color attribute for the tags
                        ];
                    }
                    break;

                case 'allowances':
                    $allowances = Allowance::where('title', 'like', '%' . $query . '%')
                        ->where(function ($query) use ($workspace_id) {
                            $query->where('workspace_id', $workspace_id);
                        })
                        ->get();
                    foreach ($allowances as $allowance) {
                        $results[] = [
                            'id' => $allowance->id,
                            'text' => $allowance->title
                        ];
                    }
                    break;

                case 'deductions':
                    $deductions = Deduction::where('title', 'like', '%' . $query . '%')
                        ->where(function ($query) use ($workspace_id) {
                            $query->where('workspace_id', $workspace_id);
                        })
                        ->get();
                    foreach ($deductions as $deduction) {
                        $results[] = [
                            'id' => $deduction->id,
                            'text' => $deduction->title
                        ];
                    }
                    break;

                case 'items':
                    $items = Item::where('title', 'like', '%' . $query . '%')
                        ->where(function ($query) use ($workspace_id) {
                            $query->where('workspace_id', $workspace_id);
                        })
                        ->get();
                    foreach ($items as $item) {
                        $results[] = [
                            'id' => $item->id,
                            'text' => $item->title
                        ];
                    }
                    break;

                case 'invoices':
                    // Extract the numeric part from the search query, removing any non-numeric characters
                    $searchQuery = preg_replace('/[^0-9]/', '', $query);

                    $invoices = EstimatesInvoice::where('id', 'like', '%' . $searchQuery . '%')
                        ->where('type', 'invoice')
                        ->where('workspace_id', $workspace_id)
                        ->get();

                    foreach ($invoices as $invoice) {
                        // Format the result to include the prefix "INV-"
                        $results[] = [
                            'id' => $invoice->id,
                            'text' => get_label('invoice_id_prefix', 'INVC-') . $invoice->id
                        ];
                    }

                    break;
                case 'lead_sources':
                    $lead_sources = LeadSource::where('name', 'like', '%' . $query . '%')
                        ->where(function ($query) use ($workspace_id) {
                        $query->where('workspace_id', $workspace_id)
                            ->orWhere(function ($q) {
                                $q->whereNull('workspace_id')->where('is_default', true);
                            });
                        })
                        ->get();

                    foreach ($lead_sources as $lead_source) {
                        $results[] = [
                            'id' => $lead_source->id,
                            'text' => $lead_source->name
                        ];
                    }
                    break;

                case 'lead_stages':
                    $lead_stages = LeadStage::where('name', 'like', '%' . $query . '%')
                        ->where(function ($query) use ($workspace_id) {
                        $query->where('workspace_id', $workspace_id)
                            ->orWhere(function ($q) {
                                $q->whereNull('workspace_id')->where('is_default', true);
                            });
                        })
                        ->get();

                    foreach ($lead_stages as $lead_stage) {
                        $results[] = [
                            'id' => $lead_stage->id,
                            'text' => $lead_stage->name
                        ];
                    }
                    break;



                case 'candidate_statuses':
                    $candidate_statuses = CandidateStatus::where('name', 'like', '%' . $query . '%')->get();
                    foreach ($candidate_statuses as $candidate_status) {
                        $results[] = [
                            'id' => $candidate_status->id,
                            'text' => $candidate_status->name
                        ];
                    }
                    break;

                case 'interview_candidates':
                    $interview_candidates = Candidate::where('name', 'like', '%' . $query . '%')->get();
                    foreach ($interview_candidates as $interview_candidate) {
                        $results[] = [
                            'id' => $interview_candidate->id,
                            'text' => $interview_candidate->name
                        ];
                    }
                    break;
                case 'interview_interviewer':
                    $interview_interviewers = User::where('first_name', 'like', '%' . $query . '%')
                        ->orWhere('last_name', 'like', '%' . $query . '%')
                        ->get();
                    foreach ($interview_interviewers as $interview_interviewer) {
                        $results[] = [
                            'id' => $interview_interviewer->id,
                            'text' => $interview_interviewer->first_name . " " . $interview_interviewer->last_name,
                        ];
                    }
                    break;


                default:
                    break;
            }
        } else {
            // Handle search across all types
            $results = [
                'projects' => [],
                'tasks' => [],
                'meetings' => [],
                'workspaces' => [],
                'users' => [],
                'clients' => [],
                'notes' => [],
                'todos' => [],
                'lead_sources' => []
            ];

            if ($authUser->can('manage_projects')) {
                $projects = Project::where('title', 'like', '%' . $query . '%')
                    ->where('workspace_id', $workspace_id)
                    ->get();
                foreach ($projects as $project) {
                    if (isAdminOrHasAllDataAccess() || $this->hasAccess($authUser, 'projects', Project::class, $project->id)) {
                        $results['projects'][] = [
                            'id' => $project->id,
                            'title' => $project->title
                        ];
                    }
                }
            }
            if ($authUser->can('manage_tasks')) {
                $tasks = Task::where('title', 'like', '%' . $query . '%')
                    ->where('workspace_id', $workspace_id)
                    ->get();
                foreach ($tasks as $task) {
                    if (isAdminOrHasAllDataAccess() || $this->hasAccess($authUser, 'tasks', Task::class, $task->id)) {
                        $results['tasks'][] = [
                            'id' => $task->id,
                            'title' => $task->title
                        ];
                    }
                }
            }

            if ($authUser->can('manage_meetings')) {
                $meetings = Meeting::where('title', 'like', '%' . $query . '%')
                    ->where('workspace_id', $workspace_id)
                    ->get();
                foreach ($meetings as $meeting) {
                    if (isAdminOrHasAllDataAccess() || $this->hasAccess($authUser, 'meetings', Meeting::class, $meeting->id)) {
                        $results['meetings'][] = [
                            'id' => $meeting->id,
                            'title' => $meeting->title
                        ];
                    }
                }
            }

            if ($authUser->can('manage_workspaces')) {
                $workspaces = Workspace::where('title', 'like', '%' . $query . '%')
                    ->get();
                foreach ($workspaces as $workspace) {
                    if (isAdminOrHasAllDataAccess() || $this->hasAccess($authUser, 'workspaces', Workspace::class, $workspace->id)) {
                        $results['workspaces'][] = [
                            'id' => $workspace->id,
                            'title' => $workspace->title
                        ];
                    }
                }
            }

            if ($authUser->can('manage_users')) {
                $users = User::whereHas('workspaces', function ($queryBuilder) use ($workspace_id, $query) {
                    $queryBuilder->where('workspace_id', $workspace_id)
                        ->where(function ($subQuery) use ($query) {
                            $subQuery->where('first_name', 'like', '%' . $query . '%')
                                ->orWhere('last_name', 'like', '%' . $query . '%')
                                ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $query . '%');
                        });
                })
                    ->get();
                foreach ($users as $user) {
                    $results['users'][] = [
                        'id' => $user->id,
                        'title' => $user->first_name . ' ' . $user->last_name
                    ];
                }
            }

            if ($authUser->can('manage_clients')) {
                $clients = Client::whereHas('workspaces', function ($queryBuilder) use ($workspace_id, $query) {
                    $queryBuilder->where('workspace_id', $workspace_id)
                        ->where(function ($subQuery) use ($query) {
                            $subQuery->where('first_name', 'like', '%' . $query . '%')
                                ->orWhere('last_name', 'like', '%' . $query . '%')
                                ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $query . '%');
                        });
                })
                    ->get();
                foreach ($clients as $client) {
                    $results['clients'][] = [
                        'id' => $client->id,
                        'title' => $client->first_name . ' ' . $client->last_name
                    ];
                }
            }

            $notes = $authUser->notes($query);
            $results['notes'] = $notes->map(function ($note) {
                return [
                    'id' => $note->id,
                    'title' => $note->title
                ];
            })->toArray();

            $todos = $authUser->todos(null, $query)->get();
            $results['todos'] = $todos->map(function ($todo) {
                return [
                    'id' => $todo->id,
                    'title' => $todo->title
                ];
            })->toArray();
        }

        return response()->json(['results' => $results]);
    }

    private function hasAccess($user, $typeKey, $typeModel, $itemId)
    {
        // Check if $user->$typeKey is a relationship or a collection
        if ($user->$typeKey() instanceof Illuminate\Database\Eloquent\Relations\Relation) {
            return $user->$typeKey->contains($typeModel::find($itemId));
        } else {
            return $user->$typeKey()->get()->contains($typeModel::find($itemId));
        }
    }
}
