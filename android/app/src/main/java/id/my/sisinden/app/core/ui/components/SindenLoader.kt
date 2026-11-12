package id.my.sisinden.app.core.ui.components

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.size
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import kotlin.math.cos
import kotlin.math.sin

private val DefaultMessages = listOf("Menyiapkan data", "Menghubungi server", "Menyusun tampilan", "Hampir selesai")

/**
 * Loader bermerek SISINDEN: tiga titik mengorbit dengan kecepatan berbeda di sekitar
 * cincin yang "bernapas", plus pesan yang berganti halus (tidak monoton).
 */
@Composable
fun SindenLoader(
    modifier: Modifier = Modifier,
    size: Dp = 64.dp,
    tint: Color = MaterialTheme.colorScheme.primary,
    messages: List<String>? = DefaultMessages,
    textColor: Color = MaterialTheme.colorScheme.onSurfaceVariant,
) {
    val t = rememberInfiniteTransition(label = "sindenLoader")
    val a1 by t.animateFloat(0f, 360f, infiniteRepeatable(tween(1100, easing = LinearEasing)), label = "a1")
    val a2 by t.animateFloat(0f, 360f, infiniteRepeatable(tween(1700, easing = LinearEasing)), label = "a2")
    val a3 by t.animateFloat(360f, 0f, infiniteRepeatable(tween(2300, easing = LinearEasing)), label = "a3")
    val breathe by t.animateFloat(0.82f, 1f, infiniteRepeatable(tween(900, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "breathe")

    var idx by remember { mutableIntStateOf(0) }
    if (!messages.isNullOrEmpty() && messages.size > 1) {
        LaunchedEffect(messages) {
            while (true) {
                delay(1600)
                idx = (idx + 1) % messages.size
            }
        }
    }

    Column(
        modifier.semantics { contentDescription = "Memuat" },
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Canvas(Modifier.size(size)) {
            val c = Offset(this.size.width / 2f, this.size.height / 2f)
            val r = this.size.minDimension / 2f
            drawCircle(tint.copy(alpha = 0.12f), radius = r * 0.62f * breathe, center = c)
            drawCircle(tint.copy(alpha = 0.25f), radius = r * 0.62f, center = c, style = Stroke(width = 2.dp.toPx()))
            fun dot(angle: Float, radius: Float, dotR: Float, alpha: Float) {
                val rad = Math.toRadians(angle.toDouble())
                drawCircle(tint.copy(alpha = alpha), radius = dotR, center = Offset(c.x + radius * cos(rad).toFloat(), c.y + radius * sin(rad).toFloat()))
            }
            dot(a1, r * 0.86f, 4.5.dp.toPx(), 1f)
            dot(a2, r * 0.86f, 3.5.dp.toPx(), 0.7f)
            dot(a3, r * 0.62f, 3.dp.toPx(), 0.5f)
        }
        if (!messages.isNullOrEmpty()) {
            AnimatedContent(
                targetState = messages[idx % messages.size],
                transitionSpec = {
                    (fadeIn(tween(220)) + slideInVertically { it / 2 }) togetherWith (fadeOut(tween(160)) + slideOutVertically { -it / 2 })
                },
                label = "loaderMsg",
            ) { msg ->
                Text("$msg…", style = MaterialTheme.typography.labelLarge, color = textColor, textAlign = TextAlign.Center)
            }
        }
    }
}
