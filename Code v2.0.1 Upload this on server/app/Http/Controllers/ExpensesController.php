<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Workspace;
use App\Models\ExpenseType;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class ExpensesController extends Controller
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
        $expenses = $this->workspace->expenses();
        if (!isAdminOrHasAllDataAccess()) {
            $expenses = $expenses->where(function ($query) {
                $query->where('expenses.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('expenses.user_id', $this->user->id);
            });
        }
        $expenses = $expenses->count();
        return view('expenses.list', ['expenses' => $expenses]);
    }
    public function expense_types(Request $request)
    {
        $expense_types = ExpenseType::forWorkspace($this->workspace->id);
        $expense_types = $expense_types->count();
        return view('expenses.expense_types', ['expense_types' => $expense_types]);
    }
    /**
     * Create a new expense.
     *
     * This endpoint creates a new expense item with the specified title, expense_type_id,user_id,amount,expense_date,note. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Expense Management
     *
     * @bodyParam title string required The title of the expense. Example: Finish report
     * @bodyParam title string required The expense_type_id of the expense. Example: 1
     * @bodyParam title string required The user_id of the expense. Example: 1
     * @bodyParam title string required The amount of the expense. Example: Finish report
     * @bodyParam title string required The expense_date of the expense. Example: 2024-08-07
     * @bodyParam title string required The note of the expense. Example: Finish report

     *
     * @response 200 {
     * "error": false,
     * "message": "Expense created successfully.",
     * "id": 36,
     * "data": {
     *           'id' => '1',
     *           'title' => 'Expense Title',
     *           'expense_type_id' => '1',
     *           'user_id' => '1',
     *           'amount' => '100.00',
     *           'expense_date' => '2023-10-01',
     *           'note' => 'Expense note',
     *           'created_by' => 'John Doe',
     *           'created_at' => format_date($exp->created_at, true),
     *          }
     *
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "title": [
     *       "The title field is required."
     *     ],
     *     "expense_type_id": [
     *       "The expense type field is required."
     *     ],
     *    "user_id": [
     *      "The user id field is required."
     *    ],
     *    "amount": [
     *      "The amount field is required."
     *   ],
     *   "expense_date": [
     *     "The expense date field is required."
     *    ],
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the expense."
     * }
     */
    public function store(Request $request)
    {
        // Validate the request data
        try {
            $isApi = $request->get('isApi', false);
            $formFields = $request->validate([
                'title' => 'required|unique:expenses,title', // Validate the title
                'expense_type_id' => 'required',
                'user_id' => 'nullable',
                'amount' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'amount');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'expense_date' => 'required',
                'note' => 'nullable'
            ], [
                'expense_type_id.required' => 'The expense type field is required.'
            ]);

            $expense_date = $request->input('expense_date');

            $formFields['expense_date'] = format_date($expense_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            $formFields['amount'] = str_replace(',', '', $request->input('amount'));
            $formFields['workspace_id'] = $this->workspace->id;
            $formFields['created_by'] = isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id;

            $exp = Expense::create($formFields);
            $created_by =
                strpos($exp->created_by, 'u_') === 0 ? User::find(substr($exp->created_by, 2)) : Client::find(substr($exp->created_by, 2));
            if ($isApi) {
                $created_by = strpos($exp->created_by, 'u_') === 0 ? User::find(substr($exp->created_by, 2)) : Client::find(substr($exp->created_by, 2));

                return
                    formatApiResponse(
                        false,
                        'Expense updated successfully.',
                        [
                            'id' => $exp->id,
                            'data' => [
                                'id' => $exp->id,
                                'title' => $exp->title,
                                'expense_type_id' => $exp->expense_type_id,
                            'expense_type' => $exp->expense_type,
                                'user_id' => $exp->user_id,
                            'user' => [
                                'id' => $exp->user->id,
                                'first_name' => $exp->user->first_name,
                                'last_name' => $exp->user->last_name,
                                'email' => $exp->user->email,
                                'photo' => $exp->user->photo ? asset('storage/' . $exp->user->photo) : asset('storage/photos/no-image.jpg'),
                            ],
                                'amount' => format_currency($exp->amount, false, false),
                                'expense_date' => format_date($exp->expense_date, false, to_format: 'Y-m-d'),
                                'note' => $exp->note,
                                'created_by' => ucwords($created_by->first_name . ' ' . $created_by->last_name),
                                'created_at' => format_date($exp->created_at, true, to_format: 'Y-m-d'),
                            ]
                        ]

                    );
            } else {
                return response()->json(['error' => false, 'message' => 'Expense created successfully.', 'id' => $exp->id]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {

            if ($isApi) {
                return formatApiResponse(
                    true,
                    'An error occurred while creating the expense ' . $e->getMessage() . ' ' . $e->getLine(),
                    [],
                    500
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Expense couldn\'t created.' .  $e->getMessage()]);
            }
        }
    }

    /**
     * Create a new expense type.
     *
     * This endpoint creates a new expense type item with the specified title, description. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Expense Management
     *
     * @bodyParam title string required The title of the expense. Example: Finish report
     * @bodyParam description string required The description of the expense. Example: Finish report

     *
     * @response 200 {
     * "error": false,
     * "message": "Expense Type created successfully.",
     * "id": 1,
     * "data": {
     *           'id' => '1',
     *           'title' => 'Expense Title',
     *           'description' => 'Expense Description',
     *           'created_at' => 2023-10-01 12:00:00',
     *          }
     *
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "title": [
     *       "The title field is required."
     *     ],
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the expense type."
     * }
     */
    public function store_expense_type(Request $request)
    {
        // Validate the request data
        try {
            $isApi = $request->get('isApi', false);
            $formFields = $request->validate([
                'title' => 'required|unique:expense_types,title', // Validate the type
                'description' => 'nullable'
            ]);
            $formFields['workspace_id'] = $this->workspace->id;

            $et = ExpenseType::create($formFields);
            if ($isApi) {
                return
                    formatApiResponse(
                        false,
                        'Expense type created successfully.',
                        [
                            'id' => $et->id,
                            'data' => [
                                'id' => $et->id,
                                'title' => $et->title,
                                'description' => $et->description,
                                'created_at' => format_date($et->created_at, true),
                            ]
                        ]

                    );
            } else {
                return response()->json(['error' => false, 'message' => 'Expense type created successfully.', 'id' => $et->id, 'title' => $et->type, 'type' => 'expense_type']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($request->get('isApi', false), $e->errors());
        } catch (\Exception $e) {
            if ($request->get('isApi', false)) {
                return formatApiResponse(
                    true,
                    'An error occurred while creating the expense type' . $e->getMessage(),
                    [],
                    500
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Expense type couldn\'t created.' .  $e->getMessage()]);
            }
        }
    }

    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $type_ids = request('type_ids', []);
        $user_ids = request('user_ids', []);
        $exp_date_from = (request('date_from')) ? request('date_from') : "";
        $exp_date_to = (request('date_to')) ? request('date_to') : "";
        $where = ['expenses.workspace_id' => $this->workspace->id];

        $expenses = Expense::select(
            'expenses.*',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name'),
            'expense_types.title as expense_type'
        )
            ->leftJoin('users', 'expenses.user_id', '=', 'users.id')
            ->leftJoin('expense_types', 'expenses.expense_type_id', '=', 'expense_types.id');


        if (!isAdminOrHasAllDataAccess()) {
            $expenses = $expenses->where(function ($query) {
                $query->where('expenses.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('expenses.user_id', $this->user->id);
            });
        }
        if (!empty($type_ids)) {
            $expenses = $expenses->whereIn('expenses.expense_type_id', $type_ids);
        }
        if (!empty($user_ids)) {
            $expenses = $expenses->whereIn('expenses.user_id', $user_ids);
        }
        if ($exp_date_from && $exp_date_to) {
            $expenses = $expenses->whereBetween('expenses.expense_date', [$exp_date_from, $exp_date_to]);
        }
        if ($search) {
            $expenses = $expenses->where(function ($query) use ($search) {
                $query->where('expenses.title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('expenses.note', 'like', '%' . $search . '%')
                    ->orWhere('expenses.id', 'like', '%' . $search . '%');
            });
        }

        $expenses->where($where);
        $total = $expenses->count();

        $canCreate = checkPermission('create_expenses');
        $canEdit = checkPermission('edit_expenses');
        $canDelete = checkPermission('delete_expenses');

        $expenses = $expenses->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($expense) use ($canEdit, $canDelete, $canCreate) {
                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-expense" data-bs-toggle="modal" data-id="' . $expense->id . '" title="' . get_label('update', 'Update') . '" class="card-link"><i class="bx bx-edit mx-1"></i></a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $expense->id . '" data-type="expenses">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                if ($canCreate) {
                    $actions .= '<a href="javascript:void(0);" class="duplicate" data-id="' . $expense->id . '" data-title="' . $expense->title . '" data-type="expenses" title="' . get_label('duplicate', 'Duplicate') . '">' .
                        '<i class="bx bx-copy text-warning mx-2"></i>' .
                        '</a>';
                }

                $actions = $actions ?: '-';



                return [
                    'id' => $expense->id,
                    'user_id' => $expense->user_id ?? '-',
                    'user' => formatUserHtml($expense->user),
                    'title' => $expense->title,
                    'expense_type_id' => $expense->expense_type_id,
                    'expense_type' => $expense->expense_type,
                    'amount' => format_currency($expense->amount),
                    'expense_date' => format_date($expense->expense_date),
                    'note' => $expense->note,
                    'created_by' => strpos($expense->created_by, 'u_') === 0 ? formatUserHtml(User::find(substr($expense->created_by, 2))) : formatClientHtml(Client::find(substr($expense->created_by, 2))),
                    'created_at' => format_date($expense->created_at, true),
                    'updated_at' => format_date($expense->updated_at, true),
                    'actions' => $actions
                ];
            });


        return response()->json([
            "rows" => $expenses->items(),
            "total" => $total,
        ]);
    }

    public function expense_types_list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $expense_types = ExpenseType::forWorkspace($this->workspace->id);
        if ($search) {
            $expense_types = $expense_types->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $expense_types->count();
        $canEdit = checkPermission('edit_expense_types');
        $canDelete = checkPermission('delete_expense_types');
        $expense_types = $expense_types->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($expense_type) use ($canEdit, $canDelete) {
                $actions = '';

            if ($canEdit) {
                $actions .= '<a href="javascript:void(0);" class="edit-expense-type" data-id="' . $expense_type->id . '" title="' . get_label('update', 'Update') . '">' .
                    '<i class="bx bx-edit mx-1"></i>' .
                    '</a>';
            }

            if ($canDelete) {
                $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $expense_type->id . '" data-type="expense-type">' .
                    '<i class="bx bx-trash text-danger mx-1"></i>' .
                    '</button>';
            }

            $actions = $actions ?: '-';

                return [
                    'id' => $expense_type->id,
                    'title' => $expense_type->title . ($expense_type->id == 0 ? ' <span class="badge bg-success">' . get_label('default', 'Default') . '</span>' : ''),
                    'description' => $expense_type->description,
                    'created_at' => format_date($expense_type->created_at, true),
                    'updated_at' => format_date($expense_type->updated_at, true),
                    'actions' => $actions,
                ];
            });

        return response()->json([
            "rows" => $expense_types->items(),
            "total" => $total,
        ]);
    }

    public function get($id)
    {
        $exp = Expense::with(['user', 'expense_type'])->findOrFail($id);
        $exp->amount = format_currency($exp->amount, false, false);
        return response()->json(['exp' => $exp]);
    }

    public function get_expense_type($id)
    {
        $et = ExpenseType::findOrFail($id);
        return response()->json(['et' => $et]);
    }

    /**
     * Update a existing expense.
     *
     * This endpoint update existing expense item with the specified title, expense_type_id,user_id,amount,expense_date,note. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Expense Management
     *
     * @bodyParam id string required The id of the expense. Example: 1
     * @bodyParam title string required The title of the expense. Example: Finish report
     * @bodyParam expense_type_id string required The expense_type_id of the expense. Example: 1
     * @bodyParam user_id string required The user_id of the expense. Example: 1
     * @bodyParam amount string required The amount of the expense. Example: Finish report
     * @bodyParam expense_date string required The expense_date of the expense. Example: 2024-08-07
     * @bodyParam note string required The note of the expense. Example: Finish report

     *
     * @response 200 {
     * "error": false,
     * "message": "Expense created successfully.",
     * "id": 36,
     * "data": {
     *           'id' => '1',
     *           'title' => 'Expense Title',
     *           'expense_type_id' => '1',
     *           'user_id' => '1',
     *           'amount' => '100.00',
     *           'expense_date' => '2023-10-01',
     *           'note' => 'Expense note',
     *           'created_by' => 'John Doe',
     *           'created_at' => format_date($exp->created_at, true),
     *          }
     *
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "title": [
     *       "The title field is required."
     *     ],
     *     "expense_type_id": [
     *       "The expense type field is required."
     *     ],
     *    "user_id": [
     *      "The user id field is required."
     *    ],
     *    "amount": [
     *      "The amount field is required."
     *   ],
     *   "expense_date": [
     *     "The expense date field is required."
     *    ],
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the expense."
     * }
     */
    public function update(Request $request)
    {
        // Validate the request data
        try {

            $isApi = $request->get('isApi', false);
            $formFields = $request->validate([
                'id' => 'required',
                'title' => 'required|unique:expenses,title,' . $request->id,
                'expense_type_id' => 'required',
                'user_id' => 'nullable',
                'amount' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'amount');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'expense_date' => 'required',
                'note' => 'nullable'
            ], [
                'expense_type_id.required' => 'The expense type field is required.'
            ]);
            $expense_date = $request->input('expense_date');

            $formFields['expense_date'] = format_date($expense_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            $formFields['amount'] = str_replace(',', '', $request->input('amount'));
            $exp = Expense::findOrFail($request->id);

            $exp->update($formFields);

            if ($isApi) {
                $created_by = strpos($exp->created_by, 'u_') === 0 ? User::find(substr($exp->created_by, 2)) : Client::find(substr($exp->created_by, 2));

                return
                    formatApiResponse(
                        false,
                        'Expense updated successfully.',
                        [
                            'id' => $exp->id,
                            'data' => [
                                'id' => $exp->id,
                                'title' => $exp->title,
                                'expense_type_id' => $exp->expense_type_id,
                            'expense_type' => $exp->expense_type,
                                'user_id' => $exp->user_id,
                            'user' => [
                                'id' => $exp->user->id,
                                'first_name' => $exp->user->first_name,
                                'last_name' => $exp->user->last_name,
                                'email' => $exp->user->email,
                                'photo' => $exp->user->photo ? asset('storage/' . $exp->user->photo) : asset('storage/photos/no-image.jpg'),
                            ],
                                'amount' => format_currency($exp->amount, false, false),
                                'expense_date' => format_date($exp->expense_date, false, to_format: 'Y-m-d'),
                                'note' => $exp->note,
                                'created_by' => ucwords($created_by->first_name . ' ' . $created_by->last_name),
                                'created_at' => format_date($exp->created_at, true, to_format: 'Y-m-d'),
                            ]
                        ]

                    );
            } else {
                return response()->json(['error' => false, 'message' => 'Expense updated successfully.', 'id' => $exp->id]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {

            if ($isApi) {
                return formatApiResponse(
                    true,
                    'An error occurred while updating the expense ' . $e->getMessage(),
                    [],
                    500
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Expense couldn\'t updated. ' .  $e->getMessage()]);
            }
        }
    }

    /**
     * Update an existing expense type.
     *
     * This endpoint update an existing expense type item with the specified title, description. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Expense Management
     *
     * @bodyParam id string required The id of the expense. Example: 1
     * @bodyParam title string required The title of the expense. Example: Finish report
     * @bodyParam description string required The description of the expense. Example: Finish report

     *
     * @response 200 {
     * "error": false,
     * "message": "Expense Type created successfully.",
     * "id": 1,
     * "data": {
     *           'id' => '1',
     *           'title' => 'Expense Title',
     *           'description' => 'Expense Description',
     *           'created_at' => 2023-10-01 12:00:00',
     *          }
     *
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "title": [
     *       "The title field is required."
     *     ],
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the expense type."
     * }
     */
    public function update_expense_type(Request $request)
    {
        try {
            $isApi = $request->get('isApi', false);
            $formFields = $request->validate([
                'id' => ['required'],
                'title' => 'required|unique:expense_types,title,' . $request->id,
                'description' => 'nullable',
            ]);
            $et = ExpenseType::findOrFail($request->id);

            $et->update($formFields);
            if ($isApi) {
                return
                    formatApiResponse(
                        false,
                        'Expense type updated successfully.',
                        [
                            'id' => $et->id,
                            'data' => [
                                'id' => $et->id,
                                'title' => $et->title,
                                'description' => $et->description,
                                'created_at' => format_date($et->created_at, true),
                            ]
                        ]

                    );
            } else {
                return response()->json(['error' => false, 'message' => 'Expense type updated successfully.', 'id' => $et->id, 'type' => 'expense_type']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'An error occurred while updating the expense type' . $e->getMessage(),
                    [],
                    500
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Expense type couldn\'t updated.' .  $e->getMessage()]);
            }
        }
    }

    /**
     * Remove the specified expense.
     *
     * This endpoint deletes a expense item based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Expense Management
     *
     * @urlParam id int required The ID of the todo to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Expense deleted successfully.",
     *   "id": 1,
     *   "title": "Expense Title"
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Expense not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the expense."
     * }
     */
    public function destroy($id)
    {
        $exp = Expense::findOrFail($id);
        DeletionService::delete(Expense::class, $id, 'Expense');
        return response()->json(['error' => false, 'message' => 'Expense deleted successfully.', 'id' => $id, 'title' => $exp->title]);
    }

    /**
     * Remove the specified expense type.
     *
     * This endpoint deletes a expense type item based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Expense Management
     *
     * @urlParam id int required The ID of the todo to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Expense Type deleted successfully.",
     *   "id": 1,
     *   "title": "Expense Type Title"
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Expense Type not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the expense type."
     * }
     */
    public function delete_expense_type($id)
    {
        $et = ExpenseType::findOrFail($id);
        if ($et->expenses()->exists()) {
            return response()->json([
                'error' => true,
                'message' => 'This expense type is currently assigned to some expenses and cannot be deleted.'
            ]);
        }

        $et->expenses()->update(['expense_type_id' => 0]);
        $response = DeletionService::delete(ExpenseType::class, $id, 'Expense type');
        $data = $response->getData();
        if ($data->error) {
            return response()->json(['error' => true, 'message' => $data->message]);
        } else {
            return response()->json(['error' => false, 'message' => 'Expense type deleted successfully.', 'id' => $id, 'title' => $et->title, 'type' => 'expense_type']);
        }
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:expenses,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $exp = Expense::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $exp->title;
            DeletionService::delete(Expense::class, $id, 'Expense');
        }

        return response()->json(['error' => false, 'message' => 'Expense(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'type' => 'expense']);
    }

    public function delete_multiple_expense_type(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:expense_types,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        $defaultExpenseTypeIds = [];
        $nonDefaultIds = [];

        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $et = ExpenseType::findOrFail($id);
            if ($et) {
                if ($et->id == 0) { // Assuming 0 is the ID for default expense type
                    $defaultExpenseTypeIds[] = $id;
                } else {
                    $et->expenses()->update(['expense_type_id' => 0]);
                    $deletedIds[] = $id;
                    $deletedTitles[] = $et->title;
                    DeletionService::delete(ExpenseType::class, $id, 'Expense type');
                    $nonDefaultIds[] = $id;
                }
            }
        }

        if (count($defaultExpenseTypeIds) > 0) {
            if (count($ids) == 1) {
                return response()->json(['error' => true, 'message' => 'Default expense type cannot be deleted.']);
            } else {
                return response()->json(['error' => false, 'message' => 'Expense type(s) deleted successfully except default.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'type' => 'expense_type']);
            }
        } else {
            return response()->json(['error' => false, 'message' => 'Expense type(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'type' => 'expense_type']);
        }
    }

    public function duplicate($id)
    {
        // Use the general duplicateRecord function
        $title = (request()->has('title') && !empty(trim(request()->title))) ? request()->title : '';
        $duplicated = duplicateRecord(Expense::class, $id, [], $title);
        if (!$duplicated) {
            return response()->json(['error' => true, 'message' => 'Expense duplication failed.']);
        }
        return response()->json(['error' => false, 'message' => 'Expense duplicated successfully.', 'id' => $id]);
    }
    /**
     * List or search expenses.
     *
     * Retrieve a paginated list of expenses or a single expense by ID.
     *
     * @authenticated
     * @group Expense Management
     *
     * @urlParam id int optional The ID of the expense to retrieve. Example: 1
     *
     * @queryParam search string optional Search keyword for title, amount, note, or ID. Example: Lunch
     * @queryParam sort string optional Column to sort by. Default: id. Allowed: id, title, created_at, updated_at. Example: title
     * @queryParam order string optional Sort order: ASC or DESC. Default: DESC. Example: ASC
     * @queryParam limit int optional Number of records per page. Example: 10
     * @queryParam offset int optional Offset for pagination. Example: 0
     * @queryParam type_ids[] int[] optional Filter by expense type IDs. Example: [1,2]
     * @queryParam user_ids[] int[] optional Filter by user IDs. Example: [3,5]
     * @queryParam date_from date optional Start date for expense_date filtering. Example: 2023-01-01
     * @queryParam date_to date optional End date for expense_date filtering. Example: 2023-01-31
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Expenses retrieved successfully",
     *   "total": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Travel Reimbursement",
     *       "expense_type_id": 2,
     *       "expense_type": "Travel",
     *       "user_id": 5,
     *       "user": {
     *         "id": 5,
     *         "first_name": "Alice",
     *         "last_name": "Smith",
     *         "email": "alice@example.com",
     *         "photo": "https://yourdomain.com/storage/photos/alice.jpg"
     *       },
     *       "amount": "150.00",
     *       "expense_date": "2023-10-01",
     *       "note": "Flight ticket",
     *       "created_by": "John Doe",
     *       "created_at": "2023-10-01"
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Expense not found",
     *   "total": 0,
     *   "data": []
     * }
     */

    public function apiList()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $type_ids = request('type_ids', []);
        $user_ids = request('user_ids', []);
        $exp_date_from = (request('date_from')) ? request('date_from') : "";
        $exp_date_to = (request('date_to')) ? request('date_to') : "";
        $where = ['expenses.workspace_id' => $this->workspace->id];
        $limit = request('limit', 10);
        $offset = request('offset', 0);
        $id = request('id', null);
        // dd($id);
        $expenses = Expense::select(
            'expenses.*',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name'),
            'expense_types.title as expense_type'
        )
            ->leftJoin('users', 'expenses.user_id', '=', 'users.id')
            ->leftJoin('expense_types', 'expenses.expense_type_id', '=', 'expense_types.id');


        if (!isAdminOrHasAllDataAccess()) {
            $expenses = $expenses->where(function ($query) {
                $query->where('expenses.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('expenses.user_id', $this->user->id);
            });
        }
        if (!empty($type_ids)) {
            $expenses = $expenses->whereIn('expenses.expense_type_id', $type_ids);
        }
        if (!empty($user_ids)) {
            $expenses = $expenses->whereIn('expenses.user_id', $user_ids);
        }
        if ($exp_date_from && $exp_date_to) {
            $expenses = $expenses->whereBetween('expenses.expense_date', [$exp_date_from, $exp_date_to]);
        }
        if ($search) {
            $expenses = $expenses->where(function ($query) use ($search) {
                $query->where('expenses.title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('expenses.note', 'like', '%' . $search . '%')
                    ->orWhere('expenses.id', 'like', '%' . $search . '%');
            });
        }

        $expenses->where($where);
        $total = $expenses->count();
        if ($id) {
            $expense = $expenses->find($id);

            if (!$expense) {
                return formatApiResponse(
                    false,
                    'Expense not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $created_by = strpos($expense->created_by, 'u_') === 0 ? User::find(substr($expense->created_by, 2)) : Client::find(substr($expense->created_by, 2));

            return formatApiResponse(
                false,
                'Expense retrieved successfully',
                [
                    'total' => 1,
                    'data' => [
                        [
                            'id' => $expense->id,
                            'title' => $expense->title,
                            'expense_type_id' => $expense->expense_type_id,
                            'expense_type' => $expense->expense_type,
                            'user_id' => $expense->user_id,
                            'user' => [
                                'id' => $expense->user ? $expense->user->id : null,
                                'first_name' => $expense->user ? $expense->user->first_name : null,
                                'last_name' => $expense->user ? $expense->user->last_name : null,
                                'email' => $expense->user ? $expense->user->email : null,
                                'photo' => $expense->user && $expense->user->photo ? asset('storage/' . $expense->user->photo) : asset('storage/photos/no-image.jpg'),
                            ],
                            'amount' => format_currency($expense->amount, false, false),
                            'expense_date' => format_date($expense->expense_date, to_format: 'Y-m-d'),
                            'note' => $expense->note,
                            'created_by' => ucwords($created_by->first_name . ' ' . $created_by->last_name),
                            'created_at' => format_date($expense->created_at, true, to_format: 'Y-m-d'),
                        ]
                    ]
                ]
            );
        } else {

            $expenses = $expenses->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get();
            if ($expenses->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Expense not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $data = $expenses->map(function ($expense) {

                $created_by = strpos($expense->created_by, 'u_') === 0 ? User::find(substr($expense->created_by, 2)) : Client::find(substr($expense->created_by, 2));
                return [
                    'id' => $expense->id,
                    'title' => $expense->title,
                    'expense_type_id' => $expense->expense_type_id,
                    'expense_type' => $expense->expense_type,
                    'user_id' => $expense->user_id,
                    'user' => [
                        'id' => $expense->user ? $expense->user->id : null,
                        'first_name' => $expense->user ? $expense->user->first_name : null,
                        'last_name' => $expense->user ? $expense->user->last_name : null,
                        'email' => $expense->user ? $expense->user->email : null,
                        'photo' => $expense->user && $expense->user->photo ? asset('storage/' . $expense->user->photo) : asset('storage/photos/no-image.jpg'),
                    ],
                    'amount' => format_currency($expense->amount, false, false),
                    'expense_date' => format_date($expense->expense_date, to_format: 'Y-m-d'),
                    'note' => $expense->note,
                    'created_by' => ucwords($created_by->first_name . ' ' . $created_by->last_name),
                    'created_at' => format_date($expense->created_at, true, to_format: 'Y-m-d'),
                ];
            });
            return formatApiResponse(
                false,
                'Expenses retrieved successfully',
                [
                    'total' => $total,
                    'data' => $data
                ]
            );
        }
    }
    /**
     * List or search expense types.
     *
     * Retrieve a list of expense types, optionally filtered by search term or a specific ID.
     * Supports sorting, pagination, and workspace scoping. Authentication is required.
     *
     * @authenticated
     *
     * @group Expense Management
     *
     * @urlParam id int optional The ID of the specific expense type to retrieve. Example: 1
     *
     * @queryParam search string optional Search term for title, description, or ID. Example: Travel
     * @queryParam sort string optional Field to sort by. Defaults to `id`. Sortable fields: `id`, `title`, `created_at`, `updated_at`. Example: title
     * @queryParam order string optional Sort order: `ASC` or `DESC`. Defaults to `DESC`. Example: ASC
     * @queryParam limit int optional Number of items per page. Default is 10. Example: 10
     * @queryParam offset int optional Number of items to skip (for pagination). Default is 0. Example: 0
     *
     * @response 200 scenario="Single Expense Type Found" {
     *   "error": false,
     *   "message": "Expense type retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Travel",
     *       "description": "Travel expenses",
     *       "created_at": "2023-10-01"
     *     }
     *   ]
     * }
     *
     * @response 200 scenario="Multiple Expense Types Found" {
     *   "error": false,
     *   "message": "Expense types retrieved successfully",
     *   "total": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Travel",
     *       "description": "Travel expenses",
     *       "created_at": "2023-10-01"
     *     },
     *     {
     *       "id": 2,
     *       "title": "Office Supplies",
     *       "description": "Stationery and office supplies",
     *       "created_at": "2023-10-03"
     *     }
     *   ]
     * }
     *
     * @response 200 scenario="No Results Found" {
     *   "error": true,
     *   "message": "Expense type not found",
     *   "total": 0,
     *   "data": []
     * }
     */

    public function apiListExpenseTypes()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $limit = request('limit', 10);
        $offset = request('offset', 0);
        $id = request('id', null);
        $expense_types = ExpenseType::forWorkspace($this->workspace->id);

        if ($search) {
            $expense_types = $expense_types->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $expense_types->count();
        if ($id) {
            $expense_type = $expense_types->find($id);
            if (!$expense_type) {
                return formatApiResponse(
                    false,
                    'Expense type not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            return formatApiResponse(
                false,
                'Expense type retrieved successfully',
                [
                    'total' => 1,
                    'data' => [
                        [
                            'id' => $expense_type->id,
                            'title' => $expense_type->title,
                            'description' => $expense_type->description,
                            'created_at' => format_date($expense_type->created_at, true, to_format: 'Y-m-d'),
                        ]
                    ]
                ]
            );
        } else {

            $expense_types = $expense_types->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get();
            if ($expense_types->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Expense type not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $data = $expense_types->map(function ($expense_type) {
                return [
                    'id' => $expense_type->id,
                    'title' => $expense_type->title . ($expense_type->id == 0 ? ' <span class="badge bg-success">' . get_label('default', 'Default') . '</span>' : ''),
                    'description' => $expense_type->description,
                    'created_at' => format_date($expense_type->created_at, true, to_format: 'Y-m-d'),
                ];
            });
            return formatApiResponse(
                false,
                'Expense types retrieved successfully',
                [
                    'total' => $total,
                    'data' => $data
                ]
            );
        }
    }
}
