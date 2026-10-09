import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/strings.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'reports_repo.dart';

class ReportInsightsPage extends ConsumerStatefulWidget {
  const ReportInsightsPage({super.key});

  @override
  ConsumerState<ReportInsightsPage> createState() => _ReportInsightsPageState();
}

class _ReportInsightsPageState extends ConsumerState<ReportInsightsPage> {
  String _range = '30d';
  late Future<Map<String, dynamic>> _data;

  @override
  void initState() {
    super.initState();
    _data = ref.read(reportsRepoProvider).insights(_range);
  }

  void _reload({String? range}) {
    setState(() {
      if (range != null) _range = range;
      _data = ref.read(reportsRepoProvider).insights(_range);
    });
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: Text(tr(ref, 'insights'))),
      body: RefreshIndicator(
        onRefresh: () async => _reload(),
        child: FutureBuilder<Map<String, dynamic>>(
          future: _data,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return ListView(
                padding: const EdgeInsets.all(24),
                children: [
                  const SizedBox(height: 100),
                  RasdError(
                    error: snapshot.error!,
                    onRetry: () => _reload(),
                    retryLabel: tr(ref, 'retry'),
                  ),
                ],
              );
            }
            if (!snapshot.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final data = snapshot.data!;
            final reports = _map(data['reports']);
            final notes = _map(data['notes']);
            final actions = data['actions'] is List
                ? (data['actions'] as List).whereType<Map>().toList()
                : const <Map>[];
            final daily = data['daily'] is List
                ? (data['daily'] as List).whereType<Map>().toList()
                : const <Map>[];

            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 32),
              children: [
                SegmentedButton<String>(
                  segments: [
                    ButtonSegment(
                      value: 'today',
                      label: Text(tr(ref, 'range_today')),
                    ),
                    ButtonSegment(
                      value: '7d',
                      label: Text(tr(ref, 'range_7d')),
                    ),
                    ButtonSegment(
                      value: '30d',
                      label: Text(tr(ref, 'range_30d')),
                    ),
                    ButtonSegment(
                      value: 'all',
                      label: Text(tr(ref, 'range_all')),
                    ),
                  ],
                  selected: {_range},
                  onSelectionChanged: (s) => _reload(range: s.first),
                ),
                const SizedBox(height: 14),
                RasdCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              tr(ref, 'health_score'),
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                          Text(
                            '${_int(data['health'])}/100',
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(99),
                        child: LinearProgressIndicator(
                          value: (_int(data['health']).clamp(0, 100)) / 100,
                          minHeight: 9,
                          backgroundColor: cs.surfaceContainerHigh,
                          color: _healthColor(context, _int(data['health'])),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 10,
                  crossAxisSpacing: 10,
                  childAspectRatio: 1.45,
                  children: [
                    _Metric(
                      label: tr(ref, 'reports'),
                      value: _int(reports['total']),
                      icon: Icons.description_outlined,
                    ),
                    _Metric(
                      label: tr(ref, 'publish_rate'),
                      value: _int(reports['publish_rate']),
                      suffix: '%',
                      icon: Icons.publish_outlined,
                    ),
                    _Metric(
                      label: tr(ref, 'notes'),
                      value: _int(notes['total']),
                      icon: Icons.note_alt_outlined,
                    ),
                    _Metric(
                      label: tr(ref, 'accept_rate'),
                      value: _int(notes['accept_rate']),
                      suffix: '%',
                      icon: Icons.verified_outlined,
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                if (actions.isNotEmpty) ...[
                  Text(
                    tr(ref, 'quick_actions'),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  for (final action in actions)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: _ActionCard(
                        label: _actionLabel(
                          ref,
                          action['key']?.toString() ?? '',
                        ),
                        count: action['count'],
                        level: action['level']?.toString() ?? 'info',
                      ),
                    ),
                ],
                const SizedBox(height: 8),
                Text(
                  tr(ref, 'daily_activity'),
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                _DailyBars(daily: daily, ref: ref),
                const SizedBox(height: 12),
                RasdStat(
                  icon: Icons.link_off_outlined,
                  value: '${_int(notes['unlinked_accepted'])}',
                  label: tr(ref, 'unlinked_notes'),
                  color: RasdColors.adaptive(context, RasdColors.goldInk),
                  soft: cs.secondaryContainer,
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  Map<String, dynamic> _map(dynamic value) =>
      value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

  int _int(dynamic value) =>
      value is num ? value.toInt() : int.tryParse('$value') ?? 0;

  Color _healthColor(BuildContext context, int score) {
    if (score >= 65) return RasdColors.adaptive(context, RasdColors.green);
    if (score >= 40) return RasdColors.adaptive(context, RasdColors.goldInk);
    return RasdColors.adaptive(context, RasdColors.danger);
  }

  String _actionLabel(WidgetRef ref, String key) => tr(ref, switch (key) {
    'pending' => 'insight_pending',
    'unlinked' => 'insight_unlinked',
    'drafts' => 'insight_drafts',
    'drafts_blocked' => 'insight_drafts_blocked',
    'coverage' => 'insight_coverage',
    'steady' => 'insight_steady',
    _ => 'insight_empty',
  });
}

class _Metric extends StatelessWidget {
  final String label;
  final int value;
  final String suffix;
  final IconData icon;
  const _Metric({
    required this.label,
    required this.value,
    required this.icon,
    this.suffix = '',
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return RasdCard(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Icon(icon, color: cs.primary, size: 20),
          Text('$value$suffix', style: Theme.of(context).textTheme.titleLarge),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  final String label;
  final dynamic count;
  final String level;
  const _ActionCard({
    required this.label,
    required this.count,
    required this.level,
  });

  @override
  Widget build(BuildContext context) {
    final color = level == 'critical' || level == 'high'
        ? Theme.of(context).colorScheme.error
        : Theme.of(context).colorScheme.primary;
    return RasdCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        children: [
          Icon(Icons.priority_high_rounded, color: color, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(label, style: Theme.of(context).textTheme.bodyMedium),
          ),
          if (count is num)
            Text(
              '$count',
              style: TextStyle(color: color, fontWeight: FontWeight.w800),
            ),
        ],
      ),
    );
  }
}

class _DailyBars extends StatelessWidget {
  final List<Map> daily;
  final WidgetRef ref;
  const _DailyBars({required this.daily, required this.ref});

  @override
  Widget build(BuildContext context) {
    if (daily.isEmpty) return RasdEmpty(message: tr(ref, 'no_results'));
    final slice = daily.length > 14 ? daily.sublist(daily.length - 14) : daily;
    final maxValue = slice.fold<int>(1, (max, d) {
      final value =
          (d['reports'] is num ? (d['reports'] as num).toInt() : 0) +
          (d['notes'] is num ? (d['notes'] as num).toInt() : 0);
      return value > max ? value : max;
    });
    return RasdCard(
      child: SizedBox(
        height: 126,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            for (final item in slice)
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 2),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Align(
                          alignment: Alignment.bottomCenter,
                          child: FractionallySizedBox(
                            heightFactor:
                                (((item['reports'] is num
                                                ? item['reports'] as num
                                                : 0) +
                                            (item['notes'] is num
                                                ? item['notes'] as num
                                                : 0)) /
                                        maxValue)
                                    .clamp(.04, 1.0),
                            child: Container(
                              width: double.infinity,
                              decoration: BoxDecoration(
                                color: Theme.of(
                                  context,
                                ).colorScheme.primary.withValues(alpha: .75),
                                borderRadius: BorderRadius.circular(5),
                              ),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        (item['label'] ?? '').toString().split('-').last,
                        maxLines: 1,
                        style: Theme.of(
                          context,
                        ).textTheme.bodySmall?.copyWith(fontSize: 9),
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
