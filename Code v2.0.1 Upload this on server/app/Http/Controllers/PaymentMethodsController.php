<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Services\DeletionService;
use Exception;
use Illuminate\Validation\ValidationException;

class PaymentMethodsController extends Controller
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
        $payment_methods = PaymentMethod::forWorkspace($this->workspace->id);
        $payment_methods = $payment_methods->count();
        return view('payment_methods.list', ['payment_methods' => $payment_methods]);
    }
    /**
     * Create a payment method.
     *
     * This endpoint creates a payment method. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Payment Method Management
     *
     * @bodyParam title string required The title of the payment method. Example: Title

     *
     * @response 200 {
     * "error": false,
     * "message": "Payment Method created successfully.",
     * "id": 36,
     * "data": {
     *           'id' => '1',
     *           'title' => 'Title',
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
     *   "message": "An error occurred while creating the payment method."
     * }
     */
    public function store(Request $request)
    {
        try {

            $isApi = $request->get('isApi', false);
            // Validate the request data
            $formFields = $request->validate([
                'title' => 'required|unique:payment_methods,title', // Validate the title
            ]);
            $formFields['workspace_id'] = $this->workspace->id;
            $pm = PaymentMethod::create($formFields);
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Payment method created successfully',
                    [
                        'id' => $pm->id,
                        'type' => 'payment_method',
                        'data' => [
                            'id' => $pm->id,
                            'title' => $pm->title,
                            'created_at' => format_date($pm->created_at, true, to_format: 'Y-m-d'),
                            'updated_at' => format_date($pm->updated_at, true, to_format: 'Y-m-d'),
                        ]
                    ]
                );
            } else {
                return response()->json(['error' => false, 'message' => 'Payment method created successfully.', 'id' => $pm->id, 'type' => 'payment_method', 'pm' => $pm]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Payment method couldn\'t created',
                    [],
                    500,
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Payment method couldn\'t created.']);
            }
        }
    }

    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $payment_methods = PaymentMethod::forWorkspace($this->workspace->id);
        if ($search) {
            $payment_methods = $payment_methods->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $canEdit = checkPermission('edit_payment_methods');
        $canDelete = checkPermission('delete_payment_methods');

        $total = $payment_methods->count();
        $payment_methods = $payment_methods->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($payment_method) use ($canEdit, $canDelete) {
                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-pm" data-id="' . $payment_method->id . '" title="' . get_label('update', 'Update') . '" class="card-link">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $payment_method->id . '" data-type="payment-methods">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                $actions = $actions ?: '-';

                return [
                    'id' => $payment_method->id,
                    'title' => $payment_method->title . ($payment_method->id == 0 ? ' <span class="badge bg-success">' . get_label('default', 'Default') . '</span>' : ''),
                    'created_at' => format_date($payment_method->created_at, true),
                    'updated_at' => format_date($payment_method->updated_at, true),
                    'actions' => $actions,
                ];
            });

        return response()->json([
            "rows" => $payment_methods->items(),
            "total" => $total,
        ]);
    }

    public function get($id)
    {
        $pm = PaymentMethod::findOrFail($id);
        return response()->json(['pm' => $pm]);
    }
    /**
     * Update an existing payment method.
     *
     * This endpoint updates an existing payment method with the specified id. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Payment Method Management
     *
     * @bodyParam id string required The id of the payment method. Example : 1
     * @bodyParam title string required The title of the payment method. Example: Title

     *
     * @response 200 {
     * "error": false,
     * "message": "Payment Method created successfully.",
     * "id": 36,
     * "data": {
     *           'id' => '1',
     *           'title' => 'Title',
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
     *   "message": "An error occurred while creating the payment method."
     * }
     */
    public function update(Request $request)
    {
        try {
            $isApi = $request->get('isApi', false);
            $formFields = $request->validate([
            'id' => ['required'],
            'title' => 'required|unique:payment_methods,title,' . $request->id,
        ]);
        $pm = PaymentMethod::findOrFail($request->id);
            $pm->update($formFields);
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Payment method updated successfully',
                    [
                        'id' => $pm->id,
                        'type' => 'payment_method',
                        'data' => [
                            'id' => $pm->id,
                            'title' => $pm->title,
                            'created_at' => format_date($pm->created_at, true, to_format: 'Y-m-d'),
                            'updated_at' => format_date($pm->updated_at, true, to_format: 'Y-m-d')
                        ]
                    ]
                );
            } else {
                return response()->json(['error' => false, 'message' => 'Payment method updated successfully.', 'id' => $pm->id, 'type' => 'payment_method']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Payment method couldn\'t updated',
                    [],
                    500,
                );
            }
        }
    }
    /**
     * Remove the specified payment method.
     *
     * This endpoint deletes a payment method based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Payment Method Management
     *
     * @urlParam id int required The ID of the todo to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Payment Method deleted successfully.",
     *   "id": 1,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Payment Method not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the Payment Method."
     * }
     */
    public function destroy($id)
    {
        $pm = PaymentMethod::findOrFail($id);
        $pm->payslips()->update(['payment_method_id' => 0]);
        $pm->payments()->update(['payment_method_id' => 0]);
        $response = DeletionService::delete(PaymentMethod::class, $id, 'Payment method');
        $data = $response->getData();
        if ($data->error) {
            return response()->json(['error' => true, 'message' => $data->message]);
        } else {
            return response()->json(['error' => false, 'message' => 'Payment method deleted successfully.', 'id' => $id, 'title' => $pm->title, 'type' => 'payment_method']);
        }
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:payment_methods,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedPms = [];
        $deletedPmTitles = [];
        $defaultPaymentMethodIds = [];
        $nonDefaultIds = [];

        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $pm = PaymentMethod::findOrFail($id);
            if ($pm->id == 0) { // Assuming 0 is the ID for default payment method
                $defaultPaymentMethodIds[] = $id;
            } else {
                $pm->payslips()->update(['payment_method_id' => 0]);
                $pm->payments()->update(['payment_method_id' => 0]);
                $deletedPms[] = $id;
                $deletedPmTitles[] = $pm->title;
                DeletionService::delete(PaymentMethod::class, $id, 'Payment method');
                $nonDefaultIds[] = $id;
            }
        }

        if (count($defaultPaymentMethodIds) > 0) {
            if (count($ids) == 1) {
                return response()->json(['error' => true, 'message' => 'Default payment method cannot be deleted.']);
            } else {
                return response()->json(['error' => false, 'message' => 'Payment method(s) deleted successfully except default.', 'id' => $deletedPms, 'titles' => $deletedPmTitles, 'type' => 'payment_method']);
            }
        } else {
            return response()->json(['error' => false, 'message' => 'Payment method(s) deleted successfully.', 'id' => $deletedPms, 'titles' => $deletedPmTitles, 'type' => 'payment_method']);
        }
    }
    /**
     * List or search payments methods.
     *
     * This endpoint retrieves a list of payments methods based on various filters. The user must be authenticated to perform this action. The request allows searching and sorting by different parameters.
     *
     * @authenticated
     *
     * @group Payment Method Management
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
     *   "message": "Payment Method retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
            "id": 1,
            "title": "Payment Method Title",
            "created_at": "2025-04-16 09:41:57",
            "updated_at": "2025-04-16 09:41:57"
           }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Payment Method not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Payment Methods not found",
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
        $payment_methods = PaymentMethod::forWorkspace($this->workspace->id);
        if ($search) {
            $payment_methods = $payment_methods->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $canEdit = checkPermission('edit_payment_methods');
        $canDelete = checkPermission('delete_payment_methods');

        $total = $payment_methods->count();
        if ($id) {
            $payment_method = $payment_methods->find($id);
            if (!$payment_method) {
                return formatApiResponse(
                    false,
                    'Payment Method Not Found',
                    [
                        'total' => 0,
                        'data' => []
                    ],
                );
            }
            return formatApiResponse(
                false,
                'Payment Method Retrived Successfully',
                [
                    'id' => $payment_method->id,
                    'data' => [
                        'id' => $payment_method->id,
                        'title' => $payment_method->title,
                        'created_at' => format_date($payment_method->created_at, true, to_format: 'Y-m-d'),
                        'updated_at' => format_date($payment_method->updated_at, true, to_format: 'Y-m-d')
                    ]
                ]
            );
        } else {
            $payment_methods = $payment_methods->orderBy($sort, $order)->skip($offset)->take($limit)->get();
            if ($payment_methods->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Payment Methods Not Found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $data = $payment_methods->map(function ($payment_method) {
                return [
                    'id' => $payment_method->id,
                    'title' => $payment_method->title,
                    'created_at' => format_date($payment_method->created_at, true, to_format: 'Y-m-d'),
                    'updated_at' => format_date($payment_method->updated_at, true, to_format: 'Y-m-d'),
                ];
            });
            return formatApiResponse(
                false,
                'Payment Methods Retrived Successfully',
                [
                    'total' => $total,
                    'data' => $data,
                ]
            );
        }
    }
}
