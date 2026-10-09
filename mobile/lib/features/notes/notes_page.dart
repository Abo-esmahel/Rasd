import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/config.dart';
import '../../core/models.dart';
import '../../core/strings.dart';
import '../../core/wall_clock.dart';
import '../../core/widgets.dart';
import '../auth/auth_provider.dart';
import '../home/user_profile_page.dart';
import 'note_detail_page.dart';
import 'notes_providers.dart';
import 'notes_repo.dart';

const _tabs = [null, 'draft', 'pending', 'accepted', 'rejected'];

class NotesPage extends ConsumerStatefulWidget {
  const NotesPage({super.key});

  @override
  ConsumerState<NotesPage> createState() => _NotesPageState();
}

class _NotesPageState extends ConsumerState<NotesPage>
    with WidgetsBindingObserver {
  final _scroll = ScrollController();
  final _searchCtrl = TextEditingController();
  Timer? _debounce;
  DateTime? _lastResumeRefresh;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 400) {
        ref.read(notesListProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _debounce?.cancel();
    _searchCtrl.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _onSearch(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      final q = v.trim();
      ref.read(noteFilterProvider.notifier).state = ref
          .read(noteFilterProvider)
          .copyWith(q: q.isEmpty ? null : q, clearQ: q.isEmpty);
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      final now = DateTime.now();
      if (_lastResumeRefresh == null ||
          now.difference(_lastResumeRefresh!).inSeconds > 30) {
        _lastResumeRefresh = now;
        try {
          ref.read(notesListProvider.notifier).refresh();
        } catch (_) {}
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final filter = ref.watch(noteFilterProvider);
    final list = ref.watch(notesListProvider);
    final user = ref.watch(authProvider).valueOrNull;
    final activeFilters = filter.activeCount;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 0),
          child: TextField(
            controller: _searchCtrl,
            onChanged: _onSearch,
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(
              hintText: tr(ref, 'search'),
              prefixIcon: const Icon(Icons.search_outlined, size: 20),
              suffixIcon: filter.q == null
                  ? null
                  : IconButton(
                      icon: const Icon(Icons.clear, size: 18),
                      onPressed: () {
                        _searchCtrl.clear();
                        ref.read(noteFilterProvider.notifier).state = filter
                            .copyWith(clearQ: true);
                      },
                    ),
            ),
          ),
        ),
        if (user != null && !user.isGM)
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 10, 14, 0),
            child: SegmentedButton<bool>(
              segments: [
                ButtonSegment(value: false, label: Text(tr(ref, 'notes'))),
                ButtonSegment(value: true, label: Text(tr(ref, 'my_notes'))),
              ],
              selected: {filter.mine},
              onSelectionChanged: (s) =>
                  ref.read(noteFilterProvider.notifier).state = filter.copyWith(
                    mine: s.first,
                  ),
            ),
          ),
        SizedBox(
          height: 48,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            children: _tabs.map((t) {
              final active = filter.status == t;
              final label = t == null ? tr(ref, 'all') : tr(ref, t);
              return Padding(
                padding: const EdgeInsetsDirectional.only(end: 8),
                child: ChoiceChip(
                  label: Text(label),
                  selected: active,
                  onSelected: (_) =>
                      ref.read(noteFilterProvider.notifier).state = filter
                          .copyWith(status: t, clearStatus: t == null),
                ),
              );
            }).toList(),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  list.valueOrNull == null ? '' : '${list.valueOrNull!.length}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
              TextButton.icon(
                onPressed: () => _openFilter(context, filter),
                icon: Badge(
                  isLabelVisible: activeFilters > 0,
                  label: Text('$activeFilters'),
                  child: const Icon(Icons.filter_list_outlined),
                ),
                label: Text(tr(ref, 'filter')),
              ),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => ref.read(notesListProvider.notifier).refresh(),
            child: list.when(
              loading: () => const RasdSkeletonList(),
              error: (e, _) => RasdError(
                error: e,
                onRetry: () => ref.read(notesListProvider.notifier).refresh(),
                retryLabel: tr(ref, 'retry'),
              ),
              data: (items) => items.isEmpty
                  ? RasdEmpty(message: tr(ref, 'no_results'))
                  : ListView.builder(
                      controller: _scroll,
                      padding: const EdgeInsets.fromLTRB(12, 4, 12, 88),
                      itemCount: items.length,
                      itemBuilder: (_, i) => _NoteCard(note: items[i]),
                    ),
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _openFilter(BuildContext context, NoteFilter filter) async {
    final dateCtrl = TextEditingController(text: filter.date ?? '');
    final floorCtrl = TextEditingController(
      text: filter.floor?.toString() ?? '',
    );
    final camCtrl = TextEditingController(
      text: filter.camera?.toString() ?? '',
    );
    int? observerId = filter.observerId;
    String? observerName = filter.observerName;
    String sort = filter.sort;
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setS) => Padding(
          padding: EdgeInsets.fromLTRB(
            16,
            16,
            16,
            16 + MediaQuery.of(ctx).viewInsets.bottom,
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextField(
                  controller: dateCtrl,
                  readOnly: true,
                  decoration: InputDecoration(
                    labelText: tr(ref, 'date'),
                    prefixIcon: const Icon(Icons.calendar_today_outlined),
                    suffixIcon: dateCtrl.text.isEmpty
                        ? null
                        : IconButton(
                            icon: const Icon(Icons.clear, size: 18),
                            onPressed: () => setS(() => dateCtrl.clear()),
                          ),
                  ),
                  onTap: () async {
                    final now = DateTime.now();
                    final picked = await showDatePicker(
                      context: ctx,
                      initialDate: DateTime.tryParse(dateCtrl.text) ?? now,
                      firstDate: DateTime(2020),
                      lastDate: now,
                    );
                    if (picked != null) {
                      setS(
                        () => dateCtrl.text =
                            '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}',
                      );
                    }
                  },
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: floorCtrl,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: tr(ref, 'floor'),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextField(
                        controller: camCtrl,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: tr(ref, 'camera'),
                        ),
                      ),
                    ),
                  ],
                ),
                if (!filter.mine) ...[
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: () async {
                      final picked = await _pickObserver(ctx, observerId);
                      if (picked != null) {
                        setS(() {
                          observerId = picked['id'] as int?;
                          observerName = picked['name'] as String?;
                        });
                      }
                    },
                    icon: const Icon(Icons.person_search_outlined, size: 18),
                    label: Text(observerName ?? tr(ref, 'all_observers')),
                  ),
                ],
                const SizedBox(height: 12),
                Text(
                  tr(ref, 'sort_by'),
                  style: Theme.of(ctx).textTheme.labelLarge,
                ),
                const SizedBox(height: 6),
                SegmentedButton<String>(
                  segments: [
                    ButtonSegment(
                      value: 'latest',
                      label: Text(tr(ref, 'sort_newest')),
                    ),
                    ButtonSegment(
                      value: 'oldest',
                      label: Text(tr(ref, 'sort_oldest')),
                    ),
                    if (!filter.mine)
                      ButtonSegment(
                        value: 'observer',
                        label: Text(tr(ref, 'sort_observer')),
                      ),
                  ],
                  selected: {sort},
                  onSelectionChanged: (s) => setS(() => sort = s.first),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: FilledButton(
                        onPressed: () {
                          ref
                              .read(noteFilterProvider.notifier)
                              .state = filter.copyWith(
                            date: dateCtrl.text.isEmpty ? null : dateCtrl.text,
                            clearDate: dateCtrl.text.isEmpty,
                            floor: int.tryParse(floorCtrl.text),
                            clearFloor: floorCtrl.text.isEmpty,
                            camera: int.tryParse(camCtrl.text),
                            clearCamera: camCtrl.text.isEmpty,
                            observerId: observerId,
                            observerName: observerName,
                            clearObserver: observerId == null,
                            sort: sort,
                          );
                          Navigator.pop(ctx);
                        },
                        child: Text(tr(ref, 'apply')),
                      ),
                    ),
                    const SizedBox(width: 12),
                    TextButton(
                      onPressed: () {
                        ref.read(noteFilterProvider.notifier).state = filter
                            .copyWith(
                              clearDate: true,
                              clearFloor: true,
                              clearCamera: true,
                              clearObserver: true,
                              sort: 'latest',
                            );
                        Navigator.pop(ctx);
                      },
                      child: Text(tr(ref, 'clear')),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<Map<String, Object?>?> _pickObserver(
    BuildContext ctx,
    int? currentId,
  ) async {
    final repo = ref.read(notesRepoProvider);
    var query = '';
    List<Map<String, dynamic>> items = [];
    bool loading = true;
    String? error;
    Future<void> load(StateSetter setD) async {
      setD(() {
        loading = true;
        error = null;
      });
      try {
        items = await repo.observers(search: query);
      } catch (e) {
        error = e.toString();
      }
      setD(() => loading = false);
    }

    return showDialog<Map<String, Object?>?>(
      context: ctx,
      builder: (dctx) => StatefulBuilder(
        builder: (dctx, setD) {
          if (loading && items.isEmpty && error == null) {
            load(setD);
          }
          return AlertDialog(
            title: Text(tr(ref, 'observer')),
            content: SizedBox(
              width: double.maxFinite,
              height: MediaQuery.of(dctx).size.height * 0.5,
              child: Column(
                children: [
                  TextField(
                    decoration: InputDecoration(
                      hintText: tr(ref, 'search_observer'),
                      prefixIcon: const Icon(Icons.search_outlined),
                    ),
                    onChanged: (v) {
                      query = v.trim();
                      if (query.length >= 2 || query.isEmpty) load(setD);
                    },
                  ),
                  const SizedBox(height: 8),
                  if (loading)
                    const Padding(
                      padding: EdgeInsets.all(16),
                      child: CircularProgressIndicator(),
                    )
                  else if (error != null)
                    Text(error!)
                  else
                    Expanded(
                      child: RadioGroup<int?>(
                        groupValue: currentId,
                        onChanged: (v) {
                          if (v == null) {
                            Navigator.pop(dctx, {'id': null, 'name': null});
                          } else {
                            final o = items.firstWhere(
                              (e) => (e['id'] as num?)?.toInt() == v,
                              orElse: () => <String, dynamic>{},
                            );
                            Navigator.pop(dctx, {
                              'id': v,
                              'name': (o['name'] ?? '').toString(),
                            });
                          }
                        },
                        child: ListView(
                          shrinkWrap: true,
                          children: [
                            RadioListTile<int?>(
                              value: null,
                              title: Text(tr(ref, 'all_observers')),
                            ),
                            for (final o in items)
                              RadioListTile<int?>(
                                value: (o['id'] as num?)?.toInt(),
                                title: Text((o['name'] ?? '').toString()),
                                subtitle: Text(
                                  '@${(o['username'] ?? '').toString()}',
                                ),
                                secondary: (o['id'] as num?) == null
                                    ? null
                                    : IconButton(
                                        tooltip: tr(ref, 'view_profile'),
                                        onPressed: () => openUserProfile(
                                          context,
                                          (o['id'] as num).toInt(),
                                        ),
                                        icon: const Icon(
                                          Icons.person_outline,
                                          size: 20,
                                        ),
                                      ),
                              ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dctx),
                child: Text(tr(ref, 'cancel')),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _NoteCard extends ConsumerWidget {
  final Note note;
  const _NoteCard({required this.note});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final ownerName = note.owner?.name ?? '—';
    Attachment? photo;
    for (final a in note.attachments) {
      if (a.isImage) {
        photo = a;
        break;
      }
    }
    final obs = Wall.parts(note.observedAtRaw);
    final obsLabel = obs.date.isEmpty
        ? ''
        : '${obs.date} · ${Wall.time12(obs.time)}';
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: RasdCard(
        padding: const EdgeInsets.all(16),
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => NoteDetailPage(id: note.id)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (photo != null) ...[
              _CardPhoto(att: photo),
              const SizedBox(height: 12),
            ],
            Row(
              children: [
                RasdUserChip(
                  userId: note.owner?.id,
                  avatarUrl: note.owner?.avatarUrl,
                  name: ownerName,
                  size: 42,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      RasdUserChip(
                        userId: note.owner?.id,
                        name: ownerName,
                        showAvatar: false,
                        nameStyle: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 14,
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${tr(ref, 'camera')} ${note.cameraNumber} · ${tr(ref, 'floor')} ${note.floorNumber}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                RasdStatusChip(
                  status: note.status,
                  label: tr(ref, note.status),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                color: cs.surfaceContainerHigh,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Text(
                note.description,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 14, height: 1.9),
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                if (obsLabel.isNotEmpty) ...[
                  Icon(
                    Icons.access_time_rounded,
                    size: 14,
                    color: cs.onSurfaceVariant,
                  ),
                  const SizedBox(width: 4),
                  Text(obsLabel, style: Theme.of(context).textTheme.bodySmall),
                ],
                if (note.attachments.isNotEmpty) ...[
                  const SizedBox(width: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: cs.primaryContainer.withValues(alpha: .5),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.attach_file_rounded,
                          size: 13,
                          color: cs.primary,
                        ),
                        const SizedBox(width: 3),
                        Text(
                          '${note.attachments.length}',
                          style: TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w800,
                            color: cs.primary,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const Spacer(),
                Icon(
                  Icons.arrow_back_ios_new_rounded,
                  size: 15,
                  color: cs.onSurfaceVariant,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CardPhoto extends ConsumerWidget {
  final Attachment att;
  const _CardPhoto({required this.att});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final url = '${ref.watch(baseUrlProvider)}/api/attachments/${att.id}/view';
    return AspectRatio(
      aspectRatio: 16 / 9,
      child: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerHigh,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Theme.of(context).colorScheme.outline),
        ),
        clipBehavior: Clip.antiAlias,
        child: RasdNetworkImage(
          url: url,
          headers: ref.watch(authImageHeadersProvider),
          isSvg: att.mime == 'image/svg+xml',
        ),
      ),
    );
  }
}
