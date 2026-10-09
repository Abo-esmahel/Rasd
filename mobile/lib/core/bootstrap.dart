import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../features/auth/auth_provider.dart';
import '../features/notifications/notifications_repo.dart';
import 'avatar.dart';
import 'cache_janitor.dart';
import 'config.dart';
import 'notify_sound.dart';
import 'strings.dart';

enum BootPhase { storage, prefs, session, warmup, done }

class BootState {
  final BootPhase phase;
  final double progress;
  final Object? error;

  final bool sessionAvailable;
  const BootState({
    required this.phase,
    required this.progress,
    this.error,
    this.sessionAvailable = false,
  });

  bool get failed => error != null;
  bool get finished => phase == BootPhase.done && error == null;

  BootState clearError() => BootState(
    phase: phase,
    progress: progress,
    sessionAvailable: sessionAvailable,
  );
}

final bootstrapProvider = StateNotifierProvider<BootstrapNotifier, BootState>((
  ref,
) {
  return BootstrapNotifier(ref);
});

class BootstrapNotifier extends StateNotifier<BootState> {
  final Ref _ref;
  bool _running = false;

  BootstrapNotifier(this._ref)
    : super(const BootState(phase: BootPhase.storage, progress: 0));

  Future<void> start() async {
    if (_running || state.finished) return;
    _running = true;
    try {
      state = const BootState(phase: BootPhase.storage, progress: 0.05);
      // قراءات مستقلة تماماً (رابط السيرفر، اللغة، الصوت، نسخة الأفاتار،
      // تهيئة الإشعارات) — التتابع كان يجمع زمنها عبثاً، وأثقلها حوار
      // إذن النظام الذي قد يعلق أول تشغيل.
      await Future.wait([
        loadSavedBaseUrl(_ref),
        loadSavedLocale(_ref),
        loadSoundPref(_ref),
        loadAvatarVersion(_ref),
        NotifySound.init(),
      ]);

      state = const BootState(phase: BootPhase.prefs, progress: 0.25);

      state = const BootState(phase: BootPhase.session, progress: 0.55);
      final sessionAvailable = await _ref.read(authProvider.notifier).restore();

      state = BootState(
        phase: BootPhase.warmup,
        progress: 0.85,
        sessionAvailable: sessionAvailable,
      );
      if (sessionAvailable) {
        try {
          await _ref.read(unreadCountProvider.notifier).refresh();
        } catch (_) {}
      }

      state = BootState(
        phase: BootPhase.done,
        progress: 1,
        sessionAvailable: sessionAvailable,
      );

      unawaited(CacheJanitor.maybeSweep());
    } catch (e) {
      state = BootState(phase: state.phase, progress: state.progress, error: e);
    } finally {
      _running = false;
    }
  }

  Future<void> retry() async {
    state = state.clearError();
    await start();
  }
}
