import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../core/config.dart';
import '../../core/server_discovery.dart' as discovery;
import '../../core/strings.dart';
import '../../core/validators.dart';
import 'auth_provider.dart';

const _green = Color(0xFF0E6A38);
const _gold = Color(0xFFD9AB3C);

class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final _userCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  late final TextEditingController _serverCtrl;
  bool _obscure = true;
  bool _busy = false;
  bool _scanning = false;
  String? _scanStatus;
  String? _error;
  String? _usernameError;
  String? _passwordError;
  String? _serverError;

  @override
  void initState() {
    super.initState();
    _serverCtrl = TextEditingController(text: ref.read(baseUrlProvider));
  }

  @override
  void dispose() {
    _userCtrl.dispose();
    _passCtrl.dispose();
    _serverCtrl.dispose();
    super.dispose();
  }

  Widget _entrance(int index, Widget child) =>
      TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: 1),
        duration: Duration(milliseconds: 450 + index * 120),
        curve: Curves.easeOutCubic,
        builder: (_, v, c) => Opacity(
          opacity: v,
          child: Transform.translate(
            offset: Offset(0, 24 * (1 - v)),
            child: c,
          ),
        ),
        child: child,
      );

  Future<void> _submit() async {
    final u = _userCtrl.text.trim();
    final uErr = V.username(u);
    final pErr = V.password(_passCtrl.text);
    if (uErr != null || pErr != null) {
      setState(() {
        _usernameError = uErr;
        _passwordError = pErr;
        _error = null;
      });
      return;
    }
    final serverErr = V.serverAddress(_serverCtrl.text);
    if (serverErr != null) {
      setState(() => _serverError = serverErr);
      return;
    }
    final base = normalizeBaseUrl(_serverCtrl.text);
    ref.read(baseUrlProvider.notifier).state = base;
    await saveBaseUrl(base);
    setState(() {
      _busy = true;
      _error = null;
      _usernameError = null;
      _passwordError = null;
    });
    final err = await ref.read(authProvider.notifier).login(u, _passCtrl.text);
    if (mounted) {
      setState(() {
        _busy = false;
        _error = err;
      });
    }
  }

  Future<void> _autoScan([void Function(void Function())? refresh]) async {
    if (_scanning) return;
    final lang = ref.read(localeProvider);
    void paint(void Function() b) {
      b();
      if (mounted) setState(() {});
      try {
        refresh?.call(() {});
      } catch (_) {}
    }

    paint(() {
      _scanning = true;
      _scanStatus = trOf(lang, 'scanning');
      _serverError = null;
    });
    try {
      try {
        await Permission.locationWhenInUse.request();
      } catch (_) {}
      final found = await discovery.discoverRasdServers(
        onProgress: (done, total) {
          if (!mounted) return;
          paint(() {
            _scanStatus = '${trOf(lang, 'scanning')} $done/$total';
          });
        },
      );
      if (!mounted) return;
      if (found.isEmpty) {
        paint(() => _serverError = trOf(lang, 'server_not_found'));
        return;
      }
      final best = found.first.baseUrl;
      _serverCtrl.text = best;
      ref.read(baseUrlProvider.notifier).state = best;
      await saveBaseUrl(best);
      paint(() {
        _scanStatus = '${trOf(lang, 'server_found')}: $best';
      });
    } finally {
      if (mounted) {
        paint(() {
          _scanning = false;
        });
      }
    }
  }

  Future<void> _serverSheet() async {
    final lang = ref.read(localeProvider);
    if (!_scanning) {
      _scanStatus = null;
      _serverError = null;
    }
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (sheetCtx, setSheet) => Padding(
        padding: EdgeInsets.fromLTRB(
          22,
          12,
          22,
          22 + MediaQuery.of(ctx).viewInsets.bottom,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 44,
                  height: 5,
                  decoration: BoxDecoration(
                    color: Colors.grey.withValues(alpha: .35),
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: _green.withValues(alpha: .1),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(
                      Icons.dns_outlined,
                      color: _green,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      trOf(lang, 'server_settings'),
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              TextField(
                controller: _serverCtrl,
                textDirection: TextDirection.ltr,
                keyboardType: TextInputType.url,
                decoration: InputDecoration(
                  labelText: trOf(lang, 'server_address'),
                  errorText: _serverError,
                  prefixIcon: const Icon(Icons.link_outlined, size: 20),
                ),
                onChanged: (_) {
                  if (_serverError != null) setState(() => _serverError = null);
                },
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _scanning ? null : () => _autoScan(setSheet),
                  icon: _scanning
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.radar_outlined, size: 20),
                  label: Text(trOf(lang, 'auto_scan')),
                ),
              ),
              if (_scanning || _scanStatus != null) ...[
                const SizedBox(height: 8),
                if (_scanning) const LinearProgressIndicator(minHeight: 3),
                if (_scanStatus != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text(
                      _scanStatus!,
                      textDirection: TextDirection.ltr,
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 11,
                        color: Theme.of(
                          sheetCtx,
                        ).colorScheme.onSurfaceVariant,
                        fontFamily: 'monospace',
                      ),
                    ),
                  ),
              ],
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () async {
                        _serverCtrl.text = await resolveDefaultBaseUrl();
                      },
                      child: Text(trOf(lang, 'reset_default')),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton(
                      style: FilledButton.styleFrom(backgroundColor: _green),
                      onPressed: () async {
                        final problem = V.serverAddress(_serverCtrl.text);
                        if (problem != null) {
                          setState(() => _serverError = problem);
                          return;
                        }
                        final base = normalizeBaseUrl(_serverCtrl.text);
                        _serverCtrl.text = base;
                        ref.read(baseUrlProvider.notifier).state = base;
                        await saveBaseUrl(base);
                        if (ctx.mounted) Navigator.pop(ctx);
                      },
                      child: Text(trOf(lang, 'confirm')),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
          ),
      ),
    );
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(
        statusBarColor: Colors.transparent,
      ),
      child: Scaffold(
        extendBodyBehindAppBar: true,
        body: GestureDetector(
          onTap: () => FocusScope.of(context).unfocus(),
          child: Stack(
            children: [
              Positioned.fill(
                child: Image.asset(
                  'assets/cctv-wall.jpg',
                  fit: BoxFit.cover,
                  alignment: Alignment.center,
                ),
              ),
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        const Color(0xFF07120C).withValues(alpha: .62),
                        const Color(0xFF07120C).withValues(alpha: .78),
                        const Color(0xFF04100A).withValues(alpha: .94),
                      ],
                      stops: const [0, .55, 1],
                    ),
                  ),
                ),
              ),
              SafeArea(
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 520),
                    child: ListView(
                      padding:
                          const EdgeInsets.fromLTRB(22, 34, 22, 24),
                      children: [
                        _entrance(0, _hero()),
                        const SizedBox(height: 24),
                        _entrance(1, _formCard(cs)),
                        const SizedBox(height: 16),
                        _entrance(
                          2,
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Icon(
                                Icons.wifi_outlined,
                                size: 15,
                                color: Colors.white70,
                              ),
                              const SizedBox(width: 7),
                              Flexible(
                                child: Text(
                                  tr(ref, 'lan_hint'),
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: Colors.white70,
                                    height: 1.7,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _hero() {
    return Column(
      children: [
        Text(
          tr(ref, 'ministry'),
          textAlign: TextAlign.center,
          style: TextStyle(
            color: _gold,
            fontSize: 13,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.2,
          ),
        ),
        const SizedBox(height: 18),
        Container(
          width: 148,
          height: 148,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: const RadialGradient(
              colors: [Color(0xFF0A3A33), Color(0xFF002623)],
            ),
            border: Border.all(color: _gold.withValues(alpha: .9), width: 2),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: .55),
                blurRadius: 26,
                offset: const Offset(0, 10),
              ),
              BoxShadow(
                color: _gold.withValues(alpha: .18),
                blurRadius: 40,
                spreadRadius: 2,
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 26),
            child: Image.asset(
              'assets/eagle-emblem.png',
              fit: BoxFit.contain,
            ),
          ),
        ),
        const SizedBox(height: 20),
        Text(
          tr(ref, 'app_name'),
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 38,
            fontWeight: FontWeight.w800,
            height: 1.2,
            shadows: [
              Shadow(color: Colors.black54, blurRadius: 12),
            ],
          ),
        ),
        const SizedBox(height: 10),
        Container(
          width: 56,
          height: 3,
          decoration: BoxDecoration(
            color: _gold,
            borderRadius: BorderRadius.circular(99),
          ),
        ),
        const SizedBox(height: 10),
        Text(
          tr(ref, 'login_subtitle'),
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Colors.white70,
            fontSize: 13,
            height: 1.8,
          ),
        ),
      ],
    );
  }

  Widget _formCard(ColorScheme cs) {
    return Card(
      elevation: 10,
      shadowColor: Colors.black.withValues(alpha: .45),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(26),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 22, 20, 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: _green.withValues(alpha: .1),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Icon(
                    Icons.login_outlined,
                    color: _green,
                    size: 19,
                  ),
                ),
                const SizedBox(width: 10),
                Text(
                  tr(ref, 'login'),
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),
            _field(
              controller: _userCtrl,
              label: tr(ref, 'username'),
              icon: Icons.person_outline,
              textInputAction: TextInputAction.next,
              errorText: _usernameError,
              onChanged: (_) => setState(() {
                _usernameError = null;
                _error = null;
              }),
            ),
            const SizedBox(height: 12),
            _field(
              controller: _passCtrl,
              label: tr(ref, 'password'),
              icon: Icons.lock_outline,
              obscure: _obscure,
              onSubmitted: (_) => _submit(),
              errorText: _passwordError,
              onChanged: (_) => setState(() {
                _passwordError = null;
                _error = null;
              }),
              suffix: IconButton(
                icon: Icon(
                  _obscure
                      ? Icons.visibility_off_outlined
                      : Icons.visibility_outlined,
                  size: 21,
                ),
                onPressed: () => setState(() => _obscure = !_obscure),
              ),
            ),
            const SizedBox(height: 6),
            InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: _serverSheet,
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  vertical: 10,
                  horizontal: 4,
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.dns_outlined,
                      size: 17,
                      color: cs.onSurfaceVariant,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        ref.watch(baseUrlProvider),
                        textDirection: TextDirection.ltr,
                        textAlign: TextAlign.end,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 12,
                          color: cs.onSurfaceVariant,
                          fontFamily: 'monospace',
                        ),
                      ),
                    ),
                    const SizedBox(width: 4),
                    Icon(
                      Icons.settings_outlined,
                      size: 17,
                      color: cs.onSurfaceVariant,
                    ),
                  ],
                ),
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: cs.errorContainer,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.error_outline,
                      size: 20,
                      color: cs.onErrorContainer,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _error!,
                        style: TextStyle(
                          color: cs.onErrorContainer,
                          fontSize: 13,
                          height: 1.6,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 14),
            SizedBox(
              height: 50,
              child: FilledButton(
                onPressed: _busy ? null : _submit,
                child: _busy
                    ? const SizedBox(
                        width: 21,
                        height: 21,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.2,
                          color: Colors.white,
                        ),
                      )
                    : Text(
                        tr(ref, 'login'),
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _field({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    bool obscure = false,
    Widget? suffix,
    TextInputAction? textInputAction,
    void Function(String)? onSubmitted,
    String? errorText,
    void Function(String)? onChanged,
  }) {
    final cs = Theme.of(context).colorScheme;
    return TextField(
      controller: controller,
      obscureText: obscure,
      textInputAction: textInputAction,
      onSubmitted: onSubmitted,
      onChanged: onChanged,
      style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
      decoration: InputDecoration(
        labelText: label,
        errorText: errorText,
        filled: true,
        fillColor: cs.surfaceContainerHighest.withValues(alpha: .55),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 14,
          vertical: 14,
        ),
        prefixIcon: Container(
          margin: const EdgeInsetsDirectional.only(start: 8, end: 4),
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: _green.withValues(alpha: .12),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, size: 19, color: _green),
        ),
        suffixIcon: suffix,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: cs.outlineVariant),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: cs.outlineVariant),
        ),
        focusedBorder: const OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(10)),
          borderSide: BorderSide(color: _green, width: 1.6),
        ),
      ),
    );
  }
}
