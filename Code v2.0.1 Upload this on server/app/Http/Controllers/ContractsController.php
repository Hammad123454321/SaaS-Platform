<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Workspace;
use App\Models\ContractType;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ContractsController extends Controller
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
    public function index(Request $request)
    {
        $contracts = isAdminOrHasAllDataAccess() ? $this->workspace->contracts() : $this->user->contracts();
        $contracts = $contracts->count();
        return view('contracts.list', ['contracts' => $contracts]);
    }


    /**
     * Create a new contract.
     *
     * This endpoint creates a new contract with the specified details. The user must be authenticated to perform this action. If the user is a client, the client_id will be automatically set to their ID.
     *
     * @authenticated
     *
     * @group Contract Management
     *
     * @bodyParam title string required The title of the contract. Example: Web Development Contract
     * @bodyParam value string required The contract value in currency format. Must be a valid currency format. Example: 15,000.00
     * @bodyParam start_date string required The start date of the contract. Format depends on API usage (Y-m-d for API, system format for web). Example: 2024-01-01
     * @bodyParam end_date string required The end date of the contract. Must be after start_date. Example: 2024-12-31
     * @bodyParam client_id integer required The ID of the client. Must exist in the clients table. Example: 5
     * @bodyParam project_id integer required The ID of the project. Must exist in the projects table. Example: 12
     * @bodyParam contract_type_id integer required The ID of the contract type. Must exist in the contract_types table. Example: 3
     * @bodyParam description string optional Description of the contract. Example: Full-stack web development project
     * @bodyParam isApi boolean optional Set to true for API requests to handle date format properly. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Contract created successfully.",
     *   "data": {
     *     "id": 15,
     *     "title": "Web Development Contract",
     *     "value": "15000.00",
     *     "formatted_value": "$15,000.00",
     *     "start_date": "2024-01-01",
     *     "end_date": "2024-12-31",
     *     "client": {
     *       "id": 5,
     *       "name": "John Doe"
     *     },
     *     "project": {
     *       "id": 12,
     *       "title": "Company Website"
     *     },
     *     "contract_type": {
     *       "id": 3,
     *       "type": "Fixed Price"
     *     },
     *     "description": "Full-stack web development project",
     *     "status": "not_signed",
     *     "created_at": "2024-01-15T10:30:00Z"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "data": {
     *     "title": ["The title field is required."],
     *     "value": ["The value field is required."],
     *     "start_date": ["The start date must be before end date."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function store(Request $request)
    {
        try {
            $isApi = request()->get('isApi', false);

            if (isClient()) {
                $request->merge(['client_id' => $this->user->id]);
            }

            // Simple validation with Laravel's built-in date validation
            $formFields = $request->validate([
                'title' => ['required'],
                'value' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'value');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'client_id' => ['required', 'exists:clients,id'],
                'project_id' => ['required', 'exists:projects,id'],
                'contract_type_id' => ['required', 'exists:contract_types,id'],
                'description' => ['nullable']
            ], [
                'client_id.required' => 'The client field is required.',
                'project_id.required' => 'The project field is required.',
                'contract_type_id.required' => 'The contract type field is required.',
                'start_date.date' => 'The start date must be a valid date.',
                'end_date.date' => 'The end date must be a valid date.',
                'end_date.after_or_equal' => 'The end date must be after or equal to the start date.'
            ]);

            // Format dates for storage
            $formFields['start_date'] = format_date($formFields['start_date'], false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            $formFields['end_date'] = format_date($formFields['end_date'], false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            $formFields['value'] = str_replace(',', '', $request->input('value'));
            $formFields['workspace_id'] = $this->workspace->id;
            $formFields['created_by'] = isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id;

            if ($contract = Contract::create($formFields)) {
                if ($isApi) {
                    return formatApiResponse(
                        false,
                        'Contract created successfully.',
                        [
                            'data' => formatContract($contract)
                        ],
                        200
                    );
                }
                return response()->json(['error' => false, 'message' => 'Contract created successfully.', 'id' => $contract->id]);
            } else {
                if ($isApi) {
                    return formatApiResponse(
                        true,
                        'Contract couldn\'t created.',
                        []
                    );
                }
                return response()->json(['error' => true, 'message' => 'Contract couldn\'t created.']);
            }
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                'Contract couldn\'t created.',
                [
                    'data' => [
                        'error' => $e->getMessage(),
                        // 'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                    ]
                ],
            );
        }
    }
    /**
     * Update an existing contract.
     *
     * This endpoint updates an existing contract with new details. The user must be authenticated and have permission to edit contracts.
     *
     * @authenticated
     *
     * @group Contract Management
     *
     * @bodyParam id integer required The ID of the contract to update. Must exist in the contracts table. Example: 15
     * @bodyParam title string required The title of the contract. Example: Updated Web Development Contract
     * @bodyParam value string required The contract value in currency format. Must be a valid currency format. Example: 18,000.00
     * @bodyParam start_date string required The start date of the contract. Format depends on API usage. Example: 2024-01-01
     * @bodyParam end_date string required The end date of the contract. Must be after start_date. Example: 2024-12-31
     * @bodyParam client_id integer required The ID of the client. Must exist in the clients table. Example: 5
     * @bodyParam project_id integer required The ID of the project. Must exist in the projects table. Example: 12
     * @bodyParam contract_type_id integer required The ID of the contract type. Must exist in the contract_types table. Example: 3
     * @bodyParam description string optional Description of the contract. Example: Updated full-stack web development project
     * @bodyParam signed_pdf file optional The signed PDF file. Must be a PDF file, max 10MB. No example required.

     * @bodyParam isApi boolean optional Set to true for API requests to handle date format properly. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Contract updated successfully.",
     *   "data": {
     *     "id": 15,
     *     "title": "Updated Web Development Contract",
     *     "value": "18000.00",
     *     "formatted_value": "$18,000.00",
     *     "start_date": "2024-01-01",
     *     "end_date": "2024-12-31",
     *     "client": {
     *       "id": 5,
     *       "name": "John Doe"
     *     },
     *     "project": {
     *       "id": 12,
     *       "title": "Company Website"
     *     },
     *     "contract_type": {
     *       "id": 3,
     *       "type": "Fixed Price"
     *     },
     *     "description": "Updated full-stack web development project",
     *     "status": "partially_signed",
     *     "signed_pdf_url": "storage/contracts/signed_contract_123.pdf",
     *     "updated_at": "2024-01-20T14:45:00Z"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Contract not found.",
     *   "data": []
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "data": {
     *     "signed_pdf": ["The file must be a PDF.", "The file size must be less than 10 MB."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */
    public function update(Request $request)
    {
        try {
            $isApi = request()->get('isApi', false);

            // Validation (simplified and consistent with store)
            $formFields = $request->validate([
                'id' => 'required|exists:contracts,id',
                'title' => ['required'],
                'value' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'value');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'client_id' => ['required'],
                'project_id' => ['required'],
                'contract_type_id' => ['required'],
                'description' => ['nullable'],
                'signed_pdf' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240']
            ], [
                'client_id.required' => 'The client field is required.',
                'project_id.required' => 'The project field is required.',
                'contract_type_id.required' => 'The contract type field is required.',
                'signed_pdf.mimes' => 'The file must be a PDF.',
                'signed_pdf.max' => 'The file size must be less than 10 MB.',
                'start_date.date' => 'The start date must be a valid date.',
                'end_date.date' => 'The end date must be a valid date.',
                'end_date.after_or_equal' => 'The end date must be after or equal to the start date.'
            ]);

            // Fetch the contract to update
            $contract = Contract::findOrFail($formFields['id']);

            // Handle signed PDF if uploaded
            if ($request->hasFile('signed_pdf')) {
                if ($contract->signed_pdf && Storage::disk('public')->exists('contracts/' . $contract->signed_pdf)) {
                    Storage::disk('public')->delete('contracts/' . $contract->signed_pdf);
                }

                $file = $request->file('signed_pdf');
                $filePath = $file->store('contracts/', 'public');
                $formFields['signed_pdf'] = basename($filePath);
            }

            // Format dates for storage
            $formFields['start_date'] = format_date($formFields['start_date'], false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            $formFields['end_date'] = format_date($formFields['end_date'], false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');

            // Clean currency value
            $formFields['value'] = str_replace(',', '', $request->input('value'));

            // Update the contract
            if ($contract->update($formFields)) {
                if ($isApi) {
                    return formatApiResponse(
                        false,
                        'Contract updated successfully.',
                        [
                            'data' => formatContract($contract)
                        ],
                        200
                    );
                }

                return response()->json([
                    'error' => false,
                    'message' => 'Contract updated successfully.',
                    'id' => $formFields['id']
                ]);
            } else {
                if ($isApi) {
                    return formatApiResponse(true, 'Contract couldn\'t be updated.', [], 500);
                }

                return response()->json(['error' => true, 'message' => 'Contract couldn\'t be updated.']);
            }
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500
            );
        }
    }


    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $statuses = request('statuses', []);
        $type_ids = request('type_ids', []);
        $project_ids = request('project_ids', []);
        $client_ids = request('client_ids', []);
        $date_between_from = request('date_between_from') ?: "";
        $date_between_to = request('date_between_to') ?: "";
        $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
        $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
        $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
        $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";
        $where = ['contracts.workspace_id' => $this->workspace->id];

        $contracts = Contract::select(
            'contracts.*',
            DB::raw('CONCAT(clients.first_name, " ", clients.last_name) AS client_name'),
            'contract_types.type as contract_type',
            'projects.title as project_title'
        )
            ->leftJoin('users', 'contracts.created_by', '=', 'users.id')
            ->leftJoin('clients', 'contracts.client_id', '=', 'clients.id')
            ->leftJoin('contract_types', 'contracts.contract_type_id', '=', 'contract_types.id')
            ->leftJoin('projects', 'contracts.project_id', '=', 'projects.id');


        if (!isAdminOrHasAllDataAccess()) {
            $contracts = $contracts->where(function ($query) {
                $query->where('contracts.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('contracts.client_id', $this->user->id);
            });
        }

        if (!empty($project_ids)) {
            $contracts = $contracts->whereIn('contracts.project_id', $project_ids);
        }
        if (!empty($type_ids)) {
            $contracts = $contracts->whereIn('contracts.contract_type_id', $type_ids);
        }
        if (!empty($client_ids)) {
            $contracts = $contracts->whereIn('contracts.client_id', $client_ids);
        }
        if (!empty($statuses)) {
            $contracts = $contracts->where(function ($query) use ($statuses) {
                foreach ($statuses as $status) {
                    if ($status === 'partially_signed') {
                        $query->orWhere(function ($subquery) {
                            $subquery->where(function ($inner) {
                                $inner->whereNotNull('promisor_sign')
                                    ->whereNull('promisee_sign');
                            })
                                ->orWhere(function ($inner) {
                                    $inner->whereNull('promisor_sign')
                                        ->whereNotNull('promisee_sign');
                                });
                        });
                    } elseif ($status === 'signed') {
                        $query->orWhere(function ($subquery) {
                            $subquery->whereNotNull('promisor_sign')
                                ->whereNotNull('promisee_sign');
                        });
                    } elseif ($status === 'not_signed') {
                        $query->orWhere(function ($subquery) {
                            $subquery->whereNull('promisor_sign')
                                ->whereNull('promisee_sign');
                        });
                    }
                }
            });
        }
        if ($date_between_from && $date_between_to) {
            $contracts = $contracts->where('contracts.start_date', '>=', $date_between_from)
                ->where('contracts.end_date', '<=', $date_between_to);
        }
        if ($start_date_from && $start_date_to) {
            $contracts = $contracts->whereBetween('contracts.start_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $contracts  = $contracts->whereBetween('contracts.end_date', [$end_date_from, $end_date_to]);
        }
        if ($search) {
            $contracts = $contracts->where(function ($query) use ($search) {
                $query->where('contracts.title', 'like', '%' . $search . '%')
                    ->orWhere('value', 'like', '%' . $search . '%')
                    ->orWhere('contracts.description', 'like', '%' . $search . '%')
                    ->orWhere('contracts.id', 'like', '%' . $search . '%')
                    ->orWhere(DB::raw('CONCAT("' . get_label('contract_id_prefix', 'Contract ID prefix') . '", contracts.id)'), 'like', '%' . $search . '%');
            });
        }

        $contracts->where($where);
        $total = $contracts->count();

        $canCreate = checkPermission('create_contracts');
        $canEdit = checkPermission('edit_contracts');
        $canDelete = checkPermission('delete_contracts');

        $contracts = $contracts->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($contract) use ($canEdit, $canDelete, $canCreate) {
                // Format "from_date" and "to_date" with labels
                $formattedDates = format_date($contract->start_date, false) . ' ' . get_label('to', 'To') . ' ' . format_date($contract->end_date, false);

                $promisorSign = $contract->promisor_sign;
                $promiseeSign = $contract->promisee_sign;

                $statusBadge = '';

                $promisor_sign_status = !is_null($promisorSign) ? '<span class="badge bg-success">' . get_label('signed', 'Signed') . '</span>' : '<span class="badge bg-danger">' . get_label('not_signed', 'Not signed') . '</span>';
                $promisee_sign_status = !is_null($promiseeSign) ? '<span class="badge bg-success">' . get_label('signed', 'Signed') . '</span>' : '<span class="badge bg-danger">' . get_label('not_signed', 'Not signed') . '</span>';

                if (!is_null($promisorSign) && !is_null($promiseeSign)) {
                    $statusBadge = '<span class="badge bg-success">' . get_label('signed', 'Signed') . '</span>';
                } elseif (!is_null($promisorSign) || !is_null($promiseeSign)) {
                    $statusBadge = '<span class="badge bg-warning">' . get_label('partially_signed', 'Partially signed') . '</span>';
                } else {
                    $statusBadge = '<span class="badge bg-danger">' . get_label('not_signed', 'Not signed') . '</span>';
                }

                $actions = '';
                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-contract" data-bs-toggle="modal" data-bs-target="#edit_contract_modal" data-id="' . $contract->id . '" title="' . get_label('update', 'Update') . '"><i class="bx bx-edit mx-1"></i></a>';
                }
                if ($canDelete) {
                    $actions .= '<button title=' . get_label('delete', 'Delete') . ' type="button" class="btn delete" data-id="' . $contract->id . '" data-type="contracts" data-table="contracts_table"><i class="bx bx-trash text-danger"></i></button>';
                }
                if ($canCreate) {
                    $actions .= '<a href="javascript:void(0);" class="duplicate" data-id="' . $contract->id . '" data-title="' . $contract->title . '" data-type="contracts" data-table="contracts_table" title=' . get_label('duplicate', 'Duplicate') . '><i class="bx bx-copy text-warning mx-2"></i></a>';
                }
                // Check if signed PDF exists
                if (isset($contract->signed_pdf) && !empty($contract->signed_pdf) && Storage::disk('public')->exists('contracts/' . $contract->signed_pdf)) {
                    $actions .= '<a href="' . Storage::url('contracts/' . $contract->signed_pdf) . '" title="' . get_label('contract_pdf', 'Contract PDF') . '" target="_blank"><i class="bx bx-file text-success ms-4"></i></a>';
                }
                $actions = $actions ?: '-';
                return [
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'value' => format_currency($contract->value),
                    'start_date' => format_date($contract->start_date),
                    'end_date' => format_date($contract->end_date),
                    'duration' => $formattedDates,
                    'client' => formatClientHtml($contract->client),
                    'project' => "<a href='" . route('projects.info', ['id' => $contract->project_id]) . "'>{$contract->project_title}</a>",
                    'contract_type' => $contract->contract_type,
                    'description' => $contract->description,
                    'promisor_sign' => $promisor_sign_status,
                    'promisee_sign' => $promisee_sign_status,
                    'status' => $statusBadge,
                    'created_by' => strpos($contract->created_by, 'u_') === 0 ? formatUserHtml(User::find(substr($contract->created_by, 2))) : formatClientHtml(Client::find(substr($contract->created_by, 2))),
                    'created_at' => format_date($contract->created_at, true),
                    'updated_at' => format_date($contract->updated_at, true),
                    'actions' => $actions
                ];
            });


        return response()->json([
            "rows" => $contracts->items(),
            "total" => $total,
        ]);
    }

    /**
     * List contracts with filtering and pagination.
     *
     * This endpoint retrieves a paginated list of contracts with optional search, sorting, and filtering capabilities. The user must be authenticated to perform this action. Access is restricted based on user permissions.
     *
     * @authenticated
     *
     * @group Contract Management
     *
     * @queryParam search string optional Filters contracts by title, value, description, or contract ID. Example: Web Development
     * @queryParam sort string optional The field to sort by (id, title, value, start_date, end_date, created_at). Defaults to id. Example: start_date
     * @queryParam order string optional The sort order (ASC or DESC). Defaults to DESC. Example: ASC
     * @queryParam limit integer optional The number of contracts per page (1-100). Defaults to 10. Example: 20
     * @queryParam statuses array optional Filters contracts by status (signed, partially_signed, not_signed). Example: ["signed", "partially_signed"]
     * @queryParam type_ids array optional Filters contracts by contract type IDs. Each ID must exist in contract_types table. Example: [1, 3, 5]
     * @queryParam project_ids array optional Filters contracts by project IDs. Each ID must exist in projects table. Example: [12, 15, 18]
     * @queryParam client_ids array optional Filters contracts by client IDs. Each ID must exist in clients table. Example: [5, 8]
     * @queryParam date_between_from string optional Filter contracts that start on or after this date. Format: Y-m-d. Example: 2024-01-01
     * @queryParam date_between_to string optional Filter contracts that end on or before this date. Format: Y-m-d. Example: 2024-12-31
     * @queryParam start_date_from string optional Filter contracts with start date from this date. Format: Y-m-d. Example: 2024-01-01
     * @queryParam start_date_to string optional Filter contracts with start date until this date. Format: Y-m-d. Example: 2024-06-30
     * @queryParam end_date_from string optional Filter contracts with end date from this date. Format: Y-m-d. Example: 2024-06-01
     * @queryParam end_date_to string optional Filter contracts with end date until this date. Format: Y-m-d. Example: 2024-12-31
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Contracts retrieved successfully!",
     *   "total": 25,
     *   "data": [
     *     {
     *       "id": 15,
     *       "title": "Web Development Contract",
     *       "value": "15000.00",
     *       "formatted_value": "$15,000.00",
     *       "start_date": "2024-01-01",
     *       "end_date": "2024-12-31",
     *       "duration": "Jan 01, 2024 To Dec 31, 2024",
     *       "client": {
     *         "id": 5,
     *         "name": "John Doe",
     *         "email": "john@example.com"
     *       },
     *       "project": {
     *         "id": 12,
     *         "title": "Company Website"
     *       },
     *       "contract_type": {
     *         "id": 3,
     *         "type": "Fixed Price"
     *       },
     *       "description": "Full-stack web development project",
     *       "status": "signed",
     *       "promisor_signed": true,
     *       "promisee_signed": true,
     *       "signed_pdf_url": "storage/contracts/signed_contract_123.pdf",
     *       "created_by": {
     *         "id": 1,
     *         "name": "Admin User",
     *         "type": "user"
     *       },
     *       "created_at": "2024-01-15T10:30:00Z",
     *       "updated_at": "2024-01-20T14:45:00Z"
     *     }
     *   ]
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "data": {
     *     "limit": ["The limit must be between 1 and 100."],
     *     "start_date_from": ["The start date from must be a valid date."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function apiList()
    {
        try {
            $search = request('search');
            $sort = (request('sort')) ? request('sort') : "id";
            $order = (request('order')) ? request('order') : "DESC";
            $limit = (request('limit')) ? request('limit') : 10;
            $statuses = request('statuses', []);
            $type_ids = request('type_ids', []);
            $project_ids = request('project_ids', []);
            $client_ids = request('client_ids', []);
            $date_between_from = request('date_between_from') ?: "";
            $date_between_to = request('date_between_to') ?: "";
            $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
            $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
            $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
            $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";
            $where = ['contracts.workspace_id' => $this->workspace->id];

            $contracts = Contract::select(
                'contracts.*',
                DB::raw('CONCAT(clients.first_name, " ", clients.last_name) AS client_name'),
                'contract_types.type as contract_type',
                'projects.title as project_title'
            )
                ->leftJoin('users', 'contracts.created_by', '=', 'users.id')
                ->leftJoin('clients', 'contracts.client_id', '=', 'clients.id')
                ->leftJoin('contract_types', 'contracts.contract_type_id', '=', 'contract_types.id')
                ->leftJoin('projects', 'contracts.project_id', '=', 'projects.id');

            if (!isAdminOrHasAllDataAccess()) {
                $contracts = $contracts->where(function ($query) {
                    $query->where('contracts.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                        ->orWhere('contracts.client_id', $this->user->id);
                });
            }

            if (!empty($project_ids)) {
                $contracts = $contracts->whereIn('contracts.project_id', $project_ids);
            }
            if (!empty($type_ids)) {
                $contracts = $contracts->whereIn('contracts.contract_type_id', $type_ids);
            }
            if (!empty($client_ids)) {
                $contracts = $contracts->whereIn('contracts.client_id', $client_ids);
            }
            if (!empty($statuses)) {
                $contracts = $contracts->where(function ($query) use ($statuses) {
                    foreach ($statuses as $status) {
                        if ($status === 'partially_signed') {
                            $query->orWhere(function ($subquery) {
                                $subquery->where(function ($inner) {
                                    $inner->whereNotNull('promisor_sign')
                                        ->whereNull('promisee_sign');
                                })
                                    ->orWhere(function ($inner) {
                                        $inner->whereNull('promisor_sign')
                                            ->whereNotNull('promisee_sign');
                                    });
                            });
                        } elseif ($status === 'signed') {
                            $query->orWhere(function ($subquery) {
                                $subquery->whereNotNull('promisor_sign')
                                    ->whereNotNull('promisee_sign');
                            });
                        } elseif ($status === 'not_signed') {
                            $query->orWhere(function ($subquery) {
                                $subquery->whereNull('promisor_sign')
                                    ->whereNull('promisee_sign');
                            });
                        }
                    }
                });
            }
            if ($date_between_from && $date_between_to) {
                $contracts = $contracts->where('contracts.start_date', '>=', $date_between_from)
                    ->where('contracts.end_date', '<=', $date_between_to);
            }
            if ($start_date_from && $start_date_to) {
                $contracts = $contracts->whereBetween('contracts.start_date', [$start_date_from, $start_date_to]);
            }
            if ($end_date_from && $end_date_to) {
                $contracts = $contracts->whereBetween('contracts.end_date', [$end_date_from, $end_date_to]);
            }
            if ($search) {
                $contracts = $contracts->where(function ($query) use ($search) {
                    $query->where('contracts.title', 'like', '%' . $search . '%')
                        ->orWhere('value', 'like', '%' . $search . '%')
                        ->orWhere('contracts.description', 'like', '%' . $search . '%')
                        ->orWhere('contracts.id', 'like', '%' . $search . '%')
                        ->orWhere(DB::raw('CONCAT("' . get_label('contract_id_prefix', 'Contract ID prefix') . '", contracts.id)'), 'like', '%' . $search . '%');
                });
            }

            $contracts->where($where);
            $total = $contracts->count();

            $contracts = $contracts->orderBy($sort, $order)
                ->take($limit)
                ->get()
                ->map(function ($contract) {
                $formattedDates = format_date($contract->start_date, to_format: 'Y-m-d') . ' ' . get_label('to', 'To') . ' ' . format_date($contract->end_date, to_format: 'Y-m-d');
                $data = formatContract($contract);
                $data['duration'] = $formattedDates;
                    return $data;
                });

            return formatApiResponse(
                false,
                'Contracts retrieved successfully!',
                [
                    'total' => $total,
                    'data' => $contracts
                ],
                200
            );
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred.',
                [],
                500
            );
        }
    }

    /**
     * Retrieve a single contract by ID.
     *
     * This endpoint retrieves detailed information about a specific contract. The user must be authenticated and have access to the contract based on their permissions.
     *
     * @authenticated
     *
     * @group Contract Management
     *
     * @urlParam id integer required The ID of the contract to retrieve. Must exist in the contracts table. Example: 15
     * @queryParam isApi boolean optional Set to true for API requests. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Contract retrieved successfully.",
     *   "data": {
     *     "id": 15,
     *     "title": "Web Development Contract",
     *     "value": "15000.00",
     *     "formatted_value": "$15,000.00",
     *     "start_date": "2024-01-01",
     *     "end_date": "2024-12-31",
     *     "client": {
     *       "id": 5,
     *       "name": "John Doe",
     *       "email": "john@example.com",
     *       "phone": "+1234567890"
     *     },
     *     "project": {
     *       "id": 12,
     *       "title": "Company Website",
     *       "description": "Modern responsive website"
     *     },
     *     "contract_type": {
     *       "id": 3,
     *       "type": "Fixed Price"
     *     },
     *     "description": "Full-stack web development project",
     *     "status": "signed",
     *     "promisor_signed": true,
     *     "promisee_signed": true,
     *     "promisor_sign_date": "2024-01-18T09:15:00Z",
     *     "promisee_sign_date": "2024-01-19T14:22:00Z",
     *     "signed_pdf_url": "storage/contracts/signed_contract_123.pdf",
     *     "created_by": {
     *       "id": 1,
     *       "name": "Admin User",
     *       "type": "user"
     *     },
     *     "workspace_id": 1,
     *     "created_at": "2024-01-15T10:30:00Z",
     *     "updated_at": "2024-01-20T14:45:00Z"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Contract not found.",
     *   "data": []
     * }
     *
     * @response 403 {
     *   "error": true,
     *   "message": "Access denied.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function get($id)
    {
        try {

            $isApi = request()->get('isApi', false);
            $contract = Contract::with(['client', 'project', 'contract_type'])->findOrFail($id);
            $contract->value = format_currency($contract->value, false, false);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Contract retrieved successfully.',
                    [
                        'data' => formatContract($contract)
                    ]
                );
            }

            return response()->json(['error' => false, 'contract' => $contract]);
        } catch (ModelNotFoundException $e) {

            return formatApiResponse(true, 'Contract not found.', [], 404);
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500
            );
        }
    }

    public function duplicate($id)
    {
        // Use the general duplicateRecord function
        $title = (request()->has('title') && !empty(trim(request()->title))) ? request()->title : '';
        $duplicate = duplicateRecord(Contract::class, $id, [], $title);

        if (!$duplicate) {
            return response()->json(['error' => true, 'message' => 'Contract duplication failed.']);
        }
        return response()->json(['error' => false, 'id' => $id, 'message' => 'Contract duplicated successfully.']);
    }

    public function sign(Request $request, $id)
    {
        $contract = Contract::select(
            'contracts.*',
            'clients.id as client_id',
            'contracts.created_by as created_by_id',
            DB::raw('CONCAT(clients.first_name, " ", clients.last_name) AS client_name'),
            'contract_types.type as contract_type',
            'projects.title as project_title',
            'projects.id as project_id'
        )->where('contracts.id', '=', $id)
            ->leftJoin('users', 'contracts.created_by', '=', 'users.id')
            ->leftJoin('clients', 'contracts.client_id', '=', 'clients.id')
            ->leftJoin('contract_types', 'contracts.contract_type_id', '=', 'contract_types.id')
            ->leftJoin('projects', 'contracts.project_id', '=', 'projects.id')->first();

        if (strpos($contract->created_by, 'u_') === 0) {
            // The ID corresponds to a user
            $creator = User::find(substr($contract->created_by, 2)); // Remove the 'u_' prefix
            if ($creator !== null) {
                if (checkPermission('manage_users')) {
                    $contract->creator = '<a href="' . route('users.profile', ['id' => $creator->id]) . '">' . $creator->first_name . ' ' . $creator->last_name . '</a>';
                } else {
                    $contract->creator = $creator->first_name . ' ' . $creator->last_name;
                }
            }
        } elseif (strpos($contract->created_by, 'c_') === 0) {
            // The ID corresponds to a client
            $creator = Client::find(substr($contract->created_by, 2)); // Remove the 'c_' prefix
            if ($creator !== null) {
                if (checkPermission('manage_clients')) {
                    $contract->creator = '<a href="' . url('/clients/profile/' . $creator->id) . '">' . $creator->first_name . ' ' . $creator->last_name . '</a>';
                } else {
                    $contract->creator = $creator->first_name . ' ' . $creator->last_name;
                }
            }
        }

        if (!isset($contract->creator)) {
            $contract->creator = '-';
        }

        return view('contracts.sign', compact('contract'));
    }


    /**
     * Sign a contract with a base64 image.
     *
     * This endpoint allows an authenticated user to sign a contract by uploading a base64-encoded signature image. The user must have appropriate permissions based on their role (admin or client). The image is saved, and the corresponding signature field in the contract is updated.
     *
     * @authenticated
     *
     * @group Contract Management
     *
     * @bodyParam id integer required The ID of the contract to sign. Example: 12
     * @bodyParam signatureImage string required A base64-encoded PNG image string of the signature. Must include the data URI prefix (e.g., "data:image/png;base64,..."). Example: data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...
     * @queryParam isApi boolean optional If true, returns a formatted API response instead of a regular JSON response. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "id": 12,
     *   "activity_message": "John Doe signed contract NDA Agreement"
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The id field is required. (and 1 more error)"
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */


    public function create_sign(Request $request)
    {

        try {

            $isApi = request()->get('isApi', false);

            $formFields = $request->validate([
                'id' => 'required',
                'signatureImage' => 'required'
            ]);
            $contract = Contract::findOrFail($formFields['id']);
            $base64Data = $request->input('signatureImage');
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Data));
            // $imageData = base64_decode($base64Data);
            $filename = 'signature_' . uniqid() . '.png';
            Storage::put('public/signatures/' . $filename, $imageData);
            $signedAs = null;
            if (($this->user->id == $contract->created_by || isAdminOrHasAllDataAccess()) && !isClient()) {
                $contract->promisor_sign = $filename;
                $signedAs = 'promisor';
            } elseif (($this->user->id == $contract->client_id) && isClient()) {
                $contract->promisee_sign = $filename;
                $signedAs = 'promisee';
            }
            if ($contract->save()) {
                Session::flash('message', 'Signature created successfully.');

                return formatApiResponse(
                    false,
                    trim($this->user->first_name) . ' ' . trim($this->user->last_name) . " signed contract {$contract->title} as {$signedAs}.",
                    [
                        'id' => $formFields['id'],
                        'signed_as' => $signedAs
                    ],
                    200
                );


                return response()->json(['error' => false, 'id' => $formFields['id'], 'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' signed contract ' . trim($contract->title)]);
            } else {

                return formatApiResponse(
                    true,
                    'Signature couldn\'t created.',
                    []
                );

                return response()->json(['error' => true, 'message' => 'Signature couldn\'t created.']);
            }
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred.',
                [],
                500
            );
        }
    }


    /**
     * Remove signature from a contract.
     *
     * This endpoint allows authorized users to remove their signature from a contract, effectively "unsigned" the contract.
     *
     * @authenticated
     *
     * @group Contract Management
     *
     * @urlParam id integer required The ID of the contract to remove signature from. Must exist in the contracts table. Example: 15
     * @queryParam isApi boolean optional Set to true for API requests. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "John Doe unsigned contract Web Development Contract",
     *   "id": 15
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Contract not found.",
     *   "data": []
     * }
     *
     * @response 403 {
     *   "error": true,
     *   "message": "Unauthorized access.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function delete_sign($id)
    {

        try {

            $isApi = request()->get('isApi', false);

            $contract = Contract::findOrFail($id);
            if (($this->user->id == str_replace('u_', "", $contract->created_by) || isAdminOrHasAllDataAccess()) && !isClient()) {
                Storage::delete('public/signatures/' . $contract->promisor_sign);
                Contract::where('id', $id)->update(['promisor_sign' => null]);
                Session::flash('message', 'Signature deleted successfully.');

                if ($isApi) {
                    return formatApiResponse(
                        false,
                        trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' unsigned contract ' . trim($contract->title),
                        [
                            'id' => $id
                        ]

                    );
                }

                return response()->json(['error' => false, 'id' => $id, 'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' unsigned contract ' . trim($contract->title)]);
            } elseif ($this->user->id == $contract->client_id && isClient()) {
                Storage::delete('public/signatures/' . $contract->promisee_sign);
                Contract::where('id', $id)->update(['promisee_sign' => null]);
                Session::flash('message', 'Signature deleted successfully.');

                if ($isApi) {
                    return formatApiResponse(
                        false,
                        trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' unsigned contract ' . trim($contract->title),
                        [
                            'id' => $id
                        ]
                    );
                }

                return response()->json(['error' => false, 'id' => $id, 'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' unsigned contract ' . trim($contract->title)]);
            } else {
                Session::flash('error', 'Un authorized access.');


                return response()->json(['error' => true]);
            }
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500
            );
        }
    }

    /**
     * Delete a contract and associated files.
     *
     * This endpoint deletes a contract by its ID, including associated signature images and the signed PDF file (if any). Only authorized users can perform this operation.
     *
     * @authenticated
     *
     * @group Contract Management
     *
     * @urlParam id integer required The ID of the contract to delete. Example: 15
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Contract deleted successfully!",
     *   "data": []
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Contract not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred",
     *   "data": []
     * }
     */


    public function destroy($id)
    {

        try {

            $contract = Contract::findOrFail($id);
            if ($response = DeletionService::delete(Contract::class, $id, 'Contract')) {
                Storage::delete('public/signatures/' . $contract->promisor_sign);
                Storage::delete('public/signatures/' . $contract->promisee_sign);
                // Check if the contract has a signed PDF and delete it
                if ($contract->signed_pdf && Storage::disk('public')->exists('contracts/' . $contract->signed_pdf)) {
                    Storage::disk('public')->delete('contracts/' . $contract->signed_pdf);
                }
            }
            return $response;
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500
            );
        }
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:contracts,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedContracts = [];
        $deletedContractTitles = [];
        // Perform deletion using validated IDs

        foreach ($ids as $id) {
            $contract = Contract::findOrFail($id);
            if ($contract) {
                $deletedContracts[] = $id;
                $deletedContractTitles[] = $contract->title;
                if (DeletionService::delete(Contract::class, $id, 'Contract')) {
                    Storage::delete('public/signatures/' . $contract->promisor_sign);
                    Storage::delete('public/signatures/' . $contract->promisee_sign);
                    // Check and delete signed PDF
                    if ($contract->signed_pdf && Storage::disk('public')->exists('contracts/' . $contract->signed_pdf)) {
                        Storage::disk('public')->delete('contracts/' . $contract->signed_pdf);
                    }
                }
            }
        }
        return response()->json(['error' => false, 'message' => 'Contract(s) deleted successfully.', 'id' => $deletedContracts, 'titles' => $deletedContractTitles]);
    }

    public function contract_types(Request $request)
    {

        $contract_types = ContractType::forWorkspace($this->workspace->id);
        $contract_types = $contract_types->count();
        return view('contracts.contract_types', ['contract_types' => $contract_types]);
    }


    /**
     * Create a new contract type.
     *
     * This endpoint creates a new contract type that can be used when creating contracts. The user must be authenticated and have permission to create contract types.
     *
     * @authenticated
     *
     * @group Contract Type Management
     *
     * @bodyParam type string required The name of the contract type. Must be unique. Example: Hourly Rate
     * @bodyParam isApi boolean optional Set to true for API requests. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Contract type created successfully.",
     *   "data": {
     *     "id": 5,
     *     "type": "Hourly Rate",
     *     "workspace_id": 1,
     *     "created_at": "2024-01-15T10:30:00Z",
     *     "updated_at": "2024-01-15T10:30:00Z"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "data": {
     *     "type": ["The type field is required.", "The type has already been taken."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function store_contract_type(Request $request)
    {

        try {

            $isApi = request()->get('isApi', false);



            // Validate the request data
            $formFields = $request->validate([
                'type' => 'required|unique:contract_types,type', // Validate the type
            ]);
            $formFields['workspace_id'] = $this->workspace->id;

            if ($ct = ContractType::create($formFields)) {

                if ($isApi) {

                    return formatApiResponse(
                        false,
                        'Contract type created successfully.',
                        [
                            'data' => formatContractType($ct)
                        ],
                        200
                    );
                }

                return response()->json(
                    [
                        'error' => false,
                        'message' => 'Contract type created     successfully.',
                        'type' => 'contract_type',
                        'data' => [
                            'id' => $ct->id,
                            'name' => $ct->type,
                        ],
                        'id' => $ct->id,
                        'ct' => $ct
                    ]
                );
            } else {

                if ($isApi) {
                    return formatApiResponse(
                        true,
                        'Contract type couldn\'t created.',
                        []
                    );
                }

                return response()->json(['error' => true, 'message' => 'Contract type couldn\'t created.']);
            }
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500

            );
        }
    }

    public function contract_types_list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $contract_types = ContractType::forWorkspace($this->workspace->id);
        if ($search) {
            $contract_types = $contract_types->where(function ($query) use ($search) {
                $query->where('type', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        $total = $contract_types->count();
        $canEdit = checkPermission('edit_contract_types');
        $canDelete = checkPermission('delete_contract_types');
        $contract_types = $contract_types->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($contract_type) use ($canEdit, $canDelete) {
                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-contract-type" data-bs-target="#edit_contract_type_modal" data-id="' . $contract_type->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $contract_type->id . '" data-type="contract-type">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                $actions = $actions ?: '-';

                return [
                    'id' => $contract_type->id,
                    'type' => $contract_type->type . ($contract_type->id == 0 ? ' <span class="badge bg-success">' . get_label('default', 'Default') . '</span>' : ''),
                    'created_at' => format_date($contract_type->created_at, true),
                    'updated_at' => format_date($contract_type->updated_at, true),
                    'actions' => $actions,
                ];
            });

        return response()->json([
            "rows" => $contract_types->items(),
            "total" => $total,
        ]);
    }


    /**
     * List contract types with filtering and pagination.
     *
     * This endpoint retrieves a paginated list of contract types with optional search and sorting capabilities. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Contract Type Management
     *
     * @queryParam search string optional Filters contract types by type name or ID. Example: Fixed
     * @queryParam sort string optional The field to sort by (id, type, created_at, updated_at). Defaults to id. Example: type
     * @queryParam order string optional The sort order (ASC or DESC). Defaults to DESC. Example: ASC
     * @queryParam limit integer optional The number of contract types per page (1-100). Defaults to 10. Example: 20
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Contract types retrieved successfully.",
     *   "total": 8,
     *   "data": [
     *     {
     *       "id": 1,
     *       "type": "Fixed Price",
     *       "workspace_id": 1,
     *       "created_at": "2024-01-01T08:00:00Z",
     *       "updated_at": "2024-01-01T08:00:00Z"
     *     },
     *     {
     *       "id": 2,
     *       "type": "Time & Materials",
     *       "workspace_id": 1,
     *       "created_at": "2024-01-02T09:15:00Z",
     *       "updated_at": "2024-01-02T09:15:00Z"
     *     },
     *     {
     *       "id": 3,
     *       "type": "Monthly Retainer",
     *       "workspace_id": 1,
     *       "created_at": "2024-01-03T10:30:00Z",
     *       "updated_at": "2024-01-03T10:30:00Z"
     *     }
     *   ]
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "data": {
     *     "limit": ["The limit must be between 1 and 100."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function contract_types_apiList()
    {

        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $limit = (request('limit')) ? request('limit') : 10;
        $contract_types = ContractType::forWorkspace($this->workspace->id);
        if ($search) {
            $contract_types = $contract_types->where(function ($query) use ($search) {
                $query->where('type', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        $total = $contract_types->count();

        $contract_types = $contract_types->orderBy($sort, $order)
            ->take($limit)
            ->get()
            ->map(function ($contract_type) {


                return formatContractType($contract_type);
            });

        return formatApiResponse(
            false,
            'Contract types retrieved successfully.',
            [
                'total' => $total,
                'data' => $contract_types
            ]
        );
    }


    /**
     * Retrieve a single contract type by ID.
     *
     * This endpoint retrieves detailed information about a specific contract type. The user must be authenticated and have access to the contract type.
     *
     * @authenticated
     *
     * @group Contract Type Management
     *
     * @urlParam id integer required The ID of the contract type to retrieve. Must exist in the contract_types table. Example: 3
     * @queryParam isApi boolean optional Set to true for API requests. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Contract type retrieved successfully.",
     *   "data": {
     *     "id": 3,
     *     "type": "Monthly Retainer",
     *     "workspace_id": 1,
     *     "created_at": "2024-01-03T10:30:00Z",
     *     "updated_at": "2024-01-03T10:30:00Z"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Contract type not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function get_contract_type($id)
    {
        try {

            $isApi = request()->get('isApi', false);

            $ct = ContractType::findOrFail($id);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Contract type retrieved successfully.',
                    [
                        'data' => formatContractType($ct)
                    ],
                    200
                );
            }

            return response()->json(['ct' => $ct]);
        } catch (\Exception $e) {

            return formatApiResponse(
                false,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500
            );
        }
    }

    /**
     * Update an existing contract type.
     *
     * This endpoint updates an existing contract type with new details. The user must be authenticated and have permission to edit contract types.
     *
     * @authenticated
     *
     * @group Contract Type Management
     *
     * @bodyParam id integer required The ID of the contract type to update. Must exist in the contract_types table. Example: 3
     * @bodyParam type string required The updated name of the contract type. Must be unique (excluding current record). Example: Updated Monthly Retainer
     * @bodyParam isApi boolean optional Set to true for API requests. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Contract type updated successfully.",
     *   "data": {
     *     "id": 3,
     *     "type": "Updated Monthly Retainer",
     *     "workspace_id": 1,
     *     "created_at": "2024-01-03T10:30:00Z",
     *     "updated_at": "2024-01-20T15:45:00Z"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Contract type not found.",
     *   "data": []
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "data": {
     *     "type": ["The type field is required.", "The type has already been taken."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function update_contract_type(Request $request)
    {

        try {

            $isApi = request()->get('isApi', false);

            $formFields = $request->validate([
                'id' => ['required'],
                'type' => 'required|unique:contract_types,type,' . $request->id,
            ]);
            $ct = ContractType::findOrFail($request->id);
            if ($ct->update($formFields)) {

                if ($isApi) {
                    return formatApiResponse(
                        false,
                        'Contract type updated successfully.',
                        [
                            'data' => $ct
                        ],
                        200
                    );
                }

                return response()->json(['error' => false, 'message' => 'Contract type updated successfully.', 'id' => $ct->id, 'title' => $formFields['type'], 'type' => 'contract_type']);
            } else {

                if ($isApi) {
                    return formatApiResponse(
                        true,
                        'Contract type couldn\'t updated.',
                        []
                    );
                }

                return response()->json(['error' => true, 'message' => 'Contract type couldn\'t updated.']);
            }
        } catch (\Exception $e) {
            formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500
            );
        }
    }


    /**
     * Delete a contract type.
     *
     * This endpoint deletes a contract type. Any contracts using this type will be updated to use the default contract type (ID: 0). The user must be authenticated and have permission to delete contract types.
     *
     * @authenticated
     *
     * @group Contract Type Management
     *
     * @urlParam id integer required The ID of the contract type to delete. Must exist in the contract_types table and cannot be the default type (ID: 0). Example: 5
     * @queryParam isApi boolean optional Set to true for API requests. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Contract type deleted successfully.",
     *   "data": {
     *     "id": 5,
     *     "type": "Hourly Rate",
     *     "workspace_id": 1
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Contract type not found.",
     *   "data": []
     * }
     *
     * @response 403 {
     *   "error": true,
     *   "message": "Default contract type cannot be deleted.",
     *   "data": []
     * }
     *
     * @response 409 {
     *   "error": true,
     *   "message": "Cannot delete contract type as it is being used by existing contracts.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function delete_contract_type($id)
    {

        try {

            $isApi = request()->get('isApi', false);

            $ct = ContractType::findOrFail($id);
            $ct->contracts()->update(['contract_type_id' => 0]);
            $response = DeletionService::delete(ContractType::class, $id, 'Contract type');
            $data = $response->getData();
            if ($data->error) {

                if ($isApi) {
                    return formatApiResponse(
                        true,
                        $data->message,
                        [],
                    );
                }

                return response()->json(['error' => true, 'message' => $data->message]);
            } else {

                if ($isApi) {
                    return formatApiResponse(
                        false,
                        'Contract type deleted successfully.',
                        [
                            'data' => $ct
                        ],
                        200

                    );
                }

                return response()->json(['error' => false, 'message' => 'Contract type deleted successfully.', 'id' => $id, 'title' => $ct->type, 'type' => 'contract_type']);
            }
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500
            );
        }
    }

    public function delete_multiple_contract_type(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:contract_types,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedContractTypes = [];
        $deletedContractTypeTitles = [];
        $defaultContractTypeIds = [];
        $nonDefaultIds = [];

        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $ct = ContractType::findOrFail($id);
            if ($ct) {
                if ($ct->id == 0) { // Assuming 0 is the ID for default contract type
                    $defaultContractTypeIds[] = $id;
                } else {
                    $ct->contracts()->update(['contract_type_id' => 0]);
                    $deletedContractTypes[] = $id;
                    $deletedContractTypeTitles[] = $ct->type;
                    DeletionService::delete(ContractType::class, $id, 'Contract type');
                    $nonDefaultIds[] = $id;
                }
            }
        }

        if (count($defaultContractTypeIds) > 0) {
            if (count($ids) == 1) {
                return response()->json(['error' => true, 'message' => 'Default contract type cannot be deleted.']);
            } else {
                return response()->json(['error' => false, 'message' => 'Contract type(s) deleted successfully except default.', 'id' => $deletedContractTypes, 'titles' => $deletedContractTypeTitles, 'type' => 'contract_type']);
            }
        } else {
            return response()->json(['error' => false, 'message' => 'Contract type(s) deleted successfully.', 'id' => $deletedContractTypes, 'titles' => $deletedContractTypeTitles, 'type' => 'contract_type']);
        }
    }
}
