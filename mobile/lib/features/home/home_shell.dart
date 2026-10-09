import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models.dart';
import '../../core/offline_cache.dart';
import '../../core/strings.dart';
import '../../core/widgets.dart';
import '../auth/auth_provider.dart';
import '../gallery/gallery_page.dart';
import '../notes/note_create_page.dart';
import '../notes/notes_page.dart';
import 'dashboard_page.dart';
import '../notes/notes_providers.dart';
import '../notifications/notifications_page.dart';
import '../notifications/notifications_repo.dart';
import '../reports/reports_page.dart';
import '../reports/reports_providers.dart';
import '../submissions/submission_create_page.dart';
import '../submissions/submissions_page.dart';
import '../submissions/submissions_repo.dart';
import 'profile_page.dart';

class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key});

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell>
    with WidgetsBindingObserver {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      ref.read(unreadCountProvider.notifier).refresh();
      ref.read(notificationsListProvider.notifier).pollTick();
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).valueOrNull;
    final unread = ref.watch(unreadCountProvider);
    final gm = user?.isGM ?? false;
    final isMonitor = user == null ? false : user.isMonitor;
    final isWriter = user == null ? false : user.isWriter;

    final tabs = <_Tab>[];
    void add(
      String id,
      Widget page,
      String label,
      IconData icon,
      IconData selectedIcon,
    ) {
      tabs.add(_Tab(id, page, label, icon, selectedIcon));
    }

    add(
      'home',
      const DashboardPage(),
      tr(ref, 'home'),
      Icons.home_outlined,
      Icons.home_rounded,
    );
    if (!gm) {
      add(
        'notes',
        const NotesPage(),
        tr(ref, 'notes'),
        Icons.note_alt_outlined,
        Icons.note_alt_rounded,
      );
      add(
        'submissions',
        const SubmissionsPage(),
        tr(ref, 'submissions'),
        Icons.outbox_outlined,
        Icons.outbox_rounded,
      );
    }
    if (isWriter || gm) {
      add(
        'reports',
        const ReportsPage(),
        tr(ref, 'reports'),
        Icons.description_outlined,
        Icons.description_rounded,
      );
    }
    add(
      'gallery',
      const GalleryPage(),
      tr(ref, 'gallery'),
      Icons.photo_library_outlined,
      Icons.photo_library_rounded,
    );

    _index = _index.clamp(0, tabs.length - 1);
    final current = tabs[_index].id;
    final fabTarget = current == 'notes' && (isMonitor || isWriter)
        ? 'note'
        : current == 'submissions' && isMonitor
        ? 'submission'
        : current == 'reports' && isWriter
        ? 'report'
        : null;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(tr(ref, 'app_name')),
            Text(
              tr(ref, 'ministry'),
              style: TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w600,
                color: Theme.of(context).colorScheme.onSurfaceVariant,
                height: 1.3,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            onPressed: () => Navigator.push(
              context,
              RasdSlideRoute(page: const NotificationsPage()),
            ),
            icon: Badge(
              isLabelVisible: unread > 0,
              label: Text('$unread'),
              child: const Icon(Icons.notifications_outlined),
            ),
          ),
          Padding(
            padding: const EdgeInsetsDirectional.only(end: 8),
            child: _AvatarChip(
              user: user,
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ProfilePage()),
              ),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          Consumer(
            builder: (context, ref, _) {
              if (!ref.watch(offlineStatusProvider)) {
                return const SizedBox.shrink();
              }
              final dark = Theme.of(context).brightness == Brightness.dark;
              return Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 9,
                ),
                color: dark
                    ? const Color(0xFF3B321D)
                    : const Color(0xFFFFF4D6),
                child: Row(
                  children: [
                    Icon(
                      Icons.cloud_off_outlined,
                      size: 18,
                      color: dark
                          ? const Color(0xFFFFD166)
                          : const Color(0xFF8A5A00),
                    ),
                    const SizedBox(width: 9),
                    Expanded(
                      child: Text(
                        tr(ref, 'offline_banner'),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 11.5,
                          height: 1.35,
                          fontWeight: FontWeight.w700,
                          color: dark
                              ? const Color(0xFFFFE4A0)
                              : const Color(0xFF704900),
                        ),
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
          Expanded(
            child: IndexedStack(
              index: _index,
              children: [for (final t in tabs) t.page],
            ),
          ),
        ],
      ),
      floatingActionButton: fabTarget == null
          ? null
          : FloatingActionButton(
              onPressed: () async {
                if (fabTarget == 'report') {
                  await showModalBottomSheet(
                    context: context,
                    isScrollControlled: true,
                    builder: (_) => const ReportCreateSheet(),
                  );
                  try {
                    await ref.read(reportsListProvider.notifier).refresh();
                  } catch (_) {}
                  return;
                }
                final ok = await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => fabTarget == 'note'
                        ? const NoteCreatePage()
                        : const SubmissionCreatePage(),
                  ),
                );
                if (ok == true && context.mounted) {
                  try {
                    if (fabTarget == 'note') {
                      await ref.read(notesListProvider.notifier).refresh();
                      ref.invalidate(profileStatsProvider);
                    } else {
                      await ref
                          .read(submissionsListProvider.notifier)
                          .refresh();
                    }
                  } catch (_) {}
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(tr(ref, 'created_ok'))),
                  );
                }
              },
              child: const Icon(Icons.add),
            ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: [
          for (var i = 0; i < tabs.length; i++)
            NavigationDestination(
              icon: Icon(tabs[i].icon),
              selectedIcon: Icon(tabs[i].selectedIcon),
              label: tabs[i].label,
            ),
        ],
      ),
    );
  }
}

class _Tab {
  final String id;
  final Widget page;
  final String label;
  final IconData icon;
  final IconData selectedIcon;
  _Tab(this.id, this.page, this.label, this.icon, this.selectedIcon);
}

class _AvatarChip extends ConsumerWidget {
  final User? user;
  final VoidCallback onTap;
  const _AvatarChip({required this.user, required this.onTap});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: RasdAvatar(
        url: user?.avatarUrl,
        name: user?.name ?? '?',
        size: 38,
        ringWidth: 2,
      ),
    );
  }
}
