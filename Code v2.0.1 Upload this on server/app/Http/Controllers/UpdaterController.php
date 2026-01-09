<?php

namespace App\Http\Controllers;

use ZipArchive;
use App\Models\Update;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class UpdaterController extends Controller
{
    public function index()
    {
        return view('settings.system_updater');
    }

    public function is_dir_empty($dir)
    {
        if (!is_readable($dir)) {
            return null;
        }
        return (count(scandir($dir)) == 2);
    }

    public function update(Request $request)
    {
        ini_set('max_execution_time', 900);
        Log::channel('update')->info("🟢 System update process started.");

        $zip = new ZipArchive();
        $updatePath = Config::get('constants.UPDATE_PATH');
        $fullUpdatePath = public_path($updatePath);

        if (!empty($_FILES['update_file']['name'][0])) {

            Log::channel('update')->info("🟢 Update file detected, starting upload process.");

            if (!File::exists($fullUpdatePath)) {
                File::makeDirectory($fullUpdatePath, 0777, true);
                Log::channel('update')->info("✅ Update directory created at: $fullUpdatePath");
            }

            $uploadData = $request->file('update_file.0');
            $ext = strtolower($uploadData->getClientOriginalExtension());

            if ($ext !== "zip") {
                Log::channel('update')->error("❌ Invalid file extension: .$ext. Only zip files are allowed.");
                return response()->json(["error" => true, "message" => "Please insert a valid Zip File."]);
            }

            if ($uploadData->move($fullUpdatePath)) {
                $filename = $uploadData->getFilename();
                Log::channel('update')->info("✅ File uploaded successfully: $filename");

                $res = $zip->open($fullUpdatePath . $filename);
                if ($res === true) {
                    $extractPath = $fullUpdatePath;
                    $zip->extractTo($extractPath);
                    $zip->close();
                    Log::channel('update')->info("✅ Zip file extracted to: $extractPath");

                    if (file_exists($updatePath . "package.json") || file_exists($updatePath . "plugin/package.json")) {

                        $system_info = get_system_update_info();
                        if (isset($system_info['updated_error']) || isset($system_info['sequence_error'])) {
                            Log::channel('update')->error("❌ System update info error: " . $system_info['message']);
                            File::deleteDirectory($fullUpdatePath);
                            return response()->json(['error' => true, 'message' => $system_info['message']]);
                        }

                        $sub_directory = file_exists($updatePath . "plugin/package.json") ? "plugin/" : "";
                        $packagePath = $updatePath . $sub_directory . "package.json";

                        if (file_exists($packagePath)) {
                            $package_data = json_decode(file_get_contents($packagePath), true);

                            if (!empty($package_data)) {
                                Log::channel('update')->info("✅ Package data loaded: " . json_encode($package_data));

                                // Folders Creation
                                if (isset($package_data['folders'])) {
                                    $foldersJsonPath = $updatePath . $sub_directory . $package_data['folders'];
                                    if (file_exists($foldersJsonPath)) {
                                        $folders = json_decode(file_get_contents($foldersJsonPath), true);
                                        foreach ($folders as $key => $line) {
                                            $destination = base_path($line);
                                            if (!is_dir($destination)) {
                                                mkdir($destination, 0777, true);
                                                Log::channel('update')->info("✅ Created folder: $destination");
                                            }
                                        }
                                    }
                                }

                                // Files Copy
                                if (isset($package_data['files'])) {
                                    $filesJsonPath = $updatePath . $sub_directory . $package_data['files'];
                                    if (file_exists($filesJsonPath)) {
                                        $files = json_decode(file_get_contents($filesJsonPath), true);
                                        foreach ($files as $source => $destinationRelative) {
                                            $sourcePath = $fullUpdatePath . $sub_directory . $source;
                                            $destination = base_path($destinationRelative);
                                            $destinationDir = dirname($destination);

                                            if (!is_dir($destinationDir)) {
                                                mkdir($destinationDir, 0755, true);
                                            }
                                            if (file_exists($sourcePath)) {
                                                copy($sourcePath, $destination);
                                                Log::channel('update')->info("✅ Copied file: $sourcePath to $destination");
                                            }
                                        }
                                    }
                                }

                                // Archives Extraction
                                if (isset($package_data['archives'])) {
                                    $archivesJsonPath = $updatePath . $sub_directory . $package_data['archives'];
                                    if (file_exists($archivesJsonPath)) {
                                        $archives = json_decode(file_get_contents($archivesJsonPath), true);
                                        foreach ($archives as $source => $destinationRelative) {
                                            $sourcePath = $fullUpdatePath . $sub_directory . $source;
                                            $destination = base_path($destinationRelative);
                                            $archiveZip = new ZipArchive;
                                            if ($archiveZip->open($sourcePath) === TRUE) {
                                                $archiveZip->extractTo($destination);
                                                $archiveZip->close();
                                                Log::channel('update')->info("✅ Extracted archive: $sourcePath to $destination");
                                            } else {
                                                Log::channel('update')->error("❌ Failed to open archive: $sourcePath");
                                            }
                                        }
                                    }
                                }

                                // Run migrations
                                $migrationDir = $fullUpdatePath . $sub_directory . 'update-files/database/migrations';
                                $migrationPath = 'public/' . $updatePath . $sub_directory . 'update-files/database/migrations';
                                if (is_dir($migrationDir)) {
                                    try {
                                        Artisan::call('migrate', ['--path' => $migrationPath]);
                                        Log::channel('update')->info("✅ Migrations run from path: $migrationPath");
                                    } catch (\Throwable $e) {
                                        Log::channel('update')->error("❌ Migration error: " . $e->getMessage());
                                    }
                                }

                                // Run manual queries
                                if (!empty($package_data['manual_queries']) && !empty($package_data['query_path'])) {
                                    try {
                                        $sqlContent = File::get($fullUpdatePath . $package_data['query_path']);
                                        $queries = explode(';', $sqlContent);
                                        foreach ($queries as $query) {
                                            $query = trim($query);
                                            if (!empty($query)) {
                                                DB::statement($query);
                                            }
                                        }
                                        Log::channel('update')->info("✅ Manual SQL queries executed from: " . $package_data['query_path']);
                                    } catch (\Throwable $e) {
                                        Log::channel('update')->error("❌ Manual SQL query error: " . $e->getMessage());
                                    }
                                }

                                // Save update version
                                Update::create(['version' => $system_info['file_current_version']]);
                                Log::channel('update')->info("✅ Update version saved: " . $system_info['file_current_version']);

                                File::deleteDirectory($fullUpdatePath);
                                Log::channel('update')->info("✅ Update directory cleaned up.");

                                Artisan::call('cache:clear');
                                Artisan::call('config:clear');
                                Artisan::call('route:clear');
                                Artisan::call('view:clear');
                                Log::channel('update')->info("✅ Application caches cleared.");

                                Log::channel('update')->info("🟩 System updated successfully to version " . $package_data['version']);

                                return response()->json([
                                    'error' => false,
                                    'message' => 'Congratulations! Version ' . $package_data['version'] . ' is successfully installed.'
                                ]);
                            } else {
                                Log::channel('update')->error("❌ Invalid package installer file, missing package data.");
                                File::deleteDirectory($fullUpdatePath);
                                return response()->json(['error' => true, 'message' => 'Invalid plugin installer file!. No package data found / missing package data.']);
                            }
                        }
                    } else {
                        Log::channel('update')->error("❌ Invalid update file, missing package.json.");
                        File::deleteDirectory($fullUpdatePath);
                        return response()->json(['error' => true, 'message' => 'Invalid update file! It seems like you are trying to update the system using the wrong file.']);
                    }
                } else {
                    Log::channel('update')->error("❌ Extraction failed for uploaded file.");
                    return response()->json(['error' => true, 'message' => "Extraction failed."]);
                }
            } else {
                Log::channel('update')->error("❌ File upload failed: " . $uploadData->getErrorString());
                return response()->json(['error' => true, 'message' => $uploadData->getErrorString()]);
            }
        } else {
            Log::channel('update')->error("❌ No file selected for update upload.");
            return response()->json(['error' => true, 'message' => 'You did not select a file to upload.']);
        }
    }
}
