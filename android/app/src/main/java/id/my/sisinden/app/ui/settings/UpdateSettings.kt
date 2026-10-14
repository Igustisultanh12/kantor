package id.my.sisinden.app.ui.settings

import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.SystemUpdate
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.SindenApplication
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.update.AppUpdater
import id.my.sisinden.app.core.update.UpdateState
import kotlinx.coroutines.launch

/** Kartu "Pembaruan aplikasi": versi terpasang, cek manual, unduh & pasang otomatis. */
@Composable
internal fun UpdateCard() {
    val context = LocalContext.current
    val state by AppUpdater.state.collectAsStateWithLifecycle()
    var auto by remember { mutableStateOf(AppUpdater.isAutoEnabled(context)) }
    val busy = state is UpdateState.Checking || state is UpdateState.Downloading || state is UpdateState.Installing

    SindenCard(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Outlined.SystemUpdate, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.width(Spacing.md))
            Text("Pembaruan aplikasi", style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
            Text("v${BuildConfig.VERSION_NAME}", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        Spacer(Modifier.height(Spacing.sm))
        val status = when (val s = state) {
            UpdateState.Idle -> "Versi baru diperiksa otomatis dari server rilis resmi."
            UpdateState.Checking -> "Memeriksa pembaruan…"
            is UpdateState.UpToDate -> "Aplikasi sudah versi terbaru (v${s.version})."
            is UpdateState.Downloading -> "Mengunduh v${s.version}… ${s.percent}%"
            is UpdateState.Installing -> "Memasang v${s.version}… Jika diminta, ketuk \"Perbarui\"."
            is UpdateState.Failed -> s.message
        }
        Text(
            status,
            style = MaterialTheme.typography.bodySmall,
            color = if (state is UpdateState.Failed) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
        )
        (state as? UpdateState.Downloading)?.let { d ->
            Spacer(Modifier.height(Spacing.sm))
            LinearProgressIndicator(progress = { d.percent / 100f }, modifier = Modifier.fillMaxWidth())
        }
        Spacer(Modifier.height(Spacing.sm))
        SwitchRow(
            title = "Perbarui otomatis",
            subtitle = "Unduh & pasang versi baru secara otomatis (di latar belakang hanya lewat Wi-Fi)",
            checked = auto,
            onChange = {
                auto = it
                AppUpdater.setAutoEnabled(context, it)
            },
        )
        OutlinedButton(
            // Berjalan di cakupan aplikasi: unduhan tetap lanjut walau layar Pengaturan ditutup.
            onClick = {
                (context.applicationContext as SindenApplication).appScope.launch {
                    AppUpdater.checkAndUpdate(context.applicationContext, interactive = true)
                }
            },
            enabled = !busy,
            modifier = Modifier.fillMaxWidth(),
        ) { Text(if (busy) "Sedang diproses…" else "Periksa pembaruan sekarang") }
    }
}
