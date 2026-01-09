<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CandidateStatus;
use App\Services\DeletionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CandidateStatusController extends Controller
{
    public function index()
    {
        $candidate_statuses = CandidateStatus::all();
        return view('candidate.candidate_status.index', compact('candidate_statuses'));
    }




    // Method: store
    /**
     * Create a new candidate status.
     *
     * This endpoint creates a new candidate status with a specified name and color. The user must be authenticated to perform this action. The status is automatically assigned an order based on the highest existing order plus one.
     *
     * @authenticated
     *
     * @group Candidate Status Management
     *
     * @bodyParam name string required The name of the candidate status. Maximum length is 255 characters. Example: Interviewing
     * @bodyParam color string required The color associated with the status (e.g., primary, success). Example: primary
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 201 {
     *   "error": false,
     *   "message": "Candidate status retrieved successfully!",
     *   "data": {
     *     "id": 1,
     *     "name": "Interviewing",
     *     "color": "primary",
     *     "order": 1,
     *     "created_at": "2025-05-15 16:11:00",
     *     "updated_at": "2025-05-15 16:11:00"
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
     *   "message": "An error occurred while creating the candidate status."
     * }
     */


    public function store(Request $request)
    {

        $isApi = request()->get('isApi', false);
        try {
        $request->validate([
            'name' => 'required|string|max:255|unique:candidate_statuses,name',
            'color' => 'required'
        ]);

        $order = CandidateStatus::max('order') + 1;

        $candidate_status = CandidateStatus::create([
            'name' => $request->name,
            'order' => $order,
            'color' => $request->color
        ]);

            if ($isApi) {
            return formatApiResponse(
                false,
                'Candidate status Created successfully!',
                [
                    'data' => formatCandidateStatus($candidate_status)
                ],
                200
            );
        }

        return response()->json([
            'error' => false,
            'message' => 'Status Created Successfully!',
            'candidate_statuses' => $candidate_status
        ]);
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors(), 'Validation failed while creating candidate status.');
        } catch (\Exception $e) {
            Log::error('Error creating candidate status', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all(),
            ]);
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while creating the candidate status.'
            ], 500);
        }
    }



    // Method: update
    /**
     * Update a candidate status.
     *
     * This endpoint updates the name and color of an existing candidate status. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Candidate Status Management
     *
     * @urlParam id integer required The ID of the candidate status to update. Must exist in the `candidate_statuses` table. Example: 1
     * @bodyParam name string required The name of the candidate status. Maximum length is 255 characters. Example: Interviewing
     * @bodyParam color string optional The color associated with the status (e.g., primary, success). Example: success
     * @queryParam isApi boolean optional Indicates if the response should be formatted for API use. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Candidate status updated successfully!",
     *   "data": {
     *     "id": 1,
     *     "name": "Interviewing",
     *     "color": "success",
     *     "order": 1,
     *     "created_at": "2025-05-15 16:11:00",
     *     "updated_at": "2025-05-15 16:12:00"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Candidate status not found"
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The name field is required."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the candidate status."
     * }
     */


    public function update(Request $request, $id)
    {

        $isApi = request('isApi', false);
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $candidate_status = CandidateStatus::findOrFail($id);

        $candidate_status->update([
            'name' => $request->name,
            'color' => $request->color
        ]);

        if ($isApi) {
            return formatApiResponse(
                false,
                'Candidate status updated successfully!',
                [
                    'data' => $candidate_status
                ],
                200
            );
        }

        return response()->json([
            'error' => false,
            'message' => 'Status updated Successfully!',
            'candidate_status' => $candidate_status
        ]);
    }



    // Method: destroy
    /**
     * Delete a candidate status.
     *
     * This endpoint deletes a specific candidate status. The user must be authenticated and have appropriate permissions. The status cannot be deleted if it is assigned to one or more candidates.
     *
     * @authenticated
     *
     * @group Candidate Status Management
     *
     * @urlParam id integer required The ID of the candidate status to delete. Must exist in the `candidate_statuses` table. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Candidate Status deleted successfully!"
     * }
     *
     * @response 400 {
     *   "error": false,
     *   "message": "Cannot delete. This status is assigned to one or more candidates."
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Candidate status not found"
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the candidate status."
     * }
     */


    public function destroy($id)
    {

        $candidate_status = CandidateStatus::findOrFail($id);

        $candidateCount = $candidate_status->candidates->count();

        if ($candidateCount > 0) {
            return response()->json([
                'error' => false,
                'message' => ' Cannot delete . This status is assigned to one or more candidates . '
            ]);
        }

        $response = DeletionService::delete(CandidateStatus::class, $candidate_status->id, 'Candidate Status');

        return $response;
    }

    public function destroy_multiple(Request $request)
    {

        $validatedData = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:candidate_statuses,id'
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $notDeleted = [];

        foreach ($ids as $id) {
            $candidate_status = CandidateStatus::findOrFail($id);

            // If status is linked to candidates, skip deletion
            if ($candidate_status->candidates()->count() > 0) {
                $notDeleted[] = $id;
                continue;
            }


            DeletionService::delete(CandidateStatus::class, $candidate_status->id, 'Candidate Status');
            $deletedIds[] = $id;
        }

        return response()->json([
            'error' => count($notDeleted) > 0,
            'message' => count($notDeleted) ? 'Some statuses could not be deleted because they are assigned to candidates.' : 'Candidate Status(es) Deleted Successfully!',
            'id' => $deletedIds,
        ]);
    }



    // Method: reorder
    /**
     * Reorder candidate statuses.
     *
     * This endpoint updates the order of candidate statuses based on the provided array of IDs and positions. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Candidate Status Management
     *
     * @bodyParam order array required An array of objects containing status IDs and their new positions. Example: [{"id": 1, "position": 1}, {"id": 2, "position": 2}]
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Order updated successfully!",
     *   "data": []
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The order field is required."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while reordering the candidate statuses."
     * }
     */


    public function reorder(Request $request)
    {
        $isApi = request('isApi', false);
        foreach ($request->order as $item) {
            CandidateStatus::where('id', $item['id'])->update([
                'order' => $item['position']
            ]);
        }

        if ($isApi) {
            return formatApiResponse(
                false,
                'Order updated successfully!',
                [],
                200
            );
        }

        return response()->json([
            'error' => false,
            'message' => 'Order updated successfully!'
        ]);
    }


    public function list()
    {

        $search = request('search');
        $limit = request('limit', 10);
        $offset = request('offset', 0);
        $order = request('order', 'DESC');
        $sort = request('sort', 'id');

        $query = CandidateStatus::orderBy('order');


        if ($search) {
            $query->where('name', 'like', "%$search%");
        }

        $total = $query->count();

        $canEdit = checkPermission('edit_candidate_status');
        $canDelete = checkPermission('delete_candidate_status');

        $statuses = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($status) use ($canDelete, $canEdit) {

            $actions = '';

            if ($canEdit) {
                $actions .= '<a href="javascript:void(0);" class="edit-candidate-status-btn"
                                        data-candidate-status=\'' . htmlspecialchars(json_encode($status), ENT_QUOTES, 'UTF-8') . '\'
                                        title="' . get_label('update', 'Update') . '">
                                        <i class="bx bx-edit mx-1"></i>
                                        </a>';
            }


            if ($canDelete) {
                $actions .= '<button type="button"
                                        class="btn delete"
                                        data-id="' . $status->id . '"
                                        data-type="candidate_status"
                                        title="' . get_label('delete', 'Delete') . '">
                                        <i class="bx bx-trash text-danger mx-1"></i>
                                        </button>';
            }

            return [
                'id' => $status->id,
                'order' => $status->order,
                'name' => ucwords($status->name),
                'created_at' => format_date($status->created_at),
                'color' => '<span class="badge bg-' . $status->color . '">' . ucfirst($status->color) . '</span>',
                'updated_at' => format_date($status->updated_at),
                'actions' => $actions ?: '-'
            ];
            });

        return response()->json([
            'rows' => $statuses,
            'total' => $total,
        ]);
    }



    // Method: apiList
    /**
     * List candidate statuses or retrieve a single status.
     *
     * This endpoint retrieves a paginated list of candidate statuses or a single status by ID, with optional search, sorting, and pagination parameters. The user must be authenticated to perform this action. The response includes permission details for editing and deletion.
     *
     * @authenticated
     *
     * @group Candidate Status Management
     *
     * @urlParam id integer optional The ID of the candidate status to retrieve. If provided, returns a single status. Must exist in the `candidate_statuses` table. Example: 1
     * @queryParam search string optional Filters statuses by name. Example: Interview
     * @queryParam sort string optional The field to sort by (id, name, color, order, created_at, updated_at). Defaults to id. Example: name
     * @queryParam order string optional The sort order (ASC, DESC). Defaults to DESC. Example: ASC
     * @queryParam limit integer optional The number of statuses per page (1-100). Defaults to 10. Example: 20
     * @queryParam offset integer optional The number of statuses to skip. Defaults to 0. Example: 10
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Candidate statuses retreived successfully!",
     *   "data": {
     *     "total": 5,
     *     "data": [
     *       {
     *         "id": 1,
     *         "name": "Interviewing",
     *         "color": "primary",
     *         "order": 1,
     *         "created_at": "2025-05-15 16:11:00",
     *         "updated_at": "2025-05-15 16:11:00",
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
     *   "message": "Candidate stutus not found.",
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
                'sort' => 'nullable|string|in:id,name,to_email,subject,scheduled_at,created_at,updated_at',
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

            // Build Query
            $query = CandidateStatus::query();

            //Fetch single status if id is provided
            if ($id) {

                $candidate_status = $query->findOrFail($id);
                $data = formatCandidateStatus($candidate_status);
                $data['can_edit'] = checkPermission('edit_candidate_status');
                $data['cand_delete'] = checkPermission('delete_candidate_status');

                Log::info('Single candidate status fetched via API', [
                    'candidate_Status_id' => $id,
                    'user_id' => auth()->id() ?? 'guest',
                ]);

                return formatApiResponse(
                    false,
                    'Candidate status retrieved successfully!',
                    [
                        'total' => 1,
                        'data' => [$data],
                        'permissions' => [
                            'can_edit' => $data['can_edit'],
                            'cad_delete' => $data['can_delete']
                        ]

                    ]
                );
            }

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            $total = $query->count();

            $canEdit = checkPermission('edit_candidate_status');
            $canDelete = checkPermission('delete_candidate_status');

            $candidate_statuses = $query->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($candidate_status) use ($canEdit, $canDelete) {
                    $data = formatCandidateStatus($candidate_status);
                    $data['can_edit'] = $canEdit;
                    $data['can_delete'] = $canDelete;
                    return $data;
                });

            return formatApiResponse(
                false,
                'Candidate statuses retreived successfully!',
                [
                    'total' => $total,
                    'data' => $candidate_statuses->toArray(),
                    'permissions' => [
                        'can_edit' => $canEdit,
                        'can_delete' => $canDelete
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
            Log::error('Candidate status not found in apiList', [
                'candidate_status_id' => $id,
                'exception' => $e->getMessage(),
            ]);
            return formatApiResponse(true, 'Candidate stutus not found.', [], 404);
        } catch (\Exception $e) {
            Log::error('Error in apiList', [
                'candidate_status_id' => $id,
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
