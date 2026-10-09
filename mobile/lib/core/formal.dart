import 'package:flutter/material.dart';
import 'strings.dart';

class RasdFormal {
  static String fullDate(BuildContext context, DateTime dt) =>
      MaterialLocalizations.of(context).formatFullDate(dt.toLocal());

  static String dateTime(BuildContext context, DateTime dt) {
    final loc = MaterialLocalizations.of(context);
    final l = dt.toLocal();
    return '${loc.formatMediumDate(l)} · ${loc.formatTimeOfDay(TimeOfDay.fromDateTime(l))}';
  }

  static String time(BuildContext context, DateTime dt) =>
      MaterialLocalizations.of(context)
          .formatTimeOfDay(TimeOfDay.fromDateTime(dt.toLocal()));

  static String compact(BuildContext context, DateTime dt) {
    final l = dt.toLocal();
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final that = DateTime(l.year, l.month, l.day);
    final diff = today.difference(that).inDays;

    if (diff == 0) return time(context, l);
    if (diff == 1) return trc(context, 'yesterday');
    return MaterialLocalizations.of(context).formatShortDate(l);
  }

  static DateTime? tryParseDate(String raw) {
    final t = raw.trim();
    if (t.isEmpty) return null;
    return DateTime.tryParse(t.length >= 10 ? t.substring(0, 10) : t);
  }
}

class RasdTimestamp extends StatelessWidget {
  final DateTime? at;
  final bool dense;
  const RasdTimestamp({super.key, this.at, this.dense = false});

  @override
  Widget build(BuildContext context) {
    final moment = at;
    if (moment == null) return const SizedBox.shrink();

    final label = RasdFormal.compact(context, moment);
    final style = Theme.of(context).textTheme.labelSmall?.copyWith(
      color: Theme.of(context).colorScheme.onSurfaceVariant,
      decoration: TextDecoration.underline,
      decorationColor: Theme.of(
        context,
      ).colorScheme.onSurfaceVariant.withValues(alpha: .35),
      decorationStyle: TextDecorationStyle.solid,
    );

    return Tooltip(
      triggerMode: TooltipTriggerMode.tap,
      preferBelow: false,
      message: RasdFormal.dateTime(context, moment),
      textStyle: const TextStyle(fontSize: 12, color: Colors.white),
      decoration: BoxDecoration(
        color: const Color(0xFF1F2A24),
        borderRadius: BorderRadius.circular(8),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      waitDuration: const Duration(milliseconds: 200),
      child: InkWell(
        borderRadius: BorderRadius.circular(6),
        onTap: () {},
        child: Padding(
          padding: EdgeInsets.symmetric(
            horizontal: 4,
            vertical: dense ? 2 : 4,
          ),
          child: Text(label, style: style),
        ),
      ),
    );
  }
}

String trc(BuildContext context, String key) =>
    trOf(Localizations.localeOf(context).languageCode, key);
