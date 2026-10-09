import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/server_pdf.dart';
import '../../core/share_center.dart';
import '../../core/strings.dart';
import '../../core/wall_clock.dart';
import '../../core/widgets.dart';
import '../auth/auth_provider.dart';
import '../home/user_profile_page.dart';
import '../notes/attachment_viewer.dart';
import '../notes/note_print_page.dart';
import 'submissions_repo.dart';

class SubmissionDetailPage extends ConsumerStatefulWidget {
  final int id;
  const SubmissionDetailPage({super.key, required this.id});

  @override
  ConsumerState<SubmissionDetailPage> createState() =>
      _SubmissionDetailPageState();
}

class _SubmissionDetailPageState extends ConsumerState<SubmissionDetailPage> {
  bool _busy = false;

  Future<void> _run(Future<Submission> Function() call) async {
    setState(() => _busy = true);
    final err = await ref
        .read(submissionDetailProvider(widget.id).notifier)
        .run(call);
    if (!mounted) return;
    setState(() => _busy = false);
    if (err != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(err)));
    }
  }

  void _openPrint(BuildContext context, WidgetRef ref, Submission s) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => NotePrintPage(
          title: '${tr(ref, 'print_submission')} #${s.id}',
          kind: ServerPdfKind.submission,
          id: s.id,
          fileName: 'submission-${s.id}.pdf',
        ),
      ),
    );
  }

  Future<void> _askReject() async {
    final lang = ref.read(localeProvider);
    final ctrl = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(trOf(lang, 'reject_reason')),
        content: TextField(
          controller: ctrl,
          maxLines: 3,
          decoration: InputDecoration(hintText: trOf(lang, 'reject_reason')),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(trOf(lang, 'cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(trOf(lang, 'confirm')),
          ),
        ],
      ),
    );
    if (ok == true && ctrl.text.trim().length >= 5) {
      await _run(
        () => ref
            .read(submissionsRepoProvider)
            .reject(widget.id, ctrl.text.trim()),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final me = ref.watch(authProvider).valueOrNull;
    final detail = ref.watch(submissionDetailProvider(widget.id));
    final sub = detail.valueOrNull;
    final canDl =
        sub != null &&
        me != null &&
        (me.id == sub.userId || me.isWriter || me.isGM);
    return Scaffold(
      appBar: AppBar(
        actions: [
          if (sub != null) ...[
            IconButton(
              tooltip: tr(ref, 'print_submission'),
              onPressed: () => _openPrint(context, ref, sub),
              icon: const Icon(Icons.print_outlined),
            ),
            IconButton(
              tooltip: tr(ref, 'share_title'),
              onPressed: () => ShareCenter.sheet(
                context,
                ref,
                ShareCenter.submission(ref, sub, canDl),
              ),
              icon: const Icon(Icons.share_outlined),
            ),
          ],
        ],
      ),
      body: detail.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => RasdError(
          error: e,
          onRetry: () =>
              ref.read(submissionDetailProvider(widget.id).notifier).reload(),
          retryLabel: tr(ref, 'retry'),
        ),
        data: (s) {
          final myId = me?.id;
          final canModerate =
              (me?.isWriter ?? false) &&
              s.isPending &&
              s.writers.any((w) => w.id == myId);
          return Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 680),
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(
                    '${tr(ref, 'camera')} ${s.cameraNumber} · ${tr(ref, 'floor')} ${s.floorNumber}',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Builder(
                    builder: (_) {
                      final p = Wall.parts(s.observedAtRaw);
                      if (p.date.isEmpty) {
                        return const SizedBox.shrink();
                      }
                      return Row(
                        children: [
                          const Icon(Icons.schedule_outlined, size: 15),
                          const SizedBox(width: 6),
                          Text(
                            '${p.date} · ${Wall.time12(p.time)}',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(fontWeight: FontWeight.bold),
                          ),
                        ],
                      );
                    },
                  ),
                  const SizedBox(height: 8),
                  SelectableText(
                    s.description,
                    style: const TextStyle(height: 1.9, fontSize: 15),
                  ),
                  if (s.isRejected && s.rejectionReason != null) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.errorContainer,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Text(s.rejectionReason!),
                    ),
                  ],
                  if (s.writers.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      children: s.writers
                          .map(
                            (w) => ActionChip(
                              avatar: RasdAvatar(
                                url: w.avatarUrl,
                                name: w.name,
                                size: 22,
                              ),
                              label: Text(w.name),
                              onPressed: () => openUserProfile(context, w.id),
                            ),
                          )
                          .toList(),
                    ),
                  ],
                  if (s.attachments.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text(
                      tr(ref, 'attachments'),
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    ...s.attachments.map(
                      (a) => ListTile(
                        leading: Icon(
                          a.isImage
                              ? Icons.image_outlined
                              : a.isVideo
                              ? Icons.videocam_outlined
                              : Icons.audiotrack_outlined,
                        ),
                        title: Text(
                          a.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        trailing: const Icon(
                          Icons.open_in_new_outlined,
                          size: 18,
                        ),
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => AttachmentViewer(
                              att: a,
                              canDownload: true,
                              viewPath:
                                  '/api/submission-attachments/${a.id}/view',
                              downloadFile: () => ref
                                  .read(submissionsRepoProvider)
                                  .downloadAttachment(a.id, a.name),
                            ),
                          ),
                        ),
                      ),
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
                        if (s.isDraft && myId != null && myId == s.userId)
                          FilledButton(
                            onPressed: () => _pickWritersAnd(
                              (ids) => _run(
                                () => ref
                                    .read(submissionsRepoProvider)
                                    .submit(s.id, ids),
                              ),
                            ),
                            child: Text(tr(ref, 'send')),
                          ),
                        if (canModerate) ...[
                          FilledButton(
                            onPressed: () => _run(
                              () => ref
                                  .read(submissionsRepoProvider)
                                  .accept(s.id),
                            ),
                            child: Text(tr(ref, 'accept')),
                          ),
                          OutlinedButton(
                            onPressed: _askReject,
                            child: Text(tr(ref, 'reject')),
                          ),
                        ],
                      ],
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _pickWritersAnd(Future<void> Function(List<int>) go) async {
    try {
      final writers = await ref.read(submissionsRepoProvider).writers();
      if (!mounted) return;
      final picked = <int>{};
      var query = '';
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => StatefulBuilder(
          builder: (ctx, setD) => AlertDialog(
            title: TextField(
              decoration: InputDecoration(
                hintText: tr(ref, 'search'),
                prefixIcon: const Icon(Icons.search_outlined),
              ),
              onChanged: (v) => setD(() => query = v.trim()),
            ),
            content: SizedBox(
              width: double.maxFinite,
              height: MediaQuery.of(ctx).size.height * 0.5,
              child: Builder(
                builder: (_) {
                  final shown = writers
                      .where(
                        (w) =>
                            query.isEmpty ||
                            w.name.contains(query) ||
                            w.username.contains(query),
                      )
                      .toList();
                  return ListView.builder(
                    shrinkWrap: true,
                    itemCount: shown.length,
                    itemBuilder: (_, i) {
                      final w = shown[i];
                      return StatefulBuilder(
                        builder: (ctx, setS) => CheckboxListTile(
                          value: picked.contains(w.id),
                          onChanged: (v) => setS(() {
                            if (v == true) {
                              picked.add(w.id);
                            } else {
                              picked.remove(w.id);
                            }
                            setD(() {});
                          }),
                          title: Text(w.name),
                          subtitle: Text('@${w.username}'),
                          secondary: IconButton(
                            tooltip: tr(ref, 'view_profile'),
                            onPressed: () => openUserProfile(context, w.id),
                            icon: const Icon(Icons.person_outline, size: 20),
                          ),
                        ),
                      );
                    },
                  );
                },
              ),
            ),
            actions: [
              Text(
                '${picked.length}',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: Text(tr(ref, 'confirm')),
              ),
            ],
          ),
        ),
      );
      if (ok == true && picked.isNotEmpty) await go(picked.toList());
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }
}
