import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Widget buildNoteItem(int index, {bool last = false, bool cardMargin = false}) =>
    Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Column(
              children: [
                Container(
                  key: Key('badge-$index'),
                  width: 30,
                  height: 30,
                  alignment: Alignment.center,
                  color: const Color(0xFFCCD),
                  child: Text('$index'),
                ),
                if (!last)
                  Expanded(
                    child: Container(
                      width: 2,
                      margin: const EdgeInsets.symmetric(vertical: 4),
                      color: const Color(0xFF999),
                    ),
                  ),
              ],
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Card(
                clipBehavior: Clip.antiAlias,
                margin: cardMargin
                    ? const EdgeInsets.symmetric(vertical: 5)
                    : EdgeInsets.zero,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    ListTile(key: Key('surface-$index'), title: Text('note $index')),
                    Container(height: 30),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );

void main() {
  testWidgets('badge aligns with the VISIBLE top of the card', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Column(
            children: [buildNoteItem(1), buildNoteItem(2, last: true)],
          ),
        ),
      ),
    );

    final badge = tester.getRect(find.byKey(const Key('badge-1')));
    final surface = tester.getRect(find.byKey(const Key('surface-1')));

    expect(
      badge.top,
      moreOrLessEquals(surface.top, epsilon: 0.5),
      reason: 'circle must sit on the visible top edge of the card',
    );
  });

  testWidgets('the buggy variant (margin on Card) really does misalign', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Column(
            children: [
              buildNoteItem(1, cardMargin: true),
              buildNoteItem(2, last: true, cardMargin: true),
            ],
          ),
        ),
      ),
    );

    final badge = tester.getRect(find.byKey(const Key('badge-1')));
    final surface = tester.getRect(find.byKey(const Key('surface-1')));

    expect(
      badge.top,
      moreOrLessEquals(surface.top - 5, epsilon: 0.5),
      reason: 'with margin on the Card the badge floats above the surface',
    );
  });

  testWidgets('cards are separated vertically', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Column(
            children: [buildNoteItem(1), buildNoteItem(2, last: true)],
          ),
        ),
      ),
    );
    final s1 = tester.getRect(find.byKey(const Key('surface-1')));
    final s2 = tester.getRect(find.byKey(const Key('surface-2')));
    expect(s2.top - s1.bottom, greaterThanOrEqualTo(10));
  });
}
