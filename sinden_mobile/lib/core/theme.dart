import 'package:flutter/material.dart';

class AppTheme {
  // Material 3 Brand & Key Color Tokens
  static const Color primaryNavy = Color(0xFF1E3A8A);     // M3 Primary Navy
  static const Color secondaryGold = Color(0xFFD97706);   // M3 Secondary Gold
  static const Color neutralSlate = Color(0xFF475569);    // M3 Neutral Slate
  static const Color surfaceLight = Color(0xFFF8FAFC);    // M3 Surface Container Low
  static const Color surfaceContainer = Color(0xFFF1F5F9); // M3 Surface Container
  static const Color cardLight = Color(0xFFFFFFFF);       // M3 Surface High
  static const Color outlineLight = Color(0xFFE2E8F0);     // M3 Outline Variant

  static ThemeData lightTheme = ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    colorScheme: ColorScheme.fromSeed(
      seedColor: primaryNavy,
      brightness: Brightness.light,
      primary: primaryNavy,
      onPrimary: Colors.white,
      primaryContainer: const Color(0xFFE0E7FF),
      onPrimaryContainer: const Color(0xFF1E1B4B),
      secondary: secondaryGold,
      onSecondary: Colors.white,
      secondaryContainer: const Color(0xFFFEF3C7),
      onSecondaryContainer: const Color(0xFF78350F),
      tertiary: neutralSlate,
      surface: surfaceLight,
      onSurface: const Color(0xFF0F172A),
      surfaceContainerHighest: surfaceContainer,
      outline: outlineLight,
      outlineVariant: const Color(0xFFCBD5E1),
    ),
    scaffoldBackgroundColor: surfaceLight,
    appBarTheme: const AppBarTheme(
      elevation: 0,
      scrolledUnderElevation: 1.0,
      backgroundColor: Colors.white,
      foregroundColor: Color(0xFF0F172A),
      centerTitle: false,
      titleTextStyle: TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w800,
        color: Color(0xFF0F172A),
        letterSpacing: 0.2,
      ),
    ),
    cardTheme: CardTheme(
      elevation: 0,
      color: cardLight,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20), // M3 Medium Corner Radius
        side: const BorderSide(color: outlineLight, width: 1),
      ),
      margin: const EdgeInsets.symmetric(vertical: 6),
    ),
    navigationBarTheme: NavigationBarThemeData(
      elevation: 1,
      backgroundColor: Colors.white,
      indicatorColor: const Color(0xFFE0E7FF),
      indicatorShape: const StadiumBorder(), // M3 Stadium Indicator
      labelTextStyle: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.selected)) {
          return const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w800,
            color: primaryNavy,
          );
        }
        return const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: Color(0xFF64748B),
        );
      }),
      iconTheme: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.selected)) {
          return const IconThemeData(color: primaryNavy, size: 22);
        }
        return const IconThemeData(color: Color(0xFF64748B), size: 22);
      }),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: const Color(0xFFF8FAFC),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16), // M3 Filled Outlined Field
        borderSide: const BorderSide(color: outlineLight),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: outlineLight),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: primaryNavy, width: 2),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFFDC2626)),
      ),
      labelStyle: const TextStyle(fontSize: 13, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
      hintStyle: const TextStyle(fontSize: 13, color: Color(0xFF94A3B8)),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: primaryNavy,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)), // M3 Button Radius
        textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800, letterSpacing: 0.3),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: primaryNavy,
        side: const BorderSide(color: outlineLight),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
      ),
    ),
    dialogTheme: DialogTheme(
      elevation: 3,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)), // M3 Dialog Radius
      backgroundColor: Colors.white,
    ),
    splashFactory: InkSparkle.splashFactory, // Google M3 Dynamic InkSparkle Touch Motion
    pageTransitionsTheme: const PageTransitionsTheme(
      builders: {
        TargetPlatform.android: ZoomPageTransitionsBuilder(), // Google M3 Predictive Zoom Page Motion
        TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
      },
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(
      color: primaryNavy,
      circularTrackColor: Color(0xFFE2E8F0), // M3 Surface Track
      linearTrackColor: Color(0xFFE2E8F0),
      strokeWidth: 3.5,
    ),
    dividerTheme: const DividerThemeData(
      color: outlineLight,
      thickness: 1,
      space: 1,
    ),
  );
}