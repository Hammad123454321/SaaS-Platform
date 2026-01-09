<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Deduction;
use Illuminate\Support\Facades\Session;
use App\Services\DeletionService;

class DeductionsController extends Controller
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
        $deductions = $this->workspace->deductions();
        $deductions = $deductions->count();
        return view('deductions.list', ['deductions' => $deductions]);
    }


    /**
     * Create a new deduction.
     *
     * Creates a deduction in the current workspace. Deductions can be of type `amount` or `percentage`.
     *
     * @group Deduction Management
     *
     * @bodyParam title string required The name/title of the deduction. Must be unique. Example: Income Tax
     * @bodyParam type string required The type of deduction. Either "amount" or "percentage". Example: percentage
     * @bodyParam amount string The fixed amount for the deduction. Required if type is "amount". Example: 150.00
     * @bodyParam percentage numeric The percentage value of the deduction. Required if type is "percentage". Example: 5
     * @bodyParam isApi boolean optional Whether to return a formatted API response. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Deduction created successfully.",
     *   "data": {
     *     "id": 9,
     *     "title": "Income Tax",
     *     "type": "Percentage",
     *     "percentage": 5,
     *     "amount": "0.00",
     *     "created_at": "30 May, 2025",
     *     "updated_at": "30 May, 2025"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The given data was invalid.",
     *   "data": {
     *     "errors": {
     *       "title": ["The title field is required."],
     *       "type": ["The type field is required."]
     *     }
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred",
     *   "data": []
     * }
     */

    public function store(Request $request)
    {

        try {

            $isApi = request()->get('isApi', false);

            // Validate the request data
            $validatedData = $request->validate([
                'title' => 'required|unique:deductions,title',
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
            $validatedData['workspace_id'] = $this->workspace->id;
            if ($deduction = Deduction::create($validatedData)) {

                if ($isApi) {
                    return formatApiResponse(
                        false,
                        'Deduction created successfully.',
                        [
                            'data' => formatDeduction($deduction)
                        ]
                    );
                }

                return response()->json(['error' => false, 'message' => 'Deduction created successfully.', 'id' => $deduction->id, 'deduction' => $deduction]);
            } else {

                if ($isApi) {
                    return formatApiResponse(
                        true,
                        'Deduction couldn\'t created.',
                        [],

                    );
                }

                return response()->json(['error' => true, 'message' => 'Deduction couldn\'t created.']);
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

    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $types = request('types');
        $deductions = $this->workspace->deductions();
        if ($search) {
            $deductions = $deductions->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('percentage', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        if (!empty($types)) {
            $deductions = $deductions->whereIn('type', $types);
        }
        $canEdit = checkPermission('edit_deductions');
        $canDelete = checkPermission('delete_deductions');

        $total = $deductions->count();
        $deductions = $deductions->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($deduction) use ($canEdit, $canDelete) {
                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-deduction" data-id="' . $deduction->id . '" title="' . get_label('update', 'Update') . '" class="card-link">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $deduction->id . '" data-type="deductions">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                $actions = $actions ?: '-';

                return [
                    'id' => $deduction->id,
                    'title' => $deduction->title,
                    'type' => ucfirst($deduction->type),
                    'percentage' => $deduction->percentage,
                    'amount' => format_currency($deduction->amount),
                    'created_at' => format_date($deduction->created_at, true),
                    'updated_at' => format_date($deduction->updated_at, true),
                    'actions' => $actions,
                ];
            });

        return response()->json([
            "rows" => $deductions->items(),
            "total" => $total,
        ]);
    }


    /**
     * Get list of deductions.
     *
     * Returns a list of deductions for the current workspace in API format, with optional filtering and sorting.
     *
     * @group Deduction Management
     *
     * @queryParam search string optional Search keyword to filter deductions. Example: Tax
     * @queryParam sort string optional Field to sort by. Defaults to "id". Example: title
     * @queryParam order string optional Sort order: ASC or DESC. Defaults to DESC. Example: ASC
     * @queryParam limit integer optional Number of records to return. Defaults to 10. Example: 25
     * @queryParam types[] string[] optional Filter by deduction types. Options: amount, percentage. Example: ["percentage"]
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Deductions retrieved successfully.",
     *     "total": 1,
     *     "data": [
     *       {
     *         "id": 1
     *         "title": "Income Tax",
     *         "type": "Percentage",
     *         "percentage": 5,
     *         "amount": "0.00",
     *         "created_at": "30 May, 2025",
     *         "updated_at": "30 May, 2025"
     *       }
     *     ]
     * }
     */


    public function apiList()
    {

        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $limit = (request('order')) ? request('limit') : 10;
        $types = array_filter(request('types', []), fn($v) => filled($v));
        $deductions = $this->workspace->deductions();
        if ($search) {
            $deductions = $deductions->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('percentage', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        if (!empty($types)) {
            $deductions = $deductions->whereIn('type', $types);
        }

        $total = $deductions->count();
        $deductions = $deductions->orderBy($sort, $order)
            ->take($limit)
            ->get()
            ->map(function ($deduction) {

                return formatDeduction($deduction);
            });


        return formatApiResponse(
            false,
            'Deduction retrieved successfully.',
            [
                'total' => $total,
                'data' => $deductions
            ]
        );
    }


    /**
     * Get a deduction by ID.
     *
     * Retrieves a specific deduction using its ID.
     *
     * @group Deduction Management
     *
     * @urlParam id integer required The ID of the deduction to retrieve. Must exist in the `deductions` table. Example: 1
     * @queryParam isApi boolean optional Whether to return a formatted API response. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Deduction retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "title": "Income Tax",
     *     "type": "Percentage",
     *     "percentage": 5,
     *     "amount": "0.00",
     *     "created_at": "30 May, 2025",
     *     "updated_at": "30 May, 2025"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "No query results for model [App\\Models\\Deduction] 9999",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred",
     *   "data": []
     * }
     */

    public function get($id)
    {
        try {

            $isApi = request()->get('isApi', false);

            $deduction = Deduction::findOrFail($id);
            $deduction->amount = format_currency($deduction->amount, false, false);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Deduction retrieved successfully.',
                    [
                        'data' => formatDeduction($deduction)
                    ]
                );
            }

            return response()->json(['deduction' => $deduction]);
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
     * Update an existing deduction.
     *
     * Updates the specified deduction in the current workspace.
     *
     * @group Deduction Management
     *
     * @bodyParam id integer required The ID of the deduction to update. Must exist in the `deductions` table. Example: 1
     * @bodyParam title string required The name/title of the deduction. Must be unique. Example: Updated Income Tax
     * @bodyParam type string required The type of deduction. Either "amount" or "percentage". Example: amount
     * @bodyParam amount string optional The fixed amount for the deduction. Required if type is "amount". Example: 100.00
     * @bodyParam percentage numeric optional The percentage value of the deduction. Required if type is "percentage". Example: 10
     * @bodyParam isApi boolean optional Whether to return a formatted API response. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Deduction updated successfully.",
     *   "data": {
     *     "id": 1,
     *     "title": "Updated Income Tax",
     *     "type": "Amount",
     *     "percentage": null,
     *     "amount": "100.00",
     *     "created_at": "30 May, 2025",
     *     "updated_at": "30 May, 2025"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "No query results for model [App\\Models\\Deduction] 9999",
     *   "data": []
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The given data was invalid.",
     *   "data": {
     *     "errors": {
     *       "title": ["The title has already been taken."]
     *     }
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred",
     *   "data": []
     * }
     */


    public function update(Request $request)
    {
        try {
            $isApi = request()->get('isApi', false);

            $validatedData = $request->validate([
                'title' => 'required|unique:deductions,title,' . $request->id,
                'type' => ['required', Rule::in(['amount', 'percentage'])],

                'amount' => [
                    Rule::requiredIf(fn() => $request->type === 'amount'),
                    'nullable',
                    function ($attribute, $value, $fail) {
                        if ($value !== null && $value !== '') {
                            $cleaned = str_replace(',', '', $value);
                            if (!is_numeric($cleaned)) {
                                $fail('The amount must be a valid numeric value.');
                            }
                            if ($cleaned < 0) {
                                $fail('The amount cannot be negative.');
                            }
                        }
                    },
                ],

                'percentage' => [
                    Rule::requiredIf(fn() => $request->type === 'percentage'),
                    'nullable',
                    'numeric',
                    'between:0,100',
                ],
            ], [
                'percentage.numeric' => 'Percentage must be a numeric value.',
                'percentage.between' => 'Percentage must be between 0 and 100.',
                'title.required' => 'Title is required.',
                'title.unique' => 'This deduction title is already taken.',
                'type.required' => 'Type is required.',
                'type.in' => 'Type must be either amount or percentage.',
            ]);

            // Ensure only one type is set, nullify the other
            if ($request->type === 'amount') {
                $validatedData['amount'] = str_replace(',', '', $request->input('amount'));
                $validatedData['percentage'] = null;
            } elseif ($request->type === 'percentage') {
                $validatedData['percentage'] = $request->input('percentage');
                $validatedData['amount'] = null;
            }

            $validatedData['workspace_id'] = $this->workspace->id;

            $deduction = Deduction::findOrFail($request->id);

            if ($deduction->update($validatedData)) {
                if ($isApi) {
                    return formatApiResponse(
                        false,
                        'Deduction updated successfully.',
                        ['data' => formatDeduction($deduction)]
                    );
                }

                return response()->json([
                    'error' => false,
                    'message' => 'Deduction updated successfully.',
                    'id' => $deduction->id,
                ]);
            } else {
                if ($isApi) {
                    return formatApiResponse(true, 'Deduction could not be updated.', []);
                }

                return response()->json([
                    'error' => true,
                    'message' => 'Deduction could not be updated.',
                ]);
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
     * Delete a deduction.
     *
     * Deletes the deduction with the given ID and detaches associated payslips.
     *
     * @group Deduction Management
     *
     * @urlParam id integer required The ID of the deduction to delete. Must exist in the `deductions` table. Example: 2
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Deduction deleted successfully.",
     *   "data": []
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Deduction not found.",
     *   "data": []
     * }
     */

    public function destroy($id)
    {
        $deduction = Deduction::findOrFail($id);
        $deduction->payslips()->detach();
        $response = DeletionService::delete(Deduction::class, $id, 'Deduction');
        return $response;
    }
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:deductions,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $deduction = Deduction::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $deduction->title;
            $deduction->payslips()->detach();
            DeletionService::delete(Deduction::class, $id, 'Deduction');
        }

        return response()->json(['error' => false, 'message' => 'Deduction(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
}
