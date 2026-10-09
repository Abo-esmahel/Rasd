import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../users/manage_users_page.dart';

import '../../core/api_client.dart';
import '../../core/biometric_lock.dart';
import '../../core/config.dart';
import '../../core/storage.dart';
import '../../core/strings.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../../core/validators.dart';
import '../../core/server_pdf.dart';
import '../auth/auth_provider.dart';
import 'profile_edit_page.dart';
import 'ranking_page.dart';

final profileStatsProvider = FutureProvider<Map<String, int>>((ref) async {
  final dio = ref.watch(dioProvider);
  try {
    final res = await dio.get('/me/stats');
    final body = res.data as Map;
    final d = Map<String, dynamic>.from(body['data'] as Map);
    return {
      for (final k in ['total', 'draft', 'pending', 'accepted', 'rejected'])
        k: ((d[k] ?? 0) as num).toInt(),
    };
  } on DioException catch (e) {
    throw toApiException(e);
  }
});

final themeModeProvider = StateNotifierProvider<ThemeModeNotifier, ThemeMode>(
  (ref) => ThemeModeNotifier(),
);

class ThemeModeNotifier extends StateNotifier<ThemeMode> {
  ThemeModeNotifier() : super(ThemeMode.light) {
    _load();
  }

  Future<void> _load() async {
    try {
      final saved = await Prefs.themeMode();
      state = switch (saved) {
        'dark' => ThemeMode.dark,
        'system' => ThemeMode.system,
        _ => ThemeMode.light,
      };
    } catch (_) {}
  }

  Future<void> set(ThemeMode mode) async {
    state = mode;
    try {
      await Prefs.setThemeMode(switch (mode) {
        ThemeMode.dark => 'dark',
        ThemeMode.system => 'system',
        ThemeMode.light => 'light',
      });
    } catch (_) {}
  }
}

class ProfilePage extends ConsumerStatefulWidget {
  const ProfilePage({super.key});

  @override
  ConsumerState<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends ConsumerState<ProfilePage> {
  bool _loggingOut = false;

  Future<void> _logout() async {
    if (_loggingOut) return;
    final lang = ref.read(localeProvider);
    final ok = await confirmDestructive(
      context,
      title: trOf(lang, 'logout_confirm_title'),
      body: trOf(lang, 'logout_confirm_body'),
      confirmLabel: trOf(lang, 'logout'),
      cancelLabel: trOf(lang, 'cancel'),
    );
    if (!ok || !mounted) return;
    setState(() => _loggingOut = true);
    try {
      await ref.read(authProvider.notifier).logout();
      if (!mounted) return;
      Navigator.of(context).popUntil((route) => route.isFirst);
    } finally {
      if (mounted) setState(() => _loggingOut = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ref = this.ref;
    final user = ref.watch(authProvider).valueOrNull;
    final theme = ref.watch(themeModeProvider);
    return Scaffold(
      appBar: AppBar(title: Text(tr(ref, 'account'))),
      body: Center(
        child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 680),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (user != null) ...[
              Stack(
                clipBehavior: Clip.none,
                children: [
                  _Cover(),
                  PositionedDirectional(
                    start: 16,
                    bottom: -44,
                    child: RasdAvatar(
                      url: user.avatarUrl,
                      name: user.name,
                      size: 88,
                      ringWidth: 3,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 52),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Builder(
                    builder: (context) {
                      final dark =
                          Theme.of(context).brightness == Brightness.dark;
                      final nameColor = dark
                          ? Colors.white
                          : const Color(0xFF1A2E1F);
                      final subColor = dark
                          ? const Color(0xFFB9C6BB)
                          : const Color(0xFF44564D);
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user.name,
                            style: TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                              height: 1.3,
                              color: nameColor,
                              decoration: TextDecoration.none,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Row(
                            children: [
                              Flexible(
                                child: Text(
                                  '@${user.username}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  textDirection: TextDirection.ltr,
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: subColor,
                                    decoration: TextDecoration.none,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 3,
                                ),
                                decoration: BoxDecoration(
                                  color: dark
                                      ? const Color(0xFF1E3328)
                                      : const Color(0xFFE9F4EC),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Text(
                                  tr(
                                    ref,
                                    user.isGM
                                        ? 'role_gm'
                                        : user.isWriter
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
                          if (user.personalNumber != null &&
                              user.personalNumber!.isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(
                              user.personalNumber!,
                              textDirection: TextDirection.ltr,
                              style: TextStyle(fontSize: 12, color: subColor),
                            ),
                          ],
                        ],
                      );
                    },
                  ),
                ],
              ),
              const SizedBox(height: 2),
            ],
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () async {
                  final ok = await Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const ProfileEditPage()),
                  );
                  if (ok == true) {
                    ref.invalidate(profileStatsProvider);
                  }
                },
                icon: const Icon(Icons.edit_outlined, size: 18),
                label: Text(tr(ref, 'edit_profile')),
              ),
            ),
            if (!(user?.isGM ?? false)) ...[
              const SizedBox(height: 10),
              Consumer(
                builder: (context, ref, _) {
                  final stats = ref.watch(profileStatsProvider);
                  return stats.when(
                    loading: () => const Card(
                      child: Padding(
                        padding: EdgeInsets.all(20),
                        child: Center(child: CircularProgressIndicator()),
                      ),
                    ),
                    error: (_, __) => RasdCard(
                      child: Row(
                        children: [
                          Icon(
                            Icons.cloud_off_outlined,
                            color: Theme.of(context).colorScheme.error,
                          ),
                          const SizedBox(width: 10),
                          Expanded(child: Text(tr(ref, 'retry'))),
                          IconButton(
                            tooltip: tr(ref, 'retry'),
                            onPressed: () => ref.invalidate(profileStatsProvider),
                            icon: const Icon(Icons.refresh_rounded),
                          ),
                        ],
                      ),
                    ),
                    data: (s) => _StatsCard(s: s),
                  );
                },
              ),
            ],
            const SizedBox(height: 10),
            RasdCard(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Column(
                children: [
                  _tile(
                    context,
                    icon: Icons.leaderboard_outlined,
                    title: tr(ref, 'ranking'),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const RankingPage()),
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16),
                    child: Divider(height: 1),
                  ),
                  _tile(
                    context,
                    icon: Icons.language_outlined,
                    title: tr(ref, 'language'),
                    trailing: SizedBox(
                      width: 150,
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        alignment: AlignmentDirectional.centerEnd,
                        child: SegmentedButton<String>(
                          segments: const [
                            ButtonSegment(value: 'ar', label: Text('عربي')),
                            ButtonSegment(value: 'en', label: Text('EN')),
                          ],
                          selected: {ref.watch(localeProvider)},
                          onSelectionChanged: (s) async {
                            ref.read(localeProvider.notifier).state = s.first;
                            ServerPdf.clearMemo();
                            await Prefs.setLocale(s.first);
                          },
                        ),
                      ),
                    ),
                  ),
                  if (user?.isGM ?? false) ...[
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 16),
                      child: Divider(height: 1),
                    ),
                    _tile(
                      context,
                      icon: Icons.admin_panel_settings_outlined,
                      title: tr(ref, 'manage_users'),
                      subtitle: tr(ref, 'manage_users_subtitle'),
                      ltrSubtitle: false,
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const ManageUsersPage(),
                        ),
                      ),
                    ),
                  ],
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16),
                    child: Divider(height: 1),
                  ),
                  _tile(
                    context,
                    icon: Icons.brightness_6_outlined,
                    title: tr(ref, 'theme'),
                    trailing: SizedBox(
                      width: 210,
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        alignment: AlignmentDirectional.centerEnd,
                        child: SegmentedButton<ThemeMode>(
                          segments: [
                            const ButtonSegment(
                              value: ThemeMode.system,
                              icon: Icon(Icons.settings_suggest_outlined),
                            ),
                            ButtonSegment(
                              value: ThemeMode.dark,
                              label: Text(tr(ref, 'dark')),
                            ),
                            ButtonSegment(
                              value: ThemeMode.light,
                              label: Text(tr(ref, 'light')),
                            ),
                          ],
                          selected: {theme},
                          onSelectionChanged: (s) async {
                            await ref
                                .read(themeModeProvider.notifier)
                                .set(s.first);
                          },
                        ),
                      ),
                    ),
                  ),
                  Consumer(
                    builder: (context, ref, _) {
                      final supported =
                          ref.watch(biometricSupportProvider).valueOrNull ==
                              true;
                      final lock = ref.watch(biometricLockProvider);
                      if (!supported || lock.isLoading) {
                        return const SizedBox.shrink();
                      }
                      return Column(
                        children: [
                          const Padding(
                            padding: EdgeInsets.symmetric(horizontal: 16),
                            child: Divider(height: 1),
                          ),
                          _tile(
                            context,
                            icon: Icons.fingerprint_rounded,
                            title: tr(ref, 'biometric_lock'),
                            trailing: Switch.adaptive(
                              value: lock.valueOrNull ?? false,
                              onChanged: (enabled) async {
                                if (enabled) {
                                  final verified =
                                      await BiometricLockService.authenticate(
                                    tr(ref, 'biometric_enable_reason'),
                                  );
                                  if (!verified || !context.mounted) return;
                                }
                                await ref
                                    .read(biometricLockProvider.notifier)
                                    .setEnabled(enabled);
                              },
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16),
                    child: Divider(height: 1),
                  ),
                  _tile(
                    context,
                    icon: Icons.dns_outlined,
                    title: tr(ref, 'server_address'),
                    subtitle: ref.watch(baseUrlProvider),
                    onTap: () async {
                      final ctrl = TextEditingController(
                        text: ref.read(baseUrlProvider),
                      );
                      String? serverError;
                      final ok = await showDialog<bool>(
                        context: context,
                        builder: (ctx) => StatefulBuilder(
                          builder: (ctx, setDialogState) => AlertDialog(
                            title: Text(tr(ref, 'server_address')),
                            content: TextField(
                              controller: ctrl,
                              textDirection: TextDirection.ltr,
                              keyboardType: TextInputType.url,
                              decoration: InputDecoration(
                                errorText: serverError,
                                hintText: 'https://example.com',
                              ),
                              onChanged: (_) =>
                                  setDialogState(() => serverError = null),
                            ),
                            actions: [
                              TextButton(
                                onPressed: () => Navigator.pop(ctx, false),
                                child: Text(tr(ref, 'cancel')),
                              ),
                              FilledButton(
                                onPressed: () {
                                  final error = V.serverAddress(ctrl.text);
                                  if (error != null) {
                                    setDialogState(() => serverError = error);
                                    return;
                                  }
                                  Navigator.pop(ctx, true);
                                },
                                child: Text(tr(ref, 'confirm')),
                              ),
                            ],
                          ),
                        ),
                      );
                      if (ok == true) {
                        final base = normalizeBaseUrl(ctrl.text);
                        ref.read(baseUrlProvider.notifier).state = base;
                        await saveBaseUrl(base);
                      }
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _loggingOut ? null : _logout,
              icon: _loggingOut
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.logout_outlined),
              label: Text(tr(ref, 'logout')),
              style: OutlinedButton.styleFrom(
                foregroundColor: Theme.of(context).colorScheme.error,
                minimumSize: const Size.fromHeight(50),
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
        ),
      ),
    );
  }
}

class _Cover extends ConsumerWidget {
  const _Cover();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
                  border: Border.all(
                    color: Colors.white.withValues(alpha: .14),
                    width: 2,
                  ),
                ),
              ),
            ),
            Align(
              alignment: AlignmentDirectional.topEnd,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Container(
                  width: 66,
                  height: 66,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: const Color(0xFFD9AB3C),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: .25),
                        blurRadius: 10,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  alignment: Alignment.center,
                  child: ClipOval(
                    child: Container(
                      width: 60,
                      height: 60,
                      decoration: const BoxDecoration(
                        gradient: RadialGradient(
                          colors: [Color(0xFF0A3A33), Color(0xFF002623)],
                        ),
                      ),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 11,
                      ),
                      child: Image.asset(
                        'assets/eagle-emblem.png',
                        fit: BoxFit.contain,
                        filterQuality: FilterQuality.medium,
                      ),
                    ),
                  ),
                ),
              ),
            ),
            Align(
              alignment: AlignmentDirectional.bottomStart,
              child: Padding(
                padding: const EdgeInsetsDirectional.only(
                  start: 16,
                  bottom: 12,
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 7,
                      height: 7,
                      decoration: const BoxDecoration(
                        shape: BoxShape.circle,
                        color: Color(0xFFD9AB3C),
                      ),
                    ),
                    const SizedBox(width: 7),
                    Text(
                      tr(ref, 'ministry'),
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
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

Widget _tile(
  BuildContext context, {
  required IconData icon,
  required String title,
  String? subtitle,
  Widget? trailing,
  VoidCallback? onTap,
  bool ltrSubtitle = true,
}) {
  return ListTile(
    leading: Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: Theme.of(
          context,
        ).colorScheme.primaryContainer.withValues(alpha: .5),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Icon(icon, size: 19),
    ),
    title: Text(
      title,
      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
    ),
    subtitle: subtitle == null
        ? null
        : Text(
            subtitle,
            textDirection:
                ltrSubtitle ? TextDirection.ltr : TextDirection.rtl,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 11,
              fontFamily: ltrSubtitle ? 'monospace' : null,
            ),
          ),
    trailing:
        trailing ??
        (onTap == null ? null : const Icon(Icons.chevron_left, size: 20)),
    onTap: onTap,
    contentPadding: const EdgeInsets.symmetric(horizontal: 12),
  );
}

class _StatsCard extends ConsumerWidget {
  final Map<String, int> s;
  const _StatsCard({required this.s});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final total = s['total'] ?? 0;
    final accepted = s['accepted'] ?? 0;
    final pct = total == 0
        ? 0.0
        : (accepted / total).clamp(0.0, 1.0).toDouble();
    final items = [
      ('my_notes', total, Icons.inventory_2_outlined, RasdColors.green),
      ('accepted', accepted, Icons.task_alt_outlined, RasdColors.green),
      ('pending', s['pending'] ?? 0, Icons.schedule_outlined,
        RasdColors.goldInk),
      ('rejected', s['rejected'] ?? 0, Icons.highlight_off_outlined,
        RasdColors.danger),
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
                  color: cs.primaryContainer.withValues(alpha: .6),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  Icons.insights_outlined,
                  color: cs.primary,
                  size: 20,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      tr(ref, 'acceptance'),
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      '$accepted / $total ${tr(ref, 'accepted')}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              Text(
                '${(pct * 100).round()}٪',
                style: TextStyle(
                  color: cs.primary,
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  fontFeatures: const [FontFeature.tabularFigures()],
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
              backgroundColor: cs.surfaceContainerHigh,
              valueColor: AlwaysStoppedAnimation(cs.primary),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Divider(height: 1, color: cs.outlineVariant),
          ),
          IntrinsicHeight(
            child: Row(
              children: [
                for (var i = 0; i < items.length; i++) ...[
                  if (i > 0)
                    Container(width: 1, color: cs.outlineVariant),
                  Expanded(
                    child: _MiniStat(
                      value: items[i].$2,
                      label: tr(ref, items[i].$1),
                      icon: items[i].$3,
                      color: RasdColors.adaptive(context, items[i].$4),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  final int value;
  final String label;
  final IconData icon;
  final Color color;
  const _MiniStat({
    required this.value,
    required this.label,
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: color.withValues(alpha: .13),
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(icon, color: color, size: 17),
        ),
        const SizedBox(height: 6),
        Text(
          '$value',
          style: const TextStyle(
            fontSize: 19,
            fontWeight: FontWeight.w800,
            height: 1.1,
            fontFeatures: [FontFeature.tabularFigures()],
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(fontSize: 11, color: muted),
        ),
      ],
    );
  }
}

ThemeData rasdLight() => RasdTheme.light();
ThemeData rasdDark() => RasdTheme.dark();
