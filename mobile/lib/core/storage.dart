import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class SecureStore {
  static const _storage = FlutterSecureStorage();
  static const _kToken = 'jwt_token';
  static const _kCachedUser = 'cached_user_v1';
  static const _kApiCache = 'api_cache_v1';
  static const _kBiometricLock = 'biometric_lock_v1';

  static Future<String?> get token => _storage.read(key: _kToken);
  static Future<void> setToken(String? token) async {
    if (token == null || token.isEmpty) {
      await clearSessionData();
    } else {
      await _storage.write(key: _kToken, value: token);
    }
  }

  static Future<String?> cachedUser() => _storage.read(key: _kCachedUser);
  static Future<void> setCachedUser(String? value) async {
    if (value == null || value.isEmpty) {
      await _storage.delete(key: _kCachedUser);
    } else {
      await _storage.write(key: _kCachedUser, value: value);
    }
  }

  static Future<String?> apiCache() => _storage.read(key: _kApiCache);
  static Future<void> setApiCache(String? value) async {
    if (value == null || value.isEmpty) {
      await _storage.delete(key: _kApiCache);
    } else {
      await _storage.write(key: _kApiCache, value: value);
    }
  }

  static Future<void> clearSessionData() async {
    await Future.wait([
      _storage.delete(key: _kToken),
      _storage.delete(key: _kCachedUser),
      _storage.delete(key: _kApiCache),
      _storage.delete(key: _kBiometricLock),
    ]);
  }

  static Future<bool> biometricLockEnabled() async =>
      await _storage.read(key: _kBiometricLock) == '1';
  static Future<void> setBiometricLockEnabled(bool enabled) async {
    if (enabled) {
      await _storage.write(key: _kBiometricLock, value: '1');
    } else {
      await _storage.delete(key: _kBiometricLock);
    }
  }
}

class Prefs {
  static Future<SharedPreferences> get _p async =>
      SharedPreferences.getInstance();

  static Future<String?> locale() async => (await _p).getString('locale');
  static Future<void> setLocale(String v) async =>
      (await _p).setString('locale', v);

  static Future<String?> themeMode() async =>
      (await _p).getString('theme_mode');
  static Future<void> setThemeMode(String v) async =>
      (await _p).setString('theme_mode', v);

  static Future<bool?> sound() async {
    final p = await _p;
    return p.containsKey('notif_sound')
        ? p.getBool('notif_sound')
        : null;
  }

  static Future<void> setSound(bool v) async =>
      (await _p).setBool('notif_sound', v);

  static Future<String?> deviceToken() async =>
      (await _p).getString('device_token');
  static Future<int?> avatarVersion() async =>
      (await _p).getInt('avatar_v');
  static Future<void> setAvatarVersion(int v) async =>
      (await _p).setInt('avatar_v', v);

  static Future<void> setDeviceToken(String? v) async {
    final p = await _p;
    if (v == null) {
      await p.remove('device_token');
    } else {
      await p.setString('device_token', v);
    }
  }
}
