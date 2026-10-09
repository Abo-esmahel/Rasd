import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models.dart';
import 'notes_repo.dart';

final noteFilterProvider =
    StateProvider<NoteFilter>((ref) => const NoteFilter());

final notesListProvider =
    StateNotifierProvider<NotesListNotifier, AsyncValue<List<Note>>>((ref) {
  return NotesListNotifier(ref);
});

class NotesListNotifier extends StateNotifier<AsyncValue<List<Note>>> {
  final Ref _ref;
  int _page = 1;
  bool _hasMore = true;
  bool _loading = false;

  NotesListNotifier(this._ref) : super(const AsyncValue.loading()) {
    _ref.listen<NoteFilter>(
      noteFilterProvider,
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
      final filter = _ref.read(noteFilterProvider);
      final paged =
          await _ref.read(notesRepoProvider).list(filter, _page);
      _hasMore = paged.hasMore;
      _page++;
      final current = (reset || state.value == null) ? <Note>[] : state.value!;
      state = AsyncValue.data([...current, ...paged.items]);
    } catch (e, st) {
      if (reset && state.valueOrNull == null) {
        state = AsyncValue.error(e, st);
      }
    } finally {
      _loading = false;
    }
  }
}

final noteDetailProvider =
    StateNotifierProvider.family<NoteDetailNotifier, AsyncValue<Note>, int>(
        (ref, id) => NoteDetailNotifier(ref, id));

class NoteDetailNotifier extends StateNotifier<AsyncValue<Note>> {
  final Ref _ref;
  final int id;
  NoteDetailNotifier(this._ref, this.id)
      : super(const AsyncValue.loading()) {
    reload();
  }

  Future<void> reload() async {
    final previous = state.valueOrNull;
    if (previous == null) state = const AsyncValue.loading();
    try {
      state = AsyncValue.data(await _ref.read(notesRepoProvider).show(id));
    } catch (e, st) {
      if (previous == null) state = AsyncValue.error(e, st);
    }
  }

  Future<String?> run(Future<Note> Function() call) async {
    try {
      state = AsyncValue.data(await call());
      _ref.read(notesListProvider.notifier).refresh();
      return null;
    } on Exception catch (e) {
      return e.toString();
    }
  }
}
