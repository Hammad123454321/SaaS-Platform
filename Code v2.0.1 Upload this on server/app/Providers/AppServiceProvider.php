<?php

namespace App\Providers;

use Carbon\Carbon;
use App\Models\Tag;
use App\Models\User;
use App\Models\Client;
use App\Models\Status;
use App\Models\Setting;
use App\Models\Language;
use App\Models\Priority;
use App\Models\CustomField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use App\Services\CustomPathGenerator;
use Illuminate\Support\Facades\Config;
use App\Services\CustomManifestService;
use Illuminate\Support\ServiceProvider;
use LaravelPWA\Services\ManifestService;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Cache for loaded settings to avoid repeated database queries
     */
    private array $settingsCache = [];

    /**
     * Default configuration values
     */
    private const DEFAULTS = [
        'general' => [
            'full_logo' => 'storage/logos/default_full_logo.png',
            'half_logo' => 'storage/logos/default_half_logo.png',
            'favicon' => 'storage/logos/default_favicon.png',
            'footer_logo' => 'storage/logos/footer_logo.png',
            'company_title' => 'Taskify',
            'currency_symbol' => '₹',
            'currency_full_form' => 'Indian Rupee',
            'currency_code' => 'INR',
            'date_format' => 'DD-MM-YYYY|d-m-Y',
            'toast_time_out' => '5',
            'toast_position' => 'toast-top-right',
            'allowed_file_types' => '.png,.jpg,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt',
            'max_files_allowed' => '10',
            'allowed_max_upload_size' => '512',
            'timezone' => '',
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => '',
        ],
        'pusher' => [
            'pusher_app_id' => '',
            'pusher_app_key' => '',
            'pusher_app_secret' => '',
            'pusher_app_cluster' => '',
        ],
        'email' => [
            'email' => '',
            'password' => '',
            'smtp_host' => '',
            'smtp_port' => '',
            'email_content_type' => '',
            'smtp_encryption' => '',
        ],
        'media_storage' => [
            'media_storage_type' => '',
            's3_key' => '',
            's3_secret' => '',
            's3_region' => '',
            's3_bucket' => '',
        ],
        'company_info' => [
            'companyEmail' => '',
            'companyPhone' => '',
            'companyAddress' => '',
            'companyCity' => '',
            'companyState' => '',
            'companyCountry' => '',
            'companyZip' => '',
            'companyWebsite' => '',
            'companyVatNumber' => '',
        ],
        'google_calendar' => [
            'api_key' => '',
            'calendar_id' => '',
            'calendar_name' => '',
        ],
        'ai_models' => [
            "openrouter_endpoint" => "https://openrouter.ai/api/v1/chat/completions",
            "openrouter_system_prompt" => "You are a helpful assistant that writes concise, professional project or task descriptions.",
            "openrouter_temperature" => "0.7",
            "openrouter_max_tokens" => "1024",
            "openrouter_top_p" => "0.95",
            "gemini_endpoint" => "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent",
            "gemini_temperature" => "0.7",
            "gemini_top_k" => "40",
            "gemini_top_p" => "0.95",
            "gemini_max_output_tokens" => "1024",
            "rate_limit_per_minute" => "15",
            "rate_limit_per_day" => "1500",
            "max_retries" => "2",
            "retry_delay" => "1",
            "request_timeout" => "15",
            "max_prompt_length" => "1000",
            "enable_fallback" => "1",
            "fallback_provider" => "openrouter",
            "openrouter_api_key" => "",
            "gemini_api_key" => "",
            "is_active" => "gemini",
            "openrouter_model" => "nousresearch/deephermes-3-mistral-24b-preview:free",
            "openrouter_frequency_penalty" => "0",
            "openrouter_presence_penalty" => "0",
            "gemini_model" => "gemini-2.0-flash",
        ]
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PathGenerator::class, CustomPathGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Compose modal views
        $this->composeModalViews();

        // Load database configurations and settings
        $this->loadDatabaseSettings();

        // Configure PWA settings
        $this->configurePwaSettings();

        // Register PHP date format singleton
        $this->registerDateFormatSingleton();



        $this->loadPlugins();
    }

    /**
     * Attach data to specific views.
     */
    private function composeModalViews(): void
    {
        View::composer('modals', function ($view) {
            $view->with([
                'toSelectWorkspaceUsers' => User::select('id', 'first_name', 'last_name')->get(),
                'toSelectWorkspaceClients' => Client::select('id', 'first_name', 'last_name')->get()
            ]);
        });
    }

    /**
     * Load configurations and share global data from the database.
     */
    private function loadDatabaseSettings(): void
    {
        if (!$this->isDatabaseConnected()) {
            return;
        }

        try {
            // Load all settings in bulk to minimize database queries
            $allSettings = $this->loadAllSettings();

            // Parse date formats
            $dateFormats = $this->parseDateFormats($allSettings['general']['date_format']);

            // Load custom fields
            $customFields = $this->loadCustomFields();

            // Prepare shared data
            $sharedData = [
                'general_settings' => $allSettings['general'],
                'email_settings' => $allSettings['email'],
                'pusher_settings' => $allSettings['pusher'],
                'media_storage_settings' => $allSettings['media_storage'],
                'google_calendar_settings' => $allSettings['google_calendar'],
                'company_info' => $allSettings['company_info'],
                'ai_model_settings' => $allSettings['ai_models'],
                'languages' => Language::all(),
                'statuses' => Status::all(),
                'tags' => Tag::all(),
                'priorities' => Priority::all(),
                'js_date_format' => $dateFormats['js'],
                'php_date_format' => $dateFormats['php'],
                'taskCustomFields' => $customFields['task'],
                'projectCustomFields' => $customFields['project'],
            ];

            // Load general settings from DB
            $this->configureRecaptcha($allSettings['general']);

            // Share data globally with all views
            view()->share($sharedData);

            // Configure application defaults
            $this->configureAppDefaults($allSettings);
        } catch (\Exception $e) {
            // Log error if needed, but don't break the application
            // logger()->error('Failed to load database settings: ' . $e->getMessage());
        }
    }

    /**
     * Check if database connection is available.
     */
    private function isDatabaseConnected(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Load all settings efficiently with caching.
     */
    private function loadAllSettings(): array
    {
        if (!empty($this->settingsCache)) {
            return $this->settingsCache;
        }

        $settings = [
            'general' => $this->getSettingsWithDefaults('general_settings', self::DEFAULTS['general']),
            'pusher' => $this->getSettingsWithDefaults('pusher_settings', self::DEFAULTS['pusher']),
            'email' => $this->getSettingsWithDefaults('email_settings', self::DEFAULTS['email']),
            'media_storage' => $this->getSettingsWithDefaults('media_storage_settings', self::DEFAULTS['media_storage']),
            'google_calendar' => $this->getSettingsWithDefaults('google_calendar_settings', self::DEFAULTS['google_calendar']),
            'company_info' => $this->getSettingsWithDefaults('company_information', self::DEFAULTS['company_info']),
            'ai_models' => $this->getSettingsWithDefaults('ai_model_settings', self::DEFAULTS['ai_models']),
        ];

        // Process logo paths for general settings
        $settings['general'] = $this->processLogosPaths($settings['general']);
        $this->settingsCache = $settings;
        return $settings;
    }

    /**
     * Get settings with defaults applied.
     */


    private function getSettingsWithDefaults(string $key, array $defaults): array
    {
        $settings = get_settings($key);

        if ($settings instanceof \Illuminate\Support\Collection) {
            $settings = $settings->toArray();
        }

        // In case it's stored as a JSON string (common with one-row settings table)
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $settings = $decoded;
            }
        }
        $merged = array_merge($defaults, is_array($settings) ? $settings : []);

        // Ensure that if $settings value is empty, fallback to default
        foreach ($defaults as $key => $defaultValue) {
            if (!isset($merged[$key]) || $merged[$key] === '' || $merged[$key] === null) {
                $merged[$key] = $defaultValue;
            }
        }
        return $merged;
    }


    /**
     * Process logo paths by adding storage prefix if needed.
     */
    private function processLogosPaths(array $generalSettings): array
    {
        $logoKeys = ['full_logo', 'half_logo', 'favicon', 'footer_logo'];
        foreach ($logoKeys as $key) {
            if (
                isset($generalSettings[$key]) &&
                !empty($generalSettings[$key]) &&
                !str_starts_with($generalSettings[$key], 'storage/')
            ) {
                $generalSettings[$key] = 'storage/' . $generalSettings[$key];
            }
        }

        return $generalSettings;
    }

    /**
     * Parse date formats into JS and PHP formats.
     */
    private function parseDateFormats(string $dateFormat): array
    {
        $formats = explode('|', $dateFormat);
        return [
            'js' => $formats[0] ?? 'DD-MM-YYYY',
            'php' => $formats[1] ?? 'd-m-Y',
        ];
    }

    /**
     * Load custom fields for tasks and projects.
     */
    private function loadCustomFields(): array
    {
        return [
            'task' => CustomField::where('module', 'task')->get(),
            'project' => CustomField::where('module', 'project')->get(),
        ];
    }

    /**
     * Configure application defaults based on settings.
     */
    private function configureAppDefaults(array $settings): void
    {
        // Application timezone
        config(['app.timezone' => $settings['general']['timezone']]);

        // Media library max file size
        config(['media-library.max_file_size' => 1024 * 1024 * $settings['general']['allowed_max_upload_size']]);

        // Pusher configuration
        $this->configurePusher($settings['pusher']);

        // Mail configuration
        $this->configureMail($settings['email'], $settings['general']['company_title']);

        // Filesystem configuration
        $this->configureFilesystem($settings['media_storage']);
    }

    /**
     * Configure Pusher settings.
     */
    private function configurePusher(array $pusherSettings): void
    {
        config([
            'chatify.pusher' => [
                'key' => $pusherSettings['pusher_app_key'],
                'secret' => $pusherSettings['pusher_app_secret'],
                'app_id' => $pusherSettings['pusher_app_id'],
                'options' => ['cluster' => $pusherSettings['pusher_app_cluster']],
            ]
        ]);
    }

    /**
     * Configure mail settings.
     */
    private function configureMail(array $emailSettings, string $companyTitle): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'host' => $emailSettings['smtp_host'],
                'port' => $emailSettings['smtp_port'],
                'transport' => 'smtp',
                'encryption' => $emailSettings['smtp_encryption'],
                'username' => $emailSettings['email'],
                'password' => $emailSettings['password'],
            ],
            'mail.from' => [
                'name' => $companyTitle,
                'address' => $emailSettings['email'],
            ]
        ]);
    }

    /**
     * Configure filesystem settings.
     */
    private function configureFilesystem(array $mediaStorageSettings): void
    {
        config([
            'filesystems.disks.s3' => [
                'key' => $mediaStorageSettings['s3_key'],
                'secret' => $mediaStorageSettings['s3_secret'],
                'region' => $mediaStorageSettings['s3_region'],
                'bucket' => $mediaStorageSettings['s3_bucket'],
            ]
        ]);
    }

    /**
     * Configure PWA settings.
     */
    private function configurePwaSettings(): void
    {
        try {
            $pwaSettings = $this->getPwaSettings();

            if (empty($pwaSettings)) {
                return;
            }

            $this->setPwaConfig($pwaSettings);
        } catch (\Exception $e) {
            // Handle silently to prevent breaking the application
        }
    }

    /**
     * Get PWA settings from database.
     */
    private function getPwaSettings(): array
    {
        $pwaSettings = Setting::where('variable', 'pwa_settings')->value('value');

        if (!$pwaSettings) {
            return [];
        }

        $decoded = json_decode($pwaSettings, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Set PWA configuration values.
     */
    private function setPwaConfig(array $pwaSettings): void
    {
        $configMap = [
            'laravelpwa.manifest.name' => $pwaSettings['name'] ?? 'Taskify',
            'laravelpwa.manifest.short_name' => $pwaSettings['short_name'] ?? 'Taskify',
            'laravelpwa.manifest.description' => $pwaSettings['description'] ?? '',
            'laravelpwa.manifest.theme_color' => $pwaSettings['theme_color'] ?? '#000000',
            'laravelpwa.manifest.background_color' => $pwaSettings['background_color'] ?? '#ffffff',
        ];

        foreach ($configMap as $key => $value) {
            Config::set($key, $value);
        }

        // Set custom icons if logo is provided
        if (!empty($pwaSettings['logo'])) {
            $this->setPwaIcons($pwaSettings['logo']);
        }
    }

    /**
     * Set PWA icons configuration.
     */
    private function setPwaIcons(string $logoPath): void
    {
        $icons = [
            '512x512' => [
                'path' => $logoPath,
                'sizes' => '512x512',
                'purpose' => 'any'
            ],
        ];

        Config::set('laravelpwa.manifest.icons', $icons);
    }

    /**
     * Register PHP date format singleton.
     */
    private function registerDateFormatSingleton(): void
    {
        $generalSettings = $this->settingsCache['general'] ?? self::DEFAULTS['general'];
        $dateFormats = $this->parseDateFormats($generalSettings['date_format']);

        $this->app->singleton('php_date_format', function () use ($dateFormats) {
            return $dateFormats['php'];
        });
    }

    /**
     * Load PWA manifest settings and merge defaults with database values.
     */
    private function loadPwaManifestSettings(): array
    {
        // Load manifest defaults from config
        $manifestDefaults = config('laravelpwa.manifest', []);

        // Get dynamic pwa_settings from database
        $pwaSettings = [];
        try {
            $dbSettings = get_settings('pwa_settings');
            if (is_string($dbSettings)) {
                $dbSettings = json_decode($dbSettings, true);
            }
            if (is_array($dbSettings)) {
                $pwaSettings = $dbSettings;
            }
        } catch (\Exception $e) {
            // Handle exception silently
            $pwaSettings = [];
        }

        // Merge database settings into manifest defaults
        $manifest = array_merge($manifestDefaults, $pwaSettings);

        // Handle logo/icon override
        if (!empty($pwaSettings['logo']) && isset($manifest['icons'][0]) && is_array($manifest['icons'][0])) {
            $manifest['icons'][0]['path'] = $pwaSettings['logo'];
        }

        // Handle splash screens override
        if (!empty($pwaSettings['splash']) && is_array($pwaSettings['splash'])) {
            $manifest['splash'] = array_merge($manifestDefaults['splash'] ?? [], $pwaSettings['splash']);
        }

        return $manifest;
    }

    // reCAPATCHA
    private function configureRecaptcha(array $generalSettings): void
    {
        if (!empty($generalSettings['recaptcha_site_key']) && !empty($generalSettings['recaptcha_secret_key'])) {
            Config::set('captcha.sitekey', $generalSettings['recaptcha_site_key']);
            Config::set('captcha.secret', $generalSettings['recaptcha_secret_key']);
        }
    }
    private function loadPlugins()
    {
        $pluginsPath = base_path('plugins');

        if (File::exists($pluginsPath)) {
            $pluginDirs = File::directories($pluginsPath);

            foreach ($pluginDirs as $pluginDir) {
                $pluginJson = $pluginDir . '/plugin.json';

                if (File::exists($pluginJson)) {
                    $pluginConfig = json_decode(File::get($pluginJson), true);

                    if (
                        !empty($pluginConfig['enabled']) &&
                        !empty($pluginConfig['provider'])
                    ) {
                        $providerClass = $pluginConfig['provider'];

                        // Manually require the provider if not autoloaded
                        $providerFile = $pluginDir . '/Providers/' . class_basename(str_replace('\\', '/', $providerClass)) . '.php';
                        if (File::exists($providerFile) && !class_exists($providerClass)) {
                            require_once $providerFile;
                        }

                        if (class_exists($providerClass)) {
                            app()->register($providerClass);
                        } else {
                            Log::warning("⚠️ Provider class {$providerClass} not found for plugin: " . basename($pluginDir));
                        }
                    }
                }
            }
        }
    }
}
