import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/bootstrap.dart';
import '../../core/strings.dart';
import '../../core/widgets.dart';

class SplashPage extends ConsumerStatefulWidget {
  const SplashPage({super.key});

  @override
  ConsumerState<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends ConsumerState<SplashPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) ref.read(bootstrapProvider.notifier).start();
    });
  }

  @override
  Widget build(BuildContext context) {
    final boot = ref.watch(bootstrapProvider);
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: cs.surface,
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 460),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(24, 28, 24, 20),
              child: Column(
                children: [
                  const Spacer(flex: 3),
                  const RasdMark(size: 88),
                  const SizedBox(height: 22),
                  Text(
                    tr(ref, 'app_name'),
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: cs.onSurface,
                      fontSize: 34,
                      fontWeight: FontWeight.w700,
                      height: 1.2,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    tr(ref, 'ministry'),
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: cs.primary,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 300),
                    child: Text(
                      tr(ref, 'app_tagline'),
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: cs.onSurfaceVariant,
                        fontSize: 13,
                        height: 1.7,
                      ),
                    ),
                  ),
                  const Spacer(flex: 3),
                  if (boot.failed)
                    _BootError(
                      message: boot.error.toString(),
                      retry: () => ref.read(bootstrapProvider.notifier).retry(),
                    )
                  else ...[
                    Builder(
                      builder: (context) {
                        final p = boot.progress.clamp(0.0, 1.0);
                        final indeterminate = boot.progress <= 0;
                        return SizedBox(
                          width: 84,
                          height: 84,
                          child: Stack(
                            alignment: Alignment.center,
                            children: [
                              CircularProgressIndicator(
                                value: indeterminate ? null : p,
                                strokeWidth: 7,
                                strokeCap: StrokeCap.round,
                                backgroundColor:
                                    cs.surfaceContainerHigh,
                                valueColor:
                                    AlwaysStoppedAnimation<Color>(
                                      cs.primary,
                                    ),
                              ),
                              if (!indeterminate)
                                Text(
                                  '${(p * 100).round()}٪',
                                  style: TextStyle(
                                    color: cs.primary,
                                    fontSize: 15,
                                    fontWeight: FontWeight.w800,
                                    fontFeatures: const [
                                      FontFeature.tabularFigures(),
                                    ],
                                  ),
                                )
                              else
                                Icon(
                                  Icons.bolt_rounded,
                                  color: cs.primary,
                                  size: 26,
                                ),
                            ],
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 12),
                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 180),
                      child: Text(
                        _phaseLabel(ref, boot),
                        key: ValueKey(boot.phase),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: cs.onSurfaceVariant,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 18),
                  Text(
                    'رصد • 1.0.1',
                    style: TextStyle(
                      color: cs.onSurfaceVariant.withValues(alpha: .65),
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  String _phaseLabel(WidgetRef ref, BootState boot) => switch (boot.phase) {
    BootPhase.storage => tr(ref, 'boot_storage'),
    BootPhase.prefs => tr(ref, 'boot_prefs'),
    BootPhase.session => tr(ref, 'boot_session'),
    BootPhase.warmup => tr(ref, 'boot_warmup'),
    BootPhase.done =>
      boot.sessionAvailable ? tr(ref, 'boot_done') : tr(ref, 'boot_session'),
  };
}

class _BootError extends ConsumerWidget {
  final String message;
  final VoidCallback retry;
  const _BootError({required this.message, required this.retry});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: cs.errorContainer,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: cs.error.withValues(alpha: .28)),
      ),
      child: Column(
        children: [
          Icon(Icons.cloud_off_outlined, color: cs.error, size: 26),
          const SizedBox(height: 8),
          Text(
            message,
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: cs.onErrorContainer,
              fontSize: 12,
              height: 1.6,
            ),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: retry,
            icon: const Icon(Icons.refresh_rounded, size: 18),
            label: Text(tr(ref, 'retry')),
            style: OutlinedButton.styleFrom(
              foregroundColor: cs.onErrorContainer,
              side: BorderSide(color: cs.error.withValues(alpha: .35)),
              minimumSize: const Size.fromHeight(44),
            ),
          ),
        ],
      ),
    );
  }
}
