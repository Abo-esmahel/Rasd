<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\ReportComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EngagementController extends Controller
{
    public function comments(Request $request, Report $report): JsonResponse
    {
        abort_unless($request->user()->can('view', $report), 403);
        return response()->json(['success' => true, 'data' => $this->commentsData($report, $request->user()->id)]);
    }

    public function commentsData(Report $report, int $viewerId): array
    {
        return $report->comments()->orderBy('created_at')->get()->map(fn ($c) => $this->commentData($c, $viewerId))->values()->all();
    }

    public function storeComment(Request $request, Report $report): JsonResponse
    {
        abort_unless($request->user()->can('comment', $report), 403);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:3000'],
            'parent_id' => ['nullable', 'integer', 'exists:report_comments,id'],
        ]);
        if (!empty($data['parent_id']) && !ReportComment::where('id', $data['parent_id'])->whereNull('parent_id')->where('report_id', $report->id)->exists()) {
            return response()->json(['success' => false, 'message' => __('api.forbidden')], 422);
        }
        $comment = $report->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => trim($data['body']),
        ])->load(['user', 'reactions', 'replies']);
        $this->fanout($comment, $request->user());
        return response()->json(['success' => true, 'data' => $this->commentData($comment, $request->user()->id)], 201);
    }

    public function reactReport(Request $request, Report $report): JsonResponse
    {
        abort_unless($request->user()->can('reactReport', $report), 403);
        return $this->toggle($request, $report);
    }

    public function reactNote(Request $request, Note $note): JsonResponse
    {
        $user = $request->user();
        abort_if($note->general_submission_id !== null, 404);

        abort_unless($user->can('react', $note), 403);

        $report = Report::whereHas('notes', fn ($q) => $q->whereKey($note->getKey()))->first();
        if ($report !== null) {
            abort_unless($user->can('reactNote', [$report, $note]), 403);
        }

        return $this->toggle($request, $note);
    }

    public function reactComment(Request $request, ReportComment $comment): JsonResponse
    {
        abort_unless($request->user()->can('comment', $comment->report), 403);
        return $this->toggle($request, $comment);
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

    private function toggle(Request $request, Report|Note|ReportComment $target): JsonResponse
    {
        $data = $request->validate(['reaction' => ['required', 'string', 'in:'.implode(',', Reaction::TYPES)]]);
        $userId = $request->user()->id;
        $result = DB::transaction(function () use ($target, $userId, $data) {
            $existing = $target->reactions()->where('user_id', $userId)->lockForUpdate()->first();
            if ($existing && $existing->reaction === $data['reaction']) {
                $existing->delete();
                return null;
            }
            if ($existing) {
                $existing->update(['reaction' => $data['reaction']]);
                return $existing->fresh();
            }
            return $target->reactions()->create(['user_id' => $userId, 'reaction' => $data['reaction']]);
        });
        return response()->json(['success' => true, 'data' => ['my_reaction' => $result?->reaction, 'counts' => $this->reactionCounts($target)]]);
    }

    private function reactionCounts(Report|Note|ReportComment $target): array
    {
        return $target->reactions()->selectRaw('reaction, count(*) as total')->groupBy('reaction')->pluck('total', 'reaction')->map(fn ($n) => (int) $n)->all();
    }

    private function commentData(ReportComment $comment, int $viewerId): array
    {
        $serialize = fn ($c) => [
            'id' => $c->id,
            'body' => $c->body,
            'parent_id' => $c->parent_id,
            'created_at' => $c->created_at,
            'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->localized_name, 'avatar_url' => $c->user->avatar_url] : null,
            'reaction_counts' => $c->reactions->groupBy('reaction')->map->count()->all(),
            'my_reaction' => $c->reactions->firstWhere('user_id', $viewerId)?->reaction,
        ];
        $data = $serialize($comment);
        $data['replies'] = $comment->replies->map($serialize)->values();
        return $data;
    }
}
