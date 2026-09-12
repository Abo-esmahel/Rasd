<?php

namespace App\Console\Commands;

use App\Services\Ai\ReportContextService;
use Illuminate\Console\Command;

class RebuildAiContext extends Command
{
    protected $signature = 'reports:rebuild-ai-context';
    protected $description = 'Rebuild canonical AI context file from database (deterministic, no Gemini)';

    public function handle(ReportContextService $service): int
    {
        $path = $service->rebuild();
        $this->info("Context rebuilt: {$path}");

        return self::SUCCESS;
    }
}
