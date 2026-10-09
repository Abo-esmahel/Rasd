import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rasd_app/core/api/api_exception.dart';

DioException _dio({
  int? status,
  dynamic data,
  DioExceptionType type = DioExceptionType.badResponse,
  Map<String, List<String>>? headers,
}) {
  return DioException(
    requestOptions: RequestOptions(path: '/x'),
    type: type,
    response: status == null && data == null
        ? null
        : Response(
            requestOptions: RequestOptions(path: '/x'),
            statusCode: status,
            data: data,
            headers: Headers.fromMap(headers ?? {}),
          ),
  );
}

void main() {
  group('ApiException.fromDio', () {
    test('422 يفكك أخطاء الحقول', () {
      final e = ApiException.fromDio(_dio(status: 422, data: {
        'success': false,
        'message': 'validation',
        'errors': {
          'description': ['قصير'],
        },
      }));
      expect(e.statusCode, 422);
      expect(e.firstFieldError('description'), 'قصير');
    });

    test('401 رسالة جلسة', () {
      final e = ApiException.fromDio(
          _dio(status: 401, data: {'message': 'Unauthenticated'}));
      expect(e.userMessage, contains('جلسة'));
    });

    test('403 يمرر رسالة السيرفر', () {
      final e = ApiException.fromDio(
          _dio(status: 403, data: {'message': 'ممنوع'}));
      expect(e.userMessage, 'ممنوع');
    });

    test('404 رسالة بشرية', () {
      final e = ApiException.fromDio(_dio(status: 404));
      expect(e.userMessage, contains('غير موجود'));
    });

    test('429 مع Retry-After', () {
      final e = ApiException.fromDio(_dio(
        status: 429,
        data: {'message': 'slow'},
        headers: {'retry-after': ['30']},
      ));
      expect(e.retryAfter, 30);
      expect(e.userMessage, contains('30'));
    });

    test('AI rate limited قابل لإعادة المحاولة', () {
      final e = ApiException.fromDio(_dio(status: 429, data: {
        'success': false,
        'message': 'busy',
        'error': {
          'code': 'AI_RATE_LIMITED',
          'retryable': true,
          'retry_after': 20,
        },
      }));
      expect(e.code, 'AI_RATE_LIMITED');
      expect(e.retryable, isTrue);
      expect(e.userMessage, contains('20'));
    });

    test('عطل الشبكة رسالة LAN', () {
      final e = ApiException.fromDio(_dio(
          type: DioExceptionType.connectionError));
      expect(e.userMessage, contains('رصد المحلية'));
    });
  });
}
