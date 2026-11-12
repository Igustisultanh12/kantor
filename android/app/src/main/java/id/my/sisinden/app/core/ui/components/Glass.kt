package id.my.sisinden.app.core.ui.components

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.core.ui.theme.TileGradient
import id.my.sisinden.app.core.ui.theme.TilePalette
import kotlin.math.cos
import kotlin.math.sin

/**
 * Permukaan kaca "Aurora": isi tembus pandang, tepi bergradien (atas terang →
 * bawah samar), kilau specular lembut di sepertiga atas, dan bayangan
 * berwarna yang sangat halus. Tanpa blur latar (RenderEffect) supaya teks
 * tetap tajam dan ringan di perangkat lama.
 */
@Composable
fun Modifier.glass(
    shape: Shape = MaterialTheme.shapes.large,
    strong: Boolean = false,
    elevation: Dp = 0.dp,
): Modifier {
    val g = SindenTheme.glass
    val dark = SindenTheme.isDark
    val shadowed = if (elevation > 0.dp) {
        this.shadow(elevation, shape, clip = false, ambientColor = g.shadow, spotColor = g.shadow)
    } else this
    return shadowed
        .clip(shape)
        .background(if (strong) g.fillStrong else g.fill)
        .drawBehind {
            // kilau specular: cahaya dari atas, memudar sebelum tengah
            drawRect(
                Brush.verticalGradient(
                    0f to Color.White.copy(alpha = if (dark) 0.035f else 0.55f),
                    0.45f to Color.Transparent,
                ),
            )
        }
        .border(1.dp, Brush.verticalGradient(listOf(g.edgeTop, g.edgeBottom)), shape)
}

/**
 * Latar aplikasi "Aurora": kanvas + tiga gumpalan cahaya yang bergerak
 * perlahan (satu putaran ~40 detik). Animasi berhenti bila sistem
 * mematikan animasi.
 */
@Composable
fun GlassBackground(modifier: Modifier = Modifier, content: @Composable BoxScope.() -> Unit) {
    val g = SindenTheme.glass
    val base = MaterialTheme.colorScheme.background
    val reduce = rememberReduceMotion()
    val t = rememberInfiniteTransition(label = "aurora")
    // State dibaca di fase gambar saja → hanya redraw, tanpa rekomposisi per frame.
    val phase = t.animateFloat(
        initialValue = 0f,
        targetValue = (2 * Math.PI).toFloat(),
        animationSpec = infiniteRepeatable(tween(40_000, easing = LinearEasing), RepeatMode.Restart),
        label = "phase",
    )
    Box(
        modifier
            .fillMaxSize()
            .background(base)
            .drawBehind {
                val p = if (reduce) 0.6f else phase.value
                val w = size.width
                val h = size.height
                val r = size.maxDimension * 0.62f
                fun blob(color: Color, cx: Float, cy: Float, radius: Float) {
                    val c = Offset(cx, cy)
                    drawCircle(Brush.radialGradient(listOf(color, Color.Transparent), center = c, radius = radius), radius = radius, center = c)
                }
                blob(g.glowA, w * (0.85f + 0.12f * cos(p)), h * (0.06f + 0.05f * sin(p)), r)
                blob(g.glowB, w * (0.05f + 0.10f * sin(p * 1.3f)), h * (0.5f + 0.08f * cos(p)), r * 0.85f)
                blob(g.glowC, w * (0.9f + 0.08f * sin(p * 0.8f)), h * (0.95f + 0.04f * cos(p * 1.2f)), r * 0.8f)
            },
        content = content,
    )
}

/**
 * Kotak ikon bergradien seperti ikon aplikasi iOS: gradien diagonal,
 * kilau kaca di separuh atas, tepi terang tipis, bayangan berwarna lembut.
 */
@Composable
fun GradientIconTile(
    icon: ImageVector,
    gradient: TileGradient,
    modifier: Modifier = Modifier,
    size: Dp = 56.dp,
    iconSize: Dp = 28.dp,
    corner: Dp = 20.dp,
    contentDescription: String? = null,
) {
    // Gaya Nova: kotak ber-tint lembut + ikon berwarna (bukan gradien jenuh).
    // Warna ikon digelapkan (terang) / dicerahkan (gelap) agar kontras ikon ≥ 3:1.
    val shape = RoundedCornerShape(corner)
    val dark = SindenTheme.isDark
    val ink = if (dark) lerp(gradient.top, Color.White, 0.18f) else lerp(gradient.bottom, Color.Black, 0.22f)
    Box(
        modifier
            .size(size)
            .clip(shape)
            .background(gradient.bottom.copy(alpha = if (dark) 0.20f else 0.12f))
            .border(1.dp, gradient.bottom.copy(alpha = if (dark) 0.32f else 0.18f), shape),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, contentDescription = contentDescription, tint = ink, modifier = Modifier.size(iconSize))
    }
}

/**
 * Tombol fitur vertikal: ubin ikon 56dp + label 2 baris. Membal halus saat
 * ditekan (gaya ikon di Home Screen iOS). Area sentuh ≥ 48dp.
 */
@Composable
fun FeatureTile(
    title: String,
    icon: ImageVector,
    gradient: TileGradient,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    badge: Int = 0,
) {
    // Ubin Nova: kartu kaca radius 18 berisi kotak ikon ber-tint + label (Figma "Semua fitur").
    val interaction = remember { MutableInteractionSource() }
    val tileShape = RoundedCornerShape(18.dp)
    Column(
        modifier
            .widthIn(min = 64.dp)
            .bouncyPress(0.94f)
            .glass(tileShape)
            .clickable(interactionSource = interaction, indication = null, role = Role.Button, onClick = onClick)
            .padding(start = 4.dp, end = 4.dp, top = 12.dp, bottom = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Box {
            GradientIconTile(icon, gradient, size = 44.dp, iconSize = 22.dp, corner = 14.dp)
            if (badge > 0) CountBadge(badge, Modifier.align(Alignment.TopEnd).padding(top = 0.dp))
        }
        Text(
            title,
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurface,
            textAlign = TextAlign.Center,
            maxLines = 2,
            minLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

/**
 * Jumlah kolom grid [FeatureTile]: 4 pada huruf normal, 3 bila huruf diperbesar
 * (Pengaturan "Ukuran teks" atau setelan sistem) agar label seperti "Notifikasi"
 * tidak terpenggal di tengah kata.
 */
@Composable
fun rememberTileColumns(): Int = if (LocalDensity.current.fontScale > 1.1f) 3 else 4

/** Gradien bawaan per [Tone], untuk ubin yang tidak punya palet khusus. */
fun toneGradient(tone: Tone): TileGradient = when (tone) {
    Tone.SUCCESS -> TilePalette.Teal
    Tone.WARNING -> TilePalette.Orange
    Tone.DANGER -> TilePalette.Rose
    Tone.INFO -> TilePalette.Blue
    Tone.NEUTRAL -> TilePalette.Slate
}

/** Garis pemisah rambut (0,5–1dp) seperti separator iOS. */
@Composable
fun SoftDivider(modifier: Modifier = Modifier) {
    Box(
        modifier
            .height(1.dp)
            .background(MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.8f))
    )
}
