import 'dart:async';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

import '../../core/api/api_exception.dart';
import '../../core/strings.dart';
import '../../core/validators.dart';
import '../../core/wall_clock.dart';
import '../../core/widgets.dart';
import '../auth/auth_provider.dart';
import 'notes_providers.dart';
import 'notes_repo.dart';

class NoteCreatePage extends ConsumerStatefulWidget {
  const NoteCreatePage({super.key});

  @override
  ConsumerState<NoteCreatePage> createState() => _NoteCreatePageState();
}

class _NoteCreatePageState extends ConsumerState<NoteCreatePage> {
  final _form = GlobalKey<FormState>();
  final _floorCtrl = TextEditingController();
  final _camCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  DateTime? _date;
  TimeOfDay? _start;
  TimeOfDay? _end;
  final List<File> _files = [];

  final Map<String, String> _fileProblems = {};
  final List<File> _failedUploads = [];
  int? _createdNoteId;

  bool _busy = false;
  double _progress = 0;
  String? _error;
  final Map<String, String> _serverErrors = {};

  final _recorder = AudioRecorder();
  bool _recording = false;
  int _recSecs = 0;
  Timer? _recTimer;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _date = DateTime(now.year, now.month, now.day);
    _start = TimeOfDay(hour: now.hour, minute: now.minute);
  }

  @override
  void dispose() {
    _floorCtrl.dispose();
    _camCtrl.dispose();
    _descCtrl.dispose();
    _recTimer?.cancel();
    _recorder.dispose();
    super.dispose();
  }

  String _fmtDate(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
  String _fmtTime(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

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
        final bytes = f.lengthSync();
        final problem = V.fileProblem(p, bytes);
        if (problem != null) {
          _fileProblems[p] = problem;
        } else if (_files.length < V.maxFiles) {
          _files.add(f);
        }
      }
    });
  }

  Future<void> _takePhoto() async {
    final x = await ImagePicker().pickImage(source: ImageSource.camera);
    if (x != null && _files.length < V.maxFiles) {
      setState(() => _files.add(File(x.path)));
    }
  }

  Future<void> _takeVideo() async {
    final x = await ImagePicker().pickVideo(source: ImageSource.camera);
    if (x != null && _files.length < V.maxFiles) {
      setState(() => _files.add(File(x.path)));
    }
  }

  Future<void> _toggleRecord() async {
    if (_recording) {
      final path = await _recorder.stop();
      _recTimer?.cancel();
      setState(() => _recording = false);
      if (path != null && _files.length < V.maxFiles) {
        setState(() => _files.add(File(path)));
      }
      return;
    }
    if (!await _recorder.hasPermission()) return;
    final dir = await getTemporaryDirectory();
    final path = '${dir.path}/rec-${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _recorder.start(const RecordConfig(), path: path);
    setState(() {
      _recording = true;
      _recSecs = 0;
    });
    _recTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _recSecs++);
    });
  }

  bool _localGuards() {
    if (!_form.currentState!.validate()) return false;
    if (_date == null || _start == null) {
      setState(() => _error = tr(ref, 'datetime_required'));
      return false;
    }
    if (Wall.isFuture(_date!, _start!)) {
      setState(() => _error = tr(ref, 'time_future'));
      return false;
    }
    if (_end != null &&
        Wall.spanMinutes(_start!, _end!) > Wall.maxSpanMinutes) {
      setState(() => _error = tr(ref, 'time_span_12h'));
      return false;
    }
    return true;
  }

  String get _observedAt => Wall.combine(_date!, _start!);

  String? get _observedEndAt {
    if (_end == null) return null;
    var d = _date!;
    if (_end!.hour * 60 + _end!.minute < _start!.hour * 60 + _start!.minute) {
      d = d.add(const Duration(days: 1));
    }
    return Wall.combine(d, _end!);
  }

  Future<void> _submit({required bool send}) async {
    setState(() {
      _error = null;
      _serverErrors.clear();
    });
    if (!_localGuards()) return;
    setState(() {
      _busy = true;
      _progress = 0;
      _failedUploads.clear();
    });
    try {
      final repo = ref.read(notesRepoProvider);
      var note = await repo.create(
        floor: int.parse(_floorCtrl.text.trim()),
        camera: int.parse(_camCtrl.text.trim()),
        observedAt: _observedAt,
        observedEndAt: _observedEndAt,
        description: _descCtrl.text.trim(),
      );
      _createdNoteId = note.id;
      var firstError = '';
      for (var i = 0; i < _files.length; i++) {
        try {
          await repo.uploadAttachment(
            note.id,
            _files[i],
            onProgress: (sent, total) {
              if (total > 0 && mounted) {
                setState(() {
                  _progress = (i + sent / total) / _files.length;
                });
              }
            },
          );
        } catch (e) {
          _failedUploads.add(_files[i]);
          if (firstError.isEmpty) firstError = e.toString();
          if (mounted) setState(() {});
        }
      }
      if (_failedUploads.isNotEmpty) {
        if (mounted) {
          setState(() => _busy = false);
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                firstError.isEmpty
                    ? tr(ref, 'upload_partial')
                    : '${tr(ref, 'upload_partial')} — $firstError',
              ),
            ),
          );
        }
        return;
      }
      if (send) {
        note = await repo.send(note.id);
      }
      if (!mounted) return;
      ref.read(notesListProvider.notifier).refresh();
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        for (final en in e.fieldErrors.entries) {
          if (en.value.isNotEmpty) {
            _serverErrors[_fieldName(en.key)] = en.value.first;
          }
        }
        if (_serverErrors.isEmpty) _error = e.toString();
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = e.toString();
        });
      }
    }
  }

  Future<void> _retryFailed() async {
    final id = _createdNoteId;
    if (id == null || _failedUploads.isEmpty) return;
    setState(() => _busy = true);
    final repo = ref.read(notesRepoProvider);
    final still = <File>[];
    for (final f in _failedUploads) {
      try {
        await repo.uploadAttachment(id, f);
      } catch (_) {
        still.add(f);
      }
    }
    if (!mounted) return;
    setState(() {
      _busy = false;
      _failedUploads
        ..clear()
        ..addAll(still);
    });
    if (still.isEmpty) {
      ref.read(notesListProvider.notifier).refresh();
      Navigator.pop(context, true);
    }
  }

  String _fieldName(String key) {
    return switch (key) {
      'floor_number' => tr(ref, 'floor'),
      'camera_number' => tr(ref, 'camera'),
      'description' => tr(ref, 'description'),
      'observed_at' => tr(ref, 'date'),
      'observed_end_at' => tr(ref, 'end'),
      _ => key,
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(tr(ref, 'new_note'))),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 680),
          child: Form(
            key: _form,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                RasdHero(
                  icon: Icons.note_alt_outlined,
                  title: tr(ref, 'new_note'),
                  subtitle: tr(ref, 'notes_subtitle'),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _floorCtrl,
                        keyboardType: TextInputType.number,
                        validator: V.floor,
                        decoration: InputDecoration(
                          labelText: tr(ref, 'floor'),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextFormField(
                        controller: _camCtrl,
                        keyboardType: TextInputType.number,
                        validator: V.camera,
                        decoration: InputDecoration(
                          labelText: tr(ref, 'camera'),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.calendar_today_outlined),
                  title: Text(
                    _date == null ? tr(ref, 'date') : _fmtDate(_date!),
                  ),
                  trailing: Text(
                    _start == null ? '' : _fmtTime(_start!),
                    style: const TextStyle(fontWeight: FontWeight.bold),
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
                          _start == null ? tr(ref, 'start') : _fmtTime(_start!),
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
                          _end == null ? tr(ref, 'end') : _fmtTime(_end!),
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
                TextFormField(
                  spellCheckConfiguration:
                      const SpellCheckConfiguration.disabled(),
                  controller: _descCtrl,
                  maxLines: 6,
                  maxLength: 5000,
                  validator: V.description,
                  decoration: InputDecoration(
                    labelText: tr(ref, 'description'),
                    alignLabelWithHint: true,
                  ),
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    OutlinedButton.icon(
                      onPressed: _busy ? null : _pickFiles,
                      icon: const Icon(Icons.attach_file),
                      label: Text(
                        '${tr(ref, 'pick_photos')} (${_files.length}/${V.maxFiles})',
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
                  const SizedBox(height: 10),
                  _PendingFiles(
                    files: _files,
                    failed: _failedUploads,
                    onRemove: (f) => setState(() {
                      _files.remove(f);
                      _failedUploads.remove(f);
                      _fileProblems.removeWhere(
                        (k, _) => k.endsWith(
                          f.path.split(RegExp(r'[/\\]')).last,
                        ),
                      );
                    }),
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
                if (_failedUploads.isNotEmpty && !_busy) ...[
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: _retryFailed,
                    icon: const Icon(Icons.refresh_outlined),
                    label: Text(tr(ref, 'retry_failed')),
                  ),
                ],
                if (_busy) ...[
                  const SizedBox(height: 12),
                  LinearProgressIndicator(
                    value: _progress > 0 ? _progress : null,
                  ),
                ],
                if (_serverErrors.isNotEmpty)
                  _ServerErrorsBox(errors: _serverErrors),
                if (_error != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ],
                const SizedBox(height: 16),
              
                if ((ref.watch(authProvider).valueOrNull?.isWriter ?? false))
                  FilledButton(
                    onPressed: _busy ? null : () => _submit(send: true),
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
                        : Text(tr(ref, 'upload_note')),
                  )
                else ...[
                  FilledButton(
                    onPressed: _busy ? null : () => _submit(send: true),
                    style: FilledButton.styleFrom(
                      minimumSize: const Size.fromHeight(52),
                    ),
                    child: Text(tr(ref, 'save_send')),
                  ),
                  const SizedBox(height: 10),
                  OutlinedButton(
                    onPressed: _busy ? null : () => _submit(send: false),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(52),
                    ),
                    child: Text(tr(ref, 'save_draft')),
                  ),
                ],
                const SizedBox(height: 40),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PendingFiles extends ConsumerWidget {
  final List<File> files;
  final List<File> failed;
  final void Function(File f) onRemove;

  const _PendingFiles({
    required this.files,
    required this.failed,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final counts = <String, int>{};
    for (final f in files) {
      final k = V.kindOf(f.path);
      counts[k] = (counts[k] ?? 0) + 1;
    }
    final used = <String, int>{};

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          tr(ref, 'pending_attachments', args: {'count': '${files.length}'}),
          style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 8),
        RasdCard(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Column(
            children: [
              for (var i = 0; i < files.length; i++) ...[
                if (i > 0) const Divider(height: 1, indent: 52),
                _row(ref, cs, files[i], i, used, counts),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _row(
    WidgetRef ref,
    ColorScheme cs,
    File f,
    int index,
    Map<String, int> used,
    Map<String, int> counts,
  ) {
    final kind = V.kindOf(f.path);
    final n = (used[kind] = (used[kind] ?? 0) + 1);
    final isFailed = failed.contains(f);
    final icon = switch (kind) {
      'image' => Icons.image_outlined,
      'video' => Icons.videocam_outlined,
      'audio' => Icons.graphic_eq_outlined,
      _ => Icons.insert_drive_file_outlined,
    };
    final typeLabel = tr(
      ref,
      switch (kind) {
        'image' => 'photo',
        'video' => 'video',
        'audio' => 'audio',
        _ => 'other_file',
      },
    );
    final kb = f.lengthSync() / 1024;
    final sizeLabel = kb >= 1024
        ? '${(kb / 1024).toStringAsFixed(1)} MB'
        : '${kb.round()} KB';

    return ListTile(
      dense: true,
      contentPadding: const EdgeInsetsDirectional.fromSTEB(12, 2, 6, 2),
      leading: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: (isFailed ? cs.error : cs.primary).withValues(alpha: .12),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Icon(icon, size: 19, color: isFailed ? cs.error : cs.primary),
      ),
      title: Text(
        '$typeLabel $n',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700),
      ),
      subtitle: Text(
        isFailed
            ? '${tr(ref, 'upload_failed')} · $sizeLabel'
            : sizeLabel,
        style: TextStyle(
          fontSize: 11.5,
          color: isFailed ? cs.error : cs.onSurfaceVariant,
        ),
      ),
      trailing: IconButton(
        icon: const Icon(Icons.close, size: 18),
        onPressed: () => onRemove(f),
      ),
    );
  }
}

class _ServerErrorsBox extends StatelessWidget {
  final Map<String, String> errors;
  const _ServerErrorsBox({required this.errors});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.errorContainer,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: errors.entries
            .map(
              (en) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Text(
                  '${en.key}: ${en.value}',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onErrorContainer,
                    fontSize: 13,
                  ),
                ),
              ),
            )
            .toList(),
      ),
    );
  }
}
