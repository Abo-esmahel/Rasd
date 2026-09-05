# DATABASE.md

## الجداول

### users
| العمود     | النوع    | nullable | مفتاح    |
|-----------|----------|----------|----------|
| id        | bigint   | لا       | PK       |
| name      | varchar  | لا       | -        |
| username  | varchar  | لا       | unique   |
| password  | varchar  | لا       | -        |
| role      | enum     | لا       | -        |
| created_at| timestamp| لا       | -        |
| updated_at| timestamp| لا       | -        |

`role` فقط: `monitor` | `report_writer`

### notes
| العمود          | Tipo     | nullable | مفتاح    |
|----------------|----------|----------|----------|
| id             | bigint   | لا       | PK       |
| user_id        | bigint   | لا       | FK→users |
| floor_number   | integer  | لا       | index    |
| camera_number  | integer  | لا       | index    |
| observed_at    | datetime | لا       | index    |
| description    | text     | لا       | -        |
| status         | enum     | لا       | index    |
| rejection_reason| text    | نعم      | -        |
| processed_by   | bigint   | نعم      | FK→users |
| sent_at        | timestamp| نعم      | -        |
| processed_at   | timestamp| نعم      | -        |
| created_at     | timestamp| لا       | -        |
| updated_at     | timestamp| لا       | -        |

`status`: `draft` | `pending` | `accepted` | `rejected`

### attachments
| العمود        | Tipo     | nullable | مفتاح    |
|--------------|----------|----------|----------|
| id           | bigint   | لا       | PK       |
| note_id      | bigint   | لا       | FK→notes |
| file_path    | varchar  | لا       | -        |
| original_name| varchar  | لا       | -        |
| mime_type    | varchar  | لا       | -        |
| file_size    | bigint   | لا       | -        |
| created_at   | timestamp| لا       | -        |
| updated_at   | timestamp| لا       | -        |

## العلاقات
- User hasMany notes
- User hasMany processedNotes
- Note belongsTo owner (user_id)
- Note belongsTo processor (processed_by)
- Note hasMany attachments
- Attachment belongsTo note

## الفهارس
- users.username (unique)
- notes.user_id
- notes.status
- notes.observed_at
- notes.floor_number
- notes.camera_number
- notes.processed_by
- attachments.note_id
