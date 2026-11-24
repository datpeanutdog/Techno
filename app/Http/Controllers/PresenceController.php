<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PresenceController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'active' => ['required', 'boolean'],
        ]);
        $now = now();

        DB::table('users')
            ->where('id', $request->user()->id)
            ->update([
                'presence_heartbeat_at' => $now,
                'presence_active' => $data['active'],
            ]);

        return response()->json([
            'data' => [
                'presence_status' => $request->user()->refresh()->messengerPresenceStatus(),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
