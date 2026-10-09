import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/strings.dart';
import '../../core/widgets.dart';
import 'submission_detail_page.dart';
import 'submissions_repo.dart';

const _tabs = [null, 'draft', 'pending', 'accepted', 'rejected'];

class SubmissionsPage extends ConsumerStatefulWidget {
  const SubmissionsPage({super.key});

  @override
  ConsumerState<SubmissionsPage> createState() => _SubmissionsPageState();
}

class _SubmissionsPageState extends ConsumerState<SubmissionsPage> {
  final _scroll = ScrollController();
  final _searchCtrl = TextEditingController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 400) {
        ref.read(submissionsListProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _onSearch(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      final q = v.trim();
      final cur = ref.read(submissionFilterProvider);
      ref.read(submissionFilterProvider.notifier).state =
          cur.copyWith(q: q.isEmpty ? null : q, clearQ: q.isEmpty);
    });
  }

  @override
  Widget build(BuildContext context) {
    final list = ref.watch(submissionsListProvider);
    final filter = ref.watch(submissionFilterProvider);
    final notifier = ref.read(submissionsListProvider.notifier);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 0),
          child: Row(
            children: [
              Expanded(
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
                              ref
                                  .read(submissionFilterProvider.notifier)
                                  .state = filter.copyWith(clearQ: true);
                            },
                          ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Badge(
                isLabelVisible: filter.activeCount > 0,
                label: Text('${filter.activeCount}'),
                child: IconButton.filledTonal(
                  tooltip: tr(ref, 'filter'),
                  onPressed: () => _openFilter(context, filter),
                  icon: const Icon(Icons.filter_list_outlined, size: 20),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 6),
        SizedBox(
          height: 48,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
            children: _tabs.map((t) {
              final active = filter.status == t;
              return Padding(
                padding: const EdgeInsetsDirectional.only(end: 8),
                child: ChoiceChip(
                  label: Text(t == null ? tr(ref, 'all') : tr(ref, t)),
                  selected: active,
                  onSelected: (_) {
                    ref.read(submissionFilterProvider.notifier).state =
                        filter.copyWith(status: t, clearStatus: t == null);
                  },
                ),
              );
            }).toList(),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => notifier.refresh(),
            child: list.when(
              loading: () => const RasdSkeletonList(),
              error: (e, _) => RasdError(
                error: e,
                onRetry: () => notifier.refresh(),
                retryLabel: tr(ref, 'retry'),
              ),
              data: (items) => items.isEmpty
                  ? RasdEmpty(message: tr(ref, 'no_results'))
                  : ListView.builder(
                      controller: _scroll,
                      padding: const EdgeInsets.fromLTRB(12, 4, 12, 88),
                      itemCount: items.length,
                      itemBuilder: (_, i) {
                        final s = items[i];
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: RasdCard(
                            padding: const EdgeInsets.all(16),
                            onTap: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                  builder: (_) =>
                                      SubmissionDetailPage(id: s.id)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 10, vertical: 5),
                                      decoration: BoxDecoration(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .secondaryContainer
                                            .withValues(alpha: .6),
                                        borderRadius:
                                            BorderRadius.circular(999),
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Icon(
                                            Icons.videocam_rounded,
                                            size: 14,
                                            color: Theme.of(context)
                                                .colorScheme
                                                .onSecondaryContainer,
                                          ),
                                          const SizedBox(width: 5),
                                          Text(
                                            '${tr(ref, 'camera')} ${s.cameraNumber} · ${tr(ref, 'floor')} ${s.floorNumber}',
                                            textDirection: TextDirection.ltr,
                                            style: TextStyle(
                                              fontWeight: FontWeight.w800,
                                              fontSize: 13,
                                              color: Theme.of(context)
                                                  .colorScheme
                                                  .onSecondaryContainer,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const Spacer(),
                                    RasdStatusChip(
                                      status: s.status,
                                      label: tr(ref, s.status),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 10),
                                Text(
                                  s.description,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                      fontSize: 14, height: 1.9),
                                ),
                                if (s.attachments.isNotEmpty) ...[
                                  const SizedBox(height: 8),
                                  Row(
                                    children: [
                                      Icon(
                                        Icons.attach_file_rounded,
                                        size: 14,
                                        color: Theme.of(context)
                                            .colorScheme
                                            .onSurfaceVariant,
                                      ),
                                      const SizedBox(width: 4),
                                      Text(
                                        '${s.attachments.length}',
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodySmall,
                                      ),
                                    ],
                                  ),
                                ],
                              ],
                            ),
                          ),
                        );
                      },
                    ),
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _openFilter(
      BuildContext context, SubmissionFilter filter) async {
    final dateCtrl = TextEditingController(text: filter.date ?? '');
    final floorCtrl =
        TextEditingController(text: filter.floor?.toString() ?? '');
    final camCtrl =
        TextEditingController(text: filter.camera?.toString() ?? '');
    String sort = filter.sort;
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setS) => Padding(
          padding: EdgeInsets.fromLTRB(
              16, 16, 16, 16 + MediaQuery.of(ctx).viewInsets.bottom),
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
                    setS(() => dateCtrl.text =
                        '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}');
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
                      decoration:
                          InputDecoration(labelText: tr(ref, 'floor')),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: camCtrl,
                      keyboardType: TextInputType.number,
                      decoration:
                          InputDecoration(labelText: tr(ref, 'camera')),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(tr(ref, 'sort_by'),
                  style: Theme.of(ctx).textTheme.labelLarge),
              const SizedBox(height: 6),
              SegmentedButton<String>(
                segments: [
                  ButtonSegment(
                      value: 'latest', label: Text(tr(ref, 'sort_newest'))),
                  ButtonSegment(
                      value: 'oldest', label: Text(tr(ref, 'sort_oldest'))),
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
                        ref.read(submissionFilterProvider.notifier).state =
                            filter.copyWith(
                          date: dateCtrl.text.isEmpty ? null : dateCtrl.text,
                          clearDate: dateCtrl.text.isEmpty,
                          floor: int.tryParse(floorCtrl.text),
                          clearFloor: floorCtrl.text.isEmpty,
                          camera: int.tryParse(camCtrl.text),
                          clearCamera: camCtrl.text.isEmpty,
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
                      ref.read(submissionFilterProvider.notifier).state =
                          filter.copyWith(
                        clearDate: true,
                        clearFloor: true,
                        clearCamera: true,
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
    );
  }
}
