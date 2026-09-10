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
            'endpoint' => ['required','string','max:2000'],
            'keys.p256dh' => ['nullable','string'],
            'keys.auth' => ['nullable','string'],
        ]);
        $user = $request->user();
        $sub = PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $user->id,
                'p256dh' => $validated['keys']['p256dh'] ?? null,
                'auth' => $validated['keys']['auth'] ?? null,
                'user_agent' => $request->header('User-Agent'),
            ]
        );
        Log::info('[PUSH] subscribed', ['user_id'=>$user->id, 'endpoint'=>substr($validated['endpoint'],0,60)]);
        return response()->json(['success'=>true, 'id'=>$sub->id]);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate(['endpoint'=>['required','string']]);
        PushSubscription::where('endpoint',$validated['endpoint'])->where('user_id',$request->user()->id)->delete();
        return response()->json(['success'=>true]);
    }
}
