import 'package:flutter/material.dart';

class AppTheme {
  // =========================================================================
  // MATERIAL DESIGN 3 (M3) CORE COLOR SYSTEM TOKENS (Official Guide August 2026)
  // =========================================================================
  static const Color primaryNavy = Color(0xFF1E3A8A);       // M3 Primary
  static const Color onPrimary = Color(0xFFFFFFFF);         // M3 On-Primary
  static const Color primaryContainer = Color(0xFFDBEAFE);  // M3 Primary Container
  static const Color onPrimaryContainer = Color(0xFF172554);// M3 On-Primary Container

  static const Color secondaryGold = Color(0xFFD97706);     // M3 Secondary
  static const Color onSecondary = Color(0xFFFFFFFF);       // M3 On-Secondary
  static const Color secondaryContainer = Color(0xFFFEF3C7);// M3 Secondary Container
  static const Color onSecondaryContainer = Color(0xFF78350F);// M3 On-Secondary Container

  static const Color tertiaryTeal = Color(0xFF0D9488);      // M3 Tertiary
  static const Color onTertiary = Color(0xFFFFFFFF);        // M3 On-Tertiary
  static const Color tertiaryContainer = Color(0xFFCCFBF1); // M3 Tertiary Container

  static const Color errorRed = Color(0xFFDC2626);          // M3 Error
  static const Color onError = Color(0xFFFFFFFF);           // M3 On-Error
  static const Color errorContainer = Color(0xFFFEE2E2);     // M3 Error Container
  static const Color onErrorContainer = Color(0xFF7F1D1D);  // M3 On-Error Container

  // M3 Surface & Neutral Hierarchy
  static const Color surfaceLight = Color(0xFFF8FAFC);      // M3 Surface Container Low
  static const Color surfaceContainer = Color(0xFFF1F5F9);  // M3 Surface Container
  static const Color surfaceContainerHigh = Color(0xFFE2E8F0); // M3 Surface Container High
  static const Color cardLight = Color(0xFFFFFFFF);         // M3 Surface / Card
  static const Color onSurface = Color(0xFF0F172A);         // M3 On-Surface
  static const Color onSurfaceVariant = Color(0xFF64748B);  // M3 On-Surface Variant
  static const Color outlineLight = Color(0xFFE2E8F0);      // M3 Outline
  static const Color outlineVariant = Color(0xFFCBD5E1);    // M3 Outline Variant

  // =========================================================================
  // M3 SHAPE SYSTEM TOKENS (Chapter 5: 4dp Small, 16dp Medium, 28dp Large)
  // =========================================================================
  static const double shapeSmall = 8.0;   // Chips, small buttons, badges
  static const double shapeMedium = 16.0; // Cards, input fields, action buttons
  static const double shapeLarge = 28.0;  // Dialogs, bottom sheets, extended cards
  static const double touchTargetMin = 48.0; // Chapter 11: Minimum Touch Target Size

  // =========================================================================
  // M3 THEMEDATA CONFIGURATION
  // =========================================================================
  static ThemeData lightTheme = ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    splashFactory: InkSparkle.splashFactory, // M3 Dynamic Ripple

    colorScheme: ColorScheme.fromSeed(
      seedColor: primaryNavy,
      brightness: Brightness.light,
      primary: primaryNavy,
      onPrimary: onPrimary,
      primaryContainer: primaryContainer,
      onPrimaryContainer: onPrimaryContainer,
      secondary: secondaryGold,
      onSecondary: onSecondary,
      secondaryContainer: secondaryContainer,
      onSecondaryContainer: onSecondaryContainer,
      tertiary: tertiaryTeal,
      onTertiary: onTertiary,
      tertiaryContainer: tertiaryContainer,
      error: errorRed,
      onError: onError,
      errorContainer: errorContainer,
      onErrorContainer: onErrorContainer,
      surface: surfaceLight,
      onSurface: onSurface,
      onSurfaceVariant: onSurfaceVariant,
      outline: outlineLight,
      outlineVariant: outlineVariant,
    ),

    scaffoldBackgroundColor: surfaceLight,

    // M3 Top App Bar
    appBarTheme: const AppBarTheme(
      elevation: 0,
      scrolledUnderElevation: 1.0,
      backgroundColor: Colors.white,
      foregroundColor: onSurface,
      centerTitle: false,
      titleTextStyle: TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w900,
        color: onSurface,
        letterSpacing: 0.2,
      ),
    ),

    // M3 Cards (Chapter 8: Shape 16dp, 1dp outline, Elevation Level 0-1)
    cardTheme: CardTheme(
      elevation: 0,
      color: cardLight,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(shapeMedium),
        side: const BorderSide(color: outlineLight, width: 1),
      ),
      margin: const EdgeInsets.symmetric(vertical: 6),
    ),

    // M3 Bottom Navigation Bar (Chapter 8: Stadium Indicator, Tonal Surface)
    navigationBarTheme: NavigationBarThemeData(
      elevation: 1,
      backgroundColor: Colors.white,
      indicatorColor: primaryContainer,
      indicatorShape: const StadiumBorder(),
      labelTextStyle: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.selected)) {
          return const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w900,
            color: primaryNavy,
          );
        }
        return const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: onSurfaceVariant,
        );
      }),
      iconTheme: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.selected)) {
          return const IconThemeData(color: primaryNavy, size: 22);
        }
        return const IconThemeData(color: onSurfaceVariant, size: 22);
      }),
    ),

    // M3 Floating Action Button (Chapter 8: 16dp Medium Shape, Extended)
    floatingActionButtonTheme: FloatingActionButtonThemeData(
      backgroundColor: primaryNavy,
      foregroundColor: Colors.white,
      elevation: 3,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(shapeMedium),
      ),
    ),

    // M3 Buttons (Chapter 8: Contained, Outlined, 48dp Touch Target)
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        elevation: 1,
        backgroundColor: primaryNavy,
        foregroundColor: Colors.white,
        minimumSize: const Size(touchTargetMin, touchTargetMin),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
        ),
        textStyle: const TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.4,
        ),
      ),
    ),

    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: primaryNavy,
        minimumSize: const Size(touchTargetMin, touchTargetMin),
        side: const BorderSide(color: outlineVariant, width: 1.2),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
        ),
        textStyle: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.3,
        ),
      ),
    ),

    // M3 Dialogs (Chapter 8: 28dp Large Shape)
    dialogTheme: DialogTheme(
      backgroundColor: cardLight,
      elevation: 6,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(shapeLarge),
      ),
      titleTextStyle: const TextStyle(
        fontSize: 18,
        fontWeight: FontWeight.w900,
        color: onSurface,
      ),
    ),

    // M3 Bottom Sheets (Chapter 8: 28dp Top Radius)
    bottomSheetTheme: const BottomSheetThemeData(
      backgroundColor: Colors.white,
      elevation: 8,
      showDragHandle: true,
      dragHandleColor: outlineVariant,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(shapeLarge)),
      ),
    ),

    // M3 Input Fields (Chapter 8: Filled with Outlined Borders)
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: const Color(0xFFF8FAFC),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: outlineLight),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: outlineLight),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: primaryNavy, width: 2),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: errorRed),
      ),
      labelStyle: const TextStyle(fontSize: 13, color: onSurfaceVariant, fontWeight: FontWeight.w600),
      hintStyle: const TextStyle(fontSize: 12, color: onSurfaceVariant),
    ),

    // M3 Page Transitions (Chapter 7: Motion Specification)
    pageTransitionsTheme: const PageTransitionsTheme(
      builders: {
        TargetPlatform.android: ZoomPageTransitionsBuilder(),
        TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
      },
    ),
  );
}