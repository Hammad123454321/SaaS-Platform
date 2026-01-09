<?php

use Carbon\Carbon;
use App\Models\Tax;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Status;
use App\Models\Update;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Setting;
use App\Models\FcmToken;
use App\Models\Template;
use App\Models\Candidate;
use App\Models\Workspace;
use App\Models\ActivityLog;
use App\Models\CustomField;
use App\Models\LeaveEditor;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\LeaveRequest;
use App\Models\Notification;
use Chatify\ChatifyMessenger;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\UserClientPreference;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Twilio\Rest\Client as TwilioClient;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;
use App\Notifications\AssignmentNotification;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;


if (!function_exists('get_timezone_array')) {
    // 1.Get Time Zone
    function get_timezone_array()
    {
        $list = DateTimeZone::listAbbreviations();
        $idents = DateTimeZone::listIdentifiers();
        $data = $offset = $added = array();
        foreach ($list as $abbr => $info) {
            foreach ($info as $zone) {
                if (
                    !empty($zone['timezone_id'])
                    and
                    !in_array($zone['timezone_id'], $added)
                    and
                    in_array($zone['timezone_id'], $idents)
                ) {
                    $z = new DateTimeZone($zone['timezone_id']);
                    $c = new DateTime("", $z);
                    $zone['time'] = $c->format('h:i A');
                    $offset[] = $zone['offset'] = $z->getOffset($c);
                    $data[] = $zone;
                    $added[] = $zone['timezone_id'];
                }
            }
        }
        array_multisort($offset, SORT_ASC, $data);
        $i = 0;
        $temp = array();
        foreach ($data as $key => $row) {
            $temp[0] = $row['time'];
            $temp[1] = formatOffset($row['offset']);
            $temp[2] = $row['timezone_id'];
            $options[$i++] = $temp;
        }
        return $options;
    }
}
if (!function_exists('formatOffset')) {
    function formatOffset($offset)
    {
        $hours = $offset / 3600;
        $remainder = $offset % 3600;
        $sign = $hours > 0 ? '+' : '-';
        $hour = (int) abs($hours);
        $minutes = (int) abs($remainder / 60);
        if ($hour == 0 and $minutes == 0) {
            $sign = ' ';
        }
        return $sign . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0');
    }
}
if (!function_exists('relativeTime')) {
    function relativeTime($time)
    {
        if (!ctype_digit($time))
            $time = strtotime($time);
        $d[0] = array(1, "second");
        $d[1] = array(60, "minute");
        $d[2] = array(3600, "hour");
        $d[3] = array(86400, "day");
        $d[4] = array(604800, "week");
        $d[5] = array(2592000, "month");
        $d[6] = array(31104000, "year");
        $w = array();
        $return = "";
        $now = time();
        $diff = ($now - $time);
        $secondsLeft = $diff;
        for ($i = 6; $i > -1; $i--) {
            $w[$i] = intval($secondsLeft / $d[$i][0]);
            $secondsLeft -= ($w[$i] * $d[$i][0]);
            if ($w[$i] != 0) {
                $return .= abs($w[$i]) . " " . $d[$i][1] . (($w[$i] > 1) ? 's' : '') . " ";
            }
        }
        $return .= ($diff > 0) ? "ago" : "left";
        return $return;
    }
}
if (!function_exists('get_settings')) {
    function get_settings($variable, $default = null)
    {

        static $settings = null;
        // if ($settings === null) {
        //     // Cache forever (clear when settings change)
        //     // $settings = Cache::remember('settings_cache', now()->addMinutes(20), function () {
        //     return Setting::pluck('value', 'variable')->toArray();
        //     // });
        // }
        // dd($settings);
        $settings =  Setting::pluck('value', 'variable')->toArray();
        $value = $settings[$variable] ?? $default;

        if ($value && is_string($value) && isJson($value)) {
            return json_decode($value, true);
        }

        return $value;
    }
}
if (!function_exists('isJson')) {
    function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
if (!function_exists('create_label')) {
    function create_label($variable, $title = '', $locale = '')
    {
        if ($title == '') {
            $title = $variable;
        }
        $value = htmlspecialchars(get_label($variable, $title, $locale), ENT_QUOTES, 'UTF-8');
        return "
        <div class='mb-3 col-md-6'>
                    <label class='form-label' for='$variable'>$title</label>
                    <div class='input-group input-group-merge'>
                        <input type='text' name='$variable' class='form-control' value='$value'>
                    </div>
                </div>
        ";
    }
}
if (!function_exists('get_label')) {
    function get_label($label, $default, $locale = '')
    {
        // Check if the database connection is available
        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Exception $e) {
            $dbConnected = false;
        }
        // Only fetch general settings if the database is connected
        $general_settings = $dbConnected ? get_settings('general_settings') : [];
        if ($dbConnected && (!isset($general_settings['priLangAsAuth']) || $general_settings['priLangAsAuth'] == 1 && (Request::is('forgot-password') || Request::is('/') || Request::segment(1) == 'reset-password' || Request::is('signup')))) {
            // Get the default language set by the first admin
            $mainAdminId = getMainAdminId();
            $adminLang = DB::table('users')
                ->where('id', $mainAdminId)
                ->value('lang');
            // If a locale is not provided, use the admin's language as fallback
            if (empty($locale)) {
                $locale = $adminLang ?: app()->getLocale(); // Use admin's language or default app locale
            }
        } else {
            // Use default app locale if DB is not connected
            $locale = $locale ?: app()->getLocale();
        }
        // Check if the label exists in the requested locale
        if (Lang::has('labels.' . $label, $locale)) {
            return trans('labels.' . $label, [], $locale);
        } else {
            return $default;
        }
    }
}
if (!function_exists('empty_state')) {
    function empty_state($url)
    {
        return "
    <div class='card text-center'>
    <div class='card-body'>
        <div class='misc-wrapper'>
            <h2 class='mb-2 mx-2'>Data Not Found </h2>
            <p class='mb-4 mx-2'>Oops! 😖 Data doesn't exists.</p>
            <a href='/$url' class='btn btn-primary'>Create now</a>
            <div class='mt-3'>
                <img src='../assets/img/illustrations/page-misc-error-light.png' alt='page-misc-error-light' width='500' class='img-fluid' data-app-dark-img='illustrations/page-misc-error-dark.png' data-app-light-img='illustrations/page-misc-error-light.png' />
            </div>
        </div>
    </div>
</div>";
    }
}
if (!function_exists('format_date')) {
    function format_date($date, $time = false, $from_format = null, $to_format = null, $apply_timezone = true)
    {
        if ($date) {
            $from_format = $from_format ?? 'Y-m-d';
            $to_format = $to_format ?? get_php_date_time_format();
            $time_format = get_php_date_time_format(true);
            if ($time) {
                if ($apply_timezone) {
                    if (!$date instanceof \Carbon\Carbon) {
                        $dateObj = \Carbon\Carbon::createFromFormat($from_format . ' H:i:s', $date)
                            ->setTimezone(config('app.timezone'));
                    } else {
                        $dateObj = $date->setTimezone(config('app.timezone'));
                    }
                } else {
                    if (!$date instanceof \Carbon\Carbon) {
                        $dateObj = \Carbon\Carbon::createFromFormat($from_format . ' H:i:s', $date);
                    } else {
                        $dateObj = $date;
                    }
                }
            } else {
                if (!$date instanceof \Carbon\Carbon) {
                    $dateObj = \Carbon\Carbon::createFromFormat($from_format, $date);
                } else {
                    $dateObj = $date;
                }
            }
            $timeFormat = $time ? ' ' . $time_format : '';
            $date = $dateObj->format($to_format . $timeFormat);
            return $date;
        } else {
            return '-';
        }
    }
}
if (!function_exists('getAuthenticatedUser')) {
    function getAuthenticatedUser($idOnly = false, $withPrefix = false)
    {
        $prefix = '';
        // Check the 'web' guard (users)
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            $prefix = 'u_';
        }
        // Check the 'client' guard (clients)
        elseif (Auth::guard('client')->check()) {
            $user = Auth::guard('client')->user();
            $prefix = 'c_';
        }
        // Check the 'sanctum' guard (API tokens)
        elseif (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            // Optionally set a prefix for sanctum-authenticated users
            // $prefix = 's_';
        }
        // No user is authenticated
        else {
            return null;
        }
        if ($idOnly) {
            if ($withPrefix) {
                return $prefix . $user->id;
            } else {
                return $user->id;
            }
        }

        return $user;
    }
}
if (!function_exists('isUser')) {
    function isUser()
    {
        return Auth::guard('web')->check(); // Assuming 'role' is a field in the user model.
    }
}
if (!function_exists('isClient')) {
    function isClient()
    {
        return Auth::guard('client')->check(); // Assuming 'role' is a field in the user model.
    }
}
if (!function_exists('generateUniqueSlug')) {
    function generateUniqueSlug($title, $model, $id = null)
    {
        $slug = Str::slug($title);
        $count = 2;
        // If an ID is provided, add a where clause to exclude it
        if ($id !== null) {
            while ($model::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = Str::slug($title) . '-' . $count;
                $count++;
            }
        } else {
            while ($model::where('slug', $slug)->exists()) {
                $slug = Str::slug($title) . '-' . $count;
                $count++;
            }
        }
        return $slug;
    }
}
if (!function_exists('duplicateRecord')) {
    function duplicateRecord($model, $id, $relatedTables = [], $title = '')
    {
        $eagerLoadRelations = $relatedTables;
        $eagerLoadRelations = array_filter($eagerLoadRelations, function ($table) {
            return $table !== 'project_tasks'; // Exclude from eager loading
        });
        // Eager load the related tables excluding 'project_tasks'
        $originalRecord = $model::with($eagerLoadRelations)->find($id);
        if (!$originalRecord) {
            return false; // Record not found
        }
        // Start a new database transaction to ensure data consistency
        DB::beginTransaction();
        try {
            // Duplicate the original record
            $duplicateRecord = $originalRecord->replicate();
            // Set the title if provided
            if (!empty($title)) {
                $duplicateRecord->title = $title;
            }
            $duplicateRecord->save();
            foreach ($relatedTables as $relatedTable) {
                if ($relatedTable === 'projects') {
                    foreach ($originalRecord->$relatedTable as $project) {
                        // Duplicate the project
                        $duplicateProject = $project->replicate();
                        $duplicateProject->workspace_id = $duplicateRecord->id; // Set the new workspace ID
                        $duplicateProject->save();
                        // Attach project users
                        foreach ($project->users as $user) {
                            $duplicateProject->users()->attach($user->id);
                        }
                        // Attach project clients
                        foreach ($project->clients as $client) {
                            $duplicateProject->clients()->attach($client->id);
                        }
                        // Duplicate the project's tasks
                        if (in_array('project_tasks', $relatedTables)) {
                            foreach ($project->tasks as $task) {
                                $duplicateTask = $task->replicate();
                                $duplicateTask->workspace_id = $duplicateRecord->id;
                                $duplicateTask->project_id = $duplicateProject->id; // Set the new project ID
                                $duplicateTask->save();
                                // Duplicate task's users (if applicable)
                                foreach ($task->users as $user) {
                                    $duplicateTask->users()->attach($user->id);
                                }
                            }
                        }
                    }
                }
                if ($relatedTable === 'tasks') {
                    // Handle 'tasks' relationship separately
                    foreach ($originalRecord->$relatedTable as $task) {
                        // Duplicate the related task
                        $duplicateTask = $task->replicate();
                        $duplicateTask->project_id = $duplicateRecord->id;
                        $duplicateTask->save();
                        foreach ($task->users as $user) {
                            // Attach the duplicated user to the duplicated task
                            $duplicateTask->users()->attach($user->id);
                        }
                    }
                }
                if ($relatedTable === 'meetings') {
                    foreach ($originalRecord->$relatedTable as $meeting) {
                        $duplicateMeeting = $meeting->replicate();
                        $duplicateMeeting->workspace_id = $duplicateRecord->id; // Set the new workspace ID
                        $duplicateMeeting->save();
                        // Duplicate meeting's users
                        foreach ($meeting->users as $user) {
                            $duplicateMeeting->users()->attach($user->id);
                        }
                        // Duplicate meeting's clients
                        foreach ($meeting->clients as $client) {
                            $duplicateMeeting->clients()->attach($client->id);
                        }
                    }
                }
                if ($relatedTable === 'todos') {
                    // Duplicate todos
                    foreach ($originalRecord->$relatedTable as $todo) {
                        $duplicateTodo = $todo->replicate();
                        $duplicateTodo->workspace_id = $duplicateRecord->id; // Set the new workspace ID
                        $duplicateTodo->creator_type = $todo->creator_type; // Keep original creator type
                        $duplicateTodo->creator_id = $todo->creator_id;     // Keep original creator ID
                        $duplicateTodo->save();
                    }
                }
                if ($relatedTable === 'notes') {
                    foreach ($originalRecord->$relatedTable as $note) {
                        $duplicateNote = $note->replicate();
                        $duplicateNote->workspace_id = $duplicateRecord->id; // Set the new workspace ID
                        $duplicateNote->creator_id = $note->creator_id;      // Retain the creator_id
                        $duplicateNote->save();
                    }
                }
            }
            // Handle many-to-many relationships separately
            if (in_array('users', $relatedTables)) {
                $originalRecord->users()->each(function ($user) use ($duplicateRecord) {
                    $duplicateRecord->users()->attach($user->id);
                });
            }
            if (in_array('clients', $relatedTables)) {
                $originalRecord->clients()->each(function ($client) use ($duplicateRecord) {
                    $duplicateRecord->clients()->attach($client->id);
                });
            }
            if (in_array('tags', $relatedTables)) {
                $originalRecord->tags()->each(function ($tag) use ($duplicateRecord) {
                    $duplicateRecord->tags()->attach($tag->id);
                });
            }
            // Commit the transaction
            DB::commit();
            return $duplicateRecord;
        } catch (\Exception $e) {
            // Handle any exceptions and rollback the transaction on failure
            DB::rollback();
            return false;
        }
    }
}
if (!function_exists('is_admin_or_leave_editor')) {
    function is_admin_or_leave_editor($user = null)
    {
        if (!$user) {
            $user = getAuthenticatedUser();
        }
        // Check if the user is an admin or a leave editor based on their presence in the leave_editors table
        if ($user->hasRole('admin') || LeaveEditor::where('user_id', $user->id)->exists()) {
            return true;
        }
        return false;
    }
}
if (!function_exists('get_php_date_time_format')) {
    function get_php_date_time_format($timeFormat = false)
    {
        $general_settings = get_settings('general_settings');
        if ($timeFormat) {
            return $general_settings['time_format'] ?? 'H:i:s';
        } else {
            $date_format = $general_settings['date_format'] ?? 'DD-MM-YYYY|d-m-Y';
            $date_format = explode('|', $date_format);
            return $date_format[1];
        }
    }
}
if (!function_exists('get_system_update_info')) {
    function get_system_update_info()
    {
        $updatePath = Config::get('constants.UPDATE_PATH');
        $updaterPath = $updatePath . 'updater.json';
        $subDirectory = (File::exists($updaterPath) && File::exists($updatePath . 'update/updater.json')) ? 'update/' : '';
        if (File::exists($updaterPath) || File::exists($updatePath . $subDirectory . 'updater.json')) {
            $updaterFilePath = File::exists($updaterPath) ? $updaterPath : $updatePath . $subDirectory . 'updater.json';
            $updaterContents = File::get($updaterFilePath);
            // Check if the file contains valid JSON data
            if (!json_decode($updaterContents)) {
                throw new \RuntimeException('Invalid JSON content in updater.json');
            }
            $linesArray = json_decode($updaterContents, true);
            if (!isset($linesArray['version'], $linesArray['previous'], $linesArray['manual_queries'], $linesArray['query_path'])) {
                throw new \RuntimeException('Invalid JSON structure in updater.json');
            }
        } else {
            throw new \RuntimeException('updater.json does not exist');
        }
        $dbCurrentVersion = Update::latest()->first();
        $data['db_current_version'] = $dbCurrentVersion ? $dbCurrentVersion->version : '1.0.0';
        if ($data['db_current_version'] == $linesArray['version']) {
            $data['updated_error'] = true;
            $data['message'] = 'Oops!. This version is already updated into your system. Try another one.';
            return $data;
        }
        if ($data['db_current_version'] == $linesArray['previous']) {
            $data['file_current_version'] = $linesArray['version'];
        } else {
            $data['sequence_error'] = true;
            $data['message'] = 'Oops!. Update must performed in sequence.';
            return $data;
        }
        $data['query'] = $linesArray['manual_queries'];
        $data['query_path'] = $linesArray['query_path'];
        return $data;
    }
}
if (!function_exists('escape_array')) {
    function escape_array($array)
    {
        if (empty($array)) {
            return $array;
        }
        $db = DB::connection()->getPdo();
        if (is_array($array)) {
            return array_map(function ($value) use ($db) {
                return $db->quote($value);
            }, $array);
        } else {
            // Handle single non-array value
            return $db->quote($array);
        }
    }
}


if (!function_exists('isEmailConfigured')) {
    function isEmailConfigured()
    {
        $email_settings = get_settings('email_settings');

        // Step 1: Ensure all required SMTP fields are present
        if (
            empty($email_settings['email']) ||
            empty($email_settings['password']) ||
            empty($email_settings['smtp_host']) ||
            empty($email_settings['smtp_port'])
        ) {
            return false;
        }

        // Step 2: Try SMTP connection
        try {
            $transport = new EsmtpTransport(
                $email_settings['smtp_host'],
                (int) $email_settings['smtp_port'],
                ($email_settings['encryption'] ?? null) === 'ssl'
            );

            $transport->setUsername($email_settings['email']);
            $transport->setPassword($email_settings['password']);

            // This actually opens the connection to verify SMTP
            $transport->start();

            return true;
        } catch (TransportExceptionInterface $e) {
            Log::error('SMTP connection failed: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error('Unexpected SMTP error: ' . $e->getMessage());
            return false;
        }
    }
}





if (!function_exists('get_current_version')) {
    function get_current_version()
    {
        $dbCurrentVersion = Update::latest()->first();
        return $dbCurrentVersion ? $dbCurrentVersion->version : '1.0.0';
    }
}
if (!function_exists('isAdminOrHasAllDataAccess')) {
    function isAdminOrHasAllDataAccess($type = null, $id = null)
    {
        // Get authenticated user
        $authenticatedUser = getAuthenticatedUser();
        if ($type == 'user' && $id !== null) {
            $user = User::find($id);
            if ($user) {
                return $user->hasRole('admin') || $user->can('access_all_data');
            }
        } elseif ($type == 'client' && $id !== null) {
            $client = Client::find($id);
            if ($client) {
                return $client->hasRole('admin') || $client->can('access_all_data');
            }
        } elseif ($type === null && $id === null) {
            if ($authenticatedUser) {
                return $authenticatedUser->hasRole('admin') || $authenticatedUser->can('access_all_data');
            }
        }
        return false;
    }
}
if (!function_exists('getControllerNames')) {
    function getControllerNames()
    {
        $controllersPath = app_path('Http/Controllers');
        $files = File::files($controllersPath);
        $excludedControllers = [
            'ActivityLogController',
            'Controller',
            'HomeController',
            'InstallerController',
            'LanguageController',
            'ProfileController',
            'RolesController',
            'SearchController',
            'SettingsController',
            'UpdaterController',
            'EstimatesInvoicesController',
            'PreferenceController',
            'ReportsController',
            'NotificationsController',
            'SwaggerController'
        ];
        $controllerNames = [];
        foreach ($files as $file) {
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            // Skip controllers in the excluded list
            if (in_array($fileName, $excludedControllers)) {
                continue;
            }
            if (str_ends_with($fileName, 'Controller')) {
                // Convert to singular form, snake_case, and remove 'Controller' suffix
                $controllerName = Str::snake(Str::singular(str_replace('Controller', '', $fileName)));
                $controllerNames[] = $controllerName;
            }
        }
        // Add manually defined types
        $manuallyDefinedTypes = [
            'contract_type',
            'media',
            'estimate',
            'invoice',
            'milestone'
            // Add more types as needed
        ];
        $controllerNames = array_merge($controllerNames, $manuallyDefinedTypes);
        return $controllerNames;
    }
}
if (!function_exists('formatSize')) {
    function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
if (!function_exists('getStatusColor')) {
    function getStatusColor($status)
    {
        switch ($status) {
            case 'sent':
                return 'primary';
            case 'accepted':
            case 'fully_paid':
                return 'success';
            case 'draft':
                return 'secondary';
            case 'declined':
            case 'due':
                return 'danger';
            case 'expired':
            case 'partially_paid':
                return 'warning';
            case 'not_specified':
                return 'secondary';
            default:
                return 'info';
        }
    }
}
if (!function_exists('getStatusCount')) {
    function getStatusCount($status, $type)
    {
        $estimates_invoices = isAdminOrHasAllDataAccess() ? Workspace::find(getWorkspaceId())->estimates_invoices($status, $type) : getAuthenticatedUser()->estimates_invoices($status, $type);
        return $estimates_invoices->count();
    }
}
if (!function_exists('format_currency')) {
    function format_currency($amount, $is_currency_symbol = 1, $include_separators = true)
    {
        if ($amount == '') {
            return '';
        }
        $general_settings = get_settings('general_settings');
        $currency_symbol = $general_settings['currency_symbol'] ?? '₹';
        $currency_format = $general_settings['currency_formate'] ?? 'comma_separated';
        $decimal_points = intval($general_settings['decimal_points_in_currency'] ?? '2');
        $currency_symbol_position = $general_settings['currency_symbol_position'] ?? 'before';
        // Determine the appropriate separators based on the currency format and $use_commas parameter
        if ($include_separators) {
            $thousands_separator = ($currency_format == 'comma_separated') ? ',' : '.';
        } else {
            $thousands_separator = '';
        }
        // Format the amount with the determined separators
        $formatted_amount = number_format($amount, $decimal_points, '.', $thousands_separator);
        if ($is_currency_symbol) {
            // Format currency symbol position
            if ($currency_symbol_position === 'before') {
                $currency_amount = $currency_symbol . ' ' . $formatted_amount;
            } else {
                $currency_amount = $formatted_amount . ' ' . $currency_symbol;
            }
            return $currency_amount;
        }
        return $formatted_amount;
    }
}
if (!function_exists('get_tax_data')) {
    function get_tax_data($tax_id, $total_amount, $currency_symbol = 0)
    {
        // Check if tax_id is not empty
        if ($tax_id != '') {
            // Retrieve tax data from the database using the tax_id
            $tax = Tax::find($tax_id);
            // Check if tax data is found
            if ($tax) {
                // Get tax rate and type
                $taxRate = $tax->amount;
                $taxType = $tax->type;
                // Calculate tax amount based on tax rate and type
                $taxAmount = 0;
                $disp_tax = '';
                if ($taxType == 'percentage') {
                    $taxAmount = ($total_amount * $tax->percentage) / 100;
                    $disp_tax = format_currency($taxAmount, $currency_symbol) . '(' . $tax->percentage . '%)';
                } elseif ($taxType == 'amount') {
                    $taxAmount = $taxRate;
                    $disp_tax = format_currency($taxAmount, $currency_symbol);
                }
                // Return the calculated tax data
                return [
                    'taxAmount' => $taxAmount,
                    'taxType' => $taxType,
                    'dispTax' => $disp_tax,
                ];
            }
        }
        // Return empty data if tax_id is empty or tax data is not found
        return [
            'taxAmount' => 0,
            'taxType' => '',
            'dispTax' => '',
        ];
    }
}
if (!function_exists('processNotifications')) {
    function processNotifications($data, $recipients)
    {
        // Define an array of types for which email notifications should be sent
        $emailNotificationTypes = ['project_assignment', 'project_status_updation', 'interview_assignment', 'interview_status_update', 'task_assignment', 'task_status_updation', 'workspace_assignment', 'meeting_assignment', 'leave_request_creation', 'leave_request_status_updation', 'team_member_on_leave_alert'];
        $smsNotificationTypes = ['project_assignment', 'project_status_updation', 'interview_assignment', 'interview_status_update', 'task_assignment', 'task_status_updation', 'workspace_assignment', 'meeting_assignment', 'leave_request_creation', 'leave_request_status_updation', 'team_member_on_leave_alert'];
        if (!empty($recipients)) {
            $type = $data['type'] == 'task_status_updation' ? 'task' : ($data['type'] == 'project_status_updation' ? 'project' : ($data['type'] == 'leave_request_creation' || $data['type'] == 'leave_request_status_updation' || $data['type'] == 'team_member_on_leave_alert' ? 'leave_request' : $data['type']));
            $systemNotificationTemplate = getNotificationTemplate($data['type'], 'system');
            $pushNotificationTemplate = getNotificationTemplate($data['type'], 'push');
            if (
                !$systemNotificationTemplate || $systemNotificationTemplate->status !== 0 ||
                !$pushNotificationTemplate || $pushNotificationTemplate->status !== 0
            ) {
                $notification = Notification::create([
                    'workspace_id' => getWorkspaceId(),
                    'from_id' => getGuardName() == 'client' ? 'c_' . getAuthenticatedUser()->id : 'u_' . getAuthenticatedUser()->id,
                    'type' => $type,
                    'type_id' => $data['type_id'],
                    'action' => $data['action'],
                    'title' => getTitle($data),
                    'message' => get_message($data, NULL, 'system'),
                ]);
            }
            // Exclude creator from receiving notification
            $loggedInUserId = getGuardName() == 'client' ? 'c_' . getAuthenticatedUser()->id : 'u_' . getAuthenticatedUser()->id;
            $recipients = array_diff($recipients, [$loggedInUserId]);
            $recipients = array_unique($recipients);
            // dd($recipients);
            $whatsappNotificationTemplate = getNotificationTemplate($data['type'], 'whatsapp');
            $slackNotificationTemplate = getNotificationTemplate($data['type'], 'slack');
            foreach ($recipients as $recipient) {
                $isSystem = 0;
                $isPush = 0;
                $enabledNotifications = getUserPreferences('notification_preference', 'enabled_notifications', $recipient);
                $recipientId = substr($recipient, 2);
                if (substr($recipient, 0, 2) === 'u_') {
                    $recipientModel = User::find($recipientId);
                } elseif (substr($recipient, 0, 2) === 'c_') {
                    $recipientModel = Client::find($recipientId);
                } elseif (substr($recipient, 0, 2) === 'ca') {
                    $recipientModel = Candidate::find($recipientId);
                }
                // Check if recipient was found
                // dd($recipientModel);
                if ($recipientModel) {
                    if (!$systemNotificationTemplate || ($systemNotificationTemplate->status !== 0)) {
                        if (
                            (is_array($enabledNotifications) && empty($enabledNotifications)) || (
                                is_array($enabledNotifications) && (
                                    in_array('system_' . $data['type'] . '_assignment', $enabledNotifications) ||
                                    in_array('system_' . $data['type'], $enabledNotifications)
                                )
                            )
                        ) {
                            $isSystem = 1;
                        }
                    }
                    if (!$pushNotificationTemplate || ($pushNotificationTemplate->status !== 0)) {
                        if (
                            (is_array($enabledNotifications) && empty($enabledNotifications)) || (
                                is_array($enabledNotifications) && (
                                    in_array('push_' . $data['type'] . '_assignment', $enabledNotifications) ||
                                    in_array('push_' . $data['type'], $enabledNotifications)
                                )
                            )
                        ) {
                            $isPush = 1;
                            try {
                                sendPushNotification($recipientModel, $data);
                            } catch (\Exception $e) {
                                // dd($e);
                            }
                        }
                    }
                    if ($isSystem || $isPush) {
                        // dd($recipientModel, $notification);
                        $recipientModel->notifications()->attach($notification->id, [
                            'is_system' => $isSystem,
                            'is_push' => $isPush,
                        ]);
                    }
                    if (in_array($data['type'] . '_assignment', $emailNotificationTypes) || in_array($data['type'], $emailNotificationTypes)) {
                        if (
                            (is_array($enabledNotifications) && empty($enabledNotifications)) || (
                                is_array($enabledNotifications) && (
                                    in_array('email_' . $data['type'] . '_assignment', $enabledNotifications) ||
                                    in_array('email_' . $data['type'], $enabledNotifications)
                                )
                            )
                        ) {
                            try {
                                sendEmailNotification($recipientModel, $data);
                            } catch (\Exception $e) {
                                // dd($e->getMessage());
                            } catch (TransportExceptionInterface $e) {
                                // dd($e->getMessage());
                            } catch (Throwable $e) {
                                // dd($e->getMessage());
                                // Catch any other throwable, including non-Exception errors
                            }
                        }
                    }
                    if (in_array($data['type'] . '_assignment', $smsNotificationTypes) || in_array($data['type'], $smsNotificationTypes)) {
                        if (
                            (is_array($enabledNotifications) && empty($enabledNotifications)) || (
                                is_array($enabledNotifications) && (
                                    in_array('sms_' . $data['type'] . '_assignment', $enabledNotifications) ||
                                    in_array('sms_' . $data['type'], $enabledNotifications)
                                )
                            )
                        ) {
                            try {
                                sendSMSNotification($data, $recipientModel);
                            } catch (\Exception $e) {
                            }
                        }
                    }
                    if (!$whatsappNotificationTemplate || ($whatsappNotificationTemplate->status !== 0)) {
                        if (
                            (is_array($enabledNotifications) && empty($enabledNotifications)) || (
                                is_array($enabledNotifications) && (
                                    in_array('whatsapp_' . $data['type'] . '_assignment', $enabledNotifications) ||
                                    in_array('whatsapp_' . $data['type'], $enabledNotifications)
                                )
                            )
                        ) {
                            try {
                                sendWhatsAppNotification($recipientModel, $data);
                            } catch (\Exception $e) {
                            }
                        }
                    }
                    if (!$slackNotificationTemplate || ($slackNotificationTemplate->status !== 0)) {
                        if (
                            (is_array($enabledNotifications) && empty($enabledNotifications)) || (
                                is_array($enabledNotifications) && (
                                    in_array('slack_' . $data['type'] . '_assignment', $enabledNotifications) ||
                                    in_array('slack_' . $data['type'], $enabledNotifications)
                                )
                            )
                        ) {
                            try {
                                sendSlackNotification($recipientModel, $data);
                            } catch (\Exception $e) {
                            }
                        }
                    }
                }
            }
        }
    }
}
if (!function_exists('sendPushNotification')) {
    function sendPushNotification($recipientModel, $data)
    {
        // dd($recipientModel, $data);
        // Path to your service account key
        $serviceAccountPath = storage_path('app/firebase/firebase-service-account.json');
        // Set up the Google Client for authentication
        $googleClient = new GoogleClient();
        $googleClient->setAuthConfig($serviceAccountPath);
        $googleClient->addScope('https://www.googleapis.com/auth/firebase.messaging');
        // Generate an access token
        $accessToken = $googleClient->fetchAccessTokenWithAssertion()['access_token'];
        $projectId = json_decode(file_get_contents($serviceAccountPath), true)['project_id'];
        // Retrieve device tokens based on the recipient model
        if ($recipientModel instanceof User) {
            $deviceTokens = FcmToken::where('user_id', $recipientModel->id)->pluck('fcm_token')->toArray();
        } elseif ($recipientModel instanceof Client) {
            $deviceTokens = FcmToken::where('client_id', $recipientModel->id)->pluck('fcm_token')->toArray();
        }
        if (empty($deviceTokens)) {
            return; // No device tokens found, skip sending
        }
        // Set up Guzzle HTTP client
        $httpClient = new HttpClient([
            'base_uri' => 'https://fcm.googleapis.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
        ]);
        $title = getTitle($data, $recipientModel, 'push');
        $body = get_message($data, $recipientModel, 'push');
        // Prepare the notification message
        $message = [
            'message' => [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'notification' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                        ],
                    ],
                ],
            ],
        ];
        // Add additional data based on the notification type
        if ($data['type'] == 'project' || $data['type'] == 'project_status_updation') {
            $project = Project::find($data['type_id']);
            $message['message']['data']['item'] = json_encode([
                'type' => 'project',
                'item' => formatProject($project),
            ]);
        } elseif ($data['type'] == 'task' || $data['type'] == 'task_status_updation') {
            $task = Task::find($data['type_id']);
            $message['message']['data']['item'] = json_encode([
                'type' => 'task',
                'item' => formatTask($task),
            ]);
        } elseif ($data['type'] == 'meeting') {
            $meeting = Meeting::find($data['type_id']);
            $message['message']['data']['item'] = json_encode([
                'type' => 'meeting',
                'item' => formatMeeting($meeting),
            ]);
        } elseif ($data['type'] == 'workspace') {
            $workspace = Workspace::find($data['type_id']);
            $message['message']['data']['item'] = json_encode([
                'type' => 'workspace',
                'item' => formatWorkspace($workspace),
            ]);
        } elseif (in_array($data['type'], ['leave_request_creation', 'team_member_on_leave_alert', 'leave_request_status_updation'])) {
            $leaveRequest = LeaveRequest::find($data['type_id']);
            $message['message']['data']['item'] = json_encode([
                'type' => 'leave_request',
                'item' => formatLeaveRequest($leaveRequest),
            ]);
        }
        foreach ($deviceTokens as $deviceToken) {
            try {
                // Set the current device token
                $message['message']['token'] = $deviceToken;
                // Send the notification
                $response = $httpClient->post("projects/{$projectId}/messages:send", [
                    'json' => $message,
                ]);
                // Uncomment for debugging
                // dd($projectId);
                // dd($response);
            } catch (\Exception $e) {
                // Handle the error for the current token
                // Log the error or take appropriate action
                // Uncomment for debugging
                // dd($e->getMessage());
            }
        }
    }
}
if (!function_exists('sendEmailNotification')) {
    function sendEmailNotification($recipientModel, $data)
    {
        $template = getNotificationTemplate($data['type']);
        if (!$template || ($template->status !== 0)) {
            $recipientModel->notify(new AssignmentNotification($recipientModel, $data));
        }
    }
}
if (!function_exists('sendSMSNotification')) {
    function sendSMSNotification($data, $recipient)
    {
        $template = getNotificationTemplate($data['type'], 'sms');
        if (!$template || ($template->status !== 0)) {
            send_sms($recipient, $data);
        }
    }
}
if (!function_exists('getNotificationTemplate')) {
    function getNotificationTemplate($type, $emailOrSMS = 'email')
    {
        $template = Template::where('type', $emailOrSMS)
            ->where('name', $type . '_assignment')
            ->first();
        if (!$template) {
            // If template with $type . '_assignment' name not found, check for template with $type name
            $template = Template::where('type', $emailOrSMS)
                ->where('name', $type)
                ->first();
        }
        // dd($template);
        return $template;
    }
}
if (!function_exists('send_sms')) {
    function send_sms($recipient, $itemData = NULL, $message = NULL)
    {
        $msg = $itemData ? get_message($itemData, $recipient) : $message;
        try {
            $sms_gateway_settings = get_settings('sms_gateway_settings', true);
            $data = [
                "base_url" => $sms_gateway_settings['base_url'],
                "sms_gateway_method" => $sms_gateway_settings['sms_gateway_method']
            ];
            $data["body"] = [];
            if (isset($sms_gateway_settings["body_formdata"])) {
                foreach ($sms_gateway_settings["body_formdata"] as $key => $value) {
                    $value = parse_sms($value, $recipient->phone, $msg, $recipient->country_code);
                    $data["body"][$key] = $value;
                }
            }
            $data["header"] = [];
            if (isset($sms_gateway_settings["header_data"])) {
                foreach ($sms_gateway_settings["header_data"] as $key => $value) {
                    $value = parse_sms($value, $recipient->phone, $msg, $recipient->country_code);
                    $data["header"][] = $key . ": " . $value;
                }
            }
            $data["params"] = [];
            if (isset($sms_gateway_settings["params_data"])) {
                foreach ($sms_gateway_settings["params_data"] as $key => $value) {
                    $value = parse_sms($value, $recipient->phone, $msg, $recipient->country_code);
                    $data["params"][$key] = $value;
                }
            }
            $response = curl_sms($data["base_url"], $data["sms_gateway_method"], $data["body"], $data["header"]);
            // print_r($response);
            if ($itemData == NULL) {
                return $response;
            }
        } catch (Exception $e) {
            // Handle the exception
            if ($itemData == NULL) {
                throw new Exception('Failed to send SMS: ' . $e->getMessage());
            }
        }
    }
}
if (!function_exists('storeFcmToken')) {
    function storeFcmToken($recipientModel, $token)
    {
        // Check if the token is provided
        if (empty($token)) {
            return false; // No token to store
        }
        // Determine if the recipient is a User or Client
        $userId = null;
        $clientId = null;
        $guardName = getGuardName();
        if ($guardName == 'web') {
            $userId = $recipientModel->id; // Set user ID
        } elseif ($guardName == 'client') {
            $clientId = $recipientModel->id; // Set client ID
        }
        // Check if the token already exists for this user or client
        $query = FcmToken::where('fcm_token', $token);
        if ($userId) {
            $existingToken = $query->where('user_id', $userId)->first();
        } elseif ($clientId) {
            $existingToken = $query->where('client_id', $clientId)->first();
        }
        // If the token does not exist, save it
        if (!$existingToken) {
            FcmToken::create([
                'user_id' => $userId,
                'client_id' => $clientId,
                'fcm_token' => $token,
            ]);
        }
        return true; // Token stored successfully or already exists
    }
}
if (!function_exists('sendWhatsAppNotification')) {
    function sendWhatsAppNotification($recipient, $itemData = NULL, $message = NULL)
    {
        $msg = $itemData ? get_message($itemData, $recipient, 'whatsapp') : $message;
        $whatsapp_settings = get_settings('whatsapp_settings', true);
        $general_settings = get_settings('general_settings');
        $company_title = $general_settings['company_title'] ?? 'Taskify';
        $client = new GuzzleHttpClient();
        try {
            $response = $client->post('https://graph.facebook.com/v20.0/' . $whatsapp_settings['whatsapp_phone_number_id'] . '/messages', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $whatsapp_settings['whatsapp_access_token'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $recipient->country_code . $recipient->phone,
                    'type' => 'template',
                    'template' => [
                        'name' => 'taskify_saas_notification',
                        'language' => [
                            'code' => 'en'
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $msg  // This will replace {{1}}
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $company_title  // This will replace {{2}}
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
            ]);
            $data = json_decode($response->getBody(), true);
            if ($itemData == NULL) {
                return $data;
            }
            // dd("Message sent successfully. Response: " . print_r($data, true));
        } catch (RequestException $e) {
            // dd("Error sending message: " . $e->getMessage());
            if ($e->hasResponse()) {
                if ($itemData == NULL) {
                    throw new Exception('Failed: ' . $e->getMessage());
                }
                // dd("Response: " . $e->getResponse()->getBody()->getContents());
            }
        }
    }
}
if (!function_exists('sendSlackNotification')) {
    function sendSlackNotification($recipient, $itemData = NULL, $message = NULL)
    {
        $slack_settings = get_settings('slack_settings');
        $message = $itemData ? get_message($itemData, $recipient, 'slack') : $message;
        $botToken = $slack_settings['slack_bot_token'];
        // Create a Guzzle client for Slack API
        $client = new GuzzleHttpClient([
            'base_uri' => 'https://slack.com/api/',
            'headers' => [
                'Authorization' => 'Bearer ' . $botToken,
                'Content-Type' => 'application/json',
            ],
        ]);
        // Step 4: Look up the Slack user ID by email
        $email = $recipient->email;
        // $email = 'infinitietechnologies10@gmail.com';
        $userId = getSlackUserIdByEmail($client, $email);
        if ($userId) {
            // Step 5: Prepare the message payload
            // Assuming template has a 'content' field
            $slackMessage = [
                'channel' => $userId,
                'text' => $message,
                'username' => 'Taskify Notification',
                'icon_emoji' => ':office:',
            ];
            try {
                // Step 6: Send the Slack message
                $response = $client->post('chat.postMessage', [
                    'json' => $slackMessage
                ]);
                $responseBody = json_decode(
                    $response->getBody(),
                    true
                );
                if ($responseBody['ok']) {
                    if ($itemData === NULL) {
                        return [
                            'status' => 'success',
                            'message' => 'Slack DM sent successfully to user: ' . $userId,
                        ];
                    }
                    // Log::info('Slack DM sent successfully to user: ' . $userId);
                } else {
                    if ($itemData === NULL) {
                        return [
                            'status' => 'error',
                            'message' => 'Failed to send Slack DM: ' . $responseBody['error'],
                        ];
                    }
                    // Log::warning('Failed to send Slack DM to user ' . $userId . ': ' . $responseBody['error']);
                }
            } catch (\Exception $e) {
                if ($itemData === NULL) {
                    return [
                        'status' => 'error',
                        'message' => 'Error sending Slack DM: ' . $e->getMessage(),
                    ];
                }
                // Log::error('Error sending Slack DM to user: ' . $userId . ', Error: ' . $e->getMessage());
            }
        } else {
            if ($itemData === NULL) {
                return [
                    'status' => 'error',
                    'message' => 'Slack user ID not found for email: ' . $email,
                ];
            }
            // Log::warning('Slack user ID not found for email: ' . $email);
        }
    }
}
if (!function_exists('getSlackUserIdByEmail')) {
    function getSlackUserIdByEmail($client, $email)
    {
        try {
            $response = $client->get('users.lookupByEmail', [
                'query' => ['email' => $email]
            ]);
            $body = json_decode($response->getBody(), true);
            if ($body['ok'] === true) {
                return $body['user']['id']; // Return Slack User ID
            } else {
                Log::error("Failed to get Slack user ID: " . $body['error']);
            }
        } catch (\Exception $e) {
            Log::error('Error getting Slack user ID for email ' . $email . ': ' . $e->getMessage());
        }
    }
}
if (!function_exists('curl_sms')) {
    function curl_sms($url, $method = 'GET', $data = [], $headers = [])
    {
        $ch = curl_init();
        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
            )
        );
        if (count($headers) != 0) {
            $curl_options[CURLOPT_HTTPHEADER] = $headers;
        }
        if (strtolower($method) == 'post') {
            $curl_options[CURLOPT_POST] = 1;
            $curl_options[CURLOPT_POSTFIELDS] = http_build_query($data);
        } else {
            $curl_options[CURLOPT_CUSTOMREQUEST] = 'GET';
        }
        curl_setopt_array($ch, $curl_options);
        $result = array(
            'body' => json_decode(curl_exec($ch), true),
            'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        );
        return $result;
    }
}
if (!function_exists('parse_sms')) {
    function parse_sms($template, $phone, $msg, $country_code)
    {
        // Implement your parsing logic here
        // This is just a placeholder
        return str_replace(['{only_mobile_number}', '{message}', '{country_code}'], [$phone, $msg, $country_code], $template);
    }
}
if (!function_exists('get_message')) {
    function get_message($data, $recipient, $type = 'sms')
    {
        $authUser = getAuthenticatedUser();
        $general_settings = get_settings('general_settings');
        $company_title = $general_settings['company_title'] ?? 'Taskify';
        $siteUrl = $general_settings['site_url'] ?? request()->getSchemeAndHttpHost();
        $fetched_data = Template::where('type', $type)
            ->where('name', $data['type'] . '_assignment')
            ->first();
        if (!$fetched_data) {
            // If template with $this->data['type'] . '_assignment' name not found, check for template with $this->data['type'] name
            $fetched_data = Template::where('type', $type)
                ->where('name', $data['type'])
                ->first();
        }
        $templateContent = 'Default Content';
        $contentPlaceholders = []; // Initialize outside the switch
        // Customize content based on type
        if ($type === 'system' || $type === 'push') {
            switch ($data['type']) {
                case 'project':
                    $contentPlaceholders = [
                        '{PROJECT_ID}' => $data['type_id'],
                        '{PROJECT_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                        '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{PROJECT_URL}' => $siteUrl . '/' . $data['access_url']
                    ];
                    $templateContent = '{ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME} assigned you new project: {PROJECT_TITLE}, ID:#{PROJECT_ID}.';
                    break;
                case 'project_status_updation':
                    $contentPlaceholders = [
                        '{PROJECT_ID}' => $data['type_id'],
                        '{PROJECT_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                        '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                        '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                        '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{PROJECT_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '{UPDATER_FIRST_NAME} {UPDATER_LAST_NAME} has updated the status of project {PROJECT_TITLE}, ID:#{PROJECT_ID}, from {OLD_STATUS} to {NEW_STATUS}.';
                    break;
                case 'task':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                        '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url']
                    ];
                    $templateContent = '{ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME} assigned you new task: {TASK_TITLE}, ID:#{TASK_ID}.';
                    break;
                case 'task_status_updation':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                        '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                        '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                        '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '{UPDATER_FIRST_NAME} {UPDATER_LAST_NAME} has updated the status of task {TASK_TITLE}, ID:#{TASK_ID}, from {OLD_STATUS} to {NEW_STATUS}.';
                    break;
                case 'workspace':
                    $contentPlaceholders = [
                        '{WORKSPACE_ID}' => $data['type_id'],
                        '{WORKSPACE_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                        '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{WORKSPACE_URL}' => $siteUrl . '/workspaces'
                    ];
                    $templateContent = '{ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME} added you in a new workspace {WORKSPACE_TITLE}, ID:#{WORKSPACE_ID}.';
                    break;
                case 'meeting':
                    $contentPlaceholders = [
                        '{MEETING_ID}' => $data['type_id'],
                        '{MEETING_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                        '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{MEETING_URL}' => $siteUrl . '/meetings'
                    ];
                    $templateContent = '{ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME} added you in a new meeting {MEETING_TITLE}, ID:#{MEETING_ID}.';
                    break;
                case 'leave_request_creation':
                    $contentPlaceholders = [
                        '{ID}' => $data['type_id'],
                        '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                        '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                        '{REQUESTEE_FIRST_NAME}' => $data['team_member_first_name'],
                        '{REQUESTEE_LAST_NAME}' => $data['team_member_last_name'],
                        '{TYPE}' => $data['leave_type'],
                        '{FROM}' => $data['from'],
                        '{TO}' => $data['to'],
                        '{DURATION}' => $data['duration'],
                        '{REASON}' => $data['reason'],
                        '{COMMENT}' => $data['comment'],
                        '{STATUS}' => $data['status'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = 'New Leave Request ID:#{ID} Has Been Created By {REQUESTEE_FIRST_NAME} {REQUESTEE_LAST_NAME}.';
                    break;
                case 'leave_request_status_updation':
                    $contentPlaceholders = [
                        '{ID}' => $data['type_id'],
                        '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                        '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                        '{REQUESTEE_FIRST_NAME}' => $data['team_member_first_name'],
                        '{REQUESTEE_LAST_NAME}' => $data['team_member_last_name'],
                        '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                        '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                        '{TYPE}' => $data['leave_type'],
                        '{FROM}' => $data['from'],
                        '{TO}' => $data['to'],
                        '{DURATION}' => $data['duration'],
                        '{REASON}' => $data['reason'],
                        '{COMMENT}' => $data['comment'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = 'Leave Request ID:#{ID} Status Updated From {OLD_STATUS} To {NEW_STATUS}.';
                    break;
                case 'team_member_on_leave_alert':
                    $contentPlaceholders = [
                        '{ID}' => $data['type_id'],
                        '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                        '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                        '{REQUESTEE_FIRST_NAME}' => $data['team_member_first_name'],
                        '{REQUESTEE_LAST_NAME}' => $data['team_member_last_name'],
                        '{TYPE}' => $data['leave_type'],
                        '{FROM}' => $data['from'],
                        '{TO}' => $data['to'],
                        '{DURATION}' => $data['duration'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '{REQUESTEE_FIRST_NAME} {REQUESTEE_LAST_NAME} will be on {TYPE} leave from {FROM} to {TO}.';
                    break;
                case 'birthday_wish':
                    $contentPlaceholders = [
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{BIRTHDAY_COUNT}' => $data['birthday_count'],
                        '{ORDINAL_SUFFIX}' => $data['ordinal_suffix'],
                        '{COMPANY_TITLE}' => $company_title,
                        '{SITE_URL}' => $siteUrl
                    ];
                    $templateContent = 'Hello {FIRST_NAME} {LAST_NAME}, {COMPANY_TITLE} wishes you a very Happy Birthday!';
                    break;
                case 'work_anniversary_wish':
                    $contentPlaceholders = [
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{WORK_ANNIVERSARY_COUNT}' => $data['work_anniversary_count'],
                        '{ORDINAL_SUFFIX}' => $data['ordinal_suffix'],
                        '{COMPANY_TITLE}' => $company_title,
                        '{SITE_URL}' => $siteUrl
                    ];
                    $templateContent = 'Hello {FIRST_NAME} {LAST_NAME}, {COMPANY_TITLE} wishes you a very happy work anniversary!';
                    break;
                case 'task_reminder':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title,
                    ];
                    $templateContent = 'You have a task reminder for Task #{TASK_ID} - "{TASK_TITLE}". You can view the task here: {TASK_URL}.';
                    break;
                case 'recurring_task':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title,
                    ];
                    $templateContent = 'The recurring task #{TASK_ID} - "{TASK_TITLE}" has been executed. You can view the new instance here: {TASK_URL}';
                    break;
                case 'todo_reminder':
                    $contentPlaceholders = [
                        '{TODO_ID}' => $data['type_id'],
                        '{TODO_TITLE}' => $data['type_title'],
                        '{TODO_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title,
                    ];
                    $templateContent = 'You have a todo reminder for Todo #{TODO_ID} - "{TODO_TITLE}". You can view the task here: {TODO_URL}.';
                    break;
                case 'interview_assignment':
                    $contentPlaceholders = [
                        '{INTERVIEW_ID}' => $data['type_id'],
                        '{CANDIDATE_NAME}' => $data['candidate_name'],
                        '{ROUND}' => $data['round'],
                        '{SCHEDULED_AT}' => $data['scheduled_at'],
                        '{INTERVIEWER_FIRST_NAME}' => $data['interviewer_first_name'],
                        '{INTERVIEWER_LAST_NAME}' => $data['interviewer_last_name'],
                        '{FULL_NAME}' =>  $recipient ? $recipient->name : '',
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '{ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME} from {COMPANY_TITLE} has scheduled a new interview for {CANDIDATE_NAME}. Interview ID: #{INTERVIEW_ID}, Round: {ROUND}, Scheduled at: {SCHEDULED_AT}, Interviewer: {INTERVIEWER_FIRST_NAME} {INTERVIEWER_LAST_NAME}.';
                    break;
                case 'interview_status_update':
                    $contentPlaceholders = [
                        '{INTERVIEW_ID}' => $data['type_id'],
                        '{CANDIDATE_NAME}' => $data['candidate_name'],
                        '{ROUND}' => $data['round'],
                        '{SCHEDULED_AT}' => $data['scheduled_at'],
                        '{INTERVIEWER_FIRST_NAME}' => $data['interviewer_first_name'],
                        '{INTERVIEWER_LAST_NAME}' => $data['interviewer_last_name'],
                        '{FULL_NAME}' =>  $recipient ? $recipient->name : '',
                        '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                        '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '{UPDATER_FIRST_NAME} {UPDATER_LAST_NAME} has updated the status of your interview (ID: #{INTERVIEW_ID}) for {CANDIDATE_NAME} from "{OLD_STATUS}" to "{NEW_STATUS}".';
                    break;
            }
        } else if ($type === 'slack') {
            switch ($data['type']) {
                case 'project':
                    $contentPlaceholders = [
                        '{PROJECT_ID}' => $data['type_id'],
                        '{PROJECT_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{PROJECT_URL}' => $siteUrl . '/' . $data['access_url']
                    ];
                    $templateContent = '*New Project Assigned:* {PROJECT_TITLE}, ID: #{PROJECT_ID}. By {ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME} You can find the project here :{PROJECT_URL}';
                    break;
                case 'project_status_updation':
                    $contentPlaceholders = [
                        '{PROJECT_ID}' => $data['type_id'],
                        '{PROJECT_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                        '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{PROJECT_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '*Project Status Updated:* By {UPDATER_FIRST_NAME} {UPDATER_LAST_NAME} , {PROJECT_TITLE}, ID: #{PROJECT_ID}. Status changed from `{OLD_STATUS}` to `{NEW_STATUS}`. You can find the project here :{PROJECT_URL}';
                    break;
                case 'task':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url']
                    ];
                    $templateContent = '*New Task Assigned:* {TASK_TITLE}, ID: #{TASK_ID}. By {ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME} You can find the task here : {TASK_URL}';
                    break;
                case 'task_status_updation':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                        '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '*Task Status Updated:* By {UPDATER_FIRST_NAME} {UPDATER_LAST_NAME},  {TASK_TITLE}, ID: #{TASK_ID}. Status changed from `{OLD_STATUS}` to `{NEW_STATUS}`. You can find the Task here : {TASK_URL}';
                    break;
                case 'workspace':
                    $contentPlaceholders = [
                        '{WORKSPACE_ID}' => $data['type_id'],
                        '{WORKSPACE_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{WORKSPACE_URL}' => $siteUrl . '/workspaces'
                    ];
                    $templateContent = '*New Workspace Added:* By {ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME},   {WORKSPACE_TITLE}, ID: #{WORKSPACE_ID}. You can find the Workspace here : {WORKSPACE_URL}';
                    break;
                case 'meeting':
                    $contentPlaceholders = [
                        '{MEETING_ID}' => $data['type_id'],
                        '{MEETING_TITLE}' => $data['type_title'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{MEETING_URL}' => $siteUrl . '/meetings'
                    ];
                    $templateContent = 'New Meeting Scheduled:* By {ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME},  {MEETING_TITLE}, ID: #{MEETING_ID}. You can find the Meeting here : {MEETING_URL}';
                    break;
                case 'leave_request_creation':
                    $contentPlaceholders = [
                        '{ID}' => $data['type_id'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{REQUESTEE_FIRST_NAME}' => $data['team_member_first_name'],
                        '{REQUESTEE_LAST_NAME}' => $data['team_member_last_name'],
                        '{TYPE}' => $data['leave_type'],
                        '{FROM}' => $data['from'],
                        '{TO}' => $data['to'],
                        '{DURATION}' => $data['duration'],
                        '{REASON}' => $data['reason'],
                        '{COMMENT}' => $data['comment'],
                        '{STATUS}' => $data['status'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '*New {TYPE} Leave Request Created:* ID: #{ID} By {REQUESTEE_FIRST_NAME} {REQUESTEE_LAST_NAME} for {REASON}.  From ( {FROM} ) -  To ( {TO} ).';
                    break;
                case 'leave_request_status_updation':
                    $contentPlaceholders = [
                        '{ID}' => $data['type_id'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{REQUESTEE_FIRST_NAME}' => $data['team_member_first_name'],
                        '{REQUESTEE_LAST_NAME}' => $data['team_member_last_name'],
                        '{TYPE}' => $data['leave_type'],
                        '{FROM}' => $data['from'],
                        '{TO}' => $data['to'],
                        '{DURATION}' => $data['duration'],
                        '{REASON}' => $data['reason'],
                        '{COMMENT}' => $data['comment'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '*Leave Request Status Updated:* For {REQUESTEE_FIRST_NAME} {REQUESTEE_LAST_NAME},  ID: #{ID}. Status changed from `{OLD_STATUS}` to `{NEW_STATUS}`.';
                    break;
                case 'team_member_on_leave_alert':
                    $contentPlaceholders = [
                        '{ID}' => $data['type_id'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{REQUESTEE_FIRST_NAME}' => $data['team_member_first_name'],
                        '{REQUESTEE_LAST_NAME}' => $data['team_member_last_name'],
                        '{TYPE}' => $data['leave_type'],
                        '{FROM}' => $data['from'],
                        '{TO}' => $data['to'],
                        '{DURATION}' => $data['duration'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '*Team Member Leave Alert:* {REQUESTEE_FIRST_NAME} {REQUESTEE_LAST_NAME} will be on {TYPE} leave from {FROM} to {TO}.';
                    break;
                case 'birthday_wish':
                    $contentPlaceholders = [
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{BIRTHDAY_COUNT}' => $data['birthday_count'],
                        '{ORDINAL_SUFFIX}' => $data['ordinal_suffix'],
                        '{COMPANY_TITLE}' => $company_title,
                        '{SITE_URL}' => $siteUrl
                    ];
                    $templateContent = 'Hello *{FIRST_NAME} {LAST_NAME}*, {COMPANY_TITLE} wishes you a very Happy Birthday!';
                    break;
                case 'work_anniversary_wish':
                    $contentPlaceholders = [
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{WORK_ANNIVERSARY_COUNT}' => $data['work_anniversary_count'],
                        '{ORDINAL_SUFFIX}' => $data['ordinal_suffix'],
                        '{COMPANY_TITLE}' => $company_title,
                        '{SITE_URL}' => $siteUrl
                    ];
                    $templateContent = 'Hello *{FIRST_NAME} {LAST_NAME}*, {COMPANY_TITLE} wishes you a very happy work anniversary!';
                    break;
                case 'task_reminder':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title,
                    ];
                    $templateContent = 'You have a task reminder for Task #{TASK_ID} - "{TASK_TITLE}". You can view the task here: {TASK_URL}.';
                    break;
                case 'recurring_task':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title,
                    ];
                    $templateContent = 'The recurring task #{TASK_ID} - "{TASK_TITLE}" has been executed. You can view the new instance here: {TASK_URL}';
                    break;
                case 'todo_reminder':
                    $contentPlaceholders = [
                        '{TODO_ID}' => $data['type_id'],
                        '{TODO_TITLE}' => $data['type_title'],
                        '{TODO_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title,
                    ];
                    $templateContent = 'You have a todo reminder for Todo #{TODO_ID} - "{TODO_TITLE}". You can view the task here: {TODO_URL}.';
                    break;
                case 'interview_assignment':
                    $contentPlaceholders = [
                        '{INTERVIEW_ID}' => $data['type_id'],
                        '{CANDIDATE_NAME}' => $data['candidate_name'],
                        '{ROUND}' => $data['round'],
                        '{SCHEDULED_AT}' => $data['scheduled_at'],
                        '{INTERVIEWER_FIRST_NAME}' => $data['interviewer_first_name'],
                        '{INTERVIEWER_LAST_NAME}' => $data['interviewer_last_name'],
                        '{FULL_NAME}' =>  $recipient ? $recipient->name : '',
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '{ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME} from {COMPANY_TITLE} has scheduled a new interview for {CANDIDATE_NAME}. Interview ID: #{INTERVIEW_ID}, Round: {ROUND}, Scheduled at: {SCHEDULED_AT}, Interviewer: {INTERVIEWER_FIRST_NAME} {INTERVIEWER_LAST_NAME}.';
                    break;
                case 'interview_status_update':
                    $contentPlaceholders = [
                        '{INTERVIEW_ID}' => $data['type_id'],
                        '{CANDIDATE_NAME}' => $data['candidate_name'],
                        '{ROUND}' => $data['round'],
                        '{SCHEDULED_AT}' => $data['scheduled_at'],
                        '{INTERVIEWER_FIRST_NAME}' => $data['interviewer_first_name'],
                        '{INTERVIEWER_LAST_NAME}' => $data['interviewer_last_name'],
                        '{FULL_NAME}' =>  $recipient ? $recipient->name : '',
                        '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                        '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '{UPDATER_FIRST_NAME} {UPDATER_LAST_NAME} has updated the status of your interview (ID: #{INTERVIEW_ID}) for {CANDIDATE_NAME} from "{OLD_STATUS}" to "{NEW_STATUS}".';
                    break;
            }
        } else {
            switch ($data['type']) {
                case 'project':
                    $contentPlaceholders = [
                        '{PROJECT_ID}' => $data['type_id'],
                        '{PROJECT_TITLE}' => $data['type_title'],
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{PROJECT_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{SITE_URL}' => $siteUrl
                    ];
                    $templateContent = 'Hello, {FIRST_NAME} {LAST_NAME} You have been assigned a new project {PROJECT_TITLE}, ID:#{PROJECT_ID}.';
                    break;
                case 'project_status_updation':
                    $contentPlaceholders = [
                        '{PROJECT_ID}' => $data['type_id'],
                        '{PROJECT_TITLE}' => $data['type_title'],
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                        '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{PROJECT_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{SITE_URL}' => $siteUrl,
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '{UPDATER_FIRST_NAME} {UPDATER_LAST_NAME} has updated the status of project {PROJECT_TITLE}, ID:#{PROJECT_ID}, from {OLD_STATUS} to {NEW_STATUS}.';
                    break;
                case 'task':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{SITE_URL}' => $siteUrl
                    ];
                    $templateContent = 'Hello, {FIRST_NAME} {LAST_NAME} You have been assigned a new task {TASK_TITLE}, ID:#{TASK_ID}.';
                    break;
                case 'task_status_updation':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                        '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{SITE_URL}' => $siteUrl,
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '{UPDATER_FIRST_NAME} {UPDATER_LAST_NAME} has updated the status of task {TASK_TITLE}, ID:#{TASK_ID}, from {OLD_STATUS} to {NEW_STATUS}.';
                    break;
                case 'workspace':
                    $contentPlaceholders = [
                        '{WORKSPACE_ID}' => $data['type_id'],
                        '{WORKSPACE_TITLE}' => $data['type_title'],
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{WORKSPACE_URL}' => $siteUrl . '/workspaces',
                        '{SITE_URL}' => $siteUrl
                    ];
                    $templateContent = 'Hello, {FIRST_NAME} {LAST_NAME} You have been added in a new workspace {WORKSPACE_TITLE}, ID:#{WORKSPACE_ID}.';
                    break;
                case 'meeting':
                    $contentPlaceholders = [
                        '{MEETING_ID}' => $data['type_id'],
                        '{MEETING_TITLE}' => $data['type_title'],
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title,
                        '{MEETING_URL}' => $siteUrl . '/meetings',
                        '{SITE_URL}' => $siteUrl
                    ];
                    $templateContent = 'Hello, {FIRST_NAME} {LAST_NAME} You have been added in a new meeting {MEETING_TITLE}, ID:#{MEETING_ID}.';
                    break;
                case 'leave_request_creation':
                    $contentPlaceholders = [
                        '{ID}' => $data['type_id'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{REQUESTEE_FIRST_NAME}' => $data['team_member_first_name'],
                        '{REQUESTEE_LAST_NAME}' => $data['team_member_last_name'],
                        '{TYPE}' => $data['leave_type'],
                        '{FROM}' => $data['from'],
                        '{TO}' => $data['to'],
                        '{DURATION}' => $data['duration'],
                        '{REASON}' => $data['reason'],
                        '{COMMENT}' => $data['comment'],
                        '{STATUS}' => $data['status'],
                        '{COMPANY_TITLE}' => $company_title,
                        '{SITE_URL}' => $siteUrl,
                        '{CURRENT_YEAR}' => date('Y')
                    ];
                    $templateContent = 'New Leave Request ID:#{ID} Has Been Created By {REQUESTEE_FIRST_NAME} {REQUESTEE_LAST_NAME}.';
                    break;
                case 'leave_request_status_updation':
                    $contentPlaceholders = [
                        '{ID}' => $data['type_id'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{REQUESTEE_FIRST_NAME}' => $data['team_member_first_name'],
                        '{REQUESTEE_LAST_NAME}' => $data['team_member_last_name'],
                        '{TYPE}' => $data['leave_type'],
                        '{FROM}' => $data['from'],
                        '{TO}' => $data['to'],
                        '{DURATION}' => $data['duration'],
                        '{REASON}' => $data['reason'],
                        '{COMMENT}' => $data['comment'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{COMPANY_TITLE}' => $company_title,
                        '{SITE_URL}' => $siteUrl,
                        '{CURRENT_YEAR}' => date('Y')
                    ];
                    $templateContent = 'Leave Request ID:#{ID} Status Updated From {OLD_STATUS} To {NEW_STATUS}.';
                    break;
                case 'team_member_on_leave_alert':
                    $contentPlaceholders = [
                        '{ID}' => $data['type_id'],
                        '{USER_FIRST_NAME}' => $recipient->first_name,
                        '{USER_LAST_NAME}' => $recipient->last_name,
                        '{REQUESTEE_FIRST_NAME}' => $data['team_member_first_name'],
                        '{REQUESTEE_LAST_NAME}' => $data['team_member_last_name'],
                        '{TYPE}' => $data['leave_type'],
                        '{FROM}' => $data['from'],
                        '{TO}' => $data['to'],
                        '{DURATION}' => $data['duration'],
                        '{COMPANY_TITLE}' => $company_title,
                        '{SITE_URL}' => $siteUrl,
                        '{CURRENT_YEAR}' => date('Y')
                    ];
                    $templateContent = '{REQUESTEE_FIRST_NAME} {REQUESTEE_LAST_NAME} will be on {TYPE} leave from {FROM} to {TO}.';
                    break;
                case 'birthday_wish':
                    $contentPlaceholders = [
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{BIRTHDAY_COUNT}' => $data['birthday_count'],
                        '{ORDINAL_SUFFIX}' => $data['ordinal_suffix'],
                        '{COMPANY_TITLE}' => $company_title,
                        '{SITE_URL}' => $siteUrl
                    ];
                    $templateContent = 'Hello {FIRST_NAME} {LAST_NAME}, {COMPANY_TITLE} wishes you a very Happy Birthday!';
                    break;
                case 'work_anniversary_wish':
                    $contentPlaceholders = [
                        '{FIRST_NAME}' => $recipient->first_name,
                        '{LAST_NAME}' => $recipient->last_name,
                        '{WORK_ANNIVERSARY_COUNT}' => $data['work_anniversary_count'],
                        '{ORDINAL_SUFFIX}' => $data['ordinal_suffix'],
                        '{COMPANY_TITLE}' => $company_title,
                        '{SITE_URL}' => $siteUrl
                    ];
                    $templateContent = 'Hello {FIRST_NAME} {LAST_NAME}, {COMPANY_TITLE} wishes you a very happy work anniversary!';
                    break;
                case 'task_reminder':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title,
                        '{SITE_URL}' => $siteUrl
                    ];
                    $templateContent = 'You have a task reminder for Task #{TASK_ID} - {TASK_TITLE}. You can view the task here: {TASK_URL}';
                    break;
                case 'recurring_task':
                    $contentPlaceholders = [
                        '{TASK_ID}' => $data['type_id'],
                        '{TASK_TITLE}' => $data['type_title'],
                        '{TASK_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title,
                    ];
                    $templateContent = 'The recurring task #{TASK_ID} - "{TASK_TITLE}" has been executed. You can view the new instance here: {TASK_URL}';
                    break;
                case 'todo_reminder':
                    $contentPlaceholders = [
                        '{TODO_ID}' => $data['type_id'],
                        '{TODO_TITLE}' => $data['type_title'],
                        '{TODO_URL}' => $siteUrl . '/' . $data['access_url'],
                        '{COMPANY_TITLE}' => $company_title,
                    ];
                    $templateContent = 'You have a todo reminder for Todo #{TODO_ID} - "{TODO_TITLE}". You can view the task here: {TODO_URL}.';
                    break;
                case 'interview_assignment':
                    $contentPlaceholders = [
                        '{INTERVIEW_ID}' => $data['type_id'],
                        '{CANDIDATE_NAME}' => $data['candidate_name'],
                        '{ROUND}' => $data['round'],
                        '{SCHEDULED_AT}' => $data['scheduled_at'],
                        '{INTERVIEWER_FIRST_NAME}' => $data['interviewer_first_name'],
                        '{INTERVIEWER_LAST_NAME}' => $data['interviewer_last_name'],
                        '{FULL_NAME}' =>  $recipient ? $recipient->name : '',
                        '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                        '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '{ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME} has scheduled a new interview for {CANDIDATE_NAME}. Interview ID: #{INTERVIEW_ID}, Round: {ROUND}, Scheduled at: {SCHEDULED_AT}, Interviewer: {INTERVIEWER_FIRST_NAME} {INTERVIEWER_LAST_NAME}.';
                    break;
                case 'interview_status_update':
                    $contentPlaceholders = [
                        '{INTERVIEW_ID}' => $data['type_id'],
                        '{CANDIDATE_NAME}' => $data['candidate_name'],
                        '{ROUND}' => $data['round'],
                        '{SCHEDULED_AT}' => $data['scheduled_at'],
                        '{INTERVIEWER_FIRST_NAME}' => $data['interviewer_first_name'],
                        '{INTERVIEWER_LAST_NAME}' => $data['interviewer_last_name'],
                        '{FULL_NAME}' =>  $recipient ? $recipient->name : '',
                        '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                        '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                        '{OLD_STATUS}' => $data['old_status'],
                        '{NEW_STATUS}' => $data['new_status'],
                        '{COMPANY_TITLE}' => $company_title
                    ];
                    $templateContent = '{UPDATER_FIRST_NAME} {UPDATER_LAST_NAME} has updated the status of your interview (ID: #{INTERVIEW_ID}) for {CANDIDATE_NAME} from "{OLD_STATUS}" to "{NEW_STATUS}".';
                    break;
            }
        }
        if (filled(Arr::get($fetched_data, 'content'))) {
            $templateContent = $fetched_data->content;
        }
        // Replace placeholders with actual values
        $content = str_replace(array_keys($contentPlaceholders), array_values($contentPlaceholders), $templateContent);
        return $content;
    }
}
if (!function_exists('format_budget')) {
    function format_budget($amount)
    {
        // Check if the input is numeric or can be converted to a numeric value.
        if (!is_numeric($amount)) {
            // If the input is not numeric, return null or handle the error as needed.
            return null;
        }
        // Remove non-numeric characters from the input string.
        $amount = preg_replace('/[^0-9.]/', '', $amount);
        // Convert the input to a float.
        $amount = (float) $amount;
        // Define suffixes for thousands, millions, etc.
        $suffixes = ['', 'K', 'M', 'B', 'T'];
        // Determine the appropriate suffix and divide the amount accordingly.
        $suffixIndex = 0;
        while ($amount >= 1000 && $suffixIndex < count($suffixes) - 1) {
            $amount /= 1000;
            $suffixIndex++;
        }
        // Format the amount with the determined suffix.
        return number_format($amount, 2) . $suffixes[$suffixIndex];
    }
}
if (!function_exists('canSetStatus')) {
    function canSetStatus($status)
    {
        $user = getAuthenticatedUser();
        $isAdminOrHasAllDataAccess = isAdminOrHasAllDataAccess();
        // Ensure the user and their first role exist
        $userRoleId = $user && $user->roles->isNotEmpty() ? $user->roles->first()->id : null;
        // Check if the user has permission for this status
        $hasPermission = $userRoleId && $status->roles->contains($userRoleId) || $isAdminOrHasAllDataAccess;
        return $hasPermission;
    }
}
if (!function_exists('checkPermission')) {
    function checkPermission($permission)
    {
        static $user = null;
        if ($user === null) {
            $user = getAuthenticatedUser();
        }
        return $user->can($permission);
    }
}
if (!function_exists('getUserPreferences')) {
    function getUserPreferences($table, $column = 'visible_columns', $userId = null)
    {
        if ($userId === null) {
            $userId = getAuthenticatedUser(true, true);
        }
        $result = UserClientPreference::where('user_id', $userId)
            ->where('table_name', $table)
            ->first();
        switch ($column) {
            case 'default_view':
                if ($table == 'projects') {
                    $views = [
                        'kanban' => 'projects/kanban',
                        'list' => 'projects/list',
                        'gantt-chart' => 'projects/gantt-chart',
                        'calendar' => 'projects/calendar-view',
                    ];
                    return $result && $result->default_view
                        ? ($views[$result->default_view] ?? 'projects')
                        : 'projects';
                } elseif ($table == 'tasks') {
                    return $result && $result->default_view ? (
                        $result->default_view == 'draggable' ? 'tasks/draggable' : (
                            $result->default_view == 'calendar' ? 'tasks/calendar' : 'tasks'
                        )
                    ) : 'tasks';
                } elseif ($table == 'meetings') {
                    return $result->default_view ?? 'list';
                } elseif ($table == 'leave_requests') {
                    return $result->default_view ?? 'list';
                } elseif ($table == 'activity_logs') {
                    return $result->default_view ?? 'list';
                } elseif ($table == 'leads') {
                    return $result->default_view ?? 'list';
                }
                break;
            case 'visible_columns':
                return $result && $result->visible_columns ? $result->visible_columns : [];
                break;
            case 'enabled_notifications':
            case 'enabled_notifications':
                if ($result) {
                    if ($result->enabled_notifications === null) {
                        return null;
                    }
                    return json_decode($result->enabled_notifications, true);
                }
                return [];
                break;
                break;
            default:
                return null;
                break;
        }
    }
}
if (!function_exists('getOrdinalSuffix')) {
    function getOrdinalSuffix($number)
    {
        if (!in_array(($number % 100), [11, 12, 13])) {
            switch ($number % 10) {
                case 1:
                    return 'st';
                case 2:
                    return 'nd';
                case 3:
                    return 'rd';
            }
        }
        return 'th';
    }
}
if (!function_exists('getTitle')) {
    function getTitle($data, $recipient = NULL, $type = 'system')
    {
        static $authUser = null;
        static $companyTitle = null;
        if ($authUser === null) {
            $authUser = getAuthenticatedUser();
        }
        if ($companyTitle === null) {
            $general_settings = get_settings('general_settings');
            $companyTitle = $general_settings['company_title'] ?? 'Taskify';
        }
        $fetched_data = Template::where('type', $type)
            ->where('name', $data['type'] . '_assignment')
            ->first();
        if (!$fetched_data) {
            $fetched_data = Template::where('type', $type)
                ->where('name', $data['type'])
                ->first();
        }
        $subject = 'Default Subject'; // Set a default subject
        $subjectPlaceholders = [];
        // Customize subject based on type
        switch ($data['type']) {
            case 'project':
                $subjectPlaceholders = [
                    '{PROJECT_ID}' => $data['type_id'],
                    '{PROJECT_TITLE}' => $data['type_title'],
                    '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                    '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                    '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                    '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                    '{COMPANY_TITLE}' => $companyTitle
                ];
                break;
            case 'task':
                $subjectPlaceholders = [
                    '{TASK_ID}' => $data['type_id'],
                    '{TASK_TITLE}' => $data['type_title'],
                    '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                    '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                    '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                    '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                    '{COMPANY_TITLE}' => $companyTitle
                ];
                break;
            case 'workspace':
                $subjectPlaceholders = [
                    '{WORKSPACE_ID}' => $data['type_id'],
                    '{WORKSPACE_TITLE}' => $data['type_title'],
                    '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                    '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                    '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                    '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                    '{COMPANY_TITLE}' => $companyTitle
                ];
                break;
            case 'meeting':
                $subjectPlaceholders = [
                    '{MEETING_ID}' => $data['type_id'],
                    '{MEETING_TITLE}' => $data['type_title'],
                    '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                    '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                    '{ASSIGNEE_FIRST_NAME}' => $authUser->first_name,
                    '{ASSIGNEE_LAST_NAME}' => $authUser->last_name,
                    '{COMPANY_TITLE}' => $companyTitle
                ];
                break;
            case 'leave_request_creation':
                $subjectPlaceholders = [
                    '{ID}' => $data['type_id'],
                    '{STATUS}' => $data['status'],
                    '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                    '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                    '{REQUESTEE_FIRST_NAME}' => $data['team_member_first_name'],
                    '{REQUESTEE_LAST_NAME}' => $data['team_member_last_name'],
                    '{COMPANY_TITLE}' => $companyTitle
                ];
                break;
            case 'leave_request_status_updation':
                $subjectPlaceholders = [
                    '{ID}' => $data['type_id'],
                    '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                    '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                    '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                    '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                    '{OLD_STATUS}' => $data['old_status'],
                    '{NEW_STATUS}' => $data['new_status'],
                    '{COMPANY_TITLE}' => $companyTitle
                ];
                break;
            case 'team_member_on_leave_alert':
                $subjectPlaceholders = [
                    '{ID}' => $data['type_id'],
                    '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                    '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                    '{REQUESTEE_FIRST_NAME}' => $data['team_member_first_name'],
                    '{REQUESTEE_LAST_NAME}' => $data['team_member_last_name'],
                    '{COMPANY_TITLE}' => $companyTitle
                ];
                break;
            case 'project_status_updation':
                $subjectPlaceholders = [
                    '{PROJECT_ID}' => $data['type_id'],
                    '{PROJECT_TITLE}' => $data['type_title'],
                    '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                    '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                    '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                    '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                    '{OLD_STATUS}' => $data['old_status'],
                    '{NEW_STATUS}' => $data['new_status'],
                    '{COMPANY_TITLE}' => $companyTitle
                ];
                break;
            case 'task_status_updation':
                $subjectPlaceholders = [
                    '{TASK_ID}' => $data['type_id'],
                    '{TASK_TITLE}' => $data['type_title'],
                    '{USER_FIRST_NAME}' => $recipient ? $recipient->first_name : '',
                    '{USER_LAST_NAME}' => $recipient ? $recipient->last_name : '',
                    '{UPDATER_FIRST_NAME}' => $data['updater_first_name'],
                    '{UPDATER_LAST_NAME}' => $data['updater_last_name'],
                    '{OLD_STATUS}' => $data['old_status'],
                    '{NEW_STATUS}' => $data['new_status'],
                    '{COMPANY_TITLE}' => $companyTitle
                ];
                break;
            case 'birthday_wish':
                $subjectPlaceholders = [
                    '{FIRST_NAME}' => $recipient->first_name,
                    '{LAST_NAME}' => $recipient->last_name,
                    '{BIRTHDAY_COUNT}' => $data['birthday_count'],
                    '{ORDINAL_SUFFIX}' => $data['ordinal_suffix'],
                    '{COMPANY_TITLE}' => $companyTitle
                ];
                break;
            case 'work_anniversary_wish':
                $subjectPlaceholders = [
                    '{FIRST_NAME}' => $recipient->first_name,
                    '{LAST_NAME}' => $recipient->last_name,
                    '{WORK_ANNIVERSARY_COUNT}' => $data['work_anniversary_count'],
                    '{ORDINAL_SUFFIX}' => $data['ordinal_suffix'],
                    '{COMPANY_TITLE}' => $companyTitle
                ];
                break;
        }
        if (filled(Arr::get($fetched_data, 'subject'))) {
            $subject = $fetched_data->subject;
        } else {
            if ($data['type'] == 'leave_request_creation') {
                $subject = 'Leave Requested';
            } elseif ($data['type'] == 'leave_request_status_updation') {
                $subject = 'Leave Request Status Updated';
            } elseif ($data['type'] == 'team_member_on_leave_alert') {
                $subject = 'Team Member on Leave Alert';
            } elseif ($data['type'] == 'project_status_updation') {
                $subject = 'Project Status Updated';
            } elseif ($data['type'] == 'task_status_updation') {
                $subject = 'Task Status Updated';
            } elseif ($data['type'] == 'birthday_wish') {
                $subject = 'Happy Birthday!';
            } elseif ($data['type'] == 'work_anniversary_wish') {
                $subject = 'Happy Work Anniversary!';
            } else {
                $subject = 'New ' . ucfirst($data['type']) . ' Assigned';
            }
        }
        $subject = str_replace(array_keys($subjectPlaceholders), array_values($subjectPlaceholders), $subject);
        return $subject;
    }
}
if (!function_exists('hasPrimaryWorkspace')) {
    function hasPrimaryWorkspace()
    {
        $primaryWorkspace = \App\Models\Workspace::where('is_primary', 1)->first();
        return $primaryWorkspace ? $primaryWorkspace->id : 0;
    }
}
if (!function_exists('getWorkspaceId')) {
    function getWorkspaceId()
    {
        $workspaceId = 0;
        $authenticatedUser = getAuthenticatedUser();
        if ($authenticatedUser) {
            if (session()->has('workspace_id')) {
                // dd(getAuthenticatedUser());
                $workspaceId = session('workspace_id'); // Retrieve workspace_id from session
            } else {
                $workspaceId = request()->header('workspace_id');
            }
        }
        return $workspaceId;
    }
}
if (!function_exists('getGuardName')) {
    function getGuardName()
    {
        static $guardName = null;
        // If the guard name is already determined, return it
        if ($guardName !== null) {
            return $guardName;
        }
        // Check the 'web' guard (users)
        if (Auth::guard('web')->check()) {
            $guardName = 'web';
        }
        // Check the 'client' guard (clients)
        elseif (Auth::guard('client')->check()) {
            $guardName = 'client';
        }
        // Check the 'sanctum' guard (API tokens)
        elseif (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            // Determine if the sanctum user is a user or a client
            if ($user instanceof \App\Models\User) {
                $guardName = 'web';
            } elseif ($user instanceof \App\Models\Client) {
                $guardName = 'client';
            }
        }
        return $guardName;
    }
}
if (!function_exists('formatProject')) {
    function formatProject($project)
    {
        $customFields = CustomField::where('module', 'project')->get();
        $customFields->transform(function ($field) {
            // Check if options is not already a string (or is an array/object)
            if (is_string($field->options)) {
                $field->options = json_decode($field->options);
            }
            return $field;
        });
        // dd($project->customFieldValues);
        // Prepare custom field values for the view
        $customFieldValues = [];
        foreach ($project->customFieldValues as $fieldValue) {
            $value = $fieldValue->value;
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                // Replace all nulls with empty string
                array_walk_recursive($decoded, function (&$item) {
                    if (is_null($item)) {
                        $item = '';
                    }
                });
                $customFieldValues[$fieldValue->custom_field_id] = $decoded;
            } else {
                // If it's a single null value, also convert to empty string
                $customFieldValues[$fieldValue->custom_field_id] = [is_null($value) ? '' : $value];
            }
        }
        $auth_user = getAuthenticatedUser();
        return [
            'id' => $project->id,
            'title' => $project->title,
            'task_count' => isAdminOrHasAllDataAccess() ? count($project->tasks) : $auth_user->project_tasks($project->id)->count(),
            'status' => $project->status->title,
            'status_id' => $project->status->id,
            'priority' => $project->priority ? $project->priority->title : null,
            'priority_id' => $project->priority ? $project->priority->id : null,
            'users' => $project->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')
                ];
            }),
            'user_id' => $project->users->pluck('id')->toArray(),
            'clients' => $project->clients->map(function ($client) {
                return [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'photo' => $client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')
                ];
            }),
            'client_id' => $project->clients->pluck('id')->toArray(),
            'tags' => $project->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'title' => $tag->title
                ];
            }),
            'tag_ids' => $project->tags->pluck('id')->toArray(),
            'start_date' => $project->start_date ? format_date($project->start_date, to_format: 'Y-m-d') : null,
            'end_date' => $project->end_date ? format_date($project->end_date, to_format: 'Y-m-d') : null,
            'budget' => $project->budget ?? null,
            'task_accessibility' => $project->task_accessibility,
            'description' => $project->description,
            'note' => $project->note,
            'favorite' => getFavoriteStatus($project->id),
            'pinned' => getPinnedStatus($project->id),
            'client_can_discuss' => $project->client_can_discuss,
            'created_at' => format_date($project->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($project->updated_at, to_format: 'Y-m-d'),
            'customFields' => $customFields,
            'customFieldValues' => $customFieldValues
        ];
    }
}
if (!function_exists('formatTask')) {
    function formatTask($task)
    {
        $task->load('reminders', 'recurringTask');
        $reminder = $task->reminders[0] ?? null;
        $recurringTask = $task->recurringTask ?? null;
        $customFields = CustomField::where('module', 'task')->get();
        $customFields->transform(function ($field) {
            // Check if options is not already a string (or is an array/object)
            if (is_string($field->options)) {
                $field->options = json_decode($field->options);
            }
            return $field;
        });
        // Prepare custom field values for the view
        $customFieldValues = [];
        foreach ($task->customFieldValues as $fieldValue) {
            $value = $fieldValue->value;
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                // Replace all nulls with empty string
                array_walk_recursive($decoded, function (&$item) {
                    if (is_null($item)) {
                        $item = '';
                    }
                });
                $customFieldValues[$fieldValue->custom_field_id] = $decoded;
            } else {
                // If it's a single null value, also convert to empty string
                $customFieldValues[$fieldValue->custom_field_id] = [is_null($value) ? '' : $value];
            }
        }
        return [
            'id' => $task->id,
            'workspace_id' => $task->workspace_id,
            'title' => $task->title,
            'status' => $task->status->title,
            'status_id' => $task->status->id,
            'priority' => $task->priority ? $task->priority->title : null,
            'priority_id' => $task->priority ? $task->priority->id : null,
            'users' => $task->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')
                ];
            }),
            'user_id' => $task->users->pluck('id')->toArray(),
            'clients' => $task->project->clients->map(function ($client) {
                return [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'photo' => $client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')
                ];
            }),
            'start_date' => $task->start_date ? format_date($task->start_date, to_format: 'Y-m-d') : null,
            'due_date' => $task->due_date ? format_date($task->due_date, to_format: 'Y-m-d') : null,
            'project' => $task->project->title,
            'project_id' => $task->project->id,
            'description' => $task->description,
            'note' => $task->note,
            'favorite' => getFavoriteStatus($task->id, \App\Models\Task::class),
            'pinned' => getPinnedStatus($task->id, \App\Models\Task::class),
            'client_can_discuss' => $task->client_can_discuss,
            'created_at' => format_date($task->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($task->updated_at, to_format: 'Y-m-d'),
            'enable_reminder' => $reminder ? 1 : 0,
            'last_reminder_sent' => $reminder && $reminder->last_sent_at ? \Carbon\Carbon::parse($reminder->last_sent_at)->diffForHumans() : null,
            'frequency_type' => $reminder ? $reminder->frequency_type : null,
            'day_of_week' => $reminder && $reminder->day_of_week ? (int)$reminder->day_of_week : null,
            'day_of_month' => $reminder && $reminder->day_of_month ? (int)$reminder->day_of_month : null,
            'time_of_day' => $reminder ? $reminder->time_of_day : null,
            'enable_recurring_task' => $recurringTask ? 1 : 0,
            'recurrence_frequency' => $recurringTask ? $recurringTask->frequency : null,
            'recurrence_day_of_week' => $recurringTask && $recurringTask->day_of_week ? (int)$recurringTask->day_of_week : null,
            'recurrence_day_of_month' => $recurringTask && $recurringTask->day_of_month ? (int)$recurringTask->day_of_month : null,
            'recurrence_month_of_year' => $recurringTask && $recurringTask->month_of_year ? (int)$recurringTask->month_of_year : null,
            'recurrence_starts_from' => $recurringTask ? format_date($recurringTask->starts_from, to_format: 'Y-m-d') : null,
            'recurrence_occurrences' => $recurringTask && $recurringTask->number_of_occurrences ? (int)$recurringTask->number_of_occurrences : null,
            'completed_occurrences' => $recurringTask && $recurringTask->completed_occurrences ? (int)$recurringTask->completed_occurrences : null,
            'billing_type' => $task->billing_type,
            'completion_percentage' => $task->completion_percentage,
            'task_list_id' => $task->task_list_id,
            'customFields' => $customFields,
            'customFieldValues' => $customFieldValues,
        ];
    }
}
if (!function_exists('formatWorkspace')) {
    function formatWorkspace($workspace)
    {
        $authUser = getAuthenticatedUser();
        return [
            'id' => $workspace->id,
            'title' => $workspace->title,
            'primaryWorkspace' => $workspace->is_primary,
            'defaultWorkspace' => $authUser->default_workspace_id == $workspace->id ? 1 : 0,
            'users' => $workspace->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')
                ];
            }),
            'user_ids' => $workspace->users->pluck('id')->toArray(),
            'clients' => $workspace->clients->map(function ($client) {
                return [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'photo' => $client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')
                ];
            }),
            'client_ids' => $workspace->clients->pluck('id')->toArray(),
            'created_at' => format_date($workspace->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($workspace->updated_at, to_format: 'Y-m-d'),
        ];
    }
}
// formating email templates for api
if (!function_exists('formatEmailTemplate')) {
    function formatEmailTemplate($template)
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'subject' => $template->subject,
            'body' => $template->body,
            'workspace_id' => $template->workspace_id,
            'placeholders' => $template->placeholders,
            'created_at' => format_date($template->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($template->updated_at, to_format: 'Y-m-d'),
        ];
    }
}
/// formating sent email for api
if (!function_exists('formatEmailSend')) {
    function formatEmailSend($email)
    {
        return [
            'id' => $email->id,
            'user_id' => $email->user_id,
            'email_template_id' => $email->email_template_id,
            'workspace_id' => $email->workspace_id,
            'to_email' => $email->to_email,
            'subject' => $email->subject,
            'body' => $email->body,
            'placeholders' => $email->placeholders ?? null,
            'status' => $email->status,
            'scheduled_at' => $email->scheduled_at ? format_date($email->scheduled_at, to_format: 'Y-m-d H:i:s') : null,
            'attachments' => $email->getMedia('email-media')->map(function ($media) {
                return [
                    'id' => $media->id,
                    'file_name' => $media->file_name,
                    'url' => $media->getUrl(),
                    'size' => $media->size,
                    'mime_type' => $media->mime_type,
                ];
            })->toArray(),
            'created_at' => format_date($email->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($email->updated_at, to_format: 'Y-m-d'),
        ];
    }
}
// formating candidates for api
if (!function_exists('formatCandidate')) {
    function formatCandidate($candidate)
    {
        return [
            'id' => $candidate->id,
            'name' => $candidate->name,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'position' => $candidate->position,
            'source' => $candidate->source,
            'status' => [
                'id' => $candidate->status_id,
                'name' => $candidate->status ? $candidate->status->name : null,
            ],
            'attachments' => $candidate->getMedia('candidate-media')->map(function ($media) {
                return [
                    'id' => $media->id,
                    'file_name' => $media->file_name,
                    'url' => $media->getUrl(),
                    'size' => round($media->size / 1024, 2) . ' KB',
                    'mime_type' => $media->mime_type,
                    'uploaded_date' => format_date($media->created_at)
                ];
            })->toArray(),
            'interviews' => $candidate->interviews->map(function ($interview) {
                return [
                    'id' => $interview->id,
                    'candidate_name' => $interview->candidate->name,
                    'interviewer' => $interview->interviewer->first_name . ' ' . $interview->interviewer->last_name,
                    'round' => $interview->round,
                    'scheduled_at' => $interview->scheduled_at,
                    'status' => $interview->status,
                    'location' => $interview->location,
                    'mode' => $interview->mode,
                    'created_at' => format_date($interview->created_at, to_format: 'Y-m-d'),
                    'updated_at' => format_date($interview->updated_at, to_format: 'Y-m-d'),
                ];
            }),
            'created_at' => format_date($candidate->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($candidate->updated_at, to_format: 'Y-m-d'),
        ];
    }
}
if (!function_exists('formatCandidateStuses')) {
    function formatCandidateStatus($status)
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'order' => $status->order,
            'color' => $status->color,
            'created_at' => format_date($status->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($status->updated_at, to_format: 'Y-m-d'),
            'can_edit' => checkPermission('edit_candidate_status'),
            'can_delete' => checkPermission('delete_candidate_status'),
        ];
    }
}
if (!function_exists('formatInterview')) {
    function formatInterview($interview)
    {
        return [
            'id' => $interview->id,
            'candidate_id' => $interview->candidate->id,
            'candidate_name' => $interview->candidate->name,
            'interviewer_id' => $interview->interviewer->id,
            'interviewer_name' => $interview->interviewer->first_name  . " " . $interview->interviewer->last_name,
            'round' => $interview->round,
            'scheduled_at' => format_date($interview->scheduled_at, true, to_format: 'Y-m-d'),
            'mode' => $interview->mode,
            'location' => $interview->location,
            'status' => $interview->status,
        ];
    }
}
if (!function_exists('formatMeeting')) {
    function formatMeeting($meeting)
    {
        $currentDateTime = Carbon::now(config('app.timezone'));
        $status = (($currentDateTime < \Carbon\Carbon::parse($meeting->start_date_time, config('app.timezone'))) ? 'Will start in ' . $currentDateTime->diff(\Carbon\Carbon::parse($meeting->start_date_time, config('app.timezone')))->format('%a days %H hours %I minutes %S seconds') : (($currentDateTime > \Carbon\Carbon::parse($meeting->end_date_time, config('app.timezone')) ? 'Ended before ' . \Carbon\Carbon::parse($meeting->end_date_time, config('app.timezone'))->diff($currentDateTime)->format('%a days %H hours %I minutes %S seconds') : 'Ongoing')));
        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'start_date' => \Carbon\Carbon::parse($meeting->start_date_time)->format('Y-m-d'),
            'start_time' => \Carbon\Carbon::parse($meeting->start_date_time)->format('H:i'),
            'end_date' => \Carbon\Carbon::parse($meeting->end_date_time)->format('Y-m-d'),
            'end_time' => \Carbon\Carbon::parse($meeting->end_date_time)->format('H:i'),
            'users' => $meeting->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')
                ];
            }),
            'user_ids' => $meeting->users->pluck('id')->toArray(),
            'clients' => $meeting->clients->map(function ($client) {
                return [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'photo' => $client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')
                ];
            }),
            'client_ids' => $meeting->clients->pluck('id')->toArray(),
            'status' => $status,
            'ongoing' => $status == 'Ongoing' ? 1 : 0,
            'join_url' => url('meetings/join/web-view/' . $meeting->id),
            'created_at' => format_date($meeting->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($meeting->updated_at, to_format: 'Y-m-d')
        ];
    }
}
if (!function_exists('formatNotification')) {
    function formatNotification($notification)
    {
        $readAt = isset($notification->notification_user_read_at)
            ? format_date($notification->notification_user_read_at, true)
            : (isset($notification->client_notifications_read_at)
                ? format_date($notification->client_notifications_read_at, true)
                : (isset($notification->pivot) && isset($notification->pivot->read_at)
                    ? format_date($notification->pivot->read_at, true)
                    : null));
        $labelRead = get_label('read', 'Read');
        $labelUnread = get_label('unread', 'Unread');
        $status = is_null($readAt) ? $labelUnread : $labelRead;
        // Handle is_system logic, including pivot
        $isSystem = $notification->notification_user_is_system
            ?? $notification->client_notifications_is_system
            ?? ($notification->pivot->is_system ?? null);
        // Handle is_push logic, including pivot
        $isPush = $notification->notification_user_is_push
            ?? $notification->client_notifications_is_push
            ?? ($notification->pivot->is_push ?? null);
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'users' => $notification->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')
                ];
            }),
            'clients' => $notification->clients->map(function ($client) {
                return [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'photo' => $client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')
                ];
            }),
            'type' => ucfirst(str_replace('_', ' ', $notification->type)),
            'type_id' => $notification->type_id,
            'message' => $notification->message,
            'status' => $status,
            'is_system' => $isSystem,
            'is_push' => $isPush,
            'read_at' => $readAt,
            'created_at' => format_date($notification->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($notification->updated_at, to_format: 'Y-m-d')
        ];
    }
}
if (!function_exists('formatLeaveRequest')) {
    function formatLeaveRequest($leaveRequest)
    {
        $leaveRequest = LeaveRequest::select(
            'leave_requests.*',
            'users.photo AS user_photo',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name'),
            DB::raw('CONCAT(action_users.first_name, " ", action_users.last_name) AS action_by_name'),
            'leave_requests.action_by as action_by_id'
        )
            ->leftJoin('users', 'leave_requests.user_id', '=', 'users.id')
            ->leftJoin('users AS action_users', 'leave_requests.action_by', '=', 'action_users.id')
            ->where('leave_requests.workspace_id', getWorkspaceId())
            ->find($leaveRequest->id);
        // Calculate the duration in hours if both from_time and to_time are provided
        $fromDate = Carbon::parse($leaveRequest->from_date);
        $toDate = Carbon::parse($leaveRequest->to_date);
        $fromDateDayOfWeek = $fromDate->format('D');
        $toDateDayOfWeek = $toDate->format('D');
        if ($leaveRequest->from_time && $leaveRequest->to_time) {
            $duration = 0;
            // Loop through each day
            while ($fromDate->lessThanOrEqualTo($toDate)) {
                // Create Carbon instances for the start and end times of the leave request for the current day
                $fromDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leaveRequest->from_time);
                $toDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leaveRequest->to_time);
                // Calculate the duration for the current day and add it to the total duration
                $duration += $fromDateTime->diffInMinutes($toDateTime) / 60; // Duration in hours
                // Move to the next day
                $fromDate->addDay();
            }
        } else {
            // Calculate the inclusive duration in days
            $duration = $fromDate->diffInDays($toDate) + 1;
        }
        if ($leaveRequest->visible_to_all == 1) {
            $visibleTo = [];
        } else {
            $visibleTo = $leaveRequest->visibleToUsers->isEmpty()
                ? null
                : $leaveRequest->visibleToUsers->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')
                    ];
                });
        }
        $visibleToIds = $leaveRequest->visibleToUsers->pluck('id')->toArray();
        return [
            'id' => $leaveRequest->id,
            'user_id' => $leaveRequest->user_id,
            'user_name' => $leaveRequest->user_name,
            'user_photo' => $leaveRequest->user_photo ? asset('storage/' . $leaveRequest->user_photo) : asset('storage/photos/no-image.jpg'),
            'action_by' => $leaveRequest->action_by_name,
            'action_by_id' => $leaveRequest->action_by_id,
            'from_date' => $leaveRequest->from_date,
            'from_time' => Carbon::parse($leaveRequest->from_time)->format('h:i A'),
            'to_date' => $leaveRequest->to_date,
            'to_time' => Carbon::parse($leaveRequest->to_time)->format('h:i A'),
            'type' => $leaveRequest->from_time && $leaveRequest->to_time ? 'Partial' : 'Full',
            'leaveVisibleToAll' => $leaveRequest->visible_to_all ? 'on' : 'off',
            'partialLeave' => $leaveRequest->from_time && $leaveRequest->to_time ? 'on' : 'off',
            'duration' => ($leaveRequest->from_time && $leaveRequest->to_time)
                ? (string) $duration
                : (string) number_format($duration, 2),
            'reason' => $leaveRequest->reason,
            'comment' => $leaveRequest->comment,
            'status' => $leaveRequest->status,
            'visible_to' => $visibleTo ?? [],
            'visible_to_ids' => $visibleToIds ?? [],
            'created_at' => format_date($leaveRequest->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($leaveRequest->updated_at, to_format: 'Y-m-d'),
        ];
    }
}
if (!function_exists('formatUser')) {
    function formatUser($user, $isSignup = false)
    {
        $fcmToken = FcmToken::where('user_id', $user->id)->latest()->value('fcm_token');
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => $user->getRoleNames()->count() > 0 ? $user->getRoleNames()->first() : null,
            'role_id' => $user->roles()->count() > 0 ? $user->roles()->first()->id : null,
            'email' => $user->email,
            'phone' => $user->phone,
            'country_code' => $user->country_code,
            'country_iso_code' => $user->country_iso_code,
            'password' => $user->password,
            'password_confirmation' => $user->password,
            'type' => 'member',
            'dob' => $user->dob ? format_date($user->dob, to_format: 'Y-m-d') : null,
            'doj' => $user->doj ? format_date($user->doj, to_format: 'Y-m-d') : null,
            'address' => $user->address,
            'city' => $user->city,
            'state' => $user->state,
            'country' => $user->country,
            'zip' => $user->zip,
            'profile' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg'),
            'status' => $user->status,
            'fcm_token' => $fcmToken,
            'created_at' => format_date($user->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($user->updated_at, to_format: 'Y-m-d'),
            'assigned' => $isSignup ? [
                'projects' => 0,
                'tasks' => 0
            ] : (
                isAdminOrHasAllDataAccess('user', $user->id) ? [
                    'projects' => Workspace::find(getWorkspaceId())->projects()->count(),
                    'tasks' => Workspace::find(getWorkspaceId())->tasks()->count(),
                ] : [
                    'projects' => $user->projects()->count(),
                    'tasks' => $user->tasks()->count()
                ]
            )
        ];
    }
}
if (!function_exists('formatClient')) {
    function formatClient($client, $isSignup = false)
    {
        return [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'role' => $client->getRoleNames()->first(),
            'company' => $client->company,
            'email' => $client->email,
            'phone' => $client->phone,
            'country_code' => $client->country_code,
            'country_iso_code' => $client->country_iso_code,
            'password' => $client->password,
            'password_confirmation' => $client->password,
            'type' => 'client',
            'dob' => $client->dob ? format_date($client->dob, to_format: 'Y-m-d') : null,
            'doj' => $client->doj ? format_date($client->doj, to_format: 'Y-m-d') : null,
            'address' => $client->address ? $client->address : null,
            'city' => $client->city,
            'state' => $client->state,
            'country' => $client->country,
            'zip' => $client->zip,
            'profile' => $client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg'),
            'status' => $client->status,
            'fcm_token' => $client->fcm_token,
            'internal_purpose' => $client->internal_purpose,
            'email_verification_mail_sent' => $client->email_verification_mail_sent,
            'email_verified_at' => $client->email_verified_at,
            'created_at' => format_date($client->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($client->updated_at, to_format: 'Y-m-d'),
            'assigned' => $isSignup ? [
                'projects' => 0,
                'tasks' => 0
            ] : (
                isAdminOrHasAllDataAccess('client', $client->id) ? [
                    'projects' => Workspace::find(getWorkspaceId())->projects()->count(),
                    'tasks' => Workspace::find(getWorkspaceId())->tasks()->count(),
                ] : [
                    'projects' => $client->projects()->count(),
                    'tasks' => $client->tasks()->count()
                ]
            )
        ];
    }
}
if (!function_exists('formatNote')) {
    function formatNote($note)
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'color' => $note->color,
            'type' => $note->note_type,
            'drawing_data' => $note->drawing_data,
            'description' => $note->description,
            'workspace_id' => $note->workspace_id,
            'creator_id' => $note->creator_id,
            'created_at' => format_date($note->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($note->updated_at, to_format: 'Y-m-d'),
        ];
    }
}
if (!function_exists('formatTodo')) {
    function formatTodo($todo)
    {
        return [
            'id' => $todo->id,
            'title' => $todo->title,
            'description' => $todo->description,
            'priority' => $todo->priority,
            'is_completed' => $todo->is_completed,
            'created_at' => format_date($todo->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($todo->updated_at, to_format: 'Y-m-d'),
        ];
    }
}
if (!function_exists('formatRole')) {
    function formatRole($role)
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions->pluck('name'),
            'created_at' => format_date($role->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($role->updated_at, to_format: 'Y-m-d'),
        ];
    }
}
if (!function_exists('validate_date_format_and_order')) {
    /**
     * Validate if a date matches the format specified and ensure the start date is before or equal to the end date.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $format
     * @param string $startDateLabel
     * @param string $endDateLabel
     * @param string $startDateKey
     * @param string $endDateKey
     * @return array
     */
    function validate_date_format_and_order(
        $startDate,
        $endDate,
        $format = null,
        $startDateLabel = 'start date',
        $endDateLabel = 'end date',
        $startDateKey = 'start_date',
        $endDateKey = 'end_date'
    ) {
        $matchFormat = $format ?? get_php_date_time_format();
        $errors = [];
        // Validate start date format
        if ($startDate && !validate_date_format($startDate, $matchFormat)) {
            $errors[$startDateKey][] = 'The ' . $startDateLabel . ' does not follow the format set in settings.';
        }
        // Validate end date format
        if ($endDate && !validate_date_format($endDate, $matchFormat)) {
            $errors[$endDateKey][] = 'The ' . $endDateLabel . ' does not follow the format set in settings.';
        }
        // Validate date order
        if ($startDate && $endDate) {
            $parsedStartDate = \DateTime::createFromFormat($matchFormat, $startDate);
            $parsedEndDate = \DateTime::createFromFormat($matchFormat, $endDate);
            if ($parsedStartDate && $parsedEndDate && $parsedStartDate > $parsedEndDate) {
                $errors[$startDateKey][] = 'The ' . $startDateLabel . ' must be before or equal to the ' . $endDateLabel . '.';
            }
        }
        return $errors;
    }
}
if (!function_exists('validate_date_format')) {
    /**
     * Validate if a date matches the format specified in settings.
     *
     * @param string $date
     * @param string|null $format
     * @return bool
     */
    function validate_date_format($date, $format = null)
    {
        $format = $format ?? get_php_date_time_format();
        $parsedDate = \DateTime::createFromFormat($format, $date);
        return $parsedDate && $parsedDate->format($format) === $date;
    }
}
if (!function_exists('validate_currency_format')) {
    function validate_currency_format($value, $label)
    {
        $regex = '/^(?:\d{1,3}(?:,\d{3})*|\d+)(\.\d+)?$/';
        if (!preg_match($regex, $value)) {
            return "The $label format is invalid.";
        }
        return null;
    }
}
if (!function_exists('formatApiResponse')) {
    function formatApiResponse($error, $message, array $optionalParams = [], $statusCode = 200)
    {
        $response = [
            'error' => $error,
            'message' => $message,
        ];
        // Merge optional parameters into the response if they are provided
        $response = array_merge($response, $optionalParams);
        return response()->json($response, $statusCode);
    }
}
if (!function_exists('isSanctumAuth')) {
    function isSanctumAuth()
    {
        return Auth::guard('web')->check() || Auth::guard('client')->check() ? false : true;
    }
}
if (!function_exists('formatApiValidationError')) {
    function formatApiValidationError($isApi, $errors, $defaultMessage = 'Validation errors occurred')
    {
        if ($isApi) {
            $messages = collect($errors)->flatten()->implode("\n");
            return response()->json([
                'error' => true,
                'message' => $messages,
            ], 422);
        } else {
            return response()->json([
                'error' => true,
                'message' => $defaultMessage,
                'errors' => $errors,
            ], 422);
        }
    }
}
if (!function_exists('getMimeTypeMap')) {
    function getMimeTypeMap()
    {
        return [
            // Image MIME Types
            '.jpg' => 'image/jpeg',
            '.jpeg' => 'image/jpeg',
            '.png' => 'image/png',
            '.gif' => 'image/gif',
            '.bmp' => 'image/bmp',
            '.svg' => 'image/svg+xml',
            '.webp' => 'image/webp',
            '.tiff' => 'image/tiff',
            '.ico' => 'image/vnd.microsoft.icon',
            '.psd' => 'image/vnd.adobe.photoshop',
            '.heic' => 'image/heic',
            // Document MIME Types
            '.pdf' => 'application/pdf',
            '.doc' => 'application/msword',
            '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            '.xls' => 'application/vnd.ms-excel',
            '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            '.ppt' => 'application/vnd.ms-powerpoint',
            '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            '.txt' => 'text/plain',
            '.rtf' => 'application/rtf',
            '.odt' => 'application/vnd.oasis.opendocument.text',
            '.ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            '.odp' => 'application/vnd.oasis.opendocument.presentation',
            '.csv' => 'text/csv',
            '.md' => 'text/markdown',
            // Archive MIME Types
            '.zip' => 'application/zip',
            '.rar' => 'application/x-rar-compressed',
            '.7z' => 'application/x-7z-compressed',
            '.tar' => 'application/x-tar',
            '.gz' => 'application/gzip',
            '.bz2' => 'application/x-bzip2',
            '.xz' => 'application/x-xz',
            '.iso' => 'application/x-iso9660-image',
            // Audio MIME Types
            '.mp3' => 'audio/mpeg',
            '.wav' => 'audio/wav',
            '.ogg' => 'audio/ogg',
            '.flac' => 'audio/flac',
            '.aac' => 'audio/aac',
            '.m4a' => 'audio/x-m4a',
            '.wma' => 'audio/x-ms-wma',
            '.aiff' => 'audio/aiff',
            '.opus' => 'audio/opus',
            '.amr' => 'audio/amr',
            // Video MIME Types
            '.mp4' => 'video/mp4',
            '.avi' => 'video/x-msvideo',
            '.mov' => 'video/quicktime',
            '.wmv' => 'video/x-ms-wmv',
            '.flv' => 'video/x-flv',
            '.mkv' => 'video/x-matroska',
            '.webm' => 'video/webm',
            '.3gp' => 'video/3gpp',
            '.m4v' => 'video/x-m4v',
            '.mpg' => 'video/mpeg',
            '.mpeg' => 'video/mpeg',
            // Executable MIME Types
            '.exe' => 'application/vnd.microsoft.portable-executable',
            '.bat' => 'application/x-msdownload',
            '.sh' => 'application/x-sh',
            '.bin' => 'application/octet-stream',
            '.msi' => 'application/x-msi',
            '.cmd' => 'application/x-msdownload',
            '.jar' => 'application/java-archive',
            '.apk' => 'application/vnd.android.package-archive',
            // Code MIME Types
            '.html' => 'text/html',
            '.htm' => 'text/html',
            '.css' => 'text/css',
            '.js' => 'application/javascript',
            '.php' => 'application/x-httpd-php',
            '.java' => 'text/x-java-source',
            '.py' => 'text/x-python',
            '.rb' => 'application/x-ruby',
            '.pl' => 'application/x-perl',
            '.cpp' => 'text/x-c++',
            '.c' => 'text/x-c',
            '.h' => 'text/x-c',
            '.cs' => 'text/x-csharp',
            '.xml' => 'application/xml',
            '.json' => 'application/json',
            '.yml' => 'text/yaml',
            '.sql' => 'application/sql',
            // Font MIME Types
            '.ttf' => 'font/ttf',
            '.otf' => 'font/otf',
            '.woff' => 'font/woff',
            '.woff2' => 'font/woff2',
            '.eot' => 'application/vnd.ms-fontobject',
            // Miscellaneous MIME Types
            '.ics' => 'text/calendar',
            '.vcf' => 'text/x-vcard',
            '.swf' => 'application/x-shockwave-flash',
            '.epub' => 'application/epub+zip',
            '.mobi' => 'application/x-mobipocket-ebook',
            '.azw' => 'application/vnd.amazon.ebook',
            '.bak' => 'application/octet-stream'
        ];
    }
}
if (!function_exists('getMainAdminId')) {
    function getMainAdminId()
    {
        $mainAdminId = DB::table('model_has_roles')
            ->where('role_id', 1)
            ->orderBy('model_id')
            ->value('model_id');
        return $mainAdminId;
    }
}
if (!function_exists('getMenus')) {
    function getMenus()
    {
        $user = getAuthenticatedUser();
        $current_workspace_id = getWorkspaceId();
        $messenger = new ChatifyMessenger();
        $unread = $messenger->totalUnseenMessages();
        $pending_todos_count = $user->todos(0)->count();
        $ongoing_meetings_count = $user->meetings('ongoing')->count();
        $query = LeaveRequest::where('status', 'pending')
            ->where('workspace_id', $current_workspace_id);
        if (!is_admin_or_leave_editor()) {
            $query->where('user_id', $user->id);
        }
        $pendingLeaveRequestsCount = $query->count();
        return [
            [
                'id' => 'dashboard',
                'label' => get_label('dashboard', 'Dashboard'),
                'url' => url('home'),
                'icon' => 'bx bx-home-circle',
                'class' => 'menu-item' . (Request::is('home') ? ' active' : ''),
                'category' => 'dashboard',
            ],
            [
                'id' => 'projects',
                'label' => get_label('projects', 'Projects'),
                'url' => url('projects'),
                'icon' => 'bx bx-briefcase-alt-2',
                'class' => 'menu-item' . (Request::is('projects') || Request::is('tags/*') || Request::is('projects/*') ? ' active open' : ''),
                'category' => 'projects_and_task_management',
                'show' => ($user->can('manage_projects') || $user->can('manage_tags')) ? 1 : 0,
                'submenus' => [
                    [
                        'id' => 'manage_projects',
                        'label' => get_label('manage_projects', 'Manage projects'),
                        'url' => url(getUserPreferences('projects', 'default_view')),
                        'class' => 'menu-item' . (Request::is('projects') || (Request::is('projects/*') && !Request::is('projects/*/favorite') && !Request::is('projects/favorite') && !Request::is('projects/bulk-upload')) ? ' active' : ''),
                        'show' => ($user->can('manage_projects')) ? 1 : 0
                    ],
                    [
                        'id' => 'favorite_projects',
                        'label' => get_label('favorite_projects', 'Favorite projects'),
                        'url' => url(getUserPreferences('projects', 'default_view') . '/favorite'),
                        'class' => 'menu-item' . (Request::is('projects/favorite') || Request::is('projects/list/favorite') || Request::is('projects/kanban/favorite') ? ' active' : ''),
                        'show' => ($user->can('manage_projects')) ? 1 : 0
                    ],
                    [
                        'id' => 'projects_bulk_upload',
                        'label' => get_label('bulk_upload', 'Bulk Upload'),
                        'url' => route('projects.showBulkUploadForm'),
                        'class' => 'menu-item' . (Request::is('projects/bulk-upload') ? ' active' : ''),
                        'show' => ($user->can('manage_projects') && $user->can('create_projects')) ? 1 : 0
                    ],
                    [
                        'id' => 'tags',
                        'label' => get_label('tags', 'Tags'),
                        'url' => url('tags/manage'),
                        'class' => 'menu-item' . (Request::is('tags/*') ? ' active' : ''),
                        'show' => ($user->can('manage_tags')) ? 1 : 0
                    ],
                    [
                        'id' => 'task-lists',
                        'label' => get_label('task_lists', 'Task lists'),
                        'url' => url('/task-lists'),
                        'class' => 'menu-item' . (Request::is('task-lists/*') ? ' active' : ''),
                        'show' =>  1
                    ],
                ],
            ],
            [
                'id' => 'tasks',
                'label' => get_label('tasks', 'Tasks'),
                'url' => url('tasks'),
                'icon' => 'bx bx-task',
                'class' => 'menu-item' . (Request::is('tasks') || Request::is('tasks/*') ? ' active open' : ''),
                'show' => $user->can('manage_tasks') ? 1 : 0,
                'category' => 'projects_and_task_management',
                'submenus' => [
                    [
                        'id' => 'manage_tasks',
                        'label' => get_label('manage_tasks', 'Manage Tasks'),
                        'url' => url(getUserPreferences('tasks', 'default_view')),
                        'class' => 'menu-item' . (!(request()->query('favorite')) && (Request::is('tasks') || Request::is('tasks/*') && !Request::is('tasks/bulk-upload')) ? ' active' : ''),
                        'show' => ($user->can('manage_tasks')) ? 1 : 0
                    ],
                    [
                        'id' => 'favorite_tasks',
                        'label' => get_label('favorite_tasks', 'Favorite Tasks'),
                        'url' => url(getUserPreferences('tasks', 'default_view') . '?favorite=1'),
                        'class' => 'menu-item' . (request()->query('favorite') && (Request::is('tasks') || Request::is('tasks/calendar') || Request::is('tasks/draggable')) ? ' active' : ''),
                        'show' => ($user->can('manage_tasks')) ? 1 : 0
                    ],
                    [
                        'id' => 'tasks_bulk_upload',
                        'label' => get_label('bulk_upload', 'Bulk Upload'),
                        'url' => route('tasks.showBulkUploadForm'),
                        'class' => 'menu-item' . (Request::is('tasks/bulk-upload') ? ' active' : ''),
                        'show' => ($user->can('manage_tasks') && $user->can('create_tasks')) ? 1 : 0
                    ],
                ],
            ],
            [
                'id' => 'statuses',
                'label' => get_label('statuses', 'Statuses'),
                'url' => url('status/manage'),
                'icon' => 'bx bx-grid-small',
                'class' => 'menu-item' . (Request::is('status/manage') ? ' active' : ''),
                'show' => $user->can('manage_statuses') ? 1 : 0,
                'category' => 'projects_and_task_management',
            ],
            [
                'id' => 'priorities',
                'label' => get_label('priorities', 'Priorities'),
                'url' => url('priority/manage'),
                'icon' => 'bx bx-up-arrow-alt',
                'class' => 'menu-item' . (Request::is('priority/manage') ? ' active' : ''),
                'show' => $user->can('manage_priorities') ? 1 : 0,
                'category' => 'projects_and_task_management',
            ],
            [
                'id' => 'workspaces',
                'label' => get_label('workspaces', 'Workspaces'),
                'url' => url('workspaces'),
                'icon' => 'bx bx-check-square',
                'class' => 'menu-item' . (Request::is('workspaces') || Request::is('workspaces/*') ? ' active' : ''),
                'show' => $user->can('manage_workspaces') ? 1 : 0,
                'category' => 'team',
            ],
            [
                'id' => 'chat',
                'label' => get_label('chat', 'Chat'),
                'url' => url('chat'),
                'icon' => 'bx bx-chat',
                'class' => 'menu-item' . (Request::is('chat') || Request::is('chat/*') ? ' active' : ''),
                'badge' => ($unread > 0) ? '<span class="flex-shrink-0 badge badge-center bg-danger w-px-20 h-px-20">' . $unread . '</span>' : '',
                'show' => Auth::guard('web')->check() ? 1 : 0,
                'category' => 'team',
            ],
            [
                'id' => 'leads_management',
                'label' => get_label('leads_management', 'Leads Management'),
                'url' => '',
                'icon' => 'bx bxs-phone-call',
                'class' => 'menu-item ' . (Request::is('lead-sources') || Request::is('lead-sources/*') || Request::is('lead-stages') || Request::is('lead-stages/*') || Request::is('leads') || Request::is('leads/*') ? 'active open' : ''),
                'category' => 'utilities',
                'show' =>  $user->can('manage_leads') ? 1 : 0,
                'submenus' => [
                    [
                        'id' => 'lead_sources',
                        'label' => get_label('lead_sources', 'Lead Sources'),
                        'url' => route('lead-sources.index'),
                        'show' => $user->can('manage_leads') ? 1 : 0,
                        'class' => 'menu-item ' . (Request::is('lead-sources') || Request::is('lead-sources/*') ? 'active' : '')
                    ],
                    [
                        'id' => 'lead_stages',
                        'label' => get_label('lead_stages', 'Lead Stages'),
                        'url' => route('lead-stages.index'),
                        'show' => $user->can('manage_leads') ? 1 : 0,
                        'class' => 'menu-item ' . (Request::is('lead-stages') || Request::is('lead-stages/*') ? 'active' : '')
                    ],
                    [
                        'id' => 'leads',
                        'label' => get_label('leads', 'Leads'),
                        'url' => getDefaultRoute('leads'),
                        'show' => $user->can('manage_leads') ? 1 : 0,
                        'class' => 'menu-item ' . (Request::is('leads') || (Request::is('leads/*') && !Request::is('leads/bulk-upload')) ? 'active' : '')
                    ],
                    [
                        'id' => 'lead_bulk_upload',
                        'label' => get_label('bulk_upload', 'Bulk Upload'),
                        'url' => route('leads.upload'),
                        'class' => 'menu-item' . (Request::is('leads/bulk-upload') ? ' active' : ''),
                        'show' => ($user->can('manage_leads') && $user->can('create_leads')) ? 1 : 0
                    ],
                    [
                        'id' => 'lead_forms',
                        'label' => get_label('lead_forms', 'Lead Forms'),
                        'url' => route('lead-forms.index'),
                        'class' => 'menu-item' . (Request::is('/lead-forms') ? ' active' : ''),
                        'show' => ($user->can('manage_leads') && $user->can('create_leads')) ? 1 : 0
                    ],
                ],
            ],
            [
                'id' => 'email',
                'label' => get_label('email', 'Email'),
                'class' => 'menu-item' . (Request::is('emails') || Request::is('emails/create') || Request::is('email-templates') ? ' active open' : ''),
                'category' => 'utilities',
                'show' => ($user->can('send_email') || $user->can('manage_email_template')) ? 1 : 0,
                'icon' => 'bx bx-mail-send',
                'submenus' => [
                    [
                        'id' => 'email_history',
                        'label' => get_label('send_email', 'Send Email'),
                        'url' => route('emails.sent_list'),
                        'class' => 'menu-item' . (Request::is('emails') || Request::is('emails/create') ? ' active' : ''),
                        'show' => $user->can('send_email') ? 1 : 0
                    ],
                    [
                        'id' => 'email_templates',
                        'label' => get_label('email_templates', 'Email Templates'),
                        'url' => route('email.templates'),
                        'class' => 'menu-item' . (Request::is('email-templates') ? ' active' : ''),
                        'show' => $user->can('manage_email_template') ? 1 : 0
                    ],
                ],
            ],
            [
                'id' => 'todos',
                'label' => get_label('todos', 'Todos'),
                'url' => url('todos'),
                'icon' => 'bx bx-list-check',
                'class' => 'menu-item' . (Request::is('todos') || Request::is('todos/*') ? ' active' : ''),
                'badge' => ($pending_todos_count > 0) ? '<span class="flex-shrink-0 badge badge-center bg-danger w-px-20 h-px-20">' . $pending_todos_count . '</span>' : '',
                'category' => 'utilities',
            ],
            [
                'id' => 'meetings',
                'label' => get_label('meetings', 'Meetings'),
                'url' => getDefaultRoute('meetings'),
                'icon' => 'bx bx-shape-polygon',
                'class' => 'menu-item' . (Request::is('meetings') || Request::is('meetings/*') ? ' active' : ''),
                'badge' => ($ongoing_meetings_count > 0) ? '<span class="flex-shrink-0 badge badge-center bg-success w-px-20 h-px-20">' . $ongoing_meetings_count . '</span>' : '',
                'show' => $user->can('manage_meetings') ? 1 : 0,
                'category' => 'utilities',
            ],
            [
                'id' => 'users',
                'label' => get_label('users', 'Users'),
                'url' => url('users'),
                'icon' => 'bx bx-group',
                'class' => 'menu-item' . (Request::is('users') || Request::is('users/*') ? ' active' : ''),
                'show' => $user->can('manage_users') ? 1 : 0,
                'category' => 'team',
            ],
            [
                'id' => 'clients',
                'label' => get_label('clients', 'Clients'),
                'url' => url('clients'),
                'icon' => 'bx bx-group',
                'class' => 'menu-item' . (Request::is('clients') || Request::is('clients/*') ? ' active' : ''),
                'show' => $user->can('manage_clients') ? 1 : 0,
                'category' => 'team',
            ],
            [
                'id' => 'contracts',
                'label' => get_label('contracts', 'Contracts'),
                'url' => 'javascript:void(0)',
                'icon' => 'bx bx-news',
                'class' => 'menu-item' . (Request::is('contracts') || Request::is('contracts/*') ? ' active open' : ''),
                'show' => ($user->can('manage_contracts') || $user->can('manage_contract_types')) ? 1 : 0,
                'category' => 'finance',
                'submenus' => [
                    [
                        'id' => 'manage_contracts',
                        'label' => get_label('manage_contracts', 'Manage contracts'),
                        'url' => url('contracts'),
                        'class' => 'menu-item' . (Request::is('contracts') ? ' active' : ''),
                        'show' => $user->can('manage_contracts') ? 1 : 0
                    ],
                    [
                        'id' => 'contract_types',
                        'label' => get_label('contract_types', 'Contract types'),
                        'url' => url('contracts/contract-types'),
                        'class' => 'menu-item' . (Request::is('contracts/contract-types') ? ' active' : ''),
                        'show' => $user->can('manage_contract_types') ? 1 : 0
                    ],
                ],
            ],
            [
                'id' => 'payslips',
                'label' => get_label('payslips', 'Payslips'),
                'url' => 'javascript:void(0)',
                'icon' => 'bx bx-box',
                'class' => 'menu-item' . (Request::is('payslips') || Request::is('payslips/*') || Request::is('allowances') || Request::is('deductions') ? ' active open' : ''),
                'show' => ($user->can('manage_payslips') || $user->can('manage_allowances') || $user->can('manage_deductions')) ? 1 : 0,
                'category' => 'finance',
                'submenus' => [
                    [
                        'id' => 'manage_payslips',
                        'label' => get_label('manage_payslips', 'Manage payslips'),
                        'url' => url('payslips'),
                        'class' => 'menu-item' . (Request::is('payslips') || Request::is('payslips/*') ? ' active' : ''),
                        'show' => $user->can('manage_payslips') ? 1 : 0
                    ],
                    [
                        'id' => 'allowances',
                        'label' => get_label('allowances', 'Allowances'),
                        'url' => url('allowances'),
                        'class' => 'menu-item' . (Request::is('allowances') ? ' active' : ''),
                        'show' => $user->can('manage_allowances') ? 1 : 0
                    ],
                    [
                        'id' => 'deductions',
                        'label' => get_label('deductions', 'Deductions'),
                        'url' => url('deductions'),
                        'class' => 'menu-item' . (Request::is('deductions') ? ' active' : ''),
                        'show' => $user->can('manage_deductions') ? 1 : 0
                    ],
                ],
            ],
            [
                'id' => 'finance',
                'label' => get_label('finance', 'Finance'),
                'url' => 'javascript:void(0)',
                'icon' => 'bx bx-box',
                'class' => 'menu-item' . (Request::is('estimates-invoices') || Request::is('estimates-invoices/*') || Request::is('taxes') || Request::is('payment-methods') || Request::is('payments') || Request::is('units') || Request::is('items') || Request::is('expenses') || Request::is('expenses/*') ? ' active open' : ''),
                'show' => ($user->can('manage_estimates_invoices') || $user->can('manage_expenses') || $user->can('manage_payment_methods') ||
                    $user->can('manage_expense_types') || $user->can('manage_payments') || $user->can('manage_taxes') ||
                    $user->can('manage_units') || $user->can('manage_items')) ? 1 : 0,
                'category' => 'finance',
                'submenus' => [
                    [
                        'id' => 'expenses',
                        'label' => get_label('expenses', 'Expenses'),
                        'url' => url('expenses'),
                        'class' => 'menu-item' . (Request::is('expenses') ? ' active' : ''),
                        'show' => $user->can('manage_expenses') ? 1 : 0
                    ],
                    [
                        'id' => 'expense_types',
                        'label' => get_label('expense_types', 'Expense types'),
                        'url' => url('expenses/expense-types'),
                        'class' => 'menu-item' . (Request::is('expenses/expense-types') ? ' active' : ''),
                        'show' => $user->can('manage_expense_types') ? 1 : 0
                    ],
                    [
                        'id' => 'estimates_invoices',
                        'label' => get_label('estimates_invoices', 'Estimates/Invoices'),
                        'url' => url('estimates-invoices'),
                        'class' => 'menu-item' . (Request::is('estimates-invoices') || Request::is('estimates-invoices/*') ? ' active' : ''),
                        'show' => $user->can('manage_estimates_invoices') ? 1 : 0
                    ],
                    [
                        'id' => 'payments',
                        'label' => get_label('payments', 'Payments'),
                        'url' => url('payments'),
                        'class' => 'menu-item' . (Request::is('payments') ? ' active' : ''),
                        'show' => $user->can('manage_payments') ? 1 : 0
                    ],
                    [
                        'id' => 'payment_methods',
                        'label' => get_label('payment_methods', 'Payment methods'),
                        'url' => url('payment-methods'),
                        'class' => 'menu-item' . (Request::is('payment-methods') ? ' active' : ''),
                        'show' => $user->can('manage_payment_methods') ? 1 : 0
                    ],
                    [
                        'id' => 'taxes',
                        'label' => get_label('taxes', 'Taxes'),
                        'url' => url('taxes'),
                        'class' => 'menu-item' . (Request::is('taxes') ? ' active' : ''),
                        'show' => $user->can('manage_taxes') ? 1 : 0
                    ],
                    [
                        'id' => 'units',
                        'label' => get_label('units', 'Units'),
                        'url' => url('units'),
                        'class' => 'menu-item' . (Request::is('units') ? ' active' : ''),
                        'show' => $user->can('manage_units') ? 1 : 0
                    ],
                    [
                        'id' => 'items',
                        'label' => get_label('items', 'Items'),
                        'url' => url('items'),
                        'class' => 'menu-item' . (Request::is('items') ? ' active' : ''),
                        'show' => $user->can('manage_items') ? 1 : 0
                    ],
                ],
            ],
            [
                'id' => 'reports',
                'label' => get_label('reports', 'Reports'),
                'url' => 'javascript:void(0)',
                'icon' => 'bx bx-file',
                'class' => 'menu-item' . (Request::is('reports') || Request::is('reports/*') ? ' active open' : ''),
                'show' => $user->hasRole('admin') || Auth::guard('web')->check() || checkPermission('manage_projects') || checkPermission('manage_tasks') || checkPermission('manage_estimates_invoices') ? 1 : 0,
                'category' => 'utilities',
                'submenus' => [
                    [
                        'id' => 'projects_report',
                        'label' => get_label('projects', 'Projects'),
                        'url' => route('reports.projects'),
                        'class' => 'menu-item' . (Request::is('reports/projects') ? ' active' : ''),
                        'show' => checkPermission('manage_projects') ? 1 : 0,
                    ],
                    [
                        'id' => 'tasks_report',
                        'label' => get_label('tasks', 'Tasks'),
                        'url' => route('reports.tasks'),
                        'class' => 'menu-item' . (Request::is('reports/tasks') ? ' active' : ''),
                        'show' => checkPermission('manage_tasks') ? 1 : 0,
                    ],
                    [
                        'id' => 'estimates_invoices_report',
                        'label' => get_label('estimates_invoices', 'Estimates/Invoices'),
                        'url' => route('reports.invoices-report'),
                        'class' => 'menu-item' . (Request::is('reports/estimates-invoices') ? ' active' : ''),
                        'show' => checkPermission('manage_estimates_invoices') ? 1 : 0,
                    ],
                    [
                        'id' => 'income_vs_expense',
                        'label' => get_label('income_vs_expense', 'Income vs Expense'),
                        'url' => route('reports.income-vs-expense'),
                        'class' => 'menu-item' . (Request::is('reports/income-vs-expense') ? ' active' : ''),
                        'show' => $user->hasRole('admin') ? 1 : 0,
                    ],
                    [
                        'id' => 'leaves',
                        'label' => get_label('leaves', 'Leaves'),
                        'url' => route('reports.leaves'),
                        'class' => 'menu-item' . (Request::is('reports/leaves') ? ' active' : ''),
                        'show' => Auth::guard('web')->check() ? 1 : 0,
                    ]
                ],
            ],
            [
                'id' => 'hrms',
                'label' => get_label('HRMS', 'HRMS'),
                'icon' => 'bx bx-group',
                'class' => 'menu-item' . (Request::is('candidate*') || Request::is('candidate_status*') || Request::is('interviews*') ? ' active open' : ''),
                'show' => ($user->can('manage_candidate') || $user->can('manage_candidate_status') || $user->can('manage_interview')) ? 1 : 0,
                'category' => 'utilities',
                'submenus' => [
                    [
                        'id' => 'candidates',
                        'label' => get_label('candidate', 'Candidates'),
                        'url' => route('candidate.index'),
                        'class' => 'menu-item' . (Request::is('candidate/index') ? ' active' : ''),
                        'show' => $user->can('manage_candidate') ? 1 : 0,
                    ],
                    [
                        'id' => 'candidates_status',
                        'label' => get_label('candidate_status', 'Candidates Status'),
                        'url' => route('candidate.status.index'),
                        'class' => 'menu-item' . (Request::is('candidate_status*') ? ' active' : ''),
                        'show' => $user->can('manage_candidate_status') ? 1 : 0,
                    ],
                    [
                        'id' => 'interviews',
                        'label' => get_label('interviews', 'Interviews'),
                        'url' => route('interviews.index'),
                        'class' => 'menu-item' . (Request::is('interviews*') ? ' active' : ''),
                        'show' => $user->can('manage_interview') ? 1 : 0,
                    ],
                ]
            ],
            [
                'id' => 'notes',
                'label' => get_label('notes', 'Notes'),
                'url' => url('notes'),
                'icon' => 'bx bx-notepad',
                'class' => 'menu-item' . (Request::is('notes') || Request::is('notes/*') ? ' active' : ''),
                'category' => 'utilities',
            ],
            [
                'id' => 'leave_requests',
                'label' => get_label('leave_requests', 'Leave requests'),
                'url' => getDefaultRoute('leave_requests'),
                'icon' => 'bx bx-right-arrow-alt',
                'class' => 'menu-item' . (Request::is('leave-requests') || Request::is('leave-requests/*') ? ' active' : ''),
                'badge' => ($pendingLeaveRequestsCount > 0) ? '<span class="flex-shrink-0 badge badge-center bg-danger w-px-20 h-px-20">' . $pendingLeaveRequestsCount . '</span>' : '',
                'show' => Auth::guard('web')->check() ? 1 : 0,
                'category' => 'utilities',
            ],
            [
                'id' => 'activity_log',
                'label' => get_label('activity_log', 'Activity log'),
                'url' => getDefaultRoute('activity_logs'),
                'icon' => 'bx bx-line-chart',
                'class' => 'menu-item' . (Request::is('activity-log') || Request::is('activity-log/*') ? ' active' : ''),
                'show' => $user->can('manage_activity_log') ? 1 : 0,
                'category' => 'utilities',
            ],
            [
                'id' => 'calendars',
                'label' => get_label('calendars', 'Calendars'),
                'icon' => 'bx bx-calendar',
                'class' => 'menu-item' . (Request::is('calendars') || Request::is('calendars/*') ? ' active open' : ''),
                'show' => 1,
                'category' => 'utilities',
                'submenus' => [
                    [
                        'id' => 'holiday_calendar',
                        'label' => get_settings('google_calendar_settings')['calendar_name'] ??  get_label('holiday_calendar', 'Holiday Calendar'),
                        'url' => route('calendars.holiday_calendar'),
                        'show' => 1,
                        'class' => 'menu-item' . (Request::is('calendars/holiday-calendar') ? ' active' : ''),
                    ],
                ]
            ],
            [
                'id' => 'general_file_manager',
                'label' => get_label('general_file_manager', 'General File Manager'),
                'url' => route('file-manager.index'),
                'icon' => 'bx bx-folder-open',
                'class' => 'menu-item' . (Request::is('file-manager') || Request::is('file-manager/*') ? ' active' : ''),
                'show' => isAdminOrHasAllDataAccess() ? 1 : 0,
                'category' => 'utilities',
            ],
            [
                'id' => 'settings',
                'label' => get_label('settings', 'Settings'),
                'icon' => 'bx bx-box',
                'class' => 'menu-item' . (Request::is('settings') || Request::is('roles/*') || Request::is('settings/*') ? ' active open' : ''),
                'show' => $user->hasRole('admin') ? 1 : 0,
                'category' => 'settings',
                'submenus' => [
                    [
                        'id' => 'general',
                        'label' => get_label('general', 'General'),
                        'url' => url('settings/general'),
                        'class' => 'menu-item' . (Request::is('settings/general') ? ' active' : ''),
                    ],
                    [
                        'id' => 'company',
                        'label' => get_label('company_info', 'Company Information'),
                        'url' => url('settings/company-info'),
                        'class' => 'menu-item' . (Request::is('settings/company-info') ? ' active' : ''),
                    ],
                    [
                        'id' => 'custom_fields',
                        'label' => get_label('custom_fields', 'Custom Fields'),
                        'url' => route('custom_fields.index'),
                        'class' => 'menu-item' . (Request::is('settings/custom-fields') ? ' active' : ''),
                    ],
                    [
                        'id' => 'security',
                        'label' => get_label('security', 'Security'),
                        'url' => url('settings/security'),
                        'class' => 'menu-item' . (Request::is('settings/security') ? ' active' : ''),
                    ],
                    [
                        'id' => 'permissions',
                        'label' => get_label('permissions', 'Permissions'),
                        'url' => url('settings/permission'),
                        'class' => 'menu-item' . (Request::is('settings/permission') || Request::is('roles/*') ? ' active' : ''),
                    ],
                    [
                        'id' => 'languages',
                        'label' => get_label('languages', 'Languages'),
                        'url' => url('settings/languages'),
                        'class' => 'menu-item' . (Request::is('settings/languages') || Request::is('settings/languages/create') ? ' active' : ''),
                    ],
                    [
                        'id' => 'email',
                        'label' => get_label('email', 'Email'),
                        'url' => url('settings/email'),
                        'class' => 'menu-item' . (Request::is('settings/email') ? ' active' : ''),
                    ],
                    [
                        'id' => 'ai_model_settings',
                        'label' => get_label('ai_model_settings', 'AI Model Settings'),
                        'url' => url('settings/ai-model-settings'),
                        'class' => 'menu-item' . (Request::is('settings/ai-model-settings') ? ' active' : ''),
                    ],
                    [
                        'id' => 'sms_gateway',
                        'label' => get_label('messaging_and_integrations', 'Messaging & Integrations'),
                        'url' => url('settings/sms-gateway'),
                        'class' => 'menu-item' . (Request::is('settings/sms-gateway') ? ' active' : ''),
                    ],
                    [
                        'id' => 'google_calendar',
                        'label' => get_label('google_calendar', 'Google Calendar'),
                        'url' => route('google_calendar.index'),
                        'class' => 'menu-item' . (Request::is('settings/google-calendar') ? ' active' : ''),
                    ],
                    [
                        'id' => 'pusher',
                        'label' => get_label('pusher', 'Pusher'),
                        'url' => url('settings/pusher'),
                        'class' => 'menu-item' . (Request::is('settings/pusher') ? ' active' : ''),
                    ],
                    [
                        'id' => 'media_storage',
                        'label' => get_label('media_storage', 'Media storage'),
                        'url' => url('settings/media-storage'),
                        'class' => 'menu-item' . (Request::is('settings/media-storage') ? ' active' : ''),
                    ],
                    [
                        'id' => 'notification_templates',
                        'label' => get_label('notification_templates', 'Notification Templates'),
                        'url' => url('settings/templates'),
                        'class' => 'menu-item' . (Request::is('settings/templates') ? ' active' : ''),
                    ],
                    [
                        'id' => 'privacy_policy',
                        'label' => get_label('terms_privacy_about', 'Terms, Privacy & About'),
                        'url' => url('settings/terms-privacy-about'),
                        'class' => 'menu-item' . (Request::is('settings/terms-privacy-about') ? ' active' : ''),
                    ],
                    [
                        'id' => 'plugins',
                        'label' => get_label('plugins', 'Plugins'),
                        'url' => route('plugins.index'),
                        'class' => 'menu-item' . (Request::is('settings/plugins') ? ' active' : ''),
                    ],
                    [
                        'id' => 'system_updater',
                        'label' => get_label('system_updater', 'System updater'),
                        'url' => url('settings/system-updater'),
                        'class' => 'menu-item' . (Request::is('settings/system-updater') ? ' active' : ''),
                    ],
                    [
                        'id' => 'pwa',
                        'label' => get_label('pwa_settings', 'PWA Settings'),
                        'url' => url('settings/pwa-settings'),
                        'class' => 'menu-item' . (Request::is('settings/pwa-settings') ? ' active' : ''),
                    ]
                ]
            ]
        ];
    }
}
if (!function_exists('getAllPermissions')) {
    /**
     * Get an array of all defined permissions.
     *
     * @return array
     */
    function getAllPermissions()
    {
        $permissionsConfig = config('taskhub.permissions'); // Fetch permissions from config
        $allPermissions = [];
        foreach ($permissionsConfig as $category => $permissions) {
            $allPermissions = array_merge($allPermissions, $permissions);
        }
        return $allPermissions;
    }
}
/**
 * Replace plain @mentions in the content with HTML links to the user's profile.
 *
 * @param string $content
 * @return string
 */
if (!function_exists('replaceUserMentionsWithLinks')) {
    function replaceUserMentionsWithLinks($content)
    {
        // Find all @mentions in the content
        preg_match_all('/@([A-Za-z0-9]+\s[A-Za-z0-9]+)/', $content, $matches);
        // Initialize modified content
        $modifiedContent = $content;
        $mentionedUserIds = [];
        $mentionedClientIds = [];
        $workspaceId = getWorkspaceId();
        // Check if any matches were found
        if (!empty($matches[1])) {
            foreach ($matches[1] as $fullName) {
                // Try to find the user by their full name (first_name + last_name)
                $user = User::where(DB::raw("CONCAT(first_name, ' ', last_name)"), '=', $fullName)
                    ->whereHas('workspaces', function ($query) use ($workspaceId) {
                        $query->where('workspaces.id', $workspaceId);
                    })
                    ->first();
                if ($user) {
                    // Add user ID to the list of mentioned user IDs
                    $mentionedUserIds[] = $user->id;
                    // Check permission for managing users
                    // if (checkPermission('manage_users')) {
                    // Create a profile link for the mentioned user
                    $mentionLink = '<a href="' . route('users.profile', ['id' => $user->id]) . '">@' . $fullName . '</a>';
                    // } else {
                    //     // Non-clickable text
                    //     $mentionLink = '@' . $fullName;
                    // }
                    // Replace the plain @mention with the linked or non-clickable version
                    $modifiedContent = str_replace('@' . $fullName, $mentionLink, $modifiedContent);
                } else {
                    // If user not found, check if it's a client
                    $client = Client::where(DB::raw("CONCAT(clients.first_name, ' ', clients.last_name)"), '=', $fullName)
                        ->whereHas('workspaces', function ($query) use ($workspaceId) {
                            $query->where('workspaces.id', $workspaceId);
                        })
                        ->first();
                    if ($client) {
                        // Add client ID to the list of mentioned client IDs
                        $mentionedClientIds[] = $client->id;
                        // Check permission for managing clients
                        // if (checkPermission('manage_clients')) {
                        // Create a profile link for the mentioned client
                        $mentionLink = '<a href="' . route('clients.profile', ['id' => $client->id]) . '">@' . $fullName . '</a>';
                        // } else {
                        //     // Non-clickable text
                        //     $mentionLink = '@' . $fullName;
                        // }
                        // Replace the plain @mention with the linked or non-clickable version
                        $modifiedContent = str_replace('@' . $fullName, $mentionLink, $modifiedContent);
                    }
                }
            }
        }
        // Return the modified content along with both mentioned user and client IDs
        return [$modifiedContent, $mentionedUserIds, $mentionedClientIds];
    }
}
if (!function_exists('sendMentionNotification')) {
    function sendMentionNotification($comment, $mentionedUserIds, $workspaceId, $currentUserId, $mentionedClientIds = [])
    {
        // Ensure mentioned user IDs are unique
        $mentionedUserIds = array_unique($mentionedUserIds);
        // Ensure mentioned client IDs are unique
        $mentionedClientIds = array_unique($mentionedClientIds);
        // Initialize module variables
        $moduleType = '';
        $url = '';
        // dd($comment->commentable_type);
        switch ($comment->commentable_type) {
            case 'App\Models\Task':
                $moduleType = 'task';
                $url = route('tasks.info', ['id' => $comment->commentable_id]) . '#navs-top-discussions';
                break;
            case 'App\Models\Project':
                $moduleType = 'project';
                $url = route('projects.info', ['id' => $comment->commentable_id]) . '#navs-top-discussions';
                break;
            default:
                $moduleType = '';
                break;
        }
        $module = [];
        if ($moduleType) {
            switch ($moduleType) {
                case 'task':
                    $module = Task::find($comment->commentable_id);
                    break;
                case 'project':
                    $module = Project::find($comment->commentable_id);
                    break;
                default:
                    break;
            }
        }
        // Get the authenticated user who is sending the notification
        $authUser = getAuthenticatedUser();
        // Create the notification
        $notification = Notification::create([
            'workspace_id' => $workspaceId,
            'from_id' => 'u_' . $currentUserId,
            'type' => $moduleType . '_comment_mention',
            'type_id' => $module->id,
            'action' => 'mentioned',
            'title' => 'You were mentioned in a comment',
            'message' => $authUser->first_name . ' ' . $authUser->last_name . ' mentioned you in ' . ucfirst($moduleType) . ' <a href="' . $url . '">' . $module->title . '</a>.',
        ]);
        // Attach mentioned users to the notification
        foreach ($mentionedUserIds as $userId) {
            $notification->users()->attach($userId);
        }
        // Attach mentioned clients to the notification
        foreach ($mentionedClientIds as $clientId) {
            $client = Client::find($clientId);
            if ($client) {
                $notification->clients()->attach($clientId);
            }
        }
    }
}
if (!function_exists('get_file_settings')) {
    function get_file_settings()
    {
        $general_settings = get_settings('general_settings');
        // Remove spaces from allowed file types
        $allowed_file_types = isset($general_settings['allowed_file_types'])
            ? str_replace(' ', '', $general_settings['allowed_file_types'])
            : '.png,.jpg,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt';
        return [
            'allowed_file_types' => $allowed_file_types,
            'max_files_allowed' => isset($general_settings['max_files_allowed'])
                ? $general_settings['max_files_allowed']
                : 10,
        ];
    }
}
if (!function_exists('formatUserHtml')) {
    function formatUserHtml($user)
    {
        if (!$user) {
            return "-";
        }
        // Get the authenticated user
        $authenticatedUser = getAuthenticatedUser();
        // Get the guard name (web or client)
        $guardName = getGuardName();
        // Check if the authenticated user is the same as the user being displayed
        if (
            ($guardName === 'web' && $authenticatedUser->id === $user->id) ||
            ($guardName === 'client' && $authenticatedUser->id === $user->id)
        ) {
            // Don't show the "Make Call" option if it's the logged-in user
            $makeCallIcon = '';
        } else {
            // Check if the phone number or both phone and country code exist
            $makeCallIcon = '';
            if (!empty($user->phone) || (!empty($user->phone) && !empty($user->country_code))) {
                $makeCallLink = 'tel:' . ($user->country_code ? $user->country_code . $user->phone : $user->phone);
                $makeCallIcon = '<a href="' . $makeCallLink . '" class="text-decoration-none" title="' . get_label('make_call', 'Make Call') . '">
                                     <i class="bx bx-phone-call text-primary"></i>
                                   </a>';
            }
        }
        // If the user has 'manage_users' permission, return the full HTML with links
        $profileLink = route('users.profile', ['id' => $user->id]);
        $photoUrl = $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg');
        // Create the Send Mail link
        $sendMailLink = 'mailto:' . $user->email;
        $sendMailIcon = '<a href="' . $sendMailLink . '" class="text-decoration-none" title="' . get_label('send_mail', 'Send Mail') . '">
                            <i class="bx bx-envelope text-primary"></i>
                          </a>';
        return "<div class='d-flex justify-content-start align-items-center user-name'>
                    <div class='avatar-wrapper me-3'>
                        <div class='avatar avatar-sm pull-up'>
                            <a href='{$profileLink}'>
                                <img src='{$photoUrl}' alt='Photo' class='rounded-circle'>
                            </a>
                        </div>
                    </div>
                    <div class='d-flex flex-column'>
                        <span class='fw-semibold'>{$user->first_name} {$user->last_name} {$makeCallIcon}</span>
                        <small class='text-muted'>{$user->email} {$sendMailIcon}</small>
                    </div>
                </div>";
    }
}
if (!function_exists('formatClientHtml')) {
    function formatClientHtml($client)
    {
        if (!$client) {
            return "-";
        }
        // Get the authenticated user
        $authenticatedUser = getAuthenticatedUser();
        // Get the guard name (web or client)
        $guardName = getGuardName();
        // Check if the authenticated user is the same as the client being displayed
        if (
            ($guardName === 'web' && $authenticatedUser->id === $client->id) ||
            ($guardName === 'client' && $authenticatedUser->id === $client->id)
        ) {
            // Don't show the "Make Call" option if it's the logged-in client
            $makeCallIcon = '';
        } else {
            // Check if the phone number or both phone and country code exist
            $makeCallIcon = '';
            if (!empty($client->phone) || (!empty($client->phone) && !empty($client->country_code))) {
                $makeCallLink = 'tel:' . ($client->country_code ? $client->country_code . $client->phone : $client->phone);
                $makeCallIcon = '<a href="' . $makeCallLink . '" class="text-decoration-none" title="' . get_label('make_call', 'Make Call') . '">
                                     <i class="bx bx-phone-call text-primary"></i>
                                   </a>';
            }
        }
        // If the user has 'manage_clients' permission, return the full HTML with links
        $profileLink = route('clients.profile', ['id' => $client->id]);
        $photoUrl = $client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg');
        // Create the Send Mail link
        $sendMailLink = 'mailto:' . $client->email;
        $sendMailIcon = '<a href="' . $sendMailLink . '" class="text-decoration-none" title="' . get_label('send_mail', 'Send Mail') . '">
                            <i class="bx bx-envelope text-primary"></i>
                          </a>';
        return "<div class='d-flex justify-content-start align-items-center user-name'>
                    <div class='avatar-wrapper me-3'>
                        <div class='avatar avatar-sm pull-up'>
                            <a href='{$profileLink}'>
                                <img src='{$photoUrl}' alt='Photo' class='rounded-circle'>
                            </a>
                        </div>
                    </div>
                    <div class='d-flex flex-column'>
                        <span class='fw-semibold'>{$client->first_name} {$client->last_name} {$makeCallIcon}</span>
                        <small class='text-muted'>{$client->email} {$sendMailIcon}</small>
                    </div>
                </div>";
    }
}
if (!function_exists('getFavoriteStatus')) {
    function getFavoriteStatus($id, $model = \App\Models\Project::class)
    {
        // Ensure the model is valid and exists
        if (!class_exists($model) || !$model::find($id)) {
            return false; // Return false if the model class doesn't exist or the specific entity doesn't exist
        }
        // Get the authenticated user (either a User or a Client)
        $authUser = getAuthenticatedUser();
        // Get the favorite based on the provided model (e.g., Project, Task, etc.)
        $isFavorited = $authUser->favorites()
            ->where('favoritable_type', $model)
            ->where('favoritable_id', $id)
            ->exists();
        return (int) $isFavorited;
    }
}
if (!function_exists('getPinnedStatus')) {
    function getPinnedStatus($id, $model = \App\Models\Project::class)
    {
        // Ensure the model is valid and exists
        if (!class_exists($model) || !$model::find($id)) {
            return false; // Return false if the model class doesn't exist or the specific entity doesn't exist
        }
        // Get the authenticated user (either a User or a Client)
        $authUser = getAuthenticatedUser();
        // Get the pinned status based on the provided model (e.g., Project, Task, etc.)
        $isPinned = $authUser->pinned()
            ->where('pinnable_type', $model)
            ->where('pinnable_id', $id)
            ->exists();
        return (int) $isPinned;
    }
}
if (!function_exists('logActivity')) {
    function logActivity($type, $typeId, $title, $operation = 'created', $parentId = null, $parentType = null)
    {
        // Retrieve necessary values once
        $authenticatedUser = getAuthenticatedUser();
        $workspaceId = getWorkspaceId();
        $guardName = getGuardName();
        // Construct the actor details
        $actorName = $authenticatedUser->first_name . ' ' . $authenticatedUser->last_name;
        $actorId = $authenticatedUser->id;
        $actorType = $guardName == 'web' ? 'user' : 'client';
        // Construct the activity message
        $message = trim($actorName) . ' ' . trim($operation) . ' ' . trim($type) . ' ' . trim($title);
        // Prepare the log data
        $logData = [
            'workspace_id' => $workspaceId,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'type_id' => $typeId,
            'type' => $type,
            'activity' => $operation,
            'message' => $message,
        ];
        // Add parent information if available
        if ($parentId) {
            $logData['parent_type_id'] = $parentId;
        }
        if ($parentType) {
            $logData['parent_type'] = $parentType;
        }
        // Create the activity log entry
        ActivityLog::create($logData);
    }
}
// Function for sending reminders for tasks or birthday or work anniversary
if (!function_exists('sendReminderNotification')) {
    /**
     * Sends reminder notifications to the given recipients based on the given data.
     *
     * @param array $data The reminder data, must contain the type of reminder.
     * @param array $recipients The recipients of the notification, must contain the user or client IDs.
     * @return void
     */
    function sendReminderNotification($data, $recipients)
    {
        Log::info('Sending reminder notification to: ' . json_encode($recipients, JSON_PRETTY_PRINT) . 'With data: ' . json_encode($data, JSON_PRETTY_PRINT));
        if (empty($recipients)) {
            return;
        }
        // Define notification types
        $notificationTypes = ['task_reminder', 'project_reminder', 'leave_request_reminder', 'recurring_task', 'todo_reminder'];
        Log::debug('Checking notification type', ['type' => $data['type'], 'valid_types' => $notificationTypes]);
        // Get notification template based on the type
        $template = getNotificationTemplate($data['type'], 'system');
        if (!$template || $template->status !== 0) {
            $notification = createNotification($data);
        }
        // Process each recipient
        foreach (array_unique($recipients) as $recipient) {
            Log::info('Processing recipient', ['recipient_id' => $recipient]);
            $recipientModel = getRecipientModel($recipient);
            if ($recipientModel) {
                Log::debug('Found recipient model', [
                    'recipient_type' => get_class($recipientModel),
                    'recipient_id' => $recipientModel->id
                ]);
                handleRecipientNotification($recipientModel, $notification, $template, $data, $notificationTypes);
            }
        }
    }
    /**
     * Creates a new notification from the given data.
     *
     * @param array $data An associative array containing the notification details,
     *                    including the 'type', 'type_id', and 'action'.
     * @return \App\Models\Notification The newly created notification instance.
     */
    function createNotification($data)
    {
        return Notification::create(
            [
                'workspace_id' => $data['workspace_id'],
                'from_id' => $data['from_id'],
                'type' => $data['type'],
                'type_id' => $data['type_id'],
                'action' => $data['action'],
                'title' => getTitle($data),
                'message' => get_message($data, null, 'system'),
            ]
        );
    }
    /**
     * Given a recipient identifier, returns the corresponding model instance.
     *
     * A recipient identifier is a string that starts with either 'u_' for a user or
     * 'c_' for a client, followed by the numeric identifier of the user or client.
     * For example, 'u_1' refers to a user with identifier 1, and 'c_2' refers to a
     * client with identifier 2.
     *
     * @param string $recipient The recipient identifier.
     * @return \App\Models\User|\App\Models\Client|null The recipient model instance, or null if not found.
     */
    function getRecipientModel($recipient)
    {
        $recipientId = substr($recipient, 2);
        if (substr($recipient, 0, 2) === 'u_') {
            return User::find($recipientId);
        } elseif (substr($recipient, 0, 2) === 'c_') {
            return Client::find($recipientId);
        }
        return null;
    }
    /**
     * Handles a notification for a recipient based on their notification preferences.
     *
     * This function takes a recipient model, a notification, a template, data about the
     * notification, and an array of notification types. It checks the recipient's
     * preferences for the notification types and sends notifications accordingly.
     * If the notification is already attached to the recipient, it will not be attached again.
     *
     * @param mixed $recipientModel The recipient model to send the notification to.
     * @param mixed $notification The notification to be sent.
     * @param mixed $template The template to use for the notification.
     * @param array $data An associative array containing details about the notification.
     * @param array $notificationTypes An array of notification types to check for.
     */
    function handleRecipientNotification($recipientModel, $notification, $template, $data, $notificationTypes)
    {
        Log::info('Handling recipient notification', [
            'recipient_id' => $recipientModel->id,
            'notification_type' => $data['type']
        ]);
        $enabledNotifications = getUserPreferences('notification_preference', 'enabled_notifications', 'u_' . $recipientModel->id);
        // Attach the notification to the recipient
        attachNotificationIfNeeded($recipientModel, $notification, $template, $enabledNotifications, $data);
        Log::info('Starting notification delivery process', [
            'recipient_id' => $recipientModel->id,
            'notification_types' => $notificationTypes,
            'enabled_notifications' => $enabledNotifications
        ]);
        // Send notifications based on preferences
        sendEmailIfEnabled($recipientModel, $enabledNotifications, $data, $notificationTypes);
        sendSMSIfEnabled($recipientModel, $enabledNotifications, $data, $notificationTypes);
        sendWhatsAppIfEnabled($recipientModel, $enabledNotifications, $data, $notificationTypes);
        sendSlackIfEnabled($recipientModel, $enabledNotifications, $data, $notificationTypes);
    }
    /**
     * Attach a notification to the recipient if the recipient has enabled system notifications for the given type
     * of notification and the notification template is not found or is not enabled.
     *
     * @param mixed $recipientModel The recipient model (User or Client) to which the notification should be attached.
     * @param Notification $notification The notification to be attached to the recipient.
     * @param Template $template The notification template to be checked for enabled status.
     * @param array $enabledNotifications An array of enabled notification types for the recipient.
     * @param array $data The data for the notification, including the type of notification.
     */
    function attachNotificationIfNeeded($recipientModel, $notification, $template, $enabledNotifications, $data)
    {
        Log::debug('Checking if notification needs to be attached', [
            'recipient_id' => $recipientModel->id,
            'notification_id' => $notification ? $notification->id : null,
            'template_exists' => (bool) $template,
            'template_status' => $template ? $template->status : null
        ]);
        if (!$template || $template->status !== 0) {
            if (is_array($enabledNotifications) && (empty($enabledNotifications) || in_array('system_' . $data['type'], $enabledNotifications))) {
                $recipientModel->notifications()->attach($notification->id);
            }
        }
    }
    /**
     * Send an email notification if the recipient has enabled email notifications for the given type of notification.
     *
     * @param mixed $recipientModel The recipient model (User or Client) to which the notification should be sent.
     * @param array $enabledNotifications An array of enabled notification types for the recipient.
     * @param array $data The notification data.
     * @param array $notificationTypes An array of notification types for which email notifications should be sent.
     * @return void
     */
    function sendEmailIfEnabled($recipientModel, $enabledNotifications, $data, $notificationTypes)
    {
        Log::debug('Checking email notification preferences', [
            'recipient_id' => $recipientModel->id,
            'notification_type' => $data['type'],
            'is_type_valid' => in_array($data['type'], $notificationTypes),
            'is_enabled' => isNotificationEnabled($enabledNotifications, 'email_' . $data['type'])
        ]);
        if (in_array($data['type'], $notificationTypes) && isNotificationEnabled($enabledNotifications, 'email_' . $data['type'])) {
            try {
                sendEmailNotification($recipientModel, $data);
            } catch (\Exception $e) {
                Log::error('Email Notification Error: ' . $e->getMessage());
            }
        }
    }
    /**
     * Send SMS notification if enabled.
     *
     * This function sends an SMS notification to the given recipient if the
     * notification type is enabled in the recipient's preferences.
     *
     * @param  \App\Models\User|\App\Models\Client  $recipientModel
     * @param  array  $enabledNotifications
     * @param  array  $data
     * @param  array  $notificationTypes
     * @return void
     */
    function sendSMSIfEnabled($recipientModel, $enabledNotifications, $data, $notificationTypes)
    {
        Log::debug('Checking SMS notification preferences', [
            'recipient_id' => $recipientModel->id,
            'notification_type' => $data['type'],
            'is_type_valid' => in_array($data['type'], $notificationTypes),
            'is_enabled' => isNotificationEnabled($enabledNotifications, 'sms_' . $data['type'])
        ]);
        if (in_array($data['type'], $notificationTypes) && isNotificationEnabled($enabledNotifications, 'sms_' . $data['type'])) {
            try {
                sendSMSNotification($data, $recipientModel);
            } catch (\Exception $e) {
                Log::error('SMS Notification Error: ' . $e->getMessage());
            }
        }
    }
    /**
     * Send WhatsApp notification if enabled.
     *
     * This function sends a WhatsApp notification to the given recipient if the
     * notification type is enabled in the recipient's preferences.
     *
     * @param  \App\Models\User|\App\Models\Client  $recipientModel
     * @param  array  $enabledNotifications
     * @param  array  $data
     * @param  array  $notificationTypes
     * @return void
     */
    function sendWhatsAppIfEnabled($recipientModel, $enabledNotifications, $data, $notificationTypes)
    {
        Log::debug('Checking WhatsApp notification preferences', [
            'recipient_id' => $recipientModel->id,
            'notification_type' => $data['type'],
            'is_type_valid' => in_array($data['type'], $notificationTypes),
            'is_enabled' => isNotificationEnabled($enabledNotifications, 'whatsapp_' . $data['type'])
        ]);
        if (in_array($data['type'], $notificationTypes) && isNotificationEnabled($enabledNotifications, 'whatsapp_' . $data['type'])) {
            try {
                sendWhatsAppNotification($recipientModel, $data);
            } catch (\Exception $e) {
                Log::error('WhatsApp Notification Error: ' . $e->getMessage());
            }
        }
    }
    /**
     * Send a Slack notification if the recipient has enabled Slack notifications for the given type.
     *
     * @param User|Client $recipientModel The recipient model to send the notification to.
     * @param array $enabledNotifications An array of enabled notification types.
     * @param array $data An associative array containing the notification details,
     *                    including the 'type', 'type_id', and 'action'.
     * @param array $notificationTypes An array of notification types.
     */
    function sendSlackIfEnabled($recipientModel, $enabledNotifications, $data, $notificationTypes)
    {
        Log::debug('Checking Slack notification preferences', [
            'recipient_id' => $recipientModel->id,
            'notification_type' => $data['type'],
            'is_type_valid' => in_array($data['type'], $notificationTypes),
            'is_enabled' => isNotificationEnabled($enabledNotifications, 'slack_' . $data['type'])
        ]);
        if (in_array($data['type'], $notificationTypes) && isNotificationEnabled($enabledNotifications, 'slack_' . $data['type'])) {
            try {
                sendSlackNotification($recipientModel, $data);
            } catch (\Exception $e) {
                Log::error('Slack Notification Error: ' . $e->getMessage());
            }
        }
    }
    /**
     * Check if a notification type is enabled for a user/client.
     *
     * @param array $enabledNotifications An array of enabled notification types.
     * @param string $type The notification type to check.
     * @return bool True if the notification type is enabled.
     */
    function isNotificationEnabled($enabledNotifications, $type)
    {
        return is_array($enabledNotifications) && (empty($enabledNotifications) || in_array($type, $enabledNotifications));
    }
}
if (!function_exists('getDefaultStatus')) {
    /**
     * Get the default status ID based on the given status name.
     *
     * @param string $statusName
     * @return object|null
     */
    function getDefaultStatus(string $statusName): ?object
    {
        // Fetch the default status using the Statuses model
        $status = Status::where('title', $statusName)
            ->where('is_default', 1) // Assuming there's an 'is_default' column
            ->first();
        // Return the ID if found, or null
        return $status ? $status : null;
    }
}
if (!function_exists('getDefaultRoute')) {
    function getDefaultRoute($type)
    {
        $defaultView = getUserPreferences($type, 'default_view');
        switch ($type) {
            case 'meetings':
                return $defaultView === 'calendar' ? route('meetings.calendar-view') : route('meetings.index');
            case 'leave_requests':
                return $defaultView === 'calendar' ? route('leave-requests.calendar') : route('leave_requests.index');
            case 'activity_logs':
                return $defaultView === 'calendar' ? route('activity_log.calendar_view') : route('activity_log.index');
            case 'leads':
                return $defaultView === 'kanban' ? route('leads.kanban_view') : route('leads.index');
            default:
                return route('dashboard'); // Fallback route to avoid returning null
        }
    }
}
if (!function_exists('generateActivityUrl')) {
    function generateActivityUrl($activity)
    {
        $base_url = url('/');
        // Mapping of singular types to correct plural forms
        $pluralMapping = [
            'project' => 'projects',
            'task' => 'tasks',
            'media' => 'media',
            'comment' => 'comments',
            'milestone' => 'milestones',
            'invoice' => 'estimates-invoices',
            'estimate' => 'estimates-invoices',
            'time-tracker' => 'time-tracker',
            'leave-request' => 'leave-requests',
            'client' => 'clients',
            'user' => 'users',
            'expense' => 'expenses',
            'expense-type' => 'expenses/expense-types',
            'item' => 'items',
            'payment' => 'payments',
            'payment-method' => 'payment-methods',
            'tax' => 'taxes',
            'unit' => 'units',
            'contract' => 'contracts',
            'todo' => 'todos',
            'contract-type' => 'contracts/contract-types',
            'payslip' => 'payslips',
            'allowance' => 'allowances',
            'deduction' => 'deductions',
            'tag' => 'tags/manage',
            'status' => 'status/manage',
            'priority' => 'priority/manage',
            'workspace' => 'workspaces',
            'note' => 'notes',
            'meeting' => 'meetings',
        ];
        // Convert type to lowercase and replace spaces with dashes
        $type = strtolower(trim($activity['type']));
        $type = str_replace(' ', '-', $type); // Fix "Contract type" → "contract-type"
        $parentType = isset($activity['parent_type']) ? strtolower(trim($activity['parent_type'])) : '';
        $parentType = str_replace(' ', '-', $parentType);
        // Ensure plural form using mapping or fallback to Str::plural()
        $pluralType = $pluralMapping[$type] ?? Str::plural($type);
        $pluralParentType = $pluralMapping[$parentType] ?? Str::plural($parentType);
        // Define URL structure for each type
        $urlPatterns = [
            'projects' => "/projects/information/{id}",
            'tasks' => "/tasks/information/{id}",
            'media' => "/{parent_type}/information/{parent_id}",
            'comments' => "/{parent_type}/information/{parent_id}",
            'milestones' => "/projects/information/{parent_id}",
            'estimates-invoices' => "/estimates-invoices/view/{id}",
            'time-tracker' => "/time-tracker",
            'leave-requests' => "/leave-requests",
            'clients' => "/clients/profile/{id}",
            'users' => "/users/profile/{id}",
            'expenses' => "/expenses",
            'expenses/expense-types' => "/expenses/expense-types",
            'items' => "/items",
            'payments' => "/payments",
            'payment-methods' => "/payment-methods",
            'taxes' => "/taxes",
            'units' => "/units",
            'contracts' => "/contracts/sign/{id}",
            'todos' => "/todos",
            'contracts/contract-types' => "/contracts/contract-types",
            'payslips' => "/payslips",
            'allowances' => "/allowances",
            'deductions' => "/deductions",
            'tags/manage' => "/tags/manage",
            'status/manage' => "/status/manage",
            'priority/manage' => "/priority/manage",
            'workspaces' => "/workspaces",
            'notes' => "/notes",
            'meetings' => "/meetings",
        ];
        // Check if the plural type exists in the URL pattern
        if (isset($urlPatterns[$pluralType])) {
            return $base_url . str_replace(
                ['{id}', '{parent_id}', '{parent_type}'],
                [$activity['type_id'], $activity['parent_type_id'] ?? '', $pluralParentType],
                $urlPatterns[$pluralType]
            );
        }
        // Fallback to list page if type not in array
        return "{$base_url}/" . $pluralType;
    }
}
if (!function_exists('formatEstimateInvoice')) {
    function formatEstimateInvoice($invoice)
    {
        $invoice->load('client', 'items'); // Load relationships
        return [
            'id' => $invoice->id,
            'type' => $invoice->type,
            'status' => $invoice->status,
            'client_id' => $invoice->client_id,
            'client' => [
                'id' => $invoice->client->id,
                'first_name' => $invoice->client->first_name,
                'last_name' => $invoice->client->last_name,
                'email' => $invoice->client->email,
                'photo' => $invoice->client->photo ? asset('storage/' . $invoice->client->photo) : asset('storage/photos/no-image.jpg'),
            ],
            'name' => $invoice->name,
            'address' => $invoice->address,
            'city' => $invoice->city,
            'state' => $invoice->state,
            'country' => $invoice->country,
            'zip_code' => $invoice->zip_code,
            'phone' => $invoice->phone,
            'note' => $invoice->note,
            'personal_note' => $invoice->personal_note,
            'from_date' => $invoice->from_date ? format_date($invoice->from_date, to_format: 'Y-m-d') : null,
            'to_date' => $invoice->to_date ? format_date($invoice->to_date, to_format: 'Y-m-d') : null,
            'total' => (string) $invoice->total,
            'tax_amount' => (string)$invoice->tax_amount,
            'final_total' => (string) $invoice->final_total,
            'created_at' => format_date($invoice->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($invoice->updated_at, to_format: 'Y-m-d'),
            'items' => $invoice->items->map(function ($item) {
                $taxTitle = null;
                if ($item->pivot->tax_id) {
                    $tax = \App\Models\Tax::find($item->pivot->tax_id);
                    $taxTitle = $tax?->title;
                }
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => (string) $item->unit,
                    'price' => (string)$item->price,
                    'amount' => $item->amount,
                    'tax' => $taxTitle
                ];
            }),
        ];
    }
}
if (!function_exists('formatLeadSource')) {
    function formatLeadSource($lead_source)
    {
        return [
            'id' => $lead_source->id,
            'name' => $lead_source->name,
            'created_at' => format_date($lead_source->created_at, false, 'Y-m-d H:i:s', 'Y-m-d'),
            'updated_at' => format_date($lead_source->updated_at, false, 'Y-m-d H:i:s', 'Y-m-d'),
        ];
    }
}
if (!function_exists('formatLeadStage')) {
    function formatLeadStage($lead_stage)
    {
        return [
            'id' => $lead_stage->id,
            'name' => $lead_stage->name,
            'slug' => $lead_stage->slug,
            'order' => $lead_stage->order,
            'color' => $lead_stage->color,
            'created_at' => format_date($lead_stage->created_at, false, 'Y-m-d H:i:s', 'Y-m-d'),
            'updated_at' => format_date($lead_stage->updated_at, false, 'Y-m-d H:i:s', 'Y-m-d'),
        ];
    }
}
if (!function_exists('formatLead')) {
    function formatLead($lead)
    {
        $lead->load('source', 'stage', 'assigned_user');
        return [
            'id' => $lead->id,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'country_code' => $lead->country_code,
            'country_iso_code' => $lead->country_iso_code,
            'lead_source_id' => $lead->source_id,
            'lead_source' => $lead->source ? $lead->source->name : '-',
            'lead_stage_id' => $lead->stage_id,
            'lead_stage' => $lead->stage ? $lead->stage->name : '-',
            'lead_stage_color' => $lead->stage->color ? $lead->stage->color : '-',
            'assigned_to' => $lead->assigned_to,
            'assigned_user' => ucfirst($lead->assigned_user->first_name) . ' ' . ucfirst($lead->assigned_user->last_name),
            'job_title' => $lead->job_title,
            'industry' => $lead->industry,
            'company' => $lead->company,
            'website' => $lead->website,
            'linkedin' => $lead->linkedin,
            'instagram' => $lead->instagram,
            'facebook' => $lead->facebook,
            'pinterest' => $lead->pinterest,
            'city' => $lead->city,
            'state' => $lead->state,
            'zip' => $lead->zip,
            'country' => $lead->country,
            'isConverted' => $lead->is_converted == 1 ? true : false,
            'assigned_user' => [
                'id' => $lead->assigned_user->id,
                'name' => $lead->assigned_user->first_name . "" . $lead->assigned_user->last_name,
                'email' => $lead->assigned_user->email,
                'profile_picture' => $lead->assigned_user ? asset('storage/' . $lead->assigned_user->photo) : asset('/photos/1.png'),
            ],
            'follow_ups' => $lead->follow_ups->map(function ($followUp) {
                return [
                    'id' => $followUp->id,
                    // 'follow_up_at' => format_date($followUp->follow_up_at, false, 'Y-m-d H:', 'Y-m-d'),
                    'follow_up_at' => $followUp->follow_up_at,
                    'type' => $followUp->type,
                    'status' => $followUp->status,
                    'note' => $followUp->note, // Strip HTML
                    // 'note' => $followUp->note ? strip_tags($followUp->note) : null, // Strip HTML
                    'assigned_to' => [
                        'id' => $followUp->assignedTo->id,
                        'name' => $followUp->assignedTo->first_name . " " . $followUp->assignedTo->last_name
                    ],
                    'created_at' => format_date($followUp->created_at, to_format: 'Y-m-d'),
                    'updated_at' => format_date($followUp->updated_at, to_format: 'Y-m-d'),
                ];
            })->toArray(),
            // 'assignedTo' => $lead->assigned_user,
            'created_at' => format_date($lead->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($lead->updated_at, to_format: 'Y-m-d'),
        ];
    }
}
if (!function_exists('formatLeadUserHtml')) {
    function formatLeadUserHtml($lead)
    {
        if (!$lead) {
            return "-";
        }
        // Check if the lead has phone and/or country code
        $makeCallIcon = '';
        if (!empty($lead->phone) || (!empty($lead->phone) && !empty($lead->country_code))) {
            $makeCallLink = 'tel:' . ($lead->country_code ? $lead->country_code . $lead->phone : $lead->phone);
            $makeCallIcon = '<a href="' . $makeCallLink . '" class="text-decoration-none" title="' . get_label('make_call', 'Make Call') . '">
                             <i class="bx bx-phone-call text-primary"></i>
                         </a>';
        }
        // Email & Mail Link
        $sendMailLink = 'mailto:' . $lead->email;
        $sendMailIcon = '<a href="' . $sendMailLink . '" class="text-decoration-none" title="' . get_label('send_mail', 'Send Mail') . '">
                        <i class="bx bx-envelope text-primary"></i>
                     </a>';
        return "<div class='d-flex justify-content-start align-items-center user-name'>
                <div class='d-flex flex-column'>
                    <span class='fw-semibold'>" . ucwords($lead->first_name . ' ' . $lead->last_name) . " {$makeCallIcon}</span>
                    <small class='text-muted'>{$lead->email} {$sendMailIcon}</small>
                </div>
            </div>";
    }
    if (!function_exists('formatLeadFollowUp')) {
        function formatLeadFollowUp($followUp)
        {
            return [
                'id' => $followUp->id,
                'lead_id' => $followUp->lead_id,
                'assigned_to' => $followUp->assigned_to,
                'followUp_at' => $followUp->follow_up_data,
                'type' => $followUp->type,
                'status' => $followUp->status,
                'note' => $followUp->note,
                'created_at' => format_date($followUp->created_at, to_format: 'Y-m-d'),
                'updated_at' => format_date($followUp->updated_at, to_format: 'Y-m-d'),
            ];
        }
    }
    if (!function_exists('formatCustomField')) {
        function formatCustomField($field)
        {
            return [
                'id' => $field->id,
                'module' => $field->module,
                'field_label' => $field->field_label,
                'field_type' => $field->field_type,
                'options' => json_decode($field->options, true),
                'required' => $field->required,
                'show_in_table' => $field->visibility
            ];
        }
    }
    if (!function_exists('formatPayslip')) {
        function formatPayslip($payslip)
        {
            // dd(format_date($payslip->payment_date, true, to_format: 'Y-m-d'));
            return [
                'id' => $payslip->id,
                'user' => [
                    'id' => $payslip->user_id,
                    'name' => $payslip->user ? ($payslip->user->full_name ?? ($payslip->user->first_name . ' ' . $payslip->user->last_name)) : '-',
                    'email' => $payslip->user->email,
                    'profile_picture' =>  $payslip->user ? asset('storage/' . $payslip->user->photo) : asset('/photos/1.png'),
                ],
                'month' => $payslip->month,
                'basic_salary' => $payslip->basic_salary,
                'working_days' => $payslip->working_days,
                'lop_days' => $payslip->lop_days,
                'paid_days' => $payslip->paid_days,
                'bonus' => $payslip->bonus,
                'incentives' => $payslip->incentives,
                'leave_deduction' => $payslip->leave_deduction,
                'ot_hours' => $payslip->ot_hours,
                'ot_rate' => $payslip->ot_rate,
                'ot_payment' => $payslip->ot_payment,
                'allowances' => $payslip->allowances->map(function ($allowance) {
                    return [
                        'id' => $allowance->id,
                        'title' => $allowance->title,
                        'amount' => $allowance->amount ?? 0, // assuming `amount` is on pivot
                    ];
                }),
                'total_allowance' => $payslip->total_allowance,
                'deductions' => $payslip->deductions->map(function ($deduction) {
                    return [
                        'id' => $deduction->id,
                        'title' => $deduction->title,
                        'amount' => $deduction->amount ?? 0, // assuming `amount` is on pivot
                    ];
                }),
                'total_deductions' => $payslip->total_deductions,
                'total_earnings' => $payslip->total_earnings,
                'net_pay' => $payslip->net_pay,
                'status' => $payslip->status,
                'status_label' => match ((int)$payslip->status) {
                    0 => 'Pending',
                    1 => 'Paid',
                    default => 'Unknown',
                },
                'payment_method_id' => $payslip->payment_method_id,
                'payment_method' => $payslip->paymentMethod->title ?? '-',
                'payment_date' =>  $payslip->payment_date !== null ? format_date($payslip->payment_date, true, to_format: 'Y-m-d') : '',
                'note' => $payslip->note,
                'created_at_date' => format_date($payslip->created_at, false, to_format: 'Y-m-d'),
                'created_at_time' => format_date($payslip->created_at, false, to_format: 'H:i:s'),
                'updated_at_date' => format_date($payslip->updated_at, false, to_format: 'Y-m-d'),
                'updated_at_time' => format_date($payslip->updated_at, false, to_format: 'H:i:s'),
                'current_date' => format_date(Carbon::now(), false, to_format: 'Y-m-d'),
                'current_time' => format_date(Carbon::now(), false, to_format: 'H:i:s'),
            ];
        }
    }
    if (!function_exists('formatAllowance')) {
        function formatAllowance($allowance)
        {
            return [
                'id' => $allowance->id,
                'title' => $allowance->title,
                'amount' => format_currency($allowance->amount, false),
                'created_at' => format_date($allowance->created_at, to_format: 'Y-m-d'),
                'updated_at' => format_date($allowance->updated_at, to_format: 'Y-m-d'),
            ];
        }
    }
    if (!function_exists('formatDeduction')) {
        function formatDeduction($deduction)
        {
            return [
                'id' => $deduction->id,
                'title' => $deduction->title,
                'type' => ucfirst($deduction->type),
                'percentage' => $deduction->percentage,
                'amount' => format_currency($deduction->amount, false),
                'created_at' => format_date($deduction->created_at, to_format: 'Y-m-d'),
                'updated_at' => format_date($deduction->updated_at, to_format: 'Y-m-d')
            ];
        }
    }
    function formatContract($contract)
    {
        // Determine sign statuses
        $promisorSign = $contract->promisor_sign;
        $promiseeSign = $contract->promisee_sign;
        $promisor_sign_status = !is_null($promisorSign) ? 'signed' : 'not_signed';
        $promisee_sign_status = !is_null($promiseeSign) ? 'signed' : 'not_signed';
        if (!is_null($promisorSign) && !is_null($promiseeSign)) {
            $status = 'signed';
        } elseif (!is_null($promisorSign) || !is_null($promiseeSign)) {
            $status = 'partially_signed';
        } else {
            $status = 'not_signed';
        }
        if (strpos($contract->created_by, 'u_') === 0) {
            $userId = substr($contract->created_by, 2);
            $user = \App\Models\User::find($userId);
            $createdBy = $user ? [
                'type' => 'user',
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'profile_picture' => $user->photo ? asset('storage/' . $user->photo) : asset('/photos/1.png'),
            ] : null;
        } else {
            $clientId = substr($contract->created_by, 2);
            $client = \App\Models\Client::find($clientId);
            $createdBy = $client ? [
                'type' => 'client',
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'profile_picture' => $client->photo ? asset('storage/' . $client->photo) : asset('/photos/1.png'),
            ] : null;
        }
        return [
            'id' => $contract->id,
            'title' => $contract->title,
            'value' => format_currency($contract->value, 0),
            'start_date' => format_date($contract->start_date, to_format: 'Y-m-d'),
            'end_date' => format_date($contract->end_date, to_format: 'Y-m-d'),
            'client' => [
                'id' => $contract->client->id,
                'name' => $contract->client->first_name . " " . $contract->client->last_name,
                'email' => $contract->client->email,
                'profile_picture' => $contract->client->photo ? asset('storage/' . $contract->client->photo) : asset('storage/photos/no-image.jpg')
            ],
            'created_by' => $createdBy,
            'project' => [
                'id' => $contract->project_id,
                'title' => $contract->project_title
            ],
            'contract_type' => [
                'id' => $contract->contract_type_id,
                'name' => $contract->contract_type
            ],
            'description' => $contract->description,
            'workspace_id' => $contract->workspace_id,
            'created_at' => format_date($contract->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($contract->updated_at, to_format: 'Y-m-d'),
            'status' => $status,
            'signatures' => [
                'promisor' => [
                    'status' => $promisor_sign_status,
                    'url' => $promisorSign && Storage::disk('public')->exists('signatures/' . $promisorSign) ? asset('storage/signatures/' . $promisorSign) : null, // CHANGED: Use asset() for consistency with profile_picture and added exists() check
                ],
                'promisee' => [
                    'status' => $promisee_sign_status,
                    'url' => $promiseeSign && Storage::disk('public')->exists('signatures/' . $promiseeSign) ? asset('storage/signatures/' . $promiseeSign) : null, // CHANGED: Use asset() for consistency with profile_picture and added exists() check
                ],
            ],
            'signed_pdf_url' => $contract->signed_pdf ? asset('storage/contracts/' . $contract->signed_pdf) : null,
        ];
    }
    if (!function_exists('formatContractType')) {
        function formatContractType($contract_type)
        {
            return [
                'id' => $contract_type->id,
                'type' => $contract_type->type,
                'created_at' => format_date($contract_type->created_at, to_format: 'Y-m-d'),
                'updated_at' => format_date($contract_type->updated_at, to_format: 'Y-m-d'),
            ];
        }
    }
}
if (!function_exists('generate_description_openrouter')) {
    /**
     * Generates a project/task description using OpenRouter's API.
     *
     * @param string $prompt The input for generating the description.
     * @param string|null $apiKey Optional API key to override settings
     * @return array{error: bool, data?: string, message?: string} Response array with status and data/message
     */
    function generate_description_openrouter(string $prompt, $apiKey = null): array
    {
        // Get settings from database
        $settings = get_ai_settings('openrouter');
        // Use provided API key or get from settings
        $apiKey = $apiKey ?: $settings['openrouter_api_key'] ?? null;
        if (empty($apiKey)) {
            Log::error('Missing OpenRouter API key');
            return [
                'error' => true,
                'message' => 'System configuration error: Missing API key.',
            ];
        }
        // Get dynamic settings
        $endpoint = $settings['openrouter_endpoint'] ?? 'https://openrouter.ai/api/v1/chat/completions';
        $model = $settings['openrouter_model'] ?? 'nousresearch/deephermes-3-mistral-24b-preview:free';
        $systemPrompt = $settings['openrouter_system_prompt'] ?? 'You are a helpful assistant that writes concise, professional project or task descriptions.';
        $temperature = $settings['openrouter_temperature'] ?? 0.7;
        $maxTokens = $settings['openrouter_max_tokens'] ?? 1024;
        $topP = $settings['openrouter_top_p'] ?? 0.95;
        $frequencyPenalty = $settings['openrouter_frequency_penalty'] ?? 0;
        $presencePenalty = $settings['openrouter_presence_penalty'] ?? 0;
        $timeout = $settings['request_timeout'] ?? 15;
        $maxRetries = $settings['max_retries'] ?? 2;
        // Apply prompt formatting if configured
        $formattedPrompt = $prompt;
        if (!empty($settings['default_prompt_prefix'])) {
            $formattedPrompt = $settings['default_prompt_prefix'] . ' ' . $formattedPrompt;
        }
        if (!empty($settings['default_prompt_suffix'])) {
            $formattedPrompt .= ' ' . $settings['default_prompt_suffix'];
        }
        // Check prompt length
        $maxPromptLength = $settings['max_prompt_length'] ?? 1000;
        if (empty($formattedPrompt) || strlen($formattedPrompt) > $maxPromptLength) {
            return [
                'error' => true,
                'message' => "Invalid prompt length. Must be between 1 and {$maxPromptLength} characters.",
            ];
        }
        $client = new \GuzzleHttp\Client(['timeout' => $timeout]);
        $attempt = 0;
        while ($attempt < $maxRetries) {
            try {
                $response = $client->post($endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'HTTP-Referer' => config('app.url'),
                        'X-Title' => 'Taskify', // Optional: Name your app
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $formattedPrompt],
                        ],
                        'temperature' => (float)$temperature,
                        'max_tokens' => (int)$maxTokens,
                        'top_p' => (float)$topP,
                        'frequency_penalty' => (float)$frequencyPenalty,
                        'presence_penalty' => (float)$presencePenalty,
                    ],
                ]);
                $body = json_decode($response->getBody(), true);
                if (isset($body['choices'][0]['message']['content'])) {
                    return [
                        'error' => false,
                        'data' => $body['choices'][0]['message']['content'],
                    ];
                }
                return [
                    'error' => true,
                    'message' => $body['error']['message'],
                ];
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt >= $maxRetries) {
                    Log::error('OpenRouter API Error', [
                        'message' => $e->getMessage(),
                    ]);
                    // Try fallback if enabled
                    if (
                        !empty($settings['enable_fallback']) && $settings['enable_fallback'] &&
                        !empty($settings['fallback_provider']) && $settings['fallback_provider'] === 'gemini'
                    ) {
                        $fallbackResult = generate_description_gemini($prompt);
                        if (!$fallbackResult['error']) {
                            // Add note that fallback was used
                            $fallbackResult['data'] = '[Generated using fallback provider] ' . $fallbackResult['data'];
                        }
                        return $fallbackResult;
                    }
                    return [
                        'error' => true,
                        'message' => 'An error occurred while generating the description using OpenRouter API.',
                    ];
                }
                // Wait before retrying
                $retryDelay = $settings['retry_delay'] ?? 1;
                sleep($retryDelay);
            }
        }
        // Should not reach here, but just in case
        return [
            'error' => true,
            'message' => 'Failed to generate description after multiple attempts.',
        ];
    }
}
if (!function_exists('generate_description_gemini')) {
    /**
     * Generates a project/task description using Gemini API.
     *
     * @param string $prompt The input for generating the description.
     * @param string|null $apiKey Optional API key to override settings
     * @return array{error: bool, data?: string, message?: string} Response array with status and data/message
     */
    function generate_description_gemini(string $prompt, $apiKey = null)
    {
        try {
            // Get settings from database
            $settings = get_ai_settings('gemini');
            // Use provided API key or get from settings
            $apiKey = $apiKey ?: $settings['gemini_api_key'] ?? null;
            if (empty($apiKey)) {
                Log::error('Missing Gemini API key');
                return [
                    'error' => true,
                    'message' => 'System configuration error: Missing API key.',
                ];
            }
            // Get dynamic settings
            $model = $settings['gemini_model'] ?? 'gemini-2.0-flash';
            $endpointTemplate = $settings['gemini_endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
            $endpoint = sprintf($endpointTemplate, $model);
            if (strpos($endpoint, '?key=') === false) {
                $endpoint .= '?key=' . $apiKey;
            }
            $temperature = $settings['gemini_temperature'] ?? 0.7;
            $topK = $settings['gemini_top_k'] ?? 40;
            $topP = $settings['gemini_top_p'] ?? 0.95;
            $maxOutputTokens = $settings['gemini_max_output_tokens'] ?? 1024;
            $timeout = $settings['request_timeout'] ?? 15;
            $maxRetries = $settings['max_retries'] ?? 2;
            // Rate limiting settings
            $MAX_REQUESTS_PER_MINUTE = $settings['rate_limit_per_minute'] ?? 15;
            $MAX_REQUESTS_PER_DAY = $settings['rate_limit_per_day'] ?? 1500;
            $userId = auth()->user()?->id ?? request()->ip();
            $minuteKey = "gemini_rate_minute_{$userId}";
            $dayKey = "gemini_rate_day_{$userId}";
            $currentTime = now();
            $minuteRequests = Cache::get($minuteKey, 0);
            if ($minuteRequests >= $MAX_REQUESTS_PER_MINUTE) {
                $retryAfter = 60 - $currentTime->second;
                return [
                    'error' => true,
                    'message' => "Rate limit exceeded. Please try again in {$retryAfter} seconds.",
                ];
            }
            $dayRequests = Cache::get($dayKey, 0);
            if ($dayRequests >= $MAX_REQUESTS_PER_DAY) {
                $tomorrow = $currentTime->addDay()->startOfDay();
                $hoursRemaining = $currentTime->diffInHours($tomorrow);
                return [
                    'error' => true,
                    'message' => "Daily limit exceeded. Please try again in {$hoursRemaining} hours.",
                ];
            }
            // Apply prompt formatting if configured
            $formattedPrompt = $prompt;
            if (!empty($settings['default_prompt_prefix'])) {
                $formattedPrompt = $settings['default_prompt_prefix'] . ' ' . $formattedPrompt;
            }
            if (!empty($settings['default_prompt_suffix'])) {
                $formattedPrompt .= ' ' . $settings['default_prompt_suffix'];
            }
            // Set default prompt prefix for Gemini if not specified
            if (strpos($formattedPrompt, "Generate a concise") === false) {
                $formattedPrompt = "Generate a concise, professional description for the following: {$formattedPrompt}";
            }
            $maxPromptLength = $settings['max_prompt_length'] ?? 1000;
            if (empty($formattedPrompt) || strlen($formattedPrompt) > $maxPromptLength) {
                return [
                    'error' => true,
                    'message' => "Invalid prompt length. Must be between 1 and {$maxPromptLength} characters.",
                ];
            }
            $client = new \GuzzleHttp\Client(['timeout' => $timeout]);
            $attempt = 0;
            while ($attempt < $maxRetries) {
                try {
                    $response = $client->post($endpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'contents' => [
                                [
                                    'parts' => [
                                        [
                                            'text' => $formattedPrompt
                                        ]
                                    ]
                                ]
                            ],
                            'generationConfig' => [
                                'temperature' => (float)$temperature,
                                'topK' => (int)$topK,
                                'topP' => (float)$topP,
                                'maxOutputTokens' => (int)$maxOutputTokens,
                            ]
                        ]
                    ]);
                    $result = json_decode($response->getBody(), true);
                    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                        return [
                            'error' => true,
                            'message' => 'Invalid API response. Please Contact Support'
                        ];
                    }
                    Cache::put($minuteKey, $minuteRequests + 1, now()->addMinutes(1));
                    Cache::put($dayKey, $dayRequests + 1, now()->addDays(1));
                    return [
                        'error' => false,
                        'data' => $result['candidates'][0]['content']['parts'][0]['text'],
                    ];
                } catch (\Exception $e) {
                    $attempt++;
                    if ($attempt >= $maxRetries) {
                        Log::error('Gemini API Error', [
                            'message' => $e->getMessage(),
                        ]);
                        // Try fallback if enabled
                        if (
                            !empty($settings['enable_fallback']) && $settings['enable_fallback'] &&
                            !empty($settings['fallback_provider']) && $settings['fallback_provider'] === 'openrouter'
                        ) {
                            $fallbackResult = generate_description_openrouter($prompt);
                            if (!$fallbackResult['error']) {
                                // Add note that fallback was used
                                $fallbackResult['data'] = '[Generated using fallback provider] ' . $fallbackResult['data'];
                            }
                            return $fallbackResult;
                        }
                        return [
                            'error' => true,
                            'message' => 'Failed to generate description. Please try again later.',
                        ];
                    }
                    // Wait before retrying
                    $retryDelay = $settings['retry_delay'] ?? 1;
                    sleep($retryDelay);
                }
            }
        } catch (\Exception $e) {
            Log::critical('Unexpected Error in generate_description_gemini', [
                'error' => $e->getMessage(),
            ]);
            return [
                'error' => true,
                'message' => 'An unexpected error occurred. Please try again later.',
            ];
        }
    }
}
if (!function_exists('generate_description')) {
    /**
     * Determines which AI model to use and generates a description.
     *
     * @param string $prompt The input for generating the description.
     * @return mixed The generated description or error response.
     */
    function generate_description(string $prompt)
    {
        $ai_model_settings = get_settings('ai_model_settings');
        $selectedModel = $ai_model_settings['is_active']; // Assume this is stored in app settings
        if ($selectedModel === 'openrouter') {
            Log::info('Creating Description Using Openrouter AI Model/API');
            return generate_description_openrouter($prompt, $ai_model_settings['openrouter_api_key']);
        } elseif ($selectedModel === 'gemini') {
            Log::info('Creating Description Using Google Gemini AI Model/API');
            return generate_description_gemini($prompt, $ai_model_settings['gemini_api_key']);
        } else {
            return [
                'error' => true,
                'message' => 'Invalid AI model selected. Please update your settings.'
            ];
        }
    }
}
if (!function_exists('get_ai_settings')) {
    /**
     * Retrieve AI model settings from the database
     *
     * @param string|null $provider Specific provider to get settings for
     * @return array AI settings from the database with defaults applied
     */
    function get_ai_settings(?string $provider = null): array
    {
        $settings = Setting::where('variable', 'ai_model_settings')->first();
        if (!$settings) {
            // Return default settings if none found
            return [
                'is_active' => 'openrouter',
                'openrouter_endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
                'openrouter_system_prompt' => 'You are a helpful assistant that writes concise, professional project or task descriptions.',
                'openrouter_temperature' => 0.7,
                'openrouter_max_tokens' => 1024,
                'openrouter_top_p' => 0.95,
                'gemini_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
                'gemini_temperature' => 0.7,
                'gemini_top_k' => 40,
                'gemini_top_p' => 0.95,
                'gemini_max_output_tokens' => 1024,
                'rate_limit_per_minute' => 15,
                'rate_limit_per_day' => 1500,
                'max_retries' => 2,
                'retry_delay' => 1,
                'request_timeout' => 15,
                'max_prompt_length' => 1000,
            ];
        }
        $settings = json_decode($settings->value, true);
        // If a specific provider is requested, only return those settings
        if ($provider) {
            $providerSettings = [];
            // Get all settings that belong to the requested provider
            foreach ($settings as $key => $value) {
                if (strpos($key, $provider) === 0 || !str_contains($key, 'openrouter_') && !str_contains($key, 'gemini_')) {
                    $providerSettings[$key] = $value;
                }
            }
            // Add global settings that aren't provider-specific
            $globalKeys = [
                'is_active',
                'rate_limit_per_minute',
                'rate_limit_per_day',
                'max_retries',
                'retry_delay',
                'request_timeout',
                'max_prompt_length',
                'enable_fallback',
                'fallback_provider'
            ];
            foreach ($globalKeys as $key) {
                if (isset($settings[$key])) {
                    $providerSettings[$key] = $settings[$key];
                }
            }
            return $providerSettings;
        }
        return $settings;
    }
}
if (!function_exists('getStatusCounts')) {
    function getStatusCounts($statuses, $auth_user, $type = 'projects')
    {
        $statusCounts = [];
        $totalCount = 0;
        foreach ($statuses as $status) {
            $count = isAdminOrHasAllDataAccess()
                ? count($status->$type)
                : $auth_user->{"status_{$type}"}($status->id)->count();
            $statusCounts[$status->id] = $count;
            $totalCount += $count;
        }
        arsort($statusCounts); // Sort by count descending
        return [$statusCounts, $totalCount];
    }
}
if (!function_exists('formatComment')) {
    function formatComment($comment)
    {
        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'commenter' => $comment->commenter ? [
                'id' => $comment->commenter->id,
                'first_name' => $comment->commenter->first_name,
                'last_name' => $comment->commenter->last_name,
                'email' => $comment->commenter->email,
                'photo' => $comment->commenter->photo ? asset('storage/' . $comment->commenter->photo) : asset('storage/photos/no-image.jpg'),
            ] : null,
            'created_at' => format_date($comment->created_at, to_format: 'Y-m-d H:i:s'),
            'sent_time' => $comment->created_at->diffForHumans(), // <-- Added for "5 seconds ago"
            'attachments' => $comment->attachments->map(function ($a) {
                return [
                    'id' => $a->id,
                    'file_name' => $a->file_name,
                    'file_path' => $a->file_path,
                    'file_type' => $a->file_type,
                    'url' => asset('storage/' . $a->file_path),
                ];
            }),
            'children' => $comment->children->map(function ($child) {
                return formatComment($child);
            })->values(),
        ];
    }
}
if (!function_exists('formatLeadForm')) {
    function formatLeadForm($leadForm)
    {
        return [
            'id' => $leadForm->id,
            'title' => $leadForm->title,
            'description' => $leadForm->description,
            'source' => $leadForm->leadSource ? [
                'id' => $leadForm->leadSource->id,
                'name' => $leadForm->leadSource->name,
            ] : null,
            'stage' => $leadForm->leadStage ? [
                'id' => $leadForm->leadStage->id,
                'name' => $leadForm->leadStage->name,
                'color' => $leadForm->leadStage->color,
            ] : null,
            'assigned_to' => $leadForm->assignedUser ? [
                'id' => $leadForm->assignedUser->id,
                'first_name' => $leadForm->assignedUser->first_name,
                'last_name' => $leadForm->assignedUser->last_name,
                'email' => $leadForm->assignedUser->email,
                'photo' => $leadForm->assignedUser->photo ? asset('storage/' . $leadForm->assignedUser->photo) : asset('storage/photos/no-image.jpg'),
            ] : null,
            'fields' => $leadForm->leadFormFields->map(function ($field) {

                return [
                    'id' => $field->id,
                    'label' => $field->label,
                    'name' => $field->name,
                    'type' => $field->type,
                    'is_required' => (bool) $field->is_required,
                    'is_mapped' => (bool) $field->is_mapped,
                    'options' => is_array($decoded = json_decode($field->options ?? '[]', true)) && !(count($decoded) === 1 && is_null($decoded[0])) ? $decoded : [],

                    'placeholder' => $field->placeholder,
                    'order' => $field->order,
                    'validation_rules' => $field->validation_rules,
                ];
            })->values(),
            'public_url' => $leadForm->public_url,
            'embed_code' => $leadForm->embed_code,
            'leads_count' => $leadForm->leads_count ?? 0,
            'created_at' => format_date($leadForm->created_at, to_format: 'Y-m-d'),
            'updated_at' => format_date($leadForm->updated_at, to_format: 'Y-m-d'),

        ];
    }
}

if (!function_exists('formatLeadFormResponse')) {
    /**
     * Format a Lead model for clean API response when returned from lead form responses.
     *
     * @param \App\Models\Lead $lead
     * @return array
     */
    function formatLeadFormResponse($lead)
    {
        return [
            'id' => $lead->id,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'full_name' => trim($lead->first_name . ' ' . $lead->last_name),
            'email' => $lead->email,
            'phone' => $lead->phone,
            'company' => $lead->company ?? null,
            'source' => $lead->leadSource ? [
                'id' => $lead->leadSource->id,
                'name' => $lead->leadSource->name,
            ] : null,
            'stage' => $lead->leadStage ? [
                'id' => $lead->leadStage->id,
                'name' => $lead->leadStage->name,
                'color' => $lead->leadStage->color,
            ] : null,
            'assigned_to' => $lead->assignedUser ? [
                'id' => $lead->assignedUser->id,
                'first_name' => $lead->assignedUser->first_name,
                'last_name' => $lead->assignedUser->last_name,
                'email' => $lead->assignedUser->email,
                'photo' => $lead->assignedUser->photo ? asset('storage/' . $lead->assignedUser->photo) : asset('storage/photos/no-image.jpg'),
            ] : null,
            'submitted_at' => format_date($lead->created_at, to_format: "Y-m-d"),
            'sent_time' => $lead->created_at->diffForHumans(),
            'custom_fields' => $lead->custom_fields ?? [], // if you store custom fields for each lead form response
        ];
    }
}
