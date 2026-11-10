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

/**
 * Gradien hero/header "Aurora": biru tua → biru SINDEN → indigo. Teks putih
 * di atasnya tetap ≥ 4,5:1 di seluruh rentang gradien.
 */
val HeroGradientLight = listOf(Color(0xFF0A3A9E), Color(0xFF1D5FE0), Indigo600)
// Gelap: biru tua → indigo redup (tidak memudar ke hitam agar bentuk header tetap terlihat)
val HeroGradientDark = listOf(Color(0xFF0B2560), Color(0xFF1B2470), Color(0xFF2A1F68))

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

val LightStatusColors = StatusColors(
    success = Color(0xFF0E8A63),
    onSuccessContainer = Color(0xFF064A35),
    successContainer = Teal100,
    warning = Orange700,
    onWarningContainer = Color(0xFF5A2B00),
    warningContainer = Orange100,
    danger = Color(0xFFC62828),
    onDangerContainer = Red900,
    dangerContainer = Red100,
    info = Blue600,
    onInfoContainer = Blue900,
    infoContainer = Blue50,
    neutral = Grey400,
    onNeutralContainer = Grey500,
    neutralContainer = Color(0xFFF0F2F5),
)

val DarkStatusColors = StatusColors(
    success = Color(0xFF5EE0B4),
    onSuccessContainer = Color(0xFFC9F7E6),
    successContainer = Color(0xFF0D3B2E),
    warning = Color(0xFFFFB866),
    onWarningContainer = Color(0xFFFFE6C7),
    warningContainer = Color(0xFF4A2A00),
    danger = Red300,
    onDangerContainer = Color(0xFFFFDAD6),
    dangerContainer = Color(0xFF5C1717),
    info = Blue300,
    onInfoContainer = Blue50,
    infoContainer = Color(0xFF12305A),
    neutral = Night400,
    onNeutralContainer = Night200,
    neutralContainer = Night700,
)
