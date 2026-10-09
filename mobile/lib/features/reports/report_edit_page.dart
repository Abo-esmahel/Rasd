import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/models.dart';
import '../../core/strings.dart';
import '../../core/validators.dart';
import 'reports_providers.dart';
import 'reports_repo.dart';

class ReportEditPage extends ConsumerStatefulWidget {
  final Report report;
  const ReportEditPage({super.key, required this.report});

  @override
  ConsumerState<ReportEditPage> createState() => _ReportEditPageState();
}

class _ReportEditPageState extends ConsumerState<ReportEditPage> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _content;
  late DateTime _date;
  bool _busy = false;
  final Map<String, String> _serverErrors = {};
  String? _error;

  @override
  void initState() {
    super.initState();
    _title = TextEditingController(text: widget.report.title);
    _content = TextEditingController(text: widget.report.content ?? '');
    final d = widget.report.reportDate;
    _date = DateTime.tryParse(d) ?? DateTime.now();
  }

  Future<void> _submit() async {
    setState(() {
      _serverErrors.clear();
      _error = null;
    });
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final title = _title.text.trim();
      await ref.read(reportsRepoProvider).update(widget.report.id, {
        if (title.isNotEmpty) 'title': title,
        'report_date':
            '${_date.year}-${_date.month.toString().padLeft(2, '0')}-${_date.day.toString().padLeft(2, '0')}',
        'content': _content.text.trim(),
      });
      if (!mounted) return;
      ref.read(reportsListProvider.notifier).refresh();
      ref.read(reportDetailProvider(widget.report.id).notifier).reload();
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        for (final en in e.fieldErrors.entries) {
          if (en.value.isNotEmpty) {
            _serverErrors[en.key] = en.value.first;
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

  @override
  void dispose() {
    _title.dispose();
    _content.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(tr(ref, 'edit'))),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              spellCheckConfiguration: const SpellCheckConfiguration.disabled(),
              controller: _title,
              validator: V.reportTitle,
              decoration: InputDecoration(labelText: tr(ref, 'title')),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.calendar_today_outlined),
              title: Text(
                '${_date.year}-${_date.month.toString().padLeft(2, '0')}-${_date.day.toString().padLeft(2, '0')}',
              ),
              onTap: () async {
                final p = await showDatePicker(
                  context: context,
                  initialDate: _date,
                  firstDate: DateTime(2020),
                  lastDate: DateTime.now(),
                );
                if (p != null) setState(() => _date = p);
              },
            ),
            TextFormField(
              spellCheckConfiguration: const SpellCheckConfiguration.disabled(),
              controller: _content,
              maxLines: 5,
              maxLength: 20000,
              decoration: InputDecoration(
                labelText: tr(ref, 'content_optional'),
              ),
            ),
            if (_serverErrors.isNotEmpty)
              ..._serverErrors.entries.map(
                (en) => Text(
                  '${en.key}: ${en.value}',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.error,
                    fontSize: 13,
                  ),
                ),
              ),
            if (_error != null)
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: _busy ? null : _submit,
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
              ),
              child: Text(tr(ref, 'confirm')),
            ),
          ],
        ),
      ),
    );
  }
}
