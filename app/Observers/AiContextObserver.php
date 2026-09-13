<?php

namespace App\Observers;

use App\Services\Ai\ReportContextService;
use Illuminate\Support\Facades\Log;


class AiContextObserver
{
    public function saved($model): void
    {
        $this->scheduleRebuild();
    }

    public function deleted($model): void
    {
        $this->scheduleRebuild();
    }

    private function scheduleRebuild(): void
    {
        if (app()->environment('testing') && !config('ai.rebuild_in_testing', false)) {
            return;
        }

        try {
            $scheduled = \Illuminate\Support\Facades\Cache::add('ai-context-rebuild-scheduled', true, 60);
            if (! $scheduled) {
                return;
            }

            \App\Jobs\RebuildAiContext::dispatch()->afterResponse();
        } catch (\Throwable $e) {
            Log::warning('[AI] schedule context rebuild failed: '.$e->getMessage());
        }
    }
}
