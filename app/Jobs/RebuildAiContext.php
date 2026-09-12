<?php

namespace App\Jobs;

use App\Services\Ai\ReportContextService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RebuildAiContext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct()
    {
    }

    public function handle(ReportContextService $contexts): void
    {
        // منع التزاحم: إذا كان هناك بناء جارٍ، تخطَّ — سيُعاد الجدولة عند الحفظ التالي.
        $lock = Cache::lock('ai-context-rebuild', 110);
        if (! $lock->get()) {
            return;
        }

        try {
            $contexts->rebuild();
        } catch (\Throwable $e) {
            Log::warning('[AI] queued context rebuild failed: '.$e->getMessage());
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $e) {
            }
        }
    }
}
