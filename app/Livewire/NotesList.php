<?php

namespace App\Livewire;

use App\Models\Note;
use App\Models\User;
use App\Services\NoteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class NotesList extends Component
{
    use WithPagination, AuthorizesRequests;

    public string $mode = 'all';
    public ?string $status = null;
    public ?string $date = null;
    public ?string $floor_number = null;
    public ?string $camera_number = null;
    public ?string $observer = null;
    public ?string $sort = null;

    protected NoteService $noteService;

    public function boot(): void
    {
        $this->noteService = app(NoteService::class);
    }

    public function updatingStatus()       { $this->resetPage(); }
    public function updatingDate()         { $this->resetPage(); }
    public function updatingFloorNumber()  { $this->resetPage(); }
    public function updatingCameraNumber() { $this->resetPage(); }
    public function updatingObserver()     { $this->resetPage(); }
    public function updatingSort()         { $this->resetPage(); }

    public function getNotesProperty()
    {
        $user = auth()->user();

        if ($this->mode === 'my') {
            $query = Note::with(['owner:id,name,avatar_path', 'processor:id,name'])
                ->withCount('attachments')
                ->where('user_id', $user->id);
        } else {
            $query = $this->noteService->getVisibleNotesQuery($user);
        }

        if ($this->status) {
            if (in_array($this->status, ['draft', 'pending', 'accepted', 'rejected'], true)) {
                $query->where('status', $this->status);
            }
        }
        if ($this->date) {
            if (strtotime($this->date) !== false) {
                $query->whereDate('observed_at', $this->date);
            }
        }
        if ($this->floor_number) {
            $query->where('floor_number', (int) $this->floor_number);
        }
        if ($this->camera_number) {
            $query->where('camera_number', (int) $this->camera_number);
        }
        if ($this->observer && $this->mode === 'all') {
            $query->where('user_id', (int) $this->observer);
        }
        if ($this->sort === 'observer' && $this->mode === 'all') {
            $query->join('users', 'users.id', '=', 'notes.user_id')
                ->orderBy('users.name')
                ->select('notes.*');
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->paginate(15, ['*'], 'page');
    }

    public function getCountsProperty(): array
    {
        $user = auth()->user();

        $cacheKey = 'notes-counts:'.$user->id.':'.$this->mode;

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 30, function () use ($user) {
            if ($this->mode === 'my') {
                $base = Note::where('user_id', $user->id);
            } else {
                if ($user->isReportWriter()) {
                    $base = Note::where(function ($q) use ($user) {
                        $q->where('user_id', $user->id)
                            ->orWhere('status', '!=', Note::STATUS_DRAFT);
                    });
                } else {
                    $base = Note::where('user_id', $user->id);
                }
            }

            $statuses = [Note::STATUS_DRAFT, Note::STATUS_PENDING, Note::STATUS_ACCEPTED, Note::STATUS_REJECTED];
            $counts = (clone $base)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $result = ['total' => array_sum($counts)];
            foreach ($statuses as $status) {
                $result[strtolower($status)] = $counts[$status] ?? 0;
            }

            return $result;
        });
    }

    public function getObserversProperty()
    {
        try {
            $cached = cache()->get('observers_list');
            if ($cached instanceof \Illuminate\Database\Eloquent\Collection && $cached->first() instanceof User) {
                return $cached;
            }
            if (is_array($cached) && isset($cached[0]['id'])) {
                return collect($cached)->map(fn($r) => (object)$r);
            }
            if ($cached !== null) cache()->forget('observers_list');
            $fresh = User::where('role', 'monitor')->orderBy('name')->get(['id', 'name']);
            cache()->put('observers_list', $fresh->toArray(), 3600);
            return $fresh;
        } catch (\Throwable $e) {
            cache()->forget('observers_list');
            return User::where('role', 'monitor')->orderBy('name')->get(['id', 'name']);
        }
    }

    public function send(int $noteId): void
    {
        $note = Note::findOrFail($noteId);
        $this->authorize('send', $note);
        app(NoteService::class)->sendNote(auth()->user(), $note);
    }

    public function accept(int $noteId): void
    {
        $note = Note::findOrFail($noteId);
        $this->authorize('accept', $note);
        app(NoteService::class)->acceptNote(auth()->user(), $note);
    }

    public function reject(int $noteId, string $reason): void
    {
        $reason = trim($reason);
        \Illuminate\Support\Facades\Validator::make(
            ['reason' => $reason],
            ['reason' => 'required|string|min:5|max:1000'],
            [],
            ['reason' => __('ui.reject_reason')]
        )->validate();
        $note = Note::findOrFail($noteId);
        $this->authorize('reject', $note);
        app(NoteService::class)->rejectNote(auth()->user(), $note, $reason);
    }

    public function resend(int $noteId): void
    {
        $note = Note::findOrFail($noteId);
        $this->authorize('resend', $note);
        app(NoteService::class)->resendRejectedNote(auth()->user(), $note);
    }

    public function destroy(int $noteId): void
    {
        $note = Note::findOrFail($noteId);
        $this->authorize('delete', $note);
        app(NoteService::class)->deleteDraft(auth()->user(), $note);
    }

    public function clearFilters(): void
    {
        $this->status = null;
        $this->date = null;
        $this->floor_number = null;
        $this->camera_number = null;
        $this->observer = null;
        $this->sort = null;
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.notes-list', [
            'notes'     => $this->notes,
            'counts'    => $this->counts,
            'observers' => $this->observers,
        ]);
    }
}
