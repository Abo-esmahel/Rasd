import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:vector_graphics_compiler/vector_graphics_compiler.dart'
    show parse;

import 'api/api_client.dart';
import 'avatar.dart';
import 'server_pdf.dart';
import 'strings.dart';
import 'theme.dart';

void showRasdSnack(
  BuildContext context,
  String message, {
  String? actionLabel,
  VoidCallback? onAction,
}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Text(message, style: const TextStyle(height: 1.7)),
        duration: const Duration(seconds: 4),
        action: actionLabel == null
            ? null
            : SnackBarAction(label: actionLabel, onPressed: onAction ?? () {}),
      ),
    );
}

class RasdMark extends StatelessWidget {
  final double size;
  final Color? foreground;
  final Color? background;

  const RasdMark({super.key, this.size = 56, this.foreground, this.background});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final ink = foreground ?? cs.primary;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: background ?? cs.primaryContainer,
        border: Border.all(color: cs.outlineVariant),
        borderRadius: BorderRadius.circular(size * .22),
      ),
      child: CustomPaint(painter: _RasdMarkPainter(ink, RasdColors.gold)),
    );
  }
}

class _RasdMarkPainter extends CustomPainter {
  final Color ink;
  final Color accent;
  const _RasdMarkPainter(this.ink, this.accent);

  @override
  void paint(Canvas canvas, Size size) {
    final s = size.shortestSide;
    final stroke = Paint()
      ..color = ink
      ..style = PaintingStyle.stroke
      ..strokeWidth = s * .045
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final paper = RRect.fromRectAndRadius(
      Rect.fromLTWH(s * .27, s * .16, s * .46, s * .66),
      Radius.circular(s * .07),
    );
    canvas.drawRRect(paper, stroke);
    canvas.drawLine(Offset(s * .38, s * .38), Offset(s * .62, s * .38), stroke);
    canvas.drawLine(Offset(s * .38, s * .49), Offset(s * .59, s * .49), stroke);
    final dot = Paint()..color = accent;
    canvas.drawCircle(Offset(s * .40, s * .63), s * .045, dot);
    canvas.drawLine(Offset(s * .51, s * .63), Offset(s * .63, s * .63), stroke);
  }

  @override
  bool shouldRepaint(covariant _RasdMarkPainter oldDelegate) =>
      oldDelegate.ink != ink || oldDelegate.accent != accent;
}

class RasdCard extends StatelessWidget {
  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;
  const RasdCard({
    super.key,
    required this.child,
    this.onTap,
    this.padding = const EdgeInsets.all(14),
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final border = cs.brightness == Brightness.dark
        ? RasdColors.darkBorder
        : RasdColors.border;
    return Container(
      decoration: BoxDecoration(
        color: cs.surface,
        border: Border.all(color: border),
        borderRadius: BorderRadius.circular(RasdRadii.card),
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          child: Padding(padding: padding, child: child),
        ),
      ),
    );
  }
}

class RasdStatusChip extends StatelessWidget {
  final String status;
  final String label;
  const RasdStatusChip({super.key, required this.status, required this.label});

  static Color colorOf(BuildContext context, String status) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    switch (status) {
      case 'accepted':
      case 'published':
        return dark ? RasdColors.darkGreen : RasdColors.green;
      case 'pending':
        return dark ? const Color(0xFFF0C040) : RasdColors.goldInk;
      case 'rejected':
        return dark ? const Color(0xFFF08080) : RasdColors.danger;
      default:
        return dark ? RasdColors.darkInk2 : RasdColors.ink2;
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = colorOf(context, status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: c.withValues(alpha: .1),
        border: Border.all(color: c.withValues(alpha: .35)),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(color: c, shape: BoxShape.circle),
          ),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              color: c,
              fontSize: 11,
              fontWeight: FontWeight.w800,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }
}

class RasdHero extends StatelessWidget {
  final IconData icon;
  final Widget? leading;
  final String title;
  final String subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;
  const RasdHero({
    super.key,
    required this.icon,
    this.leading,
    required this.title,
    required this.subtitle,
    this.actionLabel,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        border: Border.all(
          color: dark ? RasdColors.darkBorder : RasdColors.border,
        ),
        borderRadius: BorderRadius.circular(RasdRadii.block),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          if (leading != null)
            Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(15),
                border: Border.all(
                  color: Theme.of(context).colorScheme.outline,
                ),
              ),
              clipBehavior: Clip.antiAlias,
              child: leading!,
            )
          else
            Container(
              width: 54,
              height: 54,
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.primaryContainer,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                icon,
                color: Theme.of(context).colorScheme.primary,
                size: 24,
              ),
            ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          if (actionLabel != null) ...[
            const SizedBox(width: 8),
            FilledButton(
              onPressed: onAction,
              style: FilledButton.styleFrom(
                minimumSize: const Size(0, 48),
                padding: const EdgeInsets.symmetric(horizontal: 18),
              ),
              child: Text(actionLabel!),
            ),
          ],
        ],
      ),
    );
  }
}

class RasdStat extends StatelessWidget {
  final IconData icon;
  final String value;
  final String label;
  final String? delta;
  final Color color;
  final Color soft;
  const RasdStat({
    super.key,
    required this.icon,
    required this.value,
    required this.label,
    this.delta,
    this.color = RasdColors.green,
    this.soft = RasdColors.greenSoft,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final dark = cs.brightness == Brightness.dark;
    final border = dark ? RasdColors.darkBorder : RasdColors.border;
    final fg = RasdColors.adaptive(context, color);
    final chipBg = dark ? _darkSoft(soft) : soft;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: cs.surface,
        border: Border.all(color: border),
        borderRadius: BorderRadius.circular(RasdRadii.block),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: chipBg,
                  border: Border.all(color: border),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: fg, size: 18),
              ),
              if (delta != null) ...[
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: fg.withValues(alpha: .1),
                    border: Border.all(color: fg.withValues(alpha: .3)),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    delta!,
                    style: TextStyle(
                      color: fg,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 6),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 26,
              height: 1.1,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.5,
              color: cs.onSurface,
              fontFeatures: const [FontFeature.tabularFigures()],
            ),
          ),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class RasdStatCard extends StatelessWidget {
  final IconData icon;
  final int value;
  final String label;
  final Color color;
  final bool compact;
  const RasdStatCard({
    super.key,
    required this.icon,
    required this.value,
    required this.label,
    this.color = RasdColors.green,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final fg = RasdColors.adaptive(context, color);
    final pad = compact ? 11.0 : 14.0;
    final numSize = compact ? 23.0 : 27.0;
    return Container(
      padding: EdgeInsets.all(pad),
      decoration: BoxDecoration(
        color: cs.surface,
        border: Border.all(color: fg.withValues(alpha: .22)),
        borderRadius: BorderRadius.circular(RasdRadii.card),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Container(
                width: compact ? 34 : 38,
                height: compact ? 34 : 38,
                decoration: BoxDecoration(
                  color: fg.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(compact ? 10 : 11),
                ),
                child: Icon(icon, color: fg, size: compact ? 17 : 19),
              ),
              const Spacer(),
            ],
          ),
          SizedBox(height: compact ? 8 : 10),
          _CountUp(
            value: value,
            style: TextStyle(
              fontSize: numSize,
              height: 1.1,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.5,
              color: cs.onSurface,
              fontFeatures: const [FontFeature.tabularFigures()],
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: compact ? 11.5 : 12.5,
              fontWeight: FontWeight.w600,
              color: cs.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }
}

Color _darkSoft(Color soft) {
  if (soft.toARGB32() == RasdColors.greenSoft.toARGB32()) {
    return RasdColors.darkGreenSoft;
  }
  if (soft.toARGB32() == RasdColors.goldSoft.toARGB32()) {
    return const Color(0xFF2E2716);
  }
  if (soft.toARGB32() == RasdColors.dangerSoft.toARGB32()) {
    return const Color(0xFF2D1F1F);
  }
  return soft;
}

class RasdSlideRoute<T> extends PageRouteBuilder<T> {
  final Widget page;
  RasdSlideRoute({
    required this.page,
    Duration? transitionDuration,
    Duration? reverseTransitionDuration,
  }) : super(
         transitionDuration:
             transitionDuration ?? const Duration(milliseconds: 420),
         reverseTransitionDuration:
             reverseTransitionDuration ?? const Duration(milliseconds: 320),
         pageBuilder: (_, __, ___) => page,
         transitionsBuilder: (ctx, animation, __, child) {
           final curved = CurvedAnimation(
             parent: animation,
             curve: Curves.easeOutCubic,
             reverseCurve: Curves.easeInCubic,
           );
           final dir = Directionality.of(ctx) == TextDirection.rtl ? -1.0 : 1.0;
           return SlideTransition(
             position: Tween<Offset>(
               begin: Offset(dir, 0),
               end: Offset.zero,
             ).animate(curved),
             child: child,
           );
         },
       );
}

class RasdSectionHeader extends StatelessWidget {
  final String title;
  final String? hint;
  final VoidCallback? onMore;
  final String? moreLabel;
  const RasdSectionHeader({
    super.key,
    required this.title,
    this.hint,
    this.onMore,
    this.moreLabel,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(title, style: Theme.of(context).textTheme.titleMedium),
        ),
        if (hint != null)
          Text(hint!, style: Theme.of(context).textTheme.bodySmall),
        if (onMore != null) ...[
          const SizedBox(width: 4),
          TextButton(onPressed: onMore, child: Text(moreLabel ?? '')),
        ],
      ],
    );
  }
}

class RasdEmpty extends StatelessWidget {
  final String message;
  final IconData icon;
  const RasdEmpty({
    super.key,
    required this.message,
    this.icon = Icons.inbox_outlined,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return ListView(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      children: [
        Padding(
          padding: const EdgeInsets.all(40),
          child: Column(
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: cs.primaryContainer,
                  borderRadius: BorderRadius.circular(22),
                ),
                child: Icon(icon, size: 32, color: cs.primary),
              ),
              const SizedBox(height: 14),
              Text(
                message,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleSmall,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

String friendlyErrorText(Object error, String lang) {
  if (error is ApiException) return error.userMessage;
  if (error is ServerPdfException) return error.message;
  if (error is DioException) {
    final code = error.response?.statusCode;
    if (code == 401) return trOf(lang, 'login');
    if (code == 403) return trOf(lang, 'forbidden');
    if (code == 404) return trOf(lang, 'not_found');
    return trOf(lang, 'print_failed');
  }
  final text = error.toString().trim();
  if (text.isEmpty || text.length > 160 || text.contains('Exception')) {
    return trOf(lang, 'print_failed');
  }
  return text;
}

class RasdError extends ConsumerWidget {
  final Object error;
  final VoidCallback onRetry;
  final String retryLabel;
  const RasdError({
    super.key,
    required this.error,
    required this.onRetry,
    required this.retryLabel,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    return ListView(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      children: [
        Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: cs.errorContainer,
                  borderRadius: BorderRadius.circular(22),
                ),
                child: Icon(
                  Icons.cloud_off_outlined,
                  size: 32,
                  color: cs.error,
                ),
              ),
              const SizedBox(height: 14),
              Text(
                friendlyErrorText(error, ref.watch(localeProvider)),
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              FilledButton(onPressed: onRetry, child: Text(retryLabel)),
            ],
          ),
        ),
      ],
    );
  }
}

class RasdSkeletonList extends StatefulWidget {
  final int count;
  const RasdSkeletonList({super.key, this.count = 5});

  @override
  State<RasdSkeletonList> createState() => _RasdSkeletonListState();
}

class _RasdSkeletonListState extends State<RasdSkeletonList>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c;

  @override
  void initState() {
    super.initState();
    _c = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final base = cs.surfaceContainerHighest;
    return AnimatedBuilder(
      animation: _c,
      builder: (_, __) => Opacity(
        opacity: .45 + .4 * _c.value,
        child: ListView.builder(
          padding: const EdgeInsets.all(14),
          itemCount: widget.count,
          itemBuilder: (_, i) => Container(
            height: 110,
            margin: const EdgeInsets.only(bottom: 10),
            decoration: BoxDecoration(
              color: base,
              borderRadius: BorderRadius.circular(20),
            ),
          ),
        ),
      ),
    );
  }
}

final _svgBytesProvider = FutureProvider.family.autoDispose<Uint8List?, String>(
  (ref, url) async {
    final headers = ref.watch(authImageHeadersProvider);
    try {
      final res = await ref
          .read(dioProvider)
          .get<List<int>>(
            url,
            options: Options(
              responseType: ResponseType.bytes,
              headers: headers,
            ),
          );
      final list = res.data;
      if (list == null || list.isEmpty) return null;
      final bytes = Uint8List.fromList(list);
      parse(utf8.decode(bytes, allowMalformed: true));
      return bytes;
    } catch (_) {
      return null;
    }
  },
);

class RasdNetworkImage extends ConsumerWidget {
  final String url;
  final Map<String, String> headers;
  final BoxFit fit;
  final bool isSvg;
  const RasdNetworkImage({
    super.key,
    required this.url,
    this.headers = const {},
    this.fit = BoxFit.cover,
    this.isSvg = false,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (!isSvg) {
      return Image.network(
        url,
        headers: headers,
        fit: fit,
        gaplessPlayback: true,
        frameBuilder: (c, child, frame, sync) => AnimatedOpacity(
          opacity: frame == null ? 0 : 1,
          duration: const Duration(milliseconds: 250),
          child: child,
        ),
        errorBuilder: (_, __, ___) =>
            const _ImgFallback(icon: Icons.image_outlined),
      );
    }
    return ref
        .watch(_svgBytesProvider(url))
        .when(
          loading: () => const _ImgFallback(icon: Icons.image_outlined),
          error: (_, __) => const _ImgFallback(icon: Icons.image_outlined),
          data: (b) => b == null
              ? const _ImgFallback(icon: Icons.image_outlined)
              : SvgPicture.memory(
                  b,
                  fit: fit,
                  placeholderBuilder: (_) =>
                      const _ImgFallback(icon: Icons.image_outlined),
                ),
        );
  }
}

class _ImgFallback extends StatelessWidget {
  final IconData icon;
  const _ImgFallback({required this.icon});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      color: cs.surfaceContainerHigh,
      alignment: Alignment.center,
      child: Icon(icon, color: cs.onSurfaceVariant, size: 28),
    );
  }
}

class RasdAvatar extends ConsumerWidget {
  final String? url;
  final String name;
  final double size;
  final double ringWidth;
  final ImageProvider? image;
  const RasdAvatar({
    super.key,
    required this.url,
    required this.name,
    this.size = 40,
    this.ringWidth = 2,
    this.image,
  });

  String _full(BuildContext context, WidgetRef ref) => AvatarUrl.of(ref, url);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final full = _full(context, ref);
    final dark = Theme.of(context).brightness == Brightness.dark;
    final initial = name.characters.firstOrNull ?? '?';
    final inner = (size - ringWidth * 2).clamp(8.0, size);
    // فكّ الترميز بحجم العرض الفعلي فقط — الصورة الأصلية قد تكون 2MB
    // لدائرة 22px، وهذا كان يملأ ذاكرة الصور في القوائم الطويلة.
    final decodeSize = (inner * MediaQuery.devicePixelRatioOf(context))
        .round()
        .clamp(24, 512);
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: RasdColors.green.withValues(alpha: .45),
        boxShadow: [
          BoxShadow(
            color: RasdColors.green.withValues(alpha: dark ? .25 : .12),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      alignment: Alignment.center,
      child: ClipOval(
        child: Container(
          width: inner,
          height: inner,
          color: dark ? RasdColors.darkGreenSoft : RasdColors.greenSoft,
          child: Stack(
            fit: StackFit.expand,
            children: [
              _AvatarInitial(initial: initial, size: inner),
              if (image != null)
                Image(
                  image: image!,
                  fit: BoxFit.cover,
                  alignment: Alignment.center,
                  gaplessPlayback: true,
                  filterQuality: FilterQuality.medium,
                  errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                )
              else if (full.isNotEmpty)
                Image.network(
                  full,
                  fit: BoxFit.cover,
                  alignment: Alignment.center,
                  gaplessPlayback: true,
                  cacheWidth: decodeSize,
                  cacheHeight: decodeSize,
                  filterQuality: FilterQuality.medium,
                  frameBuilder: (c, child, frame, sync) => AnimatedOpacity(
                    opacity: frame == null ? 0 : 1,
                    duration: const Duration(milliseconds: 250),
                    child: child,
                  ),
                  errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AvatarInitial extends StatelessWidget {
  final String initial;
  final double size;
  const _AvatarInitial({required this.initial, required this.size});

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      color: dark ? RasdColors.darkGreenSoft : RasdColors.greenSoft,
      alignment: Alignment.center,
      child: Text(
        initial,
        style: TextStyle(
          fontSize: size * 0.42,
          fontWeight: FontWeight.w800,
          color: dark ? RasdColors.darkGreen : RasdColors.greenDark,
        ),
      ),
    );
  }
}

Future<bool> confirmDestructive(
  BuildContext context, {
  required String title,
  required String body,
  required String confirmLabel,
  required String cancelLabel,
}) async {
  final ok = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: Text(body),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx, false),
          child: Text(cancelLabel),
        ),
        FilledButton(
          style: FilledButton.styleFrom(
            backgroundColor: Theme.of(ctx).colorScheme.error,
          ),
          onPressed: () => Navigator.pop(ctx, true),
          child: Text(confirmLabel),
        ),
      ],
    ),
  );
  return ok == true;
}

class _CountUp extends StatefulWidget {
  final int value;
  final TextStyle style;
  const _CountUp({required this.value, required this.style});

  @override
  State<_CountUp> createState() => _CountUpState();
}

class _CountUpState extends State<_CountUp>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c;
  late Animation<int> _anim;
  int _from = 0;

  @override
  void initState() {
    super.initState();
    _c = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
    _anim = IntTween(
      begin: 0,
      end: widget.value,
    ).animate(CurvedAnimation(parent: _c, curve: Curves.easeOutCubic));
    _c.forward();
  }

  @override
  void didUpdateWidget(_CountUp old) {
    super.didUpdateWidget(old);
    if (widget.value != old.value) {
      _from = old.value;
      _anim = IntTween(
        begin: _from,
        end: widget.value,
      ).animate(CurvedAnimation(parent: _c, curve: Curves.easeOutCubic));
      _c.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _anim,
      builder: (_, __) => Text(
        '${_anim.value}',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: widget.style,
      ),
    );
  }
}
