<?php

namespace App\Http\Controllers\DryCleanerEnd;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Get all DryCleaner settings.
     */
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    /**
     * Update or create a setting by key.
     */
    public function update(Request $request, string $key)
    {
        $request->validate(['value' => 'required']);

        $setting = Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $request->value]
        );

        return response()->json(['key' => $setting->key, 'value' => $setting->value]);
    }
}
