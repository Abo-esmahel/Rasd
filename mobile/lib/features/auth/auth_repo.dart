import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../core/api_client.dart';
import '../../core/models.dart';
import '../../core/storage.dart';

final authRepoProvider = Provider<AuthRepo>((ref) {
  return AuthRepo(ref.watch(dioProvider));
});

class AuthRepo {
  final Dio _dio;
  AuthRepo(this._dio);

  Future<User> login(String username, String password) async {
    try {
      final res = await _dio.post('/login', data: {
        'username': username.trim(),
        'password': password,
      });
      final data = Map<String, dynamic>.from(res.data['data'] as Map);
      final user = User.fromJson(Map<String, dynamic>.from(data['user'] as Map));
      await SecureStore.setToken(null);
      await SecureStore.setToken(data['token']?.toString());
      try {
        await SecureStore.setCachedUser(jsonEncode(user.toJson()));
      } catch (_) {}
      return user;
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<User> me() async {
    try {
      final res = await _dio.get('/me');
      return User.fromJson(
          Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<User> updateProfile({
    required String name,
    required String username,
    String? personalNumber,
    String? avatarPath,
    bool removeAvatar = false,
    String? currentPassword,
    String? newPassword,
  }) async {
    try {
      late final dynamic payload;
      if (avatarPath != null && avatarPath.isNotEmpty) {
        payload = FormData.fromMap({
          'name': name.trim(),
          'username': username.trim(),
          if (personalNumber != null)
            'personal_number': personalNumber.trim(),
          if (removeAvatar) 'remove_avatar': '1',
          if (currentPassword != null &&
              currentPassword.isNotEmpty)
            'current_password': currentPassword,
          if (newPassword != null && newPassword.isNotEmpty) ...{
            'password': newPassword,
            'password_confirmation': newPassword,
          },
          'avatar': await MultipartFile.fromFile(avatarPath,
              filename: avatarPath.split(RegExp(r'[/\\]')).last),
        });
      } else {
        payload = {
          'name': name.trim(),
          'username': username.trim(),
          if (personalNumber != null)
            'personal_number': personalNumber.trim(),
          if (removeAvatar) 'remove_avatar': true,
          if (currentPassword != null &&
              currentPassword.isNotEmpty)
            'current_password': currentPassword,
          if (newPassword != null && newPassword.isNotEmpty) ...{
            'password': newPassword,
            'password_confirmation': newPassword,
          },
        };
      }
      final res = await _dio.post('/me', data: payload);
      return User.fromJson(
          Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> logout() async {
    try {
      final device = await Prefs.deviceToken();
      if (device != null && device.isNotEmpty) {
        await _dio.post('/devices/unregister',
            data: {'token': device});
      }
    } catch (_) {}
    try {
      await _dio.post('/logout');
    } catch (_) {}
    await SecureStore.setToken(null);
    await Prefs.setDeviceToken(null);
  }

  Future<void> registerDevice() async {
    try {
      var token = await Prefs.deviceToken();
      token ??= const Uuid().v4();
      await _dio.post('/devices/register', data: {
        'token': token,
        'platform': defaultTargetPlatform.name,
        'provider': 'polling',
      });
      await Prefs.setDeviceToken(token);
    } catch (_) {}
  }
}
