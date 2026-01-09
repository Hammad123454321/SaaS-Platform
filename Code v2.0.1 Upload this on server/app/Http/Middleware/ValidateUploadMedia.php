<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class ValidateUploadMedia
{
    public function handle(Request $request, Closure $next)
    {
        // Define the allowed file types and maximum number of files
        $general_settings = get_settings('general_settings');
        $allowedFileTypes = $general_settings['allowed_file_types'] ?? '.png,.jpg,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt';
        $allowedFileTypesArray = array_map('trim', explode(',', $allowedFileTypes));
        $maxFilesAllowed = $general_settings['max_files'] ?? '10';

        // Validate the files
        if ($request->hasFile('media_files')) {
            $files = $request->file('media_files');

            // Check the number of files
            if (count($files) > $maxFilesAllowed) {
                $errorMessage = get_label('max_files_count_allowed', 'You can only upload :count file(s).');
                $errorMessage = str_replace(':count', $maxFilesAllowed, $errorMessage);
                return response()->json(['error' => true, 'message' => $errorMessage], 400);
            }

            // Prepare allowed MIME types mapping
            $extensionToMimeType = getMimeTypeMap();
            $allowedMimeTypes = array_map(function ($ext) use ($extensionToMimeType) {
                return $extensionToMimeType[$ext] ?? null;
            }, $allowedFileTypesArray);

            // Check the file types and contents
            foreach ($files as $file) {
                $fileExtension = strtolower($file->getClientOriginalExtension());
                $mimeType = $file->getMimeType();

                // Validate the file extension
                if (!in_array('.' . $fileExtension, $allowedFileTypesArray)) {
                    return response()->json([
                        'error' => true,
                        'message' => get_label('file_type_not_allowed', 'File type not allowed') . ': ' . $file->getClientOriginalName()
                    ], 400);
                }

                // Validate the MIME type
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    return response()->json([
                        'error' => true,
                        'message' => get_label('file_type_not_allowed', 'File type not allowed') . ': ' . $file->getClientOriginalName()
                    ], 400);
                }
            }
        }

        return $next($request);
    }
}
