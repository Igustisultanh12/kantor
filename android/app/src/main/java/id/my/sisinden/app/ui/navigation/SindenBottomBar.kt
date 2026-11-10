package id.my.sisinden.app.ui.navigation

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.wrapContentHeight
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.shape.CircleShape
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
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.components.bouncyPress
import id.my.sisinden.app.core.ui.components.glass
import id.my.sisinden.app.core.ui.components.rememberReduceMotion
import id.my.sisinden.app.core.ui.theme.Indigo600
import id.my.sisinden.app.core.ui.theme.SindenTheme

/**
 * Tab bar "Aurora": kapsul kaca mengambang dengan indikator cair yang
 * meluncur ke tab terpilih (spring gaya iOS). Setiap ikon tetap berlabel.
 * Tab "Presensi" — aksi harian paling sering — tampil sebagai tombol bulat
 * bergradien di tengah dengan cincin berdenyut.
 */
@Composable
internal fun SindenBottomBar(
    tabs: List<TabItem>,
    currentRoute: String?,
    onSelect: (String) -> Unit,
) {
    val raised = tabs.size >= 3 && tabs.any { it.route == Routes.ATTENDANCE }
    val selectedIndex = tabs.indexOfFirst { it.route == currentRoute }
    val indicatorVisible = selectedIndex >= 0 && !(raised && tabs[selectedIndex].route == Routes.ATTENDANCE)
    val shape = RoundedCornerShape(32.dp)

    Box(
        Modifier
            .fillMaxWidth()
            .navigationBarsPadding()
            .padding(start = 14.dp, end = 14.dp, bottom = 10.dp, top = if (raised) 22.dp else 6.dp),
    ) {
        Box(Modifier.matchParentSize().glass(shape, strong = true, elevation = 18.dp))

        BoxWithConstraints(
            Modifier
                .fillMaxWidth()
                .height(72.dp)
                .padding(horizontal = 6.dp),
        ) {
            val itemWidth = maxWidth / tabs.size.coerceAtLeast(1)
            val x by animateDpAsState(
                targetValue = itemWidth * selectedIndex.coerceAtLeast(0),
                animationSpec = spring(dampingRatio = 0.72f, stiffness = 380f),
                label = "indicatorX",
            )
            val indicatorAlpha by animateFloatAsState(if (indicatorVisible) 1f else 0f, tween(200), label = "indicatorAlpha")
            // Indikator cair: kapsul kaca kebiruan yang meluncur di belakang ikon.
            Box(
                Modifier
                    .offset(x = x)
                    .width(itemWidth)
                    .fillMaxHeight()
                    .padding(horizontal = 4.dp, vertical = 7.dp)
                    .graphicsLayer { alpha = indicatorAlpha }
                    .clip(RoundedCornerShape(24.dp))
                    .background(
                        Brush.verticalGradient(
                            listOf(
                                MaterialTheme.colorScheme.primary.copy(alpha = if (SindenTheme.isDark) 0.30f else 0.14f),
                                Indigo600.copy(alpha = if (SindenTheme.isDark) 0.22f else 0.10f),
                            )
                        )
                    )
                    .border(1.dp, Brush.verticalGradient(listOf(Color.White.copy(alpha = 0.7f), Color.White.copy(alpha = 0.05f))), RoundedCornerShape(24.dp)),
            )

            Row(
                Modifier.fillMaxWidth().fillMaxHeight().selectableGroup(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceEvenly,
            ) {
                tabs.forEach { tab ->
                    val selected = currentRoute == tab.route
                    if (raised && tab.route == Routes.ATTENDANCE) {
                        RaisedItem(tab, selected) { if (!selected) onSelect(tab.route) }
                    } else {
                        BarItem(tab, selected) { if (!selected) onSelect(tab.route) }
                    }
                }
            }
        }
    }
}

@Composable
private fun RowScope.BarItem(tab: TabItem, selected: Boolean, onClick: () -> Unit) {
    val scheme = MaterialTheme.colorScheme
    val iconScale by animateFloatAsState(
        if (selected) 1.12f else 1f,
        spring(dampingRatio = 0.45f, stiffness = 420f),
        label = "tabIcon",
    )
    val tint by animateColorAsState(if (selected) scheme.primary else scheme.onSurfaceVariant, label = "tabTint")
    Column(
        Modifier
            .weight(1f)
            .bouncyPress(0.9f)
            .clip(RoundedCornerShape(22.dp))
            .selectable(
                selected = selected,
                role = Role.Tab,
                interactionSource = remember { MutableInteractionSource() },
                indication = null,
                onClick = onClick,
            )
            .padding(vertical = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Icon(
            if (selected) tab.selectedIcon else tab.icon,
            contentDescription = null,
            tint = tint,
            modifier = Modifier
                .size(26.dp)
                .graphicsLayer { scaleX = iconScale; scaleY = iconScale },
        )
        Text(
            tab.label,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = tint,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(top = 2.dp),
        )
    }
}

@Composable
private fun RowScope.RaisedItem(tab: TabItem, selected: Boolean, onClick: () -> Unit) {
    val reduce = rememberReduceMotion()
    val t = rememberInfiniteTransition(label = "raisedPulse")
    val ring = t.animateFloat(1f, 1.45f, infiniteRepeatable(tween(1800), RepeatMode.Restart), label = "ring")
    val ringAlpha = t.animateFloat(0.45f, 0f, infiniteRepeatable(tween(1800), RepeatMode.Restart), label = "ringAlpha")
    val scale by animateFloatAsState(if (selected) 1.06f else 1f, spring(dampingRatio = 0.45f, stiffness = 380f), label = "raisedScale")
    Column(
        Modifier
            .weight(1f)
            .wrapContentHeight(unbounded = true)
            .offset(y = (-16).dp)
            .bouncyPress(0.9f)
            .selectable(
                selected = selected,
                role = Role.Tab,
                interactionSource = remember { MutableInteractionSource() },
                indication = null,
                onClick = onClick,
            ),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box(contentAlignment = Alignment.Center) {
            // cincin berdenyut — mengajak presensi harian
            Box(
                Modifier
                    .size(64.dp)
                    .drawBehind {
                        if (!reduce) {
                            drawCircle(
                                color = Color(0xFF2563EB).copy(alpha = ringAlpha.value),
                                radius = size.minDimension / 2f * ring.value,
                            )
                        }
                    }
            )
            Box(
                Modifier
                    .size(64.dp)
                    .graphicsLayer { scaleX = scale; scaleY = scale }
                    .shadow(14.dp, CircleShape, ambientColor = Indigo600, spotColor = Indigo600)
                    .clip(CircleShape)
                    .background(Brush.linearGradient(listOf(Color(0xFF3B82F6), Indigo600)))
                    .drawBehind {
                        drawRect(Brush.verticalGradient(0f to Color.White.copy(alpha = 0.35f), 0.5f to Color.Transparent))
                    }
                    .border(3.dp, Color.White.copy(alpha = 0.85f), CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                Icon(tab.selectedIcon, contentDescription = null, tint = Color.White, modifier = Modifier.size(30.dp))
            }
        }
        Text(
            tab.label,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface,
            modifier = Modifier.padding(top = 4.dp),
            maxLines = 1,
        )
    }
}
