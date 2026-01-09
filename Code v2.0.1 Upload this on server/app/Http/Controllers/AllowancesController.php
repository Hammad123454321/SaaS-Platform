<?php

namespace App\Http\Controllers;

use App\Models\Allowance;
use App\Models\Workspace;

use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class AllowancesController extends Controller
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
        $allowances = $this->workspace->allowances();
        $allowances = $allowances->count();
        return view('allowances.list', ['allowances' => $allowances]);
    }


    /**
     * Create a new allowance.
     *
     * This endpoint creates a new allowance with the given title and amount. The user must be authenticated to perform this action. The request can be made via API or non-API calls, with an optional `isApi` parameter to format the response accordingly.
     *
     * @authenticated
     * @group Allowance Management
     *
     * @bodyParam title string required The title of the allowance. Must be unique. Example: Transport Allowance
     * @bodyParam amount string required The amount in currency format. Example: 1500.00
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Allowance created successfully.",
     *   "data": {
     *     "id": 6,
     *     "title": "Transport Allowance",
     *     "amount": "$1,500.00",
     *     "created_at": "01 Dec 2024",
     *     "updated_at": "15:45:22"
     *   }
     * }
     *
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "title": ["The title field is required."],
     *     "amount": ["The amount format is invalid."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Allowance couldn't created.",
     *   "data": []
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
            $formFields = $request->validate([
                'title' => 'required|unique:allowances,title', // Validate the title
                'amount' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'amount');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ]
            ]);
            $formFields['amount'] = str_replace(',', '', $request->input('amount'));
            $formFields['workspace_id'] = $this->workspace->id;

            if ($allowance = Allowance::create($formFields)) {

                if ($isApi) {
                    return formatApiResponse(
                        false,
                        'Allowance created successfully.',
                        [
                            'data' => formatAllowance($allowance)
                        ],
                        200
                    );
                }

                return response()->json(['error' => false, 'message' => 'Allowance created successfully.', 'id' => $allowance->id, 'allowance' => $allowance]);
            } else {

                if ($isApi) {
                    return formatApiResponse(
                        true,
                        'Allowance couldn\'t created.',
                        [],

                    );
                }

                return response()->json(['error' => true, 'message' => 'Allowance couldn\'t created.']);
            }
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error Occurred',
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
        $allowances = $this->workspace->allowances();
        if ($search) {
            $allowances = $allowances->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $canEdit = checkPermission('edit_allowances');
        $canDelete = checkPermission('delete_allowances');

        $total = $allowances->count();
        $allowances = $allowances->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($allowance) use ($canEdit, $canDelete) {
                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-allowance" data-id="' . $allowance->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $allowance->id . '" data-type="allowances">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                $actions = $actions ?: '-';

                return [
                    'id' => $allowance->id,
                    'title' => $allowance->title,
                    'amount' => format_currency($allowance->amount),
                    'created_at' => format_date($allowance->created_at, true),
                    'updated_at' => format_date($allowance->updated_at, 'H:i:s'),
                    'actions' => $actions,
                ];
            });

        return response()->json([
            "rows" => $allowances->items(),
            "total" => $total,
        ]);
    }

    /**
     * Retrieve an allowance's details.
     *
     * This endpoint fetches detailed information about a specific allowance by its ID. The user must be authenticated to perform this action. The request can be made via API or non-API calls, with an optional `isApi` parameter to format the response accordingly.
     *
     * @authenticated
     * @group Allowance Management
     *
     * @urlParam id integer required The ID of the allowance to retrieve. Must exist in the `allowances` table. Example: 5
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Allowance retrieved successfully.",
     *   "data": {
     *     "id": 5,
     *     "title": "Fuel Allowance",
     *     "amount": "$200.00",
     *     "created_at": "01 Dec 2024",
     *     "updated_at": "10:30:15"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Allowance not found!",
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

            $allowance = Allowance::findOrFail($id);
            $allowance->amount = format_currency($allowance->amount, false, false);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Allowance retrieved successfully.',
                    [
                        'data' => formatAllowance($allowance)
                    ],
                    200
                );
            }
            return response()->json(['allowance' => $allowance]);
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
     * Update an existing allowance.
     *
     * This endpoint allows you to update the title and amount of an existing allowance. The user must be authenticated and authorized to perform this action. The title must remain unique across all allowances. The request can be made via API or non-API calls, with an optional `isApi` parameter to format the response accordingly.
     *
     * @authenticated
     *
     * @group Allowance Management
     *
     * @bodyParam id integer required The ID of the allowance to update. Must exist in the `allowances` table. Example: 5
     * @bodyParam title string required The title of the allowance. Must be unique in the `allowances` table. Example: Housing Allowance
     * @bodyParam amount string required The amount of the allowance. Must be a valid currency format. Example: 1200.00
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Allowance updated successfully.",
     *   "data": {
     *     "id": 5,
     *     "title": "Housing Allowance",
     *     "amount": "$1,200.00",
     *     "created_at": "01 Dec 2024",
     *     "updated_at": "16:30:45"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Allowance not found!",
     *   "data": []
     * }
     *
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "title": [
     *       "The title has already been taken."
     *     ],
     *     "amount": [
     *       "The amount format is invalid."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Allowance couldn't updated.",
     *   "data": []
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

            $formFields = $request->validate([
                'id' => 'required',
                'title' => 'required|unique:allowances,title,' . $request->id,
                'amount' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'amount');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ]
            ]);
            $allowance = Allowance::findOrFail($request->id);
            $formFields['amount'] = str_replace(',', '', $request->input('amount'));
            if ($allowance->update($formFields)) {

                if ($isApi) {
                    return formatApiResponse(
                        false,
                        'Allowance updated successfully.',
                        [
                            'data' => formatAllowance($allowance)
                        ]
                    );
                }

                return response()->json(['error' => false, 'message' => 'Allowance updated successfully.', 'id' => $allowance->id]);
            } else {

                if ($isApi) {
                    return formatApiResponse(
                        true,
                        'Allowance couldn\'t updated.',
                        []
                    );
                }

                return response()->json(['error' => true, 'message' => 'Allowance couldn\'t updated.']);
            }
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occcurred.',
                [],
                500
            );
        }
    }


    /**
     * Delete an allowance.
     *
     * This endpoint deletes a specific allowance by its ID and automatically detaches any related payslips. The user must be authenticated and authorized to perform this action.
     *
     * @authenticated
     *
     * @group Allowance Management
     *
     * @urlParam id integer required The ID of the allowance to delete. Must exist in the `allowances` table. Example: 7
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Allowance deleted successfully.",
     *   "data": []
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Allowance not found!",
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

            $allowance = Allowance::findOrFail($id);
            $allowance->payslips()->detach();
            $response = DeletionService::delete(Allowance::class, $id, 'Allowance');
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
            'ids.*' => 'integer|exists:allowances,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $allowance = Allowance::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $allowance->title;
            $allowance->payslips()->detach();
            DeletionService::delete(Allowance::class, $id, 'Allowance');
        }

        return response()->json(['error' => false, 'message' => 'Allowance(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }


    /**
     * Get allowances list
     *
     * This endpoint returns a filtered and limited list of allowances, specifically formatted for API use. The user must be authenticated to perform this action. This endpoint provides search, sort, and limit functionality optimized for API consumers.
     *
     * @authenticated
     *
     * @group Allowance Management
     *
     * @queryParam search string optional Search by ID, title, or amount. Example: Bonus
     * @queryParam sort string optional Column to sort by. Defaults to "id". Example: amount
     * @queryParam order string optional Sort order: ASC or DESC. Defaults to "DESC". Example: ASC
     * @queryParam limit integer optional Maximum number of records to return. Defaults to 10. Example: 5
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Allowances retrieved successfully.",
     *     "total": 2,
     *     "data": [
     *       {
     *         "id": 1,
     *         "title": "Bonus",
     *         "amount": "$500.00",
     *         "created_at": "01 Dec 2024",
     *         "updated_at": "10:30:15"
     *       },
     *       {
     *         "id": 2,
     *         "title": "Medical",
     *         "amount": "$300.00",
     *         "created_at": "01 Dec 2024",
     *         "updated_at": "11:45:22"
     *       }
     *     ]
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred",
     *   "data": []
     * }
     */
    public function apiList()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $limit = (request('order')) ? request('limit') : 10;
        $allowances = $this->workspace->allowances();
        if ($search) {
            $allowances = $allowances->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        $total = $allowances->count();
        $allowances = $allowances->orderBy($sort, $order)
            ->take($limit)
            ->get()
            ->map(function ($allowance) {

                return formatAllowance($allowance);
            });

        return formatApiResponse(
            false,
            'Allowances retrieved successfully.',
            [
                'total' => $total,
                'data' => $allowances
            ]
        );
    }
}
