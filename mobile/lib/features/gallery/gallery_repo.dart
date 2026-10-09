import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/models.dart';

final galleryRepoProvider = Provider<GalleryRepo>((ref) {
  return GalleryRepo(ref.watch(dioProvider));
});

class GalleryFilter {
  final String? type;
  final String? date;
  final int? camera;
  final int? floor;
  final String sort;
  const GalleryFilter({
    this.type,
    this.date,
    this.camera,
    this.floor,
    this.sort = 'latest',
  });

  GalleryFilter copyWith({
    String? type,
    String? date,
    int? camera,
    int? floor,
    String? sort,
    bool clearType = false,
    bool clearDate = false,
    bool clearCamera = false,
    bool clearFloor = false,
  }) =>
      GalleryFilter(
        type: clearType ? null : (type ?? this.type),
        date: clearDate ? null : (date ?? this.date),
        camera: clearCamera ? null : (camera ?? this.camera),
        floor: clearFloor ? null : (floor ?? this.floor),
        sort: sort ?? this.sort,
      );

  int get activeCount =>
      [type, date, camera, floor].where((e) => e != null).length +
      (sort == 'latest' ? 0 : 1);
}

final galleryFilterProvider =
    StateProvider<GalleryFilter>((ref) => const GalleryFilter());

final galleryListProvider = StateNotifierProvider<GalleryNotifier,
    AsyncValue<List<GalleryItem>>>((ref) => GalleryNotifier(ref));

class GalleryNotifier
    extends StateNotifier<AsyncValue<List<GalleryItem>>> {
  final Ref _ref;
  int _page = 1;
  bool _hasMore = true;
  bool _loading = false;

  GalleryNotifier(this._ref)
      : super(const AsyncValue.loading()) {
    _ref.listen<GalleryFilter>(
      galleryFilterProvider,
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
      final f = _ref.read(galleryFilterProvider);
      final paged =
          await _ref.read(galleryRepoProvider).list(f, _page);
      _hasMore = paged.hasMore;
      _page++;
      final cur = (reset || state.value == null) ? <GalleryItem>[] : state.value!;
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

class GalleryRepo {
  final Dio _dio;
  GalleryRepo(this._dio);

  Future<Paged<GalleryItem>> list(GalleryFilter f, int page) async {
    try {
      final res = await _dio.get('/gallery', queryParameters: {
        'page': page,
        'per_page': 24,
        if (f.type != null) 'type': f.type,
        if (f.date != null) 'date': f.date,
        if (f.camera != null) 'camera_number': f.camera,
        if (f.floor != null) 'floor_number': f.floor,
        'sort': f.sort,
      });
      return Paged.fromJson<GalleryItem>(
          unwrapData(res), (j) => GalleryItem.fromJson(j));
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }
}
