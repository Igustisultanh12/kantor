package id.my.sisinden.app.ui.ibubeti

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import android.provider.OpenableColumns
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.ArrowDownward
import androidx.compose.material.icons.outlined.ArrowUpward
import androidx.compose.material.icons.outlined.AttachFile
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material.icons.outlined.Receipt
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.security.expectExternalActivity
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.components.DateField
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.IconBadge
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.IbuBetiResponse
import id.my.sisinden.app.data.model.IbuBetiTxDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.data.repository.UploadImage
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.time.LocalDate
import java.time.YearMonth

/** Bukti transaksi terpilih dari perangkat (foto/PDF). */
class PickedBukti(val upload: UploadImage, val label: String)

class IbuBetiViewModel(private val repository: SindenRepository) : LoadableViewModel<IbuBetiResponse>() {
    /** null = semua periode. */
    val month = MutableStateFlow<YearMonth?>(YearMonth.now())
    /** null = semua jenis; "MASUK" / "KELUAR". */
    val jenis = MutableStateFlow<String?>(null)
    val search = MutableStateFlow("")

    init { load() }

    override suspend fun fetch(): ApiResult<IbuBetiResponse> =
        repository.ibuBeti(month.value?.toString(), jenis.value, search.value)

    fun setMonth(ym: YearMonth?) { month.value = ym; reload() }
    fun setJenis(value: String?) { jenis.value = value; reload() }
    fun setSearch(value: String) { search.value = value }
    fun applySearch() = reload()

    fun save(id: Int?, date: String, income: Boolean, amount: Double, note: String, bukti: UploadImage?, onDone: () -> Unit) =
        action(if (id == null) "Mutasi tercatat." else "Mutasi diperbarui.", onDone) {
            repository.saveIbuBeti(id, date, if (income) "MASUK" else "KELUAR", amount, note.trim(), bukti)
        }

    fun delete(id: Int, onDone: () -> Unit) = action("Mutasi dihapus.", onDone) { repository.deleteIbuBeti(id) }

    /** Muat foto bukti (berkas privat) untuk ditampilkan. */
    fun loadImage(id: Int, onResult: (Bitmap?, String?) -> Unit) {
        viewModelScope.launch {
            when (val res = repository.ibuBetiBuktiImage(id)) {
                is ApiResult.Success -> {
                    val bmp = withContext(Dispatchers.Default) { decodeSampled(res.data) }
                    onResult(bmp, if (bmp == null) "Foto bukti tidak dapat dibuka." else null)
                }
                is ApiResult.Failure -> onResult(null, res.error.message)
            }
        }
    }

    companion object {
        const val MAX_BYTES = 5L * 1024 * 1024
        private val ALLOWED = mapOf("jpg" to "image/jpeg", "jpeg" to "image/jpeg", "png" to "image/png", "pdf" to "application/pdf")

        fun decodeSampled(bytes: ByteArray, maxSide: Int = 2048): Bitmap? {
            val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
            BitmapFactory.decodeByteArray(bytes, 0, bytes.size, bounds)
            var sample = 1
            while (bounds.outWidth / sample > maxSide || bounds.outHeight / sample > maxSide) sample *= 2
            return BitmapFactory.decodeByteArray(bytes, 0, bytes.size, BitmapFactory.Options().apply { inSampleSize = sample })
        }

        /** Baca berkas bukti dari pemilih dokumen. Mengembalikan pesan error bila tidak valid. */
        fun readBukti(context: Context, uri: Uri): Pair<PickedBukti?, String?> = runCatching {
            val resolver = context.contentResolver
            var name: String? = null
            var size = -1L
            resolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE), null, null, null)?.use { c ->
                if (c.moveToFirst()) {
                    name = c.getString(0)
                    if (!c.isNull(1)) size = c.getLong(1)
                }
            }
            val mime = resolver.getType(uri).orEmpty()
            val ext = name?.substringAfterLast('.', "")?.lowercase()?.takeIf { it in ALLOWED }
                ?: when (mime) {
                    "image/jpeg" -> "jpg"
                    "image/png" -> "png"
                    "application/pdf" -> "pdf"
                    else -> null
                }
                ?: return Pair(null, "Bukti harus berformat JPG, PNG, atau PDF.")
            if (size > MAX_BYTES) return Pair(null, "Ukuran bukti maksimal 5 MB.")
            val bytes = resolver.openInputStream(uri)?.use { it.readBytes() } ?: return Pair(null, "Berkas tidak dapat dibaca.")
            if (bytes.size > MAX_BYTES) return Pair(null, "Ukuran bukti maksimal 5 MB.")
            val fileName = "bukti_${System.currentTimeMillis()}.$ext"
            val label = (name ?: fileName) + " · " + (if (bytes.size >= 1_048_576) "%.1f MB".format(bytes.size / 1_048_576.0) else "${bytes.size / 1024} KB")
            Pair(PickedBukti(UploadImage(fileName, ALLOWED.getValue(ext), bytes), label), null)
        }.getOrElse { Pair(null, "Berkas tidak dapat dibaca.") }
    }
}

@Composable
fun IbuBetiScreen(
    repository: SindenRepository,
    permissions: PermissionSet,
    onBack: () -> Unit,
    onOpenPdf: (String, String, String) -> Unit,
) {
    val vm: IbuBetiViewModel = viewModel { IbuBetiViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val month by vm.month.collectAsStateWithLifecycle()
    val jenis by vm.jenis.collectAsStateWithLifecycle()
    val search by vm.search.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    val canManage = permissions.has(Perm.IBU_BETI_MANAGE)

    /** null = tertutup; id 0 = transaksi baru. */
    var editing by remember { mutableStateOf<IbuBetiTxDto?>(null) }
    var detail by remember { mutableStateOf<IbuBetiTxDto?>(null) }
    var confirmDelete by remember { mutableStateOf<IbuBetiTxDto?>(null) }
    var viewImage by remember { mutableStateOf<IbuBetiTxDto?>(null) }

    val openBukti: (IbuBetiTxDto) -> Unit = { tx ->
        if (tx.buktiIsPdf) {
            onOpenPdf("api/mobile/pdf/ibu-beti/bukti/${tx.id}", "Bukti Transaksi", "BUKTI_IBU_BETI_${tx.id}.pdf")
        } else {
            viewImage = tx
        }
    }

    SindenScaffold(
        title = "Rekening Ibu Beti",
        subtitle = month?.let { Fmt.monthYear(it) } ?: "Semua periode",
        onBack = onBack,
        snackbarHostState = snackbar,
        actions = {
            if (permissions.has(Perm.IBU_BETI_PDF)) {
                IconButton(onClick = {
                    val q = month?.let { "?month=$it" } ?: ""
                    onOpenPdf("api/mobile/pdf/ibu-beti$q", "Rekening Koran Ibu Beti", "REKENING_IBU_BETI_${month ?: LocalDate.now()}.pdf")
                }) { Icon(Icons.Outlined.PictureAsPdf, contentDescription = "Rekening koran PDF") }
            }
        },
        floatingActionButton = {
            if (canManage) {
                ExtendedFloatingActionButton(
                    onClick = { editing = IbuBetiTxDto() },
                    icon = { Icon(Icons.Outlined.Add, contentDescription = null) },
                    text = { Text("Mutasi") },
                )
            }
        },
    ) { padding ->
        Column(Modifier.padding(padding)) {
            Row(Modifier.fillMaxWidth().padding(horizontal = Spacing.sm), verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = { vm.setMonth((month ?: YearMonth.now()).minusMonths(1)) }) {
                    Icon(Icons.AutoMirrored.Filled.KeyboardArrowLeft, contentDescription = "Bulan sebelumnya")
                }
                Text(
                    month?.let { Fmt.monthYear(it) } ?: "Semua periode",
                    style = MaterialTheme.typography.titleMedium,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.weight(1f),
                )
                IconButton(onClick = { vm.setMonth((month ?: YearMonth.now()).plusMonths(1)) }) {
                    Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = "Bulan berikutnya")
                }
                FilterChip(selected = month == null, onClick = { vm.setMonth(if (month == null) YearMonth.now() else null) }, label = { Text("Semua") })
            }
            Row(
                Modifier.fillMaxWidth().padding(horizontal = Spacing.screen),
                horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                FilterChip(selected = jenis == null, onClick = { vm.setJenis(null) }, label = { Text("Semua") })
                FilterChip(selected = jenis == "MASUK", onClick = { vm.setJenis("MASUK") }, label = { Text("Masuk") })
                FilterChip(selected = jenis == "KELUAR", onClick = { vm.setJenis("KELUAR") }, label = { Text("Keluar") })
            }
            SindenTextField(
                search, vm::setSearch, "Cari keterangan",
                modifier = Modifier.padding(horizontal = Spacing.screen),
                imeAction = ImeAction.Search,
                keyboardActions = KeyboardActions(onSearch = { vm.applySearch() }),
                trailingIcon = {
                    IconButton(onClick = { vm.applySearch() }) { Icon(Icons.Outlined.Search, contentDescription = "Cari") }
                },
            )
            Spacer(Modifier.height(Spacing.sm))
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { data ->
                    LazyColumn(
                        contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        item { SummaryCard(data) }
                        val list = data.data.orEmpty()
                        if (list.isEmpty()) {
                            item { EmptyState("Tidak ada mutasi", "Belum ada transaksi Rekening Ibu Beti pada periode ini.") }
                        } else {
                            items(list, key = { it.id }) { tx ->
                                val tone = if (tx.isIncome) Tone.SUCCESS else Tone.DANGER
                                ListRowCard(
                                    title = tx.keterangan.orEmpty().ifBlank { "-" },
                                    subtitle = Fmt.longDate(tx.dateOnly),
                                    supporting = "Saldo " + Fmt.rupiah(tx.saldoBerjalan) + (if (tx.hasBukti) " · ada bukti" else ""),
                                    onClick = { detail = tx },
                                    leading = {
                                        IconBadge(
                                            if (tx.isIncome) Icons.Outlined.ArrowDownward else Icons.Outlined.ArrowUpward,
                                            toneAccent(tone), size = 40.dp, iconSize = 20.dp,
                                        )
                                    },
                                    trailing = {
                                        Text(
                                            (if (tx.isIncome) "+" else "−") + Fmt.rupiah(tx.jumlah),
                                            style = MaterialTheme.typography.titleSmall,
                                            color = toneAccent(tone),
                                        )
                                    },
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    detail?.let { tx ->
        FormSheet(title = "Detail mutasi", onDismiss = { detail = null }) {
            val tone = if (tx.isIncome) Tone.SUCCESS else Tone.DANGER
            Text(
                (if (tx.isIncome) "Masuk  +" else "Keluar  −") + Fmt.rupiah(tx.jumlah),
                style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold, color = toneAccent(tone),
            )
            Spacer(Modifier.height(Spacing.sm))
            DetailRow("Tanggal", Fmt.longDate(tx.dateOnly))
            DetailRow("Keterangan", tx.keterangan.orEmpty().ifBlank { "-" })
            DetailRow("Saldo setelah mutasi", Fmt.rupiah(tx.saldoBerjalan))
            DetailRow("Dicatat oleh", tx.petugasInput.orEmpty().ifBlank { "-" })
            Spacer(Modifier.height(Spacing.md))
            if (tx.hasBukti) {
                OutlinedButton(onClick = { openBukti(tx) }, modifier = Modifier.fillMaxWidth()) {
                    Icon(Icons.Outlined.Receipt, contentDescription = null)
                    Spacer(Modifier.width(Spacing.sm))
                    Text(if (tx.buktiIsPdf) "Lihat bukti (PDF)" else "Lihat foto bukti")
                }
                Spacer(Modifier.height(Spacing.sm))
            }
            if (canManage) {
                Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    OutlinedButton(onClick = { detail = null; editing = tx }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Outlined.Edit, contentDescription = null)
                        Spacer(Modifier.width(Spacing.xs))
                        Text("Ubah")
                    }
                    OutlinedButton(onClick = { detail = null; confirmDelete = tx }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Outlined.Delete, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                        Spacer(Modifier.width(Spacing.xs))
                        Text("Hapus", color = MaterialTheme.colorScheme.error)
                    }
                }
            }
            Spacer(Modifier.height(Spacing.lg))
        }
    }

    editing?.let { tx -> TxForm(tx, busy, onDismiss = { editing = null }, onSave = { date, income, amount, note, bukti ->
        vm.save(tx.id.takeIf { it > 0 }, date, income, amount, note, bukti) { editing = null }
    }) }

    confirmDelete?.let { tx ->
        ConfirmDialog(
            title = "Hapus mutasi?",
            message = "Mutasi \"${tx.keterangan.orEmpty()}\" sebesar ${Fmt.rupiah(tx.jumlah)} beserta buktinya akan dihapus.",
            confirmLabel = "Hapus",
            destructive = true,
            loading = busy,
            onDismiss = { confirmDelete = null },
            onConfirm = { vm.delete(tx.id) { confirmDelete = null } },
        )
    }

    viewImage?.let { tx -> BuktiImageDialog(vm, tx) { viewImage = null } }
}

@Composable
private fun SummaryCard(data: IbuBetiResponse) {
    val on = MaterialTheme.colorScheme.onPrimary
    SindenCard(Modifier.fillMaxWidth(), containerColor = MaterialTheme.colorScheme.primary, border = null) {
        Text(if (data.isMonthly) "Saldo akhir bulan" else "Saldo akhir", style = MaterialTheme.typography.labelMedium, color = on.copy(alpha = 0.8f))
        Text(Fmt.rupiah(data.saldoAkhir), style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold, color = on)
        Spacer(Modifier.height(Spacing.sm))
        Row {
            if (data.isMonthly) {
                Column(Modifier.weight(1f)) {
                    Text("Saldo awal", style = MaterialTheme.typography.labelSmall, color = on.copy(alpha = 0.8f))
                    Text(Fmt.rupiah(data.saldoAwal), style = MaterialTheme.typography.titleSmall, color = on)
                }
            }
            Column(Modifier.weight(1f)) {
                Text("Masuk", style = MaterialTheme.typography.labelSmall, color = on.copy(alpha = 0.8f))
                Text(Fmt.rupiah(data.totalMasuk), style = MaterialTheme.typography.titleSmall, color = on)
            }
            Column(Modifier.weight(1f)) {
                Text("Keluar", style = MaterialTheme.typography.labelSmall, color = on.copy(alpha = 0.8f))
                Text(Fmt.rupiah(data.totalKeluar), style = MaterialTheme.typography.titleSmall, color = on)
            }
        }
        if (data.isMonthly) {
            Spacer(Modifier.height(Spacing.sm))
            Text("Saldo keseluruhan " + Fmt.rupiah(data.saldoKeseluruhan), style = MaterialTheme.typography.labelSmall, color = on.copy(alpha = 0.8f))
        }
    }
}

@Composable
private fun DetailRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
        Text(label, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.weight(0.42f))
        Text(value, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(0.58f))
    }
}

@Composable
private fun TxForm(
    tx: IbuBetiTxDto,
    busy: Boolean,
    onDismiss: () -> Unit,
    onSave: (String, Boolean, Double, String, UploadImage?) -> Unit,
) {
    val context = LocalContext.current
    val isNew = tx.id <= 0
    var date by rememberSaveable { mutableStateOf(tx.dateOnly ?: LocalDate.now().toString()) }
    var income by rememberSaveable { mutableStateOf(isNew || tx.isIncome) }
    var amount by rememberSaveable { mutableStateOf(if (isNew) "" else java.math.BigDecimal.valueOf(tx.jumlah).stripTrailingZeros().toPlainString()) }
    var note by rememberSaveable { mutableStateOf(tx.keterangan.orEmpty()) }
    var bukti by remember { mutableStateOf<PickedBukti?>(null) }
    var errors by remember { mutableStateOf(emptyMap<String, String>()) }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        if (uri != null) {
            val (picked, err) = IbuBetiViewModel.readBukti(context, uri)
            bukti = picked ?: bukti
            errors = if (err != null) errors + ("bukti" to err) else errors - "bukti"
        }
    }

    FormSheet(title = if (isNew) "Mutasi Rekening Ibu Beti" else "Ubah mutasi", onDismiss = onDismiss) {
        Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            FilterChip(selected = income, onClick = { income = true }, label = { Text("Masuk") })
            FilterChip(selected = !income, onClick = { income = false }, label = { Text("Keluar") })
        }
        DateField(date, { date = it }, "Tanggal")
        SindenTextField(
            amount, { v -> amount = v.filter { it.isDigit() || it == ',' || it == '.' } }, "Jumlah (Rp)",
            error = errors["amount"], keyboardType = KeyboardType.Decimal,
            supporting = Fmt.parseAmount(amount)?.let { Fmt.rupiah(it) },
        )
        SindenTextField(note, { note = it }, "Keterangan", error = errors["note"], singleLine = false, minLines = 2)
        Spacer(Modifier.height(Spacing.sm))
        Row(verticalAlignment = Alignment.CenterVertically) {
            OutlinedButton(onClick = {
                context.expectExternalActivity()
                picker.launch(arrayOf("image/jpeg", "image/png", "application/pdf"))
            }) {
                Icon(Icons.Outlined.AttachFile, contentDescription = null)
                Spacer(Modifier.width(Spacing.xs))
                Text(if (bukti != null) "Ganti bukti" else if (!isNew && tx.hasBukti) "Ganti bukti" else "Lampirkan bukti")
            }
            if (bukti != null) {
                IconButton(onClick = { bukti = null }) { Icon(Icons.Outlined.Close, contentDescription = "Batalkan bukti") }
            }
        }
        Text(
            errors["bukti"] ?: bukti?.label ?: if (!isNew && tx.hasBukti) "Bukti lama tetap disimpan bila tidak diganti." else "Opsional · JPG, PNG, atau PDF maks. 5 MB",
            style = MaterialTheme.typography.bodySmall,
            color = if (errors["bukti"] != null) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
            maxLines = 2, overflow = TextOverflow.Ellipsis,
        )
        Spacer(Modifier.height(Spacing.lg))
        PrimaryButton("Simpan", loading = busy, onClick = {
            val value = Fmt.parseAmount(amount)
            errors = buildMap {
                if (value == null || value <= 0.0) put("amount", "Jumlah wajib diisi")
                if (note.isBlank()) put("note", "Keterangan wajib diisi")
            }
            if (errors.isEmpty() && value != null) onSave(date, income, value, note, bukti?.upload)
        })
    }
}

@Composable
private fun BuktiImageDialog(vm: IbuBetiViewModel, tx: IbuBetiTxDto, onDismiss: () -> Unit) {
    var bitmap by remember { mutableStateOf<Bitmap?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    LaunchedEffect(tx.id) { vm.loadImage(tx.id) { b, e -> bitmap = b; error = e } }
    AlertDialog(
        onDismissRequest = onDismiss,
        confirmButton = { TextButton(onClick = onDismiss) { Text("Tutup") } },
        title = { Text("Bukti transaksi", maxLines = 1, overflow = TextOverflow.Ellipsis) },
        text = {
            Box(Modifier.fillMaxWidth().heightIn(min = 160.dp), contentAlignment = Alignment.Center) {
                val b = bitmap
                when {
                    b != null -> Image(b.asImageBitmap(), contentDescription = "Foto bukti ${tx.keterangan.orEmpty()}", contentScale = ContentScale.Fit, modifier = Modifier.fillMaxWidth().heightIn(max = 480.dp))
                    error != null -> Text(error.orEmpty(), color = MaterialTheme.colorScheme.error, textAlign = TextAlign.Center)
                    else -> CircularProgressIndicator(Modifier.size(36.dp))
                }
            }
        },
    )
}
