<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationStreamController extends Controller
{
    public function stream(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');

        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        
        
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        } catch (\Throwable $e) {}
        try {
            $request->session()->save();
        } catch (\Throwable $e) {}

        
        
        
        
        $isLocalCli = (config('app.env') === 'local' && php_sapi_name() === 'cli-server') || $request->query('nosse') === '1' || $request->header('X-Disable-SSE') === '1';
        if ($isLocalCli) {
            
            
            
            return response()->json([
                'message' => 'SSE disabled for local single-threaded server — use polling /notifications/feed',
                'fallback' => 'polling',
                'poll_interval_ms' => 15000,
            ], 204, [
                'X-SSE-Disabled' => 'local-cli-single-thread',
                'Cache-Control' => 'no-store',
            ]);
        }

        
        
        $maxLifetime = 45; 
        $pollIntervalUs = 2000000; 
        $heartbeatInterval = 20; 

        $lastId = $request->query('last_id') ?: $request->header('Last-Event-ID');
        $after = $request->query('after'); 

        
        $cursorTime = null;
        if ($after) {
            try {
                $cursorTime = \Carbon\Carbon::parse($after);
            } catch (\Throwable $e) {
                $cursorTime = null;
            }
        }
        
        if ($lastId && !$cursorTime) {
            try {
                $ref = $user->notifications()->where('id', $lastId)->first();
                if ($ref) {
                    $cursorTime = $ref->created_at;
                }
            } catch (\Throwable $e) {}
        }
        
        if (!$cursorTime) {
            $cursorTime = now()->subSeconds(2);
        }

        $response = new StreamedResponse(function () use ($user, $cursorTime, $maxLifetime, $pollIntervalUs, $heartbeatInterval) {
            
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            
            $start = time();
            $lastHeartbeat = time();
            $cursor = $cursorTime;

            
            echo ": connected\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

            
            try {
                $unread = $user->unreadNotifications()->count();
                $this->sendEvent('init', ['unread_count' => $unread, 'server_time' => now()->toIso8601String()]);
            } catch (\Throwable $e) {}

            while (true) {
                
                if (connection_aborted()) {
                    break;
                }
                if (time() - $start > $maxLifetime) {
                    $this->sendEvent('end', ['reason' => 'max_lifetime_exceeded']);
                    break;
                }

                
                if (time() - $lastHeartbeat >= $heartbeatInterval) {
                    $this->sendEvent('ping', ['t' => now()->toIso8601String()]);
                    $lastHeartbeat = time();
                }

                
                try {
                    
                    $freshUser = \App\Models\User::find($user->id);
                    if (!$freshUser) {
                        $this->sendEvent('error', ['message' => 'user not found']);
                        break;
                    }

                    $new = $freshUser->notifications()
                        ->where('created_at', '>', $cursor)
                        ->orderBy('created_at', 'asc')
                        ->limit(20)
                        ->get();

                    if ($new->isNotEmpty()) {
                        $unread = $freshUser->unreadNotifications()->count();
                        foreach ($new as $n) {
                            $payload = $this->normalize($n);
                            
                            if ($n->created_at->gt($cursor)) {
                                $cursor = $n->created_at;
                            }
                            $this->sendEvent('notification', [
                                'notification' => $payload,
                                'unread_count' => $unread,
                            ], $payload['id']);
                        }
                        
                        
                    } else {
                        
                        
                        static $pollCount = 0;
                        $pollCount++;
                        if ($pollCount % 4 === 0) {
                            
                            
                            
                            static $lastUnread = null;
                            $currentUnread = $freshUser->unreadNotifications()->count();
                            if ($lastUnread === null) {
                                $lastUnread = $currentUnread;
                            } elseif ($lastUnread !== $currentUnread) {
                                $this->sendEvent('sync', ['unread_count' => $currentUnread]);
                                $lastUnread = $currentUnread;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $this->sendEvent('error', ['message' => $e->getMessage()]);
                }

                
                if (ob_get_level() > 0) ob_flush();
                flush();
                usleep($pollIntervalUs);

                
                if (connection_aborted()) break;
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no'); 
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    
    public function feed(Request $request)
    {
        $user = Auth::user();
        $after = $request->query('after');
        $lastId = $request->query('last_id');
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        $unreadOnly = $request->boolean('unread_only');

        $cursor = null;
        if ($after) {
            try { $cursor = \Carbon\Carbon::parse($after); } catch (\Throwable $e) {}
        }
        if ($lastId && !$cursor) {
            $ref = $user->notifications()->where('id', $lastId)->first();
            if ($ref) $cursor = $ref->created_at;
        }

        $query = $user->notifications()->latest();
        if ($cursor) {
            $query->where('created_at', '>', $cursor);
        }
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->limit($limit)->get()->map(fn($n) => $this->normalize($n))->values();
        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function normalize($n): array
    {
        $data = $n->data;
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }
        $rawType = $data['type'] ?? $n->type ?? 'generic';
        $priority = $data['priority'] ?? $this->inferPriority($rawType);
        $category = $data['category'] ?? $this->inferCategory($rawType);

        return [
            'id' => $n->id,
            'type' => $n->type,
            'data' => array_merge($data, [
                'priority' => $priority,
                'category' => $category,
            ]),
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
            'created_at_human' => $n->created_at->diffForHumans(),
            'created_at_full' => $n->created_at->format('Y-m-d H:i'),
        ];
    }

    private function inferPriority(string $type): string
    {
        $t = strtolower($type);
        if (str_contains($t, 'reject')) return 'high';
        if (str_contains($t, 'accept')) return 'normal';
        return 'normal';
    }

    private function inferCategory(string $type): string
    {
        $t = strtolower($type);
        if (str_contains($t, 'note_')) return 'note';
        if (str_contains($t, 'dispatch')) return 'dispatch';
        return 'generic';
    }

    private function sendEvent(string $event, array $data, ?string $id = null): void
    {
        if ($id) {
            echo "id: {$id}\n";
        }
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();
    }
}
