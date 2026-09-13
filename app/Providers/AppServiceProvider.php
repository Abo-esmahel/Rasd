<?php

namespace App\Providers;

use App\Listeners\BroadcastDatabaseNotification;
use App\Models\Attachment;
use App\Models\GeneralSubmission;
use App\Models\Note;
use App\Models\Report;
use App\Observers\AiContextObserver;
use App\Policies\GeneralSubmissionPolicy;
use App\Policies\NotePolicy;
use App\Policies\ReportPolicy;
use App\Services\Ai\AiTextGeneratorInterface;
use App\Services\Ai\GeminiAiTextGenerator;
use App\Services\Localization\GeminiTranslationProvider;
use App\Services\Localization\TranslationProviderInterface;
use Carbon\Carbon;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $helpers = $this->app->basePath('app/Support/localization.php');
        if (is_file($helpers)) {
            require_once $helpers;
        }
        $this->app->bind(AiTextGeneratorInterface::class, GeminiAiTextGenerator::class);
        // TranslationService → TranslationProviderInterface → GeminiTranslationProvider.
        $this->app->bind(TranslationProviderInterface::class, GeminiTranslationProvider::class);
    }

    public function boot(): void
    {
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(GeneralSubmission::class, GeneralSubmissionPolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);

        Note::observe(AiContextObserver::class);
        Attachment::observe(AiContextObserver::class);
        Report::observe(AiContextObserver::class);

        Note::observe(\App\Observers\TranslationWarmObserver::class);
        \App\Models\GeneralSubmission::observe(\App\Observers\TranslationWarmObserver::class);
        Report::observe(\App\Observers\TranslationWarmObserver::class);

        \App\Models\User::saved(function () {
            try {
                \Illuminate\Support\Facades\Cache::forget('observers_list');
            } catch (\Throwable $e) {
            }
        });
        \App\Models\User::deleted(function () {
            try {
                \Illuminate\Support\Facades\Cache::forget('observers_list');
            } catch (\Throwable $e) {
            }
        });

        
        
        Event::listen(NotificationSent::class, BroadcastDatabaseNotification::class);

        
        Carbon::macro('toTime12', function (): string {
            if (app()->getLocale() === 'en') {
                return $this->format('h:i A');
            }

            return $this->format('h:i').' '.($this->format('A') === 'AM' ? 'ص' : 'م');
        });
        Carbon::macro('toDatetime12', function (): string {
            
            return $this->format('Y-m-d').' '.$this->toTime12();
        });
    }
}
