package id.my.sisinden.app.ui.menu

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AccountCircle
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FeatureTile
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.appear
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.ui.theme.TileGradient
import id.my.sisinden.app.core.ui.theme.TilePalette
import id.my.sisinden.app.data.model.UserProfile
import id.my.sisinden.app.ui.navigation.Feature
import id.my.sisinden.app.ui.navigation.Routes

/** Satu ubin di layar "Semua fitur". */
private data class MoreItem(
    val route: String,
    val title: String,
    val icon: ImageVector,
    val gradient: TileGradient,
    val group: String,
)

/**
 * Tab "Lainnya" Nova (Figma "Android / Lainnya"): grid ubin 4 kolom per grup,
 * bisa dicari. Isinya modul yang diizinkan server, kecuali yang sudah ada di
 * dock bawah ([dockRoutes]), ditambah grup Akun (Profil, Pengaturan).
 */
@Composable
fun MenuScreen(
    permissions: PermissionSet,
    user: UserProfile,
    onNavigate: (String) -> Unit,
    dockRoutes: Set<String> = emptySet(),
) {
    var query by rememberSaveable { mutableStateOf("") }
    val q = query.trim().lowercase()

    val items = buildList {
        Feature.permitted(permissions)
            .filter { it.route !in dockRoutes }
            .forEach { add(MoreItem(it.route, it.title, it.icon, it.gradient, it.group.title)) }
        if (Routes.PROFILE !in dockRoutes && permissions.has(Perm.PROFILE_VIEW)) {
            add(MoreItem(Routes.PROFILE, "Profil", Icons.Outlined.AccountCircle, TilePalette.Blue, "Akun"))
        }
        add(MoreItem(Routes.SETTINGS, "Pengaturan", Icons.Outlined.Settings, TilePalette.Slate, "Akun"))
    }.filter { q.isEmpty() || it.title.lowercase().contains(q) || it.group.lowercase().contains(q) }

    val grouped = items.groupBy { it.group }

    SindenScaffold(title = "Semua fitur", subtitle = "Modul sesuai hak akses ${user.roleLabel}") { padding ->
        LazyColumn(
            Modifier.fillMaxSize().padding(padding),
            contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, top = Spacing.lg, bottom = Spacing.xxl),
            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
        ) {
            item {
                SearchField(query, { query = it }, "Cari fitur…")
                Spacer(Modifier.height(Spacing.sm))
            }
            if (grouped.isEmpty()) {
                item {
                    EmptyState(
                        title = if (q.isEmpty()) "Belum ada modul" else "Fitur tidak ditemukan",
                        message = if (q.isEmpty()) "Akun Anda belum diberi akses modul tambahan. Hubungi Admin."
                        else "Tidak ada fitur yang cocok dengan \"$query\". Coba kata lain.",
                    )
                }
            }
            var index = 0
            grouped.forEach { (group, groupItems) ->
                item(key = "h-$group") {
                    Text(
                        group,
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier
                            .padding(top = Spacing.md, bottom = Spacing.xs, start = 2.dp)
                            .semantics { heading() },
                    )
                }
                groupItems.chunked(4).forEachIndexed { rowIdx, row ->
                    val appearIndex = index++
                    item(key = "r-$group-$rowIdx") {
                        Row(
                            Modifier.fillMaxWidth().appear(appearIndex),
                            horizontalArrangement = Arrangement.spacedBy(10.dp),
                        ) {
                            row.forEach { m ->
                                FeatureTile(
                                    title = m.title,
                                    icon = m.icon,
                                    gradient = m.gradient,
                                    onClick = { onNavigate(m.route) },
                                    modifier = Modifier.weight(1f),
                                )
                            }
                            // sel kosong agar baris terakhir tetap rata kiri
                            repeat(4 - row.size) { Spacer(Modifier.weight(1f)) }
                        }
                    }
                }
            }
        }
    }
}
