import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'server_discovery.dart' as discovery;

const String kServerPort = String.fromEnvironment(
  'RASD_PORT',
  defaultValue: '8000',
);
const String kFallbackBaseUrl = String.fromEnvironment(
  'RASD_FALLBACK_URL',
  defaultValue: 'http://192.168.10.138:8000',
);

const String kDefaultBaseUrl = kFallbackBaseUrl;

const String kBaseUrlDefine = String.fromEnvironment(
  'RASD_BASE_URL',
  defaultValue: '',
);

final sharedPrefsProvider = FutureProvider<SharedPreferences>((ref) async {
  return SharedPreferences.getInstance();
});

final baseUrlProvider = StateProvider<String>((ref) => kDefaultBaseUrl);

Future<String> resolveDefaultBaseUrl() async {
  if (kBaseUrlDefine.isNotEmpty) return kBaseUrlDefine.trim();
  try {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('base_url');
    if (saved != null && saved.trim().isNotEmpty) {
      return saved.trim();
    }
  } catch (_) {}
  final gatewayUrl = await discovery.detectRouterGatewayBaseUrl();
  return gatewayUrl ?? kFallbackBaseUrl;
}

Future<void> loadSavedBaseUrl(Ref ref) async {
  if (kBaseUrlDefine.isNotEmpty) {
    ref.read(baseUrlProvider.notifier).state = kBaseUrlDefine;
    return;
  }
  try {
    final resolved = await discovery.autoResolveBaseUrl(
      fallback: kFallbackBaseUrl,
    );
    ref.read(baseUrlProvider.notifier).state = resolved;
    await saveBaseUrl(resolved);
    return;
  } catch (_) {}
  try {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('base_url');
    if (saved != null && saved.trim().isNotEmpty) {
      ref.read(baseUrlProvider.notifier).state = saved.trim();
      return;
    }
  } catch (_) {}
  ref.read(baseUrlProvider.notifier).state = kFallbackBaseUrl;
}

Future<void> saveBaseUrl(String url) async {
  try {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('base_url', url.trim());
  } catch (_) {}
}

String normalizeBaseUrl(String input) {
  var u = input.trim();
  if (!u.contains('://')) u = 'http://$u';
  final parsed = Uri.tryParse(u);
  if (parsed != null && parsed.hasAuthority) {
    u = parsed.replace(scheme: parsed.scheme.toLowerCase()).toString();
  }
  while (u.endsWith('/')) {
    u = u.substring(0, u.length - 1);
  }
  return u;
}
