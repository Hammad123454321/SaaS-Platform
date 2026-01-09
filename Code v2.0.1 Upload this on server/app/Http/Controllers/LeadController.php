<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Workspace;
use App\Models\LeadSource;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Models\UserClientPreference;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LeadController extends Controller
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


    public function index()
    {
        $leads = $this->workspace->leads();
        return view('leads.index', compact('leads'));
    }


    public function create()
    {
        $lead_sources = LeadSource::where(function ($query) {
            $query->where('workspace_id', $this->workspace->id)
                ->orWhere(function ($q) {
                    $q->whereNull('workspace_id')->where('is_default', true);
                });
        })->get();

        $lead_stages = LeadStage::where(function ($query) {
            $query->where('workspace_id', $this->workspace->id)
                ->orWhere(function ($q) {
                    $q->whereNull('workspace_id')->where('is_default', true);
                });
        })->orderBy('order', 'ASC')->get();
        $users = $this->workspace->users;
        return view('leads.create', compact('lead_sources', 'lead_stages', 'users'));
    }

    /**
     * Retrieve a specific lead.
     *
     * This endpoint retrieves the details of a specific lead by its ID. The user must be authenticated and authorized to access the lead.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @urlParam id integer required The ID of the lead to retrieve. Must exist in the `leads` table. Example: 5
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead retrieved successfully!",
     *   "data": {
     *     "id": 5,
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "email": "john.doe@example.com",
     *     "phone": "1234567890",
     *     "company": "Acme Corp",
     *     "stage_id": 2,
     *     "source_id": 3,
     *     "assigned_to": 7,
     *     "created_at": "2025-05-10T12:30:00.000000Z",
     *     "updated_at": "2025-05-15T09:12:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while retrieving the lead."
     * }
     */
    public function get($id)
    {
        try {
            $lead = Lead::findOrFail($id);

            return formatApiResponse(
                false,
                'Lead retrieved successfully!',
                [
                    'data' => formatLead($lead),
                ],
                200
            );
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
     * Create a new lead.
     *
     * This endpoint creates a new lead in the system. The user must be authenticated and belong to the workspace. All required fields must be provided, and the email must be unique.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @bodyParam first_name string required The first name of the lead. Max 255 characters. Example: John
     * @bodyParam last_name string required The last name of the lead. Max 255 characters. Example: Doe
     * @bodyParam email string required The email address of the lead. Must be unique. Example: john.doe@example.com
     * @bodyParam phone string required The phone number of the lead. Max 20 characters. Example: 1234567890
     * @bodyParam country_code string required The country code for the phone number. Max 5 characters. Example: +1
     * @bodyParam country_iso_code string required The ISO 2-letter country code. Example: US
     * @bodyParam source_id integer required The ID of the lead source. Must exist in `lead_sources`. Example: 3
     * @bodyParam stage_id integer required The ID of the lead stage. Must exist in `lead_stages`. Example: 2
     * @bodyParam assigned_to integer required The ID of the user assigned to this lead. Must exist in `users`. Example: 7
     * @bodyParam job_title string optional The lead’s job title. Max 255 characters. Example: Marketing Manager
     * @bodyParam industry string optional The industry the lead belongs to. Max 255 characters. Example: Technology
     * @bodyParam company string required The company name. Max 255 characters. Example: Acme Corp
     * @bodyParam website string optional The company website URL. Must be a valid URL. Example: https://acme.com
     * @bodyParam linkedin string optional The LinkedIn profile URL. Must be a valid URL. Example: https://linkedin.com/in/johndoe
     * @bodyParam instagram string optional The Instagram profile URL. Must be a valid URL. Example: https://instagram.com/johndoe
     * @bodyParam facebook string optional The Facebook profile URL. Must be a valid URL. Example: https://facebook.com/johndoe
     * @bodyParam pinterest string optional The Pinterest profile URL. Must be a valid URL. Example: https://pinterest.com/johndoe
     * @bodyParam city string optional The city of the lead. Max 255 characters. Example: New York
     * @bodyParam state string optional The state of the lead. Max 255 characters. Example: NY
     * @bodyParam zip string optional The zip/postal code. Max 20 characters. Example: 10001
     * @bodyParam country string optional The country of the lead. Max 255 characters. Example: United States
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead created successfully!",
     *   "data": {
     *     "id": 1,
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "email": "john.doe@example.com",
     *     "phone": "1234567890",
     *     "company": "Acme Corp",
     *     "stage_id": 2,
     *     "source_id": 3,
     *     "assigned_to": 7,
     *     "created_at": "2025-05-15T10:00:00.000000Z",
     *     "updated_at": "2025-05-15T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "email": ["The email field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the lead."
     * }
     */
    public function store(Request $request)
    {
        $isApi = $request->get('isApi', false);

        try {
            $formFields = $request->validate([
                'first_name'        => 'required|string|max:255',
                'last_name'         => 'required|string|max:255',
                'email'             => 'required|email|unique:leads,email',
                'phone'             => 'required|string|max:20',
                'country_code'      => 'required|string|max:5',
                'country_iso_code'  => 'required|string|size:2',
                'source_id'         => 'required|exists:lead_sources,id',
                'stage_id'          => 'required|exists:lead_stages,id',
                'assigned_to'       => 'required|exists:users,id',
                'job_title'         => 'nullable|string|max:255',
                'industry'          => 'nullable|string|max:255',
                'company'           => 'required|string|max:255',
                'website'           => 'nullable|url|max:255',
                'linkedin'          => 'nullable|url|max:255',
                'instagram'         => 'nullable|url|max:255',
                'facebook'          => 'nullable|url|max:255',
                'pinterest'         => 'nullable|url|max:255',
                'city'              => 'nullable|string|max:255',
                'state'             => 'nullable|string|max:255',
                'zip'               => 'nullable|string|max:20',
                'country'           => 'nullable|string|max:255',
            ]);

            $formFields['created_by'] = $this->user->id;
            $formFields['workspace_id'] = $this->workspace->id;

            $lead = Lead::create($formFields);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Lead Created Successfully.',
                    [
                        'data' => formatLead($lead),
                    ],
                    200
                );
            } else {
                return response()->json([
                    'error' => false,
                    'message' => 'Lead Created Successfully.',
                    'id' => $lead->id,
                    'type' => 'lead'
                ]);
            }
        } catch (ValidationException $e) {
            
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            
            return formatApiResponse(
                true,
                'Lead Couldn\'t Created.',
                [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            );
        }
    }


    public function show(string $id)
    {
        $lead = Lead::findOrFail($id);
        return view('leads.show', compact('lead'));
    }


    public function edit(string $id)
    {
        $lead = Lead::findOrFail($id);
        return view('leads.edit', compact('lead'));
    }

    /**
     * Update a specific lead.
     *
     * This endpoint updates the details of an existing lead. The user must be authenticated and belong to the workspace. The email must remain unique among other leads.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @urlParam id integer required The ID of the lead to update. Must exist in the `leads` table. Example: 5
     * @bodyParam first_name string required The first name of the lead. Max 255 characters. Example: John
     * @bodyParam last_name string required The last name of the lead. Max 255 characters. Example: Doe
     * @bodyParam email string required The email address. Must be unique except for this lead. Example: john.doe@example.com
     * @bodyParam phone string required The phone number. Max 20 characters. Example: 1234567890
     * @bodyParam country_code string required The country code for the phone number. Max 5 characters. Example: +1
     * @bodyParam country_iso_code string required The ISO 2-letter country code. Example: US
     * @bodyParam source_id integer required The ID of the lead source. Must exist in `lead_sources`. Example: 3
     * @bodyParam stage_id integer required The ID of the lead stage. Must exist in `lead_stages`. Example: 2
     * @bodyParam assigned_to integer required The ID of the user assigned to this lead. Must exist in `users`. Example: 7
     * @bodyParam job_title string optional The lead’s job title. Max 255 characters. Example: CTO
     * @bodyParam industry string optional The industry the lead belongs to. Max 255 characters. Example: SaaS
     * @bodyParam company string required The company name. Max 255 characters. Example: Acme Corp
     * @bodyParam website string optional The company website URL. Must be a valid URL. Example: https://acme.com
     * @bodyParam linkedin string optional The LinkedIn profile URL. Must be a valid URL. Example: https://linkedin.com/in/johndoe
     * @bodyParam instagram string optional The Instagram profile URL. Must be a valid URL. Example: https://instagram.com/johndoe
     * @bodyParam facebook string optional The Facebook profile URL. Must be a valid URL. Example: https://facebook.com/johndoe
     * @bodyParam pinterest string optional The Pinterest profile URL. Must be a valid URL. Example: https://pinterest.com/johndoe
     * @bodyParam city string optional The city of the lead. Max 255 characters. Example: San Francisco
     * @bodyParam state string optional The state of the lead. Max 255 characters. Example: CA
     * @bodyParam zip string optional The zip/postal code. Max 20 characters. Example: 94107
     * @bodyParam country string optional The country of the lead. Max 255 characters. Example: United States
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead updated successfully!",
     *   "data": {
     *     "id": 5,
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "email": "john.doe@example.com",
     *     "phone": "1234567890",
     *     "company": "Acme Corp",
     *     "stage_id": 2,
     *     "source_id": 3,
     *     "assigned_to": 7,
     *     "created_at": "2025-05-10T12:30:00.000000Z",
     *     "updated_at": "2025-05-15T09:12:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead not found."
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "email": ["The email has already been taken."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the lead."
     * }
     */
    public function update(Request $request, string $id)
    {
        $isApi = $request->get('isApi', false);

        try {
            $lead = Lead::where('workspace_id', $this->workspace->id)
                ->where('id', $id)
                ->firstOrFail();

            $formFields = $request->validate([
                'first_name'        => 'required|string|max:255',
                'last_name'         => 'required|string|max:255',
                'email'             => 'required|email|unique:leads,email,' . $lead->id,
                'phone'             => 'required|string|max:20',
                'country_code'      => 'required|string|max:5',
                'country_iso_code'  => 'required|string|size:2',
                'source_id'         => 'required|exists:lead_sources,id',
                'stage_id'          => 'required|exists:lead_stages,id',
                'assigned_to'       => 'required|exists:users,id',
                'job_title'         => 'nullable|string|max:255',
                'industry'          => 'nullable|string|max:255',
                'company'           => 'required|string|max:255',
                'website'           => 'nullable|url|max:255',
                'linkedin'          => 'nullable|url|max:255',
                'instagram'         => 'nullable|url|max:255',
                'facebook'          => 'nullable|url|max:255',
                'pinterest'         => 'nullable|url|max:255',
                'city'              => 'nullable|string|max:255',
                'state'             => 'nullable|string|max:255',
                'zip'               => 'nullable|string|max:20',
                'country'           => 'nullable|string|max:255',
            ]);

            $lead->update($formFields);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Lead Updated Successfully.',
                    [
                        'data' => formatLead($lead),
                    ]
                );
            } else {
                return response()->json([
                    'error' => false,
                    'message' => 'Lead Updated Successfully.',
                    'id' => $lead->id,
                    'type' => 'lead'
                ]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (ModelNotFoundException $e) {
            return formatApiResponse(
                true,
                'Lead not found.',
                [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            );
        } catch (Exception $e) {
            return formatApiResponse(
                true,
                'Lead Couldn\'t Updated.',
                [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            );
        }
    }

    /**
     * Delete a specific lead.
     *
     * This endpoint deletes a specific lead by its ID. The user must be authenticated and have appropriate permissions to perform the deletion.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @urlParam id integer required The ID of the lead to delete. Must exist in the `leads` table. Example: 5
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead deleted successfully!"
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the lead."
     * }
     */
    public function destroy(string $id)
    {
        $response = DeletionService::delete(Lead::class, $id, 'leads');
        return $response;
    }


    public function destroy_multiple(Request $request)
    {
        $validatedData = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:leads,id'
        ]);
        $ids = $validatedData['ids'];
        $deletedLeads = [];
        $deletedLeadsTitles = [];
        foreach ($ids as $id) {
            $lead = Lead::find($id);
            if ($lead) {
                $deletedLeadTitles[] = ucwords($lead->first_name . ' ' . $lead->last_name);

                DeletionService::delete(Lead::class, $id, 'Lead');
                $deletedLeads[] = $id;
            }
        }
        return response()->json(['error' => false, 'message' => 'Lead(s) deleted successfully.', 'id' => $deletedLeads, 'titles' => $deletedLeadsTitles]);
    }


    public function list()
    {
        $search = request('search');
        $sortOptions = [
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'recently-updated' => ['updated_at', 'desc'],
            'earliest-updated' => ['updated_at', 'asc'],
        ];
        [$sort, $order] = $sortOptions[request()->input('sort')] ?? ['id', 'desc'];
        $source_ids  = request('source_ids', []);
        $stage_ids   = request('stage_ids', []);
        $start_date = request('start_date');
        $end_date = request('end_date');

        $limit = request('limit', 10);

        $leads = isAdminOrHasAllDataAccess()
            ? $this->workspace->leads()
            : $this->user->leads();

        $leads = $leads->with(['source', 'stage', 'assigned_user']);
        $leads = $leads->orderBy($sort, $order);

        if ($search) {
            $leads->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('company', 'like', "%$search%")
                    ->orWhere('job_title', 'like', "%$search%")
                    ->orWhere('id', 'like', "%$search%");
            });
        }

        if (!empty($source_ids)) {
            $leads->whereIn('source_id', $source_ids);
        }
        if (!empty($stage_ids)) {
            $leads->whereIn('stage_id', $stage_ids);
        }
        if ($start_date && $end_date) {
            $leads->whereBetween('created_at', [$start_date, $end_date]);
        }

        $total = $leads->count();

        $leads = $leads->paginate($limit)->through(function ($lead) {
            if ($lead->stage) {
                $stage = '<span class="badge bg-' . $lead->stage->color . '">' . $lead->stage->name . '</span>';
            } else {
                $stage = "-";
            }

            return [
                'id' => $lead->id,
                'name' => formatLeadUserHtml($lead),
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company' => $lead->company,
                'website' => $lead->website,
                'job_title' => $lead->job_title,
                'stage' => $stage,
                'source' => optional($lead->source)->name,
                'assigned_to' => formatUserHtml($lead->assigned_user),
                'created_at' => format_date($lead->created_at, true),
                'updated_at' => format_date($lead->updated_at, true),
                'actions' => $this->getActions($lead),
            ];
        });

        return response()->json([
            'rows' => $leads->items(),
            'total' => $total,
        ]);
    }

    /**
     * List leads with optional filters, sorting, and pagination.
     *
     * This endpoint retrieves a paginated list of leads accessible to the authenticated user, with optional search, source and stage filters, date range filtering, and sorting. Permissions for editing and deleting are included in the response.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @queryParam search string optional Filters leads by first name, last name, email, phone, company, job title, or ID. Example: John
     * @queryParam source_ids array optional Filters leads by one or more source IDs. Example: [1, 2]
     * @queryParam stage_ids array optional Filters leads by one or more stage IDs. Example: [3, 4]
     * @queryParam start_date string optional Filters leads created on or after this date (YYYY-MM-DD). Example: 2025-01-01
     * @queryParam end_date string optional Filters leads created on or before this date (YYYY-MM-DD). Example: 2025-05-01
     * @queryParam sort string optional Sorts leads by criteria (newest, oldest, recently-updated, earliest-updated). Defaults to newest. Example: newest
     * @queryParam limit integer optional Number of leads per page (1-100). Defaults to 10. Example: 20
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Leads retrieved successfully!",
     *   "data": {
     *     "total": 50,
     *     "data": [
     *       {
     *         "id": 1,
     *         "first_name": "John",
     *         "last_name": "Doe",
     *         "email": "john.doe@example.com",
     *         "phone": "1234567890",
     *         "company": "Acme Corp",
     *         "job_title": "Manager",
     *         "source": { "id": 1, "name": "Website" },
     *         "stage": { "id": 2, "name": "Negotiation", "color": "primary" },
     *         "assigned_user": { "id": 5, "name": "Jane Smith" },
     *         "created_at": "2025-01-10T12:00:00.000000Z",
     *         "updated_at": "2025-05-15T10:00:00.000000Z",
     *         "can_edit": true,
     *         "can_delete": true
     *       }
     *     ],
     *     "permissions": {
     *       "can_edit": true,
     *       "can_delete": true
     *     }
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while retrieving the leads."
     * }
     */
    public function apiList()
    {
        try {
            $search = request('search');
            $sortOptions = [
                'newest' => ['created_at', 'desc'],
                'oldest' => ['created_at', 'asc'],
                'recently-updated' => ['updated_at', 'desc'],
                'earliest-updated' => ['updated_at', 'asc'],
            ];
            [$sort, $order] = $sortOptions[request()->input('sort')] ?? ['id', 'desc'];
            $source_ids  = request('source_ids', []);
            $stage_ids   = request('stage_ids', []);
            $start_date = request('start_date');
            $end_date = request('end_date');

            $limit = request('limit', 10);

            $leads = isAdminOrHasAllDataAccess()
                ? $this->workspace->leads()
                : $this->user->leads();

            $leads = $leads->with(['source', 'stage', 'assigned_user']);
            $leads = $leads->orderBy($sort, $order);

            if ($search) {
                $leads->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%$search%")
                        ->orWhere('last_name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%")
                        ->orWhere('company', 'like', "%$search%")
                        ->orWhere('job_title', 'like', "%$search%")
                        ->orWhere('id', 'like', "%$search%");
                });
            }

            if (!empty($source_ids)) {
                $leads->whereIn('source_id', $source_ids);
            }
            if (!empty($stage_ids)) {
                $leads->whereIn('stage_id', $stage_ids);
            }
            if ($start_date && $end_date) {
                $leads->whereBetween('created_at', [$start_date, $end_date]);
            }

            $total = $leads->count();

            $leads = $leads->take($limit)->get()->map(function ($lead) {
                return formatLead($lead);
            });

            return formatApiResponse(
                false,
                'Lead(s) retrieved successfully',
                [
                    'total' => $total,
                    'data' => $leads,
                    'permissions' => [
                        'can_edit' => checkPermission('edit_leads'),
                        'can_delete' => checkPermission('delete_leads')
                    ]
                ],
                200
            );
        } catch (\Exception $e) {
            return formatApiResponse(
                false,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                200
            );
        }
    }


    public function kanban(Request $request)
    {
        $sources = (array) $request->input('sources', []);
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $sortOptions = [
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'recently-updated' => ['updated_at', 'desc'],
            'earliest-updated' => ['updated_at', 'asc'],
        ];
        [$sort, $order] = $sortOptions[$request->input('sort')] ?? ['id', 'desc'];

        $leadsQuery = isAdminOrHasAllDataAccess()
            ? $this->workspace->leads()
            : $this->user->leads();
        $leadsQuery = $leadsQuery
            ->with(['source', 'stage', 'assigned_user'])
            ->orderBy($sort, $order);

        if (!empty($sources)) {
            $leadsQuery->whereIn('source_id', $sources);
        }
        if ($start_date && $end_date) {
            $leadsQuery->whereBetween('updated_at', [$start_date, $end_date]);
        }

        $leads = $leadsQuery->get();

        $lead_stages = LeadStage::where(function ($query) {
            $query->where('workspace_id', $this->workspace->id)
                ->orWhere(function ($q) {
                    $q->whereNull('workspace_id')->where('is_default', true);
                });
        })
            ->orderBy('order', 'ASC')
            ->get();

        return view('leads.kanban', compact('leads', 'lead_stages'));
    }

    /**
     * Change the stage of a lead.
     *
     * This endpoint updates the stage of a specific lead by its ID. The user must be authenticated and authorized to modify the lead.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @bodyParam id integer required The ID of the lead to update. Must exist in the `leads` table. Example: 123
     * @bodyParam stage_id integer required The ID of the new lead stage. Must exist in the `lead_stages` table. Example: 5
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead stage updated successfully!",
     *   "data": {
     *     "id": 123,
     *     "type": "lead",
     *     "activity_message": "Lead Stage Changed to Negotiation"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead not found."
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "stage_id": ["The selected stage_id is invalid."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the lead stage."
     * }
     */
    public function stageChange(Request $request)
    {
        $isApi = $request->get('isApi', false);
        try {
            $request->validate([
                'id' => 'required|exists:leads,id',
                'stage_id' => 'required|exists:lead_stages,id',
            ]);
            $lead = Lead::findOrFail($request->id);
            $lead->stage_id = $request->stage_id;

            $lead->save();

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Lead Stage Updated Successfully.',
                    [
                        'data' => [
                            'id' => $lead->id,
                            'type' => 'lead',
                            'activity_message' => 'Lead Stage Changed to ' . $lead->stage->name,
                        ]
                    ]
                );
            }
            return response()->json([
                'error' => false,
                'message' => 'Lead Stage Updated Successfully.',
                'id' => $lead->id,
                'type' => 'lead',
                'activity_message' => 'Lead Stage Changed to ' . $lead->stage->name,
            ]);
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (ModelNotFoundException $e) {
            return formatApiResponse(
                true,
                'Lead not found.',
                [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            );
        } catch (Exception $e) {
            return formatApiResponse(
                true,
                'Lead Couldn\'t Updated.',
                [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            );
        }
    }

    /**
     * Save the default view preference for leads.
     *
     * This endpoint sets the default view preference (e.g., list or Kanban) for the authenticated user or client when viewing leads.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @bodyParam view string required The preferred view type (e.g., list, kanban). Example: kanban
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Default view set successfully!"
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while setting the default view."
     * }
     */
    public function saveViewPreference(Request $request)
    {
        $isApi = request()->get('isApi', false);

        $view = $request->input('view');
        $prefix = isClient() ? 'c_' : 'u_';
        if (
            UserClientPreference::updateOrCreate(
                ['user_id' => $prefix . $this->user->id, 'table_name' => 'leads'],
                ['default_view' => $view]
            )
        ) {
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Default View Set Successfully.',
                    [],
                    200
                );
            } else {
                return response()->json(['error' => false, 'message' => 'Default View Set Successfully.']);
            }
        } else {
            return response()->json(['error' => true, 'message' => 'Something Went Wrong.']);
        }
    }

    /**
     * Convert a lead to a client.
     *
     * This endpoint converts a lead to a client by creating a new client record with the lead's data. The user must be authenticated, and the lead must not already be converted.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @urlParam lead integer required The ID of the lead to convert. Must exist in the `leads` table. Example: 5
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead converted to client successfully!",
     *   "data": {
     *     "id": 5,
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "email": "john.doe@example.com",
     *     "company": "Acme Corp"
     *   }
     * }
     *
     * @response 400 {
     *   "error": true,
     *   "message": "Lead is already converted to the client.",
     *   "id": 5
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead not found."
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "email": ["The email has already been taken."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while converting the lead."
     * }
     */
    public function convertToClient(Request $request, Lead $lead)
    {
        if ($lead->is_converted == 1) {
            return formatApiResponse(
                true,
                'Lead is already converted to the client.',
                [
                    'id' => $lead->id
                ]
            );
        }

        $clientData = [
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'company' => $lead->company,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'country_code' => $lead->country_code,
            'address' => $lead->address,
            'city' => $lead->city,
            'state' => $lead->state,
            'country' => $lead->country,
            'zip' => $lead->zip,
            'internal_purpose' => 'on',
        ];

        $clientRequest = new Request($clientData);
        $clientController = new \App\Http\Controllers\ClientController();
        $response = $clientController->store($clientRequest);

        $responseBody = json_decode($response->getContent(), true);

        if (isset($responseBody['error']) && $responseBody['error'] === true) {
            return formatApiValidationError(
                true,
                $responseBody['errors'] ?? []
            );
        }

        if ($response->getStatusCode() != 200) {
            return formatApiResponse(
                true,
                'Something went wrong while converting the lead.',
                []
            );
        }

        $lead->update(['is_converted' => 1, 'converted_at' => now()]);

        return $response;
    }

    /**
     * Get actions for a lead.
     *
     * This private method generates HTML for action buttons (view, edit, delete, convert) for a lead based on user permissions and lead status. It is not an API endpoint.
     */
    private function getActions($lead)
    {
        $actions = '';
        $canEdit = checkPermission('edit_leads');
        $canDelete = checkPermission('delete_leads');
        $isConverted = $lead->is_converted == 1 ? true : false;

        $actions = '<div class="d-flex align-items-center">';

        $actions .= '<a href="' . route('leads.show', ['id' => $lead->id]) . '"
                class="text-info btn btn-sm p-1 me-1"
                data-id="' . $lead->id . '"
                title="' . get_label('view', 'View') . '">
                <i class="bx bx-show"></i>
            </a>';

        if ($canEdit) {
            $actions .= '<a href="' . route('leads.edit', ['id' => $lead->id]) . '"
                    class="text-primary btn btn-sm  p-1 me-1"
                    data-id="' . $lead->id . '"
                    title="' . get_label('update', 'Update') . '">
                    <i class="bx bx-edit"></i>
                </a>';
        }

        if ($canDelete) {
            $actions .= '<button title="' . get_label('delete', 'Delete') . '"
                    type="button"
                    class="btn btn-sm p-1 delete text-danger"
                    data-id="' . $lead->id . '"
                    data-type="leads"
                    data-table="table">
                    <i class="bx bx-trash"></i>
                </button>';
        }
        if (!$isConverted) {
            $actions .= '<button class="btn btn-sm text-primary convert-to-client" title="' . get_label('convert_to_client', 'Convert To Client') . '"
                             data-id="' . $lead->id . '"><i
                            class="bx bxs-analyse me-1 p-1"></i>
                        </button>';
        }

        $actions .= '</div>';
        return $actions;
    }
}
