package id.my.sisinden.app.core.ui.components

import android.os.Build
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.AnimationSpec
import androidx.compose.animation.core.CubicBezierEasing
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.awaitEachGesture
import androidx.compose.foundation.gestures.awaitFirstDown
import androidx.compose.foundation.gestures.waitForUpOrCancellation
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.BlurEffect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.TileMode
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import android.provider.Settings
import id.my.sisinden.app.core.ui.theme.TileGradient
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/*
 * Gerak bergaya iOS untuk seluruh aplikasi.
 *  - Spring "kritis" (sedikit/ tanpa mantul) seperti UIKit/SwiftUI default.
 *  - Elemen datang dengan blur-in (Android 12+), turun ke fade+naik di bawahnya.
 *  - Tekan: mengecil 4–5% + sedikit meredup, lalu kembali dengan pantulan lembut.
 *  - Hormati "Hapus animasi" sistem (ANIMATOR_DURATION_SCALE = 0).
 */

/** Kurva keluar khas iOS (setara cubic-bezier(.32,.72,0,1)). */
val IosEase = CubicBezierEasing(0.32f, 0.72f, 0f, 1f)

/** Spring bawaan iOS: respons ~0.5 dtk, redaman ~0.85 (hampir tanpa mantul). */
fun <T> iosSpring(bouncy: Boolean = false): AnimationSpec<T> =
    if (bouncy) spring(dampingRatio = 0.62f, stiffness = 420f) else spring(dampingRatio = 0.86f, stiffness = 300f)

/** true bila pengguna mematikan animasi di Opsi pengembang / Aksesibilitas. */
@Composable
fun rememberReduceMotion(): Boolean {
    val ctx = LocalContext.current
    return remember {
        runCatching {
            Settings.Global.getFloat(ctx.contentResolver, Settings.Global.ANIMATOR_DURATION_SCALE, 1f) == 0f
        }.getOrDefault(false)
    }
}

/**
 * Animasi masuk bertahap untuk kartu & item daftar: fade + naik + membesar
 * sedikit + blur-in (API 31+). Jeda per item dibatasi agar tidak terasa lambat.
 */
@Composable
fun Modifier.appear(index: Int = 0, key: Any? = Unit, distance: Float = 48f): Modifier {
    val reduce = rememberReduceMotion()
    val anim = remember(key) { Animatable(if (reduce) 1f else 0f) }
    LaunchedEffect(key) {
        if (reduce) return@LaunchedEffect
        delay(index.coerceIn(0, 8) * 55L)
        anim.animateTo(1f, spring(dampingRatio = 0.82f, stiffness = 170f))
    }
    return this.graphicsLayer {
        val p = anim.value.coerceIn(0f, 1.05f)
        alpha = p.coerceAtMost(1f)
        translationY = (1f - anim.value) * distance
        val s = 0.94f + 0.06f * anim.value
        scaleX = s
        scaleY = s
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            val r = ((1f - p) * 18f).coerceAtLeast(0f)
            renderEffect = if (r > 0.5f) BlurEffect(r, r, TileMode.Decal) else null
        }
    }
}

/**
 * Efek tekan bergaya iOS: mengecil + meredup saat jari menempel, kembali
 * dengan pantulan halus saat dilepas. Tidak "memakan" sentuhan.
 */
@Composable
fun Modifier.bouncyPress(pressedScale: Float = 0.95f): Modifier {
    var pressed by remember { mutableStateOf(false) }
    val scale by animateFloatAsState(
        if (pressed) pressedScale else 1f,
        if (pressed) spring(dampingRatio = 1f, stiffness = 900f) else spring(dampingRatio = 0.55f, stiffness = 380f),
        label = "press",
    )
    val dim by animateFloatAsState(if (pressed) 0.86f else 1f, tween(if (pressed) 90 else 260), label = "dim")
    return this
        .graphicsLayer { scaleX = scale; scaleY = scale; alpha = dim }
        .pointerInput(Unit) {
            awaitEachGesture {
                awaitFirstDown(requireUnconsumed = false)
                pressed = true
                waitForUpOrCancellation()
                pressed = false
            }
        }
}

/** Mengambang naik-turun pelan (untuk ilustrasi kosong / ikon utama). */
@Composable
fun Modifier.floating(amplitude: Float = 6f, periodMs: Int = 2200): Modifier {
    val t = rememberInfiniteTransition(label = "float")
    val y by t.animateFloat(-amplitude, amplitude, infiniteRepeatable(tween(periodMs, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "y")
    return this.graphicsLayer { translationY = y }
}

/** Titik berdenyut sebagai penanda ada hal yang menunggu tindakan. */
@Composable
fun PulseDot(color: Color, modifier: Modifier = Modifier, size: Dp = 10.dp) {
    val t = rememberInfiniteTransition(label = "pulse")
    val ring by t.animateFloat(1f, 2.4f, infiniteRepeatable(tween(1400, easing = IosEase), RepeatMode.Restart), label = "ring")
    val alpha by t.animateFloat(0.5f, 0f, infiniteRepeatable(tween(1400, easing = IosEase), RepeatMode.Restart), label = "alpha")
    Box(modifier.size(size * 2.4f), contentAlignment = Alignment.Center) {
        Box(Modifier.size(size).graphicsLayer { scaleX = ring; scaleY = ring; this.alpha = alpha }.clip(CircleShape).background(color))
        Box(Modifier.size(size).clip(CircleShape).background(color))
    }
}

/**
 * Ubin ikon bergradasi yang "hidup": muncul dengan pantulan kecil dan sedikit
 * miring, menggantikan ikon garis polos yang terasa kaku.
 */
@Composable
fun LivelyIconTile(
    icon: ImageVector,
    gradient: TileGradient,
    modifier: Modifier = Modifier,
    size: Dp = 44.dp,
    iconSize: Dp = 22.dp,
    corner: Dp = 14.dp,
    key: Any? = Unit,
) {
    val reduce = rememberReduceMotion()
    val pop = remember(key) { Animatable(if (reduce) 1f else 0.5f) }
    val tilt = remember(key) { Animatable(if (reduce) 0f else -12f) }
    LaunchedEffect(key) {
        if (reduce) return@LaunchedEffect
        launch { pop.animateTo(1f, spring(dampingRatio = 0.5f, stiffness = Spring.StiffnessLow)) }
        launch { tilt.animateTo(0f, spring(dampingRatio = 0.45f, stiffness = Spring.StiffnessLow)) }
    }
    GradientIconTile(
        icon = icon,
        gradient = gradient,
        size = size,
        iconSize = iconSize,
        corner = corner,
        modifier = modifier.graphicsLayer { scaleX = pop.value; scaleY = pop.value; rotationZ = tilt.value },
    )
}
