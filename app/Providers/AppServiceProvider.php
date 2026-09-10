<?php

namespace App\Providers;

use App\Listeners\BroadcastDatabaseNotification;
use App\Models\GeneralSubmission;
use App\Models\Note;
use App\Policies\GeneralSubmissionPolicy;
use App\Policies\NotePolicy;
use Carbon\Carbon;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        
    }

    public function boot(): void
    {
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(GeneralSubmission::class, GeneralSubmissionPolicy::class);

        
        
        Event::listen(NotificationSent::class, BroadcastDatabaseNotification::class);

        
        Carbon::macro('toTime12', function (): string {
            
            return $this->format('h:i').' '.($this->format('A') === 'AM' ? 'ص' : 'م');
        });
        Carbon::macro('toDatetime12', function (): string {
            
            return $this->format('Y-m-d').' '.$this->toTime12();
        });
    }
}
