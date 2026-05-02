<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
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
            'support_number' => 'required|string|max:20',
            'support_email' => 'required|email|max:255',
            'class_notify_time' => 'required|integer|min:1'
        ]);

        $setting = Setting::first();

        if ($setting) {
            $setting->update([
                'class_time' => $request->class_time,
                'support_number' => $request->support_number,
                'support_email' => $request->support_email,
                'class_notify_time' => $request->class_notify_time
            ]);
        } else {
            $setting = Setting::create([
                'class_time' => $request->class_time,
                'support_number' => $request->support_number,
                'support_email' => $request->support_email,
                'class_notify_time' => $request->class_notify_time
            ]);
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

    public function logs(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = NotificationLog::with([
            'user:id,name,email',
            'batch:id,name'
        ]);

        // Search
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                ->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        // Filters
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        if ($request->filled('message_type')) {
            $query->where('message_type', $request->message_type);
        }

        $logs = $query
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Notification logs fetched successfully',
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
                'last_page'    => $logs->lastPage(),
            ]
        ]);
    }

}
