package id.my.sisinden.app.core.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.heightIn
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.ui.theme.Indigo600
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.runtime.getValue
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.graphics.Brush

enum class Tone { SUCCESS, WARNING, DANGER, INFO, NEUTRAL }

data class ToneColors(val fg: Color, val bg: Color)

@Composable
fun toneColors(tone: Tone): ToneColors {
    val s = SindenTheme.status
    return when (tone) {
        Tone.SUCCESS -> ToneColors(s.onSuccessContainer, s.successContainer)
        Tone.WARNING -> ToneColors(s.onWarningContainer, s.warningContainer)
        Tone.DANGER -> ToneColors(s.onDangerContainer, s.dangerContainer)
        Tone.INFO -> ToneColors(s.onInfoContainer, s.infoContainer)
        Tone.NEUTRAL -> ToneColors(s.onNeutralContainer, s.neutralContainer)
    }
}

@Composable
fun toneAccent(tone: Tone): Color {
    val s = SindenTheme.status
    return when (tone) {
        Tone.SUCCESS -> s.success
        Tone.WARNING -> s.warning
        Tone.DANGER -> s.danger
        Tone.INFO -> s.info
        Tone.NEUTRAL -> s.neutral
    }
}

/** Label status kecil dengan titik warna — warna tidak menjadi satu-satunya penanda (ada teks). */
@Composable
fun StatusChip(text: String, tone: Tone, modifier: Modifier = Modifier) {
    val c = toneColors(tone)
    Row(
        modifier
            .clip(RoundedCornerShape(50))
            .background(c.bg)
            .padding(horizontal = 10.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(6.dp).clip(CircleShape).background(toneAccent(tone)))
        Spacer(Modifier.width(6.dp))
        Text(text, style = MaterialTheme.typography.labelMedium, color = c.fg, fontWeight = FontWeight.SemiBold)
    }
}

/**
 * Baris chip filter gaya Livin ("Sering Dipakai · Transfer · Top-Up"):
 * terpilih = latar biru muda + garis biru 2dp + teks tebal; lainnya garis
 * tipis. Tinggi minimum 44dp agar mudah disentuh.
 */
@Composable
fun <T> FilterChipRow(
    options: List<T>,
    selected: T,
    label: (T) -> String,
    onSelect: (T) -> Unit,
    modifier: Modifier = Modifier,
    contentPadding: PaddingValues = PaddingValues(horizontal = Spacing.screen),
) {
    Row(
        modifier
            .horizontalScroll(rememberScrollState())
            .padding(contentPadding),
        horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
    ) {
        options.forEach { option ->
            PillChip(text = label(option), selected = option == selected, onClick = { onSelect(option) })
        }
    }
}

/**
 * Chip pil "Aurora": terpilih = kapsul gradien biru → indigo dengan teks
 * putih tebal; lainnya kaca dengan garis rambut. Perpindahan warna dianimasikan
 * dan chip membal halus saat ditekan. Tinggi minimum 44dp.
 */
@Composable
fun PillChip(text: String, selected: Boolean, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val scheme = MaterialTheme.colorScheme
    val isSelected = selected
    val dark = SindenTheme.isDark
    val sel by animateFloatAsState(if (selected) 1f else 0f, spring(dampingRatio = 0.8f, stiffness = 400f), label = "chipSel")
    val textColor by animateColorAsState(if (selected) Color.White else scheme.onSurface, label = "chipText")
    Surface(
        onClick = onClick,
        modifier = modifier
            .bouncyPress(0.93f)
            .heightIn(min = 44.dp)
            .semantics { this.selected = isSelected },
        shape = RoundedCornerShape(50),
        color = scheme.surface.copy(alpha = if (dark) 0.92f else 0.85f),
        border = BorderStroke(1.dp, scheme.outlineVariant.copy(alpha = 1f - sel)),
    ) {
        Box(
            Modifier
                .drawBehind {
                    if (sel > 0f) {
                        drawRect(
                            Brush.linearGradient(listOf(Color(0xFF2563EB), Indigo600)),
                            alpha = sel,
                        )
                    }
                }
                .padding(horizontal = 16.dp, vertical = 10.dp),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                text,
                style = MaterialTheme.typography.labelLarge,
                fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
                color = textColor,
                maxLines = 1,
            )
        }
    }
}

/** Lencana angka kecil (mis. jumlah notifikasi belum dibaca). */
@Composable
fun CountBadge(count: Int, modifier: Modifier = Modifier) {
    if (count <= 0) return
    Box(
        modifier
            .clip(RoundedCornerShape(50))
            .background(MaterialTheme.colorScheme.error)
            .padding(horizontal = 6.dp, vertical = 1.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            if (count > 99) "99+" else count.toString(),
            color = MaterialTheme.colorScheme.onError,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Bold,
        )
    }
}
