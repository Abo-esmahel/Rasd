import 'package:flutter_test/flutter_test.dart';
import 'package:rasd_app/core/api/api_response.dart';
import 'package:rasd_app/core/models.dart';

void main() {
  group('Note.fromJson', () {
    test('تحليل كامل + حقل واحد ناقص لا يكسر', () {
      final n = Note.fromJson({
        'id': 1,
        'user_id': 2,
        'floor_number': 3,
        'camera_number': 12,
        'observed_at': '2026-10-05T08:30:00',
        'description': 'وصف كافٍ للاختبار هنا',
        'status': 'pending',
        'owner': {'id': 2, 'username': 'u', 'role': 'monitor'},
        'attachments': [
          {'id': 9, 'original_name': 'a.jpg'},
        ],
      });
      expect(n.id, 1);
      expect(n.observedEndAt, isNull);
      expect(n.processedBy, isNull);
      expect(n.attachments.length, 1);
      expect(n.owner!.name, 'u');
      expect(n.isPending, isTrue);
    });
  });

  group('Report.fromJson', () {
    test('تاريخ مقطوع وحقول AI اختيارية', () {
      final r = Report.fromJson({
        'id': 5,
        'author_id': 7,
        'title': 'تقرير',
        'report_date': '2026-10-05T00:00:00.000000Z',
        'status': 'draft',
        'notes': [],
      });
      expect(r.reportDate, '2026-10-05');
      expect(r.locked, isFalse);
      expect(r.summary, isNull);
      expect(r.isDraft, isTrue);
    });
  });

  group('AppNotification.fromJson', () {
    test('تسوية الحقول المسطحة والمتداخلة', () {
      final n = AppNotification.fromJson({
        'id': 'abc',
        'read_at': null,
        'created_at': '2026-10-05T10:00:00Z',
        'data': {'title': 'قُبِلت', 'message': 'تم'},
      });
      expect(n.unread, isTrue);
      expect(n.title, 'قُبِلت');
      expect(n.body, 'تم');
      expect(n.priority, 'normal');
    });
  });

  group('GalleryItem.fromJson', () {
    test('روابط السيرفر الجاهزة + بدائل', () {
      final g = GalleryItem.fromJson({
        'id': 3,
        'original_name': 'x.jpg',
        'mime_type': 'image/jpeg',
        'file_size': 100,
        'kind': 'note',
        'parent_id': 8,
        'viewUrl': '/api/attachments/3/view',
        'downloadUrl': '/api/attachments/3',
        'camera': 4,
        'floor': 2,
        'owner_name': 'سامي',
      });
      expect(g.viewPath(), '/api/attachments/3/view');
      expect(g.ownerName, 'سامي');
      final g2 = GalleryItem.fromJson({'id': 4});
      expect(g2.viewPath(), contains('/attachments/4/view'));
      expect(g2.kind, 'note');
    });
  });

  group('Paged.fromJson', () {
    test('ترقيم Laravel + قائمة مباشرة + فارغ', () {
      final p = Paged.fromJson<Map<String, dynamic>>({
        'current_page': 1,
        'last_page': 3,
        'total': 25,
        'data': [
          {'a': 1},
          'junk',
        ],
      }, (j) => j);
      expect(p.items.length, 1);
      expect(p.hasMore, isTrue);
      expect(p.total, 25);
      final l = Paged.fromJson<Map<String, dynamic>>([
        {'a': 1},
      ], (j) => j);
      expect(l.hasMore, isFalse);
      final e = Paged.fromJson<Map<String, dynamic>>(null, (j) => j);
      expect(e.items, isEmpty);
    });
  });

  group('EngagementComment.fromJson', () {
    test('يستخرج معرّف الكاتب من كائن المستخدم', () {
      final c = EngagementComment.fromJson({
        'id': 7,
        'body': 'مرحبا',
        'user': {'id': 42, 'name': 'سامر', 'avatar_url': '/a.png'},
        'replies': [
          {
            'id': 8,
            'body': 'أهلا',
            'user': {'id': 43, 'name': 'ليلى'},
          },
        ],
      });
      expect(c.authorId, 42);
      expect(c.author, 'سامر');
      expect(c.avatarUrl, '/a.png');
      expect(c.replies.single.authorId, 43);
    });

    test('غياب المستخدم يعني معرّفاً فارغاً لا رقم صفر', () {
      final c = EngagementComment.fromJson({'id': 9, 'body': 'x'});
      expect(c.authorId, isNull);
      expect(c.replies, isEmpty);
    });
  });

  group('User roles', () {
    test('الأدوار الثلاثة', () {
      expect(
        User.fromJson({'id': 1, 'username': 'a', 'role': 'monitor'}).isMonitor,
        isTrue,
      );
      expect(
        User.fromJson({
          'id': 1,
          'username': 'a',
          'role': 'report_writer',
        }).isWriter,
        isTrue,
      );
      expect(
        User.fromJson({
          'id': 1,
          'username': 'a',
          'role': 'general_manager',
        }).isGM,
        isTrue,
      );
    });
  });
}
