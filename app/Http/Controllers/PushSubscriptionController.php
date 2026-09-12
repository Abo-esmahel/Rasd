<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url', 'starts_with:https://'],
            'keys.p256dh' => ['nullable', 'string', 'max:500'],
            'keys.auth' => ['nullable', 'string', 'max:500'],
        ]);
        $user = $request->user();
        // منع الاستيلاء: endpoint مملوك لمستخدم آخر لا يُعاد ربطه.
        $existing = PushSubscription::where('endpoint', $validated['endpoint'])->first();
        if ($existing && (int) $existing->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => __('api.push_already_linked')], 403);
        }
        $sub = PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $user->id,
                'p256dh' => $validated['keys']['p256dh'] ?? null,
                'auth' => $validated['keys']['auth'] ?? null,
                'user_agent' => substr((string) $request->header('User-Agent'), 0, 500),
            ]
        );
        Log::info('[PUSH] subscribed', ['user_id'=>$user->id, 'endpoint'=>substr($validated['endpoint'],0,60)]);
        return response()->json(['success'=>true, 'id'=>$sub->id]);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        PushSubscription::where('endpoint',$validated['endpoint'])->where('user_id',$request->user()->id)->delete();
        return response()->json(['success'=>true]);
    }
}
