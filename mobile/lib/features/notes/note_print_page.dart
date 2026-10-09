import 'dart:io' show File;
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';
import 'package:pdf/pdf.dart';
import 'package:printing/printing.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/server_pdf.dart';
import '../../core/strings.dart';

class NotePrintPage extends ConsumerStatefulWidget {
  final String title;
  final ServerPdfKind kind;
  final int id;
  final String fileName;
  const NotePrintPage({
    super.key,
    required this.title,
    required this.kind,
    required this.id,
    required this.fileName,
  });

  @override
  ConsumerState<NotePrintPage> createState() => _NotePrintPageState();
}

class _NotePrintPageState extends ConsumerState<NotePrintPage> {
  bool _loading = true;
  bool _pdfBusy = false;
  String? _error;
  Uint8List? _pdfBytes;
  String _stage = '';
  int _loadId = 0;

  bool _alive(int id) => id == _loadId && mounted;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _load();
    });
  }

  Future<void> _load() async {
    final id = ++_loadId;
    setState(() {
      _loading = true;
      _error = null;
      _pdfBytes = null;
      _stage = tr(ref, 'preparing_print');
    });
    try {
      final bytes = await ServerPdf.fetch(
        ref,
        widget.kind,
        widget.id,
        onStage: (s) {
          if (!_alive(id)) return;
          setState(
            () => _stage = s == 'generating' ? tr(ref, 'preparing_print') : s,
          );
        },
      );
      if (!_alive(id)) return;
      setState(() {
        _pdfBytes = bytes;
        _loading = false;
      });
    } catch (e) {
      if (!_alive(id)) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _print() async {
    final bytes = _pdfBytes;
    if (bytes == null) return;
    setState(() => _pdfBusy = true);
    try {
      await Printing.layoutPdf(
        onLayout: (_) async => bytes,
        name: widget.title,
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _pdfBusy = false);
    }
  }

  Future<void> _sharePdf() async {
    final bytes = _pdfBytes;
    if (bytes == null) return;
    setState(() => _pdfBusy = true);
    try {
      final dir = await getTemporaryDirectory();
      final f = File('${dir.path}/${widget.fileName}');
      await f.writeAsBytes(bytes);
      await SharePlus.instance.share(
        ShareParams(files: [XFile(f.path)], text: widget.title),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _pdfBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ready = _pdfBytes != null;
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title, maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [
          if (ready && !_pdfBusy && !_loading) ...[
            IconButton(
              tooltip: tr(ref, 'share_pdf'),
              onPressed: _sharePdf,
              icon: const Icon(Icons.picture_as_pdf_outlined),
            ),
            IconButton(
              tooltip: tr(ref, 'print_now'),
              onPressed: _print,
              icon: const Icon(Icons.print_outlined),
            ),
          ],
          if (_pdfBusy || _loading)
            const Padding(
              padding: EdgeInsets.all(14),
              child: SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_loading || _error != null)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: _loading
                    ? Row(
                        children: [
                          const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                          const SizedBox(width: 12),
                          Expanded(child: Text(_stage)),
                        ],
                      )
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            _error!,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.error,
                            ),
                          ),
                          const SizedBox(height: 10),
                          FilledButton.icon(
                            onPressed: _load,
                            icon: const Icon(Icons.refresh_rounded, size: 18),
                            label: Text(tr(ref, 'retry')),
                          ),
                        ],
                      ),
              ),
            ),
          if (ready) ...[
            const SizedBox(height: 4),
            Container(
              height: 560,
              decoration: BoxDecoration(
                border: Border.all(
                  color: Theme.of(context).colorScheme.outline,
                ),
                borderRadius: BorderRadius.circular(12),
              ),
              clipBehavior: Clip.antiAlias,
              child: PdfPreview(
                build: (_) async => _pdfBytes!,
                initialPageFormat: PdfPageFormat.a4,
                canChangePageFormat: false,
                canChangeOrientation: false,
                allowPrinting: false,
                allowSharing: false,
                pdfFileName: widget.fileName,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: FilledButton.icon(
                    onPressed: _pdfBusy ? null : _print,
                    icon: const Icon(Icons.print_outlined, size: 18),
                    label: Text(tr(ref, 'print_now')),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _pdfBusy ? null : _sharePdf,
                    icon: const Icon(Icons.picture_as_pdf_outlined, size: 18),
                    label: Text(tr(ref, 'share_pdf')),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
