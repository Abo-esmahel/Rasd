import 'package:flutter/material.dart';

class CalmPageTransition extends PageTransitionsBuilder {
  const CalmPageTransition();

  @override
  Duration get transitionDuration => const Duration(milliseconds: 380);

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    final curve = CurvedAnimation(
      parent: animation,
      curve: Curves.easeOutCubic,
      reverseCurve: Curves.easeInCubic,
    );
    return FadeTransition(
      opacity: curve,
      child: SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(0.06, 0),
          end: Offset.zero,
        ).animate(curve),
        child: child,
      ),
    );
  }
}

class RasdColors {
  static const green = Color(0xFF0E6A38);
  static const greenDark = Color(0xFF0A4D28);
  static const greenSoft = Color(0xFFE9F4EC);
  static const gold = Color(0xFFD9AB3C);
  static const goldSoft = Color(0xFFFEF6E0);
  static const goldInk = Color(0xFF92600A);

  static const bg = Color(0xFFF3F1E9);
  static const card = Color(0xFFFFFEFA);
  static const muted = Color(0xFFF6F4ED);
  static const border = Color(0xFFE3DFD3);
  static const borderStrong = Color(0xFFD2CCBC);
  static const ink = Color(0xFF20362B);
  static const ink2 = Color(0xFF58675C);
  static const ink3 = Color(0xFF7E897E);

  static const darkBg = Color(0xFF1E2320);
  static const darkCard = Color(0xFF252B26);
  static const darkMuted = Color(0xFF2A302B);
  static const darkBorder = Color(0xFF2E352E);
  static const darkInk = Color(0xFFE7ECE5);
  static const darkInk2 = Color(0xFF9BB0A0);
  static const darkGreen = Color(0xFF4ADE80);
  static const darkGreenSoft = Color(0xFF1E3328);

  static const danger = Color(0xFFB42323);
  static const dangerSoft = Color(0xFFFDEEEE);

  static Color adaptive(BuildContext context, Color light) {
    if (Theme.of(context).brightness != Brightness.dark) {
      return light;
    }
    if (light == RasdColors.green) return RasdColors.darkGreen;
    if (light == RasdColors.greenDark) return RasdColors.darkGreen;
    if (light == RasdColors.goldInk) return const Color(0xFFF0C040);
    if (light == RasdColors.gold) return const Color(0xFFF0C040);
    if (light == RasdColors.danger) return const Color(0xFFF08080);
    if (light.toARGB32() == Colors.blue.shade700.toARGB32()) {
      return const Color(0xFF90CAF9);
    }
    if (light.toARGB32() == Colors.amber.shade700.toARGB32()) {
      return const Color(0xFFF0C040);
    }
    return light;
  }
}

class RasdRadii {
  static const card = 14.0;
  static const block = 14.0;
  static const input = 10.0;
  static const btn = 10.0;
  static const chip = 999.0;
}

class RasdTheme {
  static const green = RasdColors.green;
  static const greenDark = RasdColors.greenDark;

  static ThemeData? _lightCache;
  static ThemeData? _darkCache;
  static ThemeData lightTheme() => _lightCache ??= light();
  static ThemeData darkTheme() => _darkCache ??= dark();

  static ThemeData light() {
    const cs = ColorScheme(
      brightness: Brightness.light,
      primary: RasdColors.green,
      onPrimary: Colors.white,
      primaryContainer: RasdColors.greenSoft,
      onPrimaryContainer: RasdColors.greenDark,
      secondary: RasdColors.gold,
      onSecondary: Colors.white,
      secondaryContainer: RasdColors.goldSoft,
      onSecondaryContainer: RasdColors.goldInk,
      tertiary: RasdColors.gold,
      error: RasdColors.danger,
      onError: Colors.white,
      errorContainer: RasdColors.dangerSoft,
      onErrorContainer: RasdColors.danger,
      surface: RasdColors.card,
      onSurface: RasdColors.ink,
      surfaceContainerHighest: RasdColors.bg,
      surfaceContainerHigh: RasdColors.muted,
      surfaceContainerLow: RasdColors.card,
      onSurfaceVariant: RasdColors.ink2,
      outline: RasdColors.border,
      outlineVariant: RasdColors.borderStrong,
      shadow: Color(0x2B1A2E1F),
      scrim: Colors.black54,
      inverseSurface: RasdColors.ink,
      onInverseSurface: Colors.white,
      inversePrimary: RasdColors.darkGreen,
      surfaceTint: RasdColors.green,
    );
    return ThemeData(
      useMaterial3: true,
      colorScheme: cs,
      scaffoldBackgroundColor: RasdColors.bg,
      appBarTheme: const AppBarTheme(
        centerTitle: true,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: RasdColors.card,
        foregroundColor: RasdColors.ink,
        titleTextStyle: TextStyle(
          color: RasdColors.ink,
          fontSize: 18,
          fontWeight: FontWeight.w800,
          height: 1.4,
        ),
      ),
      cardTheme: CardThemeData(
        color: RasdColors.card,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RasdRadii.card),
          side: const BorderSide(color: RasdColors.border),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: RasdColors.green,
          foregroundColor: Colors.white,
          minimumSize: const Size(64, 52),
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            height: 1.5,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RasdRadii.btn),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(64, 52),
          textStyle: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.bold,
            height: 1.5,
          ),
          side: const BorderSide(color: RasdColors.borderStrong),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RasdRadii.btn),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: RasdColors.green,
          textStyle: const TextStyle(fontWeight: FontWeight.bold),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: RasdColors.card,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 15,
        ),
        hintStyle: const TextStyle(color: RasdColors.ink3, fontSize: 14),
        labelStyle: const TextStyle(color: RasdColors.ink2, fontSize: 13),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RasdRadii.input),
          borderSide: const BorderSide(color: RasdColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RasdRadii.input),
          borderSide: const BorderSide(color: RasdColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RasdRadii.input),
          borderSide: const BorderSide(color: RasdColors.green, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RasdRadii.input),
          borderSide: const BorderSide(color: RasdColors.danger),
        ),
      ),
      chipTheme: ChipThemeData(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RasdRadii.chip),
          side: const BorderSide(color: RasdColors.border),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: RasdColors.card,
        elevation: 8,
        indicatorColor: RasdColors.greenSoft,
        iconTheme: WidgetStateProperty.resolveWith((s) {
          final sel = s.contains(WidgetState.selected);
          return IconThemeData(
            size: 24,
            color: sel ? RasdColors.greenDark : RasdColors.ink2,
          );
        }),
        labelTextStyle: WidgetStateProperty.resolveWith((s) {
          final sel = s.contains(WidgetState.selected);
          return TextStyle(
            fontSize: 11.5,
            fontWeight: sel ? FontWeight.w800 : FontWeight.w600,
            color: sel ? RasdColors.green : RasdColors.ink2,
          );
        }),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: RasdColors.green,
        foregroundColor: Colors.white,
        elevation: 6,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(20)),
        ),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: RasdColors.card,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        showDragHandle: false,
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: RasdColors.card,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: RasdColors.ink,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith(
          (s) => s.contains(WidgetState.selected) ? Colors.white : null,
        ),
        trackColor: WidgetStateProperty.resolveWith(
          (s) => s.contains(WidgetState.selected) ? RasdColors.green : null,
        ),
      ),
      dividerTheme: const DividerThemeData(
        color: RasdColors.border,
        thickness: 1,
        space: 1,
      ),
      listTileTheme: const ListTileThemeData(
        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(14)),
        ),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: RasdColors.green,
      ),
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: CalmPageTransition(),
          TargetPlatform.iOS: CalmPageTransition(),
        },
      ),
      textTheme: const TextTheme(
        titleLarge: TextStyle(
          fontSize: 20,
          fontWeight: FontWeight.w800,
          height: 1.4,
        ),
        titleMedium: TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w800,
          height: 1.5,
        ),
        titleSmall: TextStyle(
          fontSize: 13.5,
          fontWeight: FontWeight.bold,
          height: 1.5,
        ),
        bodyLarge: TextStyle(fontSize: 14.5, height: 1.8),
        bodyMedium: TextStyle(fontSize: 13.5, height: 1.8),
        bodySmall: TextStyle(fontSize: 12, height: 1.7, color: RasdColors.ink2),
        labelLarge: TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.bold,
          height: 1.5,
        ),
      ),
    );
  }

  static ThemeData dark() {
    const cs = ColorScheme(
      brightness: Brightness.dark,
      primary: RasdColors.darkGreen,
      onPrimary: Color(0xFF0B1F14),
      primaryContainer: RasdColors.darkGreenSoft,
      onPrimaryContainer: RasdColors.darkGreen,
      secondary: RasdColors.gold,
      onSecondary: Color(0xFF241A05),
      secondaryContainer: Color(0xFF2E2716),
      onSecondaryContainer: Color(0xFFF0C040),
      tertiary: Color(0xFFF0C040),
      error: Color(0xFFF08080),
      onError: Color(0xFF2D1313),
      errorContainer: Color(0xFF2D1F1F),
      onErrorContainer: Color(0xFFF0A0A0),
      surface: RasdColors.darkCard,
      onSurface: RasdColors.darkInk,
      surfaceContainerHighest: RasdColors.darkBg,
      surfaceContainerHigh: RasdColors.darkMuted,
      surfaceContainerLow: RasdColors.darkCard,
      onSurfaceVariant: RasdColors.darkInk2,
      outline: RasdColors.darkBorder,
      outlineVariant: Color(0xFF343A34),
      shadow: Colors.black54,
      scrim: Colors.black54,
      inverseSurface: RasdColors.darkInk,
      onInverseSurface: RasdColors.darkBg,
      inversePrimary: RasdColors.green,
      surfaceTint: RasdColors.darkGreen,
    );
    final t = light().copyWith(
      colorScheme: cs,
      scaffoldBackgroundColor: RasdColors.darkBg,
      canvasColor: RasdColors.darkBg,
      iconTheme: const IconThemeData(color: RasdColors.darkInk2),
      appBarTheme: const AppBarTheme(
        centerTitle: true,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: RasdColors.darkCard,
        foregroundColor: RasdColors.darkInk,
        titleTextStyle: TextStyle(
          color: RasdColors.darkInk,
          fontSize: 18,
          fontWeight: FontWeight.w800,
          height: 1.4,
        ),
      ),
      cardTheme: CardThemeData(
        color: RasdColors.darkCard,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RasdRadii.card),
          side: const BorderSide(color: RasdColors.darkBorder),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: RasdColors.darkMuted,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 15,
        ),
        labelStyle: const TextStyle(color: RasdColors.darkInk2),
        floatingLabelStyle: const TextStyle(color: RasdColors.darkGreen),
        hintStyle: const TextStyle(color: RasdColors.darkInk2, fontSize: 14),
        helperStyle: const TextStyle(color: RasdColors.darkInk2),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RasdRadii.input),
          borderSide: const BorderSide(color: RasdColors.darkBorder),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RasdRadii.input),
          borderSide: const BorderSide(color: RasdColors.darkBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RasdRadii.input),
          borderSide: const BorderSide(color: RasdColors.darkGreen, width: 1.6),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: RasdColors.darkGreen,
          foregroundColor: const Color(0xFF0B1F14),
          minimumSize: const Size(64, 52),
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            height: 1.5,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RasdRadii.btn),
          ),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: RasdColors.darkCard,
        elevation: 8,
        indicatorColor: RasdColors.darkGreenSoft,
        iconTheme: WidgetStateProperty.resolveWith((s) {
          final selected = s.contains(WidgetState.selected);
          return IconThemeData(
            size: 24,
            color: selected ? RasdColors.darkGreen : RasdColors.darkInk2,
          );
        }),
        labelTextStyle: WidgetStateProperty.resolveWith((s) {
          final selected = s.contains(WidgetState.selected);
          return TextStyle(
            fontSize: 11.5,
            fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
            color: selected ? RasdColors.darkGreen : RasdColors.darkInk2,
          );
        }),
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith(
          (s) => s.contains(WidgetState.selected) ? RasdColors.darkBg : null,
        ),
        trackColor: WidgetStateProperty.resolveWith(
          (s) => s.contains(WidgetState.selected) ? RasdColors.darkGreen : null,
        ),
      ),
      dividerTheme: const DividerThemeData(
        color: RasdColors.darkBorder,
        thickness: 1,
        space: 1,
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: RasdColors.darkGreen),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: RasdColors.darkCard,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        showDragHandle: false,
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: RasdColors.darkCard,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: RasdColors.darkMuted,
        contentTextStyle: const TextStyle(color: RasdColors.darkInk),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
      textTheme: const TextTheme(
        titleLarge: TextStyle(
          color: RasdColors.darkInk,
          fontSize: 20,
          fontWeight: FontWeight.w800,
          height: 1.4,
        ),
        titleMedium: TextStyle(
          color: RasdColors.darkInk,
          fontSize: 16,
          fontWeight: FontWeight.w800,
          height: 1.5,
        ),
        titleSmall: TextStyle(
          color: RasdColors.darkInk,
          fontSize: 13.5,
          fontWeight: FontWeight.bold,
          height: 1.5,
        ),
        bodyLarge: TextStyle(
          color: RasdColors.darkInk,
          fontSize: 14.5,
          height: 1.8,
        ),
        bodyMedium: TextStyle(
          color: RasdColors.darkInk,
          fontSize: 13.5,
          height: 1.8,
        ),
        bodySmall: TextStyle(
          color: RasdColors.darkInk2,
          fontSize: 12,
          height: 1.7,
        ),
        labelLarge: TextStyle(
          color: RasdColors.darkInk,
          fontSize: 13,
          fontWeight: FontWeight.bold,
          height: 1.5,
        ),
      ),
    );
    return t;
  }
}
