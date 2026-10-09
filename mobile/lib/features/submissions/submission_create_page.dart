import 'dart:async';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

import '../../core/models.dart';
import '../../core/strings.dart';
import '../home/user_profile_page.dart';
import '../../core/validators.dart';
import '../../core/wall_clock.dart';
import '../../core/widgets.dart';
import 'submissions_repo.dart';

class SubmissionCreatePage extends ConsumerStatefulWidget {
  const SubmissionCreatePage({super.key});

  @override
  ConsumerState<SubmissionCreatePage> createState() =>
      _SubmissionCreatePageState();
}

class _SubmissionCreatePageState extends ConsumerState<SubmissionCreatePage> {
  final _floorCtrl = TextEditingController();
  final _camCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  DateTime? _date;
  TimeOfDay? _start;
  TimeOfDay? _end;
  final _writers = <User>[];
  final _writerIds = <int>{};
  String _writerQuery = '';
  final List<File> _files = [];

  final Map<String, String> _fileProblems = {};
  bool _busy = false;
  String? _error;

  final _recorder = AudioRecorder();
  bool _recording = false;
  int _recSecs = 0;
  Timer? _recTimer;

  @override
  void dispose() {
    _recTimer?.cancel();
    _recorder.dispose();
    _floorCtrl.dispose();
    _camCtrl.dispose();
    _descCtrl.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _date = DateTime(now.year, now.month, now.day);
    _start = TimeOfDay(hour: now.hour, minute: now.minute);
    _loadWriters();
  }

  Future<void> _loadWriters() async {
    try {
      final ws = await ref.read(submissionsRepoProvider).writers();
      if (mounted) setState(() => _writers.addAll(ws));
    } catch (_) {}
  }

  static const _maxFiles = 5;

  Future<void> _pickFiles() async {
    final res = await FilePicker.platform.pickFiles(
      allowMultiple: true,
      type: FileType.custom,
      allowedExtensions: const [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'heic',
        'heif',
        'gif',
        'bmp',
        'mp4',
        'webm',
        'mov',
        'avi',
        '3gp',
        'mkv',
        'wmv',
        'flv',
        'ogv',
        'mpg',
        'mpeg',
        'mp3',
        'wav',
        'ogg',
        'oga',
        'm4a',
        'aac',
        'wma',
        'flac',
        'opus',
        'amr',
        '3ga',
        'weba',
        'aiff',
      ],
    );
    if (res == null) return;
    setState(() {
      for (final p in res.paths) {
        if (p == null) continue;
        final f = File(p);
        final problem = V.fileProblem(p, f.lengthSync());
        if (problem != null) {
          _fileProblems[p] = problem;
        } else if (_files.length < _maxFiles) {
          _files.add(f);
        }
      }
    });
  }

  Future<void> _takePhoto() async {
    final x = await ImagePicker().pickImage(source: ImageSource.camera);
    if (x != null && _files.length < _maxFiles) {
      setState(() => _files.add(File(x.path)));
    }
  }

  Future<void> _takeVideo() async {
    final x = await ImagePicker().pickVideo(source: ImageSource.camera);
    if (x != null && _files.length < _maxFiles) {
      setState(() => _files.add(File(x.path)));
    }
  }

  Future<void> _toggleRecord() async {
    if (_recording) {
      final path = await _recorder.stop();
      _recTimer?.cancel();
      setState(() => _recording = false);
      if (path != null && _files.length < _maxFiles) {
        setState(() => _files.add(File(path)));
      }
      return;
    }
    if (!await _recorder.hasPermission()) return;
    final dir = await getTemporaryDirectory();
    final path =
        '${dir.path}/sub-rec-${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _recorder.start(const RecordConfig(), path: path);
    setState(() {
      _recording = true;
      _recSecs = 0;
    });
    _recTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _recSecs++);
    });
  }

  Future<void> _submit() async {
    final floor = int.tryParse(_floorCtrl.text.trim());
    final cam = int.tryParse(_camCtrl.text.trim());
    if (floor == null ||
        cam == null ||
        _date == null ||
        _start == null ||
        _descCtrl.text.trim().length < 10 ||
        _writerIds.isEmpty) {
      setState(() => _error = tr(ref, 'complete_fields'));
      return;
    }
    if (Wall.isFuture(_date!, _start!)) {
      setState(() => _error = tr(ref, 'time_future'));
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    String? endAt;
    if (_end != null) {
      var d = _date!;
      if (_end!.hour * 60 + _end!.minute < _start!.hour * 60 + _start!.minute) {
        d = d.add(const Duration(days: 1));
      }
      endAt = Wall.combine(d, _end!);
    }
    try {
      await ref
          .read(submissionsRepoProvider)
          .create(
            floor: floor,
            camera: cam,
            observedAt: Wall.combine(_date!, _start!),
            observedEndAt: endAt,
            description: _descCtrl.text.trim(),
            writerIds: _writerIds.toList(),
            files: _files,
          );
      if (!mounted) return;
      ref.read(submissionsListProvider.notifier).refresh();
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = friendlyErrorText(e, ref.read(localeProvider));
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 680),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _floorCtrl,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(labelText: tr(ref, 'floor')),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: _camCtrl,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(labelText: tr(ref, 'camera')),
                    ),
                  ),
                ],
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.calendar_today_outlined),
                title: Text(
                  _date == null
                      ? tr(ref, 'date')
                      : '${_date!.year}-${_date!.month}-${_date!.day}',
                ),
                onTap: () async {
                  final p = await showDatePicker(
                    context: context,
                    initialDate: _date ?? DateTime.now(),
                    firstDate: DateTime(2020),
                    lastDate: DateTime.now(),
                  );
                  if (p != null) setState(() => _date = p);
                },
              ),
              Row(
                children: [
                  Expanded(
                    child: ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.schedule_outlined),
                      title: Text(
                        _start == null
                            ? tr(ref, 'start')
                            : '${_start!.hour.toString().padLeft(2, '0')}:${_start!.minute.toString().padLeft(2, '0')}',
                      ),
                      onTap: () async {
                        final p = await showTimePicker(
                          context: context,
                          initialTime: _start ?? TimeOfDay.now(),
                        );
                        if (p != null) {
                          setState(() => _start = p);
                        }
                      },
                    ),
                  ),
                  Expanded(
                    child: ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.schedule_outlined),
                      title: Text(
                        _end == null
                            ? tr(ref, 'end')
                            : '${_end!.hour.toString().padLeft(2, '0')}:${_end!.minute.toString().padLeft(2, '0')}',
                      ),
                      trailing: _end == null
                          ? null
                          : IconButton(
                              icon: const Icon(Icons.close, size: 18),
                              onPressed: () => setState(() => _end = null),
                            ),
                      onTap: () async {
                        final p = await showTimePicker(
                          context: context,
                          initialTime: _end ?? TimeOfDay.now(),
                        );
                        if (p != null) {
                          setState(() => _end = p);
                        }
                      },
                    ),
                  ),
                ],
              ),
              TextField(
                controller: _descCtrl,
                maxLines: 6,
                maxLength: 5000,
                decoration: InputDecoration(labelText: tr(ref, 'description')),
              ),
              const SizedBox(height: 12),
              Text(
                '${tr(ref, 'writers')} *',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              TextField(
                decoration: InputDecoration(
                  hintText: tr(ref, 'search'),
                  prefixIcon: const Icon(Icons.search_outlined),
                ),
                onChanged: (v) => setState(() => _writerQuery = v.trim()),
              ),
              ..._writers
                  .where(
                    (w) =>
                        _writerQuery.isEmpty ||
                        w.name.contains(_writerQuery) ||
                        w.username.contains(_writerQuery),
                  )
                  .map(
                    (w) => CheckboxListTile(
                      value: _writerIds.contains(w.id),
                      onChanged: (v) => setState(() {
                        if (v == true) {
                          _writerIds.add(w.id);
                        } else {
                          _writerIds.remove(w.id);
                        }
                      }),
                      title: Text(w.name),
                      subtitle: Text('@${w.username}'),
                      dense: true,
                      secondary: IconButton(
                        tooltip: tr(ref, 'view_profile'),
                        onPressed: () => openUserProfile(context, w.id),
                        icon: const Icon(Icons.person_outline, size: 20),
                      ),
                    ),
                  ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  OutlinedButton.icon(
                    onPressed: _busy ? null : _pickFiles,
                    icon: const Icon(Icons.attach_file),
                    label: Text(
                      '${tr(ref, 'pick_photos')} ($_files.length/$_maxFiles)',
                    ),
                  ),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : _takePhoto,
                    icon: const Icon(Icons.photo_camera_outlined),
                    label: Text(tr(ref, 'take_photo')),
                  ),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : _takeVideo,
                    icon: const Icon(Icons.videocam_outlined),
                    label: Text(tr(ref, 'take_video')),
                  ),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : _toggleRecord,
                    icon: Icon(
                      _recording
                          ? Icons.stop_circle_outlined
                          : Icons.mic_outlined,
                    ),
                    label: Text(
                      _recording
                          ? '${(_recSecs ~/ 60).toString().padLeft(2, '0')}:${(_recSecs % 60).toString().padLeft(2, '0')}'
                          : tr(ref, 'record_audio'),
                    ),
                    style: _recording
                        ? OutlinedButton.styleFrom(
                            foregroundColor: Theme.of(
                              context,
                            ).colorScheme.error,
                          )
                        : null,
                  ),
                ],
              ),
              if (_files.isNotEmpty) ...[
                const SizedBox(height: 8),
                ..._files.map(
                  (f) => ListTile(
                    dense: true,
                    leading: const Icon(
                      Icons.check_circle_outline,
                      color: Colors.green,
                    ),
                    title: Text(
                      f.path.split(RegExp(r'[/\\]')).last,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    subtitle: Text(
                      '${(f.lengthSync() / 1024 / 1024).toStringAsFixed(1)} MB',
                    ),
                    trailing: IconButton(
                      icon: const Icon(Icons.close),
                      onPressed: _busy
                          ? null
                          : () => setState(() {
                              _files.remove(f);
                              _fileProblems.removeWhere(
                                (k, _) => k.endsWith(
                                  f.path.split(RegExp(r'[/\\]')).last,
                                ),
                              );
                            }),
                    ),
                  ),
                ),
              ],
              if (_fileProblems.isNotEmpty) ...[
                const SizedBox(height: 4),
                ..._fileProblems.entries.map(
                  (en) => Text(
                    '${en.key.split(RegExp(r'[/\\]')).last}: ${en.value}',
                    style: TextStyle(
                      fontSize: 12,
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
              ],
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _busy ? null : _submit,
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                ),
                child: _busy
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.5,
                          color: Colors.white,
                        ),
                      )
                    : Text(tr(ref, 'send')),
              ),
              const SizedBox(height: 40),
            ],
          ),
        ),
      ),
    );
  }
}
