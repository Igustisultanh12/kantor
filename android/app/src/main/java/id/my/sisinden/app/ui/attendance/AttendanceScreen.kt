package id.my.sisinden.app.ui.attendance

import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material.icons.outlined.QrCodeScanner
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextAlign
import id.my.sisinden.app.core.ui.components.bouncyPress
import id.my.sisinden.app.core.ui.components.rememberReduceMotion
import id.my.sisinden.app.core.ui.theme.Indigo600
import id.my.sisinden.app.core.ui.theme.SindenTheme
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Business
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.DirectionsCar
import androidx.compose.material.icons.outlined.EventBusy
import androidx.compose.material.icons.outlined.LocationOn
import androidx.compose.material.icons.outlined.Security
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.ui.components.IconBadge
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.AttendanceDto
import id.my.sisinden.app.data.model.AttendanceStatus
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import java.time.LocalDate

private fun AttendanceStatus.icon(): ImageVector = when (this) {
    AttendanceStatus.HADIR -> Icons.Outlined.Business
    AttendanceStatus.DINAS_LUAR -> Icons.Outlined.DirectionsCar
    AttendanceStatus.PIKET -> Icons.Outlined.Security
    AttendanceStatus.IZIN -> Icons.Outlined.EventBusy
}

private fun statusTone(code: String?): Tone = when (code) {
    "hadir" -> Tone.SUCCESS
    "dinas_luar" -> Tone.INFO
    "piket" -> Tone.WARNING
    else -> Tone.NEUTRAL
}

@Composable
fun AttendanceScreen(repository: SindenRepository, canSubmit: Boolean) {
    val context = LocalContext.current
    val vm: AttendanceViewModel = viewModel { AttendanceViewModel(repository, context.applicationContext) }
    val state by vm.state.collectAsStateWithLifecycle()
    val form by vm.form.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)

    val locationPermission = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { result ->
        if (result.values.any { it }) vm.fetchLocation()
    }
    val requestLocation = {
        val granted = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
        if (granted) vm.fetchLocation()
        else locationPermission.launch(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION))
    }

    SindenScaffold(title = "Absensi", subtitle = Fmt.fullDate(LocalDate.now()), snackbarHostState = snackbar) { padding ->
        RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }, Modifier.padding(padding)) {
            LoadStateContent(state = state, onRetry = { vm.load() }) { data ->
                LazyColumn(
                    Modifier.fillMaxSize().imePadding(),
                    contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = Spacing.xxl),
                    verticalArrangement = Arrangement.spacedBy(Spacing.md),
                ) {
                    // Figma "Android / Absensi": tombol absen bulat besar + status hari ini.
                    item {
                        AbsenHero(
                            today = data.todayAttendance,
                            canSubmit = canSubmit,
                            submitting = form.submitting,
                            statusLabel = form.status.label,
                            onSubmit = vm::submit,
                        )
                    }
                    item { WeekRow(data.attendances.orEmpty()) }
                    item { TodayCard(data.todayAttendance, data.totalAttendedToday, data.totalActivePersonnel) }

                    if (canSubmit) {
                        item {
                            SindenCard(Modifier.fillMaxWidth()) {
                                Text(
                                    if (data.todayAttendance == null) "Lapor kehadiran" else "Perbarui laporan hari ini",
                                    style = MaterialTheme.typography.titleMedium,
                                )
                                Spacer(Modifier.height(Spacing.md))
                                Column(Modifier.selectableGroup(), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                                    AttendanceStatus.entries.chunked(2).forEach { row ->
                                        Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                                            row.forEach { st ->
                                                StatusOption(
                                                    status = st,
                                                    selected = form.status == st,
                                                    onSelect = { vm.setStatus(st) },
                                                    modifier = Modifier.weight(1f),
                                                )
                                            }
                                        }
                                    }
                                }
                                Spacer(Modifier.height(Spacing.md))
                                SindenTextField(
                                    value = form.notes,
                                    onValueChange = vm::setNotes,
                                    label = "Catatan (opsional)",
                                    placeholder = "Mis. tujuan dinas luar",
                                    singleLine = false,
                                    minLines = 2,
                                )
                                Spacer(Modifier.height(Spacing.sm))
                                LocationRow(
                                    form = form,
                                    onRequest = requestLocation,
                                    onClear = vm::clearLocation,
                                )
                                Spacer(Modifier.height(Spacing.lg))
                                PrimaryButton(
                                    text = if (data.todayAttendance == null) "Kirim presensi" else "Perbarui presensi",
                                    onClick = vm::submit,
                                    loading = form.submitting,
                                )
                            }
                        }
                    }

                    item {
                        Text("Riwayat 30 hari", style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(top = Spacing.sm))
                    }
                    val history = data.attendances.orEmpty()
                    if (history.isEmpty()) {
                        item {
                            Text(
                                "Belum ada riwayat presensi.",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    } else {
                        items(history, key = { it.id }) { HistoryRow(it) }
                    }
                }
            }
        }
    }
}

/**
 * Tombol absen Nova: lingkaran bergradien biru → indigo dengan cincin berdenyut.
 * Ketuk = kirim presensi dengan status terpilih (bawaan "Hadir"). Setelah absen,
 * berubah menjadi tanda centang hijau beserta jam. Reduced motion: cincin diam.
 */
@Composable
private fun AbsenHero(
    today: AttendanceDto?,
    canSubmit: Boolean,
    submitting: Boolean,
    statusLabel: String,
    onSubmit: () -> Unit,
) {
    val done = today != null
    val reduce = rememberReduceMotion()
    val t = rememberInfiniteTransition(label = "absenRing")
    val ring by t.animateFloat(1f, 1.35f, infiniteRepeatable(tween(1800), RepeatMode.Restart), label = "ring")
    val ringAlpha by t.animateFloat(0.35f, 0f, infiniteRepeatable(tween(1800), RepeatMode.Restart), label = "ringAlpha")
    val success = SindenTheme.status.success
    val brush = if (done) Brush.linearGradient(listOf(Color(0xFF22C55E), Color(0xFF15803D)))
    else Brush.linearGradient(listOf(Color(0xFF3B82F6), Indigo600))
    val enabled = canSubmit && !done && !submitting

    Column(
        Modifier.fillMaxWidth().padding(top = Spacing.md, bottom = Spacing.xs),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        StatusChip(
            if (done) "Sudah absen hari ini" else "Belum absen hari ini",
            if (done) Tone.SUCCESS else Tone.WARNING,
        )
        Spacer(Modifier.height(Spacing.lg))
        Box(
            Modifier
                .size(212.dp)
                .drawBehind {
                    // cincin lembut tetap + cincin berdenyut (hanya bila belum absen)
                    drawCircle((if (done) success else Indigo600).copy(alpha = 0.10f), radius = size.minDimension / 2f)
                    if (!reduce && !done) {
                        drawCircle(Color(0xFF2563EB).copy(alpha = ringAlpha), radius = size.minDimension / 2f * 0.74f * ring)
                    }
                },
            contentAlignment = Alignment.Center,
        ) {
            Box(
                Modifier
                    .size(148.dp)
                    .bouncyPress(0.94f)
                    .shadow(18.dp, CircleShape, ambientColor = Indigo600, spotColor = Indigo600)
                    .clip(CircleShape)
                    .background(brush)
                    .drawBehind { drawRect(Brush.verticalGradient(0f to Color.White.copy(alpha = 0.28f), 0.5f to Color.Transparent)) }
                    .border(3.dp, Color.White.copy(alpha = 0.85f), CircleShape)
                    .clickable(
                        enabled = enabled,
                        role = Role.Button,
                        onClickLabel = "Kirim presensi $statusLabel",
                        onClick = onSubmit,
                    )
                    .semantics {
                        contentDescription = when {
                            done -> "Sudah absen pukul ${today?.timeShort ?: "-"}"
                            !canSubmit -> "Akun ini tidak dapat mengirim presensi"
                            else -> "Ketuk untuk absen sebagai $statusLabel"
                        }
                    },
                contentAlignment = Alignment.Center,
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    when {
                        submitting -> CircularProgressIndicator(Modifier.size(36.dp), color = Color.White, strokeWidth = 3.dp)
                        done -> Icon(Icons.Outlined.CheckCircle, contentDescription = null, tint = Color.White, modifier = Modifier.size(48.dp))
                        else -> Icon(Icons.Outlined.QrCodeScanner, contentDescription = null, tint = Color.White, modifier = Modifier.size(44.dp))
                    }
                    Spacer(Modifier.height(Spacing.sm))
                    Text(
                        when {
                            submitting -> "Mengirim…"
                            done -> "${today?.timeShort ?: "-"} WIB"
                            canSubmit -> "Ketuk untuk absen"
                            else -> "Belum absen"
                        },
                        style = MaterialTheme.typography.labelLarge,
                        color = Color.White,
                        textAlign = TextAlign.Center,
                    )
                }
            }
        }
        if (!done && canSubmit) {
            Text(
                "Status: $statusLabel · ubah di bawah bila perlu",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = Spacing.sm),
            )
        }
    }
}

/** Baris "Minggu ini" Sen–Jum: hijau = hadir, biru = hari ini (belum), abu = belum/tidak hadir. */
@Composable
private fun WeekRow(history: List<AttendanceDto>) {
    val today = LocalDate.now()
    val monday = today.with(java.time.DayOfWeek.MONDAY)
    val attended = history.mapNotNull { Fmt.parseDate(it.dateOnly) }.toSet()
    val names = listOf("Sen", "Sel", "Rab", "Kam", "Jum")
    val success = SindenTheme.status.success
    SindenCard(Modifier.fillMaxWidth()) {
        Text("Minggu ini", style = MaterialTheme.typography.titleSmall)
        Spacer(Modifier.height(Spacing.md))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            names.forEachIndexed { i, name ->
                val day = monday.plusDays(i.toLong())
                val present = day in attended
                val isToday = day == today
                val label = when {
                    present -> "$name, hadir"
                    isToday -> "$name, hari ini, belum absen"
                    day.isAfter(today) -> "$name, belum"
                    else -> "$name, tidak ada presensi"
                }
                Column(
                    Modifier.semantics(mergeDescendants = true) { contentDescription = label },
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Text(name, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Spacer(Modifier.height(6.dp))
                    Box(
                        Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(
                                when {
                                    present -> success
                                    isToday -> MaterialTheme.colorScheme.primary
                                    else -> MaterialTheme.colorScheme.surfaceVariant
                                }
                            ),
                        contentAlignment = Alignment.Center,
                    ) {
                        when {
                            present -> Icon(Icons.Outlined.Check, null, tint = Color.White, modifier = Modifier.size(18.dp))
                            isToday -> Box(Modifier.size(8.dp).clip(CircleShape).background(Color.White))
                            else -> Icon(Icons.Outlined.Schedule, null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.size(16.dp))
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun TodayCard(today: AttendanceDto?, attended: Int, active: Int) {
    val tone = if (today != null) Tone.SUCCESS else Tone.WARNING
    SindenCard(
        Modifier.fillMaxWidth(),
        border = BorderStroke(1.dp, toneAccent(tone).copy(alpha = 0.4f)),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconBadge(if (today != null) Icons.Outlined.CheckCircle else Icons.Outlined.EventBusy, toneAccent(tone), size = 52.dp, iconSize = 26.dp)
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                Text(
                    if (today != null) "Sudah presensi" else "Belum presensi",
                    style = MaterialTheme.typography.titleMedium,
                )
                Text(
                    if (today != null) "${today.statusLabel} · ${today.timeShort ?: "-"} WIB" else "Silakan lapor kehadiran hari ini.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                today?.locationName?.takeIf { it.isNotBlank() }?.let {
                    Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
        Spacer(Modifier.height(Spacing.md))
        Text(
            "Kehadiran satuan hari ini: $attended dari $active personel aktif",
            style = MaterialTheme.typography.labelMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun StatusOption(status: AttendanceStatus, selected: Boolean, onSelect: () -> Unit, modifier: Modifier = Modifier) {
    val color = if (selected) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface
    val border = if (selected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outlineVariant
    Surface(
        modifier = modifier
            .height(64.dp)
            .selectable(selected = selected, onClick = onSelect, role = Role.RadioButton),
        shape = MaterialTheme.shapes.small,
        color = color,
        border = BorderStroke(if (selected) 1.5.dp else 1.dp, border),
    ) {
        Row(Modifier.padding(horizontal = Spacing.md), verticalAlignment = Alignment.CenterVertically) {
            Icon(
                status.icon(),
                contentDescription = null,
                tint = if (selected) MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.width(Spacing.sm))
            Text(
                status.label,
                style = MaterialTheme.typography.labelLarge,
                fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
                color = if (selected) MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurface,
            )
        }
    }
}

@Composable
private fun LocationRow(form: AttendanceForm, onRequest: () -> Unit, onClear: () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Outlined.LocationOn, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
        Spacer(Modifier.width(Spacing.sm))
        Column(Modifier.weight(1f)) {
            val loc = form.location
            when {
                form.locating -> Text("Mengambil lokasi…", style = MaterialTheme.typography.bodyMedium)
                loc != null -> {
                    Text("Lokasi terlampir", style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold)
                    Text(
                        String.format(java.util.Locale.US, "%.5f, %.5f", loc.latitude, loc.longitude) + (loc.accuracyMeters?.let { " (±${it.toInt()} m)" } ?: ""),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                else -> {
                    Text("Tanpa lokasi", style = MaterialTheme.typography.bodyMedium)
                    Text(
                        form.locationError ?: "Lampirkan lokasi GPS bila diminta satuan.",
                        style = MaterialTheme.typography.bodySmall,
                        color = if (form.locationError != null) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
        when {
            form.locating -> CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp)
            form.location != null -> IconButton(onClick = onClear) { Icon(Icons.Outlined.Close, contentDescription = "Hapus lokasi") }
            else -> OutlinedButton(onClick = onRequest) { Text("Ambil") }
        }
    }
}

@Composable
private fun HistoryRow(item: AttendanceDto) {
    ListRowCard(
        title = Fmt.parseDate(item.dateOnly)?.let { Fmt.fullDate(it) } ?: (item.dateOnly ?: "-"),
        subtitle = listOfNotNull(item.timeShort?.let { "$it WIB" }, item.locationName?.takeIf { it.isNotBlank() }).joinToString(" · "),
        supporting = item.notes,
        trailing = { StatusChip(item.statusLabel, statusTone(item.status)) },
    )
}
