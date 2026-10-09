import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rasd_app/core/api/api_response.dart';
import 'package:rasd_app/core/models.dart';
import 'package:rasd_app/core/widgets.dart';
import 'package:rasd_app/features/auth/auth_provider.dart';
import 'package:rasd_app/features/reports/reports_page.dart';
import 'package:rasd_app/features/reports/reports_repo.dart';

class _FakeRepo extends ReportsRepo {
  _FakeRepo(this.items) : super(Dio());
  final List<Report> items;
  @override
  Future<Paged<Report>> list({
    String? status,
    String? date,
    String? q,
    int page = 1,
  }) async => Paged<Report>(items: items, page: 1, lastPage: 1, total: items.length);
}

Report sample(String status) => Report(
      id: 1,
      authorId: 5,
      title: 'تقرير الاعتماد اليومي',
      reportDate: '2026-10-09',
      status: status,
      locked: false,
      visibleToMonitors: true,
      content: null,
      summary: null,
      recommendations: null,
      author: null,
      notes: const [],
      notesCount: 3,
    );

Future<void> pumpList(WidgetTester tester, String status) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        authProvider.overrideWith(
          (ref) => AuthNotifier(ref)
            ..state = AsyncValue.data(
              User(id: 5, name: 'W', username: 'w', role: 'report_writer'),
            ),
        ),
        reportsRepoProvider.overrideWith((ref) => _FakeRepo([sample(status)])),
      ],
      child: const MaterialApp(home: Scaffold(body: ReportsPage())),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('حالة التقرير لا تُعرض فوق صورة الورقة', (tester) async {
    await pumpList(tester, 'published');

    expect(find.byType(RasdStatusChip), findsOneWidget);

    final chip = tester.getRect(find.byType(RasdStatusChip));
    final cover = tester.getRect(find.byType(Stack).first);

    expect(
      chip.top,
      greaterThanOrEqualTo(cover.bottom - 1),
      reason: 'the chip must sit under the sheet cover, not on top of it',
    );
  });

  testWidgets('حالة المسودة أيضاً أسفل الصورة', (tester) async {
    await pumpList(tester, 'draft');
    expect(find.byType(RasdStatusChip), findsOneWidget);
    final chip = tester.getRect(find.byType(RasdStatusChip));
    final cover = tester.getRect(find.byType(Stack).first);
    expect(chip.top, greaterThanOrEqualTo(cover.bottom - 1));
  });
}
