<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Workspace;
use App\Jobs\SendEmailJob;
use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use App\Models\ScheduledEmail;
use App\Services\DeletionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Notifications\DynamicTemplateMail;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EmailSendController extends Controller
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
    public function create(Request $request)
    {
        try {
            $templates = EmailTemplate::all();
            return view('email.send', compact('templates'));
        } catch (Exception $e) {
            Log::error('Error loading email send page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load email send page.');
        }
    }



    // Method: getTemplateData
    /**
     * Retrieve email template data.
     *
     * This endpoint fetches the details of a specific email template, including its subject, body, and placeholders (excluding default ones like CURRENT_YEAR, COMPANY_TITLE). The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Email Management
     *
     * @urlParam id integer required The ID of the email template to retrieve. Must exist in the `email_templates` table. Example: 1
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Template data retrieved successfully!",
     *   "data": {
     *     "id": 1,
     *     "subject": "Welcome Email",
     *     "body": "<p>Hello USER_NAME, welcome to COMPANY_TITLE!</p>",
     *     "placeholders": ["USER_NAME"],
     *     "created_at": "2025-05-15 16:30:00",
     *     "updated_at": "2025-05-15 16:30:00"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Template not found"
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while retrieving the template data."
     * }
     */


    public function getTemplateData($id)
    {
        $isApi = request()->get('isApi', false);
        try {
            $template = EmailTemplate::findOrFail($id);
            $defaultPlaceholders = ['CURRENT_YEAR', 'COMPANY_TITLE', 'COMPANY_LOGO', 'SUBJECT'];

            preg_match_all('/{(\w+)}/', $template->body, $matches);
            // Use array_unique to remove duplicates
            $placeholders = array_diff(array_unique($matches[1]), $defaultPlaceholders);

            $templateData = formatEmailTemplate($template);
            $templateData['placeholders'] = array_values($placeholders);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Template data retrieved successfully!',
                    [
                        'data' => $templateData
                    ]
                );
            } else {
                return response()->json([
                    'subject' => $template->subject,
                    'body' => $template->body,
                    'placeholders' => array_values($placeholders) // Ensure we return an array
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Template not found'
            ], 404);
        }
    }



    // Method: preview
    /**
     * Generate a preview of an email.
     *
     * This endpoint generates a preview of an email by replacing placeholders in the provided subject and body, and listing any attachments. The user must be authenticated to perform this action. The content can be base64-encoded if specified.
     *
     * @authenticated
     *
     * @group Email Management
     *
     * @bodyParam subject string optional The subject of the email. Defaults to 'No Subject'. Example: Welcome Email
     * @bodyParam body string required The body of the email, optionally base64-encoded. Example: <p>Hello USER_NAME!</p>
     * @bodyParam placeholders array optional Key-value pairs of placeholders to replace in the subject and body. Example: "USER_NAME": "John Doe"
     * @bodyParam attachments array optional An array of files to include as attachments. Each file must not exceed the configured size limit. Example: [attachment.pdf]
     * @bodyParam is_encoded string optional Indicates if the body is base64-encoded (value: '1'). Defaults to false. Example: 1
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "preview generated successfully!",
     *   "data": "<div><p>Hello John Doe!</p></div><hr><div><strong>Attachments:</strong><ul><li>attachment.pdf</li></ul></div>"
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Failed to generate preview"
     * }
     */


    public function preview(Request $request)
    {
        $isApi = request()->get('isApi', false);
        if ($request->has('is_encoded') && $request->is_encoded == '1') {
            $decodedContent = base64_decode($request->content);
            $request->merge(['body' => $decodedContent]);
        }
        try {
            $subject = $request->subject ?? 'No Subject';
            $body = $request->body;
            $placeholders = $request->placeholders ?? [];

            // Replace placeholders
            foreach ($placeholders as $key => $value) {
                $body = str_replace("{{$key}}", $value, $body);
                $subject = str_replace("{{$key}}", $value, $subject);
            }
            // Process attachments
            $attachmentPreview = '';
            if ($request->hasFile('attachments')) {
                $files = $request->file('attachments');
                $attachmentPreview .= "<hr><div><strong>Attachments:</strong><ul>";
                foreach ($files as $file) {
                    $attachmentPreview .= "<li>{$file->getClientOriginalName()}</li>";
                }
                $attachmentPreview .= "</ul></div>";
            }

            $body = preg_replace('/background-color:\s*[^;]+;?/i', '', $body);
            //remove background color
            $html = "

        <div>{$body}</div>
        {$attachmentPreview}
        ";

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'preview generated successfully!',
                    [
                        'data' => $html
                    ]
                );
            } else {
                return response()->json(['preview' => $html]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate preview'], 500);
        }
    }



    // Method: store
    /**
     * Send or schedule emails.
     *
     * This endpoint sends or schedules emails to one or more recipients, using either a template or custom content. The user must be authenticated to perform this action. Attachments are supported, and scheduling is optional. Certain file extensions (e.g., zip, exe) are blocked for security.
     *
     * @authenticated
     *
     * @group Email Management
     *
     * @bodyParam emails array required An array of recipient email addresses. Each must be a valid email. Example: ["john.doe@example.com"]
     * @bodyParam email_template_id integer nullable The ID of the email template to use. Required if not sending a custom email. Must exist in the `email_templates` table. Example: 1
     * @bodyParam placeholders array nullable Key-value pairs of placeholders to replace in the template. Required if using a template. Example: "USER_NAME": "John Doe"
     * @bodyParam subject string nullable The subject of the email. Required if not using a template. Maximum length is 255 characters. Example: Welcome Email
     * @bodyParam body string nullable The body of the email. Required if not using a template. Example: <p>Hello!</p>
     * @bodyParam attachments array nullable An array of files to attach. Each file must not exceed the configured size limit and must not have blocked extensions (e.g., zip, exe). Example: [attachment.pdf]
     * @bodyParam scheduled_at string nullable The date and time to schedule the email (format: YYYY-MM-DD HH:MM:SS). Must be in the future. Example: 2025-05-16 10:00:00
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Emails sent successfully."
     * }
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Emails scheduled successfully!"
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The emails field is required."
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Attachments with .zip, .exe and similar file types are not allowed for security reasons."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An unexpected error occurred while sending/scheduling the emails."
     * }
     */


    public function store(Request $request)
    {
        // $isApi = request()->get('isApi', false);

        if (!isEmailConfigured()) {
            return response()->json(
                [
                    'error' => true,
                    'message' => 'Email settings are not configured. Please configure email settings to send emails.'
                ]
            );
        }

        try {
            $general_settings = get_settings('general_settings');
            $maxFileSizeBytes = config('media-library.max_file_size');
            $maxFileSizeKb = (int) ($maxFileSizeBytes / 1024);

            // Determine if this is a template email or custom email
            $isTemplateEmail = $request->filled('email_template_id');

            // Common validation rules
            $rules = [
                'emails' => 'required|array|min:1',
                'emails.*' => 'email',
                'attachments' => 'nullable|array',
                'attachments.*' => "file|max:$maxFileSizeKb",
                'scheduled_at' => 'nullable|date|after:now',
            ];

            // Add template-specific or custom-specific validation
            if ($isTemplateEmail) {
                $rules = array_merge($rules, [
                    'email_template_id' => 'required|exists:email_templates,id',
                    'placeholders' => 'required|array',
                ]);
            } else {
                $rules = array_merge($rules, [
                    'subject' => 'required|string|max:255',
                    'body' => 'required|string',
                ]);
            }

            $data = $request->validate($rules);

            // Validate file extensions (BLOCK zip, exe, bat, etc.)
            $blockedExtensions = ['zip', 'exe', 'bat', 'cmd', 'scr', 'com', 'pif', 'jar', 'js', 'php', 'html', 'htm', 'vbs', 'wsf', 'wsh', 'cmd', 'cpl', 'reg', 'dll'];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (in_array($file->getClientOriginalExtension(), $blockedExtensions)) {
                        return response()->json([
                            'error' => true,
                            'message' => 'Attachments with .zip, .exe and similar file types are not allowed for security reasons.',
                        ]);
                    }
                }
            }

            // Prepare email data for template emails
            if ($isTemplateEmail) {
                $template = EmailTemplate::findOrFail($data['email_template_id']);
                $subject = $template->subject;
                $body = $template->body;

                // Add default placeholders
                $data['placeholders'] = array_merge($data['placeholders'], [
                    'CURRENT_YEAR' => now()->year,
                    'COMPANY_TITLE' => $general_settings['company_title'] ?? 'Company Title',
                    'COMPANY_LOGO' => '<img src="' . asset("/storage/" . (get_settings('general_settings')['full_logo'] ?? 'logos/default_full_logo.png')) . '" width="200px" alt="Company Logo">',

                    'SUBJECT' => $subject,
                ]);

                // Replace placeholders in body
                foreach ($data['placeholders'] as $key => $value) {
                    $body = str_replace(['{' . $key . '}', '{{' . $key . '}}'], $value, $body);
                }
            } else {
                // For custom emails, use provided subject and body
                $subject = $data['subject'];
                $body = $data['body'];
            }

            // Determine if scheduled
            $isScheduled = !empty($data['scheduled_at']);
            $status = $isScheduled ? 'pending' : 'sent';
            $scheduledAtUtc = $isScheduled
                ? Carbon::parse($data['scheduled_at'], config('app.timezone', 'UTC'))->setTimezone('UTC')
                : null;

            $createdEmails = [];
            // Loop through each recipient and send/schedule the email
            foreach ($data['emails'] as $recipient) {
                // Store email record
                $email = ScheduledEmail::create([
                    'user_id' => auth()->id(),
                    'email_template_id' => $isTemplateEmail ? $data['email_template_id'] : null,
                    'workspace_id' => getWorkspaceId(),
                    'to_email' => $recipient,
                    'subject' => $subject,
                    'body' => $body,
                    'placeholders' => $isTemplateEmail ? $data['placeholders'] : null,
                    'scheduled_at' => $scheduledAtUtc,
                    'status' => $status,
                ]);

                // Handle attachments
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $email->addMedia($file)
                            ->sanitizingFileName(function ($fileName) {
                                $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                                $uniqueId = time() . '_' . mt_rand(1000, 9999);
                                return strtolower(str_replace(['#', '/', '\\', ' '], '-', $baseName)) . "-{$uniqueId}.{$extension}";
                            })
                            ->toMediaCollection('email-media');
                    }
                }

                if (!$isScheduled) {
                    try {
                        Mail::to($email->to_email)->send(new DynamicTemplateMail($email));
                        $email->update(['status' => 'sent']);
                    } catch (\Throwable $th) {
                        $email->update(['status' => 'failed']);
                        Log::error('Email sending failed for ' . $recipient . ': ' . $th->getMessage());
                    }
                }

                $createdEmails[] = formatEmailSend($email); // Add formatted email
            }
            $message = $isScheduled ? 'Emails scheduled successfully!' : 'Emails sent successfully.';
            // if ($isApi) {
            //     return formatApiResponse(false, $message, []);
            // } else {
            return response()->json([
                'error' => false,
                'message' => $message
            ]);
            // }
        } catch (Exception $e) {
            Log::error('Failed to send or schedule emails: ' . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'An unexpected error occurred while sending/scheduling the emails.',
                'details' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }


    public function history()
    {
        try {
            $emails = isAdminOrHasAllDataAccess() ? $this->workspace->scheduledEmails() : $this->user->scheduledEmails();
            return view('email.history', compact('emails'));
        } catch (Exception $e) {
            dd($e);
            Log::error('Error loading email history: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load email history.');
        }
    }


    // Method: destroy
    /**
     * Delete a scheduled email.
     *
     * This endpoint deletes a specific scheduled email record. The user must be authenticated and have appropriate permissions to perform this action.
     *
     * @authenticated
     *
     * @group Email Management
     *
     * @urlParam id integer required The ID of the scheduled email to delete. Must exist in the `scheduled_emails` table. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Scheduled Email deleted successfully!"
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Scheduled Email not found"
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the scheduled email."
     * }
     */


    public function destroy($id)
    {
        $email = ScheduledEmail::findOrFail($id);

        $response = DeletionService::delete(ScheduledEmail::class, $email->id, 'Scheduled Email');

        return $response;
    }

    public function destroy_multiple(Request $request)
    {
        $validatedData = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:scheduled_emails,id',


        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];

        foreach ($ids as $id) {
            $email = ScheduledEmail::findOrFail($id);
            $deletedIds[] = $id;
            DeletionService::delete(ScheduledEmail::class, $email->id, 'Scheduled Email');
        }

        return response()->json([
            'error' => false,
            'message' => 'Scheduled Email(s) deleted successfully.',
            'id' => $deletedIds,
        ]);
    }



    public function historyList(Request $request)
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $limit = (int) $request->input('limit', 10);

        $user = auth()->user();

        $query = isAdminOrHasAllDataAccess() ?  $this->workspace->scheduledEmails() :  $this->user->scheduledEmails();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('to_email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $query->orderBy('scheduled_emails.' . $sort, $order);

        $paginated = $query->paginate($limit)
            ->through(function ($email) {
                $canDelete = isAdminOrHasAllDataAccess() || ($email->user_id == auth()->id());
                $status = $email->status == 'pending' ? '<span class="badge bg-warning">Pending</span>' : ($email->status == 'sent' ? '<span class="badge bg-success">Sent</span>' :
                        '<span class="badge bg-danger">Failed</span>');
                $actions = $canDelete ? '<button type="button"
                    class="btn delete"
                    data-id="' . $email->id . '"
                    data-type="emails/history"
                    title="' . get_label('delete', 'Delete') . '">
                    <i class="bx bx-trash text-danger mx-1"></i>
                </button>' : '-';

                return [
                    'id' => $email->id,
                    'to_email' => $email->to_email,
                    'subject' => ucwords($email->subject),
                    'status' => $status,
                    'scheduled_at' => format_date($email->scheduled_at, true),
                    'created_at' => format_date($email->created_at, true),
                    'updated_at' => format_date($email->updated_at, true),
                    'user_name' => formatUserHtml($email->user) ?? 'N/A',
                    'body' => $email->body,
                    'actions' => $actions,
                ];
            });

        return response()->json([
            'total' => $paginated->total(),
            'rows' => $paginated->items(),
        ]);
    }



    // Method: apihistoryList
    /**
     * List scheduled emails or retrieve a single email.
     *
     * This endpoint retrieves a paginated list of scheduled emails or a single email by ID, with optional search, sorting, and pagination parameters. The user must be authenticated, and access is restricted based on permissions (admin/all-data-access or user-owned emails). The response includes permission details for editing and deletion.
     *
     * @authenticated
     *
     * @group Email Management
     *
     * @urlParam id integer optional The ID of the scheduled email to retrieve. If provided, returns a single email. Must exist in the `scheduled_emails` table. Example: 1
     * @queryParam search string optional Filters emails by recipient email or subject. Example: john.doe
     * @queryParam sort string optional The field to sort by (id, to_email, subject, scheduled_at, created_at, updated_at). Defaults to id. Example: subject
     * @queryParam order string optional The sort order (ASC, DESC). Defaults to DESC. Example: ASC
     * @queryParam limit integer optional The number of emails per page (1-100). Defaults to 10. Example: 20
     * @queryParam offset integer optional The number of emails to skip. Defaults to 0. Example: 10
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Emails retrieved successfully.",
     *   "data": {
     *     "total": 5,
     *     "data": [
     *       {
     *         "id": 1,
     *         "to_email": "john.doe@example.com",
     *         "subject": "Welcome Email",
     *         "body": "<p>Hello John!</p>",
     *         "status": "sent",
     *         "scheduled_at": "2025-05-15 16:30:00",
     *         "created_at": "2025-05-15 16:30:00",
     *         "updated_at": "2025-05-15 16:30:00",
     *         "user_id": 7,
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
     *   "message": "Email not found.",
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


    public function apihistoryList(Request $request, $id = null)
    {
        try {
            // Validate query parameters
            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
                'sort' => 'nullable|string|in:id,to_email,subject,scheduled_at,created_at,updated_at',
                'order' => 'nullable|string|in:ASC,DESC',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            // Validate ID if provided
            if ($id !== null && (!is_numeric($id) || $id <= 0)) {
                throw new \InvalidArgumentException('Invalid email ID.');
            }

            // Extract parameters with defaults
            $search = $validated['search'] ?? '';
            $sort = $validated['sort'] ?? 'id';
            $order = $validated['order'] ?? 'DESC';
            $limit = $validated['limit'] ?? config('pagination.default_limit', 10);
            $offset = $validated['offset'] ?? 0;

            // Determine query based on permissions
            $query = isAdminOrHasAllDataAccess()
                ? $this->workspace->scheduledEmails()
                : auth()->user()->scheduledEmails();

            // Fetch single email if ID is provided
            if ($id) {
                $email = $query->findOrFail($id);
                $data = formatEmailSend($email);
                $data['can_edit'] = checkPermission('edit_email');
                $data['can_delete'] = checkPermission('delete_email');

                Log::info('Single email history fetched via API', [
                    'email_id' => $id,
                    'user_id' => auth()->id() ?? 'guest',
                ]);

                return formatApiResponse(
                    false,
                    'Email retrieved successfully.',
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
                    $q->where('to_email', 'like', '%' . addslashes($search) . '%')
                        ->orWhere('subject', 'like', '%' . addslashes($search) . '%');
                });
            }

            // Apply sorting
            $query->orderBy($sort, $order);

            // Get total count
            $total = $query->count();

            // Check permissions
            $canEdit = checkPermission('edit_email');
            $canDelete = checkPermission('delete_email');

            // Fetch emails
            $emails = $query->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($email) use ($canEdit, $canDelete) {
                    $data = formatEmailSend($email);
                    $data['can_edit'] = $canEdit;
                    $data['can_delete'] = $canDelete;
                    return $data;
                });

            // Log success
            Log::info('Email history list fetched via API', [
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
                'Emails retrieved successfully.',
                [
                    'total' => $total,
                    'data' => $emails->toArray(),
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
            Log::warning('Validation failed in apihistoryList', [
                'errors' => $errors,
                'input' => $request->all(),
            ]);
            return formatApiResponse(true, $message, [], 422);
        } catch (ModelNotFoundException $e) {
            Log::error('Email not found in apihistoryList', [
                'email_id' => $id,
                'exception' => $e->getMessage(),
            ]);
            return formatApiResponse(true, 'Email not found.', [], 404);
        } catch (\Exception $e) {
            Log::error('Error in apihistoryList', [
                'email_id' => $id,
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
