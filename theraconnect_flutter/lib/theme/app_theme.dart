import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// App theme mirroring the web dashboard's design tokens
/// (public/css/theraconnect.css): teal brand, slate text, soft neutral
/// surfaces, Inter typeface, 12px radius, gentle shadows.
class AppTheme {
  // Brand — teal
  static const Color teal = Color(0xFF0D6E8A);
  static const Color tealMid = Color(0xFF1A8BA8);
  static const Color tealDark = Color(0xFF0A5670);
  static const Color tealLight = Color(0xFFE0F2F7);

  // Slate (text / dark surfaces)
  static const Color slate = Color(0xFF1A2332);
  static const Color slateMid = Color(0xFF334155);
  static const Color slateLight = Color(0xFF64748B);

  // Neutrals
  static const Color neutral50 = Color(0xFFF8FAFB);
  static const Color neutral100 = Color(0xFFF1F5F9);
  static const Color neutral200 = Color(0xFFE2E8F0);

  // Status
  static const Color red = Color(0xFFDC2626);
  static const Color green = Color(0xFF059669);
  static const Color amber = Color(0xFFD97706);
  static const Color blue = Color(0xFF2563EB);

  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(
      seedColor: teal,
      brightness: Brightness.light,
    ).copyWith(
      primary: teal,
      onPrimary: Colors.white,
      primaryContainer: tealLight,
      onPrimaryContainer: tealDark,
      secondary: tealMid,
      onSecondary: Colors.white,
      surface: Colors.white,
      onSurface: slate,
      surfaceContainerHighest: neutral100,
      error: red,
      onError: Colors.white,
      outline: neutral200,
      outlineVariant: neutral200,
    );

    final baseText = ThemeData(brightness: Brightness.light).textTheme;
    final textTheme = GoogleFonts.interTextTheme(baseText).apply(
      bodyColor: slate,
      displayColor: slate,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: neutral50,
      textTheme: textTheme,
      dividerColor: neutral200,
      dividerTheme: const DividerThemeData(color: neutral200, thickness: 1, space: 1),

      appBarTheme: AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: slate,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        titleTextStyle: GoogleFonts.inter(
          color: slate,
          fontSize: 20,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.02 * 20,
        ),
      ),

      cardTheme: CardThemeData(
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 1,
        shadowColor: Colors.black.withValues(alpha: 0.06),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        clipBehavior: Clip.antiAlias,
      ),

      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: teal,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          textStyle: GoogleFonts.inter(fontWeight: FontWeight.w600),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: teal,
          foregroundColor: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: teal,
          side: const BorderSide(color: neutral200),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: teal),
      ),

      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        indicatorColor: tealLight,
        elevation: 3,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return GoogleFonts.inter(
            fontSize: 12,
            fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
            color: selected ? teal : slateLight,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(color: selected ? tealDark : slateLight);
        }),
      ),

      chipTheme: ChipThemeData(
        backgroundColor: neutral100,
        side: BorderSide.none,
        labelStyle: GoogleFonts.inter(color: slateMid, fontSize: 12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: neutral200),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: neutral200),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: teal, width: 1.6),
        ),
      ),

      listTileTheme: const ListTileThemeData(iconColor: slateMid),
      progressIndicatorTheme: const ProgressIndicatorThemeData(color: teal),
      badgeTheme: const BadgeThemeData(backgroundColor: red),
    );
  }

  /// Dark variant — mirrors the [data-bs-theme="dark"] block in
  /// public/css/theraconnect.css so the mobile app matches the dashboard when
  /// the device is in dark mode. Surfaces use dark slate; the brand teal is
  /// brightened slightly so contrast holds against dark backgrounds. The text
  /// colors lift to light slate (#E2E8F0) for AAA contrast.
  static ThemeData dark() {
    const darkSlate = Color(0xFFE2E8F0);
    const darkSlateMid = Color(0xFFCBD5E1);
    const darkSlateLight = Color(0xFF94A3B8);
    const darkSurface = Color(0xFF1A2332);
    const darkScaffold = Color(0xFF141B26);
    const darkNeutral100 = Color(0xFF1F2A38);
    const darkNeutral200 = Color(0xFF334155);
    const darkTeal = Color(0xFF4FB0CC);
    const darkTealLight = Color(0xFF0A4358); // dark teal-tinted surface for chips/indicators
    const darkTealDark = Color(0xFF4FB0CC);

    final scheme = ColorScheme.fromSeed(
      seedColor: darkTeal,
      brightness: Brightness.dark,
    ).copyWith(
      primary: darkTeal,
      onPrimary: Colors.white,
      primaryContainer: darkTealLight,
      onPrimaryContainer: darkTealDark,
      secondary: darkTeal,
      onSecondary: Colors.white,
      surface: darkSurface,
      onSurface: darkSlate,
      surfaceContainerHighest: darkNeutral100,
      error: const Color(0xFFF87171),
      onError: Colors.white,
      outline: darkNeutral200,
      outlineVariant: darkNeutral200,
    );

    final baseText = ThemeData(brightness: Brightness.dark).textTheme;
    final textTheme = GoogleFonts.interTextTheme(baseText).apply(
      bodyColor: darkSlate,
      displayColor: darkSlate,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: scheme,
      scaffoldBackgroundColor: darkScaffold,
      textTheme: textTheme,
      dividerColor: darkNeutral200,
      dividerTheme: const DividerThemeData(color: darkNeutral200, thickness: 1, space: 1),

      appBarTheme: AppBarTheme(
        backgroundColor: darkSurface,
        foregroundColor: darkSlate,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        titleTextStyle: GoogleFonts.inter(
          color: darkSlate,
          fontSize: 20,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.02 * 20,
        ),
      ),

      cardTheme: CardThemeData(
        color: darkSurface,
        surfaceTintColor: Colors.transparent,
        elevation: 1,
        shadowColor: Colors.black.withValues(alpha: 0.35),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        clipBehavior: Clip.antiAlias,
      ),

      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: darkTeal,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          textStyle: GoogleFonts.inter(fontWeight: FontWeight.w600),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: darkTeal,
          foregroundColor: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: darkTeal,
          side: const BorderSide(color: darkNeutral200),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: darkTeal),
      ),

      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: darkSurface,
        surfaceTintColor: Colors.transparent,
        indicatorColor: darkTealLight,
        elevation: 3,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return GoogleFonts.inter(
            fontSize: 12,
            fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
            color: selected ? darkTeal : darkSlateLight,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(color: selected ? darkTeal : darkSlateLight);
        }),
      ),

      chipTheme: ChipThemeData(
        backgroundColor: darkNeutral100,
        side: BorderSide.none,
        labelStyle: GoogleFonts.inter(color: darkSlateMid, fontSize: 12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: darkSurface,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: darkNeutral200),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: darkNeutral200),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: darkTeal, width: 1.6),
        ),
      ),

      listTileTheme: const ListTileThemeData(iconColor: darkSlateMid),
      progressIndicatorTheme: const ProgressIndicatorThemeData(color: darkTeal),
      badgeTheme: const BadgeThemeData(backgroundColor: Color(0xFFF87171)),
    );
  }
}
