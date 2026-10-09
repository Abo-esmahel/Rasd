import 'package:dio/dio.dart';

class ApiLang {
  static String lang = 'ar';
  static String t(String ar, String en) => lang == 'en' ? en : ar;
}

class ApiException implements Exception {
  final String message;
  final int? statusCode;

  final Map<String, List<String>> fieldErrors;

  final String? code;
  final bool retryable;
  final int? retryAfter;

  final String userMessage;

  ApiException({
    required this.message,
    this.statusCode,
    this.fieldErrors = const {},
    this.code,
    this.retryable = false,
    this.retryAfter,
    String? userMessage,
  }) : userMessage = userMessage ?? message;

  String? firstFieldError(String field) {
    final v = fieldErrors[field];
    return (v == null || v.isEmpty) ? null : v.first;
  }

  @override
  String toString() => userMessage;

  factory ApiException.fromDio(DioException e) {
    final res = e.response;
    final status = res?.statusCode;
    String serverMsg = '';
    final fields = <String, List<String>>{};
    String? code;
    var retryable = false;
    int? retryAfter;

    final body = res?.data;
    if (body is Map) {
      final m = Map<String, dynamic>.from(body);
      if (m['message'] is String) serverMsg = m['message'] as String;
      final errs = m['errors'];
      if (errs is Map) {
        for (final en in errs.entries) {
          final v = en.value;
          fields[en.key.toString()] = v is List
              ? v.map((x) => x.toString()).toList()
              : [v.toString()];
        }
      }
      final err = m['error'];
      if (err is Map) {
        final em = Map<String, dynamic>.from(err);
        code = em['code']?.toString();
        retryable = em['retryable'] == true;
        final ra = em['retry_after'];
        retryAfter =
            ra is int ? ra : int.tryParse(ra?.toString() ?? '');
      }
    }
    retryAfter ??= _headerRetryAfter(res?.headers);

    if (serverMsg.isEmpty && fields.isNotEmpty) {
      serverMsg = fields.values.first.first;
    }

    final userMessage = _messageFor(
      status,
      e.type,
      serverMsg,
      code: code,
      retryable: retryable,
      retryAfter: retryAfter,
    );
    return ApiException(
      message: serverMsg.isEmpty ? userMessage : serverMsg,
      statusCode: status,
      fieldErrors: fields,
      code: code,
      retryable: retryable,
      retryAfter: retryAfter,
      userMessage: userMessage,
    );
  }

  static int? _headerRetryAfter(Headers? h) {
    if (h == null) return null;
    for (final k in ['retry-after', 'Retry-After']) {
      final v = h[k];
      if (v != null && v.isNotEmpty) return int.tryParse(v.first);
    }
    return null;
  }

  static String _messageFor(
    int? status,
    DioExceptionType type,
    String serverMsg, {
    String? code,
    bool retryable = false,
    int? retryAfter,
  }) {
    if (code != null && code.startsWith('AI_')) {
      return _aiMessage(code, serverMsg, retryAfter);
    }
    switch (status) {
      case 401:
        return ApiLang.t('انتهت جلسة الدخول — يرجى تسجيل الدخول مرة أخرى', 'Session expired — please log in again');
      case 403:
        return serverMsg.isEmpty
            ? ApiLang.t('ليس لديك صلاحية لتنفيذ هذه العملية', 'You are not allowed to perform this action')
            : serverMsg;
      case 404:
        return ApiLang.t('العنصر غير موجود أو لم يعد متاحاً', 'Item not found or no longer available');
      case 409:
        return serverMsg.isEmpty
            ? ApiLang.t('العملية غير متاحة حالياً (تعارض)', 'Operation currently unavailable (conflict)')
            : serverMsg;
      case 422:
        return serverMsg.isEmpty
            ? ApiLang.t('راجع الحقول المدخلة ثم أعد المحاولة', 'Check the entered fields and retry')
            : serverMsg;
      case 429:
        if (retryAfter != null && retryAfter > 0) {
          return ApiLang.t('تم تجاوز الحد المسموح — حاول بعد $retryAfter ثانية', 'Rate limit exceeded — retry after $retryAfter seconds');
        }
        return ApiLang.t('تم تجاوز الحد المسموح — انتظر قليلاً ثم أعد المحاولة', 'Rate limit exceeded — wait a little and retry');
      case 500:
      case 502:
      case 503:
        return ApiLang.t('حدث خطأ في الخادم — حاول مرة أخرى', 'Server error — please retry');
    }
    if (type == DioExceptionType.connectionTimeout ||
        type == DioExceptionType.receiveTimeout ||
        type == DioExceptionType.sendTimeout) {
      return ApiLang.t('انتهت مهلة الاتصال — تأكد أن الهاتف متصل بشبكة رصد المحلية', 'Connection timed out — ensure the phone is on the Rasd local network');
    }
    if (type == DioExceptionType.connectionError) {
      return ApiLang.t('تعذر الاتصال بالخادم — تأكد أن الهاتف متصل بشبكة رصد المحلية', 'Cannot reach the server — ensure the phone is on the Rasd local network');
    }
    return serverMsg.isEmpty
        ? ApiLang.t('حدث خطأ غير متوقع — حاول مرة أخرى', 'Unexpected error — please retry')
        : serverMsg;
  }

  static String _aiMessage(
      String code, String serverMsg, int? retryAfter) {
    final wait = (retryAfter != null && retryAfter > 0)
        ? ApiLang.t(' (بعد $retryAfter ثانية)', ' (after $retryAfter seconds)')
        : '';
    switch (code) {
      case 'AI_NOT_CONFIGURED':
        return ApiLang.t('الخدمة الذكية غير مهيأة على الخادم', 'Smart service is not configured on the server');
      case 'AI_DISABLED':
        return ApiLang.t('الخدمة الذكية معطلة حالياً', 'Smart service is currently disabled');
      case 'AI_AUTH':
        return ApiLang.t('تعذر التحقق من الخدمة الذكية', 'Could not authenticate the smart service');
      case 'AI_RATE_LIMITED':
        return ApiLang.t('ضغط على الخدمة الذكية — أعد المحاولة$wait', 'Smart service is busy — retry$wait');
      case 'AI_UNAVAILABLE':
        return ApiLang.t('الخدمة الذكية غير متاحة الآن — أعد المحاولة$wait', 'Smart service unavailable — retry$wait');
      case 'AI_BAD_RESPONSE':
        return ApiLang.t('رد غير صالح من الخدمة الذكية — أعد المحاولة', 'Invalid response from smart service — retry');
      case 'AI_BUSY':
        return ApiLang.t('توليد آخر جارٍ — انتظر ثم أعد المحاولة', 'Another generation is running — wait and retry');
      case 'AI_VALIDATION':
        return serverMsg.isEmpty
            ? ApiLang.t('تعذر التوليد — راجع البيانات', 'Could not generate — check the data')
            : serverMsg;
      default:
        return serverMsg.isEmpty
            ? ApiLang.t('فشل التوليد الذكي', 'Smart generation failed')
            : serverMsg;
    }
  }
}
