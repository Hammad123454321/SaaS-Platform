<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\LeadFollowUp;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LeadFollowUpController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Create a new lead follow-up.
     *
     * This endpoint creates a follow-up activity (such as call, email, or meeting) for a specific lead. The date and time are converted to UTC before storing.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @bodyParam lead_id integer required The ID of the lead to associate the follow-up with. Example: 12
     * @bodyParam assigned_to integer required The ID of the user assigned to the follow-up. Example: 5
     * @bodyParam type string required The type of follow-up. One of: email, sms, call, meeting, other. Example: call
     * @bodyParam status string required The current status of the follow-up. One of: pending, completed, rescheduled. Example: pending
     * @bodyParam follow_up_at string required The follow-up date and time in local format (Y-m-d\TH:i). Example: 2025-06-20T14:30
     * @bodyParam note string optional Additional notes for the follow-up. Max 255 characters. Example: Call scheduled with client to discuss proposal.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Follow Up Created Successfully",
     *   "data": {
     *     "id": 101,
     *     "type": "lead_follow_up"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "lead_id": ["The lead id field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Follow Up Couldn't Created.",
     *   "data": {
     *     "error": "...",
     *     "line": 123,
     *     "file": "/app/Http/Controllers/LeadFollowUpController.php"
     *   }
     * }
     */

    public function store(Request $request)
    {
        $isApi = $request->get('isApi', false);

        try {

            // 🔁 Normalize API input to web-style 'follow_up_at' if needed
            if ($isApi && $request->has(['follow_up_at_date', 'follow_up_at_time'])) {
                // dd($request->input('follow_up_at_date') . 'T' . $request->input('follow_up_at_time'));
                $request->merge([
                    'follow_up_at' => $request->input('follow_up_at_date') . 'T' . $request->input('follow_up_at_time')
                ]);
            }

            $formFields =   $request->validate([
                'assigned_to' => 'required|exists:users,id',
                'lead_id' => 'required|exists:leads,id',
                'type' => 'required|in:email,sms,call,meeting,other',
                'status' => 'required|in:pending,completed,rescheduled',
                'follow_up_at' => 'required|date',
                'note' => 'nullable|string|max:255',

            ]);


            if (!empty($formFields['follow_up_at'])) {
                // Step 1: Parse as local (assume app timezone or user timezone)
                $localDate = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $formFields['follow_up_at'], config('app.timezone'));

                // Step 2: Convert to UTC
                $utcDate = $localDate->copy()->setTimezone('UTC');

                // Step 3: Format for storing in DB
                $formFields['follow_up_at'] = $utcDate->format('Y-m-d H:i:s');
            }



            $follow_up = LeadFollowUp::create($formFields);
            return formatApiResponse(
                false,
                'Follow Up Created Successfully',
                [
                    'id' => $follow_up->id,
                    'type' =>'lead_follow_up',
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (Exception $e) {

            return formatApiResponse(
                true,
                'Follow Up Couldn\'t Created.',
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

    /**
     * Retrieve a specific lead follow-up.
     *
     * This endpoint fetches the details of a lead follow-up for editing or displaying, including the related lead and assigned user.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @urlParam id integer required The ID of the follow-up to retrieve. Example: 101
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Follow Up Retrived Successfully",
     *   "follow_up": {
     *     "id": 101,
     *     "lead_id": 12,
     *     "assigned_to": 5,
     *     "type": "call",
     *     "status": "pending",
     *     "follow_up_at": "2025-06-20T09:00:00.000000Z",
     *     "note": "Discuss proposal",
     *     "lead": {
     *       "id": 12,
     *       "first_name": "John",
     *       "last_name": "Doe"
     *     },
     *     "assigned_to_user": {
     *       "id": 5,
     *       "name": "Jane Smith"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\LeadFollowUp] 101"
     * }
     */

    public function edit(string $id)
    {
        $follow_up = LeadFollowUp::findOrFail($id);
        $follow_up->load('assignedTo','lead');
        return response()->json([
            'error' => false,
            'message' => 'Follow Up Retrived Successfully',
            'follow_up' => $follow_up
        ]);
    }

    /**
     * Update an existing lead follow-up.
     *
     * This endpoint updates the details of an existing follow-up activity for a lead, including reassignment, type, and follow-up date.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @bodyParam id integer required The ID of the follow-up to update. Example: 101
     * @bodyParam assigned_to integer required The ID of the user assigned to the follow-up. Example: 6
     * @bodyParam type string required The type of follow-up. One of: email, sms, call, meeting, other. Example: email
     * @bodyParam status string required The current status. One of: pending, completed, rescheduled. Example: completed
     * @bodyParam follow_up_at string required The updated follow-up datetime in local format (Y-m-d\TH:i). Example: 2025-06-21T16:00
     * @bodyParam note string optional Optional follow-up notes. Max 255 characters. Example: Follow-up completed.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Follow Up Updated Successfully",
     *   "data": {
     *     "id": 101,
     *     "type": "lead_follow_up"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "follow_up_at": ["The follow up at must be a valid date."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Follow Up Couldn't Be Updated.",
     *   "data": {
     *     "error": "...",
     *     "line": 123,
     *     "file": "/app/Http/Controllers/LeadFollowUpController.php"
     *   }
     * }
     */

    public function update(Request $request)
    {
        $isApi = $request->get('isApi', false);

        try {
            // Find the follow-up record
            $follow_up = LeadFollowUp::findOrFail($request->id);

            // Validate input
            $formFields = $request->validate([
                'assigned_to' => 'required|exists:users,id',
                'type' => 'required|in:email,sms,call,meeting,other',
                'status' => 'required|in:pending,completed,rescheduled',
                'follow_up_at' => 'required|date',
                'note' => 'nullable|string|max:255',
            ]);

            // Handle timezone conversion for follow_up_at
            if (!empty($formFields['follow_up_at'])) {
                $localDate = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $formFields['follow_up_at'], config('app.timezone'));
                $utcDate = $localDate->copy()->setTimezone('UTC');
                $formFields['follow_up_at'] = $utcDate->format('Y-m-d H:i:s');
            }

            // Update the record
            $follow_up->update($formFields);

            return formatApiResponse(
                false,
                'Follow Up Updated Successfully',
                [
                    'id' => $follow_up->id,
                    'type' =>'lead_follow_up',
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (ModelNotFoundException $e) {

            return formatApiResponse(true, 'follow up not found.', [], 404);
        } catch (Exception $e) {
            return formatApiResponse(
                true,
                'Follow Up Couldn\'t Be Updated.',
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
     * Delete a lead follow-up.
     *
     * This endpoint deletes a follow-up record permanently from the system.
     *
     * @authenticated
     *
     * @group Leads Management
     *
     * @urlParam id integer required The ID of the follow-up to delete. Example: 101
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Lead Follow Up deleted successfully"
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Lead Follow Up not found"
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting Lead Follow Up.",
     *   "data": {
     *     "error": "...",
     *     "line": 123,
     *     "file": "/app/Services/DeletionService.php"
     *   }
     * }
     */

    public function destroy(string $id)
    {
        $response = DeletionService::delete(LeadFollowUp::class, $id, 'Lead Follow Up');
        return $response;
    }
}
