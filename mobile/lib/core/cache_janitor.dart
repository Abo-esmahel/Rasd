import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';

class CacheSweepResult {
  final int beforeBytes;

  final int afterBytes;

  final int deletedFiles;

  final bool swept;

  const CacheSweepResult({
    required this.beforeBytes,
    required this.afterBytes,
    required this.deletedFiles,
    required this.swept,
  });

  int get freedBytes => beforeBytes - afterBytes;
}

class CacheJanitor {
  static const int maxBytes = 500 * 1024 * 1024;

  static const int targetBytes = 400 * 1024 * 1024;

  static const Duration protectWindow = Duration(minutes: 10);

  static const Duration minInterval = Duration(minutes: 15);

  static DateTime? _lastSweep;
  static Future<CacheSweepResult>? _inFlight;

  static Future<CacheSweepResult> maybeSweep({bool force = false}) {
    if (!force) {
      final last = _lastSweep;
      if (last != null && DateTime.now().difference(last) < minInterval) {
        return _inFlight ??
            Future.value(const CacheSweepResult(
              beforeBytes: 0,
              afterBytes: 0,
              deletedFiles: 0,
              swept: false,
            ));
      }
    }
    return _inFlight ??= _run(force: force).whenComplete(() {
      _inFlight = null;
      _lastSweep = DateTime.now();
    });
  }

  static Future<int> currentBytes() async => _sizeOf(await _cacheRoots());

  static Future<int> _sizeOf(List<Directory> roots) async {
    var total = 0;
    for (final dir in roots) {
      total += await _dirSize(dir);
    }
    return total;
  }

  static Future<CacheSweepResult> _run({required bool force}) async {
    return sweepRoots(await _cacheRoots(), force: force);
  }

  @visibleForTesting
  static Future<CacheSweepResult> sweepRoots(
    List<Directory> roots, {
    required bool force,
    int max = maxBytes,
    int target = targetBytes,
    Duration protect = protectWindow,
  }) async {
    var before = 0;
    var deleted = 0;
    try {
      before = await _sizeOf(roots);

      if (!force && before <= max) {
        return CacheSweepResult(
          beforeBytes: before,
          afterBytes: before,
          deletedFiles: 0,
          swept: false,
        );
      }

      final files = <_CacheFile>[];
      for (final root in roots) {
        await _collect(root, files);
      }
      files.sort((a, b) => a.modified.compareTo(b.modified));

      final cutoff = DateTime.now().subtract(protect);
      var freed = 0;
      for (final f in files) {
        if (freed >= before - target) break;
        if (f.modified.isAfter(cutoff)) continue;
        try {
          final len = await f.file.length();
          await f.file.delete();
          freed += len;
          deleted++;
        } catch (_) {
        }
      }

      var after = before - freed;
      if (after < 0) after = 0;
      if (kDebugMode && deleted > 0) {
        debugPrint('[cache] freed ${freed ~/ (1024 * 1024)}MB '
            '($deleted files, ${(before / (1024 * 1024)).round()}MB → '
            '${(after / (1024 * 1024)).round()}MB)');
      }

      return CacheSweepResult(
        beforeBytes: before,
        afterBytes: after,
        deletedFiles: deleted,
        swept: deleted > 0,
      );
    } catch (e) {
      if (kDebugMode) debugPrint('[cache] sweep failed: $e');
      return CacheSweepResult(
        beforeBytes: before,
        afterBytes: before,
        deletedFiles: 0,
        swept: false,
      );
    }
  }

  static Future<List<Directory>> _cacheRoots() async {
    final roots = <Directory>[];
    Future<void> add(Directory? d) async {
      if (d == null) return;
      if (!roots.any((r) => r.path == d.path)) roots.add(d);
    }

    try {
      await add(await getTemporaryDirectory());
    } catch (_) {}
    try {
      await add(await getApplicationCacheDirectory());
    } catch (_) {}
    try {
      for (final d in await getExternalCacheDirectories() ?? const <Directory>[]) {
        await add(d);
      }
    } catch (_) {}
    return roots;
  }

  static Future<int> _dirSize(Directory dir) async {
    var total = 0;
    try {
      if (!dir.existsSync()) return 0;
      await for (final e in dir.list(recursive: true, followLinks: false)) {
        if (e is File) {
          try {
            total += await e.length();
          } catch (_) {}
        }
      }
    } catch (_) {}
    return total;
  }

  static Future<void> _collect(Directory dir, List<_CacheFile> out) async {
    try {
      if (!dir.existsSync()) return;
      await for (final e in dir.list(recursive: true, followLinks: false)) {
        if (e is! File) continue;
        try {
          out.add(_CacheFile(e, await e.lastModified()));
        } catch (_) {}
      }
    } catch (_) {}
  }
}

class _CacheFile {
  final File file;
  final DateTime modified;
  const _CacheFile(this.file, this.modified);
}
