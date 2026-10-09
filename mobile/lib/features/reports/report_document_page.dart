import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';
import 'package:pdf/pdf.dart';
import 'package:printing/printing.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/server_pdf.dart';
import '../../core/strings.dart';

class ReportDocumentPage extends ConsumerStatefulWidget {
  final int id;
  final String title;
  const ReportDocumentPage({super.key, required this.id, required this.title});

  @override
  ConsumerState<ReportDocumentPage> createState() => _ReportDocumentPageState();
}

class _ReportDocumentPageState extends ConsumerState<ReportDocumentPage> {
  String? _error;
  Uint8List? _pdfBytes;
  bool _loading = true;
  bool _pdfBusy = false;
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
        ServerPdfKind.report,
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

  Future<void> _sharePdf() async {
    final bytes = _pdfBytes;
    if (bytes == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(tr(ref, 'document_not_ready'))));
      return;
    }
    setState(() => _pdfBusy = true);
    try {
      final dir = await getTemporaryDirectory();
      final f = File('${dir.path}/report-${widget.id}.pdf');
      await f.writeAsBytes(bytes);
      await SharePlus.instance.share(ShareParams(files: [XFile(f.path)]));
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

  Future<void> _print() async {
    final bytes = _pdfBytes;
    if (bytes == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(tr(ref, 'document_not_ready'))));
      return;
    }
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

  @override
  Widget build(BuildContext context) {
    final ready = _pdfBytes != null;
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title, maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [
          if (ready && !_pdfBusy && !_loading) ...[
            IconButton(
              tooltip: tr(ref, 'report_pdf'),
              onPressed: _sharePdf,
              icon: const Icon(Icons.picture_as_pdf_outlined),
            ),
            IconButton(
              tooltip: tr(ref, 'print_doc'),
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
      body: _error != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_error!, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: _load,
                      child: Text(tr(ref, 'retry')),
                    ),
                  ],
                ),
              ),
            )
          : _loading || !ready
          ? Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const CircularProgressIndicator(),
                  const SizedBox(height: 12),
                  Text(_stage),
                ],
              ),
            )
          : PdfPreview(
              build: (_) async => _pdfBytes!,
              initialPageFormat: PdfPageFormat.a4,
              canChangePageFormat: false,
              canChangeOrientation: false,
              allowPrinting: false,
              allowSharing: false,
              pdfFileName: 'report-${widget.id}.pdf',
            ),
    );
  }
}
