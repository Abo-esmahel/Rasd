<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\Note;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RasdDemoNotesSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk('attachments');


        $ids = Note::pluck('id')->all();
        $files = 0;
        foreach (Attachment::whereIn('note_id', $ids)->get() as $a) {
            try {
                if ($a->file_path && $disk->exists($a->file_path)) {
                    $disk->delete($a->file_path);
                    $files++;
                }
            } catch (\Throwable $e) {
            }
        }
        $attDel = Attachment::whereIn('note_id', $ids)->delete();
        $notifDel = 0;
        foreach (DatabaseNotification::all() as $n) {
            $d = $n->data;
            if (is_string($d)) {
                $d = json_decode($d, true);
            }
            $nid = is_array($d) ? ($d['note_id'] ?? null) : null;
            if ($nid && in_array($nid, $ids)) {
                $n->delete();
                $notifDel++;
            }
        }
        $noteDel = Note::whereIn('id', $ids)->delete();
        $this->command->warn("WIPE notes=$noteDel attachments=$attDel files=$files notifications=$notifDel");


        $now = now();
        $defs = [
            ['u' => 2, 'cam' => 12, 'fl' => 2, 'st' => 'pending', 'kind' => 'person',
             'obs' => [$now->copy()->subDay()->setTime(22, 40), $now->copy()->subDay()->setTime(23, 15)],
             'sent' => $now->copy()->subDay()->setTime(23, 20), 'created' => $now->copy()->subDay()->setTime(23, 22),
             'desc' => 'رصد حركة غير معتادة قرب البوابة الجانبية بعد انتهاء الدوام الرسمي، شخص يتجول بمحاذاة السور لأكثر من عشر دقائق.', 'cap' => 'حركة ليلية — البوابة الجانبية'],
            ['u' => 2, 'cam' => 5, 'fl' => 1, 'st' => 'accepted', 'kind' => 'door', 'proc' => 5,
             'obs' => [$now->copy()->subDays(3)->setTime(9, 5), null],
             'sent' => $now->copy()->subDays(3)->setTime(9, 40), 'processed' => $now->copy()->subDays(2)->setTime(10, 15), 'created' => $now->copy()->subDays(3)->setTime(9, 42),
             'desc' => 'الباب الخلفي لقاعة الاجتماعات مفتوح ولا يوجد موظفون في الجوار، تم إغلاقه وإبلاغ الحارس المناوب.', 'cap' => 'باب خلفي مفتوح — قاعة الاجتماعات'],
            ['u' => 4, 'cam' => 8, 'fl' => 3, 'st' => 'pending', 'kind' => 'static',
             'obs' => [$now->copy()->setTime(7, 30), null],
             'sent' => $now->copy()->setTime(8, 5), 'created' => $now->copy()->setTime(8, 7),
             'desc' => 'انقطاع متقطع في بث الكاميرا مع تجمد الصورة لثوان متكررة، يرجى فحص التوصيلات ووحدة التغذية.', 'cap' => 'انقطاع البث — فحص فني مطلوب'],
            ['u' => 4, 'cam' => 3, 'fl' => 1, 'st' => 'rejected', 'kind' => 'archive', 'proc' => 5,
             'reason' => 'اللقطة المرفقة غير واضحة ولا تظهر وقت الرصد، أعد الإرسال مع مرفق أوضح.',
             'obs' => [$now->copy()->subDays(4)->setTime(16, 20), null],
             'sent' => $now->copy()->subDays(4)->setTime(17, 0), 'processed' => $now->copy()->subDays(3)->setTime(11, 30), 'created' => $now->copy()->subDays(4)->setTime(17, 5),
             'desc' => 'بلاغ عن ازدحام عند المدخل الرئيسي وقت الذروة مع صعوبة تمييز التفاصيل في التسجيل.', 'cap' => 'ازدحام المدخل — لقطة غير واضحة'],
            ['u' => 1, 'cam' => 21, 'fl' => 4, 'st' => 'accepted', 'kind' => 'crowd', 'proc' => 5,
             'obs' => [$now->copy()->subDays(2)->setTime(13, 0), $now->copy()->subDays(2)->setTime(13, 20)],
             'sent' => $now->copy()->subDays(2)->setTime(13, 45), 'processed' => $now->copy()->subDays(2)->setTime(15, 10), 'created' => $now->copy()->subDays(2)->setTime(13, 50),
             'desc' => 'تجمع عدد من المراجعين أمام مدخل الاستوديو بشكل يعيق الحركة، تم تنظيم الدخول بالتعاون مع الأمن.', 'cap' => 'تجمع مراجعين — مدخل الاستوديو'],
            ['u' => 1, 'cam' => 7, 'fl' => 2, 'st' => 'draft', 'kind' => 'archive',
             'obs' => [$now->copy()->setTime(11, 10), null],
             'sent' => null, 'created' => $now->copy()->setTime(11, 25),
             'desc' => 'مسودة: ملاحظة أولية عن صوت إنذار متقطع من غرفة الأرشيف، قيد الاستكمال والتحقق قبل الإرسال.', 'cap' => 'مسودة — صوت إنذار الأرشيف'],
            ['u' => 3, 'cam' => 14, 'fl' => 5, 'st' => 'pending', 'kind' => 'corridor',
             'obs' => [$now->copy()->setTime(6, 15), null],
             'sent' => $now->copy()->setTime(6, 50), 'created' => $now->copy()->setTime(6, 55),
             'desc' => 'إضاءة ممر الطابق الخامس مطفأة بالكامل والرؤية شبه معدومة ليلا، يلزم تدخل الصيانة بشكل عاجل.', 'cap' => 'انطفاء إضاءة الممر — طابق 5'],
            ['u' => 3, 'cam' => 2, 'fl' => 1, 'st' => 'rejected', 'kind' => 'door', 'proc' => 5,
             'reason' => 'مكررة: سبق توثيق نفس الحالة في ملاحظة سابقة وما تزال قيد المعالجة.',
             'obs' => [$now->copy()->subDays(5)->setTime(10, 0), null],
             'sent' => $now->copy()->subDays(5)->setTime(10, 30), 'processed' => $now->copy()->subDays(4)->setTime(9, 0), 'created' => $now->copy()->subDays(5)->setTime(10, 35),
             'desc' => 'بلاغ عن باب المستودع الجانبي غير محكم الإغلاق صباحا.', 'cap' => 'باب المستودع — بلاغ مكرر'],
        ];

        $made = 0;
        foreach ($defs as $d) {
            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $d['obs'][0],
                'observed_end_at' => $d['obs'][1],
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => $d['reason'] ?? null,
                'processed_by' => $d['proc'] ?? null,
                'sent_at' => $d['sent'],
                'processed_at' => $d['processed'] ?? null,
            ]);
            Note::where('id', $n->id)->update(['created_at' => $d['created'], 'updated_at' => $d['created']]);
            $when = $d['obs'][0]->format('Y-m-d H:i');
            $svg = $this->svgScene($d['cam'], $d['fl'], $when, $d['kind'], $d['cap']);
            $path = 'notes/' . $n->id . '/' . (string) Str::uuid() . '.svg';
            $disk->put($path, $svg);
            Attachment::create([
                'note_id' => $n->id,
                'file_path' => $path,
                'original_name' => 'كاميرا-' . $d['cam'] . '-لقطة.svg',
                'mime_type' => 'image/svg+xml',
                'file_size' => strlen($svg),
            ]);
            $made++;
            $this->command->line("note #{$n->id} user={$d['u']} cam={$d['cam']} status={$d['st']}");
        }


        $bad = Note::get()->filter(fn ($n) => mb_strlen($n->description) >= strlen($n->description))->count();
        $local = Attachment::get()->filter->isLocal()->count();
        $this->command->info("DONE notes=$made | CHECK notes=" . Note::count() . ' attach=' . Attachment::count() . " local_ok=$local broken_arabic=$bad");
        if ($bad > 0 || Note::count() !== 8 || $local !== 8) {
            throw new \RuntimeException('SEED VERIFICATION FAILED');
        }
    }

    private function svgScene($cam, $floor, $when, $kind, $caption): string
    {
        if ($kind === 'person') {
            $extra = '<circle cx="430" cy="205" r="16" fill="#d8b24a"/><rect x="414" y="222" width="32" height="58" rx="8" fill="#d8b24a"/><rect x="120" y="120" width="400" height="10" fill="#2a3d2f"/>';
        } elseif ($kind === 'door') {
            $extra = '<rect x="270" y="110" width="100" height="170" rx="4" fill="#3d5a43" stroke="#e8e4d2" stroke-width="4"/><circle cx="354" cy="200" r="5" fill="#e8e4d2"/>';
        } elseif ($kind === 'static') {
            $extra = '<g stroke="#3d5a43" stroke-width="2"><line x1="60" y1="140" x2="580" y2="140"/><line x1="60" y1="180" x2="580" y2="180"/><line x1="60" y1="220" x2="580" y2="220"/><line x1="60" y1="260" x2="580" y2="260"/></g><text x="320" y="205" font-size="26" fill="#e05252" text-anchor="middle" font-family="sans-serif">NO SIGNAL</text>';
        } elseif ($kind === 'crowd') {
            $extra = '<g fill="#d8b24a"><circle cx="240" cy="220" r="14"/><circle cx="300" cy="210" r="14"/><circle cx="360" cy="220" r="14"/><circle cx="420" cy="212" r="14"/><rect x="200" y="238" width="260" height="42" rx="10"/></g>';
        } elseif ($kind === 'corridor') {
            $extra = '<rect x="80" y="120" width="480" height="160" fill="#0a0f0c"/><circle cx="320" cy="140" r="10" fill="none" stroke="#5f7568" stroke-width="4"/><line x1="320" y1="150" x2="320" y2="165" stroke="#5f7568" stroke-width="4"/>';
        } else {
            $extra = '<rect x="180" y="130" width="280" height="150" rx="6" fill="none" stroke="#d8b24a" stroke-width="3" stroke-dasharray="10 6"/>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">'
            . '<rect width="640" height="360" fill="#0f1a13"/>'
            . '<g stroke="#1e2e22" stroke-width="1"><line x1="0" y1="90" x2="640" y2="90"/><line x1="0" y1="180" x2="640" y2="180"/><line x1="0" y1="270" x2="640" y2="270"/><line x1="160" y1="0" x2="160" y2="360"/><line x1="320" y1="0" x2="320" y2="360"/><line x1="480" y1="0" x2="480" y2="360"/></g>'
            . '<rect width="640" height="46" fill="#0a0f0c"/>'
            . '<circle cx="24" cy="23" r="7" fill="#e05252"/><text x="44" y="30" font-size="17" fill="#e8e4d2" font-family="sans-serif">REC</text>'
            . '<text x="616" y="30" font-size="17" fill="#e8e4d2" text-anchor="end" font-family="sans-serif">CAM ' . $cam . '</text>'
            . $extra
            . '<rect y="314" width="640" height="46" fill="#0a0f0c"/>'
            . '<text x="320" y="335" font-size="16" fill="#e8e4d2" text-anchor="middle" font-family="sans-serif">' . $caption . ' — ' . $when . '</text>'
            . '</svg>';
    }
}
