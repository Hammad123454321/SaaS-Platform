<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ResetDemoData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset demo data including database and public folder';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // dd(config('constants.ALLOW_MODIFICATION'));
        if(config('constants.ALLOW_MODIFICATION') == '1') {
            $this->info('Demo Mode is Off , skip the Database Reset !');
            exit;
        }
        $this->info('Starting demo reset process...');

        // Step 1: Drop all tables
        $this->info('Dropping all tables...');
        $this->dropAllTables();

        // Step 2: Reset the database using demo_data_taskify.sql
        $sqlFilePath = public_path('assets/demo_data/demo_data_taskify.sql');

        if (file_exists($sqlFilePath)) {
            $this->info('Resetting the database...');
            $sql = file_get_contents($sqlFilePath);

            if (!$sql) {
                $this->error('Failed to read demo_data_taskify.sql!');
                return 1;
            }

            // Disable foreign key checks and execute SQL
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::unprepared($sql);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->info('Database reset successfully!');
        } else {
            $this->error('demo_data_taskify.sql file not found!');
            return 1;
        }

        // Step 3: Delete existing public folder in storage/app
        $publicFolderPath = storage_path('app/public');
        if (File::exists($publicFolderPath)) {
            $this->info('Deleting existing public folder...');
            File::deleteDirectory($publicFolderPath);
        }

        // Step 4: Unzip public.zip into storage/app/
        $zipFilePath = public_path('assets/demo_data/public.zip');
        $extractPath = storage_path('app/public');

        if (file_exists($zipFilePath)) {
            $this->info('Unzipping public.zip...');
            $zip = new ZipArchive;

            if ($zip->open($zipFilePath) === true) {
                $zip->extractTo($extractPath);
                $zip->close();
                $this->info('Unzipped public.zip successfully!');
            } else {
                $this->error('Failed to open public.zip!');
                return 1;
            }
        } else {
            $this->error('public.zip file not found!');
            return 1;
        }

        $this->info('Demo reset process completed!');
        return 0;
    }

    /**
     * Drop all tables in the database.
     */
    private function dropAllTables()
    {
        $tables = DB::select('SHOW TABLES');

        if (empty($tables)) {
            $this->info('No tables to drop.');
            return;
        }

        $tableNames = array_map('current', $tables);
        $tableNamesString = implode(',', $tableNames);

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        foreach ($tableNames as $table) {
            DB::statement("DROP TABLE IF EXISTS `$table`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('All tables dropped successfully.');
    }
}
