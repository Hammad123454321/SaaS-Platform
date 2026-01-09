<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use App\Services\DeletionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EmailTemplateController extends Controller
{
    /**
     * Display a listing of all email templates.
     */
    public function index()
    {
        $templates = EmailTemplate::all();
        return view('email-templates.index', compact('templates'));
    }

    /**
     * Extract dynamic placeholders from email body.
     * Excludes system-wide constants like COMPANY_LOGO, SUBJECT, etc.
     */
    private function extractPlaceholders($body)
    {
        preg_match_all('/\{(.*?)\}/', $body, $matches);
        $placeholders = array_unique($matches[0]);

        $exclude = ['{COMPANY_LOGO}', '{SUBJECT}', '{CURRENT_YEAR}', '{COMPANY_TITLE}'];

        return array_values(array_filter($placeholders, function ($ph) use ($exclude) {
            return !in_array($ph, $exclude);
        }));
    }


    // Method: store
    /**
     * Create a new email template.
     *
     * This endpoint creates a new email template with a specified name, subject, and body. The user must be authenticated to perform this action. The body can be base64-encoded if specified. Placeholders are automatically extracted from the body, excluding system constants like COMPANY_LOGO.
     *
     * @authenticated
     *
     * @group Email Template Management
     *
     * @bodyParam name string required The name of the email template. Maximum length is 255 characters. Example: Welcome Template
     * @bodyParam subject string required The subject of the email template. Maximum length is 255 characters. Example: Welcome to Our Company
     * @bodyParam body string nullable The body of the email template, optionally base64-encoded. Example: <p>Hello USER_NAME!</p>
     * @bodyParam is_encoded string optional Indicates if the body is base64-encoded (value: '1'). Defaults to false. Example: 1
     * @bodyParam content string optional The base64-encoded body content, used if is_encoded is '1'. Example: PGh0bWw+PHA+SGVsbG8ge1VTRVJfTkFNRX0hPC9wPjwvaHRtbD4=
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 201 {
     *   "error": false,
     *   "message": "Email Template Created Successfully!",
     *   "data": {
     *     "id": 1,
     *     "name": "Welcome Template",
     *     "subject": "Welcome to Our Company",
     *     "body": "<p>Hello USER_NAME!</p>",
     *     "placeholders": ["USER_NAME"],
     *     "workspace_id": 1,
     *     "created_at": "2025-05-15 17:00:00",
     *     "updated_at": "2025-05-15 17:00:00"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The name field is required."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Failed to create email template",
     *   "error": "Detailed error message",
     *   "code": 0,
     *   "file": "path/to/file.php",
     *   "line": 123,
     *   "trace": "Stack trace"
     * }
     */

    public function store(Request $request)
    {
        $isApi = $request->get('isApi', false);
        if ($request->has('is_encoded') && $request->is_encoded == '1') {
            $decodedContent = base64_decode($request->content);
            $request->merge(['body' => $decodedContent]);
        }
        try {

            $rule = $request->validate([
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
                'body' => 'nullable'
            ]);
            $rule['workspace_id'] = getWorkspaceId();
            $rule['placeholders'] = $this->extractPlaceholders($rule['body']);

            $email_templates = EmailTemplate::create($rule);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Email Template Created Successfully!',
                    [
                        'data' => formatEmailTemplate($email_templates)
                    ]
                );
            } else {
                return response()->json([
                    'error' => false,
                    'message' => 'Email Template Created Successfully!',
                    'email_templates' => $email_templates
                ]);
            }
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                'Failed to create email template',
                [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ],
                500);
        }
    }


    // Method: update
    /**
     * Update an existing email template.
     *
     * This endpoint updates the name, subject, and body of an existing email template. The user must be authenticated to perform this action. The body can be base64-encoded if specified. Placeholders are automatically extracted from the body, excluding system constants like COMPANY_LOGO.
     *
     * @authenticated
     *
     * @group Email Template Management
     *
     * @urlParam id integer required The ID of the email template to update. Must exist in the `email_templates` table. Example: 1
     * @bodyParam name string required The name of the email template. Maximum length is 255 characters. Example: Welcome Template
     * @bodyParam subject string required The subject of the email template. Maximum length is 255 characters. Example: Welcome to Our Company
     * @bodyParam body string required The body of the email template, optionally base64-encoded. Example: <p>Hello USER_NAME!</p>
     * @bodyParam is_encoded string optional Indicates if the body is base64-encoded (value: '1'). Defaults to false. Example: 1
     * @bodyParam content string optional The base64-encoded body content, used if is_encoded is '1'. Example: PGh0bWw+PHA+SGVsbG8ge1VTRVJfTkFNRX0hPC9wPjwvaHRtbD4=
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Email Template Updated Successfully!",
     *   "data": {
     *     "id": 1,
     *     "name": "Welcome Template",
     *     "subject": "Welcome to Our Company",
     *     "body": "<p>Hello USER_NAME!</p>",
     *     "placeholders": ["USER_NAME"],
     *     "workspace_id": 1,
     *     "created_at": "2025-05-15 17:00:00",
     *     "updated_at": "2025-05-15 17:05:00"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Email template not found"
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The name field is required."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Something went wrong."
     * }
     */


    public function update(Request $request, $id)
    {
        $isApi = $request->get('isApi', false);
        if ($request->has('is_encoded') && $request->is_encoded == '1') {
            $decodedContent = base64_decode($request->content);
            $request->merge(['body' => $decodedContent]);
        }
        try {

            $rule = $request->validate([
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
                'body' => 'required'
            ]);

            $rule['placeholders'] = $this->extractPlaceholders($rule['body']);

            $email_templates = EmailTemplate::findOrFail($id);
            $email_templates->update($rule);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Email Template Created Successfully!',
                    [
                        'data' => formatEmailTemplate($email_templates)
                    ]
                );
            } else {
                return response()->json([
                    'error' => false,
                    'message' => 'Email Template Updated Successfully!',
                    'email_templates' => $email_templates
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update email template', ['error' => $e->getMessage()]);
            return Response::json(['error' => true, 'message' => 'Something went wrong.'], 500);
        }
    }



    // Method: destroy
    /**
     * Delete an email template.
     *
     * This endpoint deletes a specific email template. The user must be authenticated and have appropriate permissions to perform this action.
     *
     * @authenticated
     *
     * @group Email Template Management
     *
     * @urlParam id integer required The ID of the email template to delete. Must exist in the `email_templates` table. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Email Template deleted successfully!"
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Email template not found"
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Something went wrong."
     * }
     */


    public function destroy(Request $request, $id)
    {
        try {
            $template = EmailTemplate::findOrFail($id);
            $response = DeletionService::delete(EmailTemplate::class, $template->id, 'Email Template');

            // Check if the request expects a JSON response (API)
            if ($request->expectsJson()) {
                return $response;
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete email template', ['error' => $e->getMessage()]);
            return Response::json(['error' => true, 'message' => 'Something went wrong.'], 500);
        }
    }

    /**
     * Delete multiple selected email templates.
     */
    public function destroy_multiple(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:email_templates,id',
            ]);

            $deletedIds = [];

            foreach ($validatedData['ids'] as $id) {
                $template = EmailTemplate::findOrFail($id);
                DeletionService::delete(EmailTemplate::class, $template->id, 'Email Template');
                $deletedIds[] = $id;
            }

            return response()->json([
                'error' => false,
                'message' => 'Email Template(s) deleted successfully.',
                'id' => $deletedIds
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete multiple email templates', ['error' => $e->getMessage()]);
            return Response::json(['error' => true, 'message' => 'Something went wrong.'], 500);
        }
    }

    /**
     * Return paginated, searchable list of email templates.
     */
    public function list()
    {
        try {
            $search = request('search');
            $sort = request('sort', 'id');
            $order = request('order', 'DESC');
            $limit = request('limit', 10);
            $offset = request('offset', 0);

            $query = EmailTemplate::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('subject', 'like', "%$search%")
                        ->orWhere('body', 'like', "%$search%");
                });
            }

            $total = $query->count();
            $canEdit = (isAdminOrHasAllDataAccess() || auth()->user()->can('manage_email_template'));
            $canDelete = (isAdminOrHasAllDataAccess() || auth()->user()->can('delete_email_template'));

            $templates = $query->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($template) use ($canEdit, $canDelete) {
                    $actions = '';

                    if ($canEdit) {
                        $actions .= '<a href="javascript:void(0);" class="edit-template-btn"
                            data-template=\'' . htmlspecialchars(json_encode($template), ENT_QUOTES, 'UTF-8') . '\'
                            title="' . get_label('update', 'Update') . '">
                            <i class="bx bx-edit mx-1"></i>
                        </a>';
                    }

                    if ($canDelete) {
                        $actions .= '<button type="button"
                            class="btn delete"
                            data-id="' . $template->id . '"
                            data-type="email-templates"
                            title="' . get_label('delete', 'Delete') . '">
                            <i class="bx bx-trash text-danger mx-1"></i>
                        </button>';
                    }

                    return [
                        'id' => $template->id,
                        'name' => $template->name,
                        'subject' => $template->subject,
                        'placeholders' => count($template->placeholders ?? []) > 0
                            ? '<button class="btn btn-sm btn-outline-primary view-placeholders-btn"
                                data-placeholders=\'' . e(json_encode($template->placeholders)) . '\'>
                                View Placeholders
                            </button>'
                            : '',
                        'created_at' => format_date($template->created_at),
                        'updated_at' => format_date($template->updated_at),
                        'actions' => $actions ?: '-',
                    ];
                });

            return response()->json([
                'rows' => $templates,
                'total' => $total
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch email templates list', ['error' => $e->getMessage()]);
            return Response::json(['error' => true, 'message' => 'Something went wrong.'], 500);
        }
    }




    // Method: apiList
    /**
     * List email templates or retrieve a single template.
     *
     * This endpoint retrieves a paginated list of email templates or a single template by ID, with optional search, sorting, and pagination parameters. The user must be authenticated to perform this action. The response includes permission details for editing and deletion.
     *
     * @authenticated
     *
     * @group Email Template Management
     *
     * @urlParam id integer optional The ID of the email template to retrieve. If provided, returns a single template. Must exist in the `email_templates` table. Example: 1
     * @queryParam search string optional Filters templates by name, subject, or body. Example: Welcome
     * @queryParam sort string optional The field to sort by (id, name, subject, created_at, updated_at). Defaults to id. Example: name
     * @queryParam order string optional The sort order (ASC, DESC). Defaults to DESC. Example: ASC
     * @queryParam limit integer optional The number of templates per page (1-100). Defaults to 10. Example: 20
     * @queryParam offset integer optional The number of templates to skip. Defaults to 0. Example: 10
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Email templates retrieved successfully.",
     *   "data": {
     *     "total": 5,
     *     "data": [
     *       {
     *         "id": 1,
     *         "name": "Welcome Template",
     *         "subject": "Welcome to Our Company",
     *         "body": "<p>Hello USER_NAME!</p>",
     *         "placeholders": ["USER_NAME"],
     *         "workspace_id": 1,
     *         "created_at": "2025-05-15 17:00:00",
     *         "updated_at": "2025-05-15 17:00:00",
     *         "can_edit": true,
     *         "can_delete": true
     *       }
     *     ],
     *     "permissions": {
     *       "can_edit": true,
     *       "can_delete": true
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Email template not found.",
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
            // Validate query parameters
            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
                'sort' => 'nullable|string|in:id,name,subject,created_at,updated_at',
                'order' => 'nullable|string|in:ASC,DESC',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            // Validate ID if provided
            if ($id !== null && (!is_numeric($id) || $id <= 0)) {
                throw new \InvalidArgumentException('Invalid email template ID.');
            }

            // Extract parameters with defaults
            $search = $validated['search'] ?? '';
            $sort = $validated['sort'] ?? 'id';
            $order = $validated['order'] ?? 'DESC';
            $limit = $validated['limit'] ?? config('pagination.default_limit', 10);
            $offset = $validated['offset'] ?? 0;

            // Build query
            $query = EmailTemplate::query();

            // Fetch single template if ID is provided
            if ($id) {
                $template = $query->findOrFail($id);
                $data = formatEmailTemplate($template);
                $data['can_edit'] = checkPermission('edit_email_template');
                $data['can_delete'] = checkPermission('delete_email_template');

                Log::info('Single email template fetched via API', [
                    'template_id' => $id,
                    'user_id' => auth()->id() ?? 'guest',
                ]);

                return formatApiResponse(
                    false,
                    'Email template retrieved successfully.',
                    [
                        'total' => 1,
                        'data' => [$data],
                        'permissions' => [
                            'can_edit' => $data['can_edit'],
                            'can_delete' => $data['can_delete'],
                        ],
                    ],
                    200
                );
            }

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . addslashes($search) . '%')
                        ->orWhere('subject', 'like', '%' . addslashes($search) . '%')
                        ->orWhere('body', 'like', '%' . addslashes($search) . '%');
                });
            }

            // Get total count
            $total = $query->count();

            // Check permissions
            $canEdit = checkPermission('edit_email_template');
            $canDelete = checkPermission('delete_email_template');

            // Fetch templates
            $templates = $query->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($template) use ($canEdit, $canDelete) {
                    $data = formatEmailTemplate($template);
                    $data['can_edit'] = $canEdit;
                    $data['can_delete'] = $canDelete;
                    return $data;
                });

            // Log success
            Log::info('Email template list fetched via API', [
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'user_id' => auth()->id() ?? 'guest',
            ]);

            return formatApiResponse(
                false,
                'Email templates retrieved successfully.',
                [
                    'total' => $total,
                    'data' => $templates->toArray(),
                    'permissions' => [
                        'can_edit' => $canEdit,
                        'can_delete' => $canDelete,
                    ],
                ],
                200
            );
        } catch (ValidationException $e) {
            $errors = $e->validator->errors()->all();
            $message = 'Validation failed: ' . implode(', ', $errors);
            Log::warning('Validation failed in apiList', [
                'errors' => $errors,
                'input' => $request->all(),
            ]);
            return formatApiResponse(true, $message, [], 422);
        } catch (ModelNotFoundException $e) {
            Log::error('Email template not found in apiList', [
                'template_id' => $id,
                'exception' => $e->getMessage(),
            ]);
            return formatApiResponse(true, 'Email template not found.', [], 404);
        } catch (\Exception $e) {
            Log::error('Error in apiList', [
                'template_id' => $id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all(),
            ]);
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred.',
                [],
                500
            );
        }
    }
}
