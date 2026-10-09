import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/config.dart';
import '../../core/models.dart';
import '../../core/strings.dart';
import '../../core/widgets.dart';
import '../home/user_profile_page.dart';
import 'gallery_repo.dart';
import 'gallery_viewer.dart';

enum GalleryMode { grid, list, compact }

class GalleryPage extends ConsumerStatefulWidget {
  const GalleryPage({super.key});

  @override
  ConsumerState<GalleryPage> createState() => _GalleryPageState();
}

class _GalleryPageState extends ConsumerState<GalleryPage> {
  final _scroll = ScrollController();
  GalleryMode _mode = GalleryMode.grid;

  int _columns(BuildContext context) {
    final w = MediaQuery.sizeOf(context).width;
    if (_mode == GalleryMode.compact) {
      return w > 700 ? 7 : 4;
    }
    return w > 700 ? 5 : 3;
  }

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 600) {
        ref.read(galleryListProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final list = ref.watch(galleryListProvider);
    final filter = ref.watch(galleryFilterProvider);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 0),
          child: Row(
            children: [
              Expanded(
                child: SegmentedButton<GalleryMode>(
                  segments: const [
                    ButtonSegment(
                      value: GalleryMode.grid,
                      icon: Icon(Icons.grid_view_outlined),
                    ),
                    ButtonSegment(
                      value: GalleryMode.list,
                      icon: Icon(Icons.view_list_outlined),
                    ),
                    ButtonSegment(
                      value: GalleryMode.compact,
                      icon: Icon(Icons.grid_on_outlined),
                    ),
                  ],
                  selected: {_mode},
                  onSelectionChanged: (s) => setState(() => _mode = s.first),
                ),
              ),
              const SizedBox(width: 8),
              Badge(
                isLabelVisible: filter.activeCount > 0,
                label: Text('${filter.activeCount}'),
                child: IconButton(
                  onPressed: () => _openFilter(context, filter),
                  icon: const Icon(Icons.filter_list_outlined),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => ref.read(galleryListProvider.notifier).refresh(),
            child: list.when(
              loading: () => const RasdSkeletonList(),
              error: (e, _) => RasdError(
                error: e,
                onRetry: () => ref.read(galleryListProvider.notifier).refresh(),
                retryLabel: tr(ref, 'retry'),
              ),
              data: (items) => items.isEmpty
                  ? RasdEmpty(message: tr(ref, 'no_results'))
                  : _mode == GalleryMode.list
                  ? ListView.builder(
                      controller: _scroll,
                      padding: const EdgeInsets.all(12),
                      itemCount: items.length,
                      itemBuilder: (_, i) => _ListTile(item: items[i]),
                    )
                  : GridView.builder(
                      controller: _scroll,
                      padding: const EdgeInsets.all(12),
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: _columns(context),
                        crossAxisSpacing: 8,
                        mainAxisSpacing: 8,
                      ),
                      itemCount: items.length,
                      itemBuilder: (_, i) => _Thumb(item: items[i]),
                    ),
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _openFilter(BuildContext context, GalleryFilter filter) async {
    String? type = filter.type;
    String date = filter.date ?? '';
    final camCtrl = TextEditingController(
      text: filter.camera?.toString() ?? '',
    );
    final floorCtrl = TextEditingController(
      text: filter.floor?.toString() ?? '',
    );
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
                Wrap(
                  spacing: 8,
                  children: [
                    ChoiceChip(
                      label: Text(tr(ref, 'all')),
                      selected: type == null,
                      onSelected: (_) => setS(() => type = null),
                    ),
                    for (final t in ['image', 'video', 'audio'])
                      ChoiceChip(
                        label: Text(t),
                        selected: type == t,
                        onSelected: (_) =>
                            setS(() => type = type == t ? null : t),
                      ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: camCtrl,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: tr(ref, 'camera'),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextField(
                        controller: floorCtrl,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: tr(ref, 'floor'),
                        ),
                      ),
                    ),
                  ],
                ),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.calendar_today_outlined),
                  title: Text(date.isEmpty ? tr(ref, 'date') : date),
                  trailing: date.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(Icons.close, size: 18),
                          onPressed: () => setS(() => date = ''),
                        ),
                  onTap: () async {
                    final p = await showDatePicker(
                      context: ctx,
                      initialDate: DateTime.tryParse(date) ?? DateTime.now(),
                      firstDate: DateTime(2020),
                      lastDate: DateTime.now(),
                    );
                    if (p != null) {
                      setS(
                        () => date =
                            '${p.year}-${p.month.toString().padLeft(2, '0')}-${p.day.toString().padLeft(2, '0')}',
                      );
                    }
                  },
                ),
                SegmentedButton<String>(
                  segments: [
                    ButtonSegment(
                      value: 'latest',
                      label: Text(tr(ref, 'newest')),
                    ),
                    ButtonSegment(
                      value: 'oldest',
                      label: Text(tr(ref, 'oldest')),
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
                              .read(galleryFilterProvider.notifier)
                              .state = GalleryFilter(
                            type: type,
                            date: date.isEmpty ? null : date,
                            camera: int.tryParse(camCtrl.text),
                            floor: int.tryParse(floorCtrl.text),
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
                        ref.read(galleryFilterProvider.notifier).state =
                            const GalleryFilter();
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
}

class _Thumb extends ConsumerWidget {
  final GalleryItem item;
  const _Thumb({required this.item});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    return InkWell(
      onTap: () => _open(context, item),
      borderRadius: BorderRadius.circular(14),
      child: Container(
        decoration: BoxDecoration(
          color: cs.surfaceContainerHigh,
          border: Border.all(color: cs.outline),
          borderRadius: BorderRadius.circular(14),
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (item.isImage)
              _AuthImage(item: item)
            else
              _FilePlaceholder(item: item, iconSize: 30),
            PositionedDirectional(
              top: 6,
              end: 6,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: .55),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      item.isVideo
                          ? Icons.videocam_outlined
                          : item.isImage
                          ? Icons.photo_outlined
                          : Icons.audiotrack_outlined,
                      size: 11,
                      color: Colors.white,
                    ),
                    const SizedBox(width: 3),
                    Text(
                      tr(
                        ref,
                        item.isVideo
                            ? 'video'
                            : item.isImage
                            ? 'photo'
                            : 'audio',
                      ),
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FilePlaceholder extends ConsumerWidget {
  final GalleryItem item;
  final double iconSize;
  final bool compact;
  const _FilePlaceholder({
    required this.item,
    this.iconSize = 26,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final isVideo = item.isVideo;
    final iconBox = Container(
      width: iconSize + 22,
      height: iconSize + 22,
      decoration: BoxDecoration(
        color: cs.primary.withValues(alpha: .14),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Icon(
        isVideo ? Icons.play_arrow_rounded : Icons.audio_file_outlined,
        size: iconSize,
        color: cs.primary,
      ),
    );
    if (compact) {
      return Container(
        color: cs.primaryContainer.withValues(alpha: .4),
        alignment: Alignment.center,
        child: iconBox,
      );
    }
    return Container(
      color: cs.primaryContainer.withValues(alpha: .4),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          iconBox,
          const SizedBox(height: 6),
          Text(
            tr(ref, isVideo ? 'video' : 'audio'),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: cs.onSurfaceVariant,
            ),
          ),
          if (item.name.isNotEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8),
              child: Text(
                item.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 10,
                  color: cs.onSurfaceVariant.withValues(alpha: .7),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _ListTile extends ConsumerWidget {
  final GalleryItem item;
  const _ListTile({required this.item});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Container(
          width: 52,
          height: 52,
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surfaceContainerHighest,
            borderRadius: BorderRadius.circular(10),
          ),
          clipBehavior: Clip.antiAlias,
          child: item.isImage
              ? _AuthImage(item: item)
              : _FilePlaceholder(item: item, iconSize: 20, compact: true),
        ),
        title: Text(item.name, maxLines: 1, overflow: TextOverflow.ellipsis),
        subtitle: Builder(
          builder: (ctx) {
            final base = Theme.of(ctx).textTheme.bodySmall ?? const TextStyle();
            final oid = item.ownerId;
            return RichText(
              textDirection: TextDirection.ltr,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              text: TextSpan(
                style: base,
                children: [
                  if (oid != null)
                    TextSpan(
                      text: item.ownerName,
                      style: base.copyWith(
                        fontWeight: FontWeight.w700,
                        decoration: TextDecoration.underline,
                      ),
                      recognizer: TapGestureRecognizer()
                        ..onTap = () => openUserProfile(context, oid),
                    )
                  else
                    TextSpan(text: item.ownerName),
                  TextSpan(
                    text:
                        ' · ${tr(ref, 'camera')} ${item.camera ?? '—'} · ${tr(ref, 'floor')} ${item.floor ?? '—'}',
                  ),
                ],
              ),
            );
          },
        ),
        onTap: () => _open(context, item),
      ),
    );
  }
}

void _open(BuildContext context, GalleryItem item) {
  Navigator.push(
    context,
    MaterialPageRoute(builder: (_) => GalleryViewer(item: item)),
  );
}

class _AuthImage extends ConsumerWidget {
  final GalleryItem item;
  const _AuthImage({required this.item});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final base = ref.watch(baseUrlProvider);
    final p = item.viewPath();
    final url = p.startsWith('http') ? p : '$base$p';
    return RasdNetworkImage(
      url: url,
      headers: ref.watch(authImageHeadersProvider),
      isSvg: item.mime == 'image/svg+xml',
    );
  }
}
