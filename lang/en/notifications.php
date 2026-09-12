<?php

// Structured notification presentation — localized per current locale.
// Notifications are stored structurally (title_key/message_key + params).

return [
    'note_sent_title' => 'Incoming · Camera :camera',
    'note_sent_message' => 'From :name — Floor :floor · :time',
    'note_accepted_title' => 'Accepted · Camera :camera',
    'note_accepted_message' => 'Note #:id accepted by :name — Floor :floor',
    'note_rejected_title' => 'Rejected · Camera :camera',
    'note_rejected_message' => 'Rejected by :name — :reason',
    'dispatch_sent_title' => 'Incoming · Camera :camera',
    'dispatch_sent_message' => 'From :name — Floor :floor',
    'dispatch_accepted_title' => 'Accepted · Camera :camera',
    'dispatch_accepted_message' => 'Submission accepted by :name',
    'dispatch_rejected_title' => 'Rejected · Camera :camera',
    'dispatch_rejected_message' => 'Rejected by :name — :reason',
    'report_published_title' => 'Published report: :title',
    'report_published_message' => 'A report dated :date was published',
    'fanout_note_title' => 'Incoming note',
    'fanout_note_body' => 'Note #:id · Camera :camera',
    'generic_title' => 'Notification',
    'generic_message' => 'You have a new notification',
];
