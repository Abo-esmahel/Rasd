import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';
import 'package:video_player/video_player.dart';

import '../../core/api_client.dart';
import '../../core/config.dart';
import '../../core/models.dart';
import '../../core/share_center.dart';
import '../../core/storage.dart';
import '../../core/strings.dart';
import '../auth/auth_provider.dart';
import '../home/user_profile_page.dart';
import '../notes/note_detail_page.dart';
import '../submissions/submission_detail_page.dart';

class GalleryViewer extends ConsumerStatefulWidget {
  final GalleryItem item;
  const GalleryViewer({super.key, required this.item});

  @override
  ConsumerState<GalleryViewer> createState() => _GalleryViewerState();
}

class _GalleryViewerState extends ConsumerState<GalleryViewer> {
  VideoPlayerController? _video;
  final _audio = AudioPlayer();
  bool _playingAudio = false;

  @override
  void initState() {
    super.initState();
    if (widget.item.isVideo) _initVideo();
  }

  Future<Map<String, String>> _headers() async {
    final t = await SecureStore.token;
    return {if (t != null && t.isNotEmpty) 'Authorization': 'Bearer $t'};
  }

  String _url() {
    final base = ref.read(baseUrlProvider);
    final p = widget.item.viewPath();
    return p.startsWith('http') ? p : '$base$p';
  }

  Future<void> _initVideo() async {
    final c = VideoPlayerController.networkUrl(
      Uri.parse(_url()),
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
      final dir = await getTemporaryDirectory();
      final path = '${dir.path}/gal-${widget.item.id}.tmp';
      var dl = widget.item.downloadPath();
      if (!dl.startsWith('http') && !dl.startsWith('/')) {
        dl = '/$dl';
      }
      await ref.read(dioProvider).download(dl, path);
      await _audio.play(DeviceFileSource(path));
      if (mounted) setState(() => _playingAudio = true);
    } catch (_) {}
  }

  Future<void> _share() async {
    await ShareCenter.sheet(
      context,
      ref,
      ShareCenter.gallery(ref, widget.item, true),
    );
  }

  void _openParent() {
    if (widget.item.kind == 'submission') {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => SubmissionDetailPage(id: widget.item.parentId),
        ),
      );
    } else {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => NoteDetailPage(id: widget.item.parentId),
        ),
      );
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
    final item = widget.item;
    final me = ref.watch(authProvider).valueOrNull;
    final canShare =
        (me?.isWriter ?? false) ||
        (me?.isGM ?? false) ||
        (item.kind == 'submission' &&
            item.ownerId != null &&
            item.ownerId == me?.id);
    return Scaffold(
      appBar: AppBar(
        title: Text(item.name, maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [
          if (canShare)
            IconButton(
              tooltip: tr(ref, 'share_title'),
              onPressed: _share,
              icon: const Icon(Icons.share_outlined),
            ),
        ],
      ),
      body: ListView(
        children: [
          if (item.isImage)
            FutureBuilder<Map<String, String>>(
              future: _headers(),
              builder: (c, snap) => InteractiveViewer(
                child: Image.network(
                  _url(),
                  headers: snap.data ?? const {},
                  fit: BoxFit.contain,
                  loadingBuilder: (c, w, p) => p == null
                      ? w
                      : const Padding(
                          padding: EdgeInsets.all(48),
                          child: CircularProgressIndicator(),
                        ),
                  errorBuilder: (_, __, ___) => Padding(
                    padding: const EdgeInsets.all(48),
                    child: Text(
                      tr(ref, 'no_results'),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
              ),
            )
          else if (item.isVideo)
            _video == null
                ? const Padding(
                    padding: EdgeInsets.all(48),
                    child: Center(child: CircularProgressIndicator()),
                  )
                : AspectRatio(
                    aspectRatio: _video!.value.aspectRatio,
                    child: Stack(
                      alignment: Alignment.center,
                      children: [
                        VideoPlayer(_video!),
                        IconButton(
                          iconSize: 64,
                          color: Colors.white,
                          onPressed: () => setState(() {
                            _video!.value.isPlaying
                                ? _video!.pause()
                                : _video!.play();
                          }),
                          icon: Icon(
                            _video!.value.isPlaying
                                ? Icons.pause_circle
                                : Icons.play_circle,
                          ),
                        ),
                      ],
                    ),
                  )
          else
            Padding(
              padding: const EdgeInsets.all(32),
              child: Column(
                children: [
                  const Icon(Icons.audiotrack_outlined, size: 64),
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: _toggleAudio,
                    icon: Icon(
                      _playingAudio
                          ? Icons.stop_outlined
                          : Icons.play_arrow_outlined,
                    ),
                    label: Text(
                      _playingAudio ? tr(ref, 'stop') : tr(ref, 'play'),
                    ),
                  ),
                ],
              ),
            ),
          ListTile(
            leading: const Icon(Icons.person_outline),
            title: Text(item.ownerName.isEmpty ? '—' : item.ownerName),
            subtitle: Text(
              '${tr(ref, 'camera')} ${item.camera ?? '—'} · ${tr(ref, 'floor')} ${item.floor ?? '—'}',
              textDirection: TextDirection.ltr,
            ),
            trailing: TextButton(
              onPressed: _openParent,
              child: Text(tr(ref, 'open_parent')),
            ),
            onTap: item.ownerId == null
                ? null
                : () => openUserProfile(context, item.ownerId!),
          ),
        ],
      ),
    );
  }
}
