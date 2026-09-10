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
        //
    }

    public function boot(): void
    {
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(GeneralSubmission::class, GeneralSubmissionPolicy::class);

        // Real-time broadcast decoupling: after any database notification is stored,
        // push it to the private WebSocket channel (Reverb) without coupling services to broadcasting
        Event::listen(NotificationSent::class, BroadcastDatabaseNotification::class);

        // نظام 12 ساعة للملاحظات: "02:05 م" / "11:20 ص"
        Carbon::macro('toTime12', function (): string {
            /** @var Carbon $this */
            return $this->format('h:i').' '.($this->format('A') === 'AM' ? 'ص' : 'م');
        });
        Carbon::macro('toDatetime12', function (): string {
            /** @var Carbon $this */
            return $this->format('Y-m-d').' '.$this->toTime12();
        });
    }
}
