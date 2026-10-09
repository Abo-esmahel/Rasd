import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/models.dart';

final reportsRepoProvider = Provider<ReportsRepo>((ref) {
  return ReportsRepo(ref.watch(dioProvider));
});

class ReportsRepo {
  final Dio _dio;
  ReportsRepo(this._dio);

  Future<Map<String, dynamic>> insights(String range) async {
    try {
      final res = await _dio.get(
        '/reports/insights',
        queryParameters: {'range': range},
      );
      return Map<String, dynamic>.from(unwrapData(res) as Map);
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Map<String, dynamic>> dailyStatus(String date) async {
    try {
      final res = await _dio.get(
        '/reports/daily-status',
        queryParameters: {'report_date': date},
      );
      return Map<String, dynamic>.from(unwrapData(res) as Map);
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Paged<Report>> list({
    String? status,
    String? date,
    String? q,
    int page = 1,
  }) async {
    try {
      final res = await _dio.get(
        '/reports',
        queryParameters: {
          'page': page,
          if (status != null) 'status': status,
          if (date != null) 'report_date': date,
          if (q != null && q.trim().length >= 2) 'search': q.trim(),
        },
      );
      return Paged.fromJson<Report>(unwrapData(res), (j) => Report.fromJson(j));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Report> show(int id) async {
    try {
      final res = await _dio.get('/reports/$id');
      return Report.fromJson(Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Report> create({
    required String date,
    String? title,
    String? content,
    List<int>? aiPreviewNoteIds,
  }) async {
    try {
      final res = await _dio.post(
        '/reports',
        data: {
          'report_date': date,
          if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
          if (content != null && content.trim().isNotEmpty)
            'content': content.trim(),
          if (aiPreviewNoteIds != null && aiPreviewNoteIds.isNotEmpty)
            'ai_preview_note_ids': aiPreviewNoteIds,
        },
      );
      return Report.fromJson(Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Map<String, dynamic>> preview({
    String? date,
    String? title,
    int? reportId,
  }) async {
    try {
      final res = await _dio.post(
        '/reports/ai-preview',
        data: {
          if (reportId != null) 'report_id': reportId,
          if (date != null) 'report_date': date,
          if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
        },
        options: Options(
          receiveTimeout: const Duration(seconds: 150),
          sendTimeout: const Duration(seconds: 150),
        ),
      );
      return Map<String, dynamic>.from(unwrapData(res) as Map);
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Report> update(int id, Map<String, dynamic> data) async {
    try {
      final res = await _dio.put('/reports/$id', data: data);
      return Report.fromJson(Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> delete(int id) async {
    try {
      await _dio.delete('/reports/$id');
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> reorder(int id, List<int> orderedIds) async {
    try {
      await _dio.post(
        '/reports/$id/reorder',
        data: {'ordered_ids': orderedIds},
      );
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Report> publish(int id) async {
    try {
      final res = await _dio.post('/reports/$id/publish');
      return Report.fromJson(Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Report> unpublish(int id) async {
    try {
      final res = await _dio.post('/reports/$id/unpublish');
      return Report.fromJson(Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> detach(int reportId, int noteId) async {
    try {
      await _dio.delete('/reports/$reportId/notes/$noteId');
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> attach(int reportId, List<int> noteIds) async {
    try {
      await _dio.post('/reports/$reportId/attach', data: {'note_ids': noteIds});
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> reactReport(int id, String reaction) async {
    try {
      await _dio.put('/reports/$id/reaction', data: {'reaction': reaction});
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> reactComment(int id, String reaction) async {
    try {
      await _dio.put(
        '/report-comments/$id/reaction',
        data: {'reaction': reaction},
      );
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<void> comment(int reportId, String body, {int? parentId}) async {
    try {
      await _dio.post(
        '/reports/$reportId/comments',
        data: {'body': body, if (parentId != null) 'parent_id': parentId},
      );
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Report> generate(
    int id, {
    bool regenerate = false,
    bool confirmOverwrite = false,
    bool includeImages = true,
  }) async {
    try {
      final res = await _dio.post(
        '/reports/$id/generate',
        data: {
          'regenerate': regenerate,
          'confirm_overwrite_manual': confirmOverwrite,
          'include_images': includeImages,
        },
        options: Options(
          receiveTimeout: const Duration(seconds: 150),
          sendTimeout: const Duration(seconds: 150),
        ),
      );
      return Report.fromJson(Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }
}
