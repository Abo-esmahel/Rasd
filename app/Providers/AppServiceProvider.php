<?php

namespace App\Providers;

use App\Models\GeneralSubmission;
use App\Models\Note;
use App\Policies\GeneralSubmissionPolicy;
use App\Policies\NotePolicy;
use Carbon\Carbon;
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
