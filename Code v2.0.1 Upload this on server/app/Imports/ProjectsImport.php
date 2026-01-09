<?php

namespace App\Imports;

use App\Models\Project;
use App\Models\Status;
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

class ProjectsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithEvents
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
        if (!empty(self::$manualValidationErrors)) {
            return;
        }
        $row = $this->sanitizeAndTrim($row);
        // Pre-process row data
        $data = [
            'title' => $row['title'],
            'status_id' => $row['status_id'],
            'priority_id' => $row['priority_id'] ?? null,
            'start_date' => isset($row['start_date']) ? $row['start_date'] : null,
            'end_date' => isset($row['end_date']) ? $row['end_date'] : null,
            'budget' => str_replace(',', '', $row['budget']),
            'task_accessibility' => $row['task_accessibility'],
            'description' => $row['description'] ?? null,
            'note' => $row['note'] ?? null,
            'user_ids' => isset($row['user_ids']) ? explode(',', $row['user_ids']) : [],
            'client_ids' => isset($row['client_ids']) ? explode(',', $row['client_ids']) : [],
            'tag_ids' => isset($row['tag_ids']) ? explode(',', $row['tag_ids']) : [],
            'client_can_discuss' => $row['client_can_discuss'],
            'workspace_id' => $this->workspaceId,
            'created_by' => $this->authenticatedUserId
        ];

        // Create the project
        $new_project = Project::create($data);
        if (!$this->isAdminOrHasAllDataAccess) {
            if ($this->guardName == 'client' && !in_array($this->authenticatedUserId, $data['client_ids'])) {
                array_splice($data['client_ids'], 0, 0, $this->authenticatedUserId);
            } else if ($this->guardName == 'web' && !in_array($this->authenticatedUserId, $data['user_ids'])) {
                array_splice($data['user_ids'], 0, 0, $this->authenticatedUserId);
            }
        }
        // Attach users, clients, and tags
        $new_project->users()->attach($data['user_ids']);
        $new_project->clients()->attach($data['client_ids']);
        $new_project->tags()->attach($data['tag_ids']);

        // Additional logic for favorites and notifications
        if (isset($row['is_favorite']) && $row['is_favorite'] == 1) {
            $this->authenticatedUser->favorites()->create([
                'favoritable_type' => Project::class,
                'favoritable_id' => $new_project->id,
            ]);
        }

        // Notifications
        $notification_data = [
            'type' => 'project',
            'type_id' => $new_project->id,
            'type_title' => $new_project->title,
            'access_url' => 'projects/information/' . $new_project->id,
            'action' => 'assigned'
        ];

        $userIds = $data['user_ids'];
        $clientIds = $data['client_ids'];
        $recipients = array_merge(
            array_map(fn($userId) => 'u_' . $userId, $userIds),
            array_map(fn($clientId) => 'c_' . $clientId, $clientIds)
        );
        processNotifications($notification_data, $recipients);
        logActivity('project', $new_project->id, $new_project->title);
        return $new_project;
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
            'start_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:today'
            ],
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'budget' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $error = validate_currency_format($value, 'budget');
                    if ($error) {
                        $fail($error);
                    }
                }
            ],
            'task_accessibility' => 'required|in:project_users,assigned_users',
            'user_ids' => ['nullable', 'string', function ($attribute, $value, $fail) {
                $this->validateCommaSeparatedIds($value, 'users', $attribute, $fail);
            }],
            'client_ids' => ['nullable', 'string', function ($attribute, $value, $fail) {
                $this->validateCommaSeparatedIds($value, 'clients', $attribute, $fail);
            }],
            'tag_ids' => ['nullable', 'string', function ($attribute, $value, $fail) {
                $this->validateCommaSeparatedIds($value, 'tags', $attribute, $fail);
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
            // Skip the header row (typically rowIndex 1 in most cases)
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

            // Iterate over each cell in the row
            foreach ($cellIterator as $cell) {
                $column = $cell->getColumn();
                $value = $cell->getValue();

                // Check column and assign values
                if ($column === 'D') { // Start date column
                    $startDate = $value;
                } elseif ($column === 'E') { // End date column
                    $endDate = $value;
                } elseif ($column === 'B') { // Status ID column
                    $statusId = $value;
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
                if (
                    str_contains($error, 'must be today or a future date') ||
                    str_contains($error, 'after_or_equal') ||
                    str_contains($error, 'after')
                ) {
                    return ucfirst(str_replace('_', ' ', $field)) . " can not be in past at Row {$rowNumber}.";
                }

                break;

            case 'budget':
                return "Invalid budget at Row {$rowNumber}. Use numbers only, commas as thousand separators (e.g., 1,000), a period for decimals (e.g., 1,000.50).";
                break;

            case 'task_accessibility':
                if (str_contains($error, 'required')) {
                    return "Task accessibility is required at Row {$rowNumber}.";
                }
                if (str_contains($error, 'in')) {
                    return "Invalid task accessibility at Row {$rowNumber}. Allowed values: project_users, assigned_users.";
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

            case 'client_ids':
                // Handle multiple IDs properly and accumulate invalid IDs
                if (str_contains($error, 'Invalid')) {
                    $ids = explode(',', $value);  // Split the IDs
                    foreach ($ids as $clientId) {
                        $clientId = trim($clientId);  // Trim spaces
                        // Accumulate unique invalid IDs
                        if (!in_array($clientId, $invalidIds)) {
                            $invalidIds[] = $clientId;
                        }
                    }
                    if (!empty($invalidIds)) {
                        // Combine invalid IDs into a single error message and ensure it's only processed once
                        $invalidIdsStr = implode(',', $invalidIds);
                        if (!in_array($invalidIdsStr, $processedRows[$rowNumber])) {
                            $processedRows[$rowNumber][] = $invalidIdsStr;
                            return "Invalid client IDs {$invalidIdsStr} at Row {$rowNumber}. Clients does not exist.";
                        }
                    }
                }
                break;

            case 'tag_ids':
                // Handle multiple IDs properly and accumulate invalid IDs
                if (str_contains($error, 'Invalid')) {
                    $ids = explode(',', $value);  // Split the IDs
                    foreach ($ids as $tagId) {
                        $tagId = trim($tagId);  // Trim spaces
                        // Accumulate unique invalid IDs
                        if (!in_array($tagId, $invalidIds)) {
                            $invalidIds[] = $tagId;
                        }
                    }
                    if (!empty($invalidIds)) {
                        // Combine invalid IDs into a single error message and ensure it's only processed once
                        $invalidIdsStr = implode(',', $invalidIds);
                        if (!in_array($invalidIdsStr, $processedRows[$rowNumber])) {
                            $processedRows[$rowNumber][] = $invalidIdsStr;
                            return "Invalid tag IDs {$invalidIdsStr} at Row {$rowNumber}. Tags does not exist.";
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
