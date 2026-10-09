import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config.dart';
import '../offline_cache.dart';
import '../storage.dart';
import 'api_exception.dart';
import 'interceptors.dart';

export 'api_exception.dart';
export 'api_response.dart';

final authTokenProvider = FutureProvider<String?>((ref) async {
  try {
    return await SecureStore.token;
  } catch (_) {
    return null;
  }
});

final authImageHeadersProvider = Provider<Map<String, String>>((ref) {
  final token = ref.watch(authTokenProvider).valueOrNull;
  if (token == null || token.isEmpty) return const {};
  return {'Authorization': 'Bearer $token'};
});

final dioProvider = Provider<Dio>((ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: '${ref.watch(baseUrlProvider)}/api',
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 60),
      sendTimeout: const Duration(minutes: 5),
      headers: const {'Accept': 'application/json'},
    ),
  );
  dio.interceptors.addAll([
    AuthInterceptor(),
    OfflineResponseCache(ref),
    RedactedLogInterceptor(),
  ]);
  return dio;
});

ApiException toApiException(DioException e) => ApiException.fromDio(e);

dynamic unwrapData(Response res) {
  final body = res.data;
  if (body is Map && body.containsKey('data')) return body['data'];
  return body;
}

extension FirstOrNullX<E> on Iterable<E> {
  E? get firstOrNull => isEmpty ? null : first;
}
