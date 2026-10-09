import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/models.dart';
import '../../core/strings.dart';
import '../../core/widgets.dart';
import '../auth/auth_provider.dart';
import '../notes/notes_page.dart';
import '../notes/notes_providers.dart';
import '../notes/notes_repo.dart';

final userProfileProvider = FutureProvider.family<_UserProfile, int>((
  ref,
  id,
) async {
  final dio = ref.watch(dioProvider);
  try {
    final res = await dio.get('/users/$id');
    final d = Map<String, dynamic>.from(unwrapData(res) as Map);
    final rawStats = Map<String, dynamic>.from(
      (d['stats'] as Map?) ?? const {},
    );
    return _UserProfile(
      user: User.fromJson(Map<String, dynamic>.from(d['user'] as Map)),
      isOwn: d['is_own'] == true,
      stats: {
        for (final e in rawStats.entries) e.key: (e.value as num).toInt(),
      },
    );
  } on DioException catch (e) {
    throw toApiException(e);
  }
});

class _UserProfile {
  final User user;
  final bool isOwn;
  final Map<String, int> stats;
  const _UserProfile({
    required this.user,
    required this.isOwn,
    required this.stats,
  });
}

void openUserProfile(BuildContext context, int userId) {
  Navigator.of(
    context,
  ).push(MaterialPageRoute(builder: (_) => UserProfilePage(userId: userId)));
}

class UserProfilePage extends ConsumerWidget {
  final int userId;
  const UserProfilePage({super.key, required this.userId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(userProfileProvider(userId));
    return Scaffold(
      appBar: AppBar(title: Text(tr(ref, 'view_profile'))),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 680),
          child: data.when(
            loading: () => const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (e, _) => RasdError(
              error: e,
              retryLabel: tr(ref, 'retry'),
              onRetry: () => ref.invalidate(userProfileProvider(userId)),
            ),
            data: (p) => _Body(profile: p),
          ),
        ),
      ),
    );
  }
}

class _Body extends ConsumerWidget {
  final _UserProfile profile;
  const _Body({required this.profile});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final u = profile.user;
    final dark = Theme.of(context).brightness == Brightness.dark;
    final nameColor = dark ? Colors.white : const Color(0xFF1A2E1F);
    final subColor = dark ? const Color(0xFFB9C6BB) : const Color(0xFF44564D);

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Stack(
          clipBehavior: Clip.none,
          children: [
            const _Cover(),
            PositionedDirectional(
              start: 16,
              bottom: -44,
              child: RasdAvatar(
                url: u.avatarUrl,
                name: u.name,
                size: 88,
                ringWidth: 3,
              ),
            ),
          ],
        ),
        const SizedBox(height: 52),
        Text(
          u.name,
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.w800,
            height: 1.3,
            color: nameColor,
            decoration: TextDecoration.none,
          ),
        ),
        const SizedBox(height: 6),
        Wrap(
          spacing: 8,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Text(
              '@${u.username}',
              style: TextStyle(fontSize: 12, color: subColor),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
              decoration: BoxDecoration(
                color: dark ? const Color(0xFF1E3328) : const Color(0xFFE9F4EC),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                tr(
                  ref,
                  u.isGM
                      ? 'role_gm'
                      : u.isWriter
                      ? 'role_writer'
                      : 'role_monitor',
                ),
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                  color: dark
                      ? const Color(0xFF4ADE80)
                      : const Color(0xFF0E6A38),
                ),
              ),
            ),
          ],
        ),
        if (u.personalNumber != null && u.personalNumber!.isNotEmpty) ...[
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: dark ? const Color(0xFF232B26) : const Color(0xFFF2F5F1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.badge_outlined,
                  size: 14,
                  color: subColor,
                ),
                const SizedBox(width: 6),
                Text(
                  u.personalNumber!,
                  textDirection: TextDirection.ltr,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: subColor,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
              ],
            ),
          ),
        ],
        if (!u.isGM) ...[
          const SizedBox(height: 16),
          _ViewNotesButton(profile: profile),
          const SizedBox(height: 12),
          _StatsGrid(stats: profile.stats),
        ],
      ],
    );
  }
}

class _ViewNotesButton extends ConsumerWidget {
  final _UserProfile profile;
  const _ViewNotesButton({required this.profile});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final u = profile.user;
    final total = profile.stats['total'] ?? 0;
    final cs = Theme.of(context).colorScheme;
    return SizedBox(
      width: double.infinity,
      child: FilledButton.tonalIcon(
        onPressed: () {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => ProviderScope(
                overrides: [
                  noteFilterProvider.overrideWith(
                    (ref) => NoteFilter(
                      observerId: u.id,
                      observerName: u.name,
                    ),
                  ),
                  notesListProvider.overrideWith(
                    (ref) => NotesListNotifier(ref),
                  ),
                ],
                child: Scaffold(
                  appBar: AppBar(
                    title: Text(
                      '${tr(ref, 'user_notes')} — ${u.name}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  body: const NotesPage(),
                ),
              ),
            ),
          );
        },
        icon: const Icon(Icons.inventory_2_outlined, size: 18),
        label: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                tr(ref, 'view_user_notes'),
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 9,
                  vertical: 2,
                ),
                decoration: BoxDecoration(
                  color: cs.primary,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  '$total',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    color: cs.onPrimary,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatsGrid extends ConsumerWidget {
  final Map<String, int> stats;
  const _StatsGrid({required this.stats});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final items = [
      (
        'user_notes',
        stats['total'] ?? 0,
        Icons.inventory_2_outlined,
        const Color(0xFF0E6A38),
        const Color(0xFFE3F2E8),
        const Color(0xFF12342A),
      ),
      (
        'accepted',
        stats['accepted'] ?? 0,
        Icons.task_alt_outlined,
        const Color(0xFF15803D),
        const Color(0xFFDDF5E3),
        const Color(0xFF12342A),
      ),
      (
        'pending',
        stats['pending'] ?? 0,
        Icons.schedule_outlined,
        const Color(0xFFB45309),
        const Color(0xFFFBF0D9),
        const Color(0xFF3A2A10),
      ),
      (
        'rejected',
        stats['rejected'] ?? 0,
        Icons.highlight_off_outlined,
        const Color(0xFFB91C1C),
        const Color(0xFFF9E2E2),
        const Color(0xFF3A1414),
      ),
    ];
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        mainAxisExtent: 104,
      ),
      itemCount: items.length,
      itemBuilder: (context, i) {
        final it = items[i];
        final iconColor = dark ? it.$6.withValues(alpha: .9) : it.$4;
        final iconBg = dark
            ? it.$4.withValues(alpha: .28)
            : it.$5;
        final valueColor = dark ? Colors.white : const Color(0xFF1A2E1F);
        return Container(
          decoration: BoxDecoration(
            color: dark ? const Color(0xFF232B26) : Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: dark
                  ? const Color(0xFF33403A)
                  : const Color(0xFFE4EAE3),
            ),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: iconBg,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(it.$3, size: 17, color: iconColor),
              ),
              const SizedBox(height: 6),
              Text(
                '${it.$2}',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  height: 1.1,
                  color: valueColor,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
              Text(
                tr(ref, it.$1),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w700,
                  color: dark
                      ? const Color(0xFFB9C6BB)
                      : const Color(0xFF5B6B5E),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class RasdUserChip extends ConsumerWidget {
  final int? userId;
  final String name;
  final String? avatarUrl;
  final double size;
  final bool showName;
  final bool showAvatar;
  final TextStyle? nameStyle;
  final double gap;
  final bool enabled;

  const RasdUserChip({
    super.key,
    required this.name,
    this.userId,
    this.avatarUrl,
    this.size = 40,
    this.showName = false,
    this.showAvatar = true,
    this.nameStyle,
    this.gap = 8,
    this.enabled = true,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final label = Text(
      name,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: nameStyle,
    );
    final Widget body = !showAvatar
        ? label
        : showName
        ? Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              RasdAvatar(url: avatarUrl, name: name, size: size),
              SizedBox(width: gap),
              Flexible(child: label),
            ],
          )
        : RasdAvatar(url: avatarUrl, name: name, size: size);

    final isSelf =
        enabled &&
        userId != null &&
        ref.watch(authProvider).valueOrNull?.id == userId;
    if (!enabled || userId == null || isSelf) return body;

    return InkWell(
      onTap: () => openUserProfile(context, userId!),
      borderRadius: BorderRadius.circular(999),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 2),
        child: body,
      ),
    );
  }
}

class _Cover extends StatelessWidget {
  const _Cover();

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final top = dark ? const Color(0xFF14532D) : const Color(0xFF0E6A38);
    final bottom = dark ? const Color(0xFF0B2417) : const Color(0xFF149A52);
    return Container(
      height: 148,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: LinearGradient(
          begin: Alignment.topRight,
          end: Alignment.bottomLeft,
          colors: [top, bottom],
        ),
        boxShadow: [
          BoxShadow(
            color: top.withValues(alpha: dark ? .4 : .25),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(18),
        child: Stack(
          children: [
            Positioned(
              right: -34,
              top: -44,
              child: Container(
                width: 130,
                height: 130,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: .10),
                ),
              ),
            ),
            Positioned(
              right: 44,
              top: -58,
              child: Container(
                width: 90,
                height: 90,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: .07),
                ),
              ),
            ),
            Positioned(
              left: -28,
              bottom: -52,
              child: Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: .06),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
