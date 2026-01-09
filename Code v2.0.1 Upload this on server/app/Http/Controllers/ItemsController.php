<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Item;
use Illuminate\Support\Facades\Session;
use App\Services\DeletionService;
use Exception;
use Illuminate\Validation\ValidationException;

class ItemsController extends Controller
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
        $items = $this->workspace->items();
        $items = $items->count();
        $units = $this->workspace->units;
        return view('items.list', ['items' => $items, 'units' => $units]);
    }
    /**
     * Create an item.
     *
     * This endpoint creates an item. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Item Management
     *
     * @bodyParam id string required The id of the item. Example: 1
     * @bodyParam title string required The title of the item. Example: Title
     * @bodyParam description string  The description of the item. Example:  description
     * @bodyParam price string  The price of the item. Example: 400
     * @bodyParam unit_id string  The unit_id of the item. Example: 4
     *
     * @response 200 {
     * "error": false,
     * "message": "Item created successfully.",
     * "id": 36,
     * "data": {
     *           "id": 1,
                 "title": "title",
                 "price" : 100,
                 "unit_id": 1,
                 "description" : "description",
                 "created_at": "2025-04-16 09:41:57",
                 "updated_at": "2025-04-16 09:41:57"
     *          }
     *
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": [
     *       "The  id field is required."
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
     *   "message": "An error occurred while creating the item."
     * }
     */
    public function store(Request $request)
    {
        try {
            $isApi = $request->get('isApi', false);
            // Validate the request data
            $formFields = $request->validate([
                'title' => 'required|unique:items,title',
                'price' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'price');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'unit_id' => 'nullable',
                'description' => 'nullable',
            ], [
                'price.regex' => 'The price must be a valid number with or without decimals.'
            ]);
            $formFields['price'] = str_replace(',', '', $request->input('price'));
            $formFields['workspace_id'] = $this->workspace->id;
            $res = Item::create($formFields);
            $res->load('unit');
            
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Item created successfully.',
                    [
                        'id' => $res->id,
                        'data' => [
                            'id' => $res->id,
                            'title' => $res->title,
                            'price' => format_currency($res->price, false, false),
                            'unit_id' => (string) $res->unit_id,
                            'unit_name' => $res->unit ?  $res->unit->title :'-',
                            'description' => $res->description,
                            'created_at' => format_date($res->created_at, true, to_format: 'Y-m-d'),
                            'updated_at' => format_date($res->updated_at, true, to_format: 'Y-m-d')
                        ]
                    ]
                );
            } else {
                return response()->json(['error' => false, 'message' => 'Item created successfully.', 'id' => $res->id, 'item' => $res]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Item couldn\'t created',
                    []
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Item couldn\'t created.']);
            }
        }
    }

    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $unit_ids = request('unit_ids');
        $where = ['items.workspace_id' => $this->workspace->id];
        $items = Item::select(
            'items.*',
            'units.title as unit'
        )
            ->leftJoin('units', 'items.unit_id', '=', 'units.id');
        if ($search) {
            $items = $items->where(function ($query) use ($search) {
                $query->where('items.title', 'like', '%' . $search . '%')
                    ->orWhere('items.description', 'like', '%' . $search . '%')
                    ->orWhere('price', 'like', '%' . $search . '%')
                    ->orWhere('unit_id', 'like', '%' . $search . '%')
                    ->orWhere('items.id', 'like', '%' . $search . '%');
            });
        }
        if (!empty($unit_ids)) {
            $items = $items->whereIn('unit_id', $unit_ids);
        }
        $items->where($where);
        $canEdit = checkPermission('edit_items');
        $canDelete = checkPermission('delete_items');

        $total = $items->count();
        $items = $items->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($item) use ($canEdit, $canDelete) {
                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-item" data-id="' . $item->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $item->id . '" data-type="items">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                $actions = $actions ?: '-';

                return [
                    'id' => $item->id,
                'unit_id' =>  $item->unit_id,
                    'unit' => $item->unit,
                    'title' => $item->title,
                    'price' => format_currency($item->price),
                    'description' => $item->description,
                    'created_at' => format_date($item->created_at, true),
                    'updated_at' => format_date($item->updated_at, 'H:i:s'),
                    'actions' => $actions,
                ];
            });

        return response()->json([
            "rows" => $items->items(),
            "total" => $total,
        ]);
    }



    public function get($id)
    {
        $item = Item::findOrFail($id);
        $item->price = format_currency($item->price, false, false);
        return response()->json(['item' => $item]);
    }
    /**
     * Update an item.
     *
     * This endpoint updates an item. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Item Management
     *
     * @bodyParam id string required The id of the item. Example: 1
     * @bodyParam title string required The title of the item. Example: Title
     * @bodyParam description string  The description of the item. Example:  description
     * @bodyParam price string  The price of the item. Example: 400
     * @bodyParam unit_id string  The unit_id of the item. Example: 4
     *
     * @response 200 {
     * "error": false,
     * "message": "Item updated successfully.",
     * "id": 36,
     * "data": {
     *           "id": 1,
                 "title": "title",
                 "price" : 100,
                 "unit_id": 1,
                 "description" : "description",
                 "created_at": "2025-04-16 09:41:57",
                 "updated_at": "2025-04-16 09:41:57"
     *          }
     *
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": [
     *       "The  id field is required."
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
     *   "message": "An error occurred while updating the item."
     * }
     */
    public function update(Request $request)
    {
        try {
            $isApi = $request->get('isApi', false);
            // Validate the request data
            $formFields = $request->validate([
                'title' => 'required|unique:items,title,' . $request->id,
                'price' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'price');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'unit_id' => 'nullable',
                'description' => 'nullable',
            ]);
            $formFields['price'] = str_replace(',', '', $request->input('price'));
            $formFields['workspace_id'] = $this->workspace->id;

            $item = Item::findOrFail($request->id);
            $item->update($formFields);
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Item updated successfully',
                    [
                        'id' => $item->id,
                        'data' => [
                            'id' => $item->id,
                            'title' => $item->title,
                            'price' => format_currency($item->price, false, false),
                            'unit_id' => (string)$item->unit_id,
                            'unit_name' => $item->unit->title,
                            'description' => $item->description,
                            'created_at' => format_date($item->created_at, true, to_format: 'Y-m-d'),
                            'updated_at' => format_date($item->updated_at, true, to_format: 'Y-m-d')
                        ]
                    ]
                );
            } else {
                return response()->json(['error' => false, 'message' => 'Item updated successfully.', 'id' => $item->id]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Item couldn\'t updated',
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Item couldn\'t updated.']);
            }
        }
    }
    /**
     * Remove the specified item.
     *
     * This endpoint deletes a item based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Item Management
     *
     * @urlParam id int required The ID of the item to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Item deleted successfully.",
     *   "id": 1,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Item not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the Item."
     * }
     */
    public function destroy($id)
    {
        $response = DeletionService::delete(Item::class, $id, 'Item');
        return $response;
    }
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:items,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $unit = Item::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $unit->title;
            DeletionService::delete(Item::class, $id, 'Item');
        }

        return response()->json(['error' => false, 'message' => 'Item(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
    /**
     * Retrieve a list of items or a specific item.
     *
     * This endpoint fetches item records associated with the authenticated user's workspace. You can retrieve a single item by its ID or fetch a paginated list using filters such as search terms, sorting, unit filters, and pagination controls.
     *
     * Filters available:
     * - **search**: Filter by title, description, price, unit ID, or item ID (partial match).
     * - **unit_ids**: Filter by one or more unit IDs (exact match).
     * - **id**: If provided, fetches a single item record with detailed fields.
     *
     * @authenticated
     *
     * @group Item Management
     *
     * @urlParam id int optional The ID of the item to retrieve a single record. If not provided, a list will be returned. Example: 5
     *
     * @queryParam search string optional Filter items by title, description, price, unit_id, or item ID. Example: Water Bottle
     * @queryParam sort string optional Column to sort by. Defaults to "id". Available values: id, title, price, created_at, updated_at. Example: title
     * @queryParam order string optional Sort direction: ASC or DESC. Defaults to "DESC". Example: ASC
     * @queryParam unit_ids array optional Filter items by one or more unit IDs. Example: [1, 2, 3]
     * @queryParam limit int optional Number of items to return. Default is 10. Example: 15
     * @queryParam offset int optional Offset for paginated data. Default is 0. Example: 20
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Items Retrieved Successfully",
     *   "total": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Notebook",
     *       "price": "₹100.00",
     *       "unit_id": 2,
     *       "unit_name": "Piece",
     *       "description": "A ruled notebook",
     *       "created_at": "2025-05-01",
     *       "updated_at": "2025-05-04"
     *     },
     *     ...
     *   ]
     * }
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Item Retrieved Successfully",
     *   "total": 1,
     *   "data": {
     *       "id": 1,
     *       "title": "Notebook",
     *       "price": "₹100.00",
     *       "unit_id": 2,
     *       "unit_name": "Piece",
     *       "description": "A ruled notebook",
     *       "created_at": "2025-05-01",
     *       "updated_at": "2025-05-04"
     *   }
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Item not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Items Not Found",
     *   "total": 0,
     *   "data": []
     * }
     */

    public function apiList()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $unit_ids = request('unit_ids');
        $where = ['items.workspace_id' => $this->workspace->id];
        $id = request('id', null);
        $limit = request('limit', 10);
        $offset = request('offset', 0);
        $items = Item::select(
            'items.*',
            'units.title as unit'
        )
            ->leftJoin('units', 'items.unit_id', '=', 'units.id');
        if ($search) {
            $items = $items->where(function ($query) use ($search) {
                $query->where('items.title', 'like', '%' . $search . '%')
                    ->orWhere('items.description', 'like', '%' . $search . '%')
                    ->orWhere('price', 'like', '%' . $search . '%')
                    ->orWhere('unit_id', 'like', '%' . $search . '%')
                    ->orWhere('items.id', 'like', '%' . $search . '%');
            });
        }
        if (!empty($unit_ids)) {
            $items = $items->whereIn('unit_id', $unit_ids);
        }
        $items->where($where);
        $total = $items->count();
        if ($id) {
            $item = $items->find($id);
            if (!$item) {
                return formatApiResponse(
                    false,
                    'Item not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            return formatApiResponse(
                false,
                'Item Retrived Successfully',
                [
                    'total' => 1,
                    'data' => [
                        'id' => $item->id,
                        'title' => $item->title,
                        'price' => format_currency($item->price, false, false),
                        'unit_id' => (string)$item->unit_id,
                        'unit_name' => $item->unit,
                        'description' => $item->description,
                        'created_at' => format_date($item->created_at, true, to_format: 'Y-m-d'),
                        'updated_at' => format_date($item->updated_at, true, to_format: 'Y-m-d')
                    ]
                ]
            );
        } else {
            $items = $items->orderBy($sort, $order)->skip($offset)->take($limit)->get();
            if ($items->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Items Not Found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $data = $items->map(function ($item) {

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'price' => format_currency($item->price, false, false),
                    'unit_id' => (string)$item->unit_id,
                    'unit_name' => $item->unit,
                    'description' => $item->description,
                    'created_at' => format_date($item->created_at, true, to_format: 'Y-m-d'),
                    'updated_at' => format_date($item->updated_at, true, to_format: 'Y-m-d')

                ];
            });
            return formatApiResponse(
                false,
                'Items Retrived Successfully',
                [
                    'total' => $total,
                    'data' => $data
                ]
            );
        }
    }
}
