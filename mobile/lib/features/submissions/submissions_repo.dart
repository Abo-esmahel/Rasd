import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';

import '../../core/api_client.dart';
import '../../core/cache_janitor.dart';
import '../../core/models.dart';

final submissionsRepoProvider = Provider<SubmissionsRepo>((ref) {
  return SubmissionsRepo(ref.watch(dioProvider));
});

class Submission {
  final int id;
  final int userId;
  final int floorNumber;
  final int cameraNumber;
  final DateTime? observedAt;
  final String? observedAtRaw;
  final String description;
  final String status;
  final String? rejectionReason;
  final User? owner;
  final List<User> writers;
  final List<Attachment> attachments;
  final DateTime? createdAt;

  Submission({
    required this.id,
    required this.userId,
    required this.floorNumber,
    required this.cameraNumber,
    required this.observedAt,
    required this.observedAtRaw,
    required this.description,
    required this.status,
    required this.rejectionReason,
    required this.owner,
    required this.writers,
    required this.attachments,
    required this.createdAt,
  });

  bool get isDraft => status == 'draft';
  bool get isPending => status == 'pending';
  bool get isAccepted => status == 'accepted';
  bool get isRejected => status == 'rejected';

  static List<User> _users(dynamic raw) {
    if (raw is List) {
      return raw
          .whereType<Map<String, dynamic>>()
          .map(User.fromJson)
          .toList();
    }
    return const [];
  }

  factory Submission.fromJson(Map<String, dynamic> j) {
    final atts = j['attachments'];
    return Submission(
      id: _i(j['id']),
      userId: _i(j['user_id']),
      floorNumber: _i(j['floor_number']),
      cameraNumber: _i(j['camera_number']),
      observedAt: _d(j['observed_at']),
      observedAtRaw: j['observed_at']?.toString(),
      description: (j['description'] ?? '').toString(),
      status: (j['status'] ?? '').toString(),
      rejectionReason: j['rejection_reason']?.toString(),
      owner: j['owner'] is Map<String, dynamic>
          ? User.fromJson(j['owner'] as Map<String, dynamic>)
          : (j['writer'] is Map<String, dynamic>
              ? User.fromJson(j['writer'] as Map<String, dynamic>)
              : null),
      writers: _users(j['reportWriters'] ?? j['report_writers']),
      attachments: atts is List
          ? atts
              .whereType<Map<String, dynamic>>()
              .map(Attachment.fromJson)
              .toList()
          : const [],
      createdAt: _d(j['created_at']),
    );
  }

  static int _i(dynamic v) =>
      v is int ? v : int.tryParse(v?.toString() ?? '') ?? 0;
  static DateTime? _d(dynamic v) =>
      v == null ? null : DateTime.tryParse(v.toString());
}

class SubmissionFilter {
  final String? status;
  final String? date;
  final int? floor;
  final int? camera;
  final String? q;
  final String sort;
  const SubmissionFilter({
    this.status,
    this.date,
    this.floor,
    this.camera,
    this.q,
    this.sort = 'latest',
  });

  SubmissionFilter copyWith({
    String? status,
    String? date,
    int? floor,
    int? camera,
    String? q,
    String? sort,
    bool clearStatus = false,
    bool clearDate = false,
    bool clearFloor = false,
    bool clearCamera = false,
    bool clearQ = false,
  }) => SubmissionFilter(
    status: clearStatus ? null : (status ?? this.status),
    date: clearDate ? null : (date ?? this.date),
    floor: clearFloor ? null : (floor ?? this.floor),
    camera: clearCamera ? null : (camera ?? this.camera),
    q: clearQ ? null : (q ?? this.q),
    sort: sort ?? this.sort,
  );

  int get activeCount =>
      (date != null ? 1 : 0) +
      (floor != null ? 1 : 0) +
      (camera != null ? 1 : 0) +
      (q != null && q!.trim().length >= 2 ? 1 : 0) +
      (sort != 'latest' ? 1 : 0);

  Map<String, dynamic> toQuery(int page) => {
    'page': page,
    if (status != null) 'status': status,
    if (date != null) 'date': date,
    if (floor != null) 'floor_number': floor,
    if (camera != null) 'camera_number': camera,
    if (q != null && q!.trim().length >= 2) 'search': q!.trim(),
    if (sort != 'latest') 'sort': sort,
  };
}

final submissionFilterProvider =
    StateProvider<SubmissionFilter>((ref) => const SubmissionFilter());

class SubmissionsRepo {
  final Dio _dio;
  SubmissionsRepo(this._dio);

  Future<Paged<Submission>> list(SubmissionFilter f, int page) async {
    try {
      final res = await _dio.get('/general-submissions',
          queryParameters: f.toQuery(page));
      return Paged.fromJson<Submission>(
          unwrapData(res), (j) => Submission.fromJson(j));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Paged<Submission>> listByStatus({String? status, int page = 1}) =>
      list(SubmissionFilter(status: status), page);

  Future<Submission> show(int id) async {
    try {
      final res = await _dio.get('/general-submissions/$id');
      return Submission.fromJson(
          Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Submission> create({
    required int floor,
    required int camera,
    required String observedAt,
    String? observedEndAt,
    required String description,
    required List<int> writerIds,
    required List<File> files,
    void Function(int sent, int total)? onProgress,
  }) async {
    try {
      final form = FormData.fromMap({
        'floor_number': floor,
        'camera_number': camera,
        'observed_at': observedAt,
        if (observedEndAt != null) 'observed_end_at': observedEndAt,
        'description': description,
        'report_writer_ids[]': writerIds,
        'client_files_count': files.length,
        'files': [
          for (final f in files)
            await MultipartFile.fromFile(f.path,
                filename: f.path.split(RegExp(r'[/\\]')).last),
        ],
      });
      final res = await _dio.post('/general-submissions',
          data: form, onSendProgress: onProgress);
      final body = Map<String, dynamic>.from(res.data as Map);
      final d = body['data'];
      if (d is Map) {
        return Submission.fromJson(Map<String, dynamic>.from(d));
      }
      throw ApiException(
          message: (body['message'] ?? 'فشل الإنشاء').toString());
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Submission> _one(String path,
      [Map<String, dynamic>? data]) async {
    try {
      final res = await _dio.post(path, data: data);
      return Submission.fromJson(
          Map<String, dynamic>.from(unwrapData(res) as Map));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<Submission> submit(int id, List<int> writerIds) =>
      _one('/general-submissions/$id/submit',
          {'report_writer_ids': writerIds});
  Future<Submission> accept(int id) =>
      _one('/general-submissions/$id/accept');
  Future<Submission> reject(int id, String reason) => _one(
      '/general-submissions/$id/reject',
      {'rejection_reason': reason});

  Future<Uint8List?> viewBytes(int id) async {
    try {
      final res = await _dio.get(
        '/submission-attachments/$id/view',
        options: Options(responseType: ResponseType.bytes),
      );
      final data = res.data;
      if (data is List<int>) return Uint8List.fromList(data);
      return null;
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<File> downloadAttachment(int id, String name) async {
    try {
      final dir = await getTemporaryDirectory();
      final safe = name.isEmpty
          ? 'sub-$id'
          : name.replaceAll(RegExp(r'[\\/:*?"<>|]'), '_');
      final path = '${dir.path}/sub-${id}_$safe';
      await _dio.download(
          '/submission-attachments/$id/download', path);
      unawaited(CacheJanitor.maybeSweep());
      return File(path);
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  Future<List<User>> writers() async {
    try {
      final res = await _dio.get('/users/writers');
      final body = res.data as Map;
      final raw = body['data'];
      if (raw is List) {
        return raw
            .whereType<Map<String, dynamic>>()
            .map(User.fromJson)
            .toList();
      }
      return const [];
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }
}

final submissionsListProvider = StateNotifierProvider<
    SubmissionsNotifier, AsyncValue<List<Submission>>>((ref) {
  return SubmissionsNotifier(ref);
});

class SubmissionsNotifier
    extends StateNotifier<AsyncValue<List<Submission>>> {
  final Ref _ref;
  int _page = 1;
  bool _hasMore = true;
  bool _loading = false;
  String? get status => _ref.read(submissionFilterProvider).status;
  set status(String? v) {
    final cur = _ref.read(submissionFilterProvider);
    _ref.read(submissionFilterProvider.notifier).state = cur.copyWith(
      status: v,
      clearStatus: v == null,
    );
  }

  SubmissionsNotifier(this._ref)
      : super(const AsyncValue.loading()) {
    _ref.listen<SubmissionFilter>(
      submissionFilterProvider,
      (_, __) => refresh(keepData: false),
    );
    refresh();
  }

  Future<void> refresh({bool keepData = true}) async {
    _page = 1;
    _hasMore = true;
    if (!keepData || state.valueOrNull == null) {
      state = const AsyncValue.loading();
    }
    await _load(reset: true);
  }

  Future<void> loadMore() async {
    if (_loading || !_hasMore || state.isLoading) return;
    await _load();
  }

  Future<void> _load({bool reset = false}) async {
    _loading = true;
    try {
      final filter = _ref.read(submissionFilterProvider);
      final paged = await _ref
          .read(submissionsRepoProvider)
          .list(filter, _page);
      _hasMore = paged.hasMore;
      _page++;
      final cur =
          (reset || state.value == null) ? <Submission>[] : state.value!;
      state = AsyncValue.data([...cur, ...paged.items]);
    } catch (e, st) {
      if (reset && state.valueOrNull == null) {
        state = AsyncValue.error(e, st);
      }
    } finally {
      _loading = false;
    }
  }
}

final submissionDetailProvider = StateNotifierProvider.family<
    SubmissionDetailNotifier,
    AsyncValue<Submission>,
    int>((ref, id) => SubmissionDetailNotifier(ref, id));

class SubmissionDetailNotifier
    extends StateNotifier<AsyncValue<Submission>> {
  final Ref _ref;
  final int id;
  SubmissionDetailNotifier(this._ref, this.id)
      : super(const AsyncValue.loading()) {
    reload();
  }

  Future<void> reload() async {
    final previous = state.valueOrNull;
    if (previous == null) state = const AsyncValue.loading();
    try {
      state = AsyncValue.data(
          await _ref.read(submissionsRepoProvider).show(id));
    } catch (e, st) {
      if (previous == null) state = AsyncValue.error(e, st);
    }
  }

  Future<String?> run(Future<Submission> Function() call) async {
    try {
      state = AsyncValue.data(await call());
      _ref.read(submissionsListProvider.notifier).refresh();
      return null;
    } on Exception catch (e) {
      return e.toString();
    }
  }
}
