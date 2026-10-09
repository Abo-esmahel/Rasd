import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models.dart';
import '../../core/strings.dart';
import '../../core/theme.dart';
import '../../core/wall_clock.dart';
import '../../core/widgets.dart';
import '../auth/auth_provider.dart';
import '../notes/note_create_page.dart';
import '../notes/note_detail_page.dart';
import '../notes/notes_page.dart';
import '../notes/notes_providers.dart';
import '../notifications/notifications_page.dart';
import '../notifications/notifications_repo.dart';
import '../reports/report_detail_page.dart';
import '../reports/reports_page.dart';
import '../reports/reports_providers.dart';
import '../submissions/submission_create_page.dart';
import '../submissions/submissions_repo.dart';
import 'profile_page.dart' show profileStatsProvider;
import 'ranking_page.dart';

class DashboardPage extends ConsumerWidget {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).valueOrNull;
    final unread = ref.watch(unreadCountProvider);
    final isManager = user?.isGM ?? false;
    final isMonitor = user?.isMonitor ?? false;
    final isWriter = user?.isWriter ?? false;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 104),
      children: [
        const _DashboardMasthead(),
        const SizedBox(height: 14),
        Consumer(
          builder: (context, ref, _) {
            final stats = ref.watch(profileStatsProvider);
            return stats.when(
              loading: () => const _StatsGrid.loading(),
              error: (_, __) => RasdCard(
                child: Row(
                  children: [
                    Icon(
                      Icons.cloud_off_outlined,
                      color: Theme.of(context).colorScheme.error,
                    ),
                    const SizedBox(width: 10),
                    Expanded(child: Text(tr(ref, 'statistics_unavailable'))),
                    IconButton(
                      tooltip: tr(ref, 'retry'),
                      onPressed: () => ref.invalidate(profileStatsProvider),
                      icon: const Icon(Icons.refresh_rounded),
                    ),
                  ],
                ),
              ),
              data: (stats) => _StatsGrid(
                total: stats['total'] ?? 0,
                accepted: stats['accepted'] ?? 0,
                pending: stats['pending'] ?? 0,
                rejected: stats['rejected'] ?? 0,
                statsRef: ref,
              ),
            );
          },
        ),
        if (unread > 0) ...[
          const SizedBox(height: 12),
          _UnreadNotice(count: unread),
        ],
        const SizedBox(height: 20),
        RasdSectionHeader(
          title: tr(ref, isManager ? 'team_activity' : 'create_record'),
        ),
        const SizedBox(height: 8),
        if (isManager)
          _ActionTile(
            icon: Icons.leaderboard_outlined,
            title: tr(ref, 'ranking'),
            description: tr(ref, 'ranking_action_hint'),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const RankingPage()),
            ),
          )
        else ...[
          _ActionTile(
            icon: Icons.sticky_note_2_outlined,
            title: tr(ref, 'new_note'),
            description: tr(ref, 'note_action_hint'),
            primary: true,
            onTap: () async {
              final saved = await Navigator.push<bool>(
                context,
                MaterialPageRoute(builder: (_) => const NoteCreatePage()),
              );
              if (saved == true) {
                await ref.read(notesListProvider.notifier).refresh();
                ref.invalidate(profileStatsProvider);
              }
            },
          ),
          if (isMonitor) ...[
            const SizedBox(height: 8),
            _ActionTile(
              icon: Icons.assignment_outlined,
              title: tr(ref, 'new_submission'),
              description: tr(ref, 'submission_action_hint'),
              onTap: () async {
                final saved = await Navigator.push<bool>(
                  context,
                  MaterialPageRoute(
                    builder: (_) => const SubmissionCreatePage(),
                  ),
                );
                if (saved == true) {
                  await ref.read(submissionsListProvider.notifier).refresh();
                }
              },
            ),
          ],
          if (isWriter) ...[
            const SizedBox(height: 8),
            _ActionTile(
              icon: Icons.article_outlined,
              title: tr(ref, 'new_report'),
              description: tr(ref, 'reports_subtitle'),
              onTap: () async {
                await showModalBottomSheet(
                  context: context,
                  isScrollControlled: true,
                  builder: (_) => const ReportCreateSheet(),
                );
                await ref.read(reportsListProvider.notifier).refresh();
              },
            ),
          ],
        ],
        const SizedBox(height: 20),
        RasdSectionHeader(
          title: tr(
            ref,
            isManager || isWriter ? 'recent_reports' : 'recent_notes',
          ),
          onMore: () => Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => isManager || isWriter
                  ? const ReportsPage()
                  : const NotesPage(),
            ),
          ),
          moreLabel: tr(ref, 'view_all'),
        ),
        const SizedBox(height: 8),
        isManager || isWriter ? const _RecentReports() : const _RecentNotes(),
      ],
    );
  }
}

class _DashboardMasthead extends ConsumerWidget {
  const _DashboardMasthead();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: cs.surface,
        border: Border.all(color: cs.outlineVariant),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          const RasdMark(size: 54),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  tr(ref, 'dashboard_title'),
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontSize: 19,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  tr(ref, 'dashboard_subtitle'),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StatsGrid extends StatelessWidget {
  final int total;
  final int accepted;
  final int pending;
  final int rejected;
  final WidgetRef? statsRef;

  const _StatsGrid({
    required this.total,
    required this.accepted,
    required this.pending,
    required this.rejected,
    required this.statsRef,
  });

  const _StatsGrid.loading()
    : total = 0,
      accepted = 0,
      pending = 0,
      rejected = 0,
      statsRef = null;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 620 ? 4 : 2;
        final itemWidth = (constraints.maxWidth - (columns - 1) * 10) / columns;
        if (statsRef == null) {
          return GridView.count(
            crossAxisCount: columns,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: itemWidth / 68,
            children: List.generate(
              4,
              (_) => Container(
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surfaceContainerHigh,
                  border: Border.all(
                    color: Theme.of(context).colorScheme.outlineVariant,
                  ),
                  borderRadius: BorderRadius.circular(RasdRadii.card),
                ),
              ),
            ),
          );
        }

        final pct = total == 0
            ? 0.0
            : (accepted / total).clamp(0.0, 1.0).toDouble();
        final dark = Theme.of(context).brightness == Brightness.dark;
        final cells = <(String, int, IconData, Color)>[
          ('my_notes', total, Icons.inventory_2_outlined, RasdColors.green),
          ('accepted', accepted, Icons.task_alt_outlined, RasdColors.green),
          (
            'pending',
            pending,
            Icons.schedule_outlined,
            RasdColors.goldInk,
          ),
          (
            'rejected',
            rejected,
            Icons.highlight_off_outlined,
            RasdColors.danger,
          ),
        ];
        return RasdCard(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              Row(
                children: [
                  Container(
                    width: 38,
                    height: 38,
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primaryContainer
                          .withValues(alpha: .6),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      Icons.insights_outlined,
                      color: Theme.of(context).colorScheme.primary,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          tr(statsRef!, 'acceptance'),
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          '$accepted / $total ${tr(statsRef!, 'accepted')}',
                          style: Theme.of(
                            context,
                          ).textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                  Text(
                    '${(pct * 100).round()}٪',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.primary,
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      fontFeatures: const [
                        FontFeature.tabularFigures(),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: LinearProgressIndicator(
                  value: pct,
                  minHeight: 7,
                  backgroundColor: Theme.of(
                    context,
                  ).colorScheme.surfaceContainerHigh,
                  valueColor: AlwaysStoppedAnimation(
                    Theme.of(context).colorScheme.primary,
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Divider(
                  height: 1,
                  color: Theme.of(
                    context,
                  ).colorScheme.outlineVariant,
                ),
              ),
              GridView.builder(
                gridDelegate:
                    SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: columns,
                  mainAxisSpacing: 10,
                  crossAxisSpacing: 10,
                  mainAxisExtent: 80,
                ),
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: cells.length,
                itemBuilder: (_, i) {
                  final cell = cells[i];
                  return _DashCell(
                    value: cell.$2,
                    label: tr(statsRef!, cell.$1),
                    icon: cell.$3,
                    color: RasdColors.adaptive(context, cell.$4),
                    dark: dark,
                  );
                },
              ),
            ],
          ),
        );
      },
    );
  }
}

class _DashCell extends StatelessWidget {
  final int value;
  final String label;
  final IconData icon;
  final Color color;
  final bool dark;
  const _DashCell({
    required this.value,
    required this.label,
    required this.icon,
    required this.color,
    required this.dark,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: color.withValues(alpha: dark ? .14 : .08),
        border: Border.all(color: color.withValues(alpha: .22)),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: color.withValues(alpha: dark ? .22 : .15),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(icon, color: color, size: 20),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  '$value',
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    height: 1.1,
                    fontFeatures: [FontFeature.tabularFigures()],
                  ),
                ),
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 11, color: cs.onSurfaceVariant),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _UnreadNotice extends ConsumerWidget {
  final int count;
  const _UnreadNotice({required this.count});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    return RasdCard(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const NotificationsPage()),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 11),
      child: Row(
        children: [
          Icon(Icons.mark_email_unread_outlined, color: cs.primary, size: 21),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              tr(ref, 'unread'),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
          Text(
            '$count',
            style: TextStyle(color: cs.primary, fontWeight: FontWeight.w800),
          ),
          const SizedBox(width: 3),
          Icon(
            Icons.chevron_right_rounded,
            color: cs.onSurfaceVariant,
            size: 19,
          ),
        ],
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String description;
  final VoidCallback onTap;
  final bool primary;
  const _ActionTile({
    required this.icon,
    required this.title,
    required this.description,
    required this.onTap,
    this.primary = false,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Material(
      color: primary ? cs.primaryContainer.withValues(alpha: .72) : cs.surface,
      borderRadius: BorderRadius.circular(13),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(13),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
          decoration: BoxDecoration(
            border: Border.all(
              color: primary
                  ? cs.primary.withValues(alpha: .24)
                  : cs.outlineVariant,
            ),
            borderRadius: BorderRadius.circular(13),
          ),
          child: Row(
            children: [
              Icon(icon, color: cs.primary, size: 22),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      description,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              Icon(
                Icons.chevron_right_rounded,
                size: 20,
                color: cs.onSurfaceVariant,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RecentNotes extends ConsumerWidget {
  const _RecentNotes();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final list = ref.watch(notesListProvider);
    return list.when(
      loading: () => const _RecentPlaceholder(),
      error: (_, __) => _LoadRecentError(
        onRetry: () => ref.read(notesListProvider.notifier).refresh(),
      ),
      data: (items) {
        if (items.isEmpty) {
          return _EmptyRecent(message: tr(ref, 'recent_empty'));
        }
        return RasdCard(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Column(
            children: [
              for (var i = 0; i < items.length && i < 3; i++) ...[
                if (i > 0) const Divider(height: 1, indent: 4),
                _NoteLogEntry(
                  note: items[i],
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => NoteDetailPage(id: items[i].id),
                    ),
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _NoteLogEntry extends ConsumerWidget {
  final Note note;
  final VoidCallback onTap;
  const _NoteLogEntry({required this.note, required this.onTap});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final statusColor = RasdStatusChip.colorOf(context, note.status);
    final time = Wall.parts(note.observedAtRaw);
    final stamp = time.date.isNotEmpty
        ? '${time.date}${time.time.isNotEmpty ? ' · ${Wall.time12(time.time)}' : ''}'
        : (note.createdAt == null
              ? ''
              : '${note.createdAt!.day}/${note.createdAt!.month}/${note.createdAt!.year}');
    final desc = note.description.trim();
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: statusColor.withValues(alpha: .13),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                Icons.sticky_note_2_outlined,
                color: statusColor,
                size: 23,
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      RasdStatusChip(
                        status: note.status,
                        label: tr(ref, note.status),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        '${note.id}',
                        textDirection: TextDirection.ltr,
                        style: TextStyle(
                          fontSize: 11,
                          color: cs.onSurfaceVariant.withValues(alpha: .6),
                          fontFeatures: const [
                            FontFeature.tabularFigures(),
                          ],
                        ),
                      ),
                    ],
                  ),
                  if (desc.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(
                      desc,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        height: 1.5,
                      ),
                    ),
                  ],
                  const SizedBox(height: 5),
                  Wrap(
                    spacing: 10,
                    runSpacing: 2,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      _Meta(
                        icon: Icons.videocam_outlined,
                        text:
                            '${tr(ref, 'camera')} ${note.cameraNumber} · ${tr(ref, 'floor')} ${note.floorNumber}',
                      ),
                      if (stamp.isNotEmpty)
                        _Meta(icon: Icons.schedule_outlined, text: stamp),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  final IconData icon;
  final String text;
  const _Meta({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    final c = Theme.of(context).colorScheme.onSurfaceVariant;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 13, color: c),
        const SizedBox(width: 4),
        Flexible(
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(fontSize: 11, color: c),
          ),
        ),
      ],
    );
  }
}

class _RecentReports extends ConsumerWidget {
  const _RecentReports();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final list = ref.watch(reportsListProvider);
    return list.when(
      loading: () => const _RecentPlaceholder(),
      error: (_, __) => _LoadRecentError(
        onRetry: () => ref.read(reportsListProvider.notifier).refresh(),
      ),
      data: (items) {
        if (items.isEmpty) {
          return _EmptyRecent(message: tr(ref, 'recent_empty'));
        }
        return RasdCard(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Column(
            children: [
              for (var i = 0; i < items.length && i < 3; i++) ...[
                if (i > 0) const Divider(height: 1, indent: 4),
                _ReportLogEntry(
                  report: items[i],
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ReportDetailPage(id: items[i].id),
                    ),
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _ReportLogEntry extends ConsumerWidget {
  final Report report;
  final VoidCallback onTap;
  const _ReportLogEntry({required this.report, required this.onTap});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final count = report.notesCount ?? report.notes.length;
    final tint = report.isPublished
        ? cs.primary
        : RasdColors.adaptive(context, RasdColors.goldInk);
    final title = report.title.trim().isEmpty
        ? tr(ref, 'new_report')
        : report.title.trim();
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: tint.withValues(alpha: .13),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                Icons.article_outlined,
                color: tint,
                size: 23,
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 7),
                      RasdStatusChip(
                        status: report.isPublished ? 'published' : 'draft',
                        label: tr(
                          ref,
                          report.isPublished ? 'published' : 'draft',
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 5),
                  _Meta(
                    icon: Icons.calendar_month_outlined,
                    text:
                        '${report.reportDate} · $count ${tr(ref, 'notes')}',
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RecentPlaceholder extends StatelessWidget {
  const _RecentPlaceholder();
  @override
  Widget build(BuildContext context) => Container(
    height: 96,
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHigh,
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      borderRadius: BorderRadius.circular(14),
    ),
  );
}

class _EmptyRecent extends StatelessWidget {
  final String message;
  const _EmptyRecent({required this.message});
  @override
  Widget build(BuildContext context) => RasdCard(
    child: Row(
      children: [
        Icon(
          Icons.notes_outlined,
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(message, style: Theme.of(context).textTheme.bodySmall),
        ),
      ],
    ),
  );
}

class _LoadRecentError extends ConsumerWidget {
  final Future<void> Function() onRetry;
  const _LoadRecentError({required this.onRetry});
  @override
  Widget build(BuildContext context, WidgetRef ref) => RasdCard(
    child: Row(
      children: [
        Icon(
          Icons.cloud_off_outlined,
          color: Theme.of(context).colorScheme.error,
        ),
        const SizedBox(width: 10),
        Expanded(child: Text(tr(ref, 'retry'))),
        IconButton(onPressed: onRetry, icon: const Icon(Icons.refresh_rounded)),
      ],
    ),
  );
}
