import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';

final managedUsersProvider =
    Provider<ManagedUsersRepo>((ref) => ManagedUsersRepo(ref.watch(dioProvider)));

enum UsersFilter { all, writers, monitors, banned }

class ManagedUser {
  final int id;
  final String name;
  final String username;
  final String role;
  final String? personalNumber;
  final String? avatarUrl;
  final bool banned;
  final String? banReason;
  final String? bannedAt;

  const ManagedUser({
    required this.id,
    required this.name,
    required this.username,
    required this.role,
    this.personalNumber,
    this.avatarUrl,
    this.banned = false,
    this.banReason,
    this.bannedAt,
  });

  bool get isWriter => role == 'report_writer';
  bool get isMonitor => role == 'monitor';

  String get initial => name.trim().isEmpty ? '?' : name.trim()[0];

  static int _int(dynamic v) =>
      v is int ? v : int.tryParse('${v ?? 0}') ?? 0;

  factory ManagedUser.fromJson(Map<String, dynamic> j) => ManagedUser(
        id: _int(j['id']),
        name: (j['name'] ?? j['username'] ?? '?').toString(),
        username: (j['username'] ?? '').toString(),
        role: (j['role'] ?? 'monitor').toString(),
        personalNumber: j['personal_number']?.toString(),
        avatarUrl: j['avatar_url']?.toString(),
        banned: j['banned'] == true,
        banReason: j['ban_reason']?.toString(),
        bannedAt: j['banned_at']?.toString(),
      );
}

class ManagedUsersRepo {
  final Dio _dio;
  ManagedUsersRepo(this._dio);

  Future<List<ManagedUser>> list({
    String query = '',
    UsersFilter filter = UsersFilter.all,
  }) async {
    try {
      final res = await _dio.get('/managed-users', queryParameters: {
        if (query.trim().isNotEmpty) 'q': query.trim(),
        if (filter == UsersFilter.writers) 'role': 'report_writer',
        if (filter == UsersFilter.monitors) 'role': 'monitor',
        if (filter == UsersFilter.banned) 'banned': '1',
      });
      final data = unwrapData(res);
      if (data is! List) return const [];
      return data
          .whereType<Map<String, dynamic>>()
          .map(ManagedUser.fromJson)
          .toList();
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<ManagedUser> create({
    required String name,
    required String username,
    required String password,
    required String role,
    String? personalNumber,
  }) async {
    try {
      final res = await _dio.post('/managed-users', data: {
        'name': name.trim(),
        'username': username.trim(),
        'password': password,
        'role': role,
        if (personalNumber != null && personalNumber.trim().isNotEmpty)
          'personal_number': personalNumber.trim(),
      });
      return ManagedUser.fromJson(
          Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<ManagedUser> update(
    int id, {
    required String name,
    required String username,
    required String role,
    String? personalNumber,
  }) async {
    try {
      final res = await _dio.put('/managed-users/$id', data: {
        'name': name.trim(),
        'username': username.trim(),
        'role': role,
        'personal_number': (personalNumber ?? '').trim(),
      });
      return ManagedUser.fromJson(
          Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<ManagedUser> ban(int id, {String? reason}) async {
    try {
      final res = await _dio.post('/managed-users/$id/ban', data: {
        if (reason != null && reason.trim().isNotEmpty)
          'reason': reason.trim(),
      });
      return ManagedUser.fromJson(
          Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<ManagedUser> unban(int id) async {
    try {
      final res = await _dio.post('/managed-users/$id/unban');
      return ManagedUser.fromJson(
          Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }
}
