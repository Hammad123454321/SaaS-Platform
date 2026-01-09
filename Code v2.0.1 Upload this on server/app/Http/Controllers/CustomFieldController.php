<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\CustomField;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomFieldController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customFields = CustomField::all();
        return view('custom_fields.index', compact('customFields'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }


    /**
     * Create a new custom field.
     *
     * This endpoint allows the creation of a new custom field for either the project or task module.
     * Depending on the field type, options may be required (for example: radio, checkbox, select).
     * User must be authenticated.
     *
     * @authenticated
     *
     * @group Custom Field Management
     *
     * @bodyParam module string required The module this custom field belongs to. Must be one of: `project`, `task`. Example: task
     * @bodyParam field_label string required The label for the custom field. Example: Priority
     * @bodyParam field_type string required The type of the custom field. Must be one of: `text`, `number`, `password`, `textarea`, `radio`, `date`, `checkbox`, `select`. Example: select
     * @bodyParam options string optional Required if field_type is `radio`, `checkbox`, or `select`. Provide one option per line. Example: High\nMedium\nLow
     * @bodyParam required string optional Whether this field is required. Example: 1
     * @bodyParam visibility string optional Whether this field is visible to end users. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Custom Field Created Successfully",
     *   "data": {
     *     "id": 12,
     *     "type": "custom_field"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "data": {
     *     "field_label": ["The field label field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Custom Field Couldn't Created.",
     *   "data": {
     *     "error": "Exception message",
     *     "line": 85,
     *     "file": "/var/www/html/app/Http/Controllers/CustomFieldController.php"
     *   }
     * }
     */

    public function store(Request $request)
    {

        $isApi = $request->get('isApi', false);
        try {
            $request->validate([
                'module' => 'required|string|in:project,task',
                'field_label' => 'required|string',
                'field_type' => 'required|string|in:text,number,password,textarea,radio,date,checkbox,select',
                'options' => 'nullable|array|required_if:field_type,radio,checkbox,select',
                'required' => 'nullable|string',
                'visibility' => 'nullable|string',
            ]);
            $customField = new CustomField();
            $customField->module = $request->module;
            $customField->field_type = $request->field_type;
            $customField->field_label = $request->field_label;
            $customField->name = '';
            $customField->options = in_array($request->field_type, ['radio', 'checkbox', 'select'])
                ? json_encode(($request->options))
                : null;

            $customField->required = $request->required;
            $customField->visibility = $request->visibility;
            $customField->save();
            return formatApiResponse(
                false,
                'Custom Field Created Successfully',
                [
                    'id' => $customField->id,
                    'type' => 'custom_field',
                    'data' => formatCustomField($customField)
                ],
                200
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {
            return formatApiResponse(
                true,
                'Custom Field Couldn\'t Updated.',
                [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ],
                500

            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {

        $field = CustomField::find($id);
        // Decode JSON options for radio, checkbox, select
        if (in_array($field->field_type, ['radio', 'checkbox', 'select']) && $field->options) {
            $field->options = json_decode($field->options, true);
        }
        return response()->json(['success' => true, 'data' => $field]);
    }


    /**
     * Update an existing custom field.
     *
     * This endpoint updates the details of a custom field identified by its ID.
     * Options are required if the field type is `radio`, `checkbox`, or `select`.
     * User must be authenticated.
     *
     * @authenticated
     *
     * @group Custom Field Management
     *
     * @urlParam id integer required The ID of the custom field to update. Example: 12
     *
     * @bodyParam module string required The module this custom field belongs to. Must be one of: `project`, `task`. Example: project
     * @bodyParam field_label string required The label for the custom field. Example: Status
     * @bodyParam field_type string required The type of the custom field. Must be one of: `text`, `number`, `password`, `textarea`, `radio`, `date`, `checkbox`, `select`. Example: radio
     * @bodyParam options string optional Required if field_type is `radio`, `checkbox`, or `select`. Provide one option per line. Example: Active\nInactive
     * @bodyParam required string optional Whether this field is required. Example: 1
     * @bodyParam visibility string optional Whether this field is visible to end users. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Custom field updated successfully",
     *   "data": {
     *     "id": 12,
     *     "type": "custom_field"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "data": {
     *     "field_label": ["The field label field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": {
     *     "error": "Exception message",
     *     "line": 78,
     *     "file": "/var/www/html/app/Http/Controllers/CustomFieldController.php"
     *   }
     * }
     */
    public function update(Request $request, string $id)
    {
        $field = CustomField::find($id);


        $rules = [
            'module' => 'required|string|in:project,task',
            'field_label' => 'required|string',
            'field_type' => 'required|string|in:text,number,password,textarea,radio,date,checkbox,select',
            'options' => 'nullable|array|required_if:field_type,radio,checkbox,select',
            'required' => 'nullable|string',
            'visibility' => 'nullable|string',
        ];
        $validator = Validator::make($request->all(), $rules, [
            'regex' => 'This field must not contain special characters.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        $field->module = $request->module;
        $field->field_label = $request->field_label;
        $field->field_type = $request->field_type;
        $field->options = in_array($request->field_type, ['radio', 'checkbox', 'select'])
            ? json_encode($request->options)
            : null;
        $field->required = $request->required;
        $field->visibility = $request->visibility;
        $field->save();

        // return response()->json(['success' => 'Custom field updated successfully'], 200);
        return formatApiResponse(
            false,
            'Custom field updated successfully',
            [
                'id' => $field->id,
                'type' => 'custom_field',
                'data' => formatCustomField($field)
            ],
            200

        );
    }

    /**
     * Delete a custom field.
     *
     * This endpoint deletes a specific custom field by ID.
     * User must be authenticated.
     *
     * @authenticated
     *
     * @group Custom Field Management
     *
     * @urlParam id integer required The ID of the custom field to delete. Example: 15
     *
     * @response 200 {
     *   "error": false,
     *   "message": "CustomField deleted successfully",
     *   "data": []
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "CustomField not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */
    public function destroy(string $id)
    {
        try {
            $field = CustomField::find($id);

            $response = DeletionService::delete(CustomField::class, $field->id, 'CustomeField');

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


    public function list()
    {
        $search = request('search');
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $limit = request('limit', 10);
        $offset = request('offset', 0);


        $customFields = CustomField::orderBy($sort, $order);

        if ($search) {
            $customFields = $customFields->where(function ($query) use ($search) {
                $query->where('module', 'like', '%' . $search . '%')
                    ->orWhere('field_label', 'like', '%' . $search . '%')
                    ->orWhere('field_type', 'like', '%' . $search . '%');
            });
        }

        $total = $customFields->count();

        $canEdit = isAdminOrHasAllDataAccess();
        $canDelete = isAdminOrHasAllDataAccess();

        $customFields = $customFields
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(
            function ($field) use ($canEdit, $canDelete) {

                $actions = '';



                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-custom-field"
                                        data-id=' . $field->id . '
                                        title="' . get_label('update', 'Update') . '">
                                        <i class="bx bx-edit mx-1"></i>
                                    </a>';
                }

                if ($canDelete) {
                    $actions .= '<button type="button"
                                        class="btn delete"
                                        data-id="' . $field->id . '"
                                        data-type="settings/custom-fields"
                                        title="' . get_label('delete', 'Delete') . '">
                                        <i class="bx bx-trash text-danger mx-1"></i>
                                    </button>';
                }

                return [
                    'id' => $field->id,
                    'module' => $field->module,
                    'field_label' => $field->field_label,
                    'field_type' => $field->field_type,
                    'required' => ($field->required == '1') ? 'Yes' : 'No',
                    'visibility' => ($field->visibility == '1') ? 'Yes' : 'No',
                    'actions' => $actions ?: '-'
                ];
            }
            );

        return response()->json([
            "rows" => $customFields,
            "total" => $total,
        ]);
    }


    /**
     * List custom fields or retrieve a single custom field.
     *
     * This endpoint retrieves a paginated list of custom fields or a single custom field by ID.
     * Supports optional search, sorting, pagination, and filtering by module, label, or field type.
     * User must be authenticated to access this endpoint.
     *
     * @authenticated
     *
     * @group Custom Field Management
     *
     * @urlParam id integer optional The ID of the custom field to retrieve. If provided, returns a single custom field. Example: 3
     *
     * @queryParam search string optional Filter custom fields by module, field label, or field type. Example: priority
     * @queryParam sort string optional Field to sort by. Default is id. Example: field_label
     * @queryParam order string optional Sort direction: ASC or DESC. Default is DESC. Example: ASC
     * @queryParam limit integer optional Number of custom fields to return per page. Default is 10. Example: 20
     * @queryParam offset integer optional The number of custom fields to skip. Default is 0. Example: 5
     *
     * @response 200 {
     *   "error": false,
     *   "message": "CustomFields retrieved successfully!",
     *   "data": {
     *     "total": 2,
     *     "data": [
     *       {
     *         "id": 3,
     *         "module": "task",
     *         "field_type": "select",
     *         "field_label": "Priority",
     *         "options": ["High", "Medium", "Low"],
     *         "required": "1",
     *         "visibility": "1"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "CustomField not found",
     *   "data": []
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed: The search field must be a string.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function apiList(Request $request, $id = null)
    {
        try {

            if ($id) {

                $customField = CustomField::find($id);

                if (!$customField) {
                    return formatApiResponse(
                        true, // this is error
                        'CustomField not found',
                        [],
                        404
                    );
                } else {

                    $data = formatCustomField($customField);

                    return formatApiResponse(
                        false,
                        'CustomField retrieved successfully!',
                        [
                            'data' => $data
                        ],
                        200
                    );
                }
            }

            $search = $request->input('search');
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset');
            $sort = $request->input('sort', 'id');
            $order = $request->input('order', 'DESC');

            $customFields = CustomField::orderBy($sort, $order);


            if ($search) {
                $customFields = $customFields->where(function ($query) use ($search) {
                    $query->where('module', 'like', '%' . $search . '%')
                        ->orWhere('field_label', 'like', '%' . $search . '%')
                        ->orWhere('field_type', 'like', '%' . $search . '%');
                });
            }

            $total = $customFields->count();


            $customFields = $customFields
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($field) {
                    return formatCustomField($field);
                });


            return formatApiResponse(
                false,
                'CustomFields retrieved successfully!',
                [
                    'total' => $total,
                    'data' => $customFields
                ],
                200
            );
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

        $validatedData = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:custom_fields,id'
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        foreach ($ids as $id) {
            $custom_field = CustomField::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $custom_field->field_label;
            DeletionService::delete(CustomField::class, $id, 'custom_field');
        }

        return response()->json(['error' => false, 'message' => 'Custom Field(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
}
