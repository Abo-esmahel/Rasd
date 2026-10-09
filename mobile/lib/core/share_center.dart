import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import 'api_client.dart';
import 'config.dart';
import 'models.dart';
import 'server_pdf.dart';
import 'share_bridge.dart';
import 'strings.dart';
import 'widgets.dart';
import 'wall_clock.dart';
import '../features/notes/notes_repo.dart';
import '../features/submissions/submissions_repo.dart';


class ShareMedia {
  final String name;
  final Future<XFile> Function() download;
  ShareMedia({required this.name, required this.download});
}

class SharedContent {
  final String kindKey;
  final String kindArg;
  final List<String> lines;
  final String? description;
  final String serverPath;
  final List<ShareMedia> media;
  SharedContent({
    required this.kindKey,
    this.kindArg = '',
    this.lines = const [],
    this.description,
    required this.serverPath,
    this.media = const [],
  });
}

class ShareCenter {
  static Future<void> sheet(
    BuildContext context,
    WidgetRef ref,
    SharedContent content,
  ) async {
    final lang = ref.read(localeProvider);
    await showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 44,
                height: 5,
                decoration: BoxDecoration(
                  color: Colors.grey.withValues(alpha: .35),
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                trOf(lang, 'share_title'),
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              if (content.media.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    trOf(
                      lang,
                      'share_media',
                      args: {'count': '${content.media.length}'},
                    ),
                    style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                  ),
                ),
              const SizedBox(height: 8),
              ListTile(
                leading: Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: const Color(0xFF25D366).withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(
                    Icons.chat_bubble_outline,
                    color: Color(0xFF25D366),
                  ),
                ),
                title: Text(trOf(lang, 'whatsapp')),
                subtitle: Text(
                  trOf(lang, 'share_text_only'),
                  style: const TextStyle(fontSize: 12),
                ),
                onTap: () {
                  Navigator.pop(ctx);
                  shareViaApp(ref, content, ShareBridge.whatsapp);
                },
              ),
              ListTile(
                leading: Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: const Color(0xFF229ED9).withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(
                    Icons.send_outlined,
                    color: Color(0xFF229ED9),
                  ),
                ),
                title: Text(trOf(lang, 'telegram')),
                subtitle: Text(
                  trOf(lang, 'share_text_only'),
                  style: const TextStyle(fontSize: 12),
                ),
                onTap: () {
                  Navigator.pop(ctx);
                  shareViaApp(ref, content, ShareBridge.telegram);
                },
              ),
              ListTile(
                leading: Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: const Color(0xFF0E6A38).withValues(alpha: .1),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(
                    Icons.share_outlined,
                    color: Color(0xFF0E6A38),
                  ),
                ),
                title: Text(trOf(lang, 'system_share')),
                subtitle: Text(
                  trOf(lang, 'share_with_media'),
                  style: const TextStyle(fontSize: 12),
                ),
                onTap: () {
                  Navigator.pop(ctx);
                  shareSystem(context, ref, content);
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String buildText(WidgetRef ref, SharedContent c) {
    final base = ref.read(baseUrlProvider);
    final b = StringBuffer();
    final kind = tr(ref, c.kindKey);
    b.writeln('${tr(ref, 'app_name')} — $kind${c.kindArg}');
    for (final l in c.lines) {
      b.writeln(l);
    }
    final desc = (c.description ?? '').trim();
    if (desc.isNotEmpty) {
      b.writeln();
      b.writeln(desc.length > 600 ? '${desc.substring(0, 600)}…' : desc);
    }
    if (c.media.isNotEmpty) {
      b.writeln();
      b.writeln(tr(ref, 'share_media', args: {'count': '${c.media.length}'}));
    }
    b.writeln();
    b.writeln('$base${c.serverPath}');
    return b.toString();
  }

  static Future<void> shareViaApp(
    WidgetRef ref,
    SharedContent content,
    String package,
  ) async {
    final text = buildText(ref, content);
    final ok = await ShareBridge.shareText(
      text,
      package: await ShareBridge.isInstalled(package) ? package : null,
    );
    if (!ok) {
      final base = ref.read(baseUrlProvider);
      final link = '$base${content.serverPath}';
      final url = package == ShareBridge.telegram
          ? 'https://t.me/share/url?url=${Uri.encodeComponent(link)}&text=${Uri.encodeComponent(text)}'
          : 'https://wa.me/?text=${Uri.encodeComponent(text)}';
      await ShareBridge.openUrl(url);
    }
  }

  static SharedContent note(WidgetRef ref, Note n, bool withMedia) {
    final p = Wall.parts(n.observedAtRaw);
    final time = p.date.isEmpty ? '' : '${p.date} · ${Wall.time12(p.time)}';
    return SharedContent(
      kindKey: 'share_kind_note',
      kindArg: ' #${n.id}',
      lines: [
        '${tr(ref, 'camera')} ${n.cameraNumber} · ${tr(ref, 'floor')} ${n.floorNumber}',
        if (time.isNotEmpty) time,
        if ((n.owner?.name ?? '').isNotEmpty)
          '${tr(ref, 'name')}: ${n.owner!.name}',
        tr(ref, n.status),
      ],
      description: n.description,
      serverPath: '/notes/${n.id}',
      media: withMedia
          ? [
              for (final a in n.attachments)
                ShareMedia(
                  name: a.name,
                  download: () async {
                    final f = await ref
                        .read(notesRepoProvider)
                        .downloadAttachment(a);
                    return XFile(f.path);
                  },
                ),
            ]
          : const [],
    );
  }

  static SharedContent submission(WidgetRef ref, Submission s, bool withMedia) {
    return SharedContent(
      kindKey: 'share_kind_submission',
      kindArg: ' #${s.id}',
      lines: [
        '${tr(ref, 'camera')} ${s.cameraNumber} · ${tr(ref, 'floor')} ${s.floorNumber}',
        if ((s.owner?.name ?? '').isNotEmpty)
          '${tr(ref, 'name')}: ${s.owner!.name}',
        if (s.writers.isNotEmpty)
          '${tr(ref, 'writers')}: ${s.writers.map((w) => w.name).join('، ')}',
        tr(ref, s.status),
      ],
      description: s.description,
      serverPath: '/general-submissions/${s.id}',
      media: withMedia
          ? [
              for (final a in s.attachments)
                ShareMedia(
                  name: a.name,
                  download: () async {
                    final f = await ref
                        .read(submissionsRepoProvider)
                        .downloadAttachment(a.id, a.name);
                    return XFile(f.path);
                  },
                ),
            ]
          : const [],
    );
  }

  static SharedContent report(WidgetRef ref, Report r) {
    return SharedContent(
      kindKey: 'share_kind_report',
      kindArg: ' #${r.id}',
      lines: [
        '${tr(ref, 'date')}: ${r.reportDate}',
        if ((r.author?.name ?? '').isNotEmpty)
          '${tr(ref, 'name')}: ${r.author!.name}',
        tr(ref, r.isPublished ? 'published' : 'draft'),
        '${tr(ref, 'notes')} (${r.notes.length})',
      ],
      description: (r.summary ?? '').isEmpty ? r.content : r.summary,
      serverPath: '/reports/${r.id}',
    );
  }

  static SharedContent gallery(
    WidgetRef ref,
    GalleryItem item,
    bool withMedia,
  ) {
    return SharedContent(
      kindKey: 'share_kind_media',
      kindArg: ' #${item.id}',
      lines: [
        if (item.ownerName.isNotEmpty) '${tr(ref, 'name')}: ${item.ownerName}',
        if (item.camera != null || item.floor != null)
          '${tr(ref, 'camera')} ${item.camera ?? '—'} · ${tr(ref, 'floor')} ${item.floor ?? '—'}',
      ],
      serverPath: item.kind == 'submission'
          ? '/general-submissions/${item.parentId}'
          : '/notes/${item.parentId}',
      media: withMedia
          ? [
              ShareMedia(
                name: item.name,
                download: () async {
                  final dio = ref.read(dioProvider);
                  final dir = await _tmpDir();
                  final safe = item.name.isEmpty
                      ? 'gal-${item.id}'
                      : item.name.replaceAll(RegExp(r'[\\/:*?"<>|]'), '_');
                  final path = '$dir/gal-${item.id}_$safe';
                  var dl = item.downloadPath();
                  if (!dl.startsWith('http') && !dl.startsWith('/')) {
                    dl = '/$dl';
                  }
                  await dio.download(dl, path);
                  return XFile(path);
                },
              ),
            ]
          : const [],
    );
  }

  static Future<String> _tmpDir() async => (await getTemporaryDirectory()).path;

  static Future<void> shareSystem(
    BuildContext context,
    WidgetRef ref,
    SharedContent content,
  ) async {
    final lang = ref.read(localeProvider);
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(trOf(lang, 'preparing_media')),
          duration: const Duration(seconds: 2),
        ),
      );
    }
    final text = buildText(ref, content);
    final target = _serverPdfTarget(content.serverPath);
    if (target != null) {
      try {
        final bytes = await ServerPdf.fetch(ref, target.$1, target.$2);
        final dir = await getTemporaryDirectory();
        final name = '${target.$1.name}-${target.$2}.pdf';
        final path = '${dir.path}/$name';
        await XFile.fromData(bytes,
                name: name, mimeType: 'application/pdf')
            .saveTo(path);
        await SharePlus.instance.share(
          ShareParams(text: text, files: [XFile(path)]),
        );
        return;
      } catch (e) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                friendlyErrorText(e, ref.read(localeProvider)),
              ),
            ),
          );
        }
      }
    }
    final files = <XFile>[];
    var failedFiles = 0;
    for (final m in content.media) {
      try {
        files.add(await m.download());
      } catch (_) {
        failedFiles++;
      }
    }
    if (failedFiles > 0 && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            trOf(lang, 'share_failed_media', args: {'count': '$failedFiles'}),
          ),
        ),
      );
    }
    await SharePlus.instance.share(
      ShareParams(text: text, files: files.isEmpty ? null : files),
    );
  }

  static (ServerPdfKind, int)? _serverPdfTarget(String serverPath) {
    final m = RegExp(r'^/(notes|general-submissions|reports)/(\d+)')
        .firstMatch(serverPath.trim());
    if (m == null) return null;
    final id = int.tryParse(m.group(2) ?? '');
    if (id == null || id <= 0) return null;
    return (
      switch (m.group(1)) {
        'notes' => ServerPdfKind.note,
        'general-submissions' => ServerPdfKind.submission,
        _ => ServerPdfKind.report,
      },
      id,
    );
  }
}
