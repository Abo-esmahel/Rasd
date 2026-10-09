import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models.dart';
import 'reports_repo.dart';

class ReportFilter {
  final String? status;
  final String? date;
  final String? q;
  const ReportFilter({this.status, this.date, this.q});

  ReportFilter copyWith({
    String? status,
    String? date,
    String? q,
    bool clearStatus = false,
    bool clearDate = false,
    bool clearQ = false,
  }) => ReportFilter(
    status: clearStatus ? null : (status ?? this.status),
    date: clearDate ? null : (date ?? this.date),
    q: clearQ ? null : (q ?? this.q),
  );
}

final reportFilterProvider = StateProvider<ReportFilter>(
  (ref) => const ReportFilter(),
);

final reportsListProvider =
    StateNotifierProvider<ReportsListNotifier, AsyncValue<List<Report>>>(
      (ref) => ReportsListNotifier(ref),
    );

class ReportsListNotifier extends StateNotifier<AsyncValue<List<Report>>> {
  final Ref _ref;
  int _page = 1;
  bool _hasMore = true;
  bool _loading = false;

  ReportsListNotifier(this._ref) : super(const AsyncValue.loading()) {
    _ref.listen<ReportFilter>(
      reportFilterProvider,
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
      final f = _ref.read(reportFilterProvider);
      final paged = await _ref
          .read(reportsRepoProvider)
          .list(status: f.status, date: f.date, q: f.q, page: _page);
      _hasMore = paged.hasMore;
      _page++;
      final cur = (reset || state.value == null) ? <Report>[] : state.value!;
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

final reportDetailProvider =
    StateNotifierProvider.family<ReportDetailNotifier, AsyncValue<Report>, int>(
      (ref, id) => ReportDetailNotifier(ref, id),
    );

class ReportDetailNotifier extends StateNotifier<AsyncValue<Report>> {
  final Ref _ref;
  final int id;
  ReportDetailNotifier(this._ref, this.id) : super(const AsyncValue.loading()) {
    reload();
  }

  Future<void> reload({bool preserve = true}) async {
    final previous = state;
    if (!preserve || previous.valueOrNull == null) {
      state = const AsyncValue.loading();
    }
    try {
      state = AsyncValue.data(await _ref.read(reportsRepoProvider).show(id));
    } catch (e, st) {
      if (!preserve || previous.valueOrNull == null) {
        state = AsyncValue.error(e, st);
      }
    }
  }

  Future<String?> run(Future<Report> Function() call) async {
    try {
      state = AsyncValue.data(await call());
      _ref.read(reportsListProvider.notifier).refresh();
      return null;
    } on Exception catch (e) {
      return e.toString();
    }
  }

  Future<String?> runVoid(Future<void> Function() call) async {
    try {
      await call();
      await reload();
      _ref.read(reportsListProvider.notifier).refresh();
      return null;
    } on Exception catch (e) {
      return e.toString();
    }
  }
}
