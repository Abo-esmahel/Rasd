import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/biometric_lock.dart';
import '../../core/storage.dart';
import '../../core/strings.dart';

class BiometricGate extends ConsumerStatefulWidget {
  final Widget child;
  const BiometricGate({super.key, required this.child});

  @override
  ConsumerState<BiometricGate> createState() => _BiometricGateState();
}

class _BiometricGateState extends ConsumerState<BiometricGate>
    with WidgetsBindingObserver {
  bool _ready = false;
  bool _enabled = false;
  bool _unlocked = false;
  bool _checking = false;
  bool _promptOnResume = false;
  ProviderSubscription<AsyncValue<bool>>? _subscription;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _subscription = ref.listenManual<AsyncValue<bool>>(
      biometricLockProvider,
      (_, next) {
        final enabled = next.valueOrNull;
        if (enabled == null || !mounted) return;
        if (!enabled) {
          setState(() {
            _enabled = false;
            _unlocked = true;
            _ready = true;
          });
        } else {
          setState(() => _enabled = true);
        }
      },
    );
    WidgetsBinding.instance.addPostFrameCallback((_) => _initialize());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _subscription?.close();
    super.dispose();
  }

  Future<void> _initialize() async {
    var enabled = false;
    try {
      enabled = await SecureStore.biometricLockEnabled();
    } catch (_) {}
    if (!mounted) return;

    if (enabled && !await BiometricLockService.hasEnrolledBiometrics()) {
      enabled = false;
      try {
        await SecureStore.setBiometricLockEnabled(false);
        await ref.read(biometricLockProvider.notifier).setEnabled(false);
      } catch (_) {}
    }

    setState(() {
      _enabled = enabled;
      _ready = true;
      _unlocked = !enabled;
    });
    if (enabled) await _authenticate();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!_enabled) return;
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused ||
        state == AppLifecycleState.hidden) {
      if (!_checking) {
        _promptOnResume = true;
        if (mounted && _unlocked) setState(() => _unlocked = false);
      }
    } else if (state == AppLifecycleState.resumed &&
        _promptOnResume &&
        !_checking) {
      _promptOnResume = false;
      _authenticate();
    }
  }

  Future<void> _authenticate() async {
    if (_checking || !mounted) return;
    _checking = true;
    if (!await BiometricLockService.hasEnrolledBiometrics()) {
      _checking = false;
      await ref.read(biometricLockProvider.notifier).setEnabled(false);
      return;
    }

    final success = await BiometricLockService.authenticate(
      tr(ref, 'biometric_unlock_reason'),
    );
    _checking = false;
    if (!mounted) return;
    setState(() => _unlocked = success);
  }

  @override
  Widget build(BuildContext context) {
    if (_ready && (!_enabled || _unlocked)) return widget.child;
    if (!_ready) return const _GateLoading();
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: colors.surface,
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 360),
            child: Padding(
              padding: const EdgeInsets.all(28),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    Icons.fingerprint_rounded,
                    size: 76,
                    color: colors.primary,
                  ),
                  const SizedBox(height: 18),
                  Text(
                    tr(ref, 'biometric_lock_title'),
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    tr(ref, 'biometric_lock_hint'),
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: colors.onSurfaceVariant,
                        ),
                  ),
                  const SizedBox(height: 22),
                  FilledButton.icon(
                    onPressed: !_ready || _checking ? null : _authenticate,
                    icon: _checking
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.fingerprint_rounded),
                    label: Text(tr(ref, 'biometric_unlock')),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _GateLoading extends StatelessWidget {
  const _GateLoading();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: colors.surface,
      body: const SafeArea(
        child: Center(
          child: SizedBox(
            width: 26,
            height: 26,
            child: CircularProgressIndicator(strokeWidth: 2.5),
          ),
        ),
      ),
    );
  }
}
