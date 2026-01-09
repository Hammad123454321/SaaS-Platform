<?php

namespace App\Http\Controllers;
use App\Models\Tag;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class TagsController extends Controller
{
    public function index()
    {
        return view('tags.list');
    }
    /**
     * Create a new tag.
     *
     * This endpoint creates a new todo item with the specified title, color. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Tag Management
     *
     * @bodyParam title string required The title of the tag. Example: Finish report
     * @bodyParam color string required The priority of the tag. Must be one of "primary", "secondary", or "warning". Example: secondary
     *
     * @response 200 {
     * "error": false,
     * "message": "Tag created successfully.",
     * "id": 36,
     * "data": {
     *   "id": 36,
     *   "title": "test",
     *   "color": "secondary",
     *   "created_at": "07-08-2024 16:30:09",
     *   "updated_at": "07-08-2024 16:30:09"
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
     *       "The color must be one of the following: primary, secondary, warning, info, dark, success, danger."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the todo."
     * }
     */
    public function store(Request $request)
    {

        $isApi = $request->get('isApi', false);
        try {
            $formFields = $request->validate([
                'title' => ['required'],
                'color' => ['required']
            ]);
            $slug = generateUniqueSlug($request->title, Tag::class);
            $formFields['slug'] = $slug;
            $tag = Tag::create($formFields);
            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Tag created successfully',

                    [
                        'id' => $tag->id,
                        'data' => [
                            'id' => $tag->id,
                            'title' => $tag->title,
                            'color' => $tag->color,
                            'created_at' => format_date($tag->created_at, to_format: 'Y-m-d'),
                            'updated_at' => format_date($tag->updated_at, to_format: 'Y-m-d'),
                        ]

                    ]
                );
            }
            return response()->json(['error' => false, 'message' => 'Tag created successfully.', 'id' => $tag->id, 'tag' => $tag]);
        } catch (\Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'An error occurred while creating the tag',
                    [],
                    500
                );
            }
            return response()->json(['error' => true, 'message' => 'Tag couldn\'t be created.']);
        }
    }
    public function list()
    {
        $search = request('search');
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $tags = Tag::orderBy($sort, $order);
        if ($search) {
            $tags = $tags->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $tags->count();
        // Check permissions
        $canEdit = checkPermission('edit_tags');
        $canDelete = checkPermission('delete_tags');
        $tags = $tags
            ->paginate(request("limit"))
            ->through(function ($tag) use ($canEdit, $canDelete) {
            $actions = '';
                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-tag" data-bs-toggle="modal" data-bs-target="#edit_tag_modal" data-id="' . $tag->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
            }
                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $tag->id . '" data-type="tags">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }
                $actions = $actions ?: '-';
                return [
                    'id' => $tag->id,
                    'title' => $tag->title,
                    'color' => '<span class="badge bg-' . $tag->color . '">' . $tag->title . '</span>',
                    'created_at' => format_date($tag->created_at, true),
                    'updated_at' => format_date($tag->updated_at, true),
                    'actions' => $actions,
                ];
            });
        return response()->json([
            "rows" => $tags->items(),
            "total" => $total,
        ]);
    }
    /**
     * List or search tags.
     *
     * This endpoint retrieves a list of tags based on various filters. The user must be authenticated to perform this action. The request allows searching and sorting by different parameters.
     *
     * @authenticated
     *
     * @group Tag Management
     *
     * @urlParam id int optional The ID of the tag to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter tags by title or id. Example: Urgent
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, title, created_at, and updated_at. Example: title
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam limit int optional The number of tags per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Tags retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Urgent",
     *       "color": "primary",
     *       "created_at": "20-07-2024 17:50:09",
     *       "updated_at": "21-07-2024 19:08:16"
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Tag not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Tags not found",
     *   "total": 0,
     *   "data": []
     * }
     */
    public function apiList(Request $request, $id = '')
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $tagsQuery = Tag::query();
        // Apply search filter
        if ($search) {
            $tagsQuery->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        if ($id) {
            $tag = $tagsQuery->find($id);
            if (!$tag) {
                return formatApiResponse(
                    false,
                    'Tag not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            return formatApiResponse(
                false,
                'Tag retrieved successfully',
                [
                    'total' => 1,
                    'data' => [
                        [
                            'id' => $tag->id,
                            'title' => $tag->title,
                            'color' => $tag->color,
                            'created_at' => format_date($tag->created_at, to_format: 'Y-m-d'),
                            'updated_at' => format_date($tag->updated_at, to_format: 'Y-m-d'),
                        ]
                    ]
                ]
            );
        } else {
            $total = $tagsQuery->count(); // Get total count before applying offset and limit
            $tags = $tagsQuery->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get();
            if ($tags->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Tags not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $data = $tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'title' => $tag->title,
                    'color' => $tag->color,
                    'created_at' => format_date($tag->created_at, to_format: 'Y-m-d'),
                    'updated_at' => format_date($tag->updated_at, to_format: 'Y-m-d'),
                ];
            });
            return formatApiResponse(
                false,
                'Tags retrieved successfully',
                [
                    'total' => $total,
                    'data' => $data
                ]
            );
        }
    }
    public function get($id)
    {
        $tag = Tag::findOrFail($id);
        return response()->json(['tag' => $tag]);
    }

    /**
     * Update an existing tag.
     *
     * This endpoint updates an existing tag item with the specified title, color. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Tag Management
     *
     * @bodyParam id int required The ID of the todo to be updated. Example: 1
     * @bodyParam title string required The new title of the todo. Example: Finish report
     * @bodyParam color string required The new priority of the todo. Must be one of "primary", "secondary", "warning", "dark", "info","danger" or "success". Example: secondary
     *
     * @response 200 {
     * "error": false,
     * "message": "Tag updated successfully.",
     * "id": "36",
     * "data": {
     *   "id": 36,
     *   "title": "test",
     *   "color": "secondary",
     *   "created_at": "07-08-2024 16:30:09",
     *   "updated_at": "07-08-2024 16:30:09"
     * }
     * }
     *
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
     *       "The color must be one of the following: secondary, primary, info, warning, dark, danger."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the tag."
     * }
     */
    public function update(Request $request)
    {
        $isApi = $request->get('isApi', false);
        try {
            $formFields = $request->validate([
                'id' => ['required'],
                'title' => ['required'],
                'color' => ['required']
            ]);
            $slug = generateUniqueSlug($request->title, Tag::class, $request->id);
            $formFields['slug'] = $slug;
            $tag = Tag::findOrFail($request->id);
            if ($tag->update($formFields)) {
                if ($isApi) {
                    return formatApiResponse(
                        false,
                        'Tag updated successfully',
                        [
                            'id' => $tag->id,
                            'data' => [
                                'id' => $tag->id,
                                'title' => $tag->title,
                                'color' => $tag->color,
                                'created_at' => format_date($tag->created_at, to_format: 'Y-m-d'),
                                'updated_at' => format_date($tag->updated_at, to_format: 'Y-m-d'),
                            ]

                        ]
                    );
                } else {
                    return response()->json(['error' => false, 'message' => 'Tag updated successfully.', 'id' => $tag->id]);
                }
            } else {
                if ($isApi) {
                    return formatApiResponse(
                        true,
                        'Tag couldn\'t be updated',
                        [],
                        500
                    );
                } else {
                    return response()->json(['error' => true, 'message' => 'Tag couldn\'t updated.']);
                }
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            if ($isApi) {
                return formatApiResponse(
                    true,
                    'An error occurred while updating the tag' . $e->getMessage(),
                    [],
                    500
                );
            }
            return response()->json(['error' => true, 'message' => 'An error occurred while updating the tag' . $e->getMessage()]);
        }
    }
    /**
     * Remove the specified tag.
     *
     * This endpoint deletes a tag item based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Tag Management
     *
     * @urlParam id int required The ID of the todo to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Tag deleted successfully.",
     *   "id": 1,
     *   "title": "Tag Title"
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Tag not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the tag."
     * }
     */
    public function destroy($id)
    {
        $response = DeletionService::delete(Tag::class, $id, 'Tag');
        return $response;
    }
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:tags,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);
        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $tag = Tag::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $tag->title;
            DeletionService::delete(Tag::class, $id, 'Tag');
        }
        return response()->json(['error' => false, 'message' => 'Tag(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
}
