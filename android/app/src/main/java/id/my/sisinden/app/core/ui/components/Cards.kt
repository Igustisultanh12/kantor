package id.my.sisinden.app.core.ui.components

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.LocalIndication
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.background
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.core.ui.theme.Spacing

/** Skala tekan bergaya iOS: mengecil & meredup cepat, kembali dengan pantulan lembut. */
@Composable
fun Modifier.pressScale(interaction: MutableInteractionSource): Modifier {
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(
        targetValue = if (pressed) 0.965f else 1f,
        animationSpec = if (pressed) spring(dampingRatio = 1f, stiffness = 900f) else spring(dampingRatio = 0.55f, stiffness = 380f),
        label = "pressScale",
    )
    val dim by animateFloatAsState(if (pressed) 0.9f else 1f, spring(stiffness = 600f), label = "pressDim")
    return this.graphicsLayer { scaleX = scale; scaleY = scale; alpha = dim }
}

/**
 * Kartu dasar SISINDEN "Aurora".
 * Permukaan kaca (putih 86% di mode terang, kaca gelap di mode gelap) dengan
 * tepi terang bergradien, kilau atas, dan bayangan lembut berwarna — tidak
 * ada lagi garis abu tebal. Warna kustom dari pemanggil tetap dihormati.
 */
@OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)
@Composable
fun SindenCard(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    /** Tekan lama (mis. menu konteks berkas). Bila diisi, [onClick] juga lewat jalur ini. */
    onLongClick: (() -> Unit)? = null,
    containerColor: Color = MaterialTheme.colorScheme.surface,
    border: BorderStroke? = BorderStroke(1.5.dp, MaterialTheme.colorScheme.outlineVariant),
    contentPadding: Dp = Spacing.lg,
    content: @Composable ColumnScope.() -> Unit,
) {
    val glass = SindenTheme.glass
    val dark = SindenTheme.isDark
    val shape = MaterialTheme.shapes.medium
    val useGlass = containerColor == MaterialTheme.colorScheme.surface
    val color = when {
        !useGlass -> containerColor
        dark -> glass.fill
        else -> Color.White.copy(alpha = 0.95f) // cukup pekat agar bayangan tidak "tembus" ke isi
    }
    val stroke = when {
        border == null -> null
        useGlass -> BorderStroke(1.dp, Brush.verticalGradient(listOf(glass.edgeTop, glass.edgeBottom)))
        else -> border
    }
    val lifted = if (useGlass) {
        modifier.shadow(if (dark) 0.dp else 14.dp, shape, clip = false, ambientColor = glass.shadow, spotColor = glass.shadow)
    } else modifier
    val sheen = Modifier.drawBehind {
        if (useGlass) {
            drawRect(Brush.verticalGradient(0f to Color.White.copy(alpha = if (dark) 0.03f else 0.6f), 0.35f to Color.Transparent))
        }
    }
    if (onLongClick != null) {
        val interaction = remember { MutableInteractionSource() }
        Surface(
            modifier = lifted.pressScale(interaction).clip(shape).combinedClickable(
                interactionSource = interaction,
                indication = LocalIndication.current,
                onClick = { onClick?.invoke() },
                onLongClick = onLongClick,
            ),
            shape = shape,
            color = color,
            border = stroke,
        ) {
            Column(sheen.padding(contentPadding), content = content)
        }
    } else if (onClick != null) {
        val interaction = remember { MutableInteractionSource() }
        Surface(
            onClick = onClick,
            modifier = lifted.pressScale(interaction),
            shape = shape,
            color = color,
            border = stroke,
            interactionSource = interaction,
        ) {
            Column(sheen.padding(contentPadding), content = content)
        }
    } else {
        Surface(
            modifier = lifted,
            shape = shape,
            color = color,
            border = stroke,
        ) {
            Column(sheen.padding(contentPadding), content = content)
        }
    }
}

/** Wadah ikon "squircle" berwarna lembut (sudut 30%, seperti ubin Livin). */
@Composable
fun IconBadge(
    icon: ImageVector,
    tint: Color,
    modifier: Modifier = Modifier,
    size: Dp = 44.dp,
    iconSize: Dp = 22.dp,
    container: Color = tint.copy(alpha = 0.12f),
) {
    Box(
        modifier.size(size).clip(RoundedCornerShape(30)).background(container),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, contentDescription = null, tint = tint, modifier = Modifier.size(iconSize))
    }
}

/** Kotak inisial nama (avatar teks). */
@Composable
fun InitialsAvatar(text: String, modifier: Modifier = Modifier, size: Dp = 44.dp, highlight: Boolean = false) {
    val bg = if (highlight) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.primaryContainer
    val fg = if (highlight) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onPrimaryContainer
    Box(modifier.size(size).clip(CircleShape).background(bg), contentAlignment = Alignment.Center) {
        Text(text.take(2).uppercase(), color = fg, style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold)
    }
}

/** Kartu ringkasan angka (dashboard & modul). */
@Composable
fun StatTile(
    label: String,
    value: String,
    icon: ImageVector,
    tint: Color,
    modifier: Modifier = Modifier,
    caption: String? = null,
    onClick: (() -> Unit)? = null,
) {
    SindenCard(modifier = modifier, onClick = onClick, contentPadding = Spacing.md) {
        IconBadge(icon, tint, size = 44.dp, iconSize = 24.dp)
        Spacer(Modifier.height(Spacing.md))
        // Angka (mis. saldo) tidak boleh terpotong "Rp 125.750…": mengecil sampai muat.
        val base = MaterialTheme.typography.titleLarge
        var style by remember(value, base) { mutableStateOf(base) }
        Text(
            value,
            style = style,
            fontWeight = FontWeight.Bold,
            maxLines = 1,
            softWrap = false,
            onTextLayout = { r ->
                if (r.hasVisualOverflow && style.fontSize.value > 12f) style = style.copy(fontSize = style.fontSize * 0.9f)
            },
        )
        Text(
            label,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
        if (caption != null) {
            Spacer(Modifier.height(Spacing.xxs))
            Text(caption, style = MaterialTheme.typography.labelSmall, color = tint, maxLines = 1)
        }
    }
}

/** Baris label–nilai untuk halaman detail. */
@Composable
fun InfoRow(label: String, value: String?, modifier: Modifier = Modifier) {
    Row(modifier.fillMaxWidth().padding(vertical = Spacing.xs), verticalAlignment = Alignment.Top) {
        Text(
            label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.width(128.dp),
        )
        Text(
            value?.takeIf { it.isNotBlank() } ?: "-",
            style = MaterialTheme.typography.bodyMedium,
            fontWeight = FontWeight.Medium,
            modifier = Modifier.weight(1f),
        )
    }
}

/** Item daftar standar: ikon/avatar, judul, subjudul, trailing. */
@Composable
fun ListRowCard(
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    supporting: String? = null,
    leading: (@Composable () -> Unit)? = null,
    trailing: (@Composable RowScope.() -> Unit)? = null,
    onClick: (() -> Unit)? = null,
    onLongClick: (() -> Unit)? = null,
    /** Chip di bawah teks — tidak memakan lebar judul seperti [trailing]. */
    badge: (@Composable () -> Unit)? = null,
) {
    SindenCard(modifier = modifier.fillMaxWidth(), onClick = onClick, onLongClick = onLongClick, contentPadding = Spacing.md) {
        Row(Modifier.heightIn(min = 40.dp), verticalAlignment = Alignment.CenterVertically) {
            if (leading != null) {
                leading()
                Spacer(Modifier.width(Spacing.md))
            }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(title, style = MaterialTheme.typography.titleSmall, maxLines = 2, overflow = TextOverflow.Ellipsis)
                if (!subtitle.isNullOrBlank()) {
                    Text(
                        subtitle,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                if (!supporting.isNullOrBlank()) {
                    Text(
                        supporting,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                if (badge != null) Box(Modifier.padding(top = 4.dp)) { badge() }
            }
            if (trailing != null) {
                Spacer(Modifier.width(Spacing.sm))
                Row(verticalAlignment = Alignment.CenterVertically, content = trailing)
            }
        }
    }
}
