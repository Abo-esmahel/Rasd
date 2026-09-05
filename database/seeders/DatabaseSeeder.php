<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        // المراقبون
        $tariq = User::create(['name' => 'طارق عبد الرحمن', 'username' => 'tariq',  'password' => $password, 'role' => 'monitor']);
        $hadi  = User::create(['name' => 'هادي السهلي',     'username' => 'hadi',    'password' => $password, 'role' => 'monitor']);
        $hamza = User::create(['name' => 'حمزة الحاج قاسم', 'username' => 'hamza',   'password' => $password, 'role' => 'monitor']);
        $rami  = User::create(['name' => 'رامي حموري',      'username' => 'rami',    'password' => $password, 'role' => 'monitor']);

        // كاتب التقارير
        $writer = User::create(['name' => 'زهير العبد الله', 'username' => 'writer',  'password' => $password, 'role' => 'report_writer']);

        // ——— ملاحظات طارق ———
        $n1 = Note::create([
            'user_id'     => $tariq->id,
            'floor_number'=> 1,
            'camera_number'=> 5,
            'observed_at' => now()->subDays(3)->setTime(10, 30),
            'description' => '多年来首次发现3号摄像头画面模糊，可能是镜头脏污，需要清洁维护。',
            'status'      => 'accepted',
            'processed_by'=> $writer->id,
            'sent_at'     => now()->subDays(3)->setTime(10, 35),
            'processed_at'=> now()->subDays(2)->setTime(14, 0),
        ]);
        Attachment::create(['note_id'=>$n1->id, 'file_path'=>'notes/'.$n1->id.'/a1d036e5-3a04-44aa-8625-e50cc0ea6c81.jpg', 'original_name'=>'cam5-blur.jpg', 'mime_type'=>'image/jpeg', 'file_size'=>450000]);

        $n2 = Note::create([
            'user_id'     => $tariq->id,
            'floor_number'=> 2,
            'camera_number'=> 12,
            'observed_at' => now()->subDays(2)->setTime(15, 0),
            'description' => '楼道内有可疑人员徘徊，行为异常，请通知安保人员前往确认。',
            'status'      => 'pending',
            'sent_at'     => now()->subDays(2)->setTime(15, 10),
        ]);
        Attachment::create(['note_id'=>$n2->id, 'file_path'=>'notes/'.$n2->id.'/b7a890a6-17fa-4b99-b0ec-e6d9ebe02fd0.jpg', 'original_name'=>'suspicious-person.jpg', 'mime_type'=>'image/jpeg', 'file_size'=>520000]);
        Attachment::create(['note_id'=>$n2->id, 'file_path'=>'notes/'.$n2->id.'/926e786e-8472-4117-a2c7-3219d846d23f.jpg', 'original_name'=>'cam12-view.jpg', 'mime_type'=>'image/jpeg', 'file_size'=>480000]);

        $n3 = Note::create([
            'user_id'     => $tariq->id,
            'floor_number'=> 3,
            'camera_number'=> 8,
            'observed_at' => now()->subDay()->setTime(9, 0),
            'description' => '3号摄像头有轻微抖动，可能是固定螺丝松动，建议尽快检查。',
            'status'      => 'draft',
        ]);

        // ——— ملاحظات هادي ———
        $n4 = Note::create([
            'user_id'     => $hadi->id,
            'floor_number'=> 1,
            'camera_number'=> 3,
            'observed_at' => now()->subDays(4)->setTime(8, 15),
            'description' => '发现地下停车场入口处摄像头角度偏移，无法完整覆盖车辆进出区域。',
            'status'      => 'accepted',
            'processed_by'=> $writer->id,
            'sent_at'     => now()->subDays(4)->setTime(8, 20),
            'processed_at'=> now()->subDays(3)->setTime(11, 30),
        ]);
        Attachment::create(['note_id'=>$n4->id, 'file_path'=>'notes/'.$n4->id.'/2c856fb1-aa9d-4a38-9c18-efcc996ae2bc.jpg', 'original_name'=>'parking-cam.jpg', 'mime_type'=>'image/jpeg', 'file_size'=>610000]);

        $n5 = Note::create([
            'user_id'     => $hadi->id,
            'floor_number'=> 2,
            'camera_number'=> 7,
            'observed_at' => now()->subDays(1)->setTime(22, 45),
            'description' => '夜间2号走廊摄像头画面出现雪花噪点，红外夜视功能可能需要检修。',
            'status'      => 'rejected',
            'rejection_reason'=> 'المشكلة مؤقتة وتم حلها بإعادة تشغيل النظام',
            'processed_by'=> $writer->id,
            'sent_at'     => now()->subDays(1)->setTime(22, 50),
            'processed_at'=> now()->subHours(18),
        ]);
        Attachment::create(['note_id'=>$n5->id, 'file_path'=>'notes/'.$n5->id.'/65df0dac-57c6-4c4e-8399-9e6b5b1e7f44.jpg', 'original_name'=>'night-noise.jpg', 'mime_type'=>'image/jpeg', 'file_size'=>390000]);

        // ——— ملاحظات حمزة ———
        $n6 = Note::create([
            'user_id'     => $hamza->id,
            'floor_number'=> 4,
            'camera_number'=> 1,
            'observed_at' => now()->subDays(5)->setTime(14, 20),
            'description' => '四楼东侧走廊发现水管漏水，水迹已蔓延至摄像头下方，需紧急处理。',
            'status'      => 'accepted',
            'processed_by'=> $writer->id,
            'sent_at'     => now()->subDays(5)->setTime(14, 25),
            'processed_at'=> now()->subDays(4)->setTime(9, 0),
        ]);

        $n7 = Note::create([
            'user_id'     => $hamza->id,
            'floor_number'=> 1,
            'camera_number'=> 10,
            'observed_at' => now()->subHours(6)->setTime(11, 0),
            'description' => '大厅入口摄像头画面有横纹干扰，可能是线路老化导致。',
            'status'      => 'pending',
            'sent_at'     => now()->subHours(6)->setTime(11, 5),
        ]);

        // ——— ملاحظات رامي ———
        $n8 = Note::create([
            'user_id'     => $rami->id,
            'floor_number'=> 2,
            'camera_number'=> 6,
            'observed_at' => now()->subDays(2)->setTime(16, 30),
            'description' => '二楼电梯间摄像头检测到异常移动，视频已保存作为证据。',
            'status'      => 'accepted',
            'processed_by'=> $writer->id,
            'sent_at'     => now()->subDays(2)->setTime(16, 35),
            'processed_at'=> now()->subDays(1)->setTime(10, 0),
        ]);
        Attachment::create(['note_id'=>$n8->id, 'file_path'=>'notes/'.$n8->id.'/d28c94dd-6016-4353-b6fe-fc6829d67266.webm', 'original_name'=>'elevator-incident.webm', 'mime_type'=>'video/webm', 'file_size'=>2800000]);

        $n9 = Note::create([
            'user_id'     => $rami->id,
            'floor_number'=> 3,
            'camera_number'=> 15,
            'observed_at' => now()->subHours(3)->setTime(8, 45),
            'description' => '三楼会议室门口摄像头被人为调整角度，疑似有意避开监控区域。',
            'status'      => 'pending',
            'sent_at'     => now()->subHours(3)->setTime(8, 50),
        ]);
    }
}
