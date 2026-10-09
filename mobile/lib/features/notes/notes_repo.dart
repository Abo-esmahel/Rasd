import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';

import '../../core/api_client.dart';
import '../../core/cache_janitor.dart';
import '../../core/models.dart';

final notesRepoProvider = Provider<NotesRepo>((ref) {
  return NotesRepo(ref.watch(dioProvider));
});

class NoteFilter {
  final String? status;
  final bool mine;
  final String? date;
  final int? floor;
  final int? camera;
  final String? q;
  final int? observerId;
  final String? observerName;
  final String sort;

  const NoteFilter({
    this.status,
    this.mine = false,
    this.date,
    this.floor,
    this.camera,
    this.q,
    this.observerId,
    this.observerName,
    this.sort = 'latest',
  });

  NoteFilter copyWith({
    String? status,
    bool? mine,
    String? date,
    int? floor,
    int? camera,
    String? q,
    int? observerId,
    String? observerName,
    String? sort,
    bool clearStatus = false,
    bool clearDate = false,
    bool clearFloor = false,
    bool clearCamera = false,
    bool clearQ = false,
    bool clearObserver = false,
  }) => NoteFilter(
    status: clearStatus ? null : (status ?? this.status),
    mine: mine ?? this.mine,
    date: clearDate ? null : (date ?? this.date),
    floor: clearFloor ? null : (floor ?? this.floor),
    camera: clearCamera ? null : (camera ?? this.camera),
    q: clearQ ? null : (q ?? this.q),
    observerId: clearObserver ? null : (observerId ?? this.observerId),
    observerName: clearObserver ? null : (observerName ?? this.observerName),
    sort: sort ?? this.sort,
  );

  int get activeCount =>
      (status != null ? 1 : 0) +
      (date != null ? 1 : 0) +
      (floor != null ? 1 : 0) +
      (camera != null ? 1 : 0) +
      (q != null && q!.trim().length >= 2 ? 1 : 0) +
      (observerId != null ? 1 : 0) +
      (sort != 'latest' ? 1 : 0);

  Map<String, dynamic> toQuery(int page) => {
    'page': page,
    if (status != null) 'status': status,
    if (date != null) 'date': date,
    if (floor != null) 'floor_number': floor,
    if (camera != null) 'camera_number': camera,
    if (q != null && q!.trim().length >= 2) 'search': q!.trim(),
    if (observerId != null && !mine) 'observer': observerId,
    if (sort != 'latest') 'sort': sort,
  };
}

class NotesRepo {
  final Dio _dio;
  NotesRepo(this._dio);

  Future<Paged<Note>> list(NoteFilter f, int page) async {
    try {
      final res = await _dio.get(
        f.mine ? '/my-notes' : '/notes',
        queryParameters: f.toQuery(page),
      );
      return Paged.fromJson<Note>(unwrapData(res), (j) => Note.fromJson(j));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<List<Map<String, dynamic>>> observers({String? search}) async {
    try {
      final res = await _dio.get(
        '/users/observers',
        queryParameters: {if (search != null && search.trim().length >= 2) 'search': search.trim()},
      );
      final body = res.data as Map;
      final raw = body['data'];
      if (raw is List) {
        return raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      }
      return const [];
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Note> show(int id) async {
    try {
      final res = await _dio.get('/notes/$id');
      return Note.fromJson(Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> react(int id, String reaction) async {
    try {
      await _dio.put('/notes/$id/reaction', data: {'reaction': reaction});
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Map<String, dynamic>> translationStatus(int id, String locale) async {
    try {
      final res = await _dio.get(
        '/notes/$id/translation-status',
        queryParameters: {'locale': locale},
      );
      return Map<String, dynamic>.from(res.data as Map);
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Map<String, dynamic>> retryTranslation(int id, String locale) async {
    try {
      final res = await _dio.post(
        '/notes/$id/translation-retry',
        data: {'locale': locale},
      );
      return Map<String, dynamic>.from(res.data as Map);
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Note> create({
    required int floor,
    required int camera,
    required String observedAt,
    String? observedEndAt,
    required String description,
  }) async {
    try {
      final res = await _dio.post(
        '/notes',
        data: {
          'floor_number': floor,
          'camera_number': camera,
          'observed_at': observedAt,
          if (observedEndAt != null) 'observed_end_at': observedEndAt,
          'description': description,
        },
      );
      return Note.fromJson(Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Attachment> uploadAttachment(
    int noteId,
    File file, {
    void Function(int sent, int total)? onProgress,
  }) async {
    try {
      final name = file.path.split(RegExp(r'[/\\]')).last;
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(file.path, filename: name),
      });
      final res = await _dio.post(
        '/notes/$noteId/attachments',
        data: form,
        onSendProgress: onProgress,
      );
      return Attachment.fromJson(
        Map<String, dynamic>.from(unwrapData(res) as Map),
      );
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Note> _action(String path, [Map<String, dynamic>? data]) async {
    try {
      final res = await _dio.post(path, data: data);
      final d = unwrapData(res);
      if (d is Map) {
        return Note.fromJson(Map<String, dynamic>.from(d));
      }
      final id = int.parse(path.split('/')[2]);
      return show(id);
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Note> send(int id) => _action('/notes/$id/send');
  Future<Note> resend(int id) => _action('/notes/$id/resend');
  Future<Note> accept(int id) => _action('/notes/$id/accept');
  Future<Note> reject(int id, String reason) =>
      _action('/notes/$id/reject', {'rejection_reason': reason});

  Future<Note> update(int id, Map<String, dynamic> data) async {
    try {
      final res = await _dio.put('/notes/$id', data: data);
      return Note.fromJson(Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> delete(int id) async {
    try {
      await _dio.delete('/notes/$id');
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> deleteAttachment(int noteId, int attachmentId) async {
    try {
      await _dio.delete('/notes/$noteId/attachments/$attachmentId');
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Uint8List?> viewBytes(Attachment att) async {
    try {
      final res = await _dio.get(
        '/attachments/${att.id}/view',
        options: Options(responseType: ResponseType.bytes),
      );
      final data = res.data;
      if (data is List<int>) return Uint8List.fromList(data);
      return null;
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<File> downloadAttachment(Attachment att) async {
    try {
      final dir = await getTemporaryDirectory();
      final safeName = att.name.isEmpty
          ? 'attachment-${att.id}'
          : att.name.replaceAll(RegExp(r'[\\/:*?"<>|]'), '_');
      final path = '${dir.path}/${att.id}_$safeName';
      await _dio.download('/attachments/${att.id}', path);
      unawaited(CacheJanitor.maybeSweep());
      return File(path);
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  String viewUrl(Attachment att) => '/attachments/${att.id}/view';
}
