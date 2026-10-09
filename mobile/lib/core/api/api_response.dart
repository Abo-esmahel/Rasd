import 'package:dio/dio.dart';

class ApiEnvelope {
  final bool success;
  final String message;
  final dynamic data;
  ApiEnvelope({
    required this.success,
    required this.message,
    required this.data,
  });

  factory ApiEnvelope.fromResponse(Response res) {
    final body = res.data;
    if (body is Map) {
      final m = Map<String, dynamic>.from(body);
      return ApiEnvelope(
        success: m['success'] != false,
        message: (m['message'] ?? '').toString(),
        data: m.containsKey('data') ? m['data'] : body,
      );
    }
    return ApiEnvelope(success: true, message: '', data: body);
  }
}

class Paged<T> {
  final List<T> items;
  final int page;
  final int lastPage;
  final int total;
  Paged({
    required this.items,
    required this.page,
    required this.lastPage,
    required this.total,
  });

  bool get hasMore => page < lastPage;

  static Paged<T> fromJson<T>(
    dynamic json,
    T Function(Map<String, dynamic>) parse,
  ) {
    if (json is Map) {
      final m = Map<String, dynamic>.from(json);
      final raw = (m['data'] is List) ? m['data'] as List : const [];
      return Paged<T>(
        items: raw
            .whereType<Map<String, dynamic>>()
            .map(parse)
            .toList(),
        page: _int(m['current_page'], fallback: 1),
        lastPage: _int(m['last_page'], fallback: 1),
        total: _int(m['total']),
      );
    }
    if (json is List) {
      return Paged<T>(
        items: json
            .whereType<Map<String, dynamic>>()
            .map(parse)
            .toList(),
        page: 1,
        lastPage: 1,
        total: json.length,
      );
    }
    return Paged<T>(items: const [], page: 1, lastPage: 1, total: 0);
  }

  static int _int(dynamic v, {int fallback = 0}) {
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse(v?.toString() ?? '') ?? fallback;
  }
}
