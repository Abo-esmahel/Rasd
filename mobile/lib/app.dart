import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/api/api_exception.dart';
import 'core/bootstrap.dart';
import 'core/theme.dart';
import 'core/validators.dart';
import 'features/auth/auth_provider.dart';
import 'features/auth/biometric_gate.dart';
import 'features/auth/login_page.dart';
import 'features/auth/splash_page.dart';
import 'features/home/home_shell.dart';
import 'features/home/profile_page.dart';
import 'core/strings.dart';

class RasdApp extends ConsumerWidget {
  const RasdApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final lang = ref.watch(localeProvider);
    final theme = ref.watch(themeModeProvider);
    V.lang = lang;
    ApiLang.lang = lang;
    final dark = theme == ThemeMode.dark ||
        (theme == ThemeMode.system &&
            MediaQuery.platformBrightnessOf(context) ==
                Brightness.dark);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness:
            dark ? Brightness.light : Brightness.dark,
        systemNavigationBarColor:
            dark ? const Color(0xFF1E2320) : Colors.white,
        systemNavigationBarIconBrightness:
            dark ? Brightness.light : Brightness.dark,
      ),
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        locale: Locale(lang),
        supportedLocales: const [Locale('ar'), Locale('en')],
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        theme: RasdTheme.lightTheme(),
        darkTheme: RasdTheme.darkTheme(),
        themeMode: theme,
        themeAnimationDuration: const Duration(milliseconds: 120),
        themeAnimationCurve: Curves.easeOut,
        home: const _BootGate(),
      ),
    );
  }
}

class _BootGate extends ConsumerWidget {
  const _BootGate();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final boot = ref.watch(bootstrapProvider);
    if (!boot.finished) return const SplashPage();
    final auth = ref.watch(authProvider);
    return auth.when(
      loading: () => const SplashPage(),
      error: (_, __) => const LoginPage(),
      data: (user) => user == null
          ? const LoginPage()
          : const BiometricGate(child: HomeShell()),
    );
  }
}
