import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models.dart';
import '../../core/storage.dart';
import '../../core/strings.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../notes/note_detail_page.dart';
import '../reports/report_detail_page.dart';
import '../submissions/submission_detail_page.dart';
import 'notifications_repo.dart';

void openNotificationTarget(BuildContext context, AppNotification n) {
  int? toInt(dynamic v) => v is int ? v : int.tryParse(v?.toString() ?? '');
  final d = n.data;
  final focusDiscussion = _isDiscussion(n);
  final noteId = toInt(d['note_id']);
  if (noteId != null && noteId > 0) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => NoteDetailPage(id: noteId)),
    );
    return;
  }
  final subId = toInt(d['submission_id']) ?? toInt(d['general_submission_id']);
  if (subId != null && subId > 0) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => SubmissionDetailPage(id: subId)),
    );
    return;
  }
  final repId = toInt(d['report_id']);
  if (repId != null && repId > 0) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) =>
            ReportDetailPage(id: repId, focusDiscussion: focusDiscussion),
      ),
    );
  }
}

class NotificationsPage extends ConsumerStatefulWidget {
  const NotificationsPage({super.key});

  @override
  ConsumerState<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends ConsumerState<NotificationsPage> {
  final _selected = <String>{};
  bool _unreadOnly = false;
  String _category = 'all';

  @override
  Widget build(BuildContext context) {
    final list = ref.watch(notificationsListProvider);
    final selecting = _selected.isNotEmpty;
    final hasUnread = list.valueOrNull?.any((n) => n.unread) ?? false;
    final items = (list.valueOrNull ?? const [])
        .where((n) => !_unreadOnly || n.unread)
        .where((n) => _category == 'all' || _categoryKey(n) == _category)
        .toList();
    return Scaffold(
      appBar: AppBar(
        title: selecting
            ? Text('${_selected.length}')
            : Text(tr(ref, 'notifications')),
        leading: selecting
            ? IconButton(
                icon: const Icon(Icons.close),
                onPressed: () => setState(() => _selected.clear()),
              )
            : null,
        actions: [
          if (selecting)
            TextButton(
              onPressed: () {
                ref
                    .read(notificationsListProvider.notifier)
                    .markSelected(Set.of(_selected));
                setState(() => _selected.clear());
              },
              child: Text(tr(ref, 'mark_read_sel')),
            )
          else ...[
            IconButton(
              tooltip: tr(ref, 'sound'),
              icon: Icon(
                ref.watch(soundEnabledProvider)
                    ? Icons.volume_up_outlined
                    : Icons.volume_off_outlined,
              ),
              onPressed: () async {
                final v = !ref.read(soundEnabledProvider);
                ref.read(soundEnabledProvider.notifier).state = v;
                await Prefs.setSound(v);
              },
            ),
            if (hasUnread)
              PopupMenuButton<String>(
                tooltip: tr(ref, 'mark_all_read'),
                onSelected: (value) {
                  if (value == 'read_all') {
                    ref.read(notificationsListProvider.notifier).markAllRead();
                  }
                },
                itemBuilder: (_) => [
                  PopupMenuItem(
                    value: 'read_all',
                    child: Row(
                      children: [
                        const Icon(Icons.done_all_outlined, size: 18),
                        const SizedBox(width: 10),
                        Text(tr(ref, 'mark_all_read')),
                      ],
                    ),
                  ),
                ],
              ),
          ],
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(94),
          child: Padding(
            padding: const EdgeInsetsDirectional.only(
              start: 16,
              end: 16,
              bottom: 10,
            ),
            child: Column(
              children: [
                SizedBox(
                  height: 38,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    children: [
                      for (final key in [
                        'all',
                        'notif_cat_note',
                        'notif_cat_dispatch',
                        'notif_cat_report',
                        'notif_cat_discussion',
                        'notif_cat_generic',
                      ])
                        Padding(
                          padding: const EdgeInsetsDirectional.only(end: 7),
                          child: ChoiceChip(
                            label: Text(tr(ref, key)),
                            selected: _category == key,
                            onSelected: (_) => setState(() => _category = key),
                            visualDensity: VisualDensity.compact,
                          ),
                        ),
                    ],
                  ),
                ),
                SizedBox(
                  height: 30,
                  child: Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: FilterChip(
                      label: Text(tr(ref, 'unread')),
                      selected: _unreadOnly,
                      onSelected: (value) =>
                          setState(() => _unreadOnly = value),
                      visualDensity: VisualDensity.compact,
                      avatar: Icon(
                        _unreadOnly
                            ? Icons.mark_email_unread_outlined
                            : Icons.mail_outline,
                        size: 16,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.read(notificationsListProvider.notifier).refresh(),
        child: list.when(
          loading: () => const RasdSkeletonList(),
          error: (e, _) => RasdError(
            error: e,
            onRetry: () =>
                ref.read(notificationsListProvider.notifier).refresh(),
            retryLabel: tr(ref, 'retry'),
          ),
          data: (_) => items.isEmpty
              ? RasdEmpty(message: tr(ref, 'no_results'))
              : ListView.builder(
                  padding: const EdgeInsets.only(top: 8, bottom: 16),
                  itemCount: items.length,
                  itemBuilder: (_, i) => _Tile(
                    n: items[i],
                    selected: _selected.contains(items[i].id),
                    onTap: () {
                      final item = items[i];
                      final id = item.id;
                      if (selecting) {
                        setState(() {
                          if (!_selected.remove(id)) {
                            _selected.add(id);
                          }
                        });
                      } else {
                        _openSheet(item);
                      }
                    },
                    onLongPress: () =>
                        setState(() => _selected.add(items[i].id)),
                  ),
                ),
        ),
      ),
    );
  }

  void _openSheet(AppNotification item) {
    if (item.unread) {
      ref.read(notificationsListProvider.notifier).markOneRead(item.id);
    }
    if (!_hasNotificationTarget(item)) return;
    final cat = _categoryKey(item);
    if (cat == 'notif_cat_report' || cat == 'notif_cat_discussion') {
      openNotificationTarget(context, item);
      return;
    }
    final opened = item.unread
        ? AppNotification(
            id: item.id,
            title: item.title,
            body: item.body,
            unread: false,
            type: item.type,
            priority: item.priority,
            category: item.category,
            humanTime: item.humanTime,
            createdAt: item.createdAt,
            data: item.data,
          )
        : item;
    Navigator.push(context, RasdSlideRoute(page: _NotifDetailPage(n: opened)));
  }
}

int? _intOrNull(dynamic v) {
  if (v is int) return v;
  return int.tryParse(v?.toString() ?? '');
}

bool _isDiscussion(AppNotification n) => _intOrNull(n.data['comment_id']) != null;

bool _hasNotificationTarget(AppNotification n) {
  final data = n.data;
  for (final key in const [
    'note_id',
    'submission_id',
    'general_submission_id',
    'report_id',
  ]) {
    final id = int.tryParse(data[key]?.toString() ?? '');
    if (id != null && id > 0) return true;
  }
  return false;
}

String _categoryKey(AppNotification n) {
  final c = n.category.toLowerCase();
  final t = (n.type ?? '').toLowerCase();
  if (_intOrNull(n.data['comment_id']) != null) return 'notif_cat_discussion';
  if (c == 'note' || t.contains('note_')) return 'notif_cat_note';
  if (c == 'dispatch' || t.contains('dispatch') || t.contains('submission')) {
    return 'notif_cat_dispatch';
  }
  if (c == 'report' || t.contains('report')) return 'notif_cat_report';
  return 'notif_cat_generic';
}

String _notificationTime(AppNotification n, String lang) {
  final human = (n.humanTime ?? '').trim();
  if (human.isNotEmpty) return human;
  final created = n.createdAt?.toLocal();
  if (created == null) return '';
  final now = DateUtils.dateOnly(DateTime.now());
  final date = DateUtils.dateOnly(created);
  final clock =
      '${created.hour.toString().padLeft(2, '0')}:${created.minute.toString().padLeft(2, '0')}';
  if (date == now) return clock;
  if (date == now.subtract(const Duration(days: 1))) {
    return '${trOf(lang, 'yesterday')} · $clock';
  }
  return '${created.day.toString().padLeft(2, '0')}/${created.month.toString().padLeft(2, '0')}/${created.year}';
}

class _Tile extends ConsumerWidget {
  final AppNotification n;
  final bool selected;
  final VoidCallback onTap;
  final VoidCallback onLongPress;
  const _Tile({
    required this.n,
    required this.selected,
    required this.onTap,
    required this.onLongPress,
  });

  (IconData, Color) _kind(BuildContext context, AppNotification n) {
    Color pick(Color light) => RasdColors.adaptive(context, light);
    final isReply = _intOrNull(n.data['parent_id']) != null;
    return switch (_categoryKey(n)) {
      'notif_cat_note' => (Icons.note_alt_outlined, pick(RasdColors.green)),
      'notif_cat_dispatch' =>
        (Icons.outbox_outlined, pick(RasdColors.goldInk)),
      'notif_cat_discussion' => isReply
          ? (Icons.reply_rounded, pick(Colors.indigo.shade600))
          : (Icons.mode_comment_outlined, pick(Colors.blue.shade700)),
      'notif_cat_report' => (
        Icons.description_outlined,
        pick(Colors.blue.shade700),
      ),
      _ => (Icons.notifications_none_rounded, pick(RasdColors.ink2)),
    };
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final (icon, tint) = _kind(context, n);
    final timeLabel = _notificationTime(n, ref.read(localeProvider));
    final summary = n.body.trim() == n.title.trim() ? '' : n.body.trim();
    final title = n.title.trim().isEmpty ? summary : n.title.trim();
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Container(
        decoration: BoxDecoration(
          color: selected
              ? cs.primaryContainer.withValues(alpha: .38)
              : n.unread
              ? cs.primaryContainer.withValues(alpha: .20)
              : cs.surface,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(
            color: selected ? cs.primary : cs.outlineVariant,
            width: selected ? 1.4 : 1,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onTap,
            onLongPress: onLongPress,
            child: Padding(
              padding: const EdgeInsetsDirectional.fromSTEB(12, 10, 12, 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: tint.withValues(alpha: .10),
                      borderRadius: BorderRadius.circular(11),
                    ),
                    child: Icon(
                      selected ? Icons.check_rounded : icon,
                      color: selected ? cs.primary : tint,
                      size: 21,
                    ),
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Spacer(),
                            if (timeLabel.isNotEmpty)
                              Text(
                                timeLabel,
                                style: Theme.of(
                                  context,
                                ).textTheme.bodySmall?.copyWith(fontSize: 11),
                              ),
                            if (n.unread) ...[
                              const SizedBox(width: 6),
                              Container(
                                width: 6,
                                height: 6,
                                decoration: BoxDecoration(
                                  color: cs.primary,
                                  shape: BoxShape.circle,
                                ),
                              ),
                            ],
                          ],
                        ),
                        const SizedBox(height: 3),
                        Text(
                          tr(ref, _categoryKey(n)),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: tint,
                          ),
                        ),
                        const SizedBox(height: 1),
                        Text(
                          title.isEmpty ? '—' : title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 14,
                            height: 1.3,
                            fontWeight: n.unread
                                ? FontWeight.w700
                                : FontWeight.w500,
                          ),
                        ),
                        if (summary.isNotEmpty && summary != title) ...[
                          const SizedBox(height: 2),
                          Text(
                            summary,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 12,
                              height: 1.4,
                              color: cs.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 5),
                  Icon(
                    Icons.chevron_right_rounded,
                    size: 20,
                    color: cs.onSurfaceVariant,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _NotifDetailPage extends ConsumerWidget {
  final AppNotification n;
  const _NotifDetailPage({required this.n});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final timeLabel = _notificationTime(n, ref.read(localeProvider));
    return Scaffold(
      appBar: AppBar(title: Text(tr(ref, 'notifications'))),
      body: Padding(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (timeLabel.isNotEmpty)
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: Text(timeLabel, style: Theme.of(context).textTheme.bodySmall),
              ),
            const SizedBox(height: 14),
            Text(
              n.title.isEmpty ? '—' : n.title,
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
            ),
            const Spacer(),
            FilledButton.icon(
              onPressed: () {
                Navigator.pop(context);
                if (_hasNotificationTarget(n)) openNotificationTarget(context, n);
              },
              icon: Icon(
                _hasNotificationTarget(n)
                    ? Icons.open_in_new_rounded
                    : Icons.check_rounded,
                size: 18,
              ),
              label: Text(tr(ref, _hasNotificationTarget(n) ? 'view_details' : 'close')),
            ),
          ],
        ),
      ),
    );
  }
}
