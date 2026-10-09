<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Reaction;
use App\Models\Note;
use App\Models\Report;
use App\Models\ReportComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportEngagementController extends Controller
{
    public function storeComment(Request $request, Report $report)
    {
        abort_unless($request->user()->can('comment', $report), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:3000'],
            'parent_id' => ['nullable', 'integer', 'exists:report_comments,id'],
        ]);

        if (!empty($data['parent_id']) && !ReportComment::whereKey($data['parent_id'])
            ->whereNull('parent_id')->where('report_id', $report->id)->exists()) {
            return back()->withInput()->withErrors(['body' => __('api.forbidden')]);
        }

        $comment = $report->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => trim($data['body']),
        ]);

        $this->fanout($comment, $request->user());

        return redirect()->route('reports.show', $report)->withFragment('report-discussion')->with('success', __('ui.comment_added'));
    }

    public function reactReport(Request $request, Report $report)
    {
        abort_unless($request->user()->can('view', $report), 403);
        $data = $request->validate(['reaction' => ['required', 'string', 'in:'.implode(',', Reaction::TYPES)]]);
        $this->toggle($report, (int) $request->user()->id, $data['reaction']);

        return redirect()->route('reports.show', $report)->withFragment('report-discussion');
    }

    public function reactNote(Request $request, Note $note)
    {
        abort_if($note->general_submission_id !== null, 404);
        $user = $request->user();
        $canReact = $user->can('view', $note) || ($user->isGeneralManager() && !$note->isDraft());
        abort_unless($canReact, 403);
        $data = $request->validate(['reaction' => ['required', 'string', 'in:'.implode(',', Reaction::TYPES)]]);
        $this->toggle($note, (int) $user->id, $data['reaction']);

        return redirect()->route('notes.show', $note)->withFragment('note-reactions');
    }

    public function reactComment(Request $request, ReportComment $comment)
    {
        abort_unless($request->user()->can('view', $comment->report), 403);
        $data = $request->validate(['reaction' => ['required', 'string', 'in:'.implode(',', Reaction::TYPES)]]);
        $this->toggle($comment, (int) $request->user()->id, $data['reaction']);

        return redirect()->route('reports.show', $comment->report)->withFragment('report-discussion');
    }

    private function fanout(ReportComment $comment, \App\Models\User $user): void
    {
        try {
            \App\Jobs\FanoutCommentNotifications::dispatch(
                $comment->id,
                (int) $user->id,
                (string) $user->localized_name,
            )->afterResponse();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[COMMENT] fanout dispatch failed: '.$e->getMessage());
        }
    }

    private function toggle(Report|ReportComment|Note $target, int $userId, string $reaction): void
    {
        DB::transaction(function () use ($target, $userId, $reaction) {
            $existing = $target->reactions()->where('user_id', $userId)->lockForUpdate()->first();
            if ($existing && $existing->reaction === $reaction) {
                $existing->delete();
            } elseif ($existing) {
                $existing->update(['reaction' => $reaction]);
            } else {
                $target->reactions()->create(['user_id' => $userId, 'reaction' => $reaction]);
            }
        });
    }
}
