<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AddThreeNotesSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk('attachments');
        $now = now();
        $users = User::where('role', 'monitor')->get();
        $writer = User::where('role', 'report_writer')->first();

        $defs = [
            ['u' => $users->first()->id, 'cam' => 4, 'fl' => 2, 'st' => 'accepted',
             'obs' => [$now->copy()->subHours(2)->setMinutes(15), null],
             'sent' => $now->copy()->subHours(1)->setMinutes(45), 'processed' => $now->copy()->subMinutes(30),
             'desc' => ' باب الطوارئ في الطابق الثاني مفتوح بشكل غير طبيعي، تم الإبلاغ والتحقق.', 'cap' => 'باب طوارئ مفتوح — طابق 2'],
            ['u' => $users->get(1)?->id ?? $users->first()->id, 'cam' => 13, 'fl' => 3, 'st' => 'pending',
             'obs' => [$now->copy()->subHour()->setMinutes(30), null],
             'sent' => $now->copy()->subMinutes(45), 'processed' => null,
             'desc' => 'رصد حركة مشبوهة قرب مخزن الأرشيف في الطابق الثالث، الشخص يحاول فتح الباب المغلق.', 'cap' => 'حركة مشبوهة — مخزن الأرشيف'],
            ['u' => $users->get(2)?->id ?? $users->first()->id, 'cam' => 9, 'fl' => 1, 'st' => 'draft',
             'obs' => [$now->copy()->subMinutes(40), null],
             'sent' => null, 'processed' => null,
             'desc' => 'مسودة: صوت طقطقة متكرر يُسمع من سقف الممر في الطابق الأرضي، قيد التحقق.', 'cap' => 'مسودة — صوت طقطقة السقف'],
        ];

        foreach ($defs as $d) {
            $sent = $d['sent'];
            $processed = $d['processed'];
            $created = ($sent ?? $d['obs'][0])->copy()->addMinutes(3);

            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $d['obs'][0],
                'observed_end_at' => $d['obs'][1],
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => $d['reason'] ?? null,
                'processed_by' => in_array($d['st'], ['accepted', 'rejected']) ? $writer->id : null,
                'sent_at' => $sent,
                'processed_at' => $processed,
            ]);
            Note::where('id', $n->id)->update(['created_at' => $created, 'updated_at' => $processed ?? $created]);

            $when = $n->observed_at->format('Y-m-d H:i');
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360"><rect width="640" height="360" fill="#0f1a13"/><text x="320" y="180" font-size="24" fill="#e8e4d2" text-anchor="middle" font-family="sans-serif">CAM ' . $n->camera_number . ' — ' . $when . '</text><rect y="314" width="640" height="46" fill="#0a0f0c"/><text x="320" y="335" font-size="16" fill="#e8e4d2" text-anchor="middle" font-family="sans-serif">' . $d['cap'] . '</text></svg>';
            $path = 'notes/' . $n->id . '/' . (string) Str::uuid() . '.svg';
            $disk->put($path, $svg);
            Attachment::create([
                'note_id' => $n->id,
                'file_path' => $path,
                'original_name' => 'كاميرا-' . $n->camera_number . '-لقطة.svg',
                'mime_type' => 'image/svg+xml',
                'file_size' => strlen($svg),
            ]);
            $this->command->line("Note #{$n->id} created: {$d['cap']}");
        }
        $this->command->info('Done: 3 notes added');
    }
}
