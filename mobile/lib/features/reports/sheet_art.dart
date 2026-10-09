import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/config.dart';
import '../../core/models.dart';

int? sheetNumber(Report r) {
  final n = r.notesCount ?? r.notes.length;
  return (n >= 1 && n <= 7) ? n : null;
}

String? sheetPath(Report r) {
  final n = sheetNumber(r);
  return n == null ? null : '/api/report-sheets/$n';
}

class SheetCover extends ConsumerWidget {
  final Report report;
  final double aspectRatio;
  final BorderRadius borderRadius;
  const SheetCover({
    super.key,
    required this.report,
    this.aspectRatio = 21 / 9,
    this.borderRadius = const BorderRadius.vertical(top: Radius.circular(20)),
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = sheetPath(report);
    return AspectRatio(
      aspectRatio: aspectRatio,
      child: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topRight,
            end: Alignment.bottomLeft,
            colors: [Color(0xFF0A4D28), Color(0xFF12934F)],
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: p == null
            ? const _FallbackArt()
            : Image.network(
                '${ref.watch(baseUrlProvider)}$p',
                headers: ref.watch(authImageHeadersProvider),
                fit: BoxFit.cover,
                alignment: Alignment.topCenter,
                errorBuilder: (_, __, ___) => const _FallbackArt(),
              ),
      ),
    );
  }
}

class _FallbackArt extends StatelessWidget {
  const _FallbackArt();

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned.directional(
          textDirection: Directionality.of(context),
          top: -30,
          end: -30,
          child: const _Halo(size: 130, opacity: .12),
        ),
        Positioned.directional(
          textDirection: Directionality.of(context),
          bottom: -40,
          start: -20,
          child: const _Halo(size: 150, opacity: .08),
        ),
        Center(
          child: Icon(
            Icons.description_outlined,
            size: 44,
            color: Colors.white70,
          ),
        ),
      ],
    );
  }
}

class _Halo extends StatelessWidget {
  final double size;
  final double opacity;
  const _Halo({required this.size, required this.opacity});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withValues(alpha: opacity),
      ),
    );
  }
}
