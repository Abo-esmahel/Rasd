<?php
use App\Models\Attachment;
use App\Models\Note;
use Illuminate\Support\Facades\Storage;

$notes = Note::withCount('attachments')->orderByDesc('id')->take(8)->get(['id','user_id','status','description','created_at']);
foreach ($notes as $n) {
    echo "NOTE#{$n->id} user={$n->user_id} status={$n->status} atts={$n->attachments_count} created={$n->created_at}\n";
    foreach (Attachment::where('note_id', $n->id)->get(['id','file_path','original_name','mime_type','file_size']) as $a) {
        try {
            $stream = Storage::disk('cloudinary')->readStream($a->file_path);
            if (is_resource($stream)) { fclose($stream); }
            $cloud = 'CLOUD_OK';
        } catch (Throwable $e) {
            $cloud = 'CLOUD_MISSING('.substr($e->getMessage(),0,80).')';
        }
        echo "   ATT#{$a->id} {$a->original_name} mime={$a->mime_type} size={$a->file_size} path={$a->file_path} => $cloud\n";
    }
}
