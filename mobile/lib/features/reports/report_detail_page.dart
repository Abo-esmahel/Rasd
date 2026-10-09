import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/formal.dart';
import '../../core/models.dart';
import '../../core/reactions.dart';
import '../../core/share_center.dart';
import '../../core/strings.dart';
import '../../core/widgets.dart';
import '../auth/auth_provider.dart';
import '../home/user_profile_page.dart';
import '../notes/note_detail_page.dart';
import '../notes/notes_repo.dart';
import 'report_document_page.dart';
import 'report_edit_page.dart';
import 'reports_providers.dart';
import 'reports_repo.dart';
import 'sheet_art.dart';

class ReportDetailPage extends ConsumerStatefulWidget {
  final int id;

  final bool focusDiscussion;

  const ReportDetailPage({
    super.key,
    required this.id,
    this.focusDiscussion = false,
  });

  @override
  ConsumerState<ReportDetailPage> createState() => _ReportDetailPageState();
}

class _ReportDetailPageState extends ConsumerState<ReportDetailPage> {
  final _scroll = ScrollController();
  final _discussionKey = GlobalKey();
  bool _discussionRequested = false;
  bool _busy = false;
  bool _aiBusy = false;
  bool _reorderMode = false;
  List<Note> _order = [];
  String? _aiError;
  bool _aiRetryable = false;

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _scrollToDiscussion() {
    final ctx = _discussionKey.currentContext;
    if (ctx == null) {
      WidgetsBinding.instance.addPostFrameCallback(
        (_) => _scrollToDiscussion(),
      );
      return;
    }
    Scrollable.ensureVisible(
      ctx,
      duration: const Duration(milliseconds: 380),
      curve: Curves.easeOutCubic,
      alignment: 0.05,
    );
  }

  Future<void> _snack(Future<String?> Function() call) async {
    setState(() => _busy = true);
    final err = await call();
    if (!mounted) return;
    setState(() => _busy = false);
    if (err != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(err)));
    }
  }

  Future<void> _aiSheet(Report r) async {
    TextEditingController? draftController;
    setState(() {
      _aiBusy = true;
      _aiError = null;
      _aiRetryable = false;
    });
    try {
      final data = await ref.read(reportsRepoProvider).preview(reportId: r.id);
      final suggestion = (data['content'] ?? '').toString();
      if (suggestion.trim().isEmpty) {
        throw Exception(tr(ref, 'ai_preview_empty'));
      }
      final text = draftController = TextEditingController(text: suggestion);
      if (!mounted) return;
      setState(() => _aiBusy = false);
      final useSuggestion = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(tr(ref, 'ai_generate_suggestion')),
          content: SizedBox(
            width: 560,
            child: TextField(
              controller: text,
              minLines: 6,
              maxLines: 14,
              maxLength: 20000,
              decoration: InputDecoration(
                labelText: tr(ref, 'content_optional'),
                helperText: tr(ref, 'ai_suggestion_ready'),
                border: const OutlineInputBorder(),
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: Text(tr(ref, 'cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: Text(tr(ref, 'ai_apply_suggestion')),
            ),
          ],
        ),
      );
      final approvedText = text.text.trim();
      text.dispose();
      draftController = null;
      if (useSuggestion == true && mounted) {
        setState(() => _aiBusy = true);
        final err = await ref
            .read(reportDetailProvider(widget.id).notifier)
            .runVoid(() async {
              await ref.read(reportsRepoProvider).update(r.id, {
                'content': approvedText,
              });
            });
        if (err != null) throw Exception(err);
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(tr(ref, 'created_ok'))));
        }
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _aiError = e.toString();
          _aiRetryable = e.retryable;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _aiError = e.toString();
          _aiRetryable = false;
        });
      }
    } finally {
      draftController?.dispose();
      if (mounted) setState(() => _aiBusy = false);
    }
  }

  Future<void> _attachFlow(Report r) async {
    final all = <Note>[];
    var page = 1;
    while (true) {
      final paged = await ref
          .read(notesRepoProvider)
          .list(NoteFilter(status: 'accepted', date: r.reportDate), page);
      all.addAll(paged.items);
      if (!paged.hasMore) break;
      page++;
      if (page > 20) break;
    }
    final attached = r.notes.map((n) => n.id).toSet();
    final avail = all.where((n) => !attached.contains(n.id)).toList();
    if (!mounted) return;
    if (avail.isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(tr(ref, 'no_results'))));
      return;
    }
    final picked = <int>{};
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(tr(ref, 'attach')),
        content: SizedBox(
          width: double.maxFinite,
          height: MediaQuery.of(context).size.height * 0.5,
          child: ListView.builder(
            itemCount: avail.length,
            itemBuilder: (_, i) {
              final n = avail[i];
              return StatefulBuilder(
                builder: (ctx, setS) => CheckboxListTile(
                  value: picked.contains(n.id),
                  onChanged: (v) => setS(() {
                    if (v == true) {
                      picked.add(n.id);
                    } else {
                      picked.remove(n.id);
                    }
                  }),
                  title: Text(
                    '${tr(ref, 'camera')} ${n.cameraNumber} · ${tr(ref, 'floor')} ${n.floorNumber}',
                    textDirection: TextDirection.ltr,
                  ),
                  subtitle: Text(
                    n.description,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              );
            },
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(tr(ref, 'cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(tr(ref, 'confirm')),
          ),
        ],
      ),
    );
    if (ok == true && picked.isNotEmpty) {
      await _snack(
        () => ref
            .read(reportDetailProvider(widget.id).notifier)
            .runVoid(
              () => ref
                  .read(reportsRepoProvider)
                  .attach(widget.id, picked.toList()),
            ),
      );
    }
  }

  Future<void> _saveOrder(Report r) async {
    setState(() => _busy = true);
    try {
      await ref
          .read(reportsRepoProvider)
          .reorder(r.id, _order.map((n) => n.id).toList());
      if (!mounted) return;
      setState(() => _reorderMode = false);
      ref.read(reportDetailProvider(widget.id).notifier).reload();
    } catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final me = ref.watch(authProvider).valueOrNull;
    if (me == null || (!me.isWriter && !me.isGM)) {
      return Scaffold(
        appBar: AppBar(),
        body: Center(child: Text(tr(ref, 'forbidden'))),
      );
    }
    final detail = ref.watch(reportDetailProvider(widget.id));
    final r = detail.valueOrNull;
    return Scaffold(
      appBar: AppBar(
        title: Text(r?.title ?? ''),
        actions: [
          if (r != null) ...[
            if (r.isPublished && (me.isWriter || me.isGM))
              IconButton(
                tooltip: tr(ref, 'document'),
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        ReportDocumentPage(id: r.id, title: r.title),
                  ),
                ),
                icon: const Icon(Icons.print_outlined),
              ),
            IconButton(
              tooltip: tr(ref, 'share_title'),
              onPressed: () =>
                  ShareCenter.sheet(context, ref, ShareCenter.report(ref, r)),
              icon: const Icon(Icons.share_outlined),
            ),
          ],
        ],
      ),
      body: detail.when(
        loading: () => const RasdSkeletonList(count: 3),
        error: (e, _) => RasdError(
          error: e,
          onRetry: () =>
              ref.read(reportDetailProvider(widget.id).notifier).reload(),
          retryLabel: tr(ref, 'retry'),
        ),
        data: (r) {
          if (widget.focusDiscussion && !_discussionRequested) {
            _discussionRequested = true;
            WidgetsBinding.instance.addPostFrameCallback(
              (_) => _scrollToDiscussion(),
            );
          }
          return _reportBody(r, me);
        },
      ),
    );
  }

  Widget _reportBody(Report r, User? me) {
    final isAuthor = me != null && me.id == r.authorId;
    final canManage =
        (me?.isWriter ?? false) && isAuthor && (r.isDraft || !r.locked);
    final canAttach = canManage && r.isDraft;
    final notes = _reorderMode ? _order : r.notes;

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 680),
        child: ListView(
          controller: _scroll,
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          children: [
            Card(
              clipBehavior: Clip.antiAlias,
              elevation: 3,
              shadowColor: Colors.black.withValues(alpha: .12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Stack(
                    children: [
                      SheetCover(report: r, aspectRatio: 21 / 8),
                      Positioned.fill(
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [
                                Colors.transparent,
                                Colors.black.withValues(alpha: .55),
                              ],
                            ),
                          ),
                        ),
                      ),
                      PositionedDirectional(
                        bottom: 10,
                        start: 14,
                        end: 14,
                        child: Text(
                          r.title.isEmpty ? '—' : r.title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 17,
                            fontWeight: FontWeight.w800,
                            shadows: [
                              Shadow(color: Colors.black54, blurRadius: 10),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
                    child: _IdentityPanel(
                      reportNumber: '${r.id}',
                      children: [
                        _DataRow(
                          label: tr(ref, 'report_date_label'),
                          value: r.reportDateTime != null
                              ? RasdFormal.fullDate(
                                  context,
                                  r.reportDateTime as DateTime,
                                )
                              : null,
                          fallback: r.reportDate,
                        ),
                        _DataRow(
                          label: tr(ref, 'report_status'),
                          child: RasdStatusChip(
                            status: r.isPublished ? 'published' : 'draft',
                            label: tr(
                              ref,
                              r.isPublished ? 'published' : 'draft',
                            ),
                          ),
                        ),
                        if ((r.author?.name ?? '').isNotEmpty)
                          _DataRow(
                            label: tr(ref, 'report_author'),
                            child: Align(
                              alignment: AlignmentDirectional.centerStart,
                              child: RasdUserChip(
                                userId: r.author?.id,
                                name: r.author!.name,
                                showAvatar: false,
                                nameStyle: const TextStyle(
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w700,
                                  height: 1.5,
                                ),
                              ),
                            ),
                          ),
                        _DataRow(
                          label: tr(ref, 'notes'),
                          value: '${notes.length}',
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            const _SectionDivider(),
            if (r.summaryBlocks.isNotEmpty)
              _InfoCard(
                icon: Icons.summarize_outlined,
                title: tr(ref, 'executive_summary'),
                body: r.summary ?? '',
                blocks: r.summaryBlocks,
                prominent: true,
              ),
            if (r.recommendationBlocks.isNotEmpty) ...[
              const SizedBox(height: 12),
              _InfoCard(
                icon: Icons.tips_and_updates_outlined,
                title: tr(ref, 'recommendations'),
                body: r.recommendations ?? '',
                blocks: r.recommendationBlocks,
              ),
            ],
            if (r.contentBlocks.isNotEmpty) ...[
              const SizedBox(height: 12),
              _InfoCard(
                icon: Icons.description_outlined,
                title: tr(ref, 'report_body'),
                body: r.content ?? '',
                blocks: r.contentBlocks,
              ),
            ],
            const SizedBox(height: 16),
            const _SectionDivider(),
            if (r.canReact || r.canComment)
              _ReportEngagement(
                key: _discussionKey,
                report: r,
                canComment: r.canComment,
                canReact: r.canReact,
              ),
            const SizedBox(height: 12),
            Row(
              children: [
                Text(
                  '${tr(ref, 'notes')} (${notes.length})',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                const Spacer(),
                if (canAttach && notes.length > 1 && !_reorderMode)
                  TextButton.icon(
                    onPressed: () => setState(() {
                      _reorderMode = true;
                      _order = List.of(r.notes);
                    }),
                    icon: const Icon(Icons.reorder_outlined, size: 18),
                    label: Text(tr(ref, 'reorder')),
                  ),
              ],
            ),
            if (_reorderMode)
              ReorderableListView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: _order.length,
                onReorder: (oldI, newI) => setState(() {
                  if (newI > oldI) newI--;
                  final n = _order.removeAt(oldI);
                  _order.insert(newI, n);
                }),
                itemBuilder: (_, i) {
                  final n = _order[i];
                  return Card(
                    key: ValueKey(n.id),
                    child: ListTile(
                      leading: CircleAvatar(
                        radius: 14,
                        child: Text(
                          '${i + 1}',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      title: Text(
                        '${tr(ref, 'camera')} ${n.cameraNumber} · ${tr(ref, 'floor')} ${n.floorNumber}',
                        textDirection: TextDirection.ltr,
                      ),
                      trailing: const Icon(Icons.drag_handle_outlined),
                    ),
                  );
                },
              )
            else
              ...notes.asMap().entries.map((en) {
                final i = en.key;
                final n = en.value;
                final last = i == notes.length - 1;
                return Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Padding(
                        padding: const EdgeInsetsDirectional.only(start: 40),
                        child: Card(
                          clipBehavior: Clip.antiAlias,
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Padding(
                                padding: const EdgeInsetsDirectional.fromSTEB(
                                  12,
                                  8,
                                  6,
                                  8,
                                ),
                                child: InkWell(
                                  onTap: () => Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) => NoteDetailPage(id: n.id),
                                    ),
                                  ),
                                  child: Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            Text(
                                              '${tr(ref, 'camera')} ${n.cameraNumber} · ${tr(ref, 'floor')} ${n.floorNumber}',
                                              textDirection: TextDirection.ltr,
                                              style: const TextStyle(
                                                fontWeight: FontWeight.bold,
                                                fontSize: 14,
                                              ),
                                            ),
                                            if (n.description
                                                .trim()
                                                .isNotEmpty) ...[
                                              const SizedBox(height: 2),
                                              Text(
                                                n.description,
                                                maxLines: 2,
                                                overflow: TextOverflow.ellipsis,
                                                style: Theme.of(
                                                  context,
                                                ).textTheme.bodyMedium,
                                              ),
                                            ],
                                            if (n.observedAt != null ||
                                                (n.observedAtRaw ?? '')
                                                    .trim()
                                                    .isNotEmpty) ...[
                                              const SizedBox(height: 5),
                                              Row(
                                                children: [
                                                  Icon(
                                                    Icons.schedule_outlined,
                                                    size: 13,
                                                    color: Theme.of(context)
                                                        .colorScheme
                                                        .onSurfaceVariant,
                                                  ),
                                                  const SizedBox(width: 4),
                                                  Expanded(
                                                    child: Text(
                                                      '${tr(ref, 'observation_time')}: ${n.observedAt != null ? RasdFormal.dateTime(context, n.observedAt!) : n.observedAtRaw!.trim()}',
                                                      maxLines: 1,
                                                      overflow:
                                                          TextOverflow.ellipsis,
                                                      style: Theme.of(context)
                                                          .textTheme
                                                          .labelMedium
                                                          ?.copyWith(
                                                            color: Theme.of(context)
                                                                .colorScheme
                                                                .onSurfaceVariant,
                                                          ),
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ],
                                          ],
                                        ),
                                      ),
                                      if (canAttach)
                                        IconButton(
                                          icon: const Icon(
                                            Icons.remove_circle_outline,
                                          ),
                                          onPressed: _busy
                                              ? null
                                              : () => _snack(
                                                  () => ref
                                                      .read(
                                                        reportDetailProvider(
                                                          widget.id,
                                                        ).notifier,
                                                      )
                                                      .runVoid(
                                                        () => ref
                                                            .read(
                                                              reportsRepoProvider,
                                                            )
                                                            .detach(
                                                              widget.id,
                                                              n.id,
                                                            ),
                                                      ),
                                                ),
                                        ),
                                    ],
                                  ),
                                ),
                              ),
                              if (r.canReact)
                                _ReportNoteReactionBar(
                                  note: n,
                                  onChanged: () => ref
                                      .read(
                                        reportDetailProvider(
                                          widget.id,
                                        ).notifier,
                                      )
                                      .reload(),
                                ),
                            ],
                          ),
                        ),
                      ),
                      PositionedDirectional(
                        top: 0,
                        start: 0,
                        child: Container(
                          width: 30,
                          height: 30,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: Theme.of(
                              context,
                            ).colorScheme.primaryContainer,
                          ),
                          child: Text(
                            '${i + 1}',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: Theme.of(
                                context,
                              ).colorScheme.onPrimaryContainer,
                            ),
                          ),
                        ),
                      ),
                      if (!last)
                        PositionedDirectional(
                          top: 34,
                          bottom: -10,
                          start: 14,
                          width: 2,
                          child: DecoratedBox(
                            decoration: BoxDecoration(
                              color: Theme.of(context)
                                  .colorScheme
                                  .outlineVariant
                                  .withValues(alpha: .6),
                              borderRadius: BorderRadius.circular(99),
                            ),
                          ),
                        ),
                    ],
                  ),
                );
              }),
            const SizedBox(height: 16),
            if (_reorderMode)
              Row(
                children: [
                  Expanded(
                    child: FilledButton(
                      onPressed: _busy ? null : () => _saveOrder(r),
                      child: Text(tr(ref, 'save_order')),
                    ),
                  ),
                  const SizedBox(width: 10),
                  TextButton(
                    onPressed: () => setState(() => _reorderMode = false),
                    child: Text(tr(ref, 'cancel')),
                  ),
                ],
              )
            else if (_busy || _aiBusy)
              const Center(child: CircularProgressIndicator())
            else
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  if (canManage) ...[
                    if (r.isDraft)
                      FilledButton(
                        onPressed: () => _snack(
                          () => ref
                              .read(reportDetailProvider(widget.id).notifier)
                              .run(
                                () => ref
                                    .read(reportsRepoProvider)
                                    .publish(widget.id),
                              ),
                        ),
                        child: Text(tr(ref, 'publish')),
                      ),
                    if (r.isPublished && !r.locked)
                      OutlinedButton(
                        onPressed: () => _snack(
                          () => ref
                              .read(reportDetailProvider(widget.id).notifier)
                              .run(
                                () => ref
                                    .read(reportsRepoProvider)
                                    .unpublish(widget.id),
                              ),
                        ),
                        child: Text(tr(ref, 'unpublish')),
                      ),
                    if (canAttach)
                      OutlinedButton.icon(
                        onPressed: () => _attachFlow(r),
                        icon: const Icon(Icons.add, size: 18),
                        label: Text(tr(ref, 'attach')),
                      ),
                    if (r.isDraft && r.notes.isNotEmpty)
                      OutlinedButton.icon(
                        onPressed: _aiBusy ? null : () => _aiSheet(r),
                        icon: const Icon(Icons.auto_awesome_outlined, size: 18),
                        label: Text(tr(ref, 'ai_generate_suggestion')),
                      ),
                    OutlinedButton.icon(
                      onPressed: () async {
                        final ok = await Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => ReportEditPage(report: r),
                          ),
                        );
                        if (ok == true) {
                          ref
                              .read(reportDetailProvider(widget.id).notifier)
                              .reload();
                        }
                      },
                      icon: const Icon(Icons.edit_outlined, size: 18),
                      label: Text(tr(ref, 'edit')),
                    ),
                    OutlinedButton.icon(
                      onPressed: () => _delete(r),
                      icon: const Icon(Icons.delete_outline, size: 18),
                      label: Text(tr(ref, 'delete')),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ],
                ],
              ),
            if (_aiError != null) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.errorContainer,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _aiError!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.onErrorContainer,
                      ),
                    ),
                    if (_aiRetryable) ...[
                      const SizedBox(height: 8),
                      FilledButton(
                        onPressed: () => _aiSheet(r),
                        child: Text(tr(ref, 'retry')),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _delete(Report r) async {
    final lang = ref.read(localeProvider);
    final ok = await confirmDestructive(
      context,
      title: trOf(lang, 'delete'),
      body: trOf(lang, 'delete_confirm'),
      confirmLabel: trOf(lang, 'delete'),
      cancelLabel: trOf(lang, 'cancel'),
    );
    if (!ok) return;
    setState(() => _busy = true);
    try {
      await ref.read(reportsRepoProvider).delete(r.id);
      ref.read(reportsListProvider.notifier).refresh();
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }
}

class _ReportNoteReactionBar extends ConsumerStatefulWidget {
  final Note note;
  final VoidCallback onChanged;
  const _ReportNoteReactionBar({required this.note, required this.onChanged});
  @override
  ConsumerState<_ReportNoteReactionBar> createState() =>
      _ReportNoteReactionBarState();
}

class _ReportNoteReactionBarState
    extends ConsumerState<_ReportNoteReactionBar> {
  bool _busy = false;

  Future<void> _react(String key) async {
    setState(() => _busy = true);
    try {
      await ref.read(notesRepoProvider).react(widget.note.id, key);
      widget.onChanged();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        border: Border(
          top: BorderSide(color: cs.outlineVariant.withValues(alpha: .5)),
        ),
      ),
      padding: const EdgeInsetsDirectional.fromSTEB(12, 8, 12, 10),
      child: Align(
        alignment: AlignmentDirectional.centerStart,
        child: RasdReactionBar(
          counts: widget.note.reactionCounts,
          myReaction: widget.note.myReaction,
          busy: _busy,
          compact: true,
          lang: ref.watch(localeProvider),
          onReact: _react,
        ),
      ),
    );
  }
}

class _ReportEngagement extends ConsumerStatefulWidget {
  final Report report;
  final bool canComment;
  final bool canReact;
  const _ReportEngagement({
    super.key,
    required this.report,
    required this.canComment,
    required this.canReact,
  });
  @override
  ConsumerState<_ReportEngagement> createState() => _ReportEngagementState();
}

class _ReportEngagementState extends ConsumerState<_ReportEngagement> {
  final _controller = TextEditingController();
  final _replyController = TextEditingController();
  int? _replyTo;
  String? _sendError;
  bool _busy = false;

  @override
  void dispose() {
    _controller.dispose();
    _replyController.dispose();
    super.dispose();
  }

  Future<void> _act(Future<void> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
      await ref
          .read(reportDetailProvider(widget.report.id).notifier)
          .reload(preserve: true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _sendMainComment() async {
    final body = _controller.text.trim();
    if (body.isEmpty || _busy) return;
    setState(() {
      _busy = true;
      _sendError = null;
    });
    try {
      await ref.read(reportsRepoProvider).comment(widget.report.id, body);
      _controller.clear();
      await ref
          .read(reportDetailProvider(widget.report.id).notifier)
          .reload(preserve: true);
    } catch (e) {
      if (mounted) setState(() => _sendError = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.report;
    final repo = ref.read(reportsRepoProvider);
    final cs = Theme.of(context).colorScheme;
    return Card(
      margin: const EdgeInsets.only(top: 14),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.forum_outlined, color: cs.primary),
                const SizedBox(width: 8),
                Text(
                  tr(ref, 'report_engagement'),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            if (widget.canReact)
              RasdReactionBar(
                counts: r.reactionCounts,
                myReaction: r.myReaction,
                busy: _busy,
                compact: false,
                lang: ref.watch(localeProvider),
                onReact: (key) => _act(() => repo.reactReport(r.id, key)),
              ),
            const Divider(height: 26),
            Row(
              children: [
                const Icon(Icons.chat_bubble_outline, size: 19),
                const SizedBox(width: 8),
                Text(
                  '${tr(ref, 'comments')} (${r.comments.length})',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ],
            ),
            if (r.comments.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 14),
                child: Text(
                  tr(ref, 'no_comments'),
                  style: TextStyle(color: cs.onSurfaceVariant),
                ),
              ),
            ...r.comments.map(
              (c) => Padding(
                padding: const EdgeInsets.only(top: 12),
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: cs.surfaceContainerHighest.withValues(alpha: .45),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            Flexible(
                              child: RasdUserChip(
                                userId: c.authorId,
                                avatarUrl: c.avatarUrl,
                                name: c.author ?? tr(ref, 'member'),
                                size: 32,
                                showName: true,
                                gap: 8,
                                nameStyle: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 13.5,
                                ),
                              ),
                            ),
                            const SizedBox(width: 6),
                            RasdTimestamp(at: c.createdAt),
                          ],
                        ),
                        const SizedBox(height: 7),
                        Text(
                          c.body,
                          style: const TextStyle(height: 1.65, fontSize: 13.5),
                        ),
                        if (c.replies.isNotEmpty)
                          Container(
                            margin: const EdgeInsetsDirectional.only(
                              start: 14,
                              top: 10,
                            ),
                            padding: const EdgeInsetsDirectional.only(
                              start: 10,
                            ),
                            decoration: BoxDecoration(
                              border: BorderDirectional(
                                start: BorderSide(
                                  color: cs.primary.withValues(alpha: .28),
                                  width: 2,
                                ),
                              ),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                for (final reply in c.replies)
                                  Padding(
                                    padding: const EdgeInsets.only(bottom: 9),
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.center,
                                          children: [
                                            Flexible(
                                              child: RasdUserChip(
                                                userId: reply.authorId,
                                                avatarUrl: reply.avatarUrl,
                                                name:
                                                    reply.author ??
                                                    tr(ref, 'member'),
                                                size: 22,
                                                showName: true,
                                                gap: 7,
                                                nameStyle: const TextStyle(
                                                  fontWeight:
                                                      FontWeight.w700,
                                                  fontSize: 12.5,
                                                ),
                                              ),
                                            ),
                                            const SizedBox(width: 4),
                                            RasdTimestamp(
                                              at: reply.createdAt,
                                              dense: true,
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 3),
                                        Text(
                                          reply.body,
                                          style: const TextStyle(
                                            height: 1.55,
                                            fontSize: 13,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        if (widget.canReact) ...[
                          const SizedBox(height: 10),
                          RasdReactionBar(
                            counts: c.reactionCounts,
                            myReaction: c.myReaction,
                            busy: _busy,
                            compact: true,
                            lang: ref.watch(localeProvider),
                            onReact: (key) =>
                                _act(() => repo.reactComment(c.id, key)),
                          ),
                        ],
                        if (widget.canComment) ...[
                          Align(
                            alignment: AlignmentDirectional.centerEnd,
                            child: TextButton.icon(
                              onPressed: _busy
                                  ? null
                                  : () => setState(() {
                                      _replyTo = _replyTo == c.id ? null : c.id;
                                      _replyController.clear();
                                      _sendError = null;
                                    }),
                              icon: const Icon(Icons.reply_rounded, size: 17),
                              label: Text(tr(ref, 'reply')),
                            ),
                          ),
                          if (_replyTo == c.id)
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: TextField(
                                    controller: _replyController,
                                    minLines: 1,
                                    maxLines: 3,
                                    maxLength: 3000,
                                    decoration: InputDecoration(
                                      hintText: tr(ref, 'reply_hint'),
                                      border: OutlineInputBorder(
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      isDense: true,
                                    ),
                                  ),
                                ),
                                IconButton.filledTonal(
                                  tooltip: tr(ref, 'send'),
                                  onPressed: _busy
                                      ? null
                                      : () async {
                                          final body = _replyController.text
                                              .trim();
                                          if (body.isEmpty) return;
                                          setState(() {
                                            _busy = true;
                                            _sendError = null;
                                          });
                                          try {
                                            await repo.comment(
                                              r.id,
                                              body,
                                              parentId: c.id,
                                            );
                                            if (!mounted) return;
                                            _replyController.clear();
                                            setState(() => _replyTo = null);
                                            await ref
                                                .read(
                                                  reportDetailProvider(
                                                    r.id,
                                                  ).notifier,
                                                )
                                                .reload(preserve: true);
                                          } catch (e) {
                                            if (context.mounted) {
                                              ScaffoldMessenger.of(
                                                context,
                                              ).showSnackBar(
                                                SnackBar(
                                                  content: Text(e.toString()),
                                                ),
                                              );
                                            }
                                          } finally {
                                            if (mounted) {
                                              setState(() => _busy = false);
                                            }
                                          }
                                        },
                                  icon: _busy
                                      ? const SizedBox(
                                          width: 16,
                                          height: 16,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                          ),
                                        )
                                      : const Icon(
                                          Icons.send_rounded,
                                          size: 18,
                                        ),
                                ),
                              ],
                            ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
            ),
            if (widget.canComment) ...[
              const SizedBox(height: 12),
              TextField(
                controller: _controller,
                minLines: 1,
                maxLines: 4,
                maxLength: 3000,
                decoration: InputDecoration(
                  hintText: tr(ref, 'comment_hint'),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                  contentPadding: const EdgeInsets.all(12),
                ),
              ),
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: FilledButton.icon(
                  onPressed: _busy ? null : _sendMainComment,
                  icon: _busy
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.send_rounded, size: 17),
                  label: Text(tr(ref, 'add_comment')),
                ),
              ),
              if (_sendError != null) ...[
                Text(_sendError!, style: TextStyle(color: cs.error)),
                Align(
                  alignment: AlignmentDirectional.centerEnd,
                  child: TextButton.icon(
                    onPressed: _busy
                        ? null
                        : () {
                            final body = _controller.text.trim();
                            if (body.isEmpty) return;
                            _sendMainComment();
                          },
                    icon: const Icon(Icons.refresh_rounded),
                    label: Text(tr(ref, 'retry')),
                  ),
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

class _SectionDivider extends StatelessWidget {
  const _SectionDivider();

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final line = Expanded(
      child: Container(
        height: 1,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(99),
          gradient: LinearGradient(
            colors: [
              cs.outlineVariant.withValues(alpha: 0),
              cs.primary.withValues(alpha: .30),
              cs.outlineVariant.withValues(alpha: 0),
            ],
          ),
        ),
      ),
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: line,
    );
  }
}

class _IdentityPanel extends StatelessWidget {
  final String reportNumber;
  final List<Widget> children;
  const _IdentityPanel({required this.reportNumber, required this.children});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

    return Container(
      decoration: BoxDecoration(
        color: cs.surfaceContainerHighest.withValues(alpha: .45),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: cs.primary.withValues(alpha: .14)),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
            decoration: BoxDecoration(
              color: cs.primary.withValues(alpha: .10),
              border: Border(
                bottom: BorderSide(color: cs.primary.withValues(alpha: .16)),
              ),
            ),
            child: Row(
              children: [
                Icon(Icons.badge_outlined, size: 15, color: cs.primary),
                const SizedBox(width: 7),
                Expanded(
                  child: Text(
                    trc(context, 'report_data'),
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: cs.primary,
                    ),
                  ),
                ),
                Text(
                  '${trc(context, 'report_number')} $reportNumber',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: cs.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          for (var i = 0; i < children.length; i++) ...[
            if (i > 0)
              Divider(
                height: 1,
                thickness: 1,
                indent: 14,
                endIndent: 14,
                color: cs.outlineVariant.withValues(alpha: .35),
              ),
            children[i],
          ],
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 10),
            child: Row(
              children: [
                Icon(
                  Icons.verified_outlined,
                  size: 13,
                  color: cs.onSurfaceVariant.withValues(alpha: .7),
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    trc(context, 'report_identity_source'),
                    style: TextStyle(
                      fontSize: 11,
                      color: cs.onSurfaceVariant.withValues(alpha: .8),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DataRow extends StatelessWidget {
  final String label;
  final String? value;
  final String? fallback;
  final Widget? child;
  const _DataRow({required this.label, this.value, this.fallback, this.child});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final text = (value != null && value!.trim().isNotEmpty)
        ? value!.trim()
        : (fallback ?? '—');
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 108,
            child: Text(
              label,
              style: TextStyle(
                fontSize: 12.5,
                color: cs.onSurfaceVariant,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child:
                child ??
                Text(
                  text,
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    height: 1.5,
                  ),
                ),
          ),
        ],
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String body;
  final List<ReportTextBlock> blocks;
  final bool prominent;
  const _InfoCard({
    required this.icon,
    required this.title,
    this.body = '',
    this.blocks = const [],
    this.prominent = false,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final surface = prominent
        ? cs.primaryContainer.withValues(alpha: .55)
        : cs.surfaceContainerHighest.withValues(alpha: .5);
    return Container(
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: cs.primary.withValues(alpha: prominent ? .30 : .16),
        ),
      ),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (prominent)
              Container(width: 4, color: cs.primary.withValues(alpha: .75)),
            Expanded(
              child: Padding(
                padding: EdgeInsets.fromLTRB(
                  prominent ? 14 : 16,
                  prominent ? 14 : 12,
                  16,
                  prominent ? 16 : 14,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(icon, size: 17, color: cs.primary),
                        const SizedBox(width: 7),
                        Expanded(
                          child: Text(
                            title,
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: prominent ? 15.5 : 14.5,
                              color: cs.primary,
                              letterSpacing: .1,
                            ),
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: prominent ? 12 : 8),
                    _RichText(
                      blocks: blocks,
                      fallback: body,
                      accent: cs.primary,
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

class _RichText extends StatelessWidget {
  final List<ReportTextBlock> blocks;
  final String fallback;
  final Color accent;
  const _RichText({
    required this.blocks,
    this.fallback = '',
    required this.accent,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final items = blocks.isNotEmpty
        ? blocks
        : (fallback.trim().isEmpty
              ? const <ReportTextBlock>[]
              : [ReportTextBlock(type: 'paragraph', text: fallback.trim())]);

    if (items.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var i = 0; i < items.length; i++) ...[
          if (i > 0) SizedBox(height: items[i - 1].isHeading ? 6 : 10),
          _block(context, items[i], theme),
        ],
      ],
    );
  }

  Widget _block(BuildContext context, ReportTextBlock b, ThemeData theme) {
    if (b.isHeading) {
      return Text(
        b.text,
        style: theme.textTheme.titleSmall?.copyWith(
          fontWeight: FontWeight.w800,
          color: accent,
        ),
      );
    }

    if (b.isItem) {
      return Padding(
        padding: const EdgeInsetsDirectional.only(start: 2),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 22,
              child: Text(
                b.order > 0 ? '${b.order}.' : '•',
                textAlign: TextAlign.start,
                style: TextStyle(
                  height: 1.9,
                  fontWeight: FontWeight.w800,
                  color: accent,
                ),
              ),
            ),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                b.text,
                style: const TextStyle(height: 1.9, fontSize: 14.5),
              ),
            ),
          ],
        ),
      );
    }

    return Text(
      b.text,
      style: const TextStyle(height: 1.9, fontSize: 14.5),
      textAlign: TextAlign.start,
    );
  }
}
