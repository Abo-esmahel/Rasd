import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/interceptors.dart';
import '../../core/biometric_lock.dart';
import '../../core/models.dart';
import '../../core/notify_sound.dart';
import '../../core/offline_cache.dart';
import '../../core/storage.dart';
import '../notifications/notifications_repo.dart';
import 'auth_repo.dart';

final authProvider = StateNotifierProvider<AuthNotifier, AsyncValue<User?>>((
  ref,
) {
  return AuthNotifier(ref);
});

class AuthNotifier extends StateNotifier<AsyncValue<User?>> {
  final Ref _ref;
  AuthNotifier(this._ref) : super(const AsyncValue.loading()) {
    onUnauthorized = () async {
      await SecureStore.setToken(null);
      _ref.read(offlineStatusProvider.notifier).state = false;
      _ref.invalidate(biometricLockProvider);
      state = const AsyncValue.data(null);
    };
  }

  Future<bool> restore() async {
    final token = await SecureStore.token;
    if (token == null || token.isEmpty) {
      state = const AsyncValue.data(null);
      return false;
    }
    state = const AsyncValue.loading();
    try {
      final user = await _ref.read(authRepoProvider).me();
      try {
        await SecureStore.setCachedUser(jsonEncode(user.toJson()));
      } catch (_) {}
      state = AsyncValue.data(user);
      return true;
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        await SecureStore.setToken(null);
        _ref.read(offlineStatusProvider.notifier).state = false;
        _ref.invalidate(authTokenProvider);
        _ref.invalidate(biometricLockProvider);
        state = const AsyncValue.data(null);
        return false;
      }
      if (e.statusCode == null || e.statusCode! >= 500) {
        String? raw;
        try {
          raw = await SecureStore.cachedUser();
        } catch (_) {}
        if (raw != null) {
          try {
            final user = User.fromJson(
              Map<String, dynamic>.from(jsonDecode(raw) as Map),
            );
            _ref.read(offlineStatusProvider.notifier).state = true;
            state = AsyncValue.data(user);
            return true;
          } catch (_) {
            await SecureStore.setCachedUser(null);
          }
        }
        final offlineUser = _offlineUserFromToken(token);
        if (offlineUser != null) {
          _ref.read(offlineStatusProvider.notifier).state = true;
          state = AsyncValue.data(offlineUser);
          return true;
        }
      }
      rethrow;
    }
  }

  Future<String?> login(String username, String password) async {
    final previous = state;
    state = const AsyncValue.loading();
    try {
      final user = await _ref.read(authRepoProvider).login(username, password);
      state = AsyncValue.data(user);
      _ref.invalidate(authTokenProvider);
      _ref.read(authRepoProvider).registerDevice();
      return null;
    } on ApiException catch (e) {
      if (e.statusCode == 401 || e.statusCode == 422) {
        state = const AsyncValue.data(null);
      } else {
        state = previous;
      }
      return e.toString();
    } on Exception catch (e) {
      state = previous;
      return e.toString();
    }
  }

  void setUser(User user) {
    state = AsyncValue.data(user);
    SecureStore.setCachedUser(jsonEncode(user.toJson())).catchError((_) {});
  }

  Future<void> logout() async {
    await _ref.read(authRepoProvider).logout();
    try {
      await NotifySound.cancelAll();
    } catch (_) {}
    state = const AsyncValue.data(null);
    _ref.read(offlineStatusProvider.notifier).state = false;
    _ref.invalidate(authTokenProvider);
    _ref.invalidate(biometricLockProvider);
    try {
      _ref.invalidate(unreadCountProvider);
    } catch (_) {}
    try {
      _ref.invalidate(notificationsListProvider);
    } catch (_) {}
  }

  User? _offlineUserFromToken(String token) {
    try {
      final parts = token.split('.');
      if (parts.length != 3) return null;
      final payload = jsonDecode(
        utf8.decode(base64Url.decode(base64Url.normalize(parts[1]))),
      );
      if (payload is! Map) return null;
      final claims = Map<String, dynamic>.from(payload);
      final id = int.tryParse(claims['sub']?.toString() ?? '');
      final name = claims['name']?.toString().trim() ?? '';
      final role = claims['role']?.toString() ?? '';
      if (id == null ||
          id <= 0 ||
          name.isEmpty ||
          !{'monitor', 'report_writer', 'general_manager'}.contains(role)) {
        return null;
      }
      return User(id: id, name: name, username: '', role: role);
    } catch (_) {
      return null;
    }
  }
}
