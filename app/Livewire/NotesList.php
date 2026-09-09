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
            $query = Note::with(['owner', 'processor', 'attachments'])
                ->where('user_id', $user->id);
        } else {
            $query = $this->noteService->getVisibleNotesQuery($user);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }
        if ($this->date) {
            $query->whereDate('observed_at', $this->date);
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

        if ($this->mode === 'my') {
            $base = Note::where('user_id', $user->id);
        } else {
            // خصوصية: الملاحظ يرى أعداده الخاصة فقط، الكاتب يرى الخاصة + كل غير المسودة
            if ($user->isReportWriter()) {
                $base = Note::where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('status', '!=', Note::STATUS_DRAFT);
                });
            } else {
                $base = Note::where('user_id', $user->id);
            }
        }

        return [
            'total'    => (clone $base)->count(),
            'draft'    => (clone $base)->where('status', Note::STATUS_DRAFT)->count(),
            'pending'  => (clone $base)->where('status', Note::STATUS_PENDING)->count(),
            'accepted' => (clone $base)->where('status', Note::STATUS_ACCEPTED)->count(),
            'rejected' => (clone $base)->where('status', Note::STATUS_REJECTED)->count(),
        ];
    }

    public function getObserversProperty()
    {
        return User::where('role', 'monitor')->orderBy('name')->get();
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
