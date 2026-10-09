import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rasd_app/core/wall_clock.dart';

void main() {
  group('Wall.combine', () {
    test('صيغة جدارية بدون timezone', () {
      final s = Wall.combine(
          DateTime(2026, 10, 5),
          const TimeOfDay(hour: 8, minute: 5));
      expect(s, '2026-10-05T08:05');
    });
  });

  group('Wall.spanMinutes', () {
    test('عبور منتصف الليل', () {
      expect(
          Wall.spanMinutes(const TimeOfDay(hour: 23, minute: 0),
              const TimeOfDay(hour: 1, minute: 0)),
          120);
    });
    test('نفس اليوم', () {
      expect(
          Wall.spanMinutes(const TimeOfDay(hour: 8, minute: 0),
              const TimeOfDay(hour: 10, minute: 30)),
          150);
    });
  });

  group('Wall.time12', () {
    test('صيغة 12 ساعة عربية', () {
      expect(Wall.time12('08:05'), '8:05 ص');
      expect(Wall.time12('14:30'), '2:30 م');
      expect(Wall.time12('00:00'), '12:00 ص');
    });
  });

  group('Wall.isFuture', () {
    test('الماضي ليس مستقبلاً', () {
      expect(
          Wall.isFuture(
              DateTime.now()
                  .subtract(const Duration(days: 1)),
              const TimeOfDay(hour: 0, minute: 0)),
          isFalse);
    });
  });

  group('Wall.parts', () {
    test('تفكيك آمن', () {
      final p = Wall.parts('2026-10-05T08:30:00');
      expect(p.date, '2026-10-05');
      expect(p.time, '08:30');
      final e = Wall.parts(null);
      expect(e.date, '');
    });
  });
}
