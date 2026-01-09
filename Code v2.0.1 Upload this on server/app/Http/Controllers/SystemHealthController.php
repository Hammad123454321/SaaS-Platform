<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SystemHealthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function healthCheck()
    {
        $systemData = $this->getSystemData();
        return view('settings.system_health', compact('systemData'));
    }

    public function validateHealth(Request $request)
    {

        $request->validate([
            'health_code' => [
                'required',
                'string',
                'regex:/^[a-zA-Z0-9]{8}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{12}$/'
            ],
        ], [
            'health_code.required' => 'Please enter your purchase code.',
            'health_code.regex' => 'Invalid format. Please check your code.'
        ]);
        $healthCode = $request->input('health_code');


        $result = $this->performHealthCheck($healthCode);

        if ($result['success']) {
            $this->saveHealthData($result['data']);
            return formatApiResponse(false, $result['message']);
        }

        return formatApiResponse(true, $result['message'], [], 200);
    }

    private function getSystemData()
    {
        $key = $this->decodeKey('a1b2c3d4');
        cache()->forget('settings_cache');

        return get_settings($key);
    }

    private function performHealthCheck($code)
    {
        try {
            $url = $this->buildHealthUrl($code);

            $response = Http::timeout(30)->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'SystemHealth/1.0'
            ])->get($url);

            if ($response->failed()) {
                return ['success' => false, 'message' => 'Health validation service is currently unavailable. Please try again later.'];
            }

            $data = $response->json();

            cache()->forget('settings_cache');

            if ($data['error'] == false) {

                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Health validation successful',
                    'data' => $this->mapHealthData($data)
                ];
            }
            return ['success' => false, 'message' => $data['message'] ?? 'Health validation failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Health validation service error. Please contact support.'];
        }
    }

    private function buildHealthUrl($code)
    {

        $base = 'https://validator.infinitietech.com';
        $path = '/home/validator';

        $params = [
            'purchase_code' => $code,
            'domain_url' => url('/'),
            'item_id' => config('app.system_id', config('constants.medicine_code'))
        ];

        return $base . $path . '?' . http_build_query($params);
    }

    private function mapHealthData($responseData)
    {
        return [
            $this->decodeKey('e5f6g7h8') => $responseData['purchase_code'],
            $this->decodeKey('i9j0k1l2') => time(),
            $this->decodeKey('m3n4o5p6') => $responseData['username'] ?? '',
            $this->decodeKey('q7r8s9t0') => $responseData['item_id'] ?? '',
        ];
    }

    private function saveHealthData($data)
    {
        $key = $this->decodeKey('a1b2c3d4');

        Setting::updateOrCreate(
            ['variable' => $key],
            ['value' => json_encode($data)]
        );
    }

    private function decodeKey($hash)
    {
        $keys = config('taskhub.system_map');

        return $keys[$hash] ?? null;
    }
    public function checkPurchaseCode(Request $request, $key)
    {

        // Optional: key verification
        $secretKey = $this->decodeKey('u1v2w3x4');
        if ($key !== $secretKey) {
            abort(403, 'Unauthorized');
        }

        // Get from cache or DB
        $settings = $this->getSystemData();

        // Extract data
        $purchaseCode = $settings['code_bravo'] ?? null;
        $isValidated  = !empty($purchaseCode) && !empty($settings['code_adam']); // validate based on non-empty values

        return response()->json([
            'purchase_code' => $purchaseCode,
            'is_validated'  => $isValidated,
            'last_checked'  => !empty($settings['time_check'])
                ? date('Y-m-d H:i:s', $settings['time_check'])
                : null,
        ]);
    }
}
