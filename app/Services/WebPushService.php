<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function sendToUser(int $userId, array $payload): void
    {
        try {
            $subs = PushSubscription::where('user_id', $userId)->get();
            if ($subs->isEmpty()) {
                return;
            }

            $vapidPublic = config('app.vapid_public_key') ?? env('VAPID_PUBLIC_KEY');
            $vapidPrivate = config('app.vapid_private_key') ?? env('VAPID_PRIVATE_KEY');
            $vapidSubject = config('app.vapid_subject') ?? env('VAPID_SUBJECT', 'mailto:admin@rasd.local');

            if (!$vapidPublic || !$vapidPrivate) {
                Log::warning('[PUSH] missing VAPID keys');
                return;
            }

            $auth = [
                'VAPID' => [
                    'subject' => $vapidSubject,
                    'publicKey' => $vapidPublic,
                    'privateKey' => $vapidPrivate,
                ],
            ];

            $webPush = new WebPush($auth);
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

            foreach ($subs as $sub) {
                if (empty($sub->endpoint)) {
                    continue;
                }

                try {
                    $subscription = Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'keys' => [
                            'p256dh' => $sub->p256dh,
                            'auth' => $sub->auth,
                        ],
                    ]);

                    $webPush->queueNotification($subscription, $jsonPayload);
                } catch (\Throwable $e) {
                    Log::warning('[PUSH] queue failed', [
                        'user_id' => $userId,
                        'endpoint' => substr((string) $sub->endpoint, 0, 60),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            foreach ($webPush->flush() as $report) {
                try {
                    $endpoint = $report->getRequest()->getUri()->__toString();
                    $sub = $subs->firstWhere('endpoint', $endpoint);

                    if ($report->isSuccess()) {
                        Log::info('[PUSH] sent', [
                            'user_id' => $userId,
                            'endpoint' => substr($endpoint, 0, 60),
                        ]);
                    } else {
                        $reason = $report->getReason();
                        $response = $report->getResponse();
                        $code = $response ? $response->getStatusCode() : 0;

                        Log::warning('[PUSH] failed', [
                            'user_id' => $userId,
                            'code' => $code,
                            'reason' => substr((string) $reason, 0, 200),
                            'endpoint' => substr($endpoint, 0, 60),
                        ]);

                        if ($sub && in_array($code, [404, 410], true)) {
                            $sub->delete();
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('[PUSH] report handling failed: '.$e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[PUSH] sendToUser failed: '.$e->getMessage());
        }
    }
}
