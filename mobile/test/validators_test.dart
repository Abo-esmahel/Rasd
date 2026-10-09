import 'package:flutter_test/flutter_test.dart';
import 'package:rasd_app/core/validators.dart';

void main() {
  group('V.floor', () {
    test('يقبل أرقاماً صحيحة ضمن الحد', () {
      expect(V.floor('3'), isNull);
      expect(V.floor('0'), isNull);
    });
    test('يرفض الفارغ وغير الرقمي والخارج', () {
      expect(V.floor(''), isNotNull);
      expect(V.floor('abc'), isNotNull);
      expect(V.floor('-1'), isNotNull);
      expect(V.floor('99999'), isNotNull);
    });
  });

  group('V.camera', () {
    test('الحد الأدنى 1', () {
      expect(V.camera('1'), isNull);
      expect(V.camera('0'), isNotNull);
      expect(V.camera(''), isNotNull);
    });
  });

  group('V.description', () {
    test('الحد الأدنى 10 أحرف', () {
      expect(V.description('123456789'), isNotNull);
      expect(V.description('1234567890'), isNull);
    });
    test('الحد الأقصى 5000', () {
      expect(V.description('x' * 5001), isNotNull);
      expect(V.description('x' * 5000), isNull);
    });
  });

  group('V.rejectionReason', () {
    test('5 أحرف على الأقل', () {
      expect(V.rejectionReason('abcd'), isNotNull);
      expect(V.rejectionReason('abcde'), isNull);
    });
  });

  group('V.fileProblem', () {
    test('يرفض النوع الغريب ويقبل المدعوم', () {
      expect(V.fileProblem('a.exe', 100), isNotNull);
      expect(V.fileProblem('a.jpg', 100), isNull);
      expect(V.fileProblem('a.mp4', 100), isNull);
      expect(V.fileProblem('a.m4a', 100), isNull);
    });
    test('يرفض الأحجام الضخمة مسبقاً', () {
      expect(V.fileProblem('a.jpg', 21 * 1024 * 1024),
          isNotNull);
      expect(V.fileProblem('a.mp4', 101 * 1024 * 1024),
          isNotNull);
    });
  });

  group('localization', () {
    test('إنجليزي عند التبديل', () {
      V.lang = 'en';
      expect(V.description(''), contains('required'));
      V.lang = 'ar';
      expect(V.description(''), contains('مطلوب'));
    });
  });
}
