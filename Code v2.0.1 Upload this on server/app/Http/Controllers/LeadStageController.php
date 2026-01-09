<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\LeadStage;
use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Validation\ValidationException;

class LeadStageController extends Controller
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
        $lead_stages = $this->workspace->lead_stages();
        return view('lead_stages.index', compact('lead_stages'));
    }

    public function create() {}

    /**
     * Create a new lead stage.
     *
     * This endpoint creates a new lead stage within the current workspace. It assigns a unique slug, sets the display order, and saves the provided name and color. The user must be authenticated and authorized to manage lead stages.
     *
     * @authenticated
     *
     * @group Leads Stage Management
     *
     * @bodyParam name string required The name of the lead stage. Max 255 characters. Example: Contacted
     * @bodyParam color string required The color badge for the lead stage. Must be one of: primary, secondary, success, danger, info, dark, warning.   Example: success
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead stage created successfully!",
     *   "data": {
     *     "id": 12,
     *     "name": "Contacted",
     *     "slug": "contacted",
     *     "color": "success",
     *     "order": 3,
     *     "workspace_id": 1,
     *     "created_at": "2025-05-15T10:00:00.000000Z",
     *     "updated_at": "2025-05-15T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "name": ["The name field is required."],
     *     "color": ["The color field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the lead stage."
     * }
     */
    public function store(Request $request)
    {
        $isApi = $request->get('isApi', false);
        // dd(generateUniqueSlug($request->name, LeadStage::class, null, $this->workspace->id));
        // dd(LeadStage::getNextOrderForWorkspace($this->workspace->id));
        try {
            $request->validate([
                'name' => 'required|string',
                'color' => 'required|in:primary,secondary,success,danger,info,dark,warning',
            ]);
            $lead_stage = new LeadStage();
            $lead_stage->name = $request->name;
            $lead_stage->workspace_id = $this->workspace->id;
            $lead_stage->slug = generateUniqueSlug($request->name, LeadStage::class, null, $this->workspace->id);
            $lead_stage->order = LeadStage::getNextOrderForWorkspace($this->workspace->id);
            $lead_stage->color = $request->color;
            $lead_stage->save();

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Lead Stage Created Successfully.',
                    [
                        'data' => formatLeadStage($lead_stage)
                    ],
                    200
                );
            } else {
                return response()->json([
                    'error' => false,
                    'message' => 'Lead Stage Created Successfully.',
                    'id' => $lead_stage->id,
                    'type' => 'lead_stage'
                ]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            dd($e);
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Lead Stage Couldn\'t Created.',
                    [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile()
                    ],
                    500
                );
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Lead Stage Couldn\'t Created.'
                ]);
            }
        }
    }

    /**
     * Retrieve a specific lead stage.
     *
     * This endpoint retrieves the details of a specific lead stage by its ID. The user must be authenticated and authorized to manage lead stages.
     *
     * @authenticated
     *
     *  @group Leads Stage Management
     *
     * @urlParam id integer required The ID of the lead stage to retrieve. Must exist in the `lead_stages` table. Example: 5
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead stage retrieved successfully!",
     *   "data": {
     *     "id": 5,
     *     "name": "Qualified",
     *     "slug": "qualified",
     *     "color": "info",
     *     "order": 2,
     *     "workspace_id": 1,
     *     "created_at": "2025-05-10T12:30:00.000000Z",
     *     "updated_at": "2025-05-15T09:12:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead stage not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while retrieving the lead stage."
     * }
     */
    public function get(string $id)
    {
        $isApi = request()->get('isApi', false);
        $lead_stage = LeadStage::findOrFail($id);

        if ($isApi) {
            return formatApiResponse(
                false,
                'Lead Stage Retrived Successfully',
                [
                    'data' => formatLeadStage($lead_stage)
                ],
                200
            );
        }

        return response()->json(['error' => false, 'message' => 'Lead Stage Retrived Successfully', 'lead_stage' => $lead_stage]);
    }


    public function edit(string $id)
    {
        //
    }

    /**
     * Update a specific lead stage.
     *
     * This endpoint updates the details of an existing lead stage, including its name, slug, and color. The user must be authenticated and authorized to manage lead stages.
     *
     * @authenticated
     *
     * @group Leads Stage Management
     *
     * @bodyParam id integer required The ID of the lead stage to update. Must exist in the `lead_stages` table. Example: 3
     * @bodyParam name string required The name of the lead stage. Max 255 characters. Example: Proposal Sent
     * @bodyParam color string required The color badge for the lead stage. Must be one of: primary, secondary, success, danger, info, dark, warning. Example: warning
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead stage updated successfully!",
     *   "data": {
     *     "id": 3,
     *     "name": "Proposal Sent",
     *     "slug": "proposal-sent",
     *     "color": "warning",
     *     "order": 2,
     *     "workspace_id": 1,
     *     "created_at": "2025-05-10T12:30:00.000000Z",
     *     "updated_at": "2025-05-15T09:12:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead stage not found."
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "id": ["The selected id is invalid."],
     *     "name": ["The name field is required."],
     *     "color": ["The selected color is invalid."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the lead stage."
     * }
     */
    public function update(Request $request)
    {
        $isApi = $request->get('isApi', false);
        try {
            $request->validate([
                'id' => 'exists:lead_stages,id',
                'name' => 'required|string',
                'color' => 'required|in:primary,secondary,success,danger,info,dark,warning',
            ]);

            $lead_stage = LeadStage::findOrFail($request->id);
            $lead_stage->name = $request->name;
            $lead_stage->slug = generateUniqueSlug($request->name, LeadStage::class, $lead_stage->id);
            $lead_stage->color = $request->color;
            $lead_stage->save();

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Lead Stage Updated Successfully.',
                    [
                        'data' => formatLeadStage($lead_stage),
                    ],
                    200
                );
            } else {
                return response()->json(['error' => false, 'message' => 'Lead Stage Updated Successfully.', 'id' => $lead_stage->id, 'type' => 'lead_stage']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Lead Stage Couldn\'t Updated.',
                    [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                    ]
                );
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Lead Stage Couldn\'t Updated.',
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]);
            }
        }
    }

    /**
     * Delete a specific lead stage.
     *
     * This endpoint deletes a specific lead stage by its ID and reorders the remaining stages to maintain sequence. The user must be authenticated and authorized to manage lead stages.
     *
     * @authenticated
     *
     * @group Leads Stage Management
     *
     * @urlParam id integer required The ID of the lead stage to delete. Must exist in the `lead_stages` table. Example: 4
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead stage deleted successfully!"
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead stage not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the lead stage."
     * }
     */
    public function destroy(string $id)
    {
        $response = DeletionService::delete(LeadStage::class, $id, 'LeadStage');

        LeadStage::where(function ($query) {
            $query->where('workspace_id', $this->workspace->id)
                ->orWhere(function ($query) {
                    $query->whereNull('workspace_id')
                        ->where('is_default', 1);
                });
        })
            ->orderBy('order')
            ->get()
            ->values()
            ->each(function ($stage, $index) {
                $stage->order = $index + 1;
                $stage->save();
            });

        return $response;
    }


    public function destroy_multiple(Request $request)
    {
        $validatedData = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:lead_stages,id'
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];

        foreach ($ids as $id) {
            $lead_stage = LeadStage::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $lead_stage->name;
            DeletionService::delete(LeadStage::class, $id, 'LeadStage');
        }

        // Corrected where clause grouping
        LeadStage::where(function ($query) {
            $query->where('workspace_id', $this->workspace->id)
                ->orWhere(function ($query) {
                    $query->whereNull('workspace_id')
                        ->where('is_default', 1);
                });
        }) // No semicolon here
            ->orderBy('order')
            ->get()
            ->values()
            ->each(function ($stage, $index) {
                $stage->order = $index + 1;
                $stage->save();
            });

        return response()->json([
            'error' => false,
            'message' => 'LeadStage(s) deleted successfully.',
            'id' => $deletedIds,
            'titles' => $deletedTitles
        ]);
    }


    public function list()
    {
        $search = request('search');
        $sort = request('sort', "id");
        $order = request('order', "DESC");
        $limit = request('limit', 10);

        $lead_stages_query = $this->workspace->lead_stages()->orderBy($sort, $order);

        if ($search) {
            $lead_stages_query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        $total = $lead_stages_query->count();

        $canEdit = checkPermission('manage_leads');
        $canDelete = checkPermission('manage_leads');

        $lead_stages = $lead_stages_query
            ->paginate($limit)
            ->through(function ($lead_stage) use ($canEdit, $canDelete) {
                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-lead-stage" data-bs-toggle="modal" data-bs-target="#edit_lead_stage_modal" data-id="' . $lead_stage->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $lead_stage->id . '" data-type="lead-stages">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                return [
                    'id' => $lead_stage->id,
                    'name' => ucwords($lead_stage->name),
                    'preview' => '<span class="badge bg-' . ($lead_stage->color ?? 'secondary') . '">' . $lead_stage->name . '</span>',
                    'order' => $lead_stage->order,
                    'actions' => $actions,
                ];
            });

        return response()->json([
            "rows" => $lead_stages->items(),
            "total" => $total,
        ]);
    }

    /**
     * List lead stages with optional filters, sorting, and pagination.
     *
     * This endpoint retrieves a paginated list of lead stages for the current workspace, with optional search, sorting, and pagination parameters. The response includes permission details for editing and deletion. The user must be authenticated and authorized to manage lead stages.
     *
     * @authenticated
     *
     *@group Leads Stage Management
     *
     * @queryParam search string optional Filters lead stages by name or ID. Example: Proposal
     * @queryParam sort string optional The column to sort by (id, name, order). Defaults to id. Example: name
     * @queryParam order string optional The sort order (ASC, DESC). Defaults to DESC. Example: ASC
     * @queryParam limit integer optional Number of lead stages per page (1-100). Defaults to 10. Example: 20
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead stages retrieved successfully!",
     *   "data": {
     *     "total": 3,
     *     "data": [
     *       {
     *         "id": 1,
     *         "name": "New Lead",
     *         "slug": "new-lead",
     *         "color": "primary",
     *         "order": 1,
     *         "workspace_id": 1,
     *         "created_at": "2025-05-10T12:30:00.000000Z",
     *         "updated_at": "2025-05-15T09:12:00.000000Z",
     *         "can_edit": true,
     *         "can_delete": true
     *       },
     *       {
     *         "id": 2,
     *         "name": "Qualified",
     *         "slug": "qualified",
     *         "color": "success",
     *         "order": 2,
     *         "workspace_id": 1,
     *         "created_at": "2025-05-10T12:30:00.000000Z",
     *         "updated_at": "2025-05-15T09:12:00.000000Z",
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
     *   "message": "An error occurred while retrieving the lead stages."
     * }
     */
    public function apiList()
    {
        try {
            $search = request('search');
            $sort = request('sort', "id");
            $order = request('order', "DESC");
            $limit = request('limit', 10);

            $lead_stages_query = $this->workspace->lead_stages()->orderBy($sort, $order);

            if ($search) {
                $lead_stages_query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('id', 'like', '%' . $search . '%');
                });
            }

            $total = $lead_stages_query->count();

            $lead_stages = $lead_stages_query
                ->take($limit)
                ->get()
                ->map(function ($lead_stage) {
                    return formatLeadStage($lead_stage);
                });

            return formatApiResponse(
                false,
                'Lead Stage(s) retrieved Successfully.',
                [
                    'total' => $total,
                    'data' => $lead_stages,
                    'permissions' => [
                        'can_delete' => checkPermission('manage_leads'),
                        'can_edit' => checkPermission('manage_leads')
                    ]
                ],
                200
            );
        } catch (\Exception $e) {
            dd($e);
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred.',
                [],
                500
            );
        }
    }

    /**
     * Reorder lead stages.
     *
     * This endpoint updates the order of lead stages based on the provided array of IDs and positions. The user must be authenticated and authorized to manage lead stages.
     *
     * @authenticated
     *
     * @group Leads Stage Management
     *
     * @bodyParam order array required An array of objects containing lead stage IDs and their new positions. Example: [{"id": 1, "position": 1}, {"id": 2, "position": 2}]
     * @bodyParam order[].id integer required The ID of the lead stage to reorder. Must exist in the `lead_stages` table. Example: 1
     * @bodyParam order[].position integer required The new position for the lead stage. Example: 1
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead stages reordered successfully!",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Qualified",
     *       "slug": "qualified",
     *       "color": "success",
     *       "order": 1,
     *       "workspace_id": 1,
     *       "created_at": "2025-05-10T12:30:00.000000Z",
     *       "updated_at": "2025-05-15T09:12:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "Contacted",
     *       "slug": "contacted",
     *       "color": "info",
     *       "order": 2,
     *       "workspace_id": 1,
     *       "created_at": "2025-05-10T12:30:00.000000Z",
     *       "updated_at": "2025-05-15T09:12:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "order": ["The order field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while reordering the lead stages."
     * }
     */
    public function reorder(Request $request)
    {
        $isApi = $request->get('isApi', false);
        try {
            $request->validate([
                'order' => 'required|array',
                'order.*.id' => 'required|integer|exists:lead_stages,id',
                'order.*.position' => 'required|integer'
            ]);

            foreach ($request->order as $item) {
                LeadStage::where('id', $item['id'])->update([
                    'order' => $item['position']
                ]);
            }

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Lead Stages Reordered Successfully',
                    [
                        'data' => LeadStage::where('workspace_id', $this->workspace->id)->orderBy('order')->get()->toArray(),
                    ],
                    200
                );
            } else {
                return response()->json([
                    'error' => false,
                    'message' => 'Lead Stages Reordered Successfully'
                ]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Lead Stages Reordering Failed.',
                    [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile()
                    ],
                    500
                );
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Lead Stages Reordering Failed.',
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]);
            }
        }
    }
}
