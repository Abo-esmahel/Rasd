import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'config.dart';
import 'storage.dart';


final avatarVersionProvider = StateProvider<int>((ref) => 0);

Future<void> loadAvatarVersion(Ref ref) async {
  try {
    final p = await Prefs.avatarVersion();
    if (p != null && p > 0) {
      ref.read(avatarVersionProvider.notifier).state = p;
    }
  } catch (_) {}
}

class AvatarUrl {
  static String of(WidgetRef ref, String? path) {
    final p = (path ?? '').trim();
    if (p.isEmpty) return '';
    final full = p.startsWith('http')
        ? p
        : '${ref.watch(baseUrlProvider)}$p';
    final v = ref.watch(avatarVersionProvider);
    final sep = full.contains('?') ? '&' : '?';
    return '$full${sep}v=$v';
  }

  static Future<void> bump(WidgetRef ref) async {
    final v = DateTime.now().millisecondsSinceEpoch ~/ 1000;
    ref.read(avatarVersionProvider.notifier).state = v;
    try {
      await Prefs.setAvatarVersion(v);
    } catch (_) {}
  }
}
