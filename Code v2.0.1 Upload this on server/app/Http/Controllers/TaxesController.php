<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Tax;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class TaxesController extends Controller
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
        $taxes = $this->workspace->taxes();
        $taxes = $taxes->count();
        return view('taxes.list', ['taxes' => $taxes]);
    }
    /**
     * Create a tax.
     *
     * This endpoint creates a tax. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Tax Management
     *
     * @bodyParam title string required The title of the tax. Example: Title
     * @bodyParam type string required The type of the tax. Example: amount
     * @bodyParam amount string required if type is amount The amount of the tax. Example: 100
     * @bodyParam percentage string required if type is percentage The percentage of the tax. Example: 10

     *
     * @response 200 {
     * "error": false,
     * "message": "Tax created successfully.",
     * "id": 36,
     * "data": {
     *           'id' => '1',
     *           'title' => 'Title',
     *           'type' => 'amount',
     *           'amount' => '100',
     *           'percentage' => null,
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
     *       "The id field is required."
     *     ],
     *     "title": [
     *       "The title field is required."
     *     ],
     *     "type": [
     *       "The type field is required."
     *     ],
     *
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the tax."
     * }
     */
    public function store(Request $request)
    {
        try {
            $isApi = $request->get('isApi', false);
            // Validate the request data
            $validatedData = $request->validate([
                'title' => 'required|unique:taxes,title',
                'type' => [
                    'required',
                    Rule::in(['amount', 'percentage']),
                ],
                'amount' => [
                    Rule::requiredIf(function () use ($request) {
                        return $request->type === 'amount';
                    }),
                    'nullable',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'amount');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'percentage' => [
                    Rule::requiredIf(function () use ($request) {
                        return $request->type === 'percentage';
                    }),
                    'nullable',
                    'numeric',
                ],
            ], [
                'percentage.numeric' => 'Percentage must be a numeric value.'
            ]);
            $validatedData['amount'] = str_replace(',', '', $request->input('amount'));
            $validatedData['amount'] = $validatedData['amount'] !== '' ? $validatedData['amount'] : null;

            // Set workspace_id
            $validatedData['workspace_id'] = $this->workspace->id;
            $tax = Tax::create($validatedData);
            // Create Tax instance
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Tax created successfully',
                    [
                        'id' => $tax->id,
                        'data' => [
                            'id' => $tax->id,
                            'title' => $tax->title,
                            'type' => $tax->type,
                            'amount' => format_currency($tax->amount, false, false),
                            'percentage' => $tax->percentage,
                            'created_at' => format_date($tax->created_at, true, to_format: 'Y-m-d'),
                            'updated_at' => format_date($tax->updated_at, true, to_format: 'Y-m-d')
                        ]
                    ]
                );
            } else {
                return response()->json([
                    'error' => false,
                    'message' => 'Tax created successfully.',
                    'id' => $tax->id,
                ]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Tax couldn\'t be created',
                    []
                );
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Tax couldn\'t be created.',
                ]);
            }
        }
    }


    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $types = request('types');
        $taxes = $this->workspace->taxes();
        if ($search) {
            $taxes = $taxes->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('percentage', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        if (!empty($types)) {
            $taxes = $taxes->whereIn('type', $types);
        }
        $canEdit = checkPermission('edit_taxes');
        $canDelete = checkPermission('delete_taxes');
        $total = $taxes->count();
        $taxes = $taxes->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($tax) use ($canEdit, $canDelete) {
                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-tax" data-id="' . $tax->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $tax->id . '" data-type="taxes">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                $actions = $actions ?: '-';

                return [
                    'id' => $tax->id,
                    'title' => $tax->title,
                    'type' => ucfirst($tax->type),
                    'percentage' => $tax->percentage,
                'amount' => $tax->amount,
                    'created_at' => format_date($tax->created_at, true),
                    'updated_at' => format_date($tax->updated_at, 'H:i:s'),
                    'actions' => $actions,
                ];
            });

        return response()->json([
            "rows" => $taxes->items(),
            "total" => $total,
        ]);
    }



    public function get($id)
    {
        $tax = Tax::findOrFail($id);
        $tax->amount = format_currency($tax->amount, false, false);
        return response()->json(['tax' => $tax]);
    }
    /**
     * Update a tax.
     *
     * This endpoint updates an existing tax. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Tax Management
     *
     * @bodyParam id string required The id of the tax. Example: 1
     * @bodyParam title string required The title of the tax. Example: Title

     *
     * @response 200 {
     * "error": false,
     * "message": "Tax updated successfully.",
     * "id": 36,
     * "data": {
     *           'id' => '1',
     *           'title' => 'Title',
     *           'type' => 'amount',
     *           'amount' => '100',
     *           'percentage' => null,
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
     *       "The id field is required."
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
     *   "message": "An error occurred while updating the tax."
     * }
     */
    public function update(Request $request)
    {
        try {
            $isApi = $request->get('isApi', false);
            // Validate the request data
            $formFields = $request->validate([
                'title' => 'required|unique:taxes,title,' . $request->id
            ]);

            $formFields['workspace_id'] = $this->workspace->id;

            $tax = Tax::findOrFail($request->id);
            $tax->update($formFields);
            // dd($tax, $request);
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Tax updated successfully.',
                    [
                        'id' => $tax->id,
                        'data' => [
                            'id' => $tax->id,
                            'title' => $tax->title,
                            'type' => $tax->type,
                            'amount' => format_currency($tax->amount, false, false),
                            'percentage' => $tax->percentage,
                            'created_at' => format_date($tax->created_at, true, to_format: 'Y-m-d'),
                            'updated_at' => format_date($tax->updated_at, true, to_format: 'Y-m-d'),
                        ]
                    ]
                );
            } else {
                return response()->json(['error' => false, 'message' => 'Tax updated successfully.', 'id' => $tax->id]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Tax couldn\'t updated',
                    []
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Tax couldn\'t updated.']);
            }
        }
    }
    /**
     * Remove the specified tax.
     *
     * This endpoint deletes a tax based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Tax Management
     *
     * @urlParam id int required The ID of the todo to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Tax deleted successfully.",
     *   "id": 1,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Tax not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the Tax."
     * }
     */
    public function destroy($id)
    {
        $tax = Tax::findOrFail($id);
        DB::table('estimates_invoice_item')
            ->where('tax_id', $tax->id)
            ->update(['tax_id' => null]);
        $response = DeletionService::delete(Tax::class, $id, 'Tax');
        return $response;
    }
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:taxes,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $tax = Tax::findOrFail($id);
            DB::table('estimates_invoice_item')
                ->where('tax_id', $tax->id)
                ->update(['tax_id' => null]);
            $deletedIds[] = $id;
            $deletedTitles[] = $tax->title;
            DeletionService::delete(Tax::class, $id, 'Tax');
        }

        return response()->json(['error' => false, 'message' => 'Tax(es) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
    /**
     * List or search taxes.
     *
     * This endpoint retrieves a list of taxes based on various filters. The user must be authenticated to perform this action.
     * It supports searching, sorting, filtering by type, and pagination.
     *
     * @authenticated
     *
     * @group Tax Management
     *
     * @urlParam id int optional The ID of the tax to retrieve. Example: 1
     *
     * @queryParam search string optional The term to search taxes by title, amount, percentage, type, or ID. Example: GST
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, created_at, updated_at. Example: created_at
     * @queryParam order string optional The sorting order, either ASC or DESC. Defaults to DESC. Example: ASC
     * @queryParam types array optional Filter taxes by type. Accepts an array of types such as "percentage" or "fixed". Example: ["percentage"]
     * @queryParam limit int optional Number of records per page. Defaults to 10. Example: 10
     * @queryParam offset int optional The offset for pagination. Defaults to 0. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Taxes retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "GST",
     *       "type": "percentage",
     *       "amount": null,
     *       "percentage": 18,
     *       "created_at": "2025-04-16",
     *       "updated_at": "2025-04-16"
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Tax retrieved successfully",
     *   "total": 1,
     *   "data": {
     *     "id": 1,
     *     "title": "GST",
     *     "type": "percentage",
     *     "amount": null,
     *     "percentage": 18,
     *     "created_at": "2025-04-16",
     *     "updated_at": "2025-04-16"
     *   }
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Tax not found",
     *   "total": 0,
     *   "data": []
     * }
     */

    public function apiList()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $types = request('types');
        $id = request('id', null);
        $limit = request('limit', 10);
        $offset = request('offset', 0);
        $taxes = $this->workspace->taxes();
        if ($search) {
            $taxes = $taxes->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('percentage', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        if (!empty($types)) {
            $taxes = $taxes->whereIn('type', $types);
        }
        $total = $taxes->count();
        if ($id) {
            $tax = $taxes->find($id);
            if (!$tax) {
                return formatApiResponse(
                    false,
                    'Tax Not Found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            return formatApiResponse(
                false,
                'Tax Retrived Successfully',
                [
                    'total' => 1,
                    'data' => [
                        'id' => $tax->id,
                        'title' => $tax->title,
                        'type' => $tax->type,
                        'amount' => format_currency($tax->amount, false, false),
                        'percentage' => $tax->percentage,
                        'created_at' => format_date($tax->created_at, true, to_format: 'Y-m-d'),
                        'updated_at' => format_date($tax->updated_at, true, to_format: 'Y-m-d')
                    ]
                ]
            );
        } else {
            $taxes = $taxes->orderBy($sort, $order)->skip($offset)->take($limit)->get();
            if ($taxes->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Taxes Not Found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $data = $taxes->map(function ($tax) {
                return [
                    'id' => $tax->id,
                    'title' => $tax->title,
                    'type' => $tax->type,
                    'amount' => format_currency($tax->amount, false, false),
                    'percentage' => $tax->percentage,
                    'created_at' => format_date($tax->created_at, true, to_format: 'Y-m-d'),
                    'updated_at' => format_date($tax->updated_at, true, to_format: 'Y-m-d')
                ];
            });

            return formatApiResponse(
                false,
                'Taxes Retrived Successfully',
                [
                    'total' => $total,
                    'data' => $data
                ]
            );
        }
    }
}
