package id.my.sisinden.app.core.ui.theme

import android.app.Activity
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.LocalContentColor
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.unit.Density
import androidx.core.view.WindowCompat

/** Preferensi tema yang disimpan pengguna. */
enum class ThemeMode { SYSTEM, LIGHT, DARK }

/** Preferensi ukuran teks ("mode mudah dibaca"), dikalikan dengan setelan huruf sistem. */
enum class TextScale(val factor: Float, val label: String) {
    NORMAL(1f, "Normal"),
    LARGE(1.15f, "Besar"),
    EXTRA_LARGE(1.3f, "Sangat besar"),
}

// ===== Skema Nova (sama dengan web; PRD v1.1). Lihat Nova di Color.kt. =====
private val LightColors = lightColorScheme(
    primary = Nova.Accent, // #2563EB, teks putih 5,2:1
    onPrimary = Color.White,
    primaryContainer = Nova.AccentSoft,
    onPrimaryContainer = Color(0xFF1E3A8A),
    inversePrimary = Nova.NightAccent,
    secondary = Color(0xFF15803D),
    onSecondary = Color.White,
    secondaryContainer = Color(0xFFDCFCE7),
    onSecondaryContainer = Color(0xFF14532D),
    tertiary = Nova.Violet,
    onTertiary = Color.White,
    tertiaryContainer = Color(0xFFEDE9FE),
    onTertiaryContainer = Color(0xFF4C1D95),
    background = Nova.Canvas,
    onBackground = Nova.Fg,
    surface = Nova.Surface,
    onSurface = Nova.Fg,
    surfaceVariant = Nova.Subtle,
    onSurfaceVariant = Nova.FgMuted,
    surfaceTint = Nova.Accent,
    inverseSurface = Color(0xFF18181B),
    inverseOnSurface = Nova.Canvas,
    error = Color(0xFFDC2626),
    onError = Color.White,
    errorContainer = Color(0xFFFEE2E2),
    onErrorContainer = Color(0xFF7F1D1D),
    outline = Nova.BorderInput, // garis field ≥ 3:1
    outlineVariant = Nova.Border,
    scrim = Color.Black,
    surfaceBright = Color.White,
    surfaceDim = Color(0xFFE4E4E7),
    surfaceContainerLowest = Color.White,
    surfaceContainerLow = Color(0xFFFCFCFD),
    surfaceContainer = Nova.Subtle,
    surfaceContainerHigh = Color(0xFFEDEDF0),
    surfaceContainerHighest = Color(0xFFE4E4E7),
)

private val DarkColors = darkColorScheme(
    primary = Nova.NightAccent, // #60A5FA, 7,3:1 di atas permukaan navy
    onPrimary = Nova.NightSurface,
    primaryContainer = Nova.NightAccentSoft,
    onPrimaryContainer = Nova.NightOnSoft,
    inversePrimary = Nova.Accent,
    secondary = Color(0xFF4ADE80),
    onSecondary = Color(0xFF052E16),
    secondaryContainer = Color(0xFF0F2E22),
    onSecondaryContainer = Color(0xFFBBF7D0),
    tertiary = Color(0xFFA78BFA),
    onTertiary = Color(0xFF1E1048),
    tertiaryContainer = Color(0xFF2E1F5E),
    onTertiaryContainer = Color(0xFFDDD6FE),
    // Navy Login: latar #050B1A, permukaan bertingkat agar kartu/field terpisah jelas
    background = Nova.NightCanvas,
    onBackground = Nova.NightFg,
    surface = Nova.NightSurface,
    onSurface = Nova.NightFg,
    surfaceVariant = Nova.NightSubtle,
    onSurfaceVariant = Nova.NightFgMuted,
    surfaceTint = Nova.NightAccent,
    inverseSurface = Nova.NightFg,
    inverseOnSurface = Nova.NightCanvas,
    error = Color(0xFFF87171),
    onError = Color(0xFF450A0A),
    errorContainer = Color(0xFF3B1219),
    onErrorContainer = Color(0xFFFECACA),
    outline = Nova.NightBorderInput,
    outlineVariant = Nova.NightBorder,
    scrim = Color.Black,
    surfaceBright = Nova.NightRaised,
    surfaceDim = Nova.NightCanvas,
    surfaceContainerLowest = Color(0xFF040915),
    surfaceContainerLow = Color(0xFF09101F),
    surfaceContainer = Nova.NightSurface,
    surfaceContainerHigh = Nova.NightSubtle,
    surfaceContainerHighest = Nova.NightRaised,
)

/**
 * Token "Liquid Glass": isi tembus pandang + garis tepi bergradien (atas
 * lebih terang) + bayangan lembut. Di mode terang kaca lebih pekat agar
 * kontras teks tetap ≥ 4,5:1 sesuai prinsip ramah lansia.
 */
@Immutable
data class GlassColors(
    val fill: Color,
    val fillStrong: Color,
    val edgeTop: Color,
    val edgeBottom: Color,
    val shadow: Color,
    val glowA: Color,
    val glowB: Color,
    val glowC: Color,
)

// Kaca terang Nova: putih tembus di atas aurora pastel (sama dengan web).
private val LightGlass = GlassColors(
    fill = Color.White.copy(alpha = 0.74f),
    fillStrong = Color.White.copy(alpha = 0.9f),
    edgeTop = Color.White,
    edgeBottom = Color.White.copy(alpha = 0.35f),
    shadow = Color(0xFF0F172A).copy(alpha = 0.14f),
    glowA = Color(0xFF93C5FD).copy(alpha = 0.45f), // biru pastel
    glowB = Color(0xFFA5B4FC).copy(alpha = 0.38f), // indigo pastel
    glowC = Color(0xFF6EE7B7).copy(alpha = 0.26f), // hijau mint
)

// Kaca gelap Nova = kartu kaca halaman Login (navy tembus + garis cahaya tipis),
// di atas aurora biru/indigo/teal yang redup.
private val DarkGlass = GlassColors(
    fill = Color(0xFF162040).copy(alpha = 0.78f),
    fillStrong = Color(0xFF111A33).copy(alpha = 0.94f),
    edgeTop = Color(0xFF93C5FD).copy(alpha = 0.28f),
    edgeBottom = Color.White.copy(alpha = 0.04f),
    shadow = Color.Black.copy(alpha = 0.65f),
    glowA = Nova.AuroraBlue.copy(alpha = 0.32f),
    glowB = Nova.AuroraIndigo.copy(alpha = 0.24f),
    glowC = Nova.AuroraTeal.copy(alpha = 0.14f),
)

private val LocalStatusColors = staticCompositionLocalOf { LightStatusColors }
private val LocalGlassColors = staticCompositionLocalOf { LightGlass }
private val LocalIsDark = staticCompositionLocalOf { false }

/** Akses cepat token desain SISINDEN yang tidak tersedia di MaterialTheme. */
object SindenTheme {
    val status: StatusColors
        @Composable @ReadOnlyComposable get() = LocalStatusColors.current

    val glass: GlassColors
        @Composable @ReadOnlyComposable get() = LocalGlassColors.current

    val isDark: Boolean
        @Composable @ReadOnlyComposable get() = LocalIsDark.current

    /** Warna gradien header/hero. */
    val heroGradient: List<Color>
        @Composable @ReadOnlyComposable get() = if (LocalIsDark.current) HeroGradientDark else HeroGradientLight
}

@Composable
fun SindenTheme(
    themeMode: ThemeMode = ThemeMode.SYSTEM,
    textScale: TextScale = TextScale.NORMAL,
    content: @Composable () -> Unit,
) {
    val dark = when (themeMode) {
        ThemeMode.SYSTEM -> isSystemInDarkTheme()
        ThemeMode.LIGHT -> false
        ThemeMode.DARK -> true
    }
    val colors = if (dark) DarkColors else LightColors

    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as? Activity)?.window
            if (window != null) {
                val controller = WindowCompat.getInsetsController(window, view)
                // Bagian atas setiap layar berwarna biru (header Livin) → ikon status putih.
                controller.isAppearanceLightStatusBars = false
                controller.isAppearanceLightNavigationBars = !dark
            }
        }
    }

    val density = LocalDensity.current
    val scaledDensity = Density(density.density, density.fontScale * textScale.factor)

    CompositionLocalProvider(
        LocalStatusColors provides if (dark) DarkStatusColors else LightStatusColors,
        LocalGlassColors provides if (dark) DarkGlass else LightGlass,
        LocalIsDark provides dark,
        LocalDensity provides scaledDensity,
    ) {
        MaterialTheme(
            colorScheme = colors,
            typography = SindenTypography,
            shapes = SindenShapes,
        ) {
            // Warna teks/ikon bawaan mengikuti tema. Tanpa ini, Text di luar Surface
            // (kartu kaca, kolom ber-background) jatuh ke hitam → tak terbaca di mode gelap.
            CompositionLocalProvider(LocalContentColor provides colors.onBackground, content = content)
        }
    }
}
