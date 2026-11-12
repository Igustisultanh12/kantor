package id.my.sisinden.app.core.ui.components

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CloudOff
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.Inbox
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.Timer
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.network.ApiError
import id.my.sisinden.app.core.network.ErrorKind
import id.my.sisinden.app.core.ui.theme.Spacing

/** Status muat data standar untuk seluruh layar. */
sealed interface LoadState<out T> {
    data object Loading : LoadState<Nothing>
    data class Ready<T>(val data: T) : LoadState<T>
    data class Failed(val error: ApiError) : LoadState<Nothing>
}

val <T> LoadState<T>.dataOrNull: T? get() = (this as? LoadState.Ready<T>)?.data

/** Kilau skeleton ringan (satu animasi bersama per pemanggil). */
@Composable
fun shimmerBrush(): Brush {
    val base = MaterialTheme.colorScheme.surfaceVariant
    val highlight = MaterialTheme.colorScheme.surface
    val transition = rememberInfiniteTransition(label = "shimmer")
    val x by transition.animateFloat(
        initialValue = -400f,
        targetValue = 1200f,
        animationSpec = infiniteRepeatable(tween(1100, easing = LinearEasing), RepeatMode.Restart),
        label = "shimmerX",
    )
    return Brush.linearGradient(
        colors = listOf(base, highlight.copy(alpha = 0.9f), base),
        start = androidx.compose.ui.geometry.Offset(x, 0f),
        end = androidx.compose.ui.geometry.Offset(x + 400f, 0f),
    )
}

@Composable
fun SkeletonBlock(modifier: Modifier = Modifier, height: Dp = 16.dp, brush: Brush = shimmerBrush()) {
    Box(
        modifier
            .height(height)
            .clip(MaterialTheme.shapes.small)
            .background(brush)
    )
}

/**
 * Kerangka daftar kartu saat memuat: kartu muncul bertahap (cascade), lebar baris
 * bervariasi, bentuk ikon bergantian, dan denyut halus agar tidak monoton.
 */
@Composable
fun ListSkeleton(modifier: Modifier = Modifier, items: Int = 5) {
    val brush = shimmerBrush()
    val pulse = rememberInfiniteTransition(label = "skeletonPulse")
    val breathe by pulse.animateFloat(
        initialValue = 0.72f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(900), RepeatMode.Reverse), label = "breathe",
    )
    // Pola lebar per baris (pseudo-acak tetap) supaya tidak terlihat seragam.
    val titleW = listOf(0.72f, 0.55f, 0.83f, 0.64f, 0.78f, 0.5f)
    val subW = listOf(0.42f, 0.6f, 0.35f, 0.52f, 0.3f, 0.48f)
    Column(modifier.padding(horizontal = Spacing.screen), verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
        repeat(items) { i ->
            val appear = remember { androidx.compose.animation.core.Animatable(0f) }
            androidx.compose.runtime.LaunchedEffect(Unit) {
                kotlinx.coroutines.delay(i * 70L)
                appear.animateTo(1f, tween(320))
            }
            Box(
                Modifier.graphicsLayer { alpha = appear.value * breathe; translationY = (1f - appear.value) * 24f },
            ) {
                SindenCard {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        val iconShape = if (i % 3 == 1) MaterialTheme.shapes.medium else CircleShape
                        Box(Modifier.size(44.dp).clip(iconShape).background(brush))
                        Spacer(Modifier.width(Spacing.md))
                        Column(Modifier.weight(1f)) {
                            SkeletonBlock(Modifier.fillMaxWidth(titleW[i % titleW.size]), 14.dp, brush)
                            Spacer(Modifier.height(Spacing.sm))
                            SkeletonBlock(Modifier.fillMaxWidth(subW[i % subW.size]), 12.dp, brush)
                        }
                        if (i % 2 == 0) {
                            Spacer(Modifier.width(Spacing.md))
                            SkeletonBlock(Modifier.width(48.dp), 22.dp, brush)
                        }
                    }
                }
            }
        }
    }
}


@Composable
private fun StateMessage(
    icon: ImageVector,
    title: String,
    message: String,
    tint: Color,
    modifier: Modifier = Modifier,
    action: (@Composable () -> Unit)? = null,
) {
    Column(
        modifier.fillMaxWidth().padding(horizontal = Spacing.xxxl, vertical = Spacing.xxxl),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box(
            Modifier.size(72.dp).clip(CircleShape).background(tint.copy(alpha = 0.12f)),
            contentAlignment = Alignment.Center,
        ) {
            Icon(icon, contentDescription = null, tint = tint, modifier = Modifier.size(34.dp))
        }
        Spacer(Modifier.height(Spacing.lg))
        Text(title, style = MaterialTheme.typography.titleMedium, textAlign = TextAlign.Center)
        Spacer(Modifier.height(Spacing.xs))
        Text(
            message,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
        )
        if (action != null) {
            Spacer(Modifier.height(Spacing.lg))
            action()
        }
    }
}

@Composable
fun EmptyState(
    title: String,
    message: String,
    modifier: Modifier = Modifier,
    icon: ImageVector = Icons.Outlined.Inbox,
    action: (@Composable () -> Unit)? = null,
) = StateMessage(icon, title, message, MaterialTheme.colorScheme.primary, modifier, action)

@Composable
fun ErrorState(
    error: ApiError,
    onRetry: (() -> Unit)?,
    modifier: Modifier = Modifier,
) {
    val (icon, title) = when (error.kind) {
        ErrorKind.NETWORK -> Icons.Outlined.CloudOff to "Tidak dapat terhubung"
        ErrorKind.TIMEOUT -> Icons.Outlined.Timer to "Koneksi terlalu lama"
        ErrorKind.FORBIDDEN -> Icons.Outlined.Lock to "Akses ditolak"
        ErrorKind.NOT_FOUND -> Icons.Outlined.ErrorOutline to "Tidak ditemukan"
        else -> Icons.Outlined.ErrorOutline to "Terjadi kendala"
    }
    StateMessage(
        icon = icon,
        title = title,
        message = error.message,
        tint = MaterialTheme.colorScheme.error,
        modifier = modifier,
        action = if (onRetry != null && error.kind != ErrorKind.FORBIDDEN) {
            {
                OutlinedButton(onClick = onRetry) {
                    Icon(Icons.Outlined.Refresh, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(Spacing.sm))
                    Text("Coba lagi")
                }
            }
        } else null,
    )
}

@Composable
fun AccessDeniedState(
    modifier: Modifier = Modifier,
    message: String = "Menu ini tidak termasuk dalam hak akses akun Anda. Hubungi Admin bila Anda seharusnya memiliki akses.",
    action: (@Composable () -> Unit)? = null,
) = StateMessage(Icons.Outlined.Lock, "Akses ditolak", message, MaterialTheme.colorScheme.error, modifier, action)
