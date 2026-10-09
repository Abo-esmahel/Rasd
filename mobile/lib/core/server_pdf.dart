import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'strings.dart';

enum ServerPdfKind { note, submission, report }

extension ServerPdfKindPath on ServerPdfKind {
  String get base => switch (this) {
    ServerPdfKind.note => '/notes',
    ServerPdfKind.submission => '/general-submissions',
    ServerPdfKind.report => '/reports',
  };
}

class ServerPdfException implements Exception {
  final String message;
  const ServerPdfException(this.message);
  @override
  String toString() => message;
}

class ServerPdf {
  static final Map<String, Uint8List> _memo = {};
  static const int _memoMax = 6;

  static String _memoKey(ServerPdfKind kind, int id, String lang) =>
      '${kind.name}:$id:$lang';

  static void clearMemo() => _memo.clear();
  static Future<Uint8List> fetch(
    WidgetRef ref,
    ServerPdfKind kind,
    int id, {
    void Function(String stage)? onStage,
    int maxPolls = 20,
  }) {
    return fetchWith(
      dio: ref.read(dioProvider),
      lang: ref.read(localeProvider),
      kind: kind,
      id: id,
      onStage: onStage,
      maxPolls: maxPolls,
    );
  }

  static Future<Uint8List> fetchWith({
    required Dio dio,
    required String lang,
    required ServerPdfKind kind,
    required int id,
    void Function(String stage)? onStage,
    int maxPolls = 20,
  }) async {
    final key = _memoKey(kind, id, lang);
    final cached = _memo[key];
    if (cached != null) {
      onStage?.call('ready');
      return cached;
    }

    for (var i = 0; i < maxPolls; i++) {
      onStage?.call('generating');
      try {
        final res = await dio.get(
          '${kind.base}/$id/pdf',
          queryParameters: {'locale': lang},
          options: Options(
            responseType: ResponseType.bytes,
            receiveTimeout: const Duration(seconds: 180),
            validateStatus: (s) => s != null && (s >= 200 && s < 300),
          ),
        );
        final bytes = _asBytes(res.data);
        if (bytes != null && _isPdf(bytes)) {
          _remember(key, bytes);
          return bytes;
        }
        if (res.statusCode == 202) {
          await Future<void>.delayed(const Duration(seconds: 2));
          continue;
        }
        final message = _bodyMessage(res.data);
        if (message.isNotEmpty) {
          throw ServerPdfException(message);
        }
        throw ServerPdfException(trOf(lang, 'print_failed'));
      } on DioException catch (e) {
        throw ServerPdfException(_friendly(lang, e));
      }
    }
    throw ServerPdfException(trOf(lang, 'print_took_long'));
  }

  static void _remember(String key, Uint8List bytes) {
    _memo[key] = bytes;
    while (_memo.length > _memoMax && _memo.isNotEmpty) {
      _memo.remove(_memo.keys.first);
    }
  }

  static Future<Uint8List> zipOriginals(
    WidgetRef ref,
    ServerPdfKind kind,
    int id,
  ) async {
    final dio = ref.read(dioProvider);
    try {
      final res = await dio.get(
        '${kind.base}/$id/attachments.zip',
        options: Options(
          responseType: ResponseType.bytes,
          receiveTimeout: const Duration(seconds: 180),
        ),
      );
      final data = res.data;
      if (data is List<int> && data.isNotEmpty) {
        return Uint8List.fromList(data);
      }
      throw ServerPdfException(tr(ref, 'print_failed'));
    } on DioException catch (e) {
      throw ServerPdfException(_friendly(ref.read(localeProvider), e));
    }
  }

  static Uint8List? _asBytes(Object? data) =>
      data is List<int> ? Uint8List.fromList(data) : null;

  static bool _isPdf(Uint8List b) =>
      b.length >= 5 &&
      b[0] == 0x25 &&
      b[1] == 0x50 &&
      b[2] == 0x44 &&
      b[3] == 0x46 &&
      b[4] == 0x2D;

  static String _friendly(String lang, DioException e) {
    final code = e.response?.statusCode;
    if (code == 401) return trOf(lang, 'login');
    if (code == 403) return trOf(lang, 'forbidden');
    if (code == 404) return trOf(lang, 'not_found');
    if (code == 422) {
      final m = _bodyMessage(e.response?.data);
      if (m.isNotEmpty) return m;
      return trOf(lang, 'export_unavailable');
    }
    if (e.type == DioExceptionType.connectionError ||
        e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.sendTimeout) {
      return trOf(lang, 'offline_banner');
    }
    final m = _bodyMessage(e.response?.data);
    if (m.isNotEmpty) return m;
    return trOf(lang, 'print_failed');
  }

  static String _bodyMessage(Object? body) {
    Object? d = body;
    if (d is List<int>) {
      try {
        d = jsonDecode(utf8.decode(d));
      } catch (_) {
        return '';
      }
    }
    if (d is Map) {
      final m = (d['message'] ?? '').toString().trim();
      if (m.isNotEmpty && m.length <= 200 && !m.startsWith('<')) return m;
    }
    return '';
  }
}
