<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Services\DeletionService;
use Illuminate\Validation\ValidationException;

class NotesController extends Controller
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
        $notes = $this->user->notes();
        return view('notes.list', ['notes' => $notes]);
    }

    /**
     * Create a new note.
     *
     * This endpoint creates a new note item with the specified title, color, and description. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Note Management
     *
     * @bodyParam title string required The title of the note. Example: Meeting notes
     * @bodyParam color string required The color associated with the note. Must be one of "info", "warning", or "danger". Example: warning
     * @bodyParam description string optional A description of the note. Example: Notes from the client meeting
     *
     * @response 200 {
     * "error": false,
     * "message": "Note created successfully.",
     * "id": 44,
     * "data": {
     *   "id": 44,
     *   "title": "Test Note",
     *   "color": "info",
     *   "note_type" : "text|drawing",
     *   "drawing_data":"urlencoded(base64encoded(Svg)),
     *   "description": "test",
     *   "workspace_id": 6,
     *   "creator_id": "u_7",
     *   "created_at": "07-08-2024 16:24:57",
     *   "updated_at": "07-08-2024 16:24:57"
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "title": [
     *       "The title field is required."
     *     ],
     *     "color": [
     *       "The color must be one of the following: info, warning, danger."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the note."
     * }
     */
    public function store(Request $request)
    {

        $isApi = request()->get('isApi', false);
        $rules = [
            'note_type' => 'required|in:text,drawing',
            'title' => 'required|string',
            'color' => 'required|in:info,warning,danger',
            'description' => 'nullable|string|required_if:type,text',
            'drawing_data' => 'nullable|string|required_if:type,drawing',
        ];
        $drawingData = $request->input('drawing_data');

        if ($drawingData) {
            // Decode the base64 SVG before storing
            if ($isApi) {
                $decodedSvg = base64_decode($drawingData);
            } else {

                $decodedSvg = urldecode(base64_decode($drawingData));
            }
        } else {
            $decodedSvg = null;
        }



        try {
            $formFields = $request->validate($rules);
            $formFields['drawing_data'] = $decodedSvg;
            $formFields['workspace_id'] = $this->workspace->id;
            $formFields['creator_id'] = getGuardName() == 'client' ? 'c_' . $this->user->id : 'u_' . $this->user->id;
            $note = Note::create($formFields);
            $createdNote = Note::find($note->id);
            // Session::flash('message', 'Note created successfully.');
            return formatApiResponse(
                false,
                'Note created successfully.',
                [
                    'id' => $note->id,
                    'data' => formatNote($createdNote)
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            dd($e);
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while creating the note.'
            ], 500);
        }
    }


    /**
     * Update an existing note.
     *
     * This endpoint updates an existing note item with the specified title, color, and description. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Note Management
     *
     * @bodyParam id int required The ID of the note to be updated. Example: 1
     * @bodyParam title string required The new title of the note. Example: Meeting notes
     * @bodyParam color string required The new color of the note. Must be one of "info", "warning", or "danger". Example: warning
     * @bodyParam description string optional A new description for the note. Example: Notes from the client meeting
     *
     * @response 200 {
     * "error": false,
     * "message": "Note updated successfully.",
     * "id": 44,
     * "data": {
     *   "id": 44,
     *   "title": "Test Note",
     *   "color": "info",
     *   "description": "test",
     *   "workspace_id": 6,
     *   "creator_id": "u_7",
     *   "created_at": "07-08-2024 16:24:57",
     *   "updated_at": "07-08-2024 16:24:57"
     * }
     * }
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
     *     "color": [
     *       "The color must be one of the following: info, warning, danger."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the note."
     * }
     */
    public function update(Request $request)
    {


        $isApi = request()->get('isApi', false);
        $rules = [
            'note_type' => 'required|in:text,drawing',
            'title' => 'required|string',
            'color' => 'required|in:info,warning,danger',
            'description' => 'nullable|string|required_if:note_type,text',
            'drawing_data' => 'nullable|string|required_if:note_type,drawing',

        ];
        $drawingData = $request->input('drawing_data');

        if ($drawingData) {
            // Decode the base64 SVG before storing
            if ($isApi) {
                $decodedSvg = base64_decode($drawingData);
            } else {

                $decodedSvg = urldecode(base64_decode($drawingData));
            }
        } else {
            $decodedSvg = null;
        }

        try {
            $formFields = $request->validate($rules);
            $formFields['drawing_data'] = $decodedSvg;
            // dd($formFields);
            $note = Note::findOrFail($request->id);
            $note->update($formFields);

            // Session::flash('message', 'Note updated successfully.');
            return formatApiResponse(
                false,
                'Note updated successfully.',
                [
                    'id' => $note->id,
                    'data' => formatNote($note)
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {

            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the note.'
            ], 500);
        }
    }

    public function get($id)
    {
        $note = Note::findOrFail($id);
        return response()->json(['note' => $note]);
    }

    /**
     * List or search notes.
     *
     * This endpoint retrieves a list of notes based on various filters. The user must be authenticated to perform this action. The request allows filtering by search term and pagination parameters.
     *
     * @authenticated
     *
     * @group Note Management
     *
     * @urlParam id int optional The ID of the note to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter notes by id, title, or description. Example: Test
     * @queryParam sort string optional The field to sort by. Defaults to "is_completed". All fields are sortable. Example: created_at
     * @queryParam order string optional The sort order, either "asc" or "desc". Defaults to "desc". Example: asc
     * @queryParam limit int optional The number of notes per page for pagination. Defaults to 10. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Defaults to 0. Example: 0
     *
     * @response 200 {
     *     "error": false,
     *     "message": "Notes retrieved successfully.",
     *     "total": 1,
     *     "data": [
     *         {
     *              "id": 43,
     *              "title": "upper",
     *              "color": "warning",
     *              "description": "jhdcsd",
     *              "workspace_id": 6,
     *              "creator_id": "u_7",
     *              "created_at": "07-08-2024 16:12:13",
     *              "updated_at": "07-08-2024 16:12:13"
     *         }
     *     ]
     * }
     *
     * @response 200 {
     *     "error": true,
     *     "message": "Note not found.",
     *     "total": 0,
     *     "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Notes not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 500 {
     *     "error": true,
     *     "message": "An error occurred while retrieving the notes."
     * }
     */
    public function apiList(Request $request, $id = null)
    {
        try {
            if ($id) {
                $note = Note::find($id);

                if (!$note) {
                    return formatApiResponse(
                        false,
                        'Note not found.',
                        [
                            'total' => 0,
                            'data' => []
                        ]
                    );
                }

                return formatApiResponse(
                    false,
                    'Note retrieved successfully.',
                    [
                        'total' => 1,
                        'data' => formatNote($note)
                    ]
                );
            }

            // Extract query parameters
            $search = $request->input('search', '');
            $sort = $request->input('sort', 'created_at');
            $order = $request->input('order', 'desc');
            $limit = $request->input('limit', null);
            $offset = $request->input('offset', 0);

            // Fetch total count regardless of limit and offset
            $total = $this->user->notes($search, $sort, $order)->count();

            // Fetch paginated results
            $notes = $this->user->notes($search, $sort, $order, $limit, $offset);

            if ($notes->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Notes not found.',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }

            $formattedNotes = $notes->map(function ($note) {
                return formatNote($note);
            });

            return formatApiResponse(
                false,
                'Notes retrieved successfully.',
                [
                    'total' => $total,
                    'data' => $formattedNotes
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while retrieving the notes.'
            ], 500);
        }
    }



    /**
     * Remove the specified note.
     *
     * This endpoint deletes a note item based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Note Management
     *
     * @urlParam id int required The ID of the note to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Note deleted successfully.",
     *   "id": 1,
     *   "title": "Note Title",
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Note not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the note."
     * }
     */


    public function destroy($id)
    {
        $response = DeletionService::delete(Note::class, $id, 'Note');
        return $response;
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:notes,id' // Ensure each ID in 'ids' is an integer and exists in the notes table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];

        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $note = Note::findOrFail($id);
            // Add any additional logic you need here, such as updating related data
            $deletedIds[] = $id;
            $deletedTitles[] = $note->title; // Assuming 'title' is a field in the notes table
            DeletionService::delete(Note::class, $id, 'Note');
        }
        Session::flash('message', 'Note(s) deleted successfully.');
        return response()->json([
            'error' => false,
            'message' => 'Note(s) deleted successfully.',
            'id' => $deletedIds,
            'titles' => $deletedTitles
        ]);
    }
}
