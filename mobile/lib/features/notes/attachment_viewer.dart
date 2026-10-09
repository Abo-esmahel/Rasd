import 'dart:io';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:share_plus/share_plus.dart';
import 'package:video_player/video_player.dart';

import '../../core/config.dart';
import '../../core/models.dart';
import '../../core/storage.dart';
import '../../core/strings.dart';
import 'notes_repo.dart';

class AttachmentViewer extends ConsumerStatefulWidget {
  final Attachment att;
  final bool canDownload;
  final String? viewPath;
  final Future<File> Function()? downloadFile;
  const AttachmentViewer({
    super.key,
    required this.att,
    this.canDownload = false,
    this.viewPath,
    this.downloadFile,
  });

  @override
  ConsumerState<AttachmentViewer> createState() =>
      _AttachmentViewerState();
}

class _AttachmentViewerState
    extends ConsumerState<AttachmentViewer> {
  VideoPlayerController? _video;
  final _audio = AudioPlayer();
  bool _playingAudio = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    if (widget.att.isVideo) _initVideo();
  }

  Future<Map<String, String>> _headers() async {
    final t = await SecureStore.token;
    return {
      'Accept': '*/*',
      if (t != null && t.isNotEmpty) 'Authorization': 'Bearer $t',
    };
  }

  String _viewUrl() {
    if (widget.viewPath != null) {
      final base = ref.read(baseUrlProvider);
      final p = widget.viewPath!;
      return p.startsWith('http') ? p : '$base$p';
    }
    final base = ref.read(baseUrlProvider);
    return '$base/api/attachments/${widget.att.id}/view';
  }

  Future<void> _initVideo() async {
    final c = VideoPlayerController.networkUrl(
      Uri.parse(_viewUrl()),
      httpHeaders: await _headers(),
    );
    try {
      await c.initialize();
      if (mounted) setState(() => _video = c);
    } catch (_) {
      c.dispose();
    }
  }

  Future<void> _toggleAudio() async {
    if (_playingAudio) {
      await _audio.stop();
      if (mounted) setState(() => _playingAudio = false);
      return;
    }
    try {
      final f = await ref
          .read(notesRepoProvider)
          .downloadAttachment(widget.att);
      await _audio.play(DeviceFileSource(f.path));
      if (mounted) setState(() => _playingAudio = true);
    } catch (_) {}
  }

  Future<void> _downloadShare() async {
    setState(() => _busy = true);
    try {
      final f = widget.downloadFile != null
          ? await widget.downloadFile!()
          : await ref
              .read(notesRepoProvider)
              .downloadAttachment(widget.att);
      await SharePlus.instance
          .share(ShareParams(files: [XFile(f.path)]));
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void dispose() {
    _video?.dispose();
    _audio.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final att = widget.att;
    return Scaffold(
      appBar: AppBar(
        title: Text(att.name,
            maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [
          if (widget.canDownload)
            _busy
                ? const Padding(
                    padding: EdgeInsets.all(14),
                    child: SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                            strokeWidth: 2)),
                  )
                : IconButton(
                    onPressed: _downloadShare,
                    icon:
                        const Icon(Icons.download_outlined),
                  ),
        ],
      ),
      body: Center(
        child: att.isImage
            ? _ImageBody(
                att: att, viewPath: widget.viewPath)
            : att.isVideo
                ? _video == null
                    ? const CircularProgressIndicator()
                    : AspectRatio(
                        aspectRatio:
                            _video!.value.aspectRatio,
                        child: Stack(
                          alignment: Alignment.center,
                          children: [
                            VideoPlayer(_video!),
                            IconButton(
                              iconSize: 64,
                              color: Colors.white,
                              onPressed: () {
                                setState(() {
                                  _video!.value.isPlaying
                                      ? _video!.pause()
                                      : _video!.play();
                                });
                              },
                              icon: Icon(_video!.value
                                      .isPlaying
                                  ? Icons.pause_circle
                                  : Icons.play_circle),
                            ),
                          ],
                        ),
                      )
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.audiotrack_outlined,
                          size: 64),
                      const SizedBox(height: 12),
                      FilledButton.icon(
                        onPressed: _toggleAudio,
                        icon: Icon(_playingAudio
                            ? Icons.stop_outlined
                            : Icons.play_arrow_outlined),
                        label: Text(_playingAudio
                            ? tr(ref, 'stop')
                            : tr(ref, 'play')),
                      ),
                    ],
                  ),
      ),
    );
  }
}

class _ImageBody extends ConsumerWidget {
  final Attachment att;
  final String? viewPath;
  const _ImageBody({required this.att, this.viewPath});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final base = ref.watch(baseUrlProvider);
    final p = viewPath ??
        '/api/attachments/${att.id}/view';
    final url = p.startsWith('http') ? p : '$base$p';
    return FutureBuilder<Map<String, String>>(
      future: (() async {
        final t = await SecureStore.token;
        return {
          if (t != null && t.isNotEmpty)
            'Authorization': 'Bearer $t',
        };
      })(),
      builder: (c, snap) => InteractiveViewer(
        child: Image.network(
          url,
          headers: snap.data ?? const {},
          fit: BoxFit.contain,
          loadingBuilder: (c, w, p) =>
              p == null ? w : const CircularProgressIndicator(),
          errorBuilder: (_, __, ___) =>
              Text(tr(ref, 'no_results')),
        ),
      ),
    );
  }
}
