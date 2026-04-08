<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    public function show()
    {
        $setting = Setting::get()->first();

        return response()->json([
            'success' => true,
            'message' => 'Settings retrieved successfully',
            'data' => $setting
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'class_time' => 'required|integer|min:1',
        ]);

        $setting = Setting::first();

        if ($setting) {
            $setting->update(['class_time' => $request->class_time]);
        } else {
            $setting = Setting::create(['class_time' => $request->class_time]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully',
            'data' => $setting
        ]);
    }

    public function getClassTime(){

        $time = Setting::select('class_time')->first();

        return response()->json([
            'success' => true,
            'message' => 'Time retrieved successfully',
            'class_time' => $time->class_time
        ]);
    }
}
