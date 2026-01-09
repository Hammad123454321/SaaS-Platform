<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Workspace;
use App\Models\LeadSource;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Validation\ValidationException;

class LeadSourceController extends Controller
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
        $lead_sources = $this->workspace->lead_sources();
        return view('lead_sources.index', compact('lead_sources'));
    }


    public function create()
    {
        //
    }

    /**
     * Create a new lead source.
     *
     * This endpoint creates a new lead source with the provided name under the current workspace. The user must be authenticated and authorized to manage lead sources.
     *
     * @authenticated
     *
     * @group Leads Source Management
     *
     * @bodyParam name string required The name of the lead source. Max 255 characters. Example: Referral
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead source created successfully!",
     *   "data": {
     *     "id": 1,
     *     "name": "Referral",
     *     "workspace_id": 10,
     *     "created_at": "2025-05-20T10:00:00.000000Z",
     *     "updated_at": "2025-05-20T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "name": ["The name field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the lead source."
     * }
     */
    public function store(Request $request)
    {
        $isApi = request()->get('isApi', false);
        try {
            $request->validate([
                'name' => 'required|string'
            ]);
            $lead_source = new LeadSource();
            $lead_source->workspace_id = getWorkspaceId();
            $lead_source->name = $request->name;
            $lead_source->save();
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Lead Source Created Successfully',
                    [
                        'data' => [
                            formatLeadSource($lead_source)
                        ]
                    ],
                    200
                );
            } else {
                return response()->json(['error' => false, 'message' => 'Lead Source Created Successfully', 'id' => $lead_source->id, 'type' => 'lead_source']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Lead Source Couldn\'t Created',
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Lead Source Couldn\'t Created']);
            }
        }
    }


    public function show(string $id)
    {
        //
    }

    /**
     * Retrieve a specific lead source.
     *
     * This endpoint retrieves the details of a specific lead source by its ID. The user must be authenticated and authorized to manage lead sources.
     *
     * @authenticated
     *
     * @group Leads Source Management
     *
     * @urlParam id integer required The ID of the lead source to retrieve. Must exist in the `lead_sources` table. Example: 1
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead source retrieved successfully!",
     *   "data": {
     *     "id": 1,
     *     "name": "Referral",
     *     "workspace_id": 10,
     *     "created_at": "2025-05-20T10:00:00.000000Z",
     *     "updated_at": "2025-05-20T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead source not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while retrieving the lead source."
     * }
     */
    public function get(string $id)
    {
        $isApi = request()->get('isApi', false);

        $lead_source = LeadSource::findOrFail($id);

        if ($isApi) {
            return formatApiResponse(
                false,
                'Lead Source Retrived Successfully',
                [
                    'data' => formatLeadSource($lead_source),
                ],
                200
            );
        }
        return response()->json(['error' => false, 'message' => 'Lead Source Retrived Successfully', 'lead_source' => $lead_source]);
    }

    /**
     * Update a specific lead source.
     *
     * This endpoint updates the name of an existing lead source identified by its ID. The user must be authenticated and authorized to manage lead sources.
     *
     * @authenticated
     *
     * @group Leads Source Management
     *
     * @bodyParam id integer required The ID of the lead source to update. Must exist in the `lead_sources` table. Example: 1
     * @bodyParam name string required The new name for the lead source. Max 255 characters. Example: Referral
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead source updated successfully!",
     *   "data": {
     *     "id": 1,
     *     "name": "Referral",
     *     "workspace_id": 10,
     *     "created_at": "2025-05-20T10:00:00.000000Z",
     *     "updated_at": "2025-05-20T10:05:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead source not found."
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "id": ["The selected id is invalid."],
     *     "name": ["The name field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the lead source."
     * }
     */
    public function update(Request $request)
    {
        $isApi = request()->get('isApi', false);
        try {
            $request->validate([
                'id' => 'required|exists:lead_sources,id',
                'name' => 'required',
            ]);
            $lead_source = LeadSource::findOrFail($request->id);
            $lead_source->name = $request->name;
            $lead_source->save();
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Lead Source Updated Successfully.',
                    [
                        'data' => formatLeadSource($lead_source),
                    ],
                    200
                );
            } else {
                return response()->json(['error' => false, 'message' => 'Lead Source Updated Successfully', 'id' => $lead_source->id, 'type' => 'lead_source']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Lead Source Couldn\'t Updated.',
                    [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile()
                    ]
                );
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Lead Source Couldn\'t Updated.',
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]);
            }
        }
    }

    /**
     * Delete a specific lead source.
     *
     * This endpoint deletes a specific lead source by its ID. The user must be authenticated and authorized to manage lead sources.
     *
     * @authenticated
     *
     * @group Leads Source Management
     *
     * @urlParam id integer required The ID of the lead source to delete. Must exist in the `lead_sources` table. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead source deleted successfully!"
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead source not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the lead source."
     * }
     */
    public function destroy(string $id)
    {
        $response = DeletionService::delete(LeadSource::class, $id, 'lead_source');
        return $response;
    }


    public function destroy_multiple(Request $request)
    {
        $validatedData = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:lead_sources,id'
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        foreach ($ids as $id) {
            $lead_source = LeadSource::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $lead_source->name;
            DeletionService::delete(LeadSource::class, $id, 'lead_source');
        }

        return response()->json(['error' => false, 'message' => 'LeadSource(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }


    public function list()
    {
        $search = request('search');
        $sort = request('sort', "id");
        $order = request('order', "DESC");
        $limit = request('limit', 10);

        $lead_sources = $this->workspace->lead_sources();
        $lead_sources  = $lead_sources->orderBy($sort, $order);

        if ($search) {
            $lead_sources->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        $total = $lead_sources->count();

        $lead_sources = $lead_sources
            ->paginate($limit)
            ->through(
                fn($lead_source) => [
                    'id' => $lead_source->id,
                    'name' => ucwords($lead_source->name),
                    'created_at' => format_date($lead_source->created_at, true),
                    'updated_at' => format_date($lead_source->updated_at, true),
                    'actions' => $this->getActions($lead_source),
                ]
            );

        return response()->json([
            "rows" => $lead_sources->items(),
            "total" => $total,
        ]);
    }

    /**
     * List lead sources with optional filters, sorting, and pagination.
     *
     * This endpoint retrieves a paginated list of lead sources or a specific lead source by ID, with optional search, sorting, and pagination parameters. The response includes permission details for editing and deletion. The user must be authenticated and authorized to manage lead sources.
     *
     * @authenticated
     *
     * @group Leads Source Management
     *
     * @queryParam id integer optional The ID of a specific lead source to retrieve. Example: 1
     * @queryParam search string optional Filters lead sources by name or ID. Example: Referral
     * @queryParam sort string optional The column to sort by (id, name, created_at, updated_at). Defaults to id. Example: name
     * @queryParam order string optional The sort order (ASC, DESC). Defaults to DESC. Example: ASC
     * @queryParam limit integer optional Number of lead sources per page (1-100). Defaults to 10. Example: 20
     * @queryParam offset integer optional Number of lead sources to skip. Defaults to 0. Example: 10
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead sources retrieved successfully!",
     *   "total": 25,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Referral",
     *       "workspace_id": 10,
     *       "created_at": "2025-05-20T10:00:00.000000Z",
     *       "updated_at": "2025-05-20T10:00:00.000000Z",
     *       "can_edit": true,
     *       "can_delete": true
     *     }
     *   ],
     *   "permissions": {
     *     "can_edit": true,
     *     "can_delete": true
     *   }
     * }
     *
     * @response 404 {
     *   "error": false,
     *   "message": "Lead source(s) not found.",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while retrieving the lead sources."
     * }
     */
    public function apiList()
    {

        try {

            $limit = request('limit', 10);
            $offset = request('offset', 0);
            $id = request('id', null);
            $search = request('search');
            $sort = request('sort', "id");
            $order = request('order', "DESC");
            $limit = request('limit', 10);

            $lead_sources = $this->workspace->lead_sources();
            $lead_sources  = $lead_sources->orderBy($sort, $order);

            if ($search) {
                $lead_sources->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('id', 'like', '%' . $search . '%');
                });
            }

            $total = $lead_sources->count();
            // dd($total);
            if ($id) {
                $lead_source = $lead_sources->find($id);
                if (!$lead_source) {
                    return formatApiResponse(
                        false,
                        'Lead Source Not Found.',
                        [
                            'total' => 0,
                            'data' => []
                        ],
                        404
                    );
                }
                return formatApiResponse(
                    false,
                    'Lead Source Retrived Successfully.',
                    [
                        'total' => 1,
                        'data' => formatLeadSource($lead_source)
                    ],
                    200
                );
            } else {
                $lead_sources = $lead_sources->orderBy($sort, $order)->skip($offset)->take($limit)->get();
                if ($lead_sources->isEmpty()) {
                    return formatApiResponse(
                        false,
                        'Lead Sources Not Found.',
                        [
                            'total' => 0,
                            'data' => []
                        ],
                        404
                    );
                }
                $data = $lead_sources->map(function ($lead_source) {
                    return formatLeadSource($lead_source);
                });
                return formatApiResponse(
                    false,
                    'Lead Sources Retrived Successfully.',
                    [
                        'total' => $total,
                        'data' => $data,
                        'permissions' => [
                            'can_delete' => checkPermission('manage_leads'),
                            'can_edit' => checkPermission('manage_leads')
                        ]
                    ],
                    200
                );
            }
        } catch (\Exception $e) {
            dd($e);
        }
    }

    /**
     * Get actions for a lead source.
     *
     * This private method generates HTML for action buttons (edit, delete) for a lead source based on user permissions. It is not an API endpoint.
     */
    private function getActions($lead_source)
    {
        $actions = '';
        $canEdit = checkPermission('manage_leads');
        $canDelete = checkPermission('manage_leads');

        if ($canEdit) {
            $actions .= '<a href="javascript:void(0);" class="edit-lead-source" data-id="' . $lead_source->id . '" title="' . get_label('update', 'Update') . '">' .
                '<i class="bx bx-edit mx-1"></i>' .
                '</a>';
        }

        if ($canDelete) {
            $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $lead_source->id . '" data-type="lead-sources" data-table="table">' .
                '<i class="bx bx-trash text-danger mx-1"></i>' .
                '</button>';
        }

        return $actions ?: '-';
    }
}
