import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:rasd_app/core/cache_janitor.dart';

void main() {
  late Directory root;

  File make(String name, int kb, Duration age) {
    final f = File('${root.path}/$name');
    f.writeAsBytesSync(List<int>.filled(kb * 1024, 0));
    f.setLastModifiedSync(DateTime.now().subtract(age));
    return f;
  }

  setUp(() => root = Directory.systemTemp.createTempSync('janitor_test'));
  tearDown(() {
    if (root.existsSync()) root.deleteSync(recursive: true);
  });

  test('currentBytes يجمع الملفات في المجلدات المتداخلة', () async {
    final deep = Directory('${root.path}/a/b/c')..createSync(recursive: true);
    File('${deep.path}/x.bin').writeAsBytesSync(List.filled(1024, 0));
    File('${root.path}/y.bin').writeAsBytesSync(List.filled(1024, 0));

    expect(await CacheJanitor.currentBytes(), greaterThanOrEqualTo(2048));
  });

  test('تحت السقف: لا مسح إطلاقاً', () async {
    final only = make('a.bin', 64, const Duration(days: 30));

    final r = await CacheJanitor.sweepRoots(
      [root],
      force: false,
      max: 10 * 1024 * 1024,
      target: 8 * 1024 * 1024,
    );

    expect(r.swept, isFalse);
    expect(r.deletedFiles, 0);
    expect(only.existsSync(), isTrue);
  });

  test('فوق السقف: الأقدم يُحذف أولاً والحديث يبقى', () async {
    final oldest = make('oldest.bin', 40, const Duration(days: 30));
    final older = make('older.bin', 40, const Duration(days: 20));
    final recent = make('recent.bin', 40, const Duration(minutes: 2));

    final r = await CacheJanitor.sweepRoots(
      [root],
      force: false,
      max: 100 * 1024,
      target: 50 * 1024,
    );

    expect(r.deletedFiles, greaterThan(0));
    expect(oldest.existsSync(), isFalse, reason: 'الأقدم يُحذف أولاً');
    expect(older.existsSync(), isFalse, reason: 'يتبع الأقدم في الترتيب');
    expect(recent.existsSync(), isTrue, reason: 'حديث = قيد الاستخدام, محميّ');
    expect(r.afterBytes, lessThanOrEqualTo(100 * 1024));
  });

  test('الملفات الحديثة وحدها: السلامة تتقدّم على الهدف', () async {
    for (var i = 0; i < 5; i++) {
      make('recent_$i.bin', 40, Duration(minutes: 1 + i));
    }

    final r = await CacheJanitor.sweepRoots(
      [root],
      force: false,
      max: 10 * 1024,
      target: 5 * 1024,
    );

    expect(r.deletedFiles, 0);
    expect(r.afterBytes, greaterThan(5 * 1024),
        reason: 'الأولوية للسلامة: نتجاوز الهدف بدل حذف ملف جارٍ');
    expect(Directory('${root.path}/recent_4.bin').existsSync(), isTrue);
  });

  test('force يحذف بلا سقف أدنى', () async {
    make('old.bin', 40, const Duration(days: 30));
    make('old2.bin', 40, const Duration(days: 29));

    final r = await CacheJanitor.sweepRoots(
      [root],
      force: true,
      target: 1,
    );

    expect(r.swept, isTrue);
    expect(Directory('${root.path}/old.bin').existsSync(), isFalse);
  });
}
