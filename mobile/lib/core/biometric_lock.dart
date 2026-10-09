import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:local_auth/local_auth.dart';

import 'storage.dart';

final biometricSupportProvider = FutureProvider<bool>((ref) async {
  return BiometricLockService.hasEnrolledBiometrics();
});

final biometricLockProvider = StateNotifierProvider<BiometricLockNotifier,
    AsyncValue<bool>>((ref) => BiometricLockNotifier());

class BiometricLockNotifier extends StateNotifier<AsyncValue<bool>> {
  BiometricLockNotifier() : super(const AsyncValue.loading()) {
    _load();
  }

  Future<void> _load() async {
    try {
      state = AsyncValue.data(await SecureStore.biometricLockEnabled());
    } catch (error, stack) {
      state = AsyncValue.error(error, stack);
    }
  }

  Future<void> setEnabled(bool enabled) async {
    await SecureStore.setBiometricLockEnabled(enabled);
    state = AsyncValue.data(enabled);
  }
}

class BiometricLockService {
  static final LocalAuthentication _auth = LocalAuthentication();

  static Future<bool> hasEnrolledBiometrics() async {
    try {
      if (!await _auth.isDeviceSupported() ||
          !await _auth.canCheckBiometrics) {
        return false;
      }
      return (await _auth.getAvailableBiometrics()).isNotEmpty;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> authenticate(String reason) async {
    try {
      if (!await hasEnrolledBiometrics()) return false;
      return await _auth.authenticate(
        localizedReason: reason,
        options: const AuthenticationOptions(
          biometricOnly: true,
          stickyAuth: true,
          useErrorDialogs: false,
        ),
      );
    } catch (_) {
      return false;
    }
  }
}
