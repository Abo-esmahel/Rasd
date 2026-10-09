import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Widget buildItem({bool withReactions = true}) => Padding(
  padding: const EdgeInsets.only(bottom: 10),
  child: IntrinsicHeight(
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Column(
          children: [
            Container(
              width: 30,
              height: 30,
              alignment: Alignment.center,
              color: const Color(0xFFCCDDEE),
              child: const Text('1'),
            ),
            const Expanded(
              child: Padding(
                padding: EdgeInsets.symmetric(vertical: 4),
                child: SizedBox(
                  width: 2,
                  child: ColoredBox(color: Color(0xFF99AABB)),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Card(
            clipBehavior: Clip.antiAlias,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ListTile(
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 4,
                  ),
                  title: const Text(
                    'كاميرا 2 · الطابق 1',
                    textDirection: TextDirection.ltr,
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                  subtitle: const Text(
                    'وصف الملاحظة هنا يمر بسطرين كاملين لاختبار الفيض',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  trailing: IconButton(
                    icon: const Icon(Icons.remove_circle_outline),
                    onPressed: () {},
                  ),
                ),
                if (withReactions)
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsetsDirectional.fromSTEB(12, 8, 12, 10),
                    child: Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: [
                          for (final e in ['👍', '❤️', '💡', '🙏', '👎', '😡'])
                            ActionChip(
                              avatar: Text(e, style: const TextStyle(fontSize: 14)),
                              label: const Text('0'),
                              onPressed: () {},
                              visualDensity: VisualDensity.compact,
                              padding: const EdgeInsets.symmetric(horizontal: 2),
                            ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ],
    ),
  ),
);

Future<void> pump(WidgetTester tester, {bool withReactions = true}) async {
  tester.view.physicalSize = const Size(1080, 2400);
  tester.view.devicePixelRatio = 3.0;
  addTearDown(tester.view.reset);

  final errors = <String>[];
  final prev = FlutterError.onError;
  FlutterError.onError = (d) {
    errors.add(d.toString());
    errors.add('--- context ---\n' + d.context.toString());
    final info = d.informationCollector?.call().toString() ?? '';
    errors.add('--- info ---\n' + info.substring(0, 1200));
  };
  addTearDown(() => FlutterError.onError = prev);

  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(child: buildItem(withReactions: withReactions)),
      ),
    ),
  );
  for (final e in errors) {
    // ignore: avoid_print
    print(e);
  }
}

void main() {
  testWidgets('بطاقة الملاحظة مع شريط التفاعلات لا تفيض', (tester) async {
    await pump(tester);
    expect(tester.takeException(), isNull);
  });

  testWidgets('بطاقة الملاحظة بلا شريط تفاعلات لا تفيض', (tester) async {
    await pump(tester, withReactions: false);
    expect(tester.takeException(), isNull);
  });
}