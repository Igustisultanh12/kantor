package id.my.sisinden.app.ui.backup

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.rounded.Block
import androidx.compose.material.icons.rounded.CloudQueue
import androidx.compose.material.icons.rounded.Groups
import androidx.compose.material.icons.rounded.HourglassTop
import androidx.compose.material.icons.rounded.Lock
import androidx.compose.material.icons.rounded.LockOpen
import androidx.compose.material.icons.rounded.Sms
import androidx.compose.material.icons.rounded.Storage
import androidx.compose.material.icons.rounded.VerifiedUser
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import id.my.sisinden.app.core.ui.components.LivelyIconTile
import id.my.sisinden.app.core.ui.components.PulseDot
import id.my.sisinden.app.core.ui.components.appear
import id.my.sisinden.app.core.ui.components.bouncyPress
import id.my.sisinden.app.core.ui.components.floating
import id.my.sisinden.app.core.ui.theme.TileGradient
import id.my.sisinden.app.core.ui.theme.TilePalette
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FilterChipRow
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.InfoRow
import id.my.sisinden.app.core.ui.components.InitialsAvatar
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SectionHeader
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.model.BackupAccessRequestDto
import id.my.sisinden.app.data.model.BackupAccessResponse
import id.my.sisinden.app.data.model.BackupAccessState
import id.my.sisinden.app.data.model.BackupOverviewResponse
import id.my.sisinden.app.data.model.BackupPcDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

enum class BackupTab(val label: String) { MINE("Penyimpanan Saya"), OTHERS("Rekan"), ACCESS("Izin Akses") }

/** Permintaan yang sedang menunggu kode WhatsApp dari pengguna. */
data class PendingCode(val request: BackupAccessRequestDto, val pcName: String, val sentAtMillis: Long = System.currentTimeMillis())

class BackupViewModel(private val repository: SindenRepository) : LoadableViewModel<BackupOverviewResponse>() {
    val tab = MutableStateFlow(BackupTab.MINE)

    private val _working = MutableStateFlow(false)
    val working: StateFlow<Boolean> = _working.asStateFlow()

    /** Lembar kode WA yang sedang terbuka (null = tertutup). */
    val pendingCode = MutableStateFlow<PendingCode?>(null)

    init { load() }

    override suspend fun fetch(): ApiResult<BackupOverviewResponse> = repository.backupOverview()

    private fun run(
        successMessage: String? = null,
        block: suspend () -> ApiResult<BackupAccessResponse>,
        onSuccess: (BackupAccessResponse) -> Unit = {},
        onError: (String) -> Unit = { messages.post(it) },
    ) {
        if (_working.value) return
        _working.value = true
        viewModelScope.launch {
            when (val res = block()) {
                is ApiResult.Success -> {
                    onSuccess(res.data)
                    (successMessage ?: res.data.message)?.takeIf { it.isNotBlank() }?.let(messages::post)
                    load()
                }
                is ApiResult.Failure -> onError(res.error.message)
            }
            _working.value = false
        }
    }

    fun requestAccess(pc: BackupPcDto, reason: String, onSent: () -> Unit) = run(
        block = { repository.backupRequestAccess(pc.id, reason) },
        onSuccess = { res ->
            onSent()
            res.request?.let { pendingCode.value = PendingCode(it, pc.name.orEmpty()) }
        },
    )

    /** Lanjutkan permintaan yang kodenya belum dimasukkan (kirim kode baru bila perlu). */
    fun continueRequest(pc: BackupPcDto) {
        val id = pc.accessRequestId
        if (id == null) return
        pendingCode.value = PendingCode(BackupAccessRequestDto(id = id, pcId = pc.id, pcName = pc.name, status = BackupAccessState.VERIFYING), pc.name.orEmpty(), sentAtMillis = 0L)
    }

    fun resendCode(onError: (String) -> Unit) {
        val current = pendingCode.value ?: return
        run(
            block = { repository.backupResendCode(current.request.id) },
            onSuccess = { res -> res.request?.let { pendingCode.value = PendingCode(it, current.pcName) } },
            onError = onError,
        )
    }

    fun verifyCode(code: String, onError: (String) -> Unit) {
        val current = pendingCode.value ?: return
        run(
            block = { repository.backupVerifyAccess(current.request.id, code) },
            onSuccess = { pendingCode.value = null },
            onError = onError,
        )
    }

    fun decide(req: BackupAccessRequestDto, approve: Boolean, note: String, onDone: () -> Unit) =
        run(block = { repository.backupDecide(req.id, approve, note) }, onSuccess = { onDone() })

    fun withdraw(requestId: Int, onDone: () -> Unit) =
        run(block = { repository.backupWithdrawAccess(requestId) }, onSuccess = { onDone() })
}

@Composable
fun BackupScreen(
    repository: SindenRepository,
    onBack: () -> Unit,
    onOpenPc: (pcId: Int, title: String) -> Unit,
) {
    val vm: BackupViewModel = viewModel { BackupViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val working by vm.working.collectAsStateWithLifecycle()
    val tab by vm.tab.collectAsStateWithLifecycle()
    val pendingCode by vm.pendingCode.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)

    var search by rememberSaveable { mutableStateOf("") }
    var askFor by remember { mutableStateOf<BackupPcDto?>(null) }
    var waitingFor by remember { mutableStateOf<BackupPcDto?>(null) }
    var deciding by remember { mutableStateOf<Pair<BackupAccessRequestDto, Boolean>?>(null) }
    var revoking by remember { mutableStateOf<BackupAccessRequestDto?>(null) }

    val data = (state as? id.my.sisinden.app.core.ui.components.LoadState.Ready)?.data
    val incomingCount = data?.incomingRequests.orEmpty().size

    val openPc: (BackupPcDto) -> Unit = { pc ->
        when (pc.accessState) {
            BackupAccessState.OWNER, BackupAccessState.ADMIN, BackupAccessState.GRANTED ->
                onOpenPc(pc.id, pc.name ?: "Penyimpanan")
            BackupAccessState.VERIFYING -> vm.continueRequest(pc)
            BackupAccessState.PENDING -> waitingFor = pc
            else -> askFor = pc
        }
    }

    SindenScaffold(
        title = "Backup",
        subtitle = "Penyimpanan berkas kantor",
        onBack = onBack,
        snackbarHostState = snackbar,
    ) { padding ->
        Column(Modifier.padding(padding)) {
            FilterChipRow(
                options = BackupTab.entries.toList(),
                selected = tab,
                label = { if (it == BackupTab.ACCESS && incomingCount > 0) "${it.label} ($incomingCount)" else it.label },
                onSelect = { vm.tab.value = it },
            )
            Spacer(Modifier.height(Spacing.sm))
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { overview ->
                    LazyColumn(
                        contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        item(key = "hero") { BackupHero(overview, tab) }
                        when (tab) {
                            BackupTab.MINE -> {
                                val mine = overview.myPcs.orEmpty()
                                if (mine.isEmpty()) {
                                    item {
                                        EmptyState(
                                            "Belum punya ruang penyimpanan",
                                            "Ajukan ruang penyimpanan backup ke Admin lewat menu Backup di web SINDEN.",
                                            icon = Icons.Rounded.CloudQueue,
                                        )
                                    }
                                } else {
                                    itemsIndexed(mine, key = { _, it -> "mine-${it.id}" }) { i, pc ->
                                        MyPcCard(pc, Modifier.appear(i, key = tab).bouncyPress()) { onOpenPc(pc.id, pc.name ?: "Penyimpanan") }
                                    }
                                }
                            }

                            BackupTab.OTHERS -> {
                                item {
                                    Text(
                                        "Buka penyimpanan rekan setelah pemiliknya memberi izin. Kode verifikasi dikirim ke WhatsApp Anda saat meminta akses.",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                    Spacer(Modifier.height(Spacing.sm))
                                    SearchField(search, { search = it }, "Cari nama rekan atau penyimpanan")
                                }
                                val q = search.trim().lowercase()
                                val others = overview.otherPcs.orEmpty().filter {
                                    q.isEmpty() || it.name.orEmpty().lowercase().contains(q) || it.owner?.label.orEmpty().lowercase().contains(q)
                                }
                                if (others.isEmpty()) {
                                    item { EmptyState("Tidak ada penyimpanan", "Belum ada penyimpanan rekan yang cocok.", icon = Icons.Rounded.Groups) }
                                } else {
                                    itemsIndexed(others, key = { _, it -> "other-${it.id}" }) { i, pc ->
                                        val (chip, tone) = accessChip(pc.accessState)
                                        ListRowCard(
                                            title = pc.owner?.label ?: "-",
                                            subtitle = pc.name,
                                            supporting = actionHint(pc.accessState),
                                            leading = { LivelyIconTile(accessIcon(pc.accessState), accessGradient(pc.accessState), key = "${pc.id}-${pc.accessState}") },
                                            trailing = { StatusChip(chip, tone) },
                                            onClick = { openPc(pc) },
                                            modifier = Modifier.appear(i, key = tab).bouncyPress(),
                                        )
                                    }
                                }
                            }

                            BackupTab.ACCESS -> {
                                val incoming = overview.incomingRequests.orEmpty()
                                val grants = overview.activeGrants.orEmpty()
                                item { SectionHeader("Menunggu persetujuan Anda", subtitle = "Rekan yang ingin melihat penyimpanan Anda") }
                                if (incoming.isEmpty()) {
                                    item { Text("Tidak ada permintaan baru.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                                } else {
                                    itemsIndexed(incoming, key = { _, it -> "in-${it.id}" }) { i, req ->
                                        IncomingRequestCard(
                                            req = req,
                                            busy = working,
                                            onApprove = { deciding = req to true },
                                            onReject = { deciding = req to false },
                                            modifier = Modifier.appear(i, key = tab),
                                        )
                                    }
                                }
                                item {
                                    Spacer(Modifier.height(Spacing.md))
                                    SectionHeader("Sudah diberi akses", subtitle = "Hanya bisa melihat dan mengunduh")
                                }
                                if (grants.isEmpty()) {
                                    item { Text("Belum ada rekan yang diberi akses.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                                } else {
                                    itemsIndexed(grants, key = { _, it -> "gr-${it.id}" }) { i, g ->
                                        ListRowCard(
                                            title = g.requester?.label ?: "-",
                                            subtitle = listOfNotNull(g.pcName, g.decidedHuman?.let { "sejak $it" }).joinToString(" · "),
                                            leading = { InitialsAvatar(initials(g.requester?.name), size = 40.dp) },
                                            trailing = {
                                                TextButton(onClick = { revoking = g }) {
                                                    Text("Cabut", color = MaterialTheme.colorScheme.error)
                                                }
                                            },
                                            modifier = Modifier.appear(i + incoming.size + 1, key = tab),
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // ---- Minta akses: isi keperluan lalu kirim kode WA
    askFor?.let { pc ->
        var reason by rememberSaveable { mutableStateOf("") }
        FormSheet(title = "Minta akses penyimpanan", onDismiss = { askFor = null }) {
            Text(
                "Anda meminta izin melihat isi \"${pc.name.orEmpty()}\" milik ${pc.owner?.label.orEmpty()}.",
                style = MaterialTheme.typography.bodyMedium,
            )
            Spacer(Modifier.height(Spacing.xs))
            Text(
                "Kami kirim kode verifikasi ke WhatsApp Anda. Setelah kode dimasukkan, permintaan diteruskan ke pemilik. Anda akan diberi tahu lewat WhatsApp dan aplikasi begitu disetujui atau ditolak.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            if (pc.accessState == BackupAccessState.REJECTED) {
                Spacer(Modifier.height(Spacing.xs))
                Text("Permintaan Anda sebelumnya ditolak. Anda boleh mengajukan lagi.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
            }
            Spacer(Modifier.height(Spacing.md))
            SindenTextField(reason, { reason = it.take(500) }, "Keperluan (opsional)", placeholder = "Contoh: perlu file laporan bulan Oktober", singleLine = false, minLines = 2)
            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton("Kirim kode WhatsApp", loading = working, onClick = { vm.requestAccess(pc, reason) { askFor = null } })
        }
    }

    // ---- Masukkan kode WA
    pendingCode?.let { pending -> CodeSheet(pending = pending, working = working, vm = vm) }

    // ---- Menunggu persetujuan
    waitingFor?.let { pc ->
        FormSheet(title = "Menunggu persetujuan", onDismiss = { waitingFor = null }) {
            InfoRow("Penyimpanan", pc.name)
            InfoRow("Pemilik", pc.owner?.label)
            Spacer(Modifier.height(Spacing.sm))
            Text(
                "Permintaan Anda sudah diteruskan ke pemilik. Anda akan mendapat pemberitahuan lewat WhatsApp dan aplikasi.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(Spacing.lg))
            OutlinedButton(
                onClick = { pc.accessRequestId?.let { id -> vm.withdraw(id) { waitingFor = null } } },
                enabled = !working,
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Batalkan permintaan", color = MaterialTheme.colorScheme.error) }
        }
    }

    // ---- Setujui / tolak
    deciding?.let { (req, approve) ->
        var note by rememberSaveable(req.id, approve) { mutableStateOf("") }
        FormSheet(title = if (approve) "Beri akses?" else "Tolak permintaan?", onDismiss = { deciding = null }) {
            Text(
                if (approve) "${req.requester?.label.orEmpty()} akan bisa melihat dan mengunduh isi \"${req.pcName.orEmpty()}\". Ia tidak bisa mengubah atau menghapus berkas. Akses bisa Anda cabut kapan saja."
                else "Permintaan ${req.requester?.label.orEmpty()} untuk \"${req.pcName.orEmpty()}\" akan ditolak.",
                style = MaterialTheme.typography.bodyMedium,
            )
            Spacer(Modifier.height(Spacing.md))
            SindenTextField(note, { note = it.take(500) }, "Pesan untuk rekan (opsional)", singleLine = false, minLines = 2)
            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton(
                if (approve) "Ya, beri akses" else "Ya, tolak",
                loading = working,
                onClick = { vm.decide(req, approve, note) { deciding = null } },
            )
        }
    }

    // ---- Cabut akses
    revoking?.let { g ->
        ConfirmDialog(
            title = "Cabut akses?",
            message = "${g.requester?.label.orEmpty()} tidak akan bisa membuka \"${g.pcName.orEmpty()}\" lagi.",
            confirmLabel = "Cabut",
            destructive = true,
            loading = working,
            onDismiss = { revoking = null },
            onConfirm = { vm.withdraw(g.id) { revoking = null } },
        )
    }
}

@Composable
private fun CodeSheet(pending: PendingCode, working: Boolean, vm: BackupViewModel) {
    var code by rememberSaveable(pending.request.id) { mutableStateOf("") }
    var error by remember(pending.request.id) { mutableStateOf<String?>(null) }

    // Hitung mundur kirim ulang (60 detik sejak kode terkirim).
    val initialWait = if (pending.sentAtMillis == 0L) 0 else pending.request.resendIn.coerceAtLeast(0)
    var resendIn by remember(pending) { mutableIntStateOf(initialWait) }
    LaunchedEffect(pending) {
        while (resendIn > 0) {
            delay(1_000)
            resendIn--
        }
    }

    FormSheet(title = "Masukkan kode WhatsApp", onDismiss = { vm.pendingCode.value = null }) {
        val target = pending.request.phoneHint?.let { "WhatsApp $it" } ?: "WhatsApp Anda"
        Text(
            if (pending.sentAtMillis == 0L) "Masukkan kode 6 digit yang sudah dikirim ke $target untuk \"${pending.pcName}\". Kode berlaku 10 menit; minta kode baru bila sudah lewat."
            else "Kode 6 digit sudah dikirim ke $target. Kode berlaku 10 menit.",
            style = MaterialTheme.typography.bodyMedium,
        )
        Spacer(Modifier.height(Spacing.md))
        SindenTextField(
            value = code,
            onValueChange = { v -> code = v.filter { it.isDigit() }.take(6); error = null },
            label = "Kode verifikasi",
            placeholder = "______",
            error = error,
            keyboardType = KeyboardType.NumberPassword,
        )
        Spacer(Modifier.height(Spacing.lg))
        PrimaryButton("Verifikasi", loading = working, enabled = code.length == 6, onClick = {
            vm.verifyCode(code) { msg -> error = msg }
        })
        Spacer(Modifier.height(Spacing.xs))
        TextButton(
            onClick = { vm.resendCode { msg -> error = msg } },
            enabled = !working && resendIn == 0,
            modifier = Modifier.fillMaxWidth(),
        ) {
            Text(if (resendIn > 0) "Kirim ulang kode ($resendIn dtk)" else "Kirim ulang kode", textAlign = TextAlign.Center)
        }
    }
}

/** Kartu pembuka bergradasi dengan ikon awan mengambang dan ringkasan. */
@Composable
private fun BackupHero(overview: BackupOverviewResponse, tab: BackupTab) {
    val mine = overview.myPcs.orEmpty().size
    val granted = overview.otherPcs.orEmpty().count { it.accessState == BackupAccessState.GRANTED }
    val incoming = overview.incomingRequests.orEmpty().size
    Box(
        Modifier.fillMaxWidth().appear(key = "hero")
            .clip(RoundedCornerShape(24.dp))
            .background(Brush.linearGradient(listOf(TilePalette.Blue.top, TilePalette.Violet.bottom)))
            .padding(Spacing.lg),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(
                    when (tab) {
                        BackupTab.MINE -> "Penyimpanan Anda"
                        BackupTab.OTHERS -> "Penyimpanan rekan"
                        BackupTab.ACCESS -> "Izin akses"
                    },
                    style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, color = Color.White,
                )
                Spacer(Modifier.height(Spacing.xxs))
                Text(
                    when (tab) {
                        BackupTab.MINE -> "$mine ruang penyimpanan"
                        BackupTab.OTHERS -> "$granted penyimpanan rekan bisa Anda buka"
                        BackupTab.ACCESS -> if (incoming > 0) "$incoming permintaan menunggu keputusan Anda" else "Tidak ada permintaan baru"
                    },
                    style = MaterialTheme.typography.bodySmall, color = Color.White.copy(alpha = 0.85f),
                )
            }
            Box(contentAlignment = Alignment.TopEnd) {
                LivelyIconTile(
                    when (tab) {
                        BackupTab.MINE -> Icons.Rounded.CloudQueue
                        BackupTab.OTHERS -> Icons.Rounded.Groups
                        BackupTab.ACCESS -> Icons.Rounded.VerifiedUser
                    },
                    TileGradient(Color.White.copy(alpha = 0.35f), Color.White.copy(alpha = 0.12f)),
                    size = 56.dp, iconSize = 30.dp, corner = 18.dp, key = tab,
                    modifier = Modifier.floating(),
                )
                if (incoming > 0) PulseDot(MaterialTheme.colorScheme.error, size = 8.dp)
            }
        }
    }
}

private fun accessIcon(state: String?): ImageVector = when (state) {
    BackupAccessState.GRANTED, BackupAccessState.ADMIN -> Icons.Rounded.LockOpen
    BackupAccessState.PENDING -> Icons.Rounded.HourglassTop
    BackupAccessState.VERIFYING -> Icons.Rounded.Sms
    BackupAccessState.REJECTED -> Icons.Rounded.Block
    else -> Icons.Rounded.Lock
}

private fun accessGradient(state: String?): TileGradient = when (state) {
    BackupAccessState.GRANTED, BackupAccessState.ADMIN -> TilePalette.Teal
    BackupAccessState.PENDING -> TilePalette.Orange
    BackupAccessState.VERIFYING -> TilePalette.Sky
    BackupAccessState.REJECTED -> TilePalette.Rose
    else -> TilePalette.Slate
}

@Composable
private fun MyPcCard(pc: BackupPcDto, modifier: Modifier = Modifier, onOpen: () -> Unit) {
    val target = (pc.usagePercentage / 100.0).toFloat().coerceIn(0f, 1f)
    var started by remember { mutableStateOf(false) }
    LaunchedEffect(pc.id) { started = true }
    val progress by animateFloatAsState(if (started) target else 0f, tween(900), label = "usage")
    SindenCard(modifier.fillMaxWidth(), onClick = onOpen) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            LivelyIconTile(Icons.Rounded.Storage, TilePalette.Blue, key = pc.id)
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                Text(pc.name.orEmpty(), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                Text("Ketuk untuk membuka", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Text("${pc.usagePercentage}%", style = MaterialTheme.typography.labelLarge, color = if (pc.usagePercentage > 90) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.primary)
        }
        Spacer(Modifier.height(Spacing.md))
        LinearProgressIndicator(
            progress = { progress },
            modifier = Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(8.dp)),
        )
        Spacer(Modifier.height(Spacing.xs))
        Text(
            "${pc.usageHuman.orEmpty()} dari ${pc.quotaHuman.orEmpty()} terpakai",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun IncomingRequestCard(
    req: BackupAccessRequestDto,
    busy: Boolean,
    onApprove: () -> Unit,
    onReject: () -> Unit,
    modifier: Modifier = Modifier,
) {
    SindenCard(modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(contentAlignment = Alignment.TopEnd) {
                InitialsAvatar(initials(req.requester?.name), size = 40.dp)
                PulseDot(TilePalette.Orange.bottom, size = 7.dp)
            }
            Spacer(Modifier.width(Spacing.sm))
            Column(Modifier.weight(1f)) {
                Text(req.requester?.label.orEmpty(), style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
                Text(
                    listOfNotNull(req.pcName, req.createdHuman).joinToString(" · "),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        req.reason?.takeIf { it.isNotBlank() }?.let {
            Spacer(Modifier.height(Spacing.xs))
            Text("\"$it\"", style = MaterialTheme.typography.bodySmall, fontSize = 13.sp)
        }
        Spacer(Modifier.height(Spacing.md))
        Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            OutlinedButton(onClick = onReject, enabled = !busy, modifier = Modifier.weight(1f).bouncyPress()) {
                Text("Tolak", color = MaterialTheme.colorScheme.error)
            }
            androidx.compose.material3.Button(onClick = onApprove, enabled = !busy, modifier = Modifier.weight(1f).bouncyPress()) {
                Text("Setujui")
            }
        }
    }
}

private fun accessChip(state: String?): Pair<String, Tone> = when (state) {
    BackupAccessState.GRANTED -> "Diizinkan" to Tone.SUCCESS
    BackupAccessState.ADMIN -> "Admin" to Tone.INFO
    BackupAccessState.PENDING -> "Menunggu" to Tone.WARNING
    BackupAccessState.VERIFYING -> "Kode WA" to Tone.INFO
    BackupAccessState.REJECTED -> "Ditolak" to Tone.DANGER
    else -> "Terkunci" to Tone.NEUTRAL
}

private fun actionHint(state: String?): String = when (state) {
    BackupAccessState.GRANTED, BackupAccessState.ADMIN -> "Ketuk untuk membuka"
    BackupAccessState.PENDING -> "Menunggu persetujuan pemilik"
    BackupAccessState.VERIFYING -> "Ketuk untuk memasukkan kode WhatsApp"
    BackupAccessState.REJECTED -> "Ketuk untuk meminta akses lagi"
    else -> "Ketuk untuk meminta akses"
}

internal fun initials(name: String?): String =
    name.orEmpty().split(" ").filter { it.isNotBlank() }.take(2).joinToString("") { it.take(1) }.uppercase().ifEmpty { "?" }
