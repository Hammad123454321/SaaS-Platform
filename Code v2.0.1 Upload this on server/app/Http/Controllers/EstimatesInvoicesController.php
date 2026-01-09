<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Client;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\EstimatesInvoice;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use LaravelDaily\Invoices\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use LaravelDaily\Invoices\Classes\Party;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LaravelDaily\Invoices\Classes\InvoiceItem;




class EstimatesInvoicesController extends Controller
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
        $estimates_invoices = isAdminOrHasAllDataAccess() ? $this->workspace->estimates_invoices() : $this->user->estimates_invoices();
        $estimates_invoices = $estimates_invoices->count();
        return view('estimates-invoices.list', ['estimates_invoices' => $estimates_invoices]);
    }

    public function create(Request $request)
    {
        $units = $this->workspace->units;
        $taxes = $this->workspace->taxes;
        return view('estimates-invoices.create', ['units' => $units, 'taxes' => $taxes]);
    }

    /**
     * Store a new invoice or estimate.
     *
     * This endpoint allows the creation of a new invoice or estimate by providing details such as client, dates, items, amount, and tax info.
     * The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Estimate Invoice Management
     *
     * @bodyParam type string required Type of the document. Accepted values: estimate, invoice. Example: invoice
     * @bodyParam client_id integer required The ID of the client. Example: 1
     * @bodyParam name string required The name/title of the estimate or invoice. Example: Website Development
     * @bodyParam address string optional The address for the client. Example: 123 Main St.
     * @bodyParam city string optional The city of the client. Example: New York
     * @bodyParam state string optional The state of the client. Example: NY
     * @bodyParam country string optional The country of the client. Example: USA
     * @bodyParam zip_code string optional The postal code. Example: 10001
     * @bodyParam phone string optional The phone number. Example: +1234567890
     * @bodyParam note string optional General note. Example: Handle with urgency
     * @bodyParam personal_note string optional Internal note. Example: Discussed pricing
     * @bodyParam from_date date required Start date. Format: Y-m-d. Example: 2024-01-01
     * @bodyParam to_date date required End date. Format: Y-m-d. Example: 2024-01-31
     * @bodyParam status string optional Status of the document. Accepted values for invoice: not_specified, partially_paid, fully_paid, draft, cancelled, due. For estimate: not_specified, sent, accepted, draft, declined, expired. Example: draft
     * @bodyParam total string required Sub total amount. Format: currency. Example: 1000.00
     * @bodyParam tax_amount string optional Tax amount. Format: currency. Example: 100.00
     * @bodyParam final_total string required Final total after taxes. Format: currency. Example: 1100.00
     * @bodyParam item_ids array optional IDs of selected items. Example: [1,2]
     * @bodyParam item array required Item IDs. Example: [1,2]
     * @bodyParam quantity array required Quantity per item. Example: [2,1]
     * @bodyParam unit array optional Unit IDs for each item. Example: [1,2]
     * @bodyParam rate array required Rate per item. Example: ["500.00", "1000.00"]
     * @bodyParam tax array optional Tax ID per item. Example: [1,2]
     * @bodyParam amount array required Total amount per item. Example: ["1000.00", "1000.00"]
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Estimate/Invoice created successfully.",
     *   "data": {
     *     "id": 1,
     *     "type": "invoice",
     *     "client_id": 1,
     *     "from_date": "2024-01-01",
     *     "to_date": "2024-01-31",
     *     "total": "1000.00",
     *     "tax_amount": "100.00",
     *     "final_total": "1100.00",
     *     "status": "draft",
     *     "created_at": "2024-01-01T10:00:00.000000Z",
     *     "items": [...]
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "type": [
     *       "The type field is required."
     *     ],
     *     "client_id": [
     *       "The client field is required."
     *     ],
     *     "from_date": [
     *       "The from date field is required and must be before the to date."
     *     ],
     *     "to_date": [
     *       "The to date field is required and must be after the from date."
     *     ],
     *     "final_total": [
     *       "The final total field is required and must be a valid currency format."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the estimate/invoice."
     * }
     */

    public function store(Request $request)
    {
        try {
            $isApi = $request->get('isApi', false);

            $rules = [
                'type' => 'required|in:estimate,invoice',
                'client_id' => 'required|exists:clients,id',
                'name' => 'required',
                'address' => 'nullable',
                'city' => 'nullable',
                'state' => 'nullable',
                'country' => 'nullable',
                'zip_code' => 'nullable',
                'phone' => 'nullable',
                'note' => 'nullable',
                'from_date' => [
                    'required',
                    function ($attribute, $value, $fail) use ($isApi) {
                        $endDate = request()->input('from_date');
                        $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null);
                        if (!empty($errors['from_date'])) {
                            foreach ($errors['from_date'] as $error) {
                                $fail($error);
                            }
                        }
                    },
                ],
                'to_date' => [
                    'required',
                    function ($attribute, $value, $fail) use ($isApi) {
                        $endDate = request()->input('to_date');
                        $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null);
                        if (!empty($errors['to_date'])) {
                            foreach ($errors['to_date'] as $error) {
                                $fail($error);
                            }
                        }
                    },
                ],
                'personal_note' => 'nullable',
                'total' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'sub total');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'tax_amount' => [
                    'nullable',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'tax');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'final_total' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'final total');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
            ];

            $messages = [
                'client_id.required' => 'The client field is required.'
            ];

            // Handle 'status' field before validation
            $status = $request->input('status', ''); // Get status from request, default to empty string if not present
            if ($status == '') {
                // If status is empty, set it to 'na'
                $status = 'not_specified';
            }

            // Add conditional validation for the 'status' field based on the 'type'
            if ($request->type === 'invoice') {
                // If the type is 'invoice', restrict status to specific values
                $rules['status'] = 'in:not_specified,partially_paid,fully_paid,draft,cancelled,due';
            } elseif ($request->type === 'estimate') {
                // If the type is 'estimate', restrict status to specific values
                $rules['status'] = 'in:not_specified,sent,accepted,draft,declined,expired';
            }

            // Assign modified 'status' back to request
            $request->merge(['status' => $status]);

            $formFields = $request->validate($rules, $messages);

            // Define custom attribute names
            // Define custom attribute names
            $customAttributes = [
                'rate.*' => 'rate',
                'amount.*' => 'amount',
                'quantity.*' => 'quantity',
            ];

            $messages = [
                'rate.*.required' => 'The rate field is required for each item.',
                'amount.*.required' => 'The amount field is required for each item.',
                'quantity.*.required' => 'The quantity field is required for each item.'
            ];

            $validator = Validator::make($request->all(), [
                'quantity.*' => 'required|numeric',
                'unit.*' => 'nullable',
                'rate.*' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        // Debugging: Check the value
                        $error = validate_currency_format($value, 'rate');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'tax.*' => 'nullable',
                'amount.*' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        // Debugging: Check the value
                        $error = validate_currency_format($value, 'amount');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ]
            ], $messages, $customAttributes);

            $item_ids = $request->input('item_ids') ?? [];
            if (!empty($item_ids)) {
                $type = $request->input('type');
                $from_date = $request->input('from_date');
                $to_date = $request->input('to_date');
                $from_date = Carbon::parse($from_date);
                $to_date = Carbon::parse($to_date);

                $formFields['from_date'] =  format_date($from_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
                $formFields['to_date'] =  format_date($to_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
                $formFields['total'] = str_replace(',', '', $request->input('total'));
                $taxInput = str_replace(',', '', $request->input('tax_amount'));
                $formFields['tax_amount'] = $taxInput !== '' ? $taxInput : 0; // or null if your DB allows

                $formFields['final_total'] = str_replace(',', '', $request->input('final_total'));

                $formFields['workspace_id'] = $this->workspace->id;
                $formFields['created_by'] = isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id;

                if ($res = EstimatesInvoice::create($formFields)) {
                    foreach ($request->input('item') as $key => $item_id) {
                        $quantity = (int)$request->input('quantity')[$key];
                        $unit_id = (int)$request->input('unit')[$key];
                        $rate = (int)str_replace(',', '', $request->input('rate')[$key]);
                        $tax_id = (int) $request->input('tax')[$key];
                        $amount = (int) str_replace(',', '', $request->input('amount')[$key]);

                        // Attach the item with its respective values
                        $res->items()->attach($item_id, ['qty' => $quantity, 'unit_id' => $unit_id, 'rate' => $rate, 'tax_id' => $tax_id, 'amount' => $amount]);
                    }

                    if ($isApi) {

                        return formatApiResponse(
                            false,
                            ucfirst($type) . 'Created Successfully.',
                            [
                                'id' => $res->id,
                                'type' => $res->type,
                                'data' => formatEstimateInvoice($res)
                            ]
                        );
                    } else {

                        Session::flash('message', ucfirst($type) . ' created successfully.');
                        return response()->json(['error' => false, 'id' => $res->id, 'type' => $type]);
                    }
                } else {
                    if ($isApi) {
                        return formatApiResponse(
                            true,
                            ucfirst($type) . 'couldn\'t created.'
                        );
                    } else {
                        return response()->json(['error' => true, 'message' => ucfirst($type) . ' couldn\'t created.']);
                    }
                }
            } else {

                return response()->json(['error' => true, 'message' => 'Please add at least one item.']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {

            if ($isApi) {
                return formatApiResponse(
                    true,
                    ucfirst($request->type) . ' couldn\'t created. ',
                    [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile()

                    ],
                    500
                );
            } else {
                return response()->json(['error' => true, 'message' => ucfirst($request->type) . ' couldn\'t created.']);
            }
        }
    }

    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status = (request('status')) ? request('status') : "";
        $types = request('types', []);
        $client_ids = request('client_ids', []);
        $created_by_user_ids = request('created_by_user_ids', []);
        $created_by_client_ids = request('created_by_client_ids', []);
        $date_between_from = request('date_between_from') ?: "";
        $date_between_to = request('date_between_to') ?: "";
        $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
        $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
        $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
        $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";
        $where = ['estimates_invoices.workspace_id' => $this->workspace->id];

        if ($status != '') {
            $where['estimates_invoices.status'] = $status;
        }

        $estimates_invoices = EstimatesInvoice::select(
            'estimates_invoices.*',
            DB::raw('CONCAT(clients.first_name, " ", clients.last_name) AS client_name')
        )
            ->leftJoin('clients', 'estimates_invoices.client_id', '=', 'clients.id');


        if (!isAdminOrHasAllDataAccess()) {
            $estimates_invoices = $estimates_invoices->where(function ($query) {
                $query->where('estimates_invoices.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('estimates_invoices.client_id', $this->user->id);
            });
        }

        if (!empty($types)) {
            $estimates_invoices->whereIn('type', $types);
        }

        if (!empty($client_ids)) {
            $estimates_invoices->whereIn('client_id', $client_ids);
        }

        if (!empty($created_by_user_ids)) {
            $estimates_invoices->whereIn('estimates_invoices.created_by', array_map(function ($id) {
                return 'u_' . $id;
            }, $created_by_user_ids));
        }

        if (!empty($created_by_client_ids)) {
            $estimates_invoices->whereIn('estimates_invoices.created_by', array_map(function ($id) {
                return 'c_' . $id;
            }, $created_by_client_ids));
        }

        if ($date_between_from && $date_between_to) {
            $estimates_invoices = $estimates_invoices->where('estimates_invoices.from_date', '>=', $date_between_from)
                ->where('estimates_invoices.to_date', '<=', $date_between_to);
        }

        if ($start_date_from && $start_date_to) {
            $estimates_invoices = $estimates_invoices->whereBetween('estimates_invoices.from_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $estimates_invoices  = $estimates_invoices->whereBetween('estimates_invoices.to_date', [$end_date_from, $end_date_to]);
        }
        if ($search) {
            $estimates_invoices = $estimates_invoices->where(function ($query) use ($search) {
                $query->where('estimates_invoices.id', 'like', '%' . $search . '%')
                    ->orWhere('estimates_invoices.name', 'like', '%' . $search . '%')
                    ->orWhere(DB::raw('CONCAT("' . get_label('invoice_id_prefix', 'INVC-') . '", estimates_invoices.id)'), 'like', '%' . $search . '%')
                    ->orWhere(DB::raw('CONCAT("' . get_label('estimate_id_prefix', 'ESTMT-') . '", estimates_invoices.id)'), 'like', '%' . $search . '%');
            });
        }

        $estimates_invoices->where($where);
        $total = $estimates_invoices->count();

        $canCreate = checkPermission('create_estimates_invoices');
        $canEdit = checkPermission('edit_estimates_invoices');
        $canDelete = checkPermission('delete_estimates_invoices');

        $estimates_invoices = $estimates_invoices->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($estimates_invoice) use ($canEdit, $canDelete, $canCreate) {

                $statusBadge = '';

                if ($estimates_invoice->status == 'sent') {
                    $statusBadge = '<span class="badge bg-primary">' . get_label('sent', 'Sent') . '</span>';
                } elseif ($estimates_invoice->status == 'accepted') {
                    $statusBadge = '<span class="badge bg-success">' . get_label('accepted', 'Accepted') . '</span>';
                } elseif ($estimates_invoice->status == 'partially_paid') {
                    $statusBadge = '<span class="badge bg-warning">' . get_label('partially_paid', 'Partially paid') . '</span>';
                } elseif ($estimates_invoice->status == 'fully_paid') {
                    $statusBadge = '<span class="badge bg-success">' . get_label('fully_paid', 'Fully paid') . '</span>';
                } elseif ($estimates_invoice->status == 'draft') {
                    $statusBadge = '<span class="badge bg-secondary">' . get_label('draft', 'Draft') . '</span>';
                } elseif ($estimates_invoice->status == 'declined') {
                    $statusBadge = '<span class="badge bg-danger">' . get_label('declined', 'Declined') . '</span>';
                } elseif ($estimates_invoice->status == 'cancelled') {
                    $statusBadge = '<span class="badge bg-danger">' . get_label('cancelled', 'Cancelled') . '</span>';
                } elseif ($estimates_invoice->status == 'expired') {
                $statusBadge = '<span class="badge bg-warning">' . get_label('expired', 'Expired') . '</span>';
                } elseif ($estimates_invoice->status == 'not_specified') {
                    $statusBadge = '<span class="badge bg-secondary">' . get_label('not_specified', 'Not specified') . '</span>';
                } elseif ($estimates_invoice->status == 'due') {
                    $statusBadge = '<span class="badge bg-danger">' . get_label('due', 'Due') . '</span>';
                }

                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="' . url("/estimates-invoices/edit/{$estimates_invoice->id}") . '" title="' . get_label('update', 'Update') . '"><i class="bx bx-edit mx-1"></i></a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $estimates_invoice->id . '" data-type="estimates-invoices" data-reload="true">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                if ($canCreate) {
                    $actions .= '<a href="javascript:void(0);" class="duplicate" data-id="' . $estimates_invoice->id . '" data-type="estimates-invoices" title="' . get_label('duplicate', 'Duplicate') . '">' .
                        '<i class="bx bx-copy text-warning mx-2"></i>' .
                        '</a>';
                }

                $actions .= '<a href="' . url("/estimates-invoices/pdf/{$estimates_invoice->id}") . '" title="PDF">' .
                    '<i class="bx bxs-file-pdf text-secondary mx-2"></i>' .
                    '</a>';

                return [
                    'id' => $estimates_invoice->id,
                    'type' => ucfirst($estimates_invoice->type),
                    'client' => formatClientHtml($estimates_invoice->client),
                    'total' => format_currency($estimates_invoice->total),
                    'tax_amount' => format_currency($estimates_invoice->tax_amount),
                    'final_total' => format_currency($estimates_invoice->final_total),
                    'from_date' => format_date($estimates_invoice->from_date),
                    'to_date' => format_date($estimates_invoice->to_date),
                    'status' => $statusBadge,
                    'created_by' => strpos($estimates_invoice->created_by, 'u_') === 0 ? formatUserHtml(User::find(substr($estimates_invoice->created_by, 2))) : formatClientHtml(Client::find(substr($estimates_invoice->created_by, 2))),
                    'created_at' => format_date($estimates_invoice->created_at, true),
                    'updated_at' => format_date($estimates_invoice->updated_at, true),
                    'actions' => $actions
                ];
            });


        return response()->json([
            "rows" => $estimates_invoices->items(),
            "total" => $total,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $estimate_invoice = EstimatesInvoice::findOrFail($id);
        $units = $this->workspace->units;
        $taxes = $this->workspace->taxes;
        return view('estimates-invoices.update', ['estimate_invoice' => $estimate_invoice, 'units' => $units, 'taxes' => $taxes]);
    }

    /**
     * Update an existing estimate or invoice.
     *
     * This endpoint updates an existing estimate or invoice record with provided details including type, client, date range, financials, items, and notes.
     * The request must be authenticated.
     *
     * @authenticated
     *
     * @group Estimate Invoice Management
     *
     * @bodyParam id int required The ID of the estimate or invoice to update. Example: 5
     * @bodyParam type string required The type of the document. Accepted values: estimate, invoice. Example: invoice
     * @bodyParam client_id int required The client ID associated with this document. Example: 2
     * @bodyParam name string required The name or title of the document. Example: Invoice April 2024
     * @bodyParam address string The billing address. Example: 123 Main St
     * @bodyParam city string The city name. Example: New York
     * @bodyParam state string The state name. Example: NY
     * @bodyParam country string The country name. Example: USA
     * @bodyParam zip_code string The postal/zip code. Example: 10001
     * @bodyParam phone string The phone number. Example: +1 1234567890
     * @bodyParam note string Additional notes. Example: Payment due within 15 days
     * @bodyParam personal_note string A personal/internal note. Example: Send reminder after 10 days
     * @bodyParam from_date date required The start date for the invoice or estimate. Example: 2024-04-01
     * @bodyParam to_date date required The end date for the invoice or estimate. Example: 2024-04-30
     * @bodyParam status string The status of the document depending on its type. For invoice: not_specified, partially_paid, fully_paid, draft, cancelled, due. For estimate: not_specified, sent, accepted, draft, declined, expired. Example: due
     * @bodyParam total string required The total before tax. Example: 1000.00
     * @bodyParam tax_amount string The tax amount. Example: 100.00
     * @bodyParam final_total string required The final total after tax. Example: 1100.00
     * @bodyParam item array List of item IDs. Example: [1, 2, 3]
     * @bodyParam quantity.* numeric required Quantity for each item. Example: [2, 1, 3]
     * @bodyParam unit.* int Unit ID for each item. Example: [1, 1, 2]
     * @bodyParam rate.* string required Rate for each item. Example: ["500.00", "200.00", "100.00"]
     * @bodyParam tax.* int Tax ID for each item. Example: [1, null, 2]
     * @bodyParam amount.* string required Calculated amount for each item. Example: ["1000.00", "200.00", "300.00"]
     *
     * @response 200 {
     *  "error": false,
     *  "message": "Estimate/Invoice updated successfully.",
     *  "data": {
     *      "id": 5,
     *      "type": "invoice",
     *      "client_id": 2,
     *      "from_date": "2024-04-01",
     *      "to_date": "2024-04-30",
     *      "total": "1000.00",
     *      "tax_amount": "100.00",
     *      "final_total": "1100.00"
     *  }
     * }
     *
     * @response 422 {
     *  "error": true,
     *  "message": "Validation errors occurred.",
     *  "errors": {
     *      "client_id": ["The client field is required."],
     *      "from_date": ["The from date must be a valid date."],
     *      "to_date": ["The to date must be a valid date."],
     *      "total": ["The total format is invalid."],
     *      "final_total": ["The final total format is invalid."]
     *  }
     * }
     *
     * @response 500 {
     *  "error": true,
     *  "message": "An error occurred while updating the estimate or invoice."
     * }
     */

    public function update(Request $request)
    {
        try {
            $isApi = $request->get('isApi', false);
            $rules = [
                'id' => 'required|exists:estimates_invoices,id',
                'type' => 'required|in:estimate,invoice',
                'client_id' => 'required',
                'name' => 'required',
                'address' => 'nullable',
                'city' => 'nullable',
                'state' => 'nullable',
                'country' => 'nullable',
                'zip_code' => 'nullable',
                'phone' => 'nullable',
                'note' => 'nullable',
                'from_date' => [
                    'required',
                    function ($attribute, $value, $fail) use ($isApi) {
                        $endDate = request()->input('from_date');
                        $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null);
                        if (!empty($errors['from_date'])) {
                            foreach ($errors['from_date'] as $error) {
                                $fail($error);
                            }
                        }
                    },
                ],
                'to_date' => [
                    'required',
                    function ($attribute, $value, $fail) use ($isApi) {
                        $endDate = request()->input('to_date');
                        $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null);
                        if (!empty($errors['to_date'])) {
                            foreach ($errors['to_date'] as $error) {
                                $fail($error);
                            }
                        }
                    },
                ],
                'personal_note' => 'nullable',
                'total' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'sub total');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'tax_amount' => [
                    'nullable',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'tax');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'final_total' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'final total');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ]
            ];

            $messages = [
                'client_id.required' => 'The client field is required.'
            ];

            // Handle 'status' field before validation
            $status = $request->input('status', ''); // Get status from request, default to empty string if not present
            if ($status == '') {
                // If status is empty, set it to 'na'
                $status = 'not_specified';
            }

            // Add conditional validation for the 'status' field based on the 'type'
            if ($request->type === 'invoice') {
                // If the type is 'invoice', restrict status to specific values
                $rules['status'] = 'in:not_specified,partially_paid,fully_paid,draft,cancelled,due';
            } elseif ($request->type === 'estimate') {
                // If the type is 'estimate', restrict status to specific values
                $rules['status'] = 'in:not_specified,sent,accepted,draft,declined,expired';
            }

            // Assign modified 'status' back to request
            $request->merge(['status' => $status]);

            $formFields = $request->validate($rules, $messages);

            // Define custom attribute names
            $customAttributes = [
                'rate.*' => 'rate',
                'amount.*' => 'amount',
                'quantity.*' => 'quantity',
            ];

            $messages = [
                'rate.*.required' => 'The rate field is required for each item.',
                'amount.*.required' => 'The amount field is required for each item.',
                'quantity.*.required' => 'The quantity field is required for each item.'
            ];

            $validator = Validator::make($request->all(), [
                'quantity.*' => 'required|numeric',
                'unit.*' => 'nullable',
                'rate.*' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        // Debugging: Check the value
                        $error = validate_currency_format($value, 'rate');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'tax.*' => 'nullable',
                'amount.*' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        // Debugging: Check the value
                        $error = validate_currency_format($value, 'amount');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ]
            ], $messages, $customAttributes);

            // Check if validation fails
            if ($validator->fails()) {
                // Return validation error response
                return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
            }


            $estimate_invoice = EstimatesInvoice::findOrFail($request->input('id'));
            $itemPivotData = [];
            $items = $request->input('item') ?? [];
            if (!empty($items)) {
                $type = $request->input('type');
                $from_date = $request->input('from_date');
                $to_date = $request->input('to_date');

                // dd('here');
                $formFields['from_date'] =  format_date($from_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
                $formFields['to_date'] = format_date($to_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
                $formFields['total'] = str_replace(',', '', $request->input('total'));
                $formFields['tax_amount'] = str_replace(',', '', $request->input('tax_amount'));
                $formFields['final_total'] = str_replace(',', '', $request->input('final_total'));

                // dd($items);
                if ($estimate_invoice->update($formFields)) {
                    for ($i = 0; $i < count($items); $i++) {
                        $itemPivotData[$items[$i]] = [
                            'qty' => $request->input('quantity')[$i],
                            'unit_id' => $request->input('unit')[$i],
                            'rate' => str_replace(',', '', $request->input('rate')[$i]),
                            'tax_id' => $request->input('tax')[$i],
                            'amount' => str_replace(',', '', $request->input('amount')[$i]),
                        ];
                    }
                    $estimate_invoice->items()->sync($itemPivotData);
                    if ($isApi) {
                        return formatApiResponse(
                            false,
                            ucfirst($type) . 'updated successfully',
                            [
                                'id' => $estimate_invoice->id,
                                'type' => $estimate_invoice->type,
                                'data' => formatEstimateInvoice($estimate_invoice),
                            ]
                        );
                    } else {

                        Session::flash('message', ucfirst($type) . ' updated successfully.');
                        return response()->json(['error' => false, 'id' => $request->input('id'), 'type' => $type]);
                    }
                } else {
                    if ($isApi) {
                        return formatApiResponse(
                            false,
                            ucfirst($type) . 'couldn\'t updated',
                        );
                    } else {
                        return response()->json(['error' => true, 'message' => ucfirst($type) . ' couldn\'t updated.']);
                    }
                }
            } else {
                return response()->json(['error' => true, 'message' => 'Please add at least one item.']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {

            if ($isApi) {
                return formatApiResponse(
                    true,
                    ucfirst($type) . 'couldn\'t updated',
                    [
                        'data' => [
                            'message' => $e->getMessage(),
                            'line' => $e->getLine()
                        ]
                    ]
                );
            } else {
                return response()->json(['error' => true, 'message' => ucfirst($type) . ' couldn\'t updated.']);
            }
        }
    }

    public function view(Request $request, $id)
    {
        $estimate_invoice = EstimatesInvoice::findOrFail($id);


        // The ID corresponds to a user
        $creator = User::find(substr($estimate_invoice->created_by, 2)); // Remove the 'u_' prefix
        if ($creator !== null) {
            $estimate_invoice->creator = $creator->first_name . ' ' . $creator->last_name;
        } else {
            $estimate_invoice->creator = ' -';
        }
        $estimate_invoice->from_date = $estimate_invoice->from_date !== null ? format_date($estimate_invoice->from_date) : '';
        $estimate_invoice->to_date = $estimate_invoice->to_date !== null ? format_date($estimate_invoice->to_date) : '';

        $statusBadge = '';

        if ($estimate_invoice->status == 'sent') {
            $statusBadge = '<span class="badge bg-primary">' . get_label('sent', 'Sent') . '</span>';
        } elseif ($estimate_invoice->status == 'accepted') {
            $statusBadge = '<span class="badge bg-success">' . get_label('accepted', 'Accepted') . '</span>';
        } elseif ($estimate_invoice->status == 'partially_paid') {
            $statusBadge = '<span class="badge bg-warning">' . get_label('partially_paid', 'Partially paid') . '</span>';
        } elseif ($estimate_invoice->status == 'fully_paid') {
            $statusBadge = '<span class="badge bg-success">' . get_label('fully_paid', 'Fully paid') . '</span>';
        } elseif ($estimate_invoice->status == 'draft') {
            $statusBadge = '<span class="badge bg-secondary">' . get_label('draft', 'Draft') . '</span>';
        } elseif ($estimate_invoice->status == 'declined') {
            $statusBadge = '<span class="badge bg-danger">' . get_label('declined', 'Declined') . '</span>';
        } elseif ($estimate_invoice->status == 'expired') {
            $statusBadge = '<span class="badge bg-warning">' . get_label('expired', 'Expired') . '</span>';
        } elseif ($estimate_invoice->status == 'not_specified') {
            $statusBadge = '<span class="badge bg-secondary">' . get_label('not_specified', 'Not specified') . '</span>';
        } elseif ($estimate_invoice->status == 'due') {
            $statusBadge = '<span class="badge bg-danger">' . get_label('due', 'Due') . '</span>';
        }



        $estimate_invoice->status = $statusBadge;
        return view('estimates-invoices.view', compact('estimate_invoice'));
    }

    public function pdf(Request $request, $id)
    {
        $estimate_invoice = EstimatesInvoice::findOrFail($id);
        $general_settings = get_settings('general_settings');

        $logo = !isset($general_settings['full_logo']) || empty($general_settings['full_logo']) ? 'storage/logos/default_full_logo.png' : 'storage/' . $general_settings['full_logo'];
        $company_title = $general_settings['company_title'] ?? 'Taskify';
        $addressParts = [
            $estimate_invoice->city ?? '',
            $estimate_invoice->state ?? '',
            $estimate_invoice->country ?? '',
            $estimate_invoice->zip_code ?? '',
        ];

        $addressParts = array_filter($addressParts); // Remove empty values
        $city_state_country_zip = implode(', ', $addressParts);

        $client = new Party([
            'name' => $estimate_invoice->name ?? '',
            'address' => $estimate_invoice->address ?? '',
            'city_state_country_zip' => $city_state_country_zip,
            'phone' => $estimate_invoice->phone ?? '',
        ]);


        $customer = new Party([
            'name'          => 'Ashley Medina',
            'address'       => 'The Green Street 12',
            'code'          => '#22663214',
            'custom_fields' => [
                'order number' => '> 654321 <',
            ],
        ]);

        $items = [
            InvoiceItem::make('Service 1')
                ->description('Your product or service description')
                ->pricePerUnit(47.79)
                ->quantity(2)
                ->discount(10),
            InvoiceItem::make('Service 2')->pricePerUnit(71.96)->quantity(2),
            InvoiceItem::make('Service 3')->pricePerUnit(4.56),
            InvoiceItem::make('Service 4')->pricePerUnit(87.51)->quantity(7)->discount(4)->units('kg'),
            InvoiceItem::make('Service 5')->pricePerUnit(71.09)->quantity(7)->discountByPercent(9),
            InvoiceItem::make('Service 6')->pricePerUnit(76.32)->quantity(9),
            InvoiceItem::make('Service 7')->pricePerUnit(58.18)->quantity(3)->discount(3),
            InvoiceItem::make('Service 8')->pricePerUnit(42.99)->quantity(4)->discountByPercent(3),
            InvoiceItem::make('Service 9')->pricePerUnit(33.24)->quantity(6)->units('m2'),
            InvoiceItem::make('Service 11')->pricePerUnit(97.45)->quantity(2),
            InvoiceItem::make('Service 12')->pricePerUnit(92.82),
            InvoiceItem::make('Service 13')->pricePerUnit(12.98),
            InvoiceItem::make('Service 14')->pricePerUnit(160)->units('hours'),
            InvoiceItem::make('Service 15')->pricePerUnit(62.21)->discountByPercent(5),
            InvoiceItem::make('Service 16')->pricePerUnit(2.80),
            InvoiceItem::make('Service 17')->pricePerUnit(56.21),
            InvoiceItem::make('Service 18')->pricePerUnit(66.81)->discountByPercent(8),
            InvoiceItem::make('Service 19')->pricePerUnit(76.37),
            InvoiceItem::make('Service 20')->pricePerUnit(55.80),
        ];

        $notes = [
            'your multiline',
            'additional notes',
            'in regards of delivery or something else',
        ];
        $notes = implode("<br>", $notes);


        $invoice = Invoice::make(($estimate_invoice->type == 'estimate' ? get_label('estimate_id_prefix', 'ESTMT-') : get_label('invoice_id_prefix', 'INVC-')) . $estimate_invoice->id . ' - ' . $company_title)
            ->series('BIG')
            ->status(get_label($estimate_invoice->status, ucfirst(str_replace('_', ' ', $estimate_invoice->status))))
            ->sequence(667)
            ->serialNumberFormat('{SEQUENCE}/{SERIES}')
            ->seller($client)
            ->buyer($customer)
            ->date(now()->subWeeks(3))
            ->dateFormat('m/d/Y')
            ->payUntilDays(14)
            ->currencySymbol('$')
            ->currencyCode('USD')
            ->currencyFormat('{SYMBOL}{VALUE}')
            ->currencyThousandsSeparator('.')
            ->currencyDecimalPoint(',')
            ->filename(($estimate_invoice->type == 'estimate' ? get_label('estimate_id_prefix', 'ESTMT-') : get_label('invoice_id_prefix', 'INVC-')) . $estimate_invoice->id . ' - ' . $company_title)
            ->addItems($items)
            ->notes($notes)
            ->logo($logo)
            // You can additionally save generated invoice to configured disk
            ->setCustomData(['estimate_invoice' => $estimate_invoice]);
        // ->save('public');

        $link = $invoice->url();
        // Then send email to party with link

        // And return invoice itself to browser or have a different view
        return $invoice->stream();
    }

    /**
     * Remove the specified estimate invoice.
     *
     * This endpoint deletes a estimate invoice based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Estimate Invoice Management
     *
     * @urlParam id int required The ID of the todo to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Estimate Invoice deleted successfully.",
     *   "id": 1,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Estimate Invoice not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the Estimate Invoice."
     * }
     */
    public function destroy($id)
    {
        $estimate_invoice = EstimatesInvoice::findOrFail($id);
        $type = ucfirst($estimate_invoice->type);
        // If the type is 'invoice', delete related payments
        if ($estimate_invoice->type == 'invoice') {
            $estimate_invoice->payments()->delete();
        }
        $estimate_invoice->items()->detach();
        DeletionService::delete(EstimatesInvoice::class, $id, $type);
        return response()->json(['error' => false, 'message' => $type . ' deleted successfully.', 'id' => $id, 'title' => $estimate_invoice->type == 'estimate' ? get_label('estimate_id_prefix', 'ESTMT-') . $id : get_label('invoice_id_prefix', 'INVC-') . $id, 'type' => $estimate_invoice->type]);
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:estimates_invoices,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];

        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $res = EstimatesInvoice::findOrFail($id);
            if ($res) {
                // If the type is 'invoice', delete related payments
                if ($res->type == 'invoice') {
                    $res->payments()->delete();
                }
                $deletedIds[] = $id;
                $deletedTitles[] = ($res->type == 'estimate' ? get_label('estimate_id_prefix', 'ESTMT-') : get_label('invoice_id_prefix', 'INVC-')) . $id;
                $res->items()->detach();
                DeletionService::delete(EstimatesInvoice::class, $id, ucfirst($res->type));
            }
        }
        return response()->json(['error' => false, 'message' => 'Records deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'type' => $res->type]);
    }

    public function duplicate($id)
    {
        $relatedTables = ['items']; // Include related tables as needed
        $res = EstimatesInvoice::findOrFail($id);
        // Use the general duplicateRecord function
        $duplicate = duplicateRecord(EstimatesInvoice::class, $id, $relatedTables);

        if (!$duplicate) {
            return response()->json(['error' => true, 'message' => ucfirst($res->type) . ' duplication failed.']);
        }
        if (request()->has('reload') && request()->input('reload') === 'true') {
            Session::flash('message', ucfirst($res->type) . ' duplicated successfully.');
        }
        return response()->json(['error' => false, 'message' => ucfirst($res->type) . ' duplicated successfully.', 'id' => $id, 'type' => $res->type]);
    }
    /**
     * List or search estimate invoices.
     *
     * This endpoint retrieves a list of estimate invoices based on various filters. The user must be authenticated to perform this action. The request allows searching and sorting by different parameters.
     *
     * @authenticated
     *
     * @group Estimate Invoice Management
     *
     * @urlParam id int optional The ID of the estimate invoice to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter invoices by id or note. Example: INVC-1001
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, created_at, and updated_at. Example: id
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam limit int optional The number of items per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Estimate invoice retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "client": {
     *         "id": 2,
     *         "name": "John Doe",
     *         "email": "client@example.com",
     *         "photo": "https://example.com/storage/photos/client.jpg"
     *       },
     *       "total": "₹ 1,000.00",
     *       "status": "Pending",
     *       "due_date": "2025-04-30",
     *       "created_by": "Admin User",
     *       "created_at": "2025-04-15",
     *       "updated_at": "2025-04-15"
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Estimate invoice not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Estimate invoices not found",
     *   "total": 0,
     *   "data": []
     * }
     */
    public function apiList(Request $request)
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status = (request('status')) ? request('status') : "";
        $types = request('types', []);
        $client_ids = request('client_ids', []);
        $created_by_user_ids = request('created_by_user_ids', []);
        $created_by_client_ids = request('created_by_client_ids', []);
        $date_between_from = request('date_between_from') ?: "";
        $date_between_to = request('date_between_to') ?: "";
        $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
        $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
        $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
        $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";
        $id = request("id", null);
        $limit = request("limit", 10);
        $offset = request("offset", 0);
        $where = ['estimates_invoices.workspace_id' => $this->workspace->id];

        if ($status != '') {
            $where['estimates_invoices.status'] = $status;
        }

        $estimates_invoices = EstimatesInvoice::select(
            'estimates_invoices.*',
            DB::raw('CONCAT(clients.first_name, " ", clients.last_name) AS client_name')
        )
            ->leftJoin('clients', 'estimates_invoices.client_id', '=', 'clients.id');


        if (!isAdminOrHasAllDataAccess()) {
            $estimates_invoices = $estimates_invoices->where(function ($query) {
                $query->where('estimates_invoices.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('estimates_invoices.client_id', $this->user->id);
            });
        }

        if (!empty($types)) {
            $estimates_invoices->whereIn('type', $types);
        }

        if (!empty($client_ids)) {
            $estimates_invoices->whereIn('client_id', $client_ids);
        }

        if (!empty($created_by_user_ids)) {
            $estimates_invoices->whereIn('estimates_invoices.created_by', array_map(function ($id) {
                return 'u_' . $id;
            }, $created_by_user_ids));
        }

        if (!empty($created_by_client_ids)) {
            $estimates_invoices->whereIn('estimates_invoices.created_by', array_map(function ($id) {
                return 'c_' . $id;
            }, $created_by_client_ids));
        }

        if ($date_between_from && $date_between_to) {
            $estimates_invoices = $estimates_invoices->where('estimates_invoices.from_date', '>=', $date_between_from)
                ->where('estimates_invoices.to_date', '<=', $date_between_to);
        }

        if ($start_date_from && $start_date_to) {
            $estimates_invoices = $estimates_invoices->whereBetween('estimates_invoices.from_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $estimates_invoices  = $estimates_invoices->whereBetween('estimates_invoices.to_date', [$end_date_from, $end_date_to]);
        }
        if ($search) {
            $estimates_invoices = $estimates_invoices->where(function ($query) use ($search) {
                $query->where('estimates_invoices.id', 'like', '%' . $search . '%')
                    ->orWhere('estimates_invoices.name', 'like', '%' . $search . '%')
                    ->orWhere(DB::raw('CONCAT("' . get_label('invoice_id_prefix', 'INVC-') . '", estimates_invoices.id)'), 'like', '%' . $search . '%')
                    ->orWhere(DB::raw('CONCAT("' . get_label('estimate_id_prefix', 'ESTMT-') . '", estimates_invoices.id)'), 'like', '%' . $search . '%');
            });
        }

        $estimates_invoices->where($where);
        $total = $estimates_invoices->count();

        if ($id) {
            $invoice = $estimates_invoices->find($id);

            if (!$invoice) {
                return formatApiResponse(false, 'Estimate Invoice not found', ['total' => 0, 'data' => []]);
            }


            return formatApiResponse(
                false,
                'Estimate Invoice retrieved successfully',
                [
                    'total' => 1,
                    'data' => formatEstimateInvoice($invoice),
                ]
            );
        }

        $invoices = $estimates_invoices->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        if ($invoices->isEmpty()) {
            return formatApiResponse(false, 'Estimates Invoices not found', ['total' => 0, 'data' => []]);
        }

        $data = $invoices->map(function ($invoice) {

            return formatEstimateInvoice($invoice);
        });

        return formatApiResponse(
            false,
            'Estimates Invoices retrieved successfully',
            [
                'total' => $total,
                'data' => $data
            ]
        );
    }
}
