import 'package:dio/dio.dart';
import 'package:network_info_plus/network_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';


const String kDiscoveryPort = String.fromEnvironment(
  'RASD_PORT',
  defaultValue: '8000',
);
const Duration kDiscoveryTimeout = Duration(milliseconds: 800);
const Duration kVerifyTimeout = Duration(seconds: 2);
const int kDiscoveryBatch = 40;

const List<String> kCommonPrefixes = [
  '192.168.10',
  '192.168.1',
  '192.168.0',
  '192.168.100',
  '192.168.43',
  '192.168.137',
  '10.0.0',
  '172.16.0',
];

class FoundServer {
  final String baseUrl;
  final int latencyMs;
  const FoundServer(this.baseUrl, this.latencyMs);
}

Dio _discoveryDio(Duration timeout) => Dio(
  BaseOptions(
    connectTimeout: timeout,
    receiveTimeout: timeout,
    sendTimeout: timeout,
    followRedirects: false,
    validateStatus: (_) => true,
  ),
);

final _ipv4 = RegExp(r'^\d{1,3}(\.\d{1,3}){3}$');

bool _isUsableIp(String? ip) {
  if (ip == null || ip.isEmpty) return false;
  if (!_ipv4.hasMatch(ip)) return false;
  if (ip == '0.0.0.0' || ip == '127.0.0.1') return false;
  return true;
}

String? _prefixOf(String? ipv4) {
  if (!_isUsableIp(ipv4)) return null;
  final p = ipv4!.split('.');
  if (p.length != 4 || p.any((e) => int.tryParse(e) == null)) return null;
  return '${p[0]}.${p[1]}.${p[2]}';
}

Future<bool> isRasdServer(String baseUrl, {Duration? timeout}) async {
  return (await probeServer(
    _discoveryDio(timeout ?? kVerifyTimeout),
    baseUrl,
  )) !=
      null;
}

Future<FoundServer?> probeServer(Dio dio, String baseUrl) async {
  final sw = Stopwatch()..start();
  try {
    final r = await dio.get('$baseUrl/api/ping');
    sw.stop();
    final d = r.data;
    if (r.statusCode == 200 && d is Map && d['app'] == 'rasd') {
      return FoundServer(baseUrl, sw.elapsedMilliseconds);
    }
  } catch (_) {}
  return null;
}

Future<String?> detectRouterGatewayBaseUrl({String port = kDiscoveryPort}) async {
  try {
    final gateway = await NetworkInfo().getWifiGatewayIP();
    if (_isUsableIp(gateway)) return 'http://$gateway:$port';
  } catch (_) {}
  return null;
}

List<int> _smartOctetOrder() {
  final order = <int>[];
  for (var i = 80; i <= 160; i++) {
    order.add(i);
  }
  for (var i = 2; i < 80; i++) {
    order.add(i);
  }
  for (var i = 161; i <= 254; i++) {
    order.add(i);
  }
  return order;
}

Future<List<FoundServer>> discoverRasdServers({
  String port = kDiscoveryPort,
  Duration timeout = kDiscoveryTimeout,
  void Function(int done, int total)? onProgress,
}) async {
  final dio = _discoveryDio(timeout);
  final found = <FoundServer>[];

  String? wifiIp;
  String? gateway;
  try {
    wifiIp = await NetworkInfo().getWifiIP();
  } catch (_) {}
  try {
    gateway = await NetworkInfo().getWifiGatewayIP();
  } catch (_) {}
  if (!_isUsableIp(wifiIp)) wifiIp = null;
  if (!_isUsableIp(gateway)) gateway = null;

  if (gateway != null) {
    final f = await probeServer(dio, 'http://$gateway:$port');
    if (f != null) return [f];
  }

  final prefixes = <String>[];
  final ownPrefix = _prefixOf(wifiIp) ?? _prefixOf(gateway);
  if (ownPrefix != null) {
    prefixes.add(ownPrefix);
  } else {
    prefixes.addAll(kCommonPrefixes);
  }

  for (final prefix in prefixes) {
    final targets = <String>[];
    for (final i in _smartOctetOrder()) {
      final ip = '$prefix.$i';
      if (ip == wifiIp || ip == gateway) continue;
      targets.add('http://$ip:$port');
    }
    var done = 0;
    for (var s = 0; s < targets.length; s += kDiscoveryBatch) {
      final end = (s + kDiscoveryBatch).clamp(0, targets.length);
      final batch = targets.sublist(s, end);
      final res = await Future.wait(batch.map((u) => probeServer(dio, u)));
      for (final f in res) {
        if (f != null) found.add(f);
      }
      done += batch.length;
      onProgress?.call(done, targets.length);
      if (found.isNotEmpty) break;
    }
    if (found.isNotEmpty) break;
  }

  found.sort((a, b) => a.latencyMs.compareTo(b.latencyMs));
  return found;
}

Future<String> autoResolveBaseUrl({required String fallback}) async {
  final dio = _discoveryDio(kVerifyTimeout);
  try {
    final saved = await _savedBaseUrl();
    if (saved != null && saved.isNotEmpty) {
      if (await probeServer(dio, saved) != null) return saved;
    }
  } catch (_) {}
  try {
    final gw = await detectRouterGatewayBaseUrl();
    if (gw != null && await probeServer(dio, gw) != null) {
      return gw;
    }
  } catch (_) {}
  try {
    final found = await discoverRasdServers();
    if (found.isNotEmpty) return found.first.baseUrl;
  } catch (_) {}
  return fallback;
}

Future<String?> _savedBaseUrl() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('base_url');
    if (saved != null && saved.trim().isNotEmpty) return saved.trim();
  } catch (_) {}
  return null;
}
