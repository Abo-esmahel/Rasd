import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models.dart';
import '../../core/api/api_client.dart';
import '../../core/config.dart';
import '../../core/reactions.dart';
import '../../core/server_pdf.dart';
import '../../core/share_center.dart';
import '../../core/strings.dart';
import '../../core/wall_clock.dart';
import '../../core/widgets.dart';
import '../auth/auth_provider.dart';
import '../home/user_profile_page.dart';
import 'attachment_viewer.dart';
import 'note_edit_page.dart';
import 'note_print_page.dart';
import 'notes_providers.dart';
import 'notes_repo.dart';

class NoteDetailPage extends ConsumerWidget {
  final int id;
  const NoteDetailPage({super.key, required this.id});

  String _head(WidgetRef ref, Note n) =>
      '${tr(ref, 'camera')} ${n.cameraNumber} · ${tr(ref, 'floor')} ${n.floorNumber}';

  void _openPrint(BuildContext context, WidgetRef ref, Note n) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => NotePrintPage(
          title: '${tr(ref, 'print_note')} #${n.id}',
          kind: ServerPdfKind.note,
          id: n.id,
          fileName: 'note-${n.id}.pdf',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(noteDetailProvider(id));
    final title = detail.valueOrNull == null
        ? ''
        : _head(ref, detail.valueOrNull!);
    final me = ref.watch(authProvider).valueOrNull;
    final canDl = (me?.isWriter ?? false) || (me?.isGM ?? false);
    final note = detail.valueOrNull;
    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        actions: [
          if (note != null) ...[
            IconButton(
              tooltip: tr(ref, 'print_note'),
              onPressed: () => _openPrint(context, ref, note),
              icon: const Icon(Icons.print_outlined),
            ),
            IconButton(
              tooltip: tr(ref, 'share_title'),
              onPressed: () => ShareCenter.sheet(
                context,
                ref,
                ShareCenter.note(ref, note, canDl),
              ),
              icon: const Icon(Icons.share_outlined),
            ),
          ],
        ],
      ),
      body: detail.when(
        loading: () => const RasdSkeletonList(count: 3),
        error: (e, _) => RasdError(
          error: e,
          onRetry: () => ref.read(noteDetailProvider(id).notifier).reload(),
          retryLabel: tr(ref, 'retry'),
        ),
        data: (note) => Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 680),
            child: _Body(note: note),
          ),
        ),
      ),
    );
  }
}

class _Body extends ConsumerStatefulWidget {
  final Note note;
  const _Body({required this.note});

  @override
  ConsumerState<_Body> createState() => _BodyState();
}

class _BodyState extends ConsumerState<_Body> {
  bool _busy = false;

  Future<void> _run(Future<Note> Function() call) async {
    setState(() => _busy = true);
    final err = await ref
        .read(noteDetailProvider(widget.note.id).notifier)
        .run(call);
    if (!mounted) return;
    setState(() => _busy = false);
    if (err != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(err)));
    }
  }

  Future<void> _delete() async {
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
      await ref.read(notesRepoProvider).delete(widget.note.id);
      ref.read(notesListProvider.notifier).refresh();
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

  Future<void> _askReject() async {
    final lang = ref.read(localeProvider);
    final ctrl = TextEditingController();
    String? fieldError;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setS) => AlertDialog(
          title: Text(trOf(lang, 'reject_reason')),
          content: TextField(
            controller: ctrl,
            maxLines: 3,
            maxLength: 1000,
            decoration: InputDecoration(
              hintText: trOf(lang, 'reject_reason'),
              errorText: fieldError,
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: Text(trOf(lang, 'cancel')),
            ),
            FilledButton(
              onPressed: () {
                final err = ctrl.text.trim().length < 5
                    ? trOf(lang, 'reject_reason_short')
                    : null;
                if (err != null) {
                  setS(() => fieldError = err);
                  return;
                }
                Navigator.pop(ctx, true);
              },
              child: Text(trOf(lang, 'confirm')),
            ),
          ],
        ),
      ),
    );
    if (ok == true) {
      await _run(
        () => ref
            .read(notesRepoProvider)
            .reject(widget.note.id, ctrl.text.trim()),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final note = widget.note;
    final me = ref.watch(authProvider).valueOrNull;
    final repo = ref.read(notesRepoProvider);
    final cs = Theme.of(context).colorScheme;
    final ownerName = note.owner?.name ?? '—';
    final parts = Wall.parts(note.observedAtRaw);

    final myId = me?.id;
    final isOwner = myId != null && myId == note.userId;
    final isWriter = me?.isWriter ?? false;

    final canSend = isOwner && note.isDraft;
    final canResend = isOwner && note.isRejected;
    final canAcceptReject = isWriter && note.isPending;
    final canEdit =
        isOwner &&
        !note.isRejected &&
        (!note.isAccepted || note.processedBy == myId);
    final canDelete = isOwner && note.isDraft;
    final canAddAtt =
        isOwner &&
        !note.isRejected &&
        (!note.isAccepted || note.processedBy == me?.id);

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            RasdUserChip(
              userId: note.owner?.id,
              avatarUrl: note.owner?.avatarUrl,
              name: ownerName,
              size: 44,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  RasdUserChip(
                    userId: note.owner?.id,
                    name: ownerName,
                    showAvatar: false,
                    nameStyle: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      height: 1.4,
                    ),
                  ),
                  if (parts.date.isNotEmpty)
                    Text(
                      parts.date,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                ],
              ),
            ),
            RasdStatusChip(status: note.status, label: tr(ref, note.status)),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Icon(Icons.videocam_outlined, size: 15, color: cs.onSurfaceVariant),
            const SizedBox(width: 5),
            Text(
              '${tr(ref, 'camera')} ${note.cameraNumber}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(width: 12),
            Icon(Icons.layers_outlined, size: 15, color: cs.onSurfaceVariant),
            const SizedBox(width: 5),
            Text(
              '${tr(ref, 'floor')} ${note.floorNumber}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            if (note.attachments.isNotEmpty) ...[
              const SizedBox(width: 12),
              Icon(Icons.attach_file, size: 15, color: cs.onSurfaceVariant),
              const SizedBox(width: 5),
              Text(
                '${note.attachments.length}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ],
        ),
        const SizedBox(height: 4),
        _ObservedRow(note: note),
        const SizedBox(height: 10),
        _NoteTranslationPanel(
          key: ValueKey('translation-${note.id}-${ref.watch(localeProvider)}'),
          note: note,
        ),
        const SizedBox(height: 12),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Theme.of(
              context,
            ).colorScheme.surfaceContainerHighest.withValues(alpha: .5),
            borderRadius: BorderRadius.circular(16),
          ),
          child: SelectableText(
            note.description,
            style: const TextStyle(height: 1.9, fontSize: 15),
          ),
        ),
        if (note.canReact) _NoteReactionBar(note: note),
        if (note.isRejected && note.rejectionReason != null) ...[
          const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.errorContainer,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  tr(ref, 'reject_reason'),
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 4),
                Text(note.rejectionReason!),
              ],
            ),
          ),
        ],
        if (note.attachments.isNotEmpty) ...[
          const SizedBox(height: 12),
          Text(
            tr(ref, 'attachments'),
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              crossAxisSpacing: 8,
              mainAxisSpacing: 8,
            ),
            itemCount: note.attachments.length,
            itemBuilder: (_, i) {
              final a = note.attachments[i];
              return InkWell(
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => AttachmentViewer(
                      att: a,
                      canDownload:
                          (me?.isWriter ?? false) || (me?.isGM ?? false),
                    ),
                  ),
                ),
                child: _Thumb(att: a),
              );
            },
          ),
        ],
        const SizedBox(height: 20),
        if (_busy)
          const Center(child: CircularProgressIndicator())
        else
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              if (canSend)
                FilledButton(
                  onPressed: () => _run(() => repo.send(note.id)),
                  child: Text(tr(ref, 'send')),
                ),
              if (canResend)
                FilledButton(
                  onPressed: () => _run(() => repo.resend(note.id)),
                  child: Text(tr(ref, 'resend')),
                ),
              if (canAcceptReject) ...[
                FilledButton(
                  onPressed: () => _run(() => repo.accept(note.id)),
                  child: Text(tr(ref, 'accept')),
                ),
                OutlinedButton(
                  onPressed: _askReject,
                  child: Text(tr(ref, 'reject')),
                ),
              ],
              if (canEdit)
                OutlinedButton.icon(
                  onPressed: () async {
                    final ok = await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => NoteEditPage(note: note),
                      ),
                    );
                    if (ok == true) {
                      ref.read(noteDetailProvider(note.id).notifier).reload();
                    }
                  },
                  icon: const Icon(Icons.edit_outlined, size: 18),
                  label: Text(tr(ref, 'edit')),
                ),
              if (canAddAtt)
                OutlinedButton.icon(
                  onPressed: () async {
                    final ok = await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => NoteEditPage(note: note),
                      ),
                    );
                    if (ok == true) {
                      ref.read(noteDetailProvider(note.id).notifier).reload();
                    }
                  },
                  icon: const Icon(Icons.attach_file, size: 18),
                  label: Text(tr(ref, 'attachments')),
                ),
              if (canDelete)
                OutlinedButton.icon(
                  onPressed: _delete,
                  icon: const Icon(Icons.delete_outline, size: 18),
                  label: Text(tr(ref, 'delete')),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Theme.of(context).colorScheme.error,
                  ),
                ),
            ],
          ),
      ],
    );
  }
}

class _NoteReactionBar extends ConsumerStatefulWidget {
  final Note note;
  const _NoteReactionBar({required this.note});
  @override
  ConsumerState<_NoteReactionBar> createState() => _NoteReactionBarState();
}

class _NoteReactionBarState extends ConsumerState<_NoteReactionBar> {
  bool _busy = false;
  late Map<String, int> _counts;
  String? _mine;

  @override
  void initState() {
    super.initState();
    _counts = Map<String, int>.from(widget.note.reactionCounts);
    _mine = widget.note.myReaction;
  }

  @override
  void didUpdateWidget(covariant _NoteReactionBar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!_busy) {
      final fresh = widget.note.reactionCounts;
      if (widget.note.myReaction != _mine || !_mapsEqual(fresh, _counts)) {
        _counts = Map<String, int>.from(fresh);
        _mine = widget.note.myReaction;
      }
    }
  }

  static bool _mapsEqual(Map<String, int> a, Map<String, int> b) {
    if (a.length != b.length) return false;
    for (final k in a.keys) {
      if (a[k] != b[k]) return false;
    }
    return true;
  }

  Future<void> _react(String value) async {
    if (_busy) return;
    final prevCounts = Map<String, int>.from(_counts);
    final prevMine = _mine;
    setState(() {
      _busy = true;
      if (_mine == value) {
        _counts[value] = (_counts[value] ?? 1) - 1;
        if (_counts[value]! <= 0) _counts.remove(value);
        _mine = null;
      } else {
        if (_mine != null) {
          _counts[_mine!] = (_counts[_mine!] ?? 1) - 1;
          if (_counts[_mine!]! <= 0) _counts.remove(_mine!);
        }
        _counts[value] = (_counts[value] ?? 0) + 1;
        _mine = value;
      }
    });
    try {
      await ref.read(notesRepoProvider).react(widget.note.id, value);
      await ref.read(noteDetailProvider(widget.note.id).notifier).reload();
      final fresh = ref.read(noteDetailProvider(widget.note.id)).valueOrNull;
      if (mounted && fresh != null) {
        setState(() {
          _counts = Map<String, int>.from(fresh.reactionCounts);
          _mine = fresh.myReaction;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _counts = prevCounts;
          _mine = prevMine;
        });
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
    return Card(
      margin: const EdgeInsets.only(top: 14),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.favorite_border_rounded, color: cs.primary),
                const SizedBox(width: 8),
                Text(
                  tr(ref, 'note_engagement'),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ],
            ),
            const SizedBox(height: 8),
            RasdReactionBar(
              counts: _counts,
              myReaction: _mine,
              busy: _busy,
              compact: false,
              lang: ref.watch(localeProvider),
              onReact: _react,
            ),
          ],
        ),
      ),
    );
  }
}

class _NoteTranslationPanel extends ConsumerStatefulWidget {
  final Note note;
  const _NoteTranslationPanel({super.key, required this.note});

  @override
  ConsumerState<_NoteTranslationPanel> createState() =>
      _NoteTranslationPanelState();
}

class _NoteTranslationPanelState extends ConsumerState<_NoteTranslationPanel> {
  Map<String, dynamic>? _status;
  bool _loading = true;
  bool _requesting = false;
  String? _error;
  bool _loadedOnce = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<Map<String, dynamic>> _fetch() => ref
      .read(notesRepoProvider)
      .translationStatus(widget.note.id, ref.read(localeProvider));

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final status = await _fetch();
      if (!mounted) return;
      setState(() {
        _status = status;
        _loadedOnce = true;
      });
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _retry() async {
    final locale = ref.read(localeProvider);
    setState(() {
      _requesting = true;
      _error = null;
    });
    try {
      final result = await ref
          .read(notesRepoProvider)
          .retryTranslation(widget.note.id, locale);
      if (result['queued'] != true) {
        final current = await _fetch();
        if (mounted) {
          setState(() {
            _status = current;
            _loadedOnce = true;
          });
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                (result['message'] ?? tr(ref, 'translation_missing'))
                    .toString(),
              ),
            ),
          );
        }
        return;
      }
      for (var attempt = 0; attempt < 10; attempt++) {
        await Future<void>.delayed(const Duration(seconds: 3));
        if (!mounted) return;
        final status = await _fetch();
        if (!mounted) return;
        setState(() {
          _status = status;
          _loadedOnce = true;
        });
        if (status['state'] == 'ready') break;
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
      if (!_loadedOnce) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _requesting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_loadedOnce) return const SizedBox.shrink();
    final cs = Theme.of(context).colorScheme;
    final state = _status?['state']?.toString() ?? 'source';
    final texts = _status?['texts'] is Map
        ? Map<String, dynamic>.from(_status!['texts'] as Map)
        : const <String, dynamic>{};
    final translated = (texts['description'] ?? '').toString().trim();
    final ready =
        state == 'ready' &&
        translated.isNotEmpty &&
        translated != widget.note.description.trim();
    final hasArabic = RegExp(
      r'[\u0600-\u06FF]',
    ).hasMatch(widget.note.description);
    final locale = ref.read(localeProvider);
    final alreadyInTargetLanguage = locale == 'ar' ? hasArabic : !hasArabic;
    if (!_loading && state == 'source' && alreadyInTargetLanguage) {
      return const SizedBox.shrink();
    }

    return RasdCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.translate_rounded, color: cs.primary, size: 19),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  tr(ref, 'translation'),
                  style: Theme.of(context).textTheme.titleSmall,
                ),
              ),
              if (_loading || _requesting)
                SizedBox(
                  width: 17,
                  height: 17,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: cs.primary,
                  ),
                )
              else if (ready)
                Icon(
                  Icons.check_circle_outline_rounded,
                  color: cs.primary,
                  size: 19,
                ),
            ],
          ),
          if (ready) ...[
            const SizedBox(height: 10),
            Text(
              tr(ref, 'translated_text'),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 4),
            SelectableText(
              translated,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
          ] else ...[
            const SizedBox(height: 5),
            Text(
              _error ??
                  (_loading
                      ? tr(ref, 'translation_pending')
                      : state == 'pending'
                      ? tr(ref, 'translation_pending')
                      : tr(ref, 'translation_missing')),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            if (!_loading && !_requesting && !_errorIsAccessDenied)
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: TextButton.icon(
                  onPressed: _retry,
                  icon: const Icon(Icons.refresh_rounded, size: 17),
                  label: Text(tr(ref, 'translation_retry')),
                ),
              ),
          ],
        ],
      ),
    );
  }

  bool get _errorIsAccessDenied =>
      _error?.contains('403') == true || _error?.contains('404') == true;
}

class _ObservedRow extends ConsumerWidget {
  final Note note;
  const _ObservedRow({required this.note});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = Wall.parts(note.observedAtRaw);
    if (p.date.isEmpty) return const SizedBox.shrink();
    final pe = Wall.parts(note.observedEndAtRaw);
    final time = pe.time.isEmpty
        ? Wall.time12(p.time)
        : '${Wall.time12(p.time)} – ${Wall.time12(pe.time)}';
    return Row(
      children: [
        const Icon(Icons.schedule_outlined, size: 15),
        const SizedBox(width: 6),
        Text(
          '${p.date} · $time',
          style: Theme.of(
            context,
          ).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.bold),
        ),
      ],
    );
  }
}

class _Thumb extends ConsumerWidget {
  final Attachment att;
  const _Thumb({required this.att});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      decoration: BoxDecoration(
        color: cs.surfaceContainerHigh,
        border: Border.all(color: cs.outline),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: .08),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (att.isImage)
            _NetImage(att: att)
          else
            Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topRight,
                  end: Alignment.bottomLeft,
                  colors: [
                    cs.primaryContainer.withValues(alpha: .7),
                    cs.surfaceContainerHigh,
                  ],
                ),
              ),
              child: Icon(
                att.isVideo ? Icons.videocam_rounded : Icons.audiotrack_rounded,
                color: cs.primary,
                size: 30,
              ),
            ),
          if (att.isVideo)
            Center(
              child: Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: .55),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.play_arrow_rounded,
                  color: Colors.white,
                  size: 20,
                ),
              ),
            ),
          PositionedDirectional(
            bottom: 5,
            start: 5,
            end: 5,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: .55),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                att.isImage
                    ? tr(ref, 'photo')
                    : att.isVideo
                    ? tr(ref, 'video')
                    : tr(ref, 'audio'),
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _NetImage extends ConsumerWidget {
  final Attachment att;
  const _NetImage({required this.att});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final url = '${ref.watch(baseUrlProvider)}/api/attachments/${att.id}/view';
    final loading = ref.watch(authTokenProvider).isLoading;
    if (loading) {
      return Container(color: cs.surfaceContainerHigh);
    }
    return RasdNetworkImage(
      url: url,
      headers: ref.watch(authImageHeadersProvider),
      isSvg: att.mime == 'image/svg+xml',
    );
  }
}
