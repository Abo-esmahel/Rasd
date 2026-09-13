<?php

namespace App\Jobs;

use App\Services\Localization\TranslationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class WarmTranslationProjection implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(
        public string $type,
        public int|string $id,
        public array $fields,
        public string $locale,
    ) {
    }

    public function handle(TranslationService $service): void
    {
        $refs = [];
        foreach ($this->fields as $field => $text) {
            $text = trim((string) $text);
            if ($field !== '' && $text !== '') {
                $refs[] = ['type' => $this->type, 'id' => $this->id, 'field' => (string) $field, 'text' => $text];
            }
        }
        if ($refs === []) {
            return;
        }

        try {
            $map = $service->localizeMany($refs, $this->locale);
        } catch (\Throwable $e) {
            Log::warning('[L10N-WARM] failed', [
                'type' => $this->type, 'id' => $this->id, 'locale' => $this->locale,
                'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 200),
            ]);
            throw $e;
        }

        $warmed = 0;
        foreach ($refs as $ref) {
            $k = TranslationService::itemKey($ref['type'], $ref['id'], $ref['field']);
            if (isset($map[$k]) && $map[$k] !== $ref['text']) {
                $warmed++;
            }
        }

        Log::info('[L10N-WARM] done', [
            'type' => $this->type, 'id' => $this->id, 'locale' => $this->locale,
            'fields' => count($refs), 'warmed' => $warmed,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[L10N-WARM] exhausted', [
            'type' => $this->type, 'id' => $this->id, 'locale' => $this->locale,
            'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 300),
        ]);
    }
}
