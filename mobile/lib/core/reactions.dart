import 'package:flutter/material.dart';

import 'strings.dart';

class RasdReactions {
  static const Map<String, String> emoji = {
    'like': '👍',
    'love': '❤️',
    'insightful': '💡',
    'thanks': '🙏',
    'dislike': '👎',
    'angry': '😡',
  };

  static const List<String> order = [
    'like',
    'love',
    'insightful',
    'thanks',
    'dislike',
    'angry',
  ];

  static String labelOf(String lang, String key) =>
      trOf(lang, 'reaction_$key');

  static bool isNegative(String key) => key == 'dislike' || key == 'angry';

  static int totalOf(Map<String, int> counts) =>
      counts.values.fold(0, (a, b) => a + b);
}

class RasdReactionBar extends StatelessWidget {
  final Map<String, int> counts;
  final String? myReaction;
  final bool busy;
  final bool compact;
  final String lang;
  final ValueChanged<String> onReact;

  const RasdReactionBar({
    super.key,
    required this.counts,
    required this.myReaction,
    required this.onReact,
    required this.lang,
    this.busy = false,
    this.compact = true,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final visible = RasdReactions.order
        .where((k) => (counts[k] ?? 0) > 0 || myReaction == k)
        .toList();

    return Wrap(
      spacing: 6,
      runSpacing: 6,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        for (final key in visible)
          _Pill(
            emoji: RasdReactions.emoji[key]!,
            label: RasdReactions.labelOf(lang, key),
            count: counts[key] ?? 0,
            selected: myReaction == key,
            negative: RasdReactions.isNegative(key),
            compact: compact,
            onTap: busy ? null : () => onReact(key),
          ),
        _AddButton(
          compact: compact,
          tooltip: trOf(lang, 'react'),
          onTap: busy
              ? null
              : () => showReactionPicker(
                    context: context,
                    lang: lang,
                    counts: counts,
                    myReaction: myReaction,
                    onPick: onReact,
                  ),
        ),
        if (busy)
          SizedBox(
            width: compact ? 16 : 18,
            height: compact ? 16 : 18,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: cs.primary,
            ),
          ),
      ],
    );
  }
}

class _Pill extends StatelessWidget {
  final String emoji;
  final String label;
  final int count;
  final bool selected;
  final bool negative;
  final bool compact;
  final VoidCallback? onTap;

  const _Pill({
    required this.emoji,
    required this.label,
    required this.count,
    required this.selected,
    required this.negative,
    required this.compact,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final accent = negative ? cs.error : cs.primary;
    final accentContainer = negative ? cs.errorContainer : cs.primaryContainer;
    return ActionChip(
      avatar: Text(emoji, style: TextStyle(fontSize: compact ? 14 : 17)),
      label: Text('$count'),
      tooltip: label,
      onPressed: onTap,
      backgroundColor: selected ? accentContainer : null,
      side: BorderSide(color: selected ? accent : cs.outlineVariant),
      labelStyle: TextStyle(
        color: selected ? accent : null,
        fontWeight: selected ? FontWeight.w800 : null,
      ),
      visualDensity: compact ? VisualDensity.compact : VisualDensity.standard,
      padding: EdgeInsets.symmetric(horizontal: compact ? 2 : 4),
    );
  }
}

class _AddButton extends StatelessWidget {
  final bool compact;
  final String tooltip;
  final VoidCallback? onTap;

  const _AddButton({
    required this.compact,
    required this.tooltip,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return IconButton(
      tooltip: tooltip,
      onPressed: onTap,
      iconSize: compact ? 19 : 21,
      visualDensity: VisualDensity.compact,
      style: IconButton.styleFrom(
        side: BorderSide(color: cs.outlineVariant),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(999),
        ),
      ),
      icon: const Icon(Icons.add_reaction_outlined),
    );
  }
}

Future<void> showReactionPicker({
  required BuildContext context,
  required String lang,
  required Map<String, int> counts,
  required String? myReaction,
  required ValueChanged<String> onPick,
}) {
  final cs = Theme.of(context).colorScheme;
  return showModalBottomSheet(
    context: context,
    showDragHandle: true,
    builder: (ctx) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 8),
            child: Text(
              trOf(lang, 'choose_reaction'),
              style: Theme.of(ctx).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
          ),
          Flexible(
            child: ListView(
              shrinkWrap: true,
              children: [
                for (final key in RasdReactions.order)
                  ListTile(
                    leading: Text(
                      RasdReactions.emoji[key]!,
                      style: const TextStyle(fontSize: 24),
                    ),
                    title: Text(RasdReactions.labelOf(lang, key)),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if ((counts[key] ?? 0) > 0)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: cs.surfaceContainerHighest,
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              '${counts[key]}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        if (myReaction == key) ...[
                          const SizedBox(width: 6),
                          Icon(Icons.check_circle,
                              color: cs.primary, size: 20),
                        ],
                      ],
                    ),
                    onTap: () {
                      Navigator.pop(ctx);
                      onPick(key);
                    },
                  ),
              ],
            ),
          ),
          const SizedBox(height: 8),
        ],
      ),
    ),
  );
}
