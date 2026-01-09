<?php

namespace App\Http\Controllers;
use App\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Unit;
use Illuminate\Support\Facades\Session;
use App\Services\DeletionService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class UnitsController extends Controller
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
        $units = $this->workspace->units();
        $units = $units->count();
        return view('units.list', ['units' => $units]);
    }
    /**
     * Create an unit.
     *
     * This endpoint creates an unit. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Unit Management
     *
     * @bodyParam title string required The title of the unit. Example: Title
     * @bodyParam description string  The description of the unit. Example: amount
     *
     * @response 200 {
     * "error": false,
     * "message": "Unit created successfully.",
     * "id": 36,
     * "data": {
     *           'id' => '1',
     *           'title' => 'Title',
     *           'description' => 'Unit Description',
     *           'created_at =>'2025-04-16',
     *           'updated_at' =>'2025-04-16',
     *          }
     *
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *
     *     "title": [
     *       "The title field is required."
     *     ],
     *
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the unit."
     * }
     */

    public function store(Request $request)
    {
        try {
            $isApi = $request->get('isApi', false);
            // Validate the request data
            $formFields = $request->validate([
                'title' => 'required|unique:units,title',
                'description' => 'nullable',
            ]);
            $formFields['workspace_id'] = $this->workspace->id;
            $res = Unit::create($formFields);
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Unit created successfully.',
                    [
                        'id' => $res->id,
                        'data' => [
                            'id' => $res->id,
                            'title' => $res->title,
                            'description' => $res->description,
                            'created_at' => format_date($res->created_at, true, to_format: 'Y-m-d'),
                            'updated_at' => format_date($res->updated_at, true, to_format: 'Y-m-d'),
                        ]
                    ]
                );
            } else {
                return response()->json(['error' => false, 'message' => 'Unit created successfully.', 'id' => $res->id]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Unit couldn\'t created',
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Unit couldn\'t created.']);
            }
        }
    }
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $units = $this->workspace->units();
        if ($search) {
            $units = $units->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $canEdit = checkPermission('edit_units');
        $canDelete = checkPermission('delete_units');
        $total = $units->count();
        $units = $units->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($unit) use ($canEdit, $canDelete) {
            $actions = '';
                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-unit" data-id="' . $unit->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
            }
                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $unit->id . '" data-type="units">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
            }
            $actions = $actions ?: '-';
                return [
                    'id' => $unit->id,
                    'title' => $unit->title,
                    'description' => $unit->description,
                    'created_at' => format_date($unit->created_at, true),
                    'updated_at' => format_date($unit->updated_at, 'H:i:s'),
                    'actions' => $actions,
                ];
            });
        return response()->json([
            "rows" => $units->items(),
            "total" => $total,
        ]);
    }
    public function get($id)
    {
        $unit = Unit::findOrFail($id);
        return response()->json(['unit' => $unit]);
    }

    /**
     * Update an unit.
     *
     * This endpoint updates an unit. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Unit Management
     *
     * @bodyParam id string required The id of the unit. Example: 1
     * @bodyParam title string required The title of the unit. Example: Title
     * @bodyParam description string  The description of the unit. Example: unit description
     *
     * @response 200 {
     * "error": false,
     * "message": "Unit created successfully.",
     * "id": 36,
     * "data": {
     *           'id' => '1',
     *           'title' => 'Title',
     *           'description' => 'Unit Description',
     *           'created_at =>'2025-04-16',
     *           'updated_at' =>'2025-04-16',
     *          }
     *
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": [
     *       "The title id is required."
     *     ],
     *     "title": [
     *       "The title field is required."
     *     ],
     *
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the unit."
     * }
     */
    public function update(Request $request)
    {
        try {

            $isApi = $request->get('isApi', false);
            // Validate the request data
            $formFields = $request->validate([
                'title' => 'required|unique:units,title,' . $request->id,
                'description' => 'nullable',
            ]);
            $formFields['workspace_id'] = $this->workspace->id;
            $unit = Unit::findOrFail($request->id);
            $unit->update($formFields);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Unit updated successfully',
                    [
                        'id' => $unit->id,
                        'data' => [
                            'id' => $unit->id,
                            'title' => $unit->title,
                            'description' => $unit->description,
                            'created_at' => format_date($unit->created_at, true, to_format: 'Y-m-d'),
                            'updated_at' => format_date($unit->updated_at, true, to_format: 'Y-m-d')
                        ]
                    ]
                );
            } else {
                return response()->json(['error' => false, 'message' => 'Unit updated successfully.', 'id' => $unit->id]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Unit couldn\'t updated ' . $e->getMessage(),
                    []
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Unit couldn\'t updated ' . $e->getMessage()]);
            }
        }
    }
    /**
     * Remove the specified unit.
     *
     * This endpoint deletes a unit based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Unit Management
     *
     * @urlParam id int required The ID of the todo to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Unit deleted successfully.",
     *   "id": 1,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Unit not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the Unit."
     * }
     */
    public function destroy($id)
    {
        $unit = Unit::findOrFail($id);
        DB::table('estimates_invoice_item')
            ->where('unit_id', $unit->id)
            ->update(['unit_id' => null]);
        DB::table('items')
            ->where('unit_id', $unit->id)
            ->update(['unit_id' => null]);
        $response = DeletionService::delete(Unit::class, $id, 'Unit');
        return $response;
    }
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:units,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);
        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $unit = Unit::findOrFail($id);
            DB::table('estimates_invoice_item')
                ->where('unit_id', $unit->id)
                ->update(['unit_id' => null]);
            DB::table('items')
                ->where('unit_id', $unit->id)
                ->update(['unit_id' => null]);
            $deletedIds[] = $id;
            $deletedTitles[] = $unit->title;
            DeletionService::delete(Unit::class, $id, 'Unit');
        }
        return response()->json(['error' => false, 'message' => 'Unit(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
    /**
     * List or search units.
     *
     * This endpoint retrieves a list of units based on various filters. The user must be authenticated to perform this action. The request allows searching and sorting by different parameters.
     *
     * @authenticated
     *
     * @group Unit Management
     *
     * @urlParam id int optional The ID of the tag to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter tags by title or id. Example: Title
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id,  created_at, and updated_at. Example: id
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam limit int optional The number of tags per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Unit retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
            "id": 1,
            "title": "title",
            "description" : "unit description",
            "created_at": "2025-04-16 09:41:57",
            "updated_at": "2025-04-16 09:41:57"
           }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Unit not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Units not found",
     *   "total": 0,
     *   "data": []
     * }
     */
    public function apiList()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $id = request('id', null);
        $limit = request('limit', 10);
        $offset = request('offset', 0);
        $units = $this->workspace->units();
        if ($search) {
            $units = $units->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $canEdit = checkPermission('edit_units');
        $canDelete = checkPermission('delete_units');
        $total = $units->count();
        if ($id) {
            $unit = $units->find($id);
            if (!$unit) {
                return formatApiResponse(
                    false,
                    'Unit Not Found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            return formatApiResponse(
                false,
                'Unit Retrived Successfully',
                [
                    'total' => 1,
                    'data' => [
                        'id' => $unit->id,
                        'title' => $unit->title,
                        'description' => $unit->description,
                        'created_at' => format_date($unit->created_at, true, to_format: 'Y-m-d'),
                        'updated_at' => format_date($unit->updated_at, true, to_format: 'Y-m-d')
                    ]
                ]
            );
        } else {
            $units = $units->orderBy($sort, $order)->skip($offset)->take($limit)->get();
            if ($units->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Units not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $data = $units->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'title' => $unit->title,
                    'description' => $unit->description,
                    'created_at' => format_date($unit->created_at, true, to_format: 'Y-m-d'),
                    'updated_at' => format_date($unit->updated_at, true, to_format: 'Y-m-d')
                ];
            });
            return formatApiResponse(
                false,
                'Units Retrived Successfully',
                [
                    'total' => $total,
                    'data' => $data
                ]
            );
        }
    }
}
