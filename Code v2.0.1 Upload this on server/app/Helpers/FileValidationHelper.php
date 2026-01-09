<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class FileValidationHelper
{
    public static function validateFileUpload(Request $request, $attachmentFieldName)
    {
        $general_settings = get_settings('general_settings');
        $allowedFileTypes = $general_settings['allowed_file_types'] ?? '.png,.jpg,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt';
        $allowedFileTypesArray = array_map('trim', explode(',', $allowedFileTypes));
        $maxFilesAllowed = $general_settings['max_files'] ?? 10;

        // Check for file presence
        if ($request->hasFile($attachmentFieldName)) {
            $files = $request->file($attachmentFieldName);

            // Check if multiple files are uploaded and validate the number
            if (is_array($files)) {
                if (count($files) > $maxFilesAllowed) {
                    $errorMessage = get_label('max_files_count_allowed', 'You can only upload :count file(s).');
                    $errorMessage = str_replace(':count', $maxFilesAllowed, $errorMessage);
                    return response()->json(['error' => true, 'message' => $errorMessage], 400);
                }
            } else {
                // Make it an array for single file processing
                $files = [$files];
            }

            // Prepare allowed MIME types mapping
            $extensionToMimeType = getMimeTypeMap();
            $allowedMimeTypes = array_map(function ($ext) use ($extensionToMimeType) {
                return $extensionToMimeType[$ext] ?? null;
            }, $allowedFileTypesArray);

            // Validate each file
            foreach ($files as $file) {
                $fileExtension = strtolower($file->getClientOriginalExtension());
                $mimeType = $file->getMimeType();

                // Validate the file extension
                if (!in_array('.' . $fileExtension, $allowedFileTypesArray)) {
                    return response()->json([
                        'error' => true,
                        'message' => get_label('file_type_not_allowed', 'File type not allowed') . ': ' . $file->getClientOriginalName()
                    ], 422);
                }

                // Validate the MIME type
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    return response()->json([
                        'error' => true,
                        'message' => get_label('file_type_not_allowed', 'File type not allowed') . ': ' . $file->getClientOriginalName()
                    ], 422);
                }
            }
        }

        return true; // Return true if validation passes
    }
}
