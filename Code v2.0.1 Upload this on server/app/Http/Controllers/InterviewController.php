<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Candidate;
use App\Models\Interview;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InterviewController extends Controller
{

    public function index(){

        $interviews = Interview::all();
        $candidates = Candidate::all();
        $users = User::all();

        // dd($interviews);

        return view('interviews.index', compact('interviews', 'candidates', 'users'));
    }



    // Method: store
    /**
     * Create a new interview.
     *
     * This endpoint creates a new interview record for a candidate, with details such as the interviewer, round, schedule, mode, and status. The user must be authenticated to perform this action. A notification is triggered to inform the candidate and interviewer.
     *
     * @authenticated
     *
     * @group Interview Management
     *
     * @bodyParam candidate_id integer required The ID of the candidate for the interview. Must exist in the `candidates` table. Example: 101
     * @bodyParam interviewer_id integer required The ID of the interviewer. Must exist in the `users` table. Example: 7
     * @bodyParam round string required The interview round (e.g., Technical, HR). Maximum length is 255 characters. Example: Technical
     * @bodyParam scheduled_at string required The date and time of the interview (format: YYYY-MM-DD HH:MM:SS). Example: 2025-05-20 10:00:00
     * @bodyParam mode string required The mode of the interview (e.g., Online, In-Person). Maximum length is 255 characters. Example: Online
     * @bodyParam location string nullable The location of the interview (if applicable). Maximum length is 255 characters. Example: Zoom
     * @bodyParam status string required The status of the interview. Must be one of: scheduled, completed, cancelled. Example: scheduled
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 201 {
     *   "error": false,
     *   "message": "Interview Created Successfully!",
     *   "data": {
     *     "id": 1,
     *     "candidate_id": 101,
     *     "interviewer_id": 7,
     *     "round": "Technical",
     *     "scheduled_at": "2025-05-20 10:00:00",
     *     "mode": "Online",
     *     "location": "Zoom",
     *     "status": "scheduled",
     *     "created_at": "2025-05-15 16:15:00",
     *     "updated_at": "2025-05-15 16:15:00"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The candidate_id field is required."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the interview."
     * }
     */


    public function store(Request $request){

        $isApi = request('isApi', false);
        $form_fields =  $request->validate([
            'candidate_id' => 'required|exists:candidates,id',
            'interviewer_id' => 'required|exists:users,id',
            'round' => 'required|string|max:255',
            'scheduled_at' => 'required|date',
            'mode' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'status' => 'required|string|max:255|in:scheduled,completed,cancelled'
        ]);



        $interview = Interview::create($form_fields);
        // trigger notification

        $candidate = Candidate::find($request->candidate_id);
        $interviewer = User::find($request->interviewer_id);


        $data = [
            'type' => 'interview_assignment',
            'type_id' => $interview->id,
            'candidate_name' => $candidate->name,
            'round' => $interview->round,
            'scheduled_at' => $interview->scheduled_at,
            'mode' => $interview->mode,
            'location' => $interview->location,
            'interviewer_first_name' => $interviewer->first_name,
            'interviewer_last_name' => $interviewer->last_name,
            'access_url' => 'interviews.index',
            'action' => 'update'
        ];

        $recipients =['u_' . $interviewer->id, 'ca' . $candidate->id];
        processNotifications($data, $recipients);


        if ($isApi) {
            return formatApiResponse(
                false,
                'Interview Created Successfully!',
                [
                    'data' => formatInterview($interview)
                ],
                200
            );
        }

        return response()->json([
            'error' => false,
            'message' => 'Interview Created Successfully!',
            'interview' => $interview
        ]);


    }



    // Method: update
    /**
     * Update an interview.
     *
     * This endpoint updates the details of an existing interview, such as the candidate, interviewer, round, schedule, mode, location, or status. The user must be authenticated to perform this action. A notification is triggered if the status changes.
     *
     * @authenticated
     *
     * @group Interview Management
     *
     * @urlParam id integer required The ID of the interview to update. Must exist in the `interviews` table. Example: 1
     * @bodyParam candidate_id integer required The ID of the candidate for the interview. Must exist in the `candidates` table. Example: 101
     * @bodyParam interviewer_id integer required The ID of the interviewer. Must exist in the `users` table. Example: 7
     * @bodyParam round string required The interview round (e.g., Technical, HR). Maximum length is 255 characters. Example: Technical
     * @bodyParam scheduled_at string required The date and time of the interview (format: YYYY-MM-DD HH:MM:SS). Example: 2025-05-20 10:00:00
     * @bodyParam mode string required The mode of the interview (e.g., Online, In-Person). Maximum length is 255 characters. Example: Online
     * @bodyParam location string nullable The location of the interview (if applicable). Maximum length is 255 characters. Example: Zoom
     * @bodyParam status string required The status of the interview. Must be one of: scheduled, completed, cancelled. Example: completed
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Interview Updated Successfully!",
     *   "data": {
     *     "id": 1,
     *     "candidate_id": 101,
     *     "interviewer_id": 7,
     *     "round": "Technical",
     *     "scheduled_at": "2025-05-20 10:00:00",
     *     "mode": "Online",
     *     "location": "Zoom",
     *     "status": "completed",
     *     "created_at": "2025-05-15 16:15:00",
     *     "updated_at": "2025-05-15 16:20:00"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Interview not found"
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The candidate_id field is required."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the interview."
     * }
     */


    public function update(Request $request, $id)
    {

        $isApi = request('isApi', false);
        $request->validate([
            'candidate_id' => 'required|exists:candidates,id',
            'interviewer_id' => 'required|exists:users,id',
            'round' => 'required|string|max:255',
            'scheduled_at' => 'required|date',
            'mode' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'status' => 'required|string|max:255|in:scheduled,completed,cancelled'
        ]);

        $interview = Interview::findOrFail($id);
        $oldStatus = $interview->status;
        $interview->update($request->all());

        // trigger notification if status has changed

        if($oldStatus !== $request->status) {
            $candidate = Candidate::find($request->candidate_id);
            $interviewer = User::find($request->interviewer_id);

            $data = [
                'type' => 'interview_status_update',
                'type_id' => $interview->id,
                'candidate_name' => $candidate->name,
                'round' => $interview->round,
                'scheduled_at' => $interview->scheduled_at,
                'mode' =>$interview->mode,
                'location' => $interview->location,
                'interviewer_first_name' => $interviewer->first_name,
                'interviewer_last_name' => $interviewer->last_name,
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'updater_first_name' => getAuthenticatedUser()->first_name,
                'updater_last_name' => getAuthenticatedUser()->last_name,
                'access_url' => 'interviews',
                'action' => 'update'
            ];

            $recipients = ['u_' . $interviewer->id, 'ca' . $candidate->id];
            processNotifications($data, $recipients);
        }


        if ($isApi) {
            return formatApiResponse(
                false,
                'Interview Updated Successfully!',
                [
                    'data' => formatInterview($interview)
                ],
                200
            );
        }

        return response()->json([
            'error' => false,
            'message' => 'Interview Updated Successfully!',
            'interview' => $interview
        ]);
    }

    public function destroy_multiple(Request $request) {

        $validatedData = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:interviews,id',
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];

        foreach($ids as $id) {
            $interview = Interview::findOrFail($id);
            $deletedIds[] = $id;

            DeletionService::delete(Interview::class, $interview->id, 'Interview');
        }

        return response()->json([
            'error' => false,
            'message' => 'Interviews Deleted Successfully!',
            'deleted_ids' => $deletedIds
        ]);
    }


    // Method: destroy
    /**
     * Delete an interview.
     *
     * This endpoint deletes a specific interview record. The user must be authenticated and have appropriate permissions to perform this action.
     *
     * @authenticated
     *
     * @group Interview Management
     *
     * @urlParam id integer required The ID of the interview to delete. Must exist in the `interviews` table. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Interview deleted successfully!"
     * }
     *
     * @response 404 {
     *   "error": false,
     *   "message": "Interview not found",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the interview."
     * }
     */


    public function destroy($id) {

        try {

            $interview = Interview::findOrFail($id);


            $response = DeletionService::delete(Interview::class, $interview->id, 'Interview');

            return $response;
        } catch (ModelNotFoundException $e) {

            return formatApiResponse(
                false,
                'Interview not found',
                [],
                404
            );
        }
    }

    public function list()
    {
        $search = request('search');
        $order = request('order', 'DESC');
        $limit = request('limit',10);
        $offset = request('offset');
        $sort = request('sort', 'id');
        $interviewStatus = request('status');
        $startDate = request()->input('start_date');
        $endDate = request()->input('end_date');

        $order = 'desc';
        switch ($sort) {
            case 'newest':
                $sort = 'created_at';
                $order = 'desc';
                break;
            case 'oldest':
                $sort = 'created_at';
                $order = 'asc';
                break;
            case 'recently-updated':
                $sort = 'updated_at';
                $order = 'desc';
                break;
            case 'earliest-updated':
                $sort = 'updated_at';
                $order = 'asc';
                break;
            default:
                $sort = 'id';
                $order = 'desc';
                break;
        }



        // dd($interviewStatus);

        $query = Interview::query();

        // Apply search filters
        if ($search) {
            $query->where(function ($query) use ($search) {
                $query->whereHas('candidate', function ($q) use ($search) {
                    $q->where('candidates.id', 'like', "%$search%")
                        ->orWhere('candidates.name', 'like', "%$search%");
                })
                    ->orWhereHas('interviewer', function ($q) use ($search) {
                        $q->where('users.id', 'like', "%$search%")
                            ->orWhere('users.first_name', 'like', "%$search%")
                            ->orWhere('users.last_name', 'like', "%$search%");
                    })
                    ->orWhere('round', 'like', "%$search%")
                    ->orWhere('status', 'like', "%$search%")
                    ->orWhere('location', 'like', "%$search%")
                    ->orWhere('mode', 'like', "%$search%");
            });
        }

        if($interviewStatus) {
            $query->where('status', $interviewStatus);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        // Get total count for pagination
        // dd($query);
        $total = $query->count();

        $canEdit = checkPermission('edit_interview');
        $canDelete = checkPermission('delete_interview');

        // Apply sorting, pagination, and limit
        $interviews = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($interview) use ($canDelete, $canEdit) {

                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-interview-btn"
                                        data-interview=\'' . htmlspecialchars(json_encode($interview), ENT_QUOTES, 'UTF-8') . '\'
                                        title="' . get_label('update', 'Update') . '">
                                        <i class="bx bx-edit mx-1"></i>
                                    </a>';
                }

                if ($canDelete) {
                    $actions .= '<button type="button"
                                        class="btn delete"
                                        data-id="' . $interview->id . '"
                                        data-type="interviews"
                                        title="' . get_label('delete', 'Delete') . '">
                                        <i class="bx bx-trash text-danger mx-1"></i>
                                    </button>';
                }

                return [
                    'id' => $interview->id,
                    'candidate' => ucwords($interview->candidate->name),
                    'interviewer' => ucwords($interview->interviewer->first_name) . ' ' . ucwords($interview->interviewer->last_name),
                    'round' => ucwords($interview->round),
                    'scheduled_at' => ucwords($interview->scheduled_at),
                    'mode' => ucwords($interview->mode),
                    'location' => ucwords($interview->location),
                    'status' => ucwords($interview->status),
                    'created_at' => format_date($interview->created_at),
                    'updated_at' => format_date($interview->updated_at),
                    'actions' => $actions
                ];
            });

        // Return the result as JSON
        return response()->json([
            'rows' => $interviews,
            'total' => $total,
        ]);
    }



    // Method: apiList
    /**
     * List interviews or retrieve a single interview.
     *
     * This endpoint retrieves a paginated list of interviews or a single interview by ID, with optional search, sorting, and status filtering. The user must be authenticated to perform this action. The response includes permission details for editing and deletion.
     *
     * @authenticated
     *
     * @group Interview Management
     *
     * @urlParam id integer optional The ID of the interview to retrieve. If provided, returns a single interview. Must exist in the `interviews` table. Example: 1
     * @queryParam search string optional Filters interviews by candidate name, interviewer name, round, status, location, or mode. Example: Technical
     * @queryParam sort string optional The field to sort by (id, newest, oldest, recently-updated, earliest-updated). Defaults to id. Example: newest
     * @queryParam limit integer optional The number of interviews per page (1-100). Defaults to 10. Example: 20
     * @queryParam offset integer optional The number of interviews to skip. Defaults to 0. Example: 10
     * @queryParam status string optional Filters interviews by status (e.g., scheduled, completed, cancelled). Example: scheduled
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Interviews retrieved successfully",
     *   "data": {
     *     "total": 10,
     *     "data": [
     *       {
     *         "id": 1,
     *         "candidate_id": 101,
     *         "candidate_name": "John Doe",
     *         "interviewer_id": 7,
     *         "interviewer_name": "Jane Smith",
     *         "round": "Technical",
     *         "scheduled_at": "2025-05-20 10:00:00",
     *         "mode": "Online",
     *         "location": "Zoom",
     *         "status": "scheduled",
     *         "created_at": "2025-05-15 16:15:00",
     *         "updated_at": "2025-05-15 16:15:00",
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
     *   "message": "Interview not found.",
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
                'sort' => 'nullable|string|in:id,newest,oldest,recently-updated,earliest-updated',
                'limit' => 'nullable|integer|min:1|max:100',
                'candidate_id' => 'nullable|exists:candidates,id',
                'offset' => 'nullable|integer|min:0',
                'candidate_status' => 'nullable|array',
                'candidate_status.*' => 'integer|exists:candidate_statuses,id',
            ]);

            // Validate ID if provided
            if ($id !== null && (!is_numeric($id) || $id <= 0)) {
                throw new \InvalidArgumentException('Invalid candidate ID.');
            }

            // Extract parameters with defaults
            $search = $validated['search'] ?? '';
            $sortInput = $validated['sort'] ?? 'id';
            $limit = $validated['limit'] ?? config('pagination.default_limit', 10);
            $offset = $validated['offset'] ?? 0;
            $candidate_id = $validated['candidate_id'] ?? null;
            $interviewStatus = request('status');
            $startDate = request()->input('start_date');
            $endDate = request()->input('end_date');
            // Determine sort and order
            $sort = 'id';
            $order = 'desc';
            switch ($sortInput) {
                case 'newest':
                    $sort = 'created_at';
                    $order = 'desc';
                    break;
                case 'oldest':
                    $sort = 'created_at';
                    $order = 'asc';
                    break;
                case 'recently-updated':
                    $sort = 'updated_at';
                    $order = 'desc';
                    break;
                case 'earliest-updated':
                    $sort = 'updated_at';
                    $order = 'asc';
                    break;
                default:
                    $sort = 'id';
                    $order = 'desc';
                    break;
            }

            $query = Interview::query()->with('candidate', 'interviewer');

            if ($id) {

                $interview = $query->find($id);

                // If interview is not found
                if (!$interview) {
                    return formatApiResponse(
                        true,
                        'Interview not found',
                        [],
                        404
                    );
                }


                $data = formatInterview($interview);
                $data['can_delete'] = checkPermission('delete_interview');
                $data['can_edit'] = checkPermission('edit_interview');

                Log::info('Single candidate fetched via API', [
                    'candidate_id' => $id,
                    'user_id' => auth()->id() ?? 'guest',
                ]);

                return formatApiResponse(
                    false,
                    'Interview retrieved successfully',
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

            // Apply search filters
            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('candidate', function ($q) use ($search) {
                        $q->where('candidates.id', 'like', "%$search%")
                            ->orWhere('candidates.name', 'like', "%$search%");
                    })
                        ->orWhereHas('interviewer', function ($q) use ($search) {
                            $q->where('users.id', 'like', "%$search%")
                                ->orWhere('users.first_name', 'like', "%$search%")
                                ->orWhere('users.last_name', 'like', "%$search%");
                        })
                        ->orWhere('round', 'like', "%$search%")
                        ->orWhere('status', 'like', "%$search%")
                        ->orWhere('location', 'like', "%$search%")
                        ->orWhere('mode', 'like', "%$search%");
                });
            }

            if ($interviewStatus) {
                $query->where('status', $interviewStatus);
            }
            if ($candidate_id) {
                $query->where('candidate_id', $candidate_id);
            }

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }

            // Get total count
            $total = $query->count();

            // Check permissions
            $canEdit = checkPermission('edit_candidate');
            $canDelete = checkPermission('delete_candidate');

            // Fetch candidates
            $interviews = $query->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($interview) use ($canEdit, $canDelete) {
                    $data = formatInterview($interview);
                    $data['can_edit'] = $canEdit;
                    $data['can_delete'] = $canDelete;
                    return $data;
                });

            // Log success
            Log::info('Candidate list fetched via API', [
                'search' => $search,
                'sort' => $sortInput,
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'user_id' => auth()->id() ?? 'guest',
            ]);

            return formatApiResponse(
                false,
                'Interviews retrieved successfully',
                [
                    'total' => $total,
                    'data' => $interviews->toArray(),
                    'permissions' => [
                        'can_edit' => $canEdit,
                        'can_delete' => $canDelete,
                    ]
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
            Log::error('interview not found in apiList', [
                'inteview_id' => $id,
                'exception' => $e->getMessage(),
            ]);
            return formatApiResponse(true, 'interview not found.', [], 404);
        } catch (\Exception $e) {
            Log::error('Error in apiList', [
                'inteview_id' => $id,
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
