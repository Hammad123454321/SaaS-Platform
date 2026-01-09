<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;


class PwaSettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::where('variable', 'pwa_settings')->first();
        $pwaSettings = $settings ? json_decode($settings->value, true) : [];
        return view('pwa.index', compact('pwaSettings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'required|string|max:255',
            'theme_color' => 'required|string|regex:/^#[0-9A-F]{6}$/i',
            'background_color' => 'required|string|regex:/^#[0-9A-F]{6}$/i',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg|dimensions:min_width=512,min_height=512',
            'description' => 'required|string|max:500',
        ]);


        try{

            if ($request->hasFile('logo')) {
                $logo = $request->file('logo');
                $logoPath = $logo->storeAs('public/images/icons', 'logo-512x512.png');
                // Crop/resize to 512x512
                Image::read($logo)->cover(512, 512)->save(storage_path('app/' . $logoPath));
                $validated['logo'] = Storage::url($logoPath);
            } else {
                $existing = Setting::where('variable', 'pwa_settings')->first();
                $validated['logo'] = $existing ? json_decode($existing->value, true)['logo'] ?? '/storage/images/icons/logo-512x512.png' : '/storage/images/icons/logo-512x512.png';
            }

            Setting::updateOrCreate(
                ['variable' => 'pwa_settings'],
                ['value' => json_encode($validated)]
            );

            // Clear cache
            \Illuminate\Support\Facades\Cache::forget('pwa_settings');

            return redirect()->back()->with('message', 'PWA settings updated successfully.');

        }catch(ValidationException $e){

            $errors = $e->validator->errors()->all();
            $message = 'Validation failed:' . implode(',', $errors);



            return response()->json(['error' => true, 'message' => $message], 422);
        }catch(\Exception $e){

            return response()->json([
                'error' => true,
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ]);
        }


    }
}
