<?php

// Structured notification presentation — العرض المحلي للإشعارات.
// تُخزن الإشعارات هيكلياً (title_key/message_key + params) وتُعرض حسب الـlocale.

return [
    'note_sent_title' => 'واردة · كاميرا :camera',
    'note_sent_message' => 'من :name — طابق :floor · :time',
    'note_accepted_title' => 'مقبولة · كاميرا :camera',
    'note_accepted_message' => 'تم قبول الملاحظة #:id من :name — طابق :floor',
    'note_rejected_title' => 'مرفوضة · كاميرا :camera',
    'note_rejected_message' => 'رفضها :name — :reason',
    'dispatch_sent_title' => 'واردة · كاميرا :camera',
    'dispatch_sent_message' => 'من :name — طابق :floor',
    'dispatch_accepted_title' => 'مقبولة · كاميرا :camera',
    'dispatch_accepted_message' => 'تم قبول الإرسالية من :name',
    'dispatch_rejected_title' => 'مرفوضة · كاميرا :camera',
    'dispatch_rejected_message' => 'رفضها :name — :reason',
    'report_published_title' => 'تقرير منشور: :title',
    'report_published_message' => 'تم نشر تقرير بتاريخ :date',
    'fanout_note_title' => 'ملاحظة واردة',
    'fanout_note_body' => 'ملاحظة #:id · كاميرا :camera',
    'generic_title' => 'إشعار',
    'generic_message' => 'لديك إشعار جديد',
];
