package id.my.sisinden.app.ui.common

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import kotlin.math.cos
import kotlin.math.sin
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import id.my.sisinden.app.R

/** Malam biru pekat latar login "Aurora" (sama dengan web #050b1a). */
val AuroraNight = Color(0xFF050B1A)

/** Warna latar login web SISINDEN (Tailwind slate-950 / slate-900). */
val Slate950 = Color(0xFF020617)
val Slate900 = Color(0xFF0F172A)
val Slate300 = Color(0xFFCBD5E1)
val Slate400 = Color(0xFF94A3B8)

/**
 * Latar seperti halaman login web SISINDEN: foto latar yang diatur Admin
 * (`login_background`) ditutup lapisan gelap slate-950/80. Bila belum ada
 * foto, dipakai gradien malam dengan cahaya biru/oranye lembut.
 */
@Composable
fun BrandBackdrop(
    backgroundUrl: String?,
    modifier: Modifier = Modifier,
    content: @Composable BoxScope.() -> Unit,
) {
    // Aurora bergerak pelan (satu putaran ~36 dtk), dibaca di fase gambar saja.
    val reduce = id.my.sisinden.app.core.ui.components.rememberReduceMotion()
    val phase = rememberInfiniteTransition(label = "loginAurora").animateFloat(
        initialValue = 0f,
        targetValue = (2 * Math.PI).toFloat(),
        animationSpec = infiniteRepeatable(tween(36_000, easing = LinearEasing), RepeatMode.Restart),
        label = "phase",
    )
    Box(modifier.fillMaxSize().background(AuroraNight)) {
        if (!backgroundUrl.isNullOrBlank()) {
            AsyncImage(
                model = backgroundUrl,
                contentDescription = null,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize(),
            )
            // Lapisan gelap seperti web agar teks selalu terbaca di atas foto.
            Box(Modifier.fillMaxSize().background(AuroraNight.copy(alpha = 0.78f)))
        }
        Box(
            Modifier
                .fillMaxSize()
                .drawBehind {
                    val p = if (reduce) 0.8f else phase.value
                    val w = size.width
                    val h = size.height
                    val r = size.maxDimension * 0.6f
                    fun blob(color: Color, cx: Float, cy: Float, radius: Float) {
                        val c = Offset(cx, cy)
                        drawCircle(Brush.radialGradient(listOf(color, Color.Transparent), center = c, radius = radius), radius = radius, center = c)
                    }
                    blob(Color(0xFF1D4ED8).copy(alpha = 0.55f), w * (0.1f + 0.15f * cos(p)), h * (0.08f + 0.06f * sin(p)), r)
                    blob(Color(0xFF4F46E5).copy(alpha = 0.45f), w * (0.95f + 0.1f * sin(p * 1.2f)), h * (0.35f + 0.08f * cos(p)), r * 0.85f)
                    blob(Color(0xFF0D9488).copy(alpha = 0.32f), w * (0.4f + 0.2f * sin(p * 0.7f)), h * (1.02f + 0.04f * cos(p * 1.4f)), r * 0.8f)
                },
        )
        content()
    }
}

/**
 * Logo SISINDEN yang "mengambang" halus (naik-turun 10dp, 5 detik/siklus —
 * sama dengan animasi `float-logo` di web) dengan cahaya lembut berdenyut.
 */
@Composable
fun FloatingLogo(modifier: Modifier = Modifier, size: Dp = 132.dp) {
    val transition = rememberInfiniteTransition(label = "floatingLogo")
    val lift by transition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(2500, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "lift",
    )
    val glow by transition.animateFloat(
        initialValue = 0.25f,
        targetValue = 0.5f,
        animationSpec = infiniteRepeatable(tween(2500, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "glow",
    )
    val liftPx = with(LocalDensity.current) { 10.dp.toPx() }
    Box(
        modifier
            .size(size * 1.35f)
            .drawBehind {
                drawCircle(
                    Brush.radialGradient(listOf(Color(0xFF4BA3FE).copy(alpha = glow), Color.Transparent)),
                    radius = this.size.minDimension / 2f,
                )
            },
        contentAlignment = Alignment.Center,
    ) {
        Image(
            painterResource(R.drawable.ic_sinden_mark),
            contentDescription = "Logo SISINDEN",
            contentScale = ContentScale.Fit,
            modifier = Modifier
                .size(size)
                .graphicsLayer { translationY = -liftPx * lift },
        )
    }
}

/** Animasi kartu mengambang halus (6dp, 6 detik/siklus) seperti `float-card` di web. */
@Composable
fun rememberCardFloat(): Float {
    val transition = rememberInfiniteTransition(label = "floatingCard")
    val v by transition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(3000, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "cardLift",
    )
    return v
}
