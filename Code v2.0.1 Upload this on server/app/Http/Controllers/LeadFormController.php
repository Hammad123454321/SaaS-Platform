<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Models\LeadForm;
use App\Models\LeadStage;
use App\Models\Workspace;
use App\Models\LeadSource;
use Illuminate\Http\Request;
use App\Models\LeadFormField;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LeadFormController extends Controller
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
    public function index()
    {
        $forms = LeadForm::with(['creator', 'leadSource', 'leadStage', 'assignedUser'])
            // ->where('workspace_id', auth()->user()->workspace_id)
            ->latest()
            ->get();

        return view('lead_form.index', compact('forms'));
    }

    public function create()
    {
        $sources = LeadSource::where('workspace_id', auth()->user()->workspace_id)->get();
        $stages = LeadStage::where('workspace_id', auth()->user()->workspace_id)->get();
        // $users = User::all();
        $users = $this->workspace->users;

        return view('lead_form.create', compact('sources', 'stages', 'users'));
    }
    /**
     * Create a new lead form.
     *
     * This endpoint creates a new lead form with dynamic, mappable fields for capturing leads, mapping them to lead sources, stages, and assigned users within the current workspace.
     * The user must be authenticated and authorized to manage lead forms.
     *
     * @authenticated
     *
     * @group Lead Form Management
     *
     * @bodyParam title string required The title of the lead form. Max 255 characters. Example: Website Leads
     * @bodyParam description string optional The description of the lead form. Example: Capture leads from website visitors
     * @bodyParam source_id integer required The ID of the lead source. Must exist in lead_sources. Example: 2
     * @bodyParam stage_id integer required The ID of the lead stage. Must exist in lead_stages. Example: 2
     * @bodyParam assigned_to integer required The ID of the user to assign leads to. Must exist in users. Example: 11
     * @bodyParam fields array required An array of field definitions for the form.
     *
     * @bodyParam fields[].label string required The label for the field. Example: First Name
     * @bodyParam fields[].type string required The type of the field. Allowed: text, textarea, select, radio, checkbox, number, email, tel. Example: text
     * @bodyParam fields[].is_required boolean optional Whether the field is required. Example: true
     * @bodyParam fields[].is_mapped boolean optional Whether the field should be mapped to a CRM field. Example: true
     * @bodyParam fields[].name string required_if:fields[].is_mapped,true The CRM field name to map to if mapped. Example: first_name
     * @bodyParam fields[].options array optional Required if the type is select, radio, or checkbox. An array of options for the field. Example: ["Web Development", "SEO", "UI/UX Design"]
     * @bodyParam fields[].placeholder string optional Placeholder text for the field. Example: Enter your first name
     *
     * @bodyParam fields[0][label] string required Default required field in all forms. Example: First Name
     * @bodyParam fields[0][type] string required Example: text
     * @bodyParam fields[0][is_required] boolean required Example: true
     * @bodyParam fields[0][is_mapped] boolean required Example: true
     * @bodyParam fields[0][name] string required Example: first_name
     * @bodyParam fields[0][placeholder] string optional Example: Enter your first name
     *
     * @bodyParam fields[1][label] string required Default required field in all forms. Example: Last Name
     * @bodyParam fields[1][type] string required Example: text
     * @bodyParam fields[1][is_required] boolean required Example: true
     * @bodyParam fields[1][is_mapped] boolean required Example: true
     * @bodyParam fields[1][name] string required Example: last_name
     * @bodyParam fields[1][placeholder] string optional Example: Enter your last name
     *
     * @bodyParam fields[2][label] string required Default required field in all forms. Example: Email
     * @bodyParam fields[2][type] string required Example: text
     * @bodyParam fields[2][is_required] boolean required Example: true
     * @bodyParam fields[2][is_mapped] boolean required Example: true
     * @bodyParam fields[2][name] string required Example: email
     * @bodyParam fields[2][placeholder] string optional Example: Enter your email
     *
     * @bodyParam fields[3][label] string required Default required field in all forms. Example: Phone
     * @bodyParam fields[3][type] string required Example: number
     * @bodyParam fields[3][is_required] boolean required Example: true
     * @bodyParam fields[3][is_mapped] boolean required Example: true
     * @bodyParam fields[3][name] string required Example: phone
     * @bodyParam fields[3][placeholder] string optional Example: Enter your phone number
     *
     * @bodyParam fields[4][label] string required Default required field in all forms. Example: Company
     * @bodyParam fields[4][type] string required Example: text
     * @bodyParam fields[4][is_required] boolean required Example: true
     * @bodyParam fields[4][is_mapped] boolean required Example: true
     * @bodyParam fields[4][name] string required Example: company
     * @bodyParam fields[4][placeholder] string optional Example: Enter your company name
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead form created successfully!",
     *   "form": {
     *     "id": 12,
     *     "title": "Website Leads",
     *     "description": "Capture leads from website visitors",
     *     "created_by": 1,
     *     "workspace_id": 1,
     *     "source_id": 2,
     *     "stage_id": 2,
     *     "assigned_to": 11,
     *     "lead_form_fields": [
     *       {
     *         "id": 45,
     *         "label": "First Name",
     *         "name": "first_name",
     *         "type": "text",
     *         "is_required": true,
     *         "is_mapped": true,
     *         "options": null,
     *         "order": 1
     *       },
     *       {
     *         "id": 46,
     *         "label": "Last Name",
     *         "name": "last_name",
     *         "type": "text",
     *         "is_required": true,
     *         "is_mapped": true,
     *         "options": null,
     *         "order": 2
     *       },
     *       {
     *         "id": 47,
     *         "label": "Email",
     *         "name": "email",
     *         "type": "text",
     *         "is_required": true,
     *         "is_mapped": true,
     *         "options": null,
     *         "order": 3
     *       },
     *       {
     *         "id": 48,
     *         "label": "Phone",
     *         "name": "phone",
     *         "type": "number",
     *         "is_required": true,
     *         "is_mapped": true,
     *         "options": null,
     *         "order": 4
     *       },
     *       {
     *         "id": 49,
     *         "label": "Company",
     *         "name": "company",
     *         "type": "text",
     *         "is_required": true,
     *         "is_mapped": true,
     *         "options": null,
     *         "order": 5
     *       },
     *       {
     *         "id": 50,
     *         "label": "Interested Service",
     *         "name": null,
     *         "type": "select",
     *         "is_required": true,
     *         "is_mapped": false,
     *         "options": ["Web Development", "SEO", "UI/UX Design"],
     *         "order": 6
     *       }
     *     ]
     *   },
     *   "public_url": "https://yourapp.com/forms/website-leads",
     *   "embed_code": "<iframe src='https://yourapp.com/forms/embed/website-leads'></iframe>"
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "title": ["Form title is required."],
     *     "fields.0.label": ["Field label is required."],
     *     "fields.0.type": ["Field type is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Failed to create form: An unexpected error occurred."
     * }
     */

    public function store(Request $request)
    {
        $isApi = $request->get('isApi', false);

        // Clean up empty fields before validation
        $cleanedFields = [];
        if ($request->has('fields')) {
            foreach ($request->fields as $index => $field) {
                // Only include fields that have at least a label or type
                if (!empty($field['label']) || !empty($field['type'])) {
                    $cleanedFields[$index] = $field;
                }
            }
        }

        // Replace the fields in the request
        $request->merge(['fields' => $cleanedFields]);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source_id' => 'required|exists:lead_sources,id',
            'stage_id' => 'required|exists:lead_stages,id',
            'assigned_to' => 'required|exists:users,id',
            'redirect_url' => 'nullable|url',
            'fields' => 'required|array|min:1',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.type' => 'required|in:' . implode(',', array_keys(LeadFormField::FIELD_TYPES)),
            'fields.*.is_required' => 'boolean',
            'fields.*.is_mapped' => 'boolean',
            'fields.*.name' => 'required_if:fields.*.is_mapped,true|nullable|in:' . implode(',', array_keys(LeadFormField::MAPPABLE_FIELDS)),
            'fields.*.options' => 'nullable|array',
            'fields.*.placeholder' => 'nullable|string',
        ], [
            'fields.required' => 'At least one field is required.',
            'fields.min' => 'At least one field is required.',
            'fields.*.label.required' => 'Field label is required.',
            'fields.*.label.max' => 'Field label cannot exceed 255 characters.',
            'fields.*.type.required' => 'Field type is required.',
            'fields.*.type.in' => 'Invalid field type selected.',
            'fields.*.name.required_if' => 'Field mapping is required when field is marked as mapped.',
            'fields.*.name.in' => 'Invalid field mapping selected.',
            'fields.*.options.array' => 'Field options must be an array.',
            'fields.*.options.*.string' => 'Each option must be text.',
            'fields.*.options.*.max' => 'Each option cannot exceed 255 characters.',
            'fields.*.placeholder.max' => 'Placeholder cannot exceed 255 characters.',
            'title.required' => 'Form title is required.',
            'title.max' => 'Form title cannot exceed 255 characters.',
            'description.max' => 'Form description cannot exceed 1000 characters.',
            'source_id.required' => 'Lead source is required.',
            'source_id.exists' => 'Selected lead source does not exist.',
            'stage_id.required' => 'Lead stage is required.',
            'stage_id.exists' => 'Selected lead stage does not exist.',
            'assigned_to.required' => 'Assigned user is required.',
            'assigned_to.exists' => 'Selected user does not exist.',

            'redirect_url.url' => 'Redirect URL must be a valid URL.',
            'redirect_url.max' => 'Redirect URL cannot exceed 2048 characters.',
        ]);

        // Custom validation for select/radio/checkbox fields
        $validator->after(function ($validator) use ($request) {
            if ($request->has('fields')) {
                foreach ($request->fields as $index => $field) {
                    if (in_array($field['type'] ?? '', ['select', 'radio', 'checkbox'])) {
                        if (empty($field['options']) || !is_array($field['options']) || count(array_filter($field['options'])) === 0) {
                            $validator->errors()->add(
                                "fields.{$index}.options",
                                "At least one option is required for {$field['type']} fields."
                            );
                        }
                    }
                }
            }
        });

        if ($validator->fails()) {
            if ($isApi) {
                return formatApiValidationError($isApi, $validator->errors());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $form = LeadForm::create([
                'title' => $request->title,
                'description' => $request->description,
                'created_by' => getAuthenticatedUser()->id,
                'workspace_id' => 1,
                'source_id' => $request->source_id,
                'stage_id' => $request->stage_id,
                'assigned_to' => $request->assigned_to,
            ]);

            // Validate required fields if you have this method
            if (method_exists($this, 'validateRequiredFields')) {
                $this->validateRequiredFields($request->fields);
            }

            // Create form fields with proper ordering
            $order = 1;
            foreach ($request->fields as $index => $fieldData) {
                // Clean options array - remove empty values
                $options = null;
                if (!empty($fieldData['options']) && is_array($fieldData['options'])) {
                    $cleanOptions = array_filter($fieldData['options'], function ($option) {
                        return !empty(trim($option));
                    });
                    if (!empty($cleanOptions)) {
                        $options = json_encode(array_values($cleanOptions));
                    }
                }

                LeadFormField::create([
                    'form_id' => $form->id,
                    'label' => $fieldData['label'],
                    'name' => $fieldData['is_mapped'] ? ($fieldData['name'] ?? null) : null,
                    'type' => $fieldData['type'],
                    'is_required' => $fieldData['is_required'] ?? false,
                    'is_mapped' => $fieldData['is_mapped'] ?? false,
                    'options' => $options,
                    'placeholder' => $fieldData['placeholder'] ?? null,
                    'order' => $order++,
                    'validation_rules' => method_exists($this, 'generateValidationRules')
                        ? $this->generateValidationRules($fieldData)
                        : null,
                ]);
            }

            DB::commit();
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Lead form created successfully',
                    [
                        'data' => [
                            'id' => $form->id,
                            'form' => formatLeadForm($form),

                        ]
                    ]
                );
            } else {

                return response()->json([
                    'error' => false,
                    'message' => 'Lead form created successfully!',
                    'form' => $form->load('leadFormFields'),
                    'public_url' => $form->public_url ?? null,
                    'embed_code' => $form->embed_code ?? null
                ]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Lead form creation failed: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e
            ]);
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Failed to create form.',
                    [
                        'data' => [
                            'error' => $e->getMessage(),
                            'line' => $e->getLine(),
                            'file' => $e->getFile()
                        ]
                    ]
                );
            } else {

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create form: ' . $e->getMessage()
                ], 500);
            }
        }
    }

    public function show(LeadForm $leadForm)
    {
        $form =  $leadForm->load(['leadFormFields' => function ($query) {
            $query->orderBy('order');
        }, 'leadSource', 'leadStage', 'assignedUser']);

        return view('lead_form.public_form', compact('form'));
    }

    public function edit($id)
    {

        $leadForm = LeadForm::with(['leadFormFields' => function ($query) {
            $query->orderBy('order');
        }])->findOrFail($id);


        $sources = LeadSource::where('workspace_id', auth()->user()->workspace_id)->get();
        $stages = LeadStage::where('workspace_id', auth()->user()->workspace_id)->get();
        $users = User::all();


        return view('lead_form.edit', compact('leadForm', 'sources', 'stages', 'users'));
    }
    /**
     * Update an existing lead form.
     *
     * This endpoint updates an existing lead form, including its title, description, source, stage, assigned user, and associated fields.
     * The user must be authenticated and authorized to manage lead forms.
     *
     * @authenticated
     *
     * @group Lead Form Management
     *
     * @urlParam id integer required The ID of the lead form to update. Example: 5
     *
     * @bodyParam title string required The title of the lead form. Max 255 characters. Example: Website Leads
     * @bodyParam description string optional The description of the lead form. Example: Capture leads from website visitors
     * @bodyParam source_id integer required The ID of the lead source. Must exist in lead_sources. Example: 2
     * @bodyParam stage_id integer required The ID of the lead stage. Must exist in lead_stages. Example: 2
     * @bodyParam assigned_to integer required The ID of the user to assign leads to. Must exist in users. Example: 11
     * @bodyParam fields array required An array of at least 5 field objects for the form.
     *
     * @bodyParam fields[].label string required The label of the field. Max 255 characters. Example: First Name
     * @bodyParam fields[].type string required The type of the field. Allowed values: text, textarea, select, checkbox, radio, email, number, date, etc. Example: text
     * @bodyParam fields[].is_required boolean optional Whether the field is required. Example: true
     * @bodyParam fields[].is_mapped boolean optional Whether the field is mapped to a lead attribute. Example: true
     * @bodyParam fields[].name string required_if:fields[].is_mapped,true The mapped lead attribute if is_mapped is true. Allowed values: first_name, last_name, email, phone, company, etc. Example: first_name
     * @bodyParam fields[].options array nullable Options for select, checkbox, or radio fields. Example: ["Web Development", "SEO", "UI/UX Design"]
     * @bodyParam fields[].placeholder string optional Placeholder text for the field. Example: Enter your first name
     *
     * @bodyParam fields[0][label] string required Default required field in all forms. Example: First Name
     * @bodyParam fields[0][type] string required Example: text
     * @bodyParam fields[0][is_required] boolean required Example: true
     * @bodyParam fields[0][is_mapped] boolean required Example: true
     * @bodyParam fields[0][name] string required Example: first_name
     * @bodyParam fields[0][placeholder] string optional Example: Enter your first name
     *
     * @bodyParam fields[1][label] string required Default required field in all forms. Example: Last Name
     * @bodyParam fields[1][type] string required Example: text
     * @bodyParam fields[1][is_required] boolean required Example: true
     * @bodyParam fields[1][is_mapped] boolean required Example: true
     * @bodyParam fields[1][name] string required Example: last_name
     * @bodyParam fields[1][placeholder] string optional Example: Enter your last name
     *
     * @bodyParam fields[2][label] string required Default required field in all forms. Example: Email
     * @bodyParam fields[2][type] string required Example: text
     * @bodyParam fields[2][is_required] boolean required Example: true
     * @bodyParam fields[2][is_mapped] boolean required Example: true
     * @bodyParam fields[2][name] string required Example: email
     * @bodyParam fields[2][placeholder] string optional Example: Enter your email
     *
     * @bodyParam fields[3][label] string required Default required field in all forms. Example: Phone
     * @bodyParam fields[3][type] string required Example: number
     * @bodyParam fields[3][is_required] boolean required Example: true
     * @bodyParam fields[3][is_mapped] boolean required Example: true
     * @bodyParam fields[3][name] string required Example: phone
     * @bodyParam fields[3][placeholder] string optional Example: Enter your phone number
     *
     * @bodyParam fields[4][label] string required Default required field in all forms. Example: Company
     * @bodyParam fields[4][type] string required Example: text
     * @bodyParam fields[4][is_required] boolean required Example: true
     * @bodyParam fields[4][is_mapped] boolean required Example: true
     * @bodyParam fields[4][name] string required Example: company
     * @bodyParam fields[4][placeholder] string optional Example: Enter your company name
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead form updated successfully",
     *   "data": {
     *     "id": 5,
     *     "form": {
     *       "id": 5,
     *       "title": "Website Leads",
     *       "description": "Capture leads from website visitors",
     *       "source": "Website",
     *       "stage": "New",
     *       "assigned_to": {
     *         "id": 11,
     *         "first_name": "Dimpal",
     *         "last_name": "Shah",
     *         "email": "dimpal@example.com"
     *       },
     *       "is_active": true,
     *       "fields": [
     *         {
     *           "label": "First Name",
     *           "type": "text",
     *           "is_required": true,
     *           "is_mapped": true,
     *           "name": "first_name",
     *           "options": null,
     *           "placeholder": "Enter your first name"
     *         },
     *         {
     *           "label": "Interested Service",
     *           "type": "select",
     *           "is_required": true,
     *           "is_mapped": false,
     *           "options": ["Web Development", "SEO", "UI/UX Design"],
     *           "placeholder": null
     *         }
     *       ],
     *       "created_at": "2025-07-21T12:00:00.000000Z",
     *       "updated_at": "2025-07-21T13:00:00.000000Z"
     *     }
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed",
     *   "errors": {
     *     "title": ["The title field is required."],
     *     "fields.0.label": ["The field label is required."],
     *     "fields.0.type": ["The field type is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Failed to update form",
     *   "data": {
     *     "error": "SQLSTATE[HY000]: General error: ...",
     *     "line": 120
     *   }
     * }
     */

    public function update(Request $request, $id)
    {
        $isApi = $request->get('isApi', false);
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source_id' => 'required|exists:lead_sources,id',
            'stage_id' => 'required|exists:lead_stages,id',
            'assigned_to' => 'required|exists:users,id',
            'fields' => 'required|array|min:5',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.type' => 'required|in:' . implode(',', array_keys(LeadFormField::FIELD_TYPES)),
            'fields.*.is_required' => 'boolean',
            'fields.*.is_mapped' => 'boolean',
            'fields.*.name' => 'required_if:fields.*.is_mapped,true|nullable|in:' . implode(',', array_keys(LeadFormField::MAPPABLE_FIELDS)),
            'fields.*.options' => 'nullable|array',
            'fields.*.placeholder' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {

            $leadForm = LeadForm::findOrFail($id);
            $leadForm->update([
                'title' => $request->title,
                'description' => $request->description,
                'source_id' => $request->source_id,
                'stage_id' => $request->stage_id,
                'assigned_to' => $request->assigned_to,
            ]);

            $this->validateRequiredFields($request->fields);

            $leadForm->leadFormFields()->delete();

            foreach ($request->fields as $index => $fieldData) {

                LeadFormField::create([
                    'form_id' => $leadForm->id,
                    'label' => $fieldData['label'],
                    'name' => $fieldData['is_mapped'] ? ($fieldData['name'] ?? null) : null,
                    'type' => $fieldData['type'],
                    'is_required' => $fieldData['is_required'] ?? false,
                    'is_mapped' => $fieldData['is_mapped'] ?? false,
                    'options' => !empty($fieldData['options']) ? json_encode($fieldData['options']) : null,
                    'placeholder' => $fieldData['placeholder'] ?? null,
                    'order' => $index + 1,
                    'validation_rules' => $this->generateValidationRules($fieldData),
                ]);
            }

            DB::commit();
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Lead Form updated successfully',
                    [
                        'data' => [
                            'id' => $leadForm->id,
                            'form' => formatLeadForm($leadForm)
                        ]
                    ]
                );
            } else {

                return response()->json([
                    'success' => true,
                    'message' => 'Lead form updated successfully!'
                ]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'Failed to update form',
                    [
                        'data' => [
                            'error' => $e->getMessage(),
                            'line' => $e->getLine(),
                        ]
                    ]
                );
            } else {

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update form: ' . $e->getMessage()
                ], 500);
            }
        }
    }
    /**
     * Delete a Lead Form.
     *
     * This endpoint allows authenticated users to delete a specific status. Before deletion,
     * all associated projects and tasks will be updated to have a default status ID of `0`.
     *
     * @authenticated
     *
     * @group Lead Form Management
     *
     * @urlParam id int required The ID of the status to delete.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead Form deleted successfully.",
     *   "id": 101,
     *
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead Form not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Lead Form couldn't be deleted."
     * }
     */

    public function destroy($id)
    {
        $leadForm = LeadForm::findOrFail($id);

        $response = DeletionService::delete(LeadForm::class, $leadForm->id, 'Lead Form');

        return $response;
    }

    public function destroy_multiple(Request $request)
    {

        $validatedData = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:lead_forms,id'
        ]);



        $ids = $validatedData['ids'];
        $deletedIds = [];

        foreach ($ids as $id) {
            $candidate = LeadForm::findOrFail($id);
            $deletedIds[] = $id;

            DeletionService::delete(LeadForm::class, $candidate->id, 'Candidate');
        }

        return response()->json([
            'error' => false,
            'message' => 'Lead Form(s) Deleted Successfully!',
            'id' => $deletedIds,
        ]);
    }

    public function list()
    {
        $search = request()->input('search');
        $limit = request()->input('limit', 10);
        $offset = request()->input('offset', 0);
        $sort = request()->input('sort', 'id');
        $order = request()->input('order', 'DESC');

        $query = LeadForm::with(['leadSource', 'leadStage', 'assignedUser', 'creator']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $total = $query->count();
        $canEdit = isAdminOrHasAllDataAccess() || checkPermission('manage_leads');
        $canDelete = isAdminOrHasAllDataAccess();

        $leadForms = $query->orderBy($sort, $order)
            ->take($limit)
            ->skip($offset)
            ->get()
            ->map(function ($leadForm) use ($canDelete, $canEdit) {
                $actions = '';

            if ($leadForm->leadStage) {
                $stage = '<span class="badge bg-' . $leadForm->leadStage->color . '">' . $leadForm->leadStage->name . '</span>';
            } else {
                $stage = "-";
            }

            if ($canEdit) {
                $actions .= '
                    <a href="' . route('lead-forms.edit', $leadForm->id) . '"
                       class="mx-1"
                       title="' . get_label('update', 'Update') . '">
                        <i class="bx bx-edit text-primary"></i>
                    </a>
                    <a href="' . route('lead-forms.embed', $leadForm->id) . '"
                       class="mx-1"
                       title="' . get_label('embed_code', 'Embed Code') . '">
                        <i class="bx bx-code-alt text-info"></i>
                    </a>';
            }

                if ($canDelete) {
                $actions .= '
                    <a href="javascript:void(0);"
                       class="delete"
                       data-id="' . $leadForm->id . '"
                       data-type="lead-forms"
                       title="' . get_label('delete', 'Delete') . '">
                        <i class="bx bx-trash mx-1 text-danger"></i>
                    </a>';
                }

            $responses = '<div class="text-center">
                <a href="' . route('lead-forms.responses', $leadForm->id) . '"
                   class="get-embed-code-btn"
                   title="' . $leadForm->leads_count . ' ' . get_label('responses', 'Responses') . '">
                    <i class="bx bx-message-dots fs-5 text-success"></i>
                </a>
            </div>';

            return [
                    'id' => $leadForm->id,
                    'title' => $leadForm->title,
                'description' => $leadForm->description ? (strlen($leadForm->description) > 50 ? substr($leadForm->description, 0, 50) . '...' : $leadForm->description) : '-',
                    'source' => $leadForm->leadSource->name ?? '-',
                    'stage' => $leadForm->leadStage->name ?? '-',
                'stage' => $stage ?? '-',
                // 'assigned_to' => $leadForm->assignedUser ? ($leadForm->assignedUser->first_name . ' ' . $leadForm->assignedUser->last_name) : '-',
                'assigned_to' =>  formatUserHtml($leadForm->assignedUser) ?? 'N/A',
                'public_url' => '<a href="' . $leadForm->public_url . '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bx bx-link-external"></i> View</a>',
                'responses' => $responses,
                    'created_at' => $leadForm->created_at->format('Y-m-d'),
                'updated_at' => $leadForm->updated_at->format('Y-m-d'),
                    'actions' => $actions
                ];
            });

        return response()->json([
            'rows' => $leadForms,
            'total' => $total
        ]);
    }

    private function validateRequiredFields($fields)
    {
        $requiredFieldNames = LeadFormField::REQUIRED_FIELDS;
        $providedMappedFields = collect($fields)
            ->where('is_mapped', true)
            ->pluck('name')
            ->toArray();

        $missingFields = array_diff($requiredFieldNames, $providedMappedFields);

        if (!empty($missingFields)) {
            throw new \Exception('Missing required fields: ' . implode(', ', $missingFields));
        }
    }

    private function generateValidationRules($fieldData)
    {
        $rules = [];
        if ($fieldData['is_required'] ?? false) {
            $rules[] = 'required';
        }

        switch ($fieldData['type']) {
            case 'email':
                $rules[] = 'email';
                break;
            case 'tel':
                $rules[] = 'regex:/^[\+]?[1-9][\d]{0,15}$/';
                break;
            case 'url':
                $rules[] = 'url';
                break;
            case 'number':
                $rules[] = 'numeric';
                break;
            case 'date':
                $rules[] = 'date';
                break;
        }

        return implode('|', $rules);
    }
    /**
     * Toggle lead form active/inactive status.
     *
     * This endpoint toggles the `is_active` status of a lead form between active (1) and inactive (0).
     * The user must be authenticated and authorized to manage lead forms.
     *
     * @authenticated
     *
     * @group Lead Form Management
     *
     * @urlParam lead_form integer required The ID of the lead form to toggle status for. Example: 12
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Form status updated successfully!",
     *   "status": true
     * }
     *
     * @response 404 {
     *   "error": false,
     *   "message": "Lead form not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the lead form status."
     * }
     */

    public function toggleStatus(LeadForm $leadForm)
    {
        $leadForm->update(['is_active' => !$leadForm->is_active]);
        return response()->json([
            'success' => true,
            'message' => 'Form status updated successfully!',
            'status' => $leadForm->is_active
        ]);
    }

    public function embed(LeadForm $leadForm)
    {
        return view('lead_form.embed', compact('leadForm'));
    }

    public function responses(Request $request, $id)
    {
        $leadForm = LeadForm::findOrFail($id);

        return view('lead_form.responses', compact('leadForm'));
    }


    public function responseList(Request $request, $id)
    {
        $leadForm = LeadForm::findOrFail($id);

        $search = $request->input('search');
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');

        $query = $leadForm->leads(); // using the relationship

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('company', 'like', "%$search%");
            });
        }

        $total = $query->count();

        $leads = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->first_name . ' ' . $lead->last_name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'company' => $lead->company ?? '-',
                    'submitted_at' => format_date($lead->created_at, to_format: "Y-m-d"),
                    'actions' => '<a href="' . route('leads.show', $lead->id) . '" class="btn btn-sm btn-outline-primary">View</a>',
                ];
            });

        return response()->json([
            'total' => $total,
            'rows' => $leads,
        ]);
    }

    /**
     * List lead forms with optional filters, sorting, and pagination.
     *
     * This endpoint retrieves a paginated list of lead forms or a specific lead form by ID, with optional search, sorting, and pagination parameters. The response includes permission details for editing and deletion. The user must be authenticated and authorized to manage lead forms.
     *
     * @authenticated
     *
     * @group Lead Form Management
     *
     * @queryParam id integer optional The ID of a specific lead form to retrieve. Example: 1
     * @queryParam search string optional Filters lead forms by title or description. Example: Website
     * @queryParam sort string optional The column to sort by (id, title, created_at, updated_at). Defaults to id. Example: created_at
     * @queryParam order string optional The sort order (ASC, DESC). Defaults to DESC. Example: ASC
     * @queryParam limit integer optional Number of lead forms per page (1-100). Defaults to 10. Example: 20
     * @queryParam offset integer optional Number of lead forms to skip. Defaults to 0. Example: 10
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead forms retrieved successfully.",
     *   "total": 5,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Website Lead Form",
     *       "description": "Lead form for website captures.",
     *       "source": { "id": 2, "name": "Website" },
     *       "stage": { "id": 3, "name": "New", "color": "primary" },
     *       "assigned_to": { "id": 5, "first_name": "John", "last_name": "Doe", "email": "john@example.com", "photo": "..." },
     *       "fields": [...],
     *       "public_url": "https://...",
     *       "embed_code": "<iframe ...>",
     *       "leads_count": 15,
     *       "created_at": "2025-07-21 10:15:00",
     *       "updated_at": "2025-07-21 10:15:00",
     *       "sent_time": "2 hours ago"
     *     }
     *   ],
     *   "permissions": {
     *     "can_edit": true,
     *     "can_delete": true
     *   }
     * }
     *
     * @response 404 {
     *   "error": false,
     *   "message": "Lead form(s) not found.",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while retrieving the lead forms."
     * }
     */
    public function apiList(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);
            $id = $request->input('id', null);
            $search = $request->input('search');
            $sort = $request->input('sort', 'id');
            $order = $request->input('order', 'DESC');

            $leadFormsQuery = LeadForm::with(['leadSource', 'leadStage', 'assignedUser', 'leadFormFields']);

            if ($search) {
                $leadFormsQuery->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            $total = $leadFormsQuery->count();

            if ($id) {
                $leadForm = $leadFormsQuery->find($id);

                if (!$leadForm) {
                    return formatApiResponse(
                        false,
                        'Lead form not found.',
                        [
                            'total' => 0,
                            'data' => []
                        ],
                        404
                    );
                }

                return formatApiResponse(
                    false,
                    'Lead form retrieved successfully.',
                    [
                        'total' => 1,
                        'data' => formatLeadForm($leadForm)
                    ],
                    200
                );
            } else {
                $leadForms = $leadFormsQuery->orderBy($sort, $order)
                    ->skip($offset)
                    ->take($limit)
                    ->get();

                if ($leadForms->isEmpty()) {
                    return formatApiResponse(
                        false,
                        'Lead forms not found.',
                        [
                            'total' => 0,
                            'data' => []
                        ],
                        404
                    );
                }

                $data = $leadForms->map(fn($leadForm) => formatLeadForm($leadForm));

                return formatApiResponse(
                    false,
                    'Lead forms retrieved successfully.',
                    [
                        'total' => $total,
                        'data' => $data,
                        'permissions' => [
                            'can_edit' => checkPermission('manage_leads'),
                            'can_delete' => checkPermission('manage_leads'),
                        ],
                    ],
                    200
                );
            }
        } catch (\Exception $e) {
            Log::error('Lead Forms API List Error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return formatApiResponse(
                true,
                'An error occurred while retrieving the lead forms.',
                [],
                500
            );
        }
    }

    /**
     * List lead form responses with optional filters, sorting, and pagination.
     *
     * This endpoint retrieves a paginated list of responses (leads) for a specific lead form, with optional search, sorting, and pagination parameters.
     * The user must be authenticated and authorized to view lead form responses.
     *
     * @authenticated
     *
     * @group Lead Form Management
     *
     * @urlParam id integer required The ID of the lead form for which to retrieve responses. Example: 1
     * @queryParam search string optional Filters responses by first name, last name, email, or company. Example: John
     * @queryParam sort string optional The column to sort by (id, first_name, last_name, email, created_at). Defaults to id. Example: created_at
     * @queryParam order string optional The sort order (ASC, DESC). Defaults to DESC. Example: ASC
     * @queryParam limit integer optional Number of responses per page (1-100). Defaults to 10. Example: 20
     * @queryParam offset integer optional Number of responses to skip. Defaults to 0. Example: 10
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead form responses retrieved successfully.",
     *   "total": 2,
     *   "data": [
     *     {
     *       "id": 5,
     *       "name": "John Doe",
     *       "email": "john@example.com",
     *       "phone": "9876543210",
     *       "company": "Example Corp",
     *       "submitted_at": "2025-07-21",
     *       "sent_time": "2 hours ago"
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "error": false,
     *   "message": "Lead form responses not found.",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while retrieving the lead form responses."
     * }
     */
    public function apiResponseList(Request $request, $id)
    {
        try {
            $leadForm = LeadForm::findOrFail($id);

            $search = $request->input('search');
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);
            $sort = $request->input('sort', 'id');
            $order = $request->input('order', 'DESC');

            $query = $leadForm->leads();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%$search%")
                        ->orWhere('last_name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('company', 'like', "%$search%");
                });
            }

            $total = $query->count();

            $leads = $query->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get();

            if ($leads->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Lead form responses not found.',
                    [
                        'total' => 0,
                        'data' => []
                    ],
                    404
                );
            }

            $data = $leads->map(function ($lead) {
                return formatLeadFormResponse($lead);
            });

            return formatApiResponse(
                false,
                'Lead form responses retrieved successfully.',
                [
                    'total' => $total,
                    'data' => $data
                ],
                200
            );
        } catch (\Exception $e) {
            Log::error('Lead Form API Response List Error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return formatApiResponse(
                true,
                'An error occurred while retrieving the lead form responses.',
                [],
                500
            );
        }
    }
}
