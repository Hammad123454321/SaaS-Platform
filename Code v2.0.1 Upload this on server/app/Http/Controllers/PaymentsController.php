<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\Payment;
use App\Models\User;
use App\Models\Client;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Models\EstimatesInvoice;
use Exception;
use Illuminate\Validation\ValidationException;

class PaymentsController extends Controller
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
        $payments = $this->workspace->payments();
        $payments = $payments->count();
        $payment_methods = $this->workspace->payment_methods;
        return view('payments.list', ['payments' => $payments, 'payment_methods' => $payment_methods]);
    }
    public function expense_types(Request $request)
    {
        $expense_types = $this->workspace->expense_types();
        $expense_types = $expense_types->count();
        return view('expenses.expense_types', ['expense_types' => $expense_types]);
    }

    /**
     * Create a new payment.
     *
     * This endpoint creates a new payment with the specified user_id, invoice_id,payment_method_id,amount,payment_date,note. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Payment Management
     *
     * @bodyParam user_id string required The user_id of the payment. Example: 1
     * @bodyParam invoice_id string required The invoice_id of the payment. Example: 1
     * @bodyParam payment_method_id string required The payment_method_id of the payment methods. Example: 1
     * @bodyParam amount string required The amount of the amount. Example: 100
     * @bodyParam payment_date string required The payment_date of the payment. Example: 2024-08-07
     * @bodyParam note string required The note of the note. Example: Finish report

     *
     * @response 200 {
     * "error": false,
     * "message": "Payment created successfully.",
     * "id": 36,
     * "data": {
     *           'id' => '1',
     *           'user_id' => '1',
     *           'invoice_id' => '1',
     *           'payment_method_id' => '1',
     *           'amount' => '100.00',
     *           'payment_date' => '2023-10-01',
     *           'note' => 'Payment note',
     *           'created_at' => format_date($exp->created_at, true),
     *          }
     *
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "user_id": [
     *       "The user_id field is required."
     *     ],
     *     "invoice_id": [
     *       "The invoice_id field is required."
     *     ],
     *    "payment_method_id": [
     *      "The payment method id field is required."
     *    ],
     *    "amount": [
     *      "The amount field is required."
     *   ],
     *   "payment_date": [
     *     "The payment date field is required."
     *    ],
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the payment."
     * }
     */
    public function store(Request $request)
    {
        $isApi = $request->get('isApi', false);
        try {
            // Validate the request data
            $formFields = $request->validate([
                'user_id' => 'nullable',
                'invoice_id' => 'nullable|exists:estimates_invoices,id',
                'payment_method_id' => 'nullable',
                'amount' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'amount');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'payment_date' => 'required',
                'note' => 'nullable'
            ]);
            $payment_date = $request->input('payment_date');
            $formFields['payment_date'] = format_date($payment_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            $formFields['amount'] = str_replace(',', '', $request->input('amount'));
            $formFields['workspace_id'] = $this->workspace->id;
            $formFields['created_by'] = isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id;
            if (!empty($formFields['invoice_id'])) {
                // Check if the total paid amount exceeds the total amount from the estimates_invoices table
                $totalPaidAmount = Payment::where('invoice_id', $formFields['invoice_id'])->sum('amount');
                $totalInvoiceAmount = EstimatesInvoice::findOrFail($formFields['invoice_id'])->total;
                if ($totalPaidAmount + $formFields['amount'] > $totalInvoiceAmount) {
                    return response()->json(['error' => true, 'message' => 'Total paid amount exceeds the total invoice amount.']);
                }
            }


            $payment = Payment::create($formFields);
            if ($isApi) {
                return
                    formatApiResponse(
                        false,
                        'Payment created successfully.',
                        [
                            'id' => $payment->id,
                            'data' => [
                                'id' => $payment->id,
                                'user_id' => $payment->user_id,
                                'invoice_id' => $payment->invoice_id,
                                'payment_method_id' => $payment->payment_method_id,
                                'amount' => format_currency($payment->amount, false, false),
                                'payment_date' => format_date($payment->payment_date, to_format: 'Y-m-d'),
                                'note' => $payment->note,
                                'created_at' => format_date($payment->created_at, to_format: 'Y-m-d'),
                            ]
                        ]
                    );
            } else {
                return response()->json(['error' => false, 'message' => 'Payment created successfully.', 'id' => $payment->id]);
            }
        } catch (ValidationException $e) {
            return response()->json(formatApiValidationError($isApi, $e->errors()));
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'An error occurred while creating the payment ' . $e->getMessage() . ' ' . $e->getLine(),
                    [],
                    500
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Payment couldn\'t created.' .  $e->getMessage()]);
            }
        }
    }

    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $user_id = request('user_id') ?: [];
        $invoice_id = request('invoice_id') ?: [];
        $pm_id = request('pm_id') ?: [];
        $pm_date_from = (request('date_from')) ? request('date_from') : "";
        $pm_date_to = (request('date_to')) ? request('date_to') : "";
        $where = ['payments.workspace_id' => $this->workspace->id];

        $payments = Payment::select(
            'payments.*',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name'),
            'estimates_invoices.id as invoice',
            'payment_methods.title as payment_method'
        )
            ->leftJoin('users', 'payments.user_id', '=', 'users.id')
            ->leftJoin('estimates_invoices', 'payments.invoice_id', '=', 'estimates_invoices.id')
            ->leftJoin('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id');


        if (!isAdminOrHasAllDataAccess()) {
            $payments = $payments->where(function ($query) {
                $query->where('payments.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('payments.user_id', $this->user->id);
            });
        }
        if (!empty($invoice_id)) {
            $payments = $payments->whereIn('payments.invoice_id', $invoice_id);
        }

        if (!empty($user_id)) {
            $payments = $payments->whereIn('payments.user_id', $user_id);
        }

        if (!empty($pm_id)) {
            $payments = $payments->whereIn('payments.payment_method_id', $pm_id);
        }
        if ($pm_date_from && $pm_date_to) {
            $payments = $payments->whereBetween('payments.payment_date', [$pm_date_from, $pm_date_to]);
        }
        if ($search) {
            $payments = $payments->where(function ($query) use ($search) {
                $query->where('payments.id', 'like', '%' . $search . '%')
                    ->orWhere('payments.amount', 'like', '%' . $search . '%')
                    ->orWhere('payments.note', 'like', '%' . $search . '%');
            });
        }

        $payments->where($where);

        $total = $payments->count();
        $canEdit = checkPermission('edit_payments');
        $canDelete = checkPermission('delete_payments');
        $payments = $payments->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($payment) use ($canEdit, $canDelete) {
                $actions = '';
                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-payment" data-id="' . $payment->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }
                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $payment->id . '" data-type="payments">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }
                $actions = $actions ?: '-';
                return [
                    'id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'user' => formatUserHtml($payment->user),
                    'invoice_id' => $payment->invoice_id,
                    'invoice' => $payment->invoice ? '<a href="' . url("/estimates-invoices/view/{$payment->invoice}") . '">' . get_label('invoice_id_prefix', 'INVC-') . $payment->invoice_id . '</a>' : '-',
                    'payment_method_id' => $payment->payment_method_id,
                    'payment_method' => $payment->payment_method,
                    'amount' => format_currency($payment->amount),
                    'payment_date' => format_date($payment->payment_date),
                    'note' => $payment->note,
                    'created_by' => strpos($payment->created_by, 'u_') === 0 ? formatUserHtml(User::find(substr($payment->created_by, 2))) : formatClientHtml(Client::find(substr($payment->created_by, 2))),
                    'created_at' => format_date($payment->created_at, true),
                    'updated_at' => format_date($payment->updated_at, true),
                    'actions' => $actions,
                ];
            });


        return response()->json([
            "rows" => $payments->items(),
            "total" => $total,
        ]);
    }

    public function get($id)
    {
        $payment = Payment::with(['user', 'paymentMethod', 'invoice'])->findOrFail($id);
        $payment->amount = format_currency($payment->amount, false, false);
        return response()->json(['payment' => $payment]);
    }
    /**
     * Update an existing payment.
     *
     * This endpoint updates an existing payment with the specified user_id, invoice_id,payment_method_id,amount,payment_date,note. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Payment Management
     *
     * @bodyParam id string required The id of the payment. Example : 1
     * @bodyParam user_id string required The user_id of the payment. Example: 1
     * @bodyParam invoice_id string required The invoice_id of the payment. Example: 1
     * @bodyParam payment_method_id string required The payment_method_id of the payment methods. Example: 1
     * @bodyParam amount string required The amount of the amount. Example: 100
     * @bodyParam payment_date string required The payment_date of the payment. Example: 2024-08-07
     * @bodyParam note string required The note of the note. Example: Finish report

     *
     * @response 200 {
     * "error": false,
     * "message": "Payment created successfully.",
     * "id": 36,
     * "data": {
     *           'id' => '1',
     *           'user_id' => '1',
     *           'invoice_id' => '1',
     *           'payment_method_id' => '1',
     *           'amount' => '100.00',
     *           'payment_date' => '2023-10-01',
     *           'note' => 'Payment note',
     *           'created_at' => format_date($exp->created_at, true),
     *          }
     *
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "user_id": [
     *       "The user_id field is required."
     *     ],
     *     "invoice_id": [
     *       "The invoice_id field is required."
     *     ],
     *    "payment_method_id": [
     *      "The payment method id field is required."
     *    ],
     *    "amount": [
     *      "The amount field is required."
     *   ],
     *   "payment_date": [
     *     "The payment date field is required."
     *    ],
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the payment."
     * }
     */
    public function update(Request $request)
    {
        $isApi = $request->get('isApi', false);
        try {
            // Validate the request data
            $formFields = $request->validate([
                'id' => 'required',
                'user_id' => 'nullable',
                'invoice_id' => 'nullable|exists:estimates_invoices,id',
                'payment_method_id' => 'nullable',
                'amount' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'amount');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'payment_date' => 'required',
                'note' => 'nullable'
            ]);
            $payment_date = $request->input('payment_date');
            $formFields['payment_date'] = format_date($payment_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            $formFields['amount'] = str_replace(',', '', $request->input('amount'));
            if (!empty($formFields['invoice_id'])) {
                // Check if the total paid amount exceeds the total amount from the estimates_invoices table
                $totalPaidAmount = Payment::where('invoice_id', $formFields['invoice_id'])
                    ->where('id', '!=', $formFields['id']) // Exclude the current payment being updated
                    ->sum('amount');
                $totalInvoiceAmount = EstimatesInvoice::findOrFail($formFields['invoice_id'])->total;

                if ($totalPaidAmount + $formFields['amount'] > $totalInvoiceAmount) {
                    return response()->json(['error' => true, 'message' => 'Total paid amount exceeds the total invoice amount.']);
                }
            }

            $payment = Payment::findOrFail($request->id);
            $payment->update($formFields);
            if ($isApi) {
                return
                    formatApiResponse(
                        false,
                        'Payment Updated successfully.',
                        [
                            'id' => $payment->id,
                            'data' => [
                                'id' => $payment->id,
                                'user_id' => $payment->user_id,
                                'invoice_id' => $payment->invoice_id,
                                'payment_method_id' => $payment->payment_method_id,
                                'amount' => format_currency($payment->amount, false, false),
                                'payment_date' => format_date($payment->payment_date, to_format: 'Y-m-d'),
                                'note' => $payment->note,
                                'created_at' => format_date($payment->created_at, to_format: 'Y-m-d'),
                                'updated_at' => format_date($payment->updated_at, to_format: 'Y-m-d'),
                            ]
                        ]
                    );
            } else {
                return response()->json(['error' => false, 'message' => 'Payment updated successfully.', 'id' => $payment->id]);
            }
        } catch (ValidationException $e) {
            return response()->json(formatApiValidationError($isApi, $e->errors()));
        } catch (Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'An error occurred while updating the payment ' . $e->getMessage() . ' ' . $e->getLine(),
                    [],
                    500
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Payment couldn\'t updated.' .  $e->getMessage()]);
            }
        }
    }
    /**
     * Remove the specified payment.
     *
     * This endpoint deletes a payment based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Payment Management
     *
     * @urlParam id int required The ID of the todo to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Payment deleted successfully.",
     *   "id": 1,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Payment not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the Payment."
     * }
     */
    public function destroy($id)
    {
        $response = DeletionService::delete(Payment::class, $id, 'Payment');
        return $response;
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:payments,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        $label = get_label('payment_id', 'Payment ID');
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $deletedIds[] = $id;
            $deletedTitles[] = $label . ' ' . $id;
            DeletionService::delete(Payment::class, $id, 'Payment');
        }

        return response()->json(['error' => false, 'message' => 'Payment(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
    /**
     * List or search payments.
     *
     * This endpoint retrieves a list of payments based on various filters. The user must be authenticated to perform this action. The request allows searching and sorting by different parameters.
     *
     * @authenticated
     *
     * @group Payment Management
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
     *   "message": "Payment retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
            "id": 1,
            "user_id": 1,
            "user": {
                "id": 1,
                "first_name": "Admin",
                "last_name": "User",
                "email": "admin@gmail.com",
                "photo": "https://dev-taskify.taskhub.company/storage/photos/C03PJmIQInts2j3O2on99Nilu45UcChrepcsIFxO.jpg"
            },
            "invoice_id": null,
            "invoice": "-",
            "payment_method_id": 1,
            "payment_method": "some",
            "amount": "₹ 100.00",
            "payment_date": "2025-04-16",
            "note": "123",
            "created_by": "Admin User",
            "created_at": "2025-04-16 09:41:57",
            "updated_at": "2025-04-16 09:41:57"
        }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Payment not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Payments not found",
     *   "total": 0,
     *   "data": []
     * }
     */
    public function apiList(Request $request)
    {
        $id = request('id');
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $user_id = request('user_id') ?: [];
        $invoice_id = request('invoice_id') ?: [];
        $pm_id = request('pm_id') ?: [];
        $pm_date_from = (request('date_from')) ? request('date_from') : "";
        $pm_date_to = (request('date_to')) ? request('date_to') : "";
        $where = ['payments.workspace_id' => $this->workspace->id];
        $limit = request('limit', 10);
        $offset = request('offset', 0);
        $payments = Payment::select(
            'payments.*',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name'),
            'estimates_invoices.id as invoice',
            'payment_methods.title as payment_method'
        )
            ->leftJoin('users', 'payments.user_id', '=', 'users.id')
            ->leftJoin('estimates_invoices', 'payments.invoice_id', '=', 'estimates_invoices.id')
            ->leftJoin('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id');


        if (!isAdminOrHasAllDataAccess()) {
            $payments = $payments->where(function ($query) {
                $query->where('payments.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('payments.user_id', $this->user->id);
            });
        }
        if (!empty($invoice_id)) {
            $payments = $payments->whereIn('payments.invoice_id', $invoice_id);
        }

        if (!empty($user_id)) {
            $payments = $payments->whereIn('payments.user_id', $user_id);
        }

        if (!empty($pm_id)) {
            $payments = $payments->whereIn('payments.payment_method_id', $pm_id);
        }
        if ($pm_date_from && $pm_date_to) {
            $payments = $payments->whereBetween('payments.payment_date', [$pm_date_from, $pm_date_to]);
        }
        if ($search) {
            $payments = $payments->where(function ($query) use ($search) {
                $query->where('payments.id', 'like', '%' . $search . '%')
                    ->orWhere('payments.amount', 'like', '%' . $search . '%')
                    ->orWhere('payments.note', 'like', '%' . $search . '%');
            });
        }

        $payments->where($where);

        $total = $payments->count();
        $canEdit = checkPermission('edit_payments');
        $canDelete = checkPermission('delete_payments');
        if ($id) {
            $payment = $payments->find($id);

            if (!$payment) {
                return formatApiResponse(
                    false,
                    'Payment not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }

            $created_by = strpos($payment->created_by, 'u_') === 0 ? User::find(substr($payment->created_by, 2)) : Client::find(substr($payment->created_by, 2));
            return formatApiResponse(
                false,
                'Payment retrieved successfully',
                [
                    'total' => 1,
                    'data' => [
                        [
                            'id' => $payment->id,
                            'user_id' => $payment->user_id,
                            'user' =>  [
                                'id' => $payment->user ?  $payment->user->id : null,
                                'first_name' => $payment->user ?  $payment->user->first_name : null,
                                'last_name' => $payment->user ?  $payment->user->last_name : null,
                                'email' => $payment->user ?  $payment->user->email : null,
                                'photo' => $payment->user && $payment->user->photo ? asset('storage/' . $payment->user->photo) : asset('storage/photos/no-image.jpg')
                            ],
                            'invoice_id' => $payment->invoice_id,
                            'invoice' => $payment->invoice ? '<a href="' . url("/estimates-invoices/view/{$payment->invoice}") . '">' . get_label('invoice_id_prefix', 'INVC-') . $payment->invoice_id . '</a>' : '-',
                            'payment_method_id' => $payment->payment_method_id,
                            'payment_method' => $payment->payment_method,
                            'amount' => format_currency($payment->amount, false, false),
                            'payment_date' => format_date($payment->payment_date, to_format: 'Y-m-d'),
                            'note' => $payment->note,
                            'created_by' => ucwords($created_by->first_name . ' ' . $created_by->last_name),
                            'created_at' => format_date($payment->created_at, true, to_format: 'Y-m-d'),
                            'updated_at' => format_date($payment->updated_at, true, to_format: 'Y-m-d'),

                        ]
                    ]
                ]
            );
        } else {

            $payments = $payments->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get();
            if ($payments->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Payments not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $data = $payments->map(function ($payment) {
                $created_by = strpos($payment->created_by, 'u_') === 0 ? User::find(substr($payment->created_by, 2)) : Client::find(substr($payment->created_by, 2));
                return [
                    'id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'user' =>  [
                        'id' => $payment->user ?  $payment->user->id : null,
                        'first_name' => $payment->user ?  $payment->user->first_name : null,
                        'last_name' => $payment->user ?  $payment->user->last_name : null,
                        'email' => $payment->user ?  $payment->user->email : null,
                        'photo' => $payment->user && $payment->user->photo ? asset('storage/' . $payment->user->photo) : asset('storage/photos/no-image.jpg')
                    ],
                    'invoice_id' => $payment->invoice_id,
                    'invoice' => $payment->invoice ? '<a href="' . url("/estimates-invoices/view/{$payment->invoice}") . '">' . get_label('invoice_id_prefix', 'INVC-') . $payment->invoice_id . '</a>' : '-',
                    'payment_method_id' => $payment->payment_method_id,
                    'payment_method' => $payment->payment_method,
                    'amount' => format_currency($payment->amount, false, false),
                    'payment_date' => format_date($payment->payment_date, to_format: 'Y-m-d'),
                    'note' => $payment->note,
                    'created_by' => ucwords($created_by->first_name . ' ' . $created_by->last_name),
                    'created_at' => format_date($payment->created_at, true, to_format: 'Y-m-d'),
                    'updated_at' => format_date($payment->updated_at, true, to_format: 'Y-m-d'),
                ];
            });
            return formatApiResponse(
                false,
                'Payments retrieved successfully',
                [
                    'total' => $total,
                    'data' => $data
                ]
            );
        }
    }
}
