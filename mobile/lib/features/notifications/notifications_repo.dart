import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/models.dart';
import '../../core/notify_sound.dart';
import '../../core/storage.dart';

final notificationsRepoProvider = Provider<NotificationsRepo>((ref) {
  return NotificationsRepo(ref.watch(dioProvider));
});

final unreadCountProvider = StateNotifierProvider<UnreadNotifier, int>((ref) {
  return UnreadNotifier(ref);
});

class UnreadNotifier extends StateNotifier<int> {
  final Ref _ref;
  Timer? _timer;
  final Set<String> _announced = {};
  bool _primed = false;
  UnreadNotifier(this._ref) : super(0) {
    refresh();
    _timer = Timer.periodic(const Duration(seconds: 60), (_) => refresh());
  }

  Future<void> refresh() async {
    try {
      final now = await _ref.read(notificationsRepoProvider).unreadCount();
      if (now == null) return;
      state = now;
      try {
        final items = await _ref.read(notificationsRepoProvider).list();
        final fresh = items.where((n) => n.unread).take(5).toList();
        if (!_primed) {
          _announced.addAll(fresh.map((n) => n.id));
          _primed = true;
        } else if (_ref.read(soundEnabledProvider)) {
          for (final n in fresh) {
            if (_announced.add(n.id)) {
              await NotifySound.headsUp(n.id, n.title, n.body);
            }
          }
          if (_announced.length > 200) {
            final drop = _announced.length - 200;
            _announced.removeAll(_announced.take(drop).toList());
          }
        }
      } catch (_) {}
    } catch (_) {}
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }
}

final soundEnabledProvider = StateProvider<bool>((ref) => true);

Future<void> loadSoundPref(Ref ref) async {
  try {
    final v = await Prefs.sound();
    if (v != null) {
      ref.read(soundEnabledProvider.notifier).state = v;
    }
  } catch (_) {}
}

final notificationsListProvider =
    StateNotifierProvider<
      NotificationsNotifier,
      AsyncValue<List<AppNotification>>
    >((ref) {
      return NotificationsNotifier(ref);
    });

class NotificationsNotifier
    extends StateNotifier<AsyncValue<List<AppNotification>>> {
  final Ref _ref;
  final Set<String> _seen = {};
  String? _cursor;

  NotificationsNotifier(this._ref) : super(const AsyncValue.loading()) {
    refresh();
  }

  Future<void> refresh() async {
    final keep = state.valueOrNull;
    if (keep == null) state = const AsyncValue.loading();
    try {
      final items = await _ref.read(notificationsRepoProvider).list();
      _seen.addAll(items.map((n) => n.id));
      _cursor = items.isNotEmpty
          ? items.first.createdAt?.toIso8601String()
          : null;
      state = AsyncValue.data(items);
      _ref.read(unreadCountProvider.notifier).refresh();
    } catch (e, st) {
      if (keep == null) state = AsyncValue.error(e, st);
    }
  }

  Future<void> pollTick() async {
    try {
      final fresh = await _ref
          .read(notificationsRepoProvider)
          .feed(after: _cursor);
      if (fresh.isEmpty) return;
      final known = _seen;
      final unseen = fresh.where((n) => !known.contains(n.id)).toList();
      if (unseen.isEmpty) return;
      _seen.addAll(unseen.map((n) => n.id));
      _cursor = unseen.first.createdAt?.toIso8601String() ?? _cursor;
      final cur = state.valueOrNull ?? const [];
      state = AsyncValue.data([...unseen, ...cur]);
      _ref.read(unreadCountProvider.notifier).refresh();
    } catch (_) {}
  }

  Future<void> markAllRead() async {
    try {
      await _ref.read(notificationsRepoProvider).markAllRead();
      await NotifySound.cancelAll();
      await refresh();
    } catch (_) {}
  }

  Future<void> markSelected(Set<String> ids) async {
    if (ids.isEmpty) return;
    try {
      await _ref.read(notificationsRepoProvider).markSelected(ids);
      for (final id in ids) {
        await NotifySound.cancelId(id);
      }
      final cur = state.valueOrNull;
      if (cur != null) {
        state = AsyncValue.data(
          cur
              .map(
                (n) => ids.contains(n.id)
                    ? AppNotification(
                        id: n.id,
                        title: n.title,
                        body: n.body,
                        unread: false,
                        type: n.type,
                        priority: n.priority,
                        category: n.category,
                        humanTime: n.humanTime,
                        createdAt: n.createdAt,
                        data: n.data,
                      )
                    : n,
              )
              .toList(),
        );
      }
      _ref.read(unreadCountProvider.notifier).refresh();
    } catch (_) {}
  }

  Future<void> markOneRead(String id) async {
    try {
      await _ref.read(notificationsRepoProvider).markOneRead(id);
      await NotifySound.cancelId(id);
      final cur = state.valueOrNull;
      if (cur != null) {
        state = AsyncValue.data(
          cur
              .map(
                (n) => n.id == id
                    ? AppNotification(
                        id: n.id,
                        title: n.title,
                        body: n.body,
                        unread: false,
                        type: n.type,
                        priority: n.priority,
                        category: n.category,
                        humanTime: n.humanTime,
                        createdAt: n.createdAt,
                        data: n.data,
                      )
                    : n,
              )
              .toList(),
        );
      }
      _ref.read(unreadCountProvider.notifier).refresh();
    } catch (_) {}
  }
}

class NotificationsRepo {
  final Dio _dio;
  NotificationsRepo(this._dio);

  Future<List<AppNotification>> list() async {
    try {
      final res = await _dio.get(
        '/notifications',
        queryParameters: {'per_page': 30},
      );
      final body = res.data as Map;
      final paged = Paged.fromJson<AppNotification>(
        body['data'],
        (j) => AppNotification.fromJson(j),
      );
      return paged.items;
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<int?> unreadCount() async {
    try {
      final res = await _dio.get('/notifications/unread-count');
      final body = Map<String, dynamic>.from(res.data as Map);
      final raw = body['unread_count'] ?? body['count'];
      if (raw is num) return raw.toInt();
      if (raw is Map) {
        final value = raw['count'] ?? raw['unread_count'];
        if (value is num) return value.toInt();
        return int.tryParse(value?.toString() ?? '');
      }
      final data = body['data'];
      if (data is Map) {
        final value = data['count'] ?? data['unread_count'];
        if (value is num) return value.toInt();
        return int.tryParse(value?.toString() ?? '');
      }
      return int.tryParse(raw?.toString() ?? '');
    } catch (_) {
      return null;
    }
  }

  Future<void> markAllRead() async {
    try {
      await _dio.post('/notifications/mark-read');
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> markSelected(Set<String> ids) async {
    try {
      await _dio.post('/notifications/mark-read', data: {'ids': ids.toList()});
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<List<AppNotification>> feed({
    String? after,
    String? lastId,
    int limit = 20,
    bool unreadOnly = false,
  }) async {
    try {
      final res = await _dio.get(
        '/notifications/feed',
        queryParameters: {
          if (after != null) 'after': after,
          if (lastId != null) 'last_id': lastId,
          'limit': limit.clamp(1, 50),
          if (unreadOnly) 'unread_only': 1,
        },
      );
      final body = res.data as Map;
      final raw = body['data'];
      if (raw is List) {
        return raw
            .whereType<Map<String, dynamic>>()
            .map(AppNotification.fromJson)
            .toList();
      }
      final notifs = body['notifications'];
      if (notifs is List) {
        return notifs
            .whereType<Map<String, dynamic>>()
            .map(AppNotification.fromJson)
            .toList();
      }
      return const [];
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> markOneRead(String id) async {
    try {
      await _dio.post('/notifications/$id/read');
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }
}
