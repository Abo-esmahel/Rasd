import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../storage.dart';

typedef UnauthorizedHandler = Future<void> Function();
UnauthorizedHandler? onUnauthorized;

bool _handling401 = false;

class AuthInterceptor extends Interceptor {
  @override
  void onRequest(
      RequestOptions options, RequestInterceptorHandler handler) async {
    try {
      final token = await SecureStore.token;
      if (token != null && token.isNotEmpty) {
        options.headers['Authorization'] = 'Bearer $token';
      }
    } catch (_) {}
    handler.next(options);
  }

  @override
  void onError(
      DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401 &&
        onUnauthorized != null &&
        !_handling401) {
      _handling401 = true;
      try {
        await onUnauthorized!.call();
      } catch (_) {
      } finally {
        _handling401 = false;
      }
    }
    handler.next(err);
  }
}

const _sensitiveKeys = {
  'authorization',
  'password',
  'password_confirmation',
  'token',
  'current_password',
};

class RedactedLogInterceptor extends Interceptor {
  @override
  void onRequest(
      RequestOptions options, RequestInterceptorHandler handler) {
    if (kDebugMode) {
      final headers = Map<String, dynamic>.from(options.headers)
        ..updateAll((k, v) =>
            _sensitiveKeys.contains(k.toString().toLowerCase())
                ? '***'
                : v);
      debugPrint(
          '--> ${options.method} ${options.uri} headers=$headers');
      final data = options.data;
      if (data is Map) {
        final redacted = Map<String, dynamic>.from(data)
          ..updateAll((k, v) =>
              _sensitiveKeys.contains(k.toString().toLowerCase())
                  ? '***'
                  : (v is FormData ? '<multipart>' : v));
        debugPrint('    body=$redacted');
      } else if (data is FormData) {
        debugPrint(
            '    body=<multipart fields=${data.fields.length} files=${data.files.length}>');
      }
    }
    handler.next(options);
  }

  @override
  void onResponse(
      Response response, ResponseInterceptorHandler handler) {
    if (kDebugMode) {
      debugPrint(
          '<-- ${response.statusCode} ${response.requestOptions.uri}');
    }
    handler.next(response);
  }

  @override
  void onError(
      DioException err, ErrorInterceptorHandler handler) {
    if (kDebugMode) {
      debugPrint(
          '<-- ERROR ${err.response?.statusCode} ${err.requestOptions.uri} ${err.type}');
    }
    handler.next(err);
  }
}
