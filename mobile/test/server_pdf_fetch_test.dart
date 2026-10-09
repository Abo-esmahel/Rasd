import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rasd_app/core/server_pdf.dart';
import 'package:rasd_app/core/strings.dart';

Dio dioAlways(int status, List<int> body) {
  final dio = Dio(BaseOptions(baseUrl: 'http://x/api'));
  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) => handler.resolve(
        Response<dynamic>(
          requestOptions: options,
          statusCode: status,
          data: status == 200 ? Uint8List.fromList(body) : body,
        ),
      ),
    ),
  );
  return dio;
}

void main() {
  final pdf = <int>[0x25, 0x50, 0x44, 0x46, 0x2D, 0x31, 0x37];

  Future<Uint8List> fetch(Dio dio, {int maxPolls = 5}) {
    return ServerPdf.fetchWith(
      dio: dio,
      lang: 'ar',
      kind: ServerPdfKind.note,
      id: 5,
      maxPolls: maxPolls,
    );
  }

  test('200 with real PDF bytes returns them', () async {
    final bytes = await fetch(dioAlways(200, pdf));
    expect(bytes.sublist(0, 4), utf8.encode('%PDF'));
  });

  test(
    '202 is not an error — it is retried until it gives up readably',
    () async {
      await expectLater(
        fetch(dioAlways(202, pdf), maxPolls: 2),
        throwsA(
          isA<ServerPdfException>().having(
            (e) => e.message,
            'message',
            trOf('ar', 'print_took_long'),
          ),
        ),
      );
    },
  );

  test('202 then 200 resolves with the PDF', () async {
    final dio = Dio(BaseOptions(baseUrl: 'http://x/api'));
    var call = 0;
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final ready = call++ > 0;
          handler.resolve(
            Response<dynamic>(
              requestOptions: options,
              statusCode: ready ? 200 : 202,
              data: ready ? Uint8List.fromList(pdf) : pdf,
            ),
          );
        },
      ),
    );
    final bytes = await fetch(dio);
    expect(bytes.length, pdf.length);
  });

  test('422 keeps the server message instead of a generic failure', () async {
    final body = utf8.encode(
      jsonEncode({
        'success': false,
        'message': 'المستند الرسمي غير متاح لهذا التقرير بعد',
      }),
    );
    final dio = Dio(BaseOptions(baseUrl: 'http://x/api'));
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) => handler.reject(
          DioException(
            requestOptions: options,
            type: DioExceptionType.badResponse,
            response: Response<dynamic>(
              requestOptions: options,
              statusCode: 422,
              data: body,
            ),
          ),
        ),
      ),
    );

    await expectLater(
      fetch(dio),
      throwsA(
        isA<ServerPdfException>().having(
          (e) => e.message,
          'message',
          'المستند الرسمي غير متاح لهذا التقرير بعد',
        ),
      ),
    );
  });

  test('200 that is not a PDF shows a readable message', () async {
    await expectLater(
      fetch(dioAlways(200, utf8.encode('<html>oops'))),
      throwsA(
        isA<ServerPdfException>().having(
          (e) => e.message,
          'message',
          trOf('ar', 'print_failed'),
        ),
      ),
    );
  });

  test('a failed generation reports the server reason, not a generic error',
      () async {
    final body = utf8.encode(
      jsonEncode({
        'success': false,
        'status': 'failed',
        'message': 'تعذّر توليد المستند على السيرفر',
      }),
    );
    await expectLater(
      fetch(dioAlways(500, body)),
      throwsA(
        isA<ServerPdfException>().having(
          (e) => e.message,
          'message',
          'تعذّر توليد المستند على السيرفر',
        ),
      ),
    );
  });

  test('a repeated fetch of the same document does not hit the network again',
      () async {
    ServerPdf.clearMemo();
    final dio = Dio(BaseOptions(baseUrl: 'http://x/api'));
    var calls = 0;
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          calls++;
          handler.resolve(
            Response<dynamic>(
              requestOptions: options,
              statusCode: 200,
              data: Uint8List.fromList(pdf),
            ),
          );
        },
      ),
    );

    final a = await fetch(dio);
    final b = await fetch(dio);

    expect(b, same(a));
    expect(calls, 1, reason: 'الطلب الثاني يجب أن يخدم من الذاكرة');
    ServerPdf.clearMemo();
  });

  test('the memo is keyed by language, so switching locale refetches', () async {
    ServerPdf.clearMemo();
    final dio = dioAlways(200, pdf);
    await fetch(dio);
    await ServerPdf.fetchWith(
      dio: dio,
      lang: 'en',
      kind: ServerPdfKind.note,
      id: 5,
    );
    ServerPdf.clearMemo();
  });
}
