import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'storage.dart';

final offlineStatusProvider = StateProvider<bool>((ref) => false);

class OfflineResponseCache extends Interceptor {
  static const _fromCache = 'rasd_offline_cache_hit';
  static const _maxEntries = 60;
  static const _maxEntryBytes = 180 * 1024;
  static const _maxCacheBytes = 900 * 1024;
  static const _maxAge = Duration(days: 60);
  static Future<void> _writeTail = Future<void>.value();

  final Ref ref;
  OfflineResponseCache(this.ref);

  @override
  Future<void> onResponse(
    Response response,
    ResponseInterceptorHandler handler,
  ) async {
    if (response.extra[_fromCache] == true) {
      ref.read(offlineStatusProvider.notifier).state = true;
      handler.next(response);
      return;
    }

    ref.read(offlineStatusProvider.notifier).state = false;
    if (_isCacheable(response.requestOptions) &&
        response.statusCode != null &&
        response.statusCode! >= 200 &&
        response.statusCode! < 300) {
      await _save(response.requestOptions, response.data);
    }
    handler.next(response);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final status = err.response?.statusCode;
    final outage = status != null
        ? status >= 500
        : switch (err.type) {
            DioExceptionType.connectionError ||
            DioExceptionType.connectionTimeout ||
            DioExceptionType.receiveTimeout ||
            DioExceptionType.sendTimeout ||
            DioExceptionType.unknown => true,
            _ => false,
          };
    if (outage) {
      ref.read(offlineStatusProvider.notifier).state = true;
    } else if (status != null) {
      ref.read(offlineStatusProvider.notifier).state = false;
    }

    if (outage && _isCacheable(err.requestOptions)) {
      final entry = await _read(err.requestOptions);
      if (entry != null) {
        handler.resolve(
          Response<dynamic>(
            requestOptions: err.requestOptions,
            data: entry,
            statusCode: 200,
            extra: {...err.requestOptions.extra, _fromCache: true},
          ),
        );
        return;
      }
    }
    handler.next(err);
  }

  bool _isCacheable(RequestOptions request) {
    if (request.method.toUpperCase() != 'GET' ||
        _requestToken(request) == null ||
        request.responseType == ResponseType.bytes ||
        request.responseType == ResponseType.stream) {
      return false;
    }
    final path = request.uri.path.toLowerCase();
    if (path.endsWith('/me') ||
        path.contains('/download') ||
        path.contains('/attachments/') ||
        path.endsWith('/export')) {
      return false;
    }
    return true;
  }

  String? _requestToken(RequestOptions request) {
    final value = request.headers['Authorization']?.toString() ?? '';
    if (!value.startsWith('Bearer ')) return null;
    return value.substring(7);
  }

  String _key(RequestOptions request) => request.uri.toString();

  Future<Map<String, dynamic>> _all() async {
    try {
      final raw = await SecureStore.apiCache();
      if (raw == null || raw.isEmpty) return <String, dynamic>{};
      final decoded = jsonDecode(raw);
      if (decoded is Map) return Map<String, dynamic>.from(decoded);
    } catch (_) {}
    return <String, dynamic>{};
  }

  Future<dynamic> _read(RequestOptions request) async {
    final requestToken = _requestToken(request);
    if (requestToken == null || await SecureStore.token != requestToken) {
      return null;
    }
    await _writeTail;
    if (await SecureStore.token != requestToken) return null;
    final all = await _all();
    final raw = all[_key(request)];
    if (raw is! Map) return null;
    final entry = Map<String, dynamic>.from(raw);
    final savedAt = int.tryParse(entry['saved_at']?.toString() ?? '');
    if (savedAt == null ||
        DateTime.now().difference(
              DateTime.fromMillisecondsSinceEpoch(savedAt),
            ) >
            _maxAge) {
      return null;
    }
    return entry['data'];
  }

  Future<void> _save(RequestOptions request, dynamic data) async {
    late final String serialized;
    try {
      serialized = jsonEncode(data);
      if (utf8.encode(serialized).length > _maxEntryBytes) return;
    } catch (_) {
      return;
    }

    final previous = _writeTail;
    final done = Completer<void>();
    _writeTail = done.future;
    await previous.catchError((_) {});
    try {
      final requestToken = _requestToken(request);
      if (requestToken == null || await SecureStore.token != requestToken) {
        return;
      }
      final all = await _all();
      all[_key(request)] = {
        'saved_at': DateTime.now().millisecondsSinceEpoch,
        'data': jsonDecode(serialized),
      };

      final entries = all.entries.toList()
        ..sort((a, b) {
          final aTime = (a.value is Map)
              ? int.tryParse((a.value as Map)['saved_at']?.toString() ?? '') ?? 0
              : 0;
          final bTime = (b.value is Map)
              ? int.tryParse((b.value as Map)['saved_at']?.toString() ?? '') ?? 0
              : 0;
          return bTime.compareTo(aTime);
        });

      final kept = <String, dynamic>{};
      var usedBytes = 0;
      for (final entry in entries.take(_maxEntries)) {
        final encoded = jsonEncode(entry.value);
        final size = utf8.encode(encoded).length;
        if (usedBytes + size > _maxCacheBytes) continue;
        kept[entry.key] = entry.value;
        usedBytes += size;
      }
      await SecureStore.setApiCache(jsonEncode(kept));
    } catch (_) {
    } finally {
      done.complete();
    }
  }
}
