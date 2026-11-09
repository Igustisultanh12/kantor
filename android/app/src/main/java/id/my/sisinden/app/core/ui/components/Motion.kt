package id.my.sisinden.app.core.ui.components

import androidx.compose.animation.core.Animatable
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.theme.TileGradient
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

// Gerak & interaksi bersama (gaya SISFOPERS KC): kartu masuk bertahap,
// efek memantul saat ditekan, ikon yang "hidup". Tanpa emoji.

/** Animasi masuk bertahap (fade + naik + sedikit membesar) untuk kartu & item daftar. */
@Composable
fun Modifier.appear(index: Int = 0, key: Any? = Unit, distance: Float = 42f): Modifier {
    val anim = remember(key) { Animatable(0f) }
    LaunchedEffect(key) {
        delay(index.coerceIn(0, 10) * 45L)
        anim.animateTo(1f, tween(440, easing = FastOutSlowInEasing))
    }
    return this.graphicsLayer {
        alpha = anim.value
        translationY = (1f - anim.value) * distance
        val s = 0.96f + 0.04f * anim.value
        scaleX = s
        scaleY = s
    }
}

/**
 * Efek mengecil-memantul saat ditekan. Tidak "memakan" sentuhan, jadi bisa
 * dipasang di atas komponen yang sudah punya onClick.
 */
@Composable
fun Modifier.bouncyPress(pressedScale: Float = 0.96f): Modifier {
    var pressed by remember { mutableStateOf(false) }
    val scale by animateFloatAsState(
        if (pressed) pressedScale else 1f,
        spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessMedium),
        label = "press",
    )
    return this
        .graphicsLayer { scaleX = scale; scaleY = scale }
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
fun Modifier.floating(amplitude: Float = 6f, periodMs: Int = 1800): Modifier {
    val t = rememberInfiniteTransition(label = "float")
    val y by t.animateFloat(-amplitude, amplitude, infiniteRepeatable(tween(periodMs, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "y")
    return this.graphicsLayer { translationY = y }
}

/** Titik berdenyut sebagai penanda ada hal yang menunggu tindakan. */
@Composable
fun PulseDot(color: Color, modifier: Modifier = Modifier, size: Dp = 10.dp) {
    val t = rememberInfiniteTransition(label = "pulse")
    val ring by t.animateFloat(1f, 2.2f, infiniteRepeatable(tween(1100), RepeatMode.Restart), label = "ring")
    val alpha by t.animateFloat(0.45f, 0f, infiniteRepeatable(tween(1100), RepeatMode.Restart), label = "alpha")
    Box(modifier.size(size * 2.2f), contentAlignment = Alignment.Center) {
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
    val pop = remember(key) { Animatable(0.6f) }
    val tilt = remember(key) { Animatable(-8f) }
    LaunchedEffect(key) {
        launch { pop.animateTo(1f, spring(dampingRatio = 0.45f, stiffness = Spring.StiffnessLow)) }
        launch { tilt.animateTo(0f, spring(dampingRatio = 0.4f, stiffness = Spring.StiffnessLow)) }
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
