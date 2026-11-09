package id.my.sisinden.app.ui.backup

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.util.LruCache
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Article
import androidx.compose.material.icons.rounded.AudioFile
import androidx.compose.material.icons.rounded.ChevronRight
import androidx.compose.material.icons.rounded.Description
import androidx.compose.material.icons.rounded.Download
import androidx.compose.material.icons.rounded.Folder
import androidx.compose.material.icons.rounded.FolderOpen
import androidx.compose.material.icons.rounded.FolderZip
import androidx.compose.material.icons.rounded.Home
import androidx.compose.material.icons.rounded.Image
import androidx.compose.material.icons.rounded.InsertDriveFile
import androidx.compose.material.icons.rounded.Movie
import androidx.compose.material.icons.rounded.OpenInNew
import androidx.compose.material.icons.rounded.PictureAsPdf
import androidx.compose.material.icons.rounded.SearchOff
import androidx.compose.material.icons.rounded.Visibility
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.security.expectExternalActivity
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.InfoRow
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LivelyIconTile
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.appear
import id.my.sisinden.app.core.ui.components.bouncyPress
import id.my.sisinden.app.core.ui.components.floating
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.ui.theme.TileGradient
import id.my.sisinden.app.core.ui.theme.TilePalette
import id.my.sisinden.app.data.model.BackupFilesResponse
import id.my.sisinden.app.data.model.BackupItemDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.FullscreenViewer
import id.my.sisinden.app.ui.common.LoadableViewModel
import id.my.sisinden.app.ui.common.PdfFileViewer
import id.my.sisinden.app.ui.common.PreviewLoad
import id.my.sisinden.app.ui.common.PreviewStateContent
import id.my.sisinden.app.ui.common.StreamPlayer
import id.my.sisinden.app.ui.common.TextFileViewer
import id.my.sisinden.app.ui.common.Zoomable
import id.my.sisinden.app.ui.common.openFileExternally
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.drop
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File

/** Cache thumbnail di memori (per id berkas) agar gulir daftar tetap ringan. */
private object BackupThumbCache {
    private val cache = object : LruCache<Int, ImageBitmap>(160) {}
    fun get(id: Int): ImageBitmap? = cache.get(id)
    fun put(id: Int, image: ImageBitmap) { cache.put(id, image) }
}

private fun decodeSampled(bytes: ByteArray, maxSide: Int): Bitmap? {
    val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
    BitmapFactory.decodeByteArray(bytes, 0, bytes.size, bounds)
    if (bounds.outWidth <= 0 || bounds.outHeight <= 0) return null
    var sample = 1
    while (bounds.outWidth / sample > maxSide || bounds.outHeight / sample > maxSide) sample *= 2
    return BitmapFactory.decodeByteArray(bytes, 0, bytes.size, BitmapFactory.Options().apply { inSampleSize = sample })
}

/** Ikon & warna ubin per jenis berkas. */
internal fun kindStyle(kind: String?): Pair<ImageVector, TileGradient> = when (kind) {
    "folder" -> Icons.Rounded.Folder to TilePalette.Orange
    "image", "raw" -> Icons.Rounded.Image to TilePalette.Teal
    "pdf" -> Icons.Rounded.PictureAsPdf to TilePalette.Rose
    "video" -> Icons.Rounded.Movie to TilePalette.Violet
    "audio" -> Icons.Rounded.AudioFile to TilePalette.Sky
    "document" -> Icons.Rounded.Description to TilePalette.Blue
    "text" -> Icons.Rounded.Article to TilePalette.Slate
    "archive" -> Icons.Rounded.FolderZip to TilePalette.Lime
    else -> Icons.Rounded.InsertDriveFile to TilePalette.Slate
}

/** Jenis yang bisa dipratinjau langsung di aplikasi. */
private val PREVIEWABLE = setOf("image", "raw", "pdf", "document", "video", "audio", "text")

/** Berkas yang selesai diunduh, menunggu dibuka atau disimpan. */
data class DownloadedFile(val item: BackupItemDto, val file: File, val saveAfter: Boolean)

@OptIn(FlowPreview::class)
class BackupFilesViewModel(
    val repository: SindenRepository,
    private val pcId: Int,
) : LoadableViewModel<BackupFilesResponse>() {
    val folderId = MutableStateFlow<Int?>(null)
    val search = MutableStateFlow("")

    private val _downloading = MutableStateFlow<Int?>(null)
    val downloading: StateFlow<Int?> = _downloading.asStateFlow()

    val downloaded = MutableStateFlow<DownloadedFile?>(null)

    init {
        load()
        viewModelScope.launch { search.drop(1).debounce(400).distinctUntilChanged().collect { reload() } }
    }

    override suspend fun fetch(): ApiResult<BackupFilesResponse> = repository.backupFiles(pcId, folderId.value, search.value)

    fun openFolder(id: Int?) {
        search.value = ""
        folderId.value = id
        reload()
    }

    fun download(item: BackupItemDto, saveAfter: Boolean) {
        if (_downloading.value != null) return
        _downloading.value = item.id
        viewModelScope.launch {
            when (val res = repository.downloadBackupFile(item.id, item.name ?: "berkas")) {
                is ApiResult.Success -> downloaded.value = DownloadedFile(item, res.data, saveAfter)
                is ApiResult.Failure -> messages.post(res.error.message)
            }
            _downloading.value = null
        }
    }

    suspend fun image(item: BackupItemDto, thumbnail: Boolean): ApiResult<ByteArray?> = repository.backupImage(item.id, thumbnail)
}

@Composable
fun BackupFilesScreen(
    repository: SindenRepository,
    pcId: Int,
    title: String,
    onBack: () -> Unit,
) {
    val context = LocalContext.current
    val vm: BackupFilesViewModel = viewModel(key = "backup-$pcId") { BackupFilesViewModel(repository, pcId) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val folderId by vm.folderId.collectAsStateWithLifecycle()
    val search by vm.search.collectAsStateWithLifecycle()
    val downloading by vm.downloading.collectAsStateWithLifecycle()
    val downloaded by vm.downloaded.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()
    CollectMessages(vm.messages, snackbar)

    var actionsFor by remember { mutableStateOf<BackupItemDto?>(null) }
    var previewing by remember { mutableStateOf<BackupItemDto?>(null) }

    val data = (state as? LoadState.Ready)?.data
    val crumbs = data?.breadcrumbs.orEmpty()

    // Tombol kembali: naik satu folder dulu sebelum keluar.
    BackHandler(enabled = folderId != null && previewing == null) {
        vm.openFolder(crumbs.dropLast(1).lastOrNull()?.id)
    }

    // Simpan ke perangkat lewat Storage Access Framework (tanpa izin penyimpanan luas).
    var pendingSave by remember { mutableStateOf<DownloadedFile?>(null) }
    val saveLauncher = rememberLauncherForActivityResult(ActivityResultContracts.CreateDocument("*/*")) { uri ->
        val target = pendingSave
        pendingSave = null
        if (uri == null || target == null) return@rememberLauncherForActivityResult
        scope.launch {
            val ok = withContext(Dispatchers.IO) {
                runCatching {
                    context.contentResolver.openOutputStream(uri)?.use { out -> target.file.inputStream().use { it.copyTo(out) } } != null
                }.getOrDefault(false)
            }
            snackbar.showSnackbar(if (ok) "Berkas tersimpan di perangkat." else "Gagal menyimpan berkas. Coba pilih lokasi lain.")
        }
    }

    // Selesai unduh: buka dengan aplikasi lain, atau tawarkan lokasi simpan.
    LaunchedEffect(downloaded) {
        val done = downloaded ?: return@LaunchedEffect
        vm.downloaded.value = null
        if (done.saveAfter) {
            pendingSave = done
            context.expectExternalActivity()
            saveLauncher.launch(done.item.name ?: "berkas")
        } else {
            openFileExternally(context, done.file, done.item.name)?.let { snackbar.showSnackbar(it) }
        }
    }

    SindenScaffold(
        title = title,
        subtitle = data?.pc?.owner?.label?.let { "Milik $it" } ?: "Isi penyimpanan",
        onBack = onBack,
        snackbarHostState = snackbar,
    ) { padding ->
        Column(Modifier.padding(padding)) {
            SearchField(search, { vm.search.value = it }, "Cari berkas di semua folder", Modifier.padding(horizontal = Spacing.screen))
            Spacer(Modifier.height(Spacing.sm))

            // Jejak folder
            Row(
                Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(horizontal = Spacing.screen),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                CrumbChip("Beranda", Icons.Rounded.Home, active = folderId == null && search.isBlank()) { vm.openFolder(null) }
                crumbs.forEach { c ->
                    Icon(Icons.Rounded.ChevronRight, contentDescription = null, modifier = Modifier.size(16.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant)
                    CrumbChip(c.name.orEmpty(), Icons.Rounded.FolderOpen, active = c.id == folderId) { vm.openFolder(c.id) }
                }
            }

            AnimatedVisibility(
                visible = data != null && !data.canManage,
                enter = fadeIn() + expandVertically(),
                exit = fadeOut() + shrinkVertically(),
            ) {
                Row(
                    Modifier.padding(horizontal = Spacing.screen, vertical = Spacing.xs).fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp)).background(MaterialTheme.colorScheme.primary.copy(alpha = 0.08f))
                        .padding(Spacing.sm),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(Icons.Rounded.Visibility, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(Spacing.sm))
                    Text("Mode lihat: Anda bisa melihat dan mengunduh berkas.", style = MaterialTheme.typography.bodySmall)
                }
            }
            Spacer(Modifier.height(Spacing.xs))

            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { res ->
                    val items = res.items.orEmpty()
                    val listKey = "${res.folderId}-${res.search}"
                    LazyColumn(
                        contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        if (items.isEmpty()) {
                            item(key = "empty-$listKey") {
                                Column(Modifier.fillMaxWidth().appear(key = listKey), horizontalAlignment = Alignment.CenterHorizontally) {
                                    Spacer(Modifier.height(Spacing.xl))
                                    LivelyIconTile(
                                        if (search.isNotBlank()) Icons.Rounded.SearchOff else Icons.Rounded.FolderOpen,
                                        if (search.isNotBlank()) TilePalette.Slate else TilePalette.Orange,
                                        size = 72.dp, iconSize = 36.dp, corner = 24.dp, modifier = Modifier.floating(), key = listKey,
                                    )
                                    EmptyState(
                                        if (search.isNotBlank()) "Tidak ditemukan" else "Folder kosong",
                                        if (search.isNotBlank()) "Tidak ada berkas yang cocok dengan \"$search\"." else "Belum ada berkas di folder ini.",
                                        icon = Icons.Rounded.Folder,
                                    )
                                }
                            }
                        } else {
                            itemsIndexed(items, key = { _, it -> "$listKey-${it.id}" }) { index, item ->
                                ListRowCard(
                                    title = item.name.orEmpty(),
                                    subtitle = if (item.isFolder) "${item.childCount ?: 0} item" else listOfNotNull(item.sizeHuman, item.dateHuman).joinToString(" · "),
                                    leading = { FileLeading(item, vm) },
                                    trailing = {
                                        when {
                                            downloading == item.id -> CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp)
                                            item.isFolder -> Icon(Icons.Rounded.ChevronRight, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                                        }
                                    },
                                    onClick = {
                                        when {
                                            item.isFolder -> vm.openFolder(item.id)
                                            item.kind in PREVIEWABLE -> previewing = item
                                            else -> actionsFor = item
                                        }
                                    },
                                    modifier = Modifier.appear(index, key = listKey).bouncyPress(),
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    actionsFor?.let { item ->
        FormSheet(title = item.name.orEmpty(), onDismiss = { actionsFor = null }) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                val (icon, gradient) = kindStyle(item.kind)
                LivelyIconTile(icon, gradient, size = 52.dp, iconSize = 26.dp, corner = 16.dp)
                Spacer(Modifier.width(Spacing.md))
                Column {
                    Text("Belum bisa dipratinjau di aplikasi", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                    Text("Buka dengan aplikasi lain atau simpan ke perangkat.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
            Spacer(Modifier.height(Spacing.md))
            InfoRow("Ukuran", item.sizeHuman)
            InfoRow("Diunggah", item.dateHuman)
            Spacer(Modifier.height(Spacing.lg))
            OutlinedButton(onClick = { actionsFor = null; vm.download(item, saveAfter = false) }, enabled = downloading == null, modifier = Modifier.fillMaxWidth().bouncyPress()) {
                Icon(Icons.Rounded.OpenInNew, contentDescription = null)
                Spacer(Modifier.width(Spacing.sm))
                Text("Buka dengan aplikasi lain")
            }
            Spacer(Modifier.height(Spacing.sm))
            OutlinedButton(onClick = { actionsFor = null; vm.download(item, saveAfter = true) }, enabled = downloading == null, modifier = Modifier.fillMaxWidth().bouncyPress()) {
                Icon(Icons.Rounded.Download, contentDescription = null)
                Spacer(Modifier.width(Spacing.sm))
                Text("Simpan ke perangkat")
            }
        }
    }

    previewing?.let { item ->
        FilePreview(
            item = item,
            vm = vm,
            onDismiss = { previewing = null },
            onOpen = { vm.download(item, saveAfter = false) },
            onSave = { vm.download(item, saveAfter = true) },
            busy = downloading == item.id,
        )
    }
}

@Composable
private fun CrumbChip(label: String, icon: ImageVector, active: Boolean, onClick: () -> Unit) {
    val color = if (active) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.primary
    Row(
        Modifier.clip(RoundedCornerShape(10.dp))
            .background(if (active) MaterialTheme.colorScheme.primary.copy(alpha = 0.08f) else androidx.compose.ui.graphics.Color.Transparent)
            .clickable(enabled = !active, onClick = onClick)
            .padding(horizontal = 8.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, contentDescription = null, tint = color, modifier = Modifier.size(16.dp))
        Spacer(Modifier.width(4.dp))
        Text(label, style = MaterialTheme.typography.labelLarge, color = color, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
private fun FileLeading(item: BackupItemDto, vm: BackupFilesViewModel) {
    val (icon, gradient) = kindStyle(item.kind)
    if (!item.hasThumbnail) {
        LivelyIconTile(icon, gradient, key = item.id)
        return
    }
    val image by produceState(initialValue = BackupThumbCache.get(item.id), item.id) {
        if (value != null) return@produceState
        // Server bisa membalas 202 saat thumbnail masih dibuat: coba lagi beberapa kali.
        repeat(4) { attempt ->
            when (val res = vm.image(item, thumbnail = true)) {
                is ApiResult.Success -> {
                    val bytes = res.data
                    if (bytes != null) {
                        val bmp = withContext(Dispatchers.Default) { decodeSampled(bytes, 256) }
                        if (bmp != null) {
                            val img = bmp.asImageBitmap()
                            BackupThumbCache.put(item.id, img)
                            value = img
                        }
                        return@produceState
                    }
                }
                is ApiResult.Failure -> return@produceState
            }
            delay(1_200L * (attempt + 1))
        }
    }
    val img = image
    if (img != null) {
        Image(img, contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.size(44.dp).clip(RoundedCornerShape(14.dp)))
    } else {
        LivelyIconTile(icon, gradient, key = item.id)
    }
}

/** Pratinjau penuh sesuai jenis berkas (seperti pratinjau di web). */
@Composable
private fun FilePreview(
    item: BackupItemDto,
    vm: BackupFilesViewModel,
    onDismiss: () -> Unit,
    onOpen: () -> Unit,
    onSave: () -> Unit,
    busy: Boolean,
) {
    val dark = item.kind in setOf("image", "raw", "video", "audio")
    FullscreenViewer(
        title = item.name.orEmpty(),
        onDismiss = onDismiss,
        dark = dark,
        actions = {
            if (busy) {
                CircularProgressIndicator(Modifier.size(20.dp).padding(end = 4.dp), strokeWidth = 2.dp)
            } else {
                IconButton(onClick = onOpen) { Icon(Icons.Rounded.OpenInNew, contentDescription = "Buka dengan aplikasi lain") }
                IconButton(onClick = onSave) { Icon(Icons.Rounded.Download, contentDescription = "Simpan ke perangkat") }
            }
        },
    ) {
        when (item.kind) {
            "image", "raw" -> {
                val load by produceState<PreviewLoad<ImageBitmap>>(PreviewLoad.Loading, item.id) {
                    value = when (val res = vm.image(item, thumbnail = false)) {
                        is ApiResult.Success -> res.data?.let { withContext(Dispatchers.Default) { decodeSampled(it, 2560) } }
                            ?.let { PreviewLoad.Ready(it.asImageBitmap()) }
                            ?: PreviewLoad.Failed("Gambar tidak dapat ditampilkan. Coba buka dengan aplikasi lain.")
                        is ApiResult.Failure -> PreviewLoad.Failed(res.error.message)
                    }
                }
                PreviewStateContent(load, dark = true) { img ->
                    Zoomable { Image(img, contentDescription = item.name, contentScale = ContentScale.Fit, modifier = Modifier.fillMaxSize()) }
                }
            }
            "pdf", "document" -> {
                val load by produceState<PreviewLoad<File>>(PreviewLoad.Loading, item.id) {
                    value = when (val res = vm.repository.backupPdf(item.id, item.name ?: "dokumen", office = item.kind == "document")) {
                        is ApiResult.Success -> PreviewLoad.Ready(res.data)
                        is ApiResult.Failure -> PreviewLoad.Failed(res.error.message)
                    }
                }
                PreviewStateContent(load, dark = false) { PdfFileViewer(it) }
            }
            "video", "audio" -> {
                val source by produceState<Pair<String, Map<String, String>>?>(null, item.id) { value = vm.repository.backupStreamSource(item.id) }
                source?.let { (url, headers) -> StreamPlayer(url, headers, audioOnly = item.kind == "audio") }
                    ?: Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
            }
            "text" -> {
                val load by produceState<PreviewLoad<Pair<String, Boolean>>>(PreviewLoad.Loading, item.id) {
                    value = when (val res = vm.repository.backupText(item.id)) {
                        is ApiResult.Success -> PreviewLoad.Ready(res.data)
                        is ApiResult.Failure -> PreviewLoad.Failed(res.error.message)
                    }
                }
                PreviewStateContent(load, dark = false) { (text, truncated) -> TextFileViewer(text, truncated) }
            }
        }
    }
}
