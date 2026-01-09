<?php

namespace App\Imports;

use App\Models\Task;
use App\Models\Status;
use App\Models\Project;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Carbon\Carbon;

class TasksImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithEvents
{
    use SkipsFailures;
    private $workspaceId;
    private $authenticatedUserId;
    private $authenticatedUser;
    private $guardName;
    private $isAdminOrHasAllDataAccess;
    // To store validation errors
    public $validationErrors = [];
    public static $manualValidationErrors = [];

    public function __construct()
    {
        $this->workspaceId = getWorkspaceId();
        $this->authenticatedUserId = getAuthenticatedUser()->id;
        $this->authenticatedUser = getAuthenticatedUser();
        $this->guardName = getGuardName();
        $this->isAdminOrHasAllDataAccess = isAdminOrHasAllDataAccess();
    }
    public function model(array $row)
    {
        $row = $this->sanitizeAndTrim($row);
        // Pre-process row data
        $data = [
            'title' => $row['title'],
            'status_id' => $row['status_id'],
            'priority_id' => $row['priority_id'] ?? null,
            'project_id' => $row['project_id'],
            'start_date' => isset($row['start_date']) ? $row['start_date'] : null,
            'due_date' => isset($row['end_date']) ? $row['end_date'] : null,
            'description' => $row['description'] ?? null,
            'note' => $row['note'] ?? null,
            'user_ids' => isset($row['user_ids']) ? explode(',', $row['user_ids']) : [],
            'client_can_discuss' => $row['client_can_discuss'],
            'workspace_id' => $this->workspaceId,
            'created_by' => $this->authenticatedUserId
        ];

        // Create the project
        $new_task = Task::create($data);
        if (!$this->isAdminOrHasAllDataAccess) {
            if ($this->guardName == 'web' && !in_array($this->authenticatedUserId, $data['user_ids'])) {
                array_splice($data['user_ids'], 0, 0, $this->authenticatedUserId);
            }
        }
        // Attach users, clients, and tags
        $new_task->users()->attach($data['user_ids']);

        // Additional logic for favorites and notifications
        if (isset($row['is_favorite']) && $row['is_favorite'] == 1) {
            $this->authenticatedUser->favorites()->create([
                'favoritable_type' => Task::class,
                'favoritable_id' => $new_task->id,
            ]);
        }

        // Notifications
        $notification_data = [
            'type' => 'task',
            'type_id' => $new_task->id,
            'type_title' => $new_task->title,
            'access_url' => 'tasks/information/' . $new_task->id,
            'action' => 'assigned'
        ];

        $userIds = $data['user_ids'];
        $recipients = array_map(function ($userId) {
            return 'u_' . $userId;
        }, $userIds);
        processNotifications($notification_data, $recipients);
        logActivity('task', $new_task->id, $new_task->title, parentId: $data['project_id'], parentType: 'project');
        return $new_task;
    }

    private function sanitizeAndTrim(array $row): array
    {
        $allowedTags = '
            <a><abbr><acronym><address><b><bdo><blockquote><br><caption><cite>
            <code><col><colgroup><dd><del><dfn><div><dl><dt><em><h1><h2><h3><h4>
            <h5><h6><hr><i><img><ins><kbd><label><legend><li><object><ol><p>
            <pre><q><s><samp><small><span><strike><strong><sub><sup><table>
            <tbody><td><tfoot><th><thead><tr><tt><u><ul><var>';

        return Arr::map($row, function ($value) use ($allowedTags) {
            // Only sanitize and trim strings
            if (is_string($value)) {
                return trim(strip_tags($value, $allowedTags));
            }
            return $value;
        });
    }

    public function rules(): array
    {
        return [
            'title' => 'required',
            'status_id' => 'required',
            'priority_id' => 'nullable|exists:priorities,id',
            'project_id' => [
                'required',
                'exists:projects,id',
                function ($attribute, $value, $fail) {
                    if (!$this->isAdminOrHasAllDataAccess && !$this->authenticatedUser->projects->contains($value)) {
                        $fail('You are not a participant of this project.');
                    }
                },
            ],
            'start_date' => [
                'nullable',
                'date_format:Y-m-d'
            ],
            'end_date' => 'nullable|date_format:Y-m-d',
            'user_ids' => ['nullable', 'string', function ($attribute, $value, $fail) {
                $this->validateCommaSeparatedIds($value, 'users', $attribute, $fail);
            }],
            'client_can_discuss' => 'required|boolean'
        ];
    }

    // Handle validation failures
    public function onFailure(...$failures)
    {
        foreach ($failures as $failure) {
            foreach ($failure->errors() as $error) {
                $rowNumber = $failure->row(); // Get row number
                $field = $failure->attribute(); // Get the field name
                $value = $failure->values()[$field] ?? null; // Get the invalid value

                // Format error message
                $message = $this->formatErrorMessage($field, $error, $rowNumber, $value);

                // Store the error message in the validationErrors array
                $this->validationErrors[] = $message;
            }
        }
    }



    // Custom message formatter
    protected function validateCommaSeparatedIds($value, $table, $attribute, $fail)
    {
        if ($value) {
            $ids = explode(',', $value); // Convert the string to an array
            foreach ($ids as $id) {
                $id = trim($id); // Trim any extra spaces

                // Validate each ID exists in the specified table
                $exists = DB::table($table)->where('id', $id)->exists();
                if (!$exists) {
                    // Use the singular ID value to indicate which ID is invalid
                    $fail("Invalid {$attribute} ID {$id}. {$table} ID does not exist.");
                }
            }
        }
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => [self::class, 'beforeImport'],
        ];
    }

    public static function beforeImport(BeforeImport $event)
    {
        // Access the reader and get the first sheet
        $sheet = $event->getReader()->getSheet(0);

        // Initialize arrays to store manual validation errors and seen entries
        $manualValidationErrors = [];

        // Iterate over each row in the sheet
        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            // Skip the first row (headers)
            if ($rowIndex === 1) {
                continue;
            }
            // Get the row's cell iterator (to access each cell)
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false); // Iterate over all cells

            // Initialize variables for required fields
            $startDate = null;
            $endDate = null;
            $statusId = null;
            $projectId = null;
            $userIds = null;

            // Iterate over each cell in the row
            foreach ($cellIterator as $cell) {
                $column = $cell->getColumn();
                $value = $cell->getValue();

                // Check column and assign values
                if ($column === 'E') { // Start date column
                    $startDate = $value;
                } elseif ($column === 'F') { // End date column
                    $endDate = $value;
                } elseif ($column === 'B') { // Status ID column
                    $statusId = $value;
                } elseif ($column === 'I') { // User IDs column
                    $userIds = $value;
                } elseif ($column === 'D') { // Project ID column
                    $projectId = $value;
                }
            }
            $status = Status::find($statusId);
            if ($status) {
                if (!canSetStatus($status)) {
                    $manualValidationErrors[] = "Not authorized to set status ID $statusId. at row " . ($rowIndex) . ".";
                }
            } else {
                $manualValidationErrors[] = "Invalid Status ID $statusId. at row " . ($rowIndex) . ". Status does not exist.";
            }
            if ($startDate && $endDate) {
                $startDate = Carbon::parse($startDate);
                $endDate = Carbon::parse($endDate);
                if ($startDate->gt($endDate)) {
                    $manualValidationErrors[] = "Start date must be less than or equal to end date at row " . ($rowIndex) . ".";
                }
            }
            // Validate User Participation in Project
            if ($projectId && $userIds) {
                $project = Project::with('users')->find($projectId);
                if ($project) {
                    $userIdsArray = explode(',', $userIds); // Assuming user IDs are comma-separated
                    $projectUserIds = $project->users->pluck('id')->toArray();
                    $invalidUserIds = array_diff($userIdsArray, $projectUserIds);

                    if (!empty($invalidUserIds)) {
                        $manualValidationErrors[] = "The following users are not participants of project ID $projectId at row " . ($rowIndex) . ": " . implode(', ', $invalidUserIds) . ".";
                    }
                }
            }
        }

        // After processing all rows, assign the errors to the static property if needed
        self::$manualValidationErrors = $manualValidationErrors;
    }

    private function formatErrorMessage($field, $error, $rowNumber, $value = null)
    {
        static $processedRows = [];  // Static variable to keep track of processed rows

        // Initialize the error message for the current row if not already processed
        if (!isset($processedRows[$rowNumber])) {
            $processedRows[$rowNumber] = [];
        }

        $invalidIds = [];  // Array to accumulate invalid IDs for each field

        switch ($field) {
            case 'title':
                if (str_contains($error, 'required')) {
                    return "Title is required at Row {$rowNumber}.";
                }
                break;

            case 'status_id':
                if (str_contains($error, 'required')) {
                    return "Status ID is required at Row {$rowNumber}.";
                }
                break;

            case 'project_id':
                if (str_contains($error, 'required')) {
                    return "Project ID is required at Row {$rowNumber}.";
                }
                if (str_contains($error, 'invalid')) {
                    return "Invalid project ID {$value} at Row {$rowNumber}. Project does not exist.";
                }
                if (str_contains($error, 'participant')) {
                    return "You are not a participant of project with ID {$value} at row {$rowNumber}.";
                }
                break;

            case 'priority_id':
                if (str_contains($error, 'invalid')) {
                    return "Invalid priority ID {$value} at Row {$rowNumber}. Priority does not exist.";
                }
                break;

            case 'start_date':
            case 'end_date':
                if (str_contains($error, 'match')) {
                    return "Invalid date format for " . str_replace('_', ' ', $field) . " at Row {$rowNumber}. Expected format: YYYY-MM-DD (e.g., 2024-01-01).";
                }
                break;

            case 'user_ids':
                // Handle multiple IDs properly and accumulate invalid IDs
                if (str_contains($error, 'Invalid')) {
                    $ids = explode(',', $value);  // Split the IDs
                    foreach ($ids as $userId) {
                        $userId = trim($userId);  // Trim spaces
                        // Accumulate unique invalid IDs
                        if (!in_array($userId, $invalidIds)) {
                            $invalidIds[] = $userId;
                        }
                    }
                    if (!empty($invalidIds)) {
                        // Combine invalid IDs into a single error message and ensure it's only processed once
                        $invalidIdsStr = implode(',', $invalidIds);
                        if (!in_array($invalidIdsStr, $processedRows[$rowNumber])) {
                            $processedRows[$rowNumber][] = $invalidIdsStr;
                            return "Invalid user IDs {$invalidIdsStr} at Row {$rowNumber}. Users does not exist.";
                        }
                    }
                }
                break;
            case 'client_can_discuss':
                if (str_contains($error, 'required')) {
                    return "Client can discuss is required at Row {$rowNumber}.";
                }
                if (str_contains($error, 'boolean')) {
                    return "Client can discuss must be a boolean value (0 or 1) at Row {$rowNumber}.";
                }
                break;

            default:
                return "Invalid data in field '{$field}' at Row {$rowNumber}.";
        }

        // Default fallback for other cases
        return null;
    }

    // Method to fetch all validation errors (including manual errors)
    public function getValidationErrors()
    {
        // Merge validation errors with the flattened manual validation errors
        $mergedErrors = array_merge($this->validationErrors, self::$manualValidationErrors);

        return $mergedErrors;
    }

    // Method to get manual validation errors
    public function getManualValidationErrors()
    {
        return self::$manualValidationErrors;
    }
}
