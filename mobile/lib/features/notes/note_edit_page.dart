import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/models.dart';
import '../../core/strings.dart';
import '../../core/validators.dart';
import '../../core/wall_clock.dart';
import 'notes_providers.dart';
import 'notes_repo.dart';

class NoteEditPage extends ConsumerStatefulWidget {
  final Note note;
  const NoteEditPage({super.key, required this.note});

  @override
  ConsumerState<NoteEditPage> createState() => _NoteEditPageState();
}

class _NoteEditPageState extends ConsumerState<NoteEditPage> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _floor;
  late final TextEditingController _cam;
  late final TextEditingController _desc;
  DateTime? _date;
  TimeOfDay? _start;
  TimeOfDay? _end;
  final List<File> _newFiles = [];
  final _removedAttIds = <int>{};
  bool _busy = false;
  String? _error;
  final _serverErrors = <String, String>{};

  @override
  void initState() {
    super.initState();
    final n = widget.note;
    _floor = TextEditingController(text: '${n.floorNumber}');
    _cam = TextEditingController(text: '${n.cameraNumber}');
    _desc = TextEditingController(text: n.description);
    final p = Wall.parts(n.observedAtRaw);
    _date = DateTime.tryParse(p.date.isEmpty ? '' : p.date);
    _start = _tod(p.time);
    final pe = Wall.parts(n.observedEndAtRaw);
    _end = _tod(pe.time);
  }

  TimeOfDay? _tod(String hhmm) {
    final p = hhmm.split(':');
    if (p.length < 2) return null;
    final h = int.tryParse(p[0]);
    final m = int.tryParse(p[1]);
    if (h == null || m == null) return null;
    return TimeOfDay(hour: h, minute: m);
  }

  Future<void> _pickFiles() async {
    final res = await FilePicker.platform.pickFiles(
      allowMultiple: true,
      type: FileType.custom,
      allowedExtensions: const [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'mp4',
        'webm',
        'mov',
        '3gp',
        'mkv',
        'mp3',
        'wav',
        'ogg',
        'm4a',
        'aac',
      ],
    );
    if (res == null) return;
    setState(() {
      for (final p in res.paths) {
        if (p != null) _newFiles.add(File(p));
      }
    });
  }

  Future<void> _submit() async {
    setState(() {
      _error = null;
      _serverErrors.clear();
    });
    if (!_form.currentState!.validate()) return;
    if (_date == null || _start == null) {
      setState(() => _error = tr(ref, 'datetime_required'));
      return;
    }
    if (Wall.isFuture(_date!, _start!)) {
      setState(() => _error = tr(ref, 'time_future'));
      return;
    }
    if (_end != null &&
        Wall.spanMinutes(_start!, _end!) > Wall.maxSpanMinutes) {
      setState(() => _error = tr(ref, 'time_span_12h'));
      return;
    }
    final remaining = widget.note.attachments.length - _removedAttIds.length;
    if (remaining + _newFiles.length > V.maxFiles) {
      setState(
        () => _error = tr(
          ref,
          'attach_max',
          args: {'max': '${V.maxFiles}', 'count': '$remaining'},
        ),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      final repo = ref.read(notesRepoProvider);
      final end = _end;
      final data = {
        'floor_number': int.parse(_floor.text.trim()),
        'camera_number': int.parse(_cam.text.trim()),
        'observed_at': Wall.combine(_date!, _start!),
        'observed_end_at': end == null
            ? null
            : Wall.combine(
                end.hour * 60 + end.minute < _start!.hour * 60 + _start!.minute
                    ? _date!.add(const Duration(days: 1))
                    : _date!,
                end,
              ),
        'description': _desc.text.trim(),
      };
      var note = await repo.update(widget.note.id, data);
      for (final id in _removedAttIds) {
        await repo.deleteAttachment(note.id, id);
      }
      for (final f in _newFiles) {
        await repo.uploadAttachment(note.id, f);
      }
      note = await repo.show(note.id);
      if (!mounted) return;
      ref.read(notesListProvider.notifier).refresh();
      ref.read(noteDetailProvider(note.id).notifier).reload();
      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = null;
        _serverErrors.clear();
        if (e is ApiException) {
          for (final en in e.fieldErrors.entries) {
            final list = en.value;
            if (list.isNotEmpty) {
              _serverErrors[_fieldName(en.key)] = list.first;
            }
          }
          if (_serverErrors.isEmpty) _error = e.toString();
        } else {
          _error = e.toString();
        }
      });
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
  void dispose() {
    _floor.dispose();
    _cam.dispose();
    _desc.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final n = widget.note;
    final remaining = n.attachments.where(
      (a) => !_removedAttIds.contains(a.id),
    );
    return Scaffold(
      appBar: AppBar(title: Text(tr(ref, 'edit'))),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 680),
          child: Form(
            key: _form,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _floor,
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
                        controller: _cam,
                        keyboardType: TextInputType.number,
                        validator: V.camera,
                        decoration: InputDecoration(
                          labelText: tr(ref, 'camera'),
                        ),
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
                        : '${_date!.year}-${_date!.month.toString().padLeft(2, '0')}-${_date!.day.toString().padLeft(2, '0')}',
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
                TextFormField(
                  spellCheckConfiguration:
                      const SpellCheckConfiguration.disabled(),
                  controller: _desc,
                  maxLines: 6,
                  maxLength: 5000,
                  validator: V.description,
                  decoration: InputDecoration(
                    labelText: tr(ref, 'description'),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  tr(ref, 'attachments'),
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                ...remaining.map(
                  (a) => ListTile(
                    dense: true,
                    leading: const Icon(Icons.attach_file),
                    title: Text(
                      a.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    trailing: IconButton(
                      icon: const Icon(Icons.delete_outline),
                      onPressed: _busy
                          ? null
                          : () => setState(() => _removedAttIds.add(a.id)),
                    ),
                  ),
                ),
                ..._newFiles.map(
                  (f) => ListTile(
                    dense: true,
                    leading: const Icon(Icons.upload_file),
                    title: Text(
                      f.path.split(RegExp(r'[/\\]')).last,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    trailing: IconButton(
                      icon: const Icon(Icons.close),
                      onPressed: _busy
                          ? null
                          : () => setState(() => _newFiles.remove(f)),
                    ),
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: _busy ? null : _pickFiles,
                  icon: const Icon(Icons.add),
                  label: Text(tr(ref, 'pick_photos')),
                ),
                if (_serverErrors.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.errorContainer,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: _serverErrors.entries
                          .map(
                            (en) => Padding(
                              padding: const EdgeInsets.symmetric(vertical: 2),
                              child: Text(
                                '${en.key}: ${en.value}',
                                style: TextStyle(
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.onErrorContainer,
                                  fontSize: 13,
                                ),
                              ),
                            ),
                          )
                          .toList(),
                    ),
                  ),
                ],
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
                      : Text(tr(ref, 'confirm')),
                ),
                const SizedBox(height: 40),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
