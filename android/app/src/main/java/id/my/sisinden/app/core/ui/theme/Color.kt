package id.my.sisinden.app.core.ui.theme

import androidx.compose.runtime.Immutable
import androidx.compose.ui.graphics.Color

/*
 * Palet "Livin Glass" SISINDEN v1.2
 *
 * Perpaduan dua referensi Figma:
 *  - Elder Friendly Livin' (Mandiri redesign): biru #2B93FE, putih bersih,
 *    abu #333 untuk teks, ubin fitur bergradien, kontras minimal 4,5:1.
 *  - iOS 26 Liquid Glass: permukaan kaca tembus pandang, garis tepi terang,
 *    cahaya lembut di latar, tab bar mengambang.
 *
 * Biru Livin asli (#2B93FE) hanya 3,1:1 terhadap putih, jadi dipakai untuk
 * gradien/dekorasi. Teks, tautan, dan tombol memakai [Blue600] (4,9:1).
 */

// Biru Livin
val Blue50 = Color(0xFFE6F2FF)
val Blue100 = Color(0xFFCCE5FF)
val Blue200 = Color(0xFF9ECEFF)
val Blue300 = Color(0xFF6BB4FF)
val Blue400 = Color(0xFF4BA3FE)
val Blue500 = Color(0xFF2B93FE) // Foundation/Blue/blue-500 (dekoratif)
val Blue600 = Color(0xFF0F6FD6) // primer aksesibel
val Blue700 = Color(0xFF0A5CB8)
val Blue800 = Color(0xFF08498F)
val Blue900 = Color(0xFF062F5E)

// Abu bergaya iOS (sedikit kebiruan agar serasi dengan aurora)
val Grey50 = Color(0xFFE9ECF2)
val Grey100 = Color(0xFFC3C9D4)
val Grey200 = Color(0xFFA2A9B6)
val Grey300 = Color(0xFF747B88)
val Grey400 = Color(0xFF5A6170) // teks sekunder (6,1:1 di atas putih)
val Grey500 = Color(0xFF1C2230) // teks utama (lebih pekat, gaya label iOS)
val Line = Color(0xFFE6E9F0) // garis rambut kartu
val Wash = Color(0xFFF4F6FA) // latar bar info di dalam kartu
val Canvas = Color(0xFFF2F4F9) // latar aplikasi terang ("grouped background" iOS)

// Malam (iOS dark: hitam pekat + permukaan abu bertingkat)
val Night950 = Color(0xFF000000)
val Night900 = Color(0xFF05060A)
val Night850 = Color(0xFF111217)
val Night800 = Color(0xFF1C1D22)
val Night700 = Color(0xFF2B2C33)
val Night600 = Color(0xFF3B3C44)
val Night400 = Color(0xFF9A9BA6)
val Night200 = Color(0xFFD6D7DE)
val Night100 = Color(0xFFF4F4F8)

// Aurora (latar bergerak & aksen modern)
val Indigo500 = Color(0xFF6366F1)
val Indigo600 = Color(0xFF4F46E5)
val Cyan400 = Color(0xFF22D3EE)
val Pink400 = Color(0xFFF472B6)

// Aksen
val Teal500 = Color(0xFF16C79A)
val Teal700 = Color(0xFF0B7A5F)
val Teal100 = Color(0xFFD5F7EC)
val Orange500 = Color(0xFFFF8A1F)
val Orange700 = Color(0xFFB45300)
val Orange100 = Color(0xFFFFEBD6)
val Violet400 = Color(0xFF9B87F5)
val Red500 = Color(0xFFD93A3A)
val Red300 = Color(0xFFFF8A80)
val Red100 = Color(0xFFFDE3E3)
val Red900 = Color(0xFF5C1111)

/*
 * ===== Token "Nova" (docs/PRD_REDESIGN_UIUX.md v1.1, sama dengan web) =====
 * Terang: zinc netral + satu aksen biru #2563EB.
 * Gelap: estetika halaman Login: navy malam #050B1A + kaca navy.
 * Semua pasangan teks diukur ≥ 4,5:1.
 */
object Nova {
    // Terang
    val Canvas = Color(0xFFFAFAFA)
    val Surface = Color(0xFFFFFFFF)
    val Subtle = Color(0xFFF4F4F5)
    val Border = Color(0xFFE4E4E7)
    val BorderInput = Color(0xFF8E8E96)
    val Fg = Color(0xFF09090B)
    val FgMuted = Color(0xFF52525B)
    val FgSubtle = Color(0xFF6B6B74)
    val Accent = Color(0xFF2563EB)
    val AccentSoft = Color(0xFFEFF6FF)
    val Violet = Color(0xFF8B5CF6)

    // Gelap (navy Login)
    val NightCanvas = Color(0xFF050B1A)
    val NightSurface = Color(0xFF0B1224)
    val NightSubtle = Color(0xFF111A33)
    val NightRaised = Color(0xFF18223D)
    val NightBorder = Color(0xFF1E2A44)
    val NightBorderInput = Color(0xFF56658A)
    val NightFg = Color(0xFFF1F5F9)
    val NightFgMuted = Color(0xFFA8B3C7)
    val NightFgSubtle = Color(0xFF8A96AD)
    val NightAccent = Color(0xFF60A5FA)
    val NightAccentSoft = Color(0xFF172554)
    val NightOnSoft = Color(0xFF93C5FD)

    // Aurora halaman Login
    val AuroraBlue = Color(0xFF1D4ED8)
    val AuroraIndigo = Color(0xFF4F46E5)
    val AuroraTeal = Color(0xFF0D9488)
}

/**
 * Gradien hero/header Nova: biru → indigo (sama dengan kartu "Absensi hari ini"
 * di Figma). Teks putih ≥ 5:1 di seluruh rentang.
 */
val HeroGradientLight = listOf(Color(0xFF1D4ED8), Color(0xFF2563EB), Indigo600)
// Gelap: navy → indigo tua, senada latar Login.
val HeroGradientDark = listOf(Color(0xFF1E3A8A), Color(0xFF1E2A6B), Color(0xFF312E81))

/** Pasangan warna ubin fitur bergradien (atas → bawah), mengikuti kit Livin. */
@Immutable
data class TileGradient(val top: Color, val bottom: Color)

/** Ubin ikon bergaya ikon aplikasi iOS: gradien cerah dari kiri-atas ke kanan-bawah. */
object TilePalette {
    val Teal = TileGradient(Color(0xFF34E0B4), Color(0xFF0FA585))
    val Orange = TileGradient(Color(0xFFFFB347), Color(0xFFFF6A00))
    val Sky = TileGradient(Color(0xFF5BD8FF), Color(0xFF1D9BF0))
    val Lime = TileGradient(Color(0xFFC6E36B), Color(0xFF7FB024))
    val Blue = TileGradient(Color(0xFF63A8FF), Color(0xFF2357E8))
    val Violet = TileGradient(Color(0xFFB794FF), Color(0xFF6D3FF0))
    val Rose = TileGradient(Color(0xFFFF8FA3), Color(0xFFF0365A))
    val Slate = TileGradient(Color(0xFF9AA6BF), Color(0xFF55617C))
}

/** Warna status semantik (sukses/peringatan/bahaya/info/netral) untuk chip & badge. */
@Immutable
data class StatusColors(
    val success: Color,
    val onSuccessContainer: Color,
    val successContainer: Color,
    val warning: Color,
    val onWarningContainer: Color,
    val warningContainer: Color,
    val danger: Color,
    val onDangerContainer: Color,
    val dangerContainer: Color,
    val info: Color,
    val onInfoContainer: Color,
    val infoContainer: Color,
    val neutral: Color,
    val onNeutralContainer: Color,
    val neutralContainer: Color,
)

// Status Nova: warna teks status selalu varian "-fg" yang ≥ 4,5:1 (audit A1).
val LightStatusColors = StatusColors(
    success = Color(0xFF15803D),
    onSuccessContainer = Color(0xFF14532D),
    successContainer = Color(0xFFDCFCE7),
    warning = Color(0xFFB45309),
    onWarningContainer = Color(0xFF78350F),
    warningContainer = Color(0xFFFEF3C7),
    danger = Color(0xFFDC2626),
    onDangerContainer = Color(0xFF7F1D1D),
    dangerContainer = Color(0xFFFEE2E2),
    info = Color(0xFF1D4ED8),
    onInfoContainer = Color(0xFF1E3A8A),
    infoContainer = Nova.AccentSoft,
    neutral = Nova.FgMuted,
    onNeutralContainer = Nova.Fg,
    neutralContainer = Nova.Subtle,
)

val DarkStatusColors = StatusColors(
    success = Color(0xFF4ADE80),
    onSuccessContainer = Color(0xFFBBF7D0),
    successContainer = Color(0xFF0F2E22),
    warning = Color(0xFFFBBF24),
    onWarningContainer = Color(0xFFFDE68A),
    warningContainer = Color(0xFF33260A),
    danger = Color(0xFFF87171),
    onDangerContainer = Color(0xFFFECACA),
    dangerContainer = Color(0xFF3B1219),
    info = Nova.NightOnSoft,
    onInfoContainer = Color(0xFFDBEAFE),
    infoContainer = Nova.NightAccentSoft,
    neutral = Nova.NightFgMuted,
    onNeutralContainer = Nova.NightFg,
    neutralContainer = Nova.NightRaised,
)
