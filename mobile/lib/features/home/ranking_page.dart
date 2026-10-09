import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/strings.dart';
import '../../core/widgets.dart';
import 'user_profile_page.dart';

class _RankingData {
  final List<_Row> monitors;
  final Map<String, int> stats;
  _RankingData({required this.monitors, required this.stats});
}

class _Row {
  final int id;
  final String name;
  final String username;
  final int total;
  final int accepted;
  _Row({
    required this.id,
    required this.name,
    required this.username,
    required this.total,
    required this.accepted,
  });
}

final rankingProvider = FutureProvider<_RankingData>((ref) async {
  final dio = ref.watch(dioProvider);
  try {
    final res = await dio.get('/ranking');
    final d = Map<String, dynamic>.from(unwrapData(res) as Map);
    final monitors = (d['monitors'] as List? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(
          (m) => _Row(
            id: (m['id'] ?? 0) as int,
            name: (m['name'] ?? '').toString(),
            username: (m['username'] ?? '').toString(),
            total: (m['total_notes'] ?? 0) as int,
            accepted: (m['accepted_notes'] ?? 0) as int,
          ),
        )
        .toList();
    final stats = <String, int>{};
    for (final e in (d['stats'] as Map? ?? {}).entries) {
      stats[e.key.toString()] = (e.value as num? ?? 0).toInt();
    }
    return _RankingData(monitors: monitors, stats: stats);
  } on DioException catch (e) {
    throw toApiException(e);
  }
});

class RankingPage extends ConsumerWidget {
  const RankingPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(rankingProvider);
    return Scaffold(
      appBar: AppBar(title: Text(tr(ref, 'ranking'))),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(rankingProvider),
        child: data.when(
          loading: () => const RasdSkeletonList(),
          error: (e, _) => RasdError(
            error: e,
            onRetry: () => ref.invalidate(rankingProvider),
            retryLabel: tr(ref, 'retry'),
          ),
          data: (r) {
            final rows = [...r.monitors]
              ..sort((a, b) {
                final c = b.accepted.compareTo(a.accepted);
                return c != 0 ? c : b.total.compareTo(a.total);
              });
            return ListView.builder(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
              itemCount: rows.length + 1,
              itemBuilder: (_, i) {
                if (i == 0) return _StatsHeader(stats: r.stats);
                return _RankRow(rank: i, m: rows[i - 1]);
              },
            );
          },
        ),
      ),
    );
  }
}

class _StatsHeader extends ConsumerWidget {
  final Map<String, int> stats;
  const _StatsHeader({required this.stats});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final items = [
      ('total', stats['total'] ?? 0),
      ('accepted', stats['accepted'] ?? 0),
      ('pending', stats['pending'] ?? 0),
      ('rejected', stats['rejected'] ?? 0),
    ];
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: RasdCard(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 14),
        child: IntrinsicHeight(
          child: Row(
            children: [
              for (var i = 0; i < items.length; i++) ...[
                if (i > 0) Container(width: 1, color: cs.outlineVariant),
                Expanded(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        '${items[i].$2}',
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                          height: 1.1,
                          fontFeatures: [FontFeature.tabularFigures()],
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        tr(ref, items[i].$1),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 11,
                          color: cs.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _RankRow extends ConsumerWidget {
  final int rank;
  final _Row m;
  const _RankRow({required this.rank, required this.m});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final dark = cs.brightness == Brightness.dark;
    (Color, Color)? medal() {
      switch (rank) {
        case 1:
          return dark
              ? (const Color(0xFFF0C040), const Color(0xFF2E2716))
              : (const Color(0xFFB98A1F), const Color(0xFFFEF6E0));
        case 2:
          return dark
              ? (const Color(0xFFB6C2CF), const Color(0xFF232A33))
              : (const Color(0xFF64748B), const Color(0xFFF1F5F9));
        case 3:
          return dark
              ? (const Color(0xFFE0A35E), const Color(0xFF2E2118))
              : (const Color(0xFFB0793F), const Color(0xFFFBF1E6));
        default:
          return null;
      }
    }

    final md = medal();
    final rate = m.total == 0 ? 0 : ((m.accepted / m.total) * 100).round();
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: RasdCard(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: md == null ? cs.surfaceContainerHigh : md.$2,
                border: Border.all(
                  color: md == null ? cs.outline : md.$1.withValues(alpha: .4),
                ),
                borderRadius: BorderRadius.circular(15),
              ),
              alignment: Alignment.center,
              child: Text(
                '$rank',
                style: TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                  color: md == null ? cs.onSurfaceVariant : md.$1,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  RasdUserChip(
                    userId: m.id,
                    name: m.name,
                    showAvatar: false,
                    nameStyle: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      height: 1.4,
                    ),
                  ),
                  Text(
                    '@${m.username} · ${m.accepted}/${m.total} · $rate٪',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textDirection: TextDirection.ltr,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: cs.primaryContainer,
                border: Border.all(color: cs.primary.withValues(alpha: .3)),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    Icons.verified_outlined,
                    size: 13,
                    color: cs.onPrimaryContainer,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    '${m.accepted}',
                    style: TextStyle(
                      color: cs.onPrimaryContainer,
                      fontWeight: FontWeight.w800,
                      fontFeatures: const [FontFeature.tabularFigures()],
                    ),
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
