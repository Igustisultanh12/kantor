package id.my.sisinden.app.ui.backup

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.util.LruCache
import android.webkit.MimeTypeMap
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.gestures.rememberTransformableState
import androidx.compose.foundation.gestures.transformable
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
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AudioFile
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Download
import androidx.compose.material.icons.outlined.Folder
import androidx.compose.material.icons.outlined.FolderZip
import androidx.compose.material.icons.outlined.Image
import androidx.compose.material.icons.outlined.InsertDriveFile
import androidx.compose.material.icons.outlined.Movie
import androidx.compose.material.icons.outlined.OpenInNew
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material3.CircularProgressIndicator
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
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.core.content.FileProvider
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.security.expectExternalActivity
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.InfoRow
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.model.BackupFilesResponse
import id.my.sisinden.app.data.model.BackupItemDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
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

/** Berkas yang selesai diunduh, menunggu dibuka atau disimpan. */
data class DownloadedFile(val item: BackupItemDto, val file: File, val saveAfter: Boolean)

@OptIn(FlowPreview::class)
class BackupFilesViewModel(
    private val repository: SindenRepository,
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

private fun mimeOf(name: String?): String {
    val ext = name.orEmpty().substringAfterLast('.', "").lowercase()
    if (ext == "arw") return "image/x-sony-arw"
    return MimeTypeMap.getSingleton().getMimeTypeFromExtension(ext) ?: "application/octet-stream"
}

private fun kindIcon(item: BackupItemDto): ImageVector = when (item.kind) {
    "folder" -> Icons.Outlined.Folder
    "image", "raw" -> Icons.Outlined.Image
    "pdf" -> Icons.Outlined.PictureAsPdf
    "video" -> Icons.Outlined.Movie
    "audio" -> Icons.Outlined.AudioFile
    "document" -> Icons.Outlined.Description
    "archive" -> Icons.Outlined.FolderZip
    else -> Icons.Outlined.InsertDriveFile
}

private fun openExternally(context: Context, file: File, name: String?): String? {
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    val intent = Intent(Intent.ACTION_VIEW).apply {
        setDataAndType(uri, mimeOf(name))
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
    }
    return try {
        context.expectExternalActivity()
        context.startActivity(Intent.createChooser(intent, "Buka dengan").addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
        null
    } catch (_: ActivityNotFoundException) {
        "Tidak ada aplikasi untuk membuka berkas ini. Simpan ke perangkat lalu buka dari pengelola berkas."
    }
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

    val data = (state as? id.my.sisinden.app.core.ui.components.LoadState.Ready)?.data
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
            openExternally(context, done.file, done.item.name)?.let { snackbar.showSnackbar(it) }
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
                CrumbChip("Beranda", active = folderId == null && search.isBlank()) { vm.openFolder(null) }
                crumbs.forEach { c ->
                    Icon(Icons.Outlined.ChevronRight, contentDescription = null, modifier = Modifier.size(16.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant)
                    CrumbChip(c.name.orEmpty(), active = c.id == folderId) { vm.openFolder(c.id) }
                }
            }

            if (data != null && !data.canManage) {
                Row(
                    Modifier.padding(horizontal = Spacing.screen, vertical = Spacing.xs).fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp)).background(MaterialTheme.colorScheme.primary.copy(alpha = 0.08f))
                        .padding(Spacing.sm),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(Icons.Outlined.Visibility, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(Spacing.sm))
                    Text("Mode lihat: Anda bisa melihat dan mengunduh berkas.", style = MaterialTheme.typography.bodySmall)
                }
            }
            Spacer(Modifier.height(Spacing.xs))

            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { res ->
                    val items = res.items.orEmpty()
                    LazyColumn(
                        contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        if (items.isEmpty()) {
                            item {
                                EmptyState(
                                    if (search.isNotBlank()) "Tidak ditemukan" else "Folder kosong",
                                    if (search.isNotBlank()) "Tidak ada berkas yang cocok dengan \"$search\"." else "Belum ada berkas di folder ini.",
                                    icon = Icons.Outlined.Folder,
                                )
                            }
                        } else {
                            items(items, key = { it.id }) { item ->
                                ListRowCard(
                                    title = item.name.orEmpty(),
                                    subtitle = if (item.isFolder) "${item.childCount ?: 0} item" else listOfNotNull(item.sizeHuman, item.dateHuman).joinToString(" · "),
                                    leading = { FileLeading(item, vm) },
                                    trailing = {
                                        if (downloading == item.id) CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp)
                                        else if (item.isFolder) Icon(Icons.Outlined.ChevronRight, contentDescription = null)
                                    },
                                    onClick = {
                                        when {
                                            item.isFolder -> vm.openFolder(item.id)
                                            item.isImage -> previewing = item
                                            else -> actionsFor = item
                                        }
                                    },
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
            InfoRow("Ukuran", item.sizeHuman)
            InfoRow("Diunggah", item.dateHuman)
            Spacer(Modifier.height(Spacing.lg))
            OutlinedButton(onClick = { actionsFor = null; vm.download(item, saveAfter = false) }, enabled = downloading == null, modifier = Modifier.fillMaxWidth()) {
                Icon(Icons.Outlined.OpenInNew, contentDescription = null)
                Spacer(Modifier.width(Spacing.sm))
                Text("Buka dengan aplikasi lain")
            }
            Spacer(Modifier.height(Spacing.sm))
            OutlinedButton(onClick = { actionsFor = null; vm.download(item, saveAfter = true) }, enabled = downloading == null, modifier = Modifier.fillMaxWidth()) {
                Icon(Icons.Outlined.Download, contentDescription = null)
                Spacer(Modifier.width(Spacing.sm))
                Text("Simpan ke perangkat")
            }
        }
    }

    previewing?.let { item ->
        ImagePreviewDialog(
            item = item,
            vm = vm,
            onDismiss = { previewing = null },
            onOpen = { previewing = null; vm.download(item, saveAfter = false) },
            onSave = { previewing = null; vm.download(item, saveAfter = true) },
        )
    }
}

@Composable
private fun CrumbChip(label: String, active: Boolean, onClick: () -> Unit) {
    Text(
        label,
        style = MaterialTheme.typography.labelLarge,
        color = if (active) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.primary,
        maxLines = 1,
        overflow = TextOverflow.Ellipsis,
        modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable(enabled = !active, onClick = onClick).padding(horizontal = 6.dp, vertical = 6.dp),
    )
}

@Composable
private fun FileLeading(item: BackupItemDto, vm: BackupFilesViewModel) {
    val box = Modifier.size(44.dp).clip(RoundedCornerShape(10.dp))
    if (!item.hasThumbnail) {
        Box(box.background(MaterialTheme.colorScheme.primary.copy(alpha = 0.08f)), contentAlignment = Alignment.Center) {
            Icon(kindIcon(item), contentDescription = null, tint = MaterialTheme.colorScheme.primary)
        }
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
        Image(img, contentDescription = null, contentScale = ContentScale.Crop, modifier = box)
    } else {
        Box(box.background(MaterialTheme.colorScheme.primary.copy(alpha = 0.08f)), contentAlignment = Alignment.Center) {
            Icon(kindIcon(item), contentDescription = null, tint = MaterialTheme.colorScheme.primary)
        }
    }
}

@Composable
private fun ImagePreviewDialog(
    item: BackupItemDto,
    vm: BackupFilesViewModel,
    onDismiss: () -> Unit,
    onOpen: () -> Unit,
    onSave: () -> Unit,
) {
    val result by produceState<Pair<ImageBitmap?, String?>>(initialValue = null to null, item.id) {
        value = when (val res = vm.image(item, thumbnail = false)) {
            is ApiResult.Success -> {
                val bmp = res.data?.let { withContext(Dispatchers.Default) { decodeSampled(it, 2048) } }
                if (bmp != null) bmp.asImageBitmap() to null else null to "Gambar tidak dapat ditampilkan. Coba buka dengan aplikasi lain."
            }
            is ApiResult.Failure -> null to res.error.message
        }
    }
    var scale by remember { mutableFloatStateOf(1f) }
    var offsetX by remember { mutableFloatStateOf(0f) }
    var offsetY by remember { mutableFloatStateOf(0f) }
    val transform = rememberTransformableState { zoom, pan, _ ->
        scale = (scale * zoom).coerceIn(1f, 6f)
        if (scale > 1f) { offsetX += pan.x; offsetY += pan.y } else { offsetX = 0f; offsetY = 0f }
    }

    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Box(Modifier.fillMaxSize().background(Color.Black)) {
            val (img, error) = result
            when {
                img != null -> Image(
                    img,
                    contentDescription = item.name,
                    contentScale = ContentScale.Fit,
                    modifier = Modifier.fillMaxSize()
                        .pointerInput(Unit) { detectTapGestures(onDoubleTap = { scale = if (scale > 1f) 1f else 2.5f; offsetX = 0f; offsetY = 0f }) }
                        .transformable(transform)
                        .graphicsLayer(scaleX = scale, scaleY = scale, translationX = offsetX, translationY = offsetY),
                )
                error != null -> Text(error, color = Color.White, modifier = Modifier.align(Alignment.Center).padding(Spacing.xl))
                else -> CircularProgressIndicator(Modifier.align(Alignment.Center), color = Color.White)
            }

            Row(
                Modifier.fillMaxWidth().statusBarsPadding().background(Color.Black.copy(alpha = 0.45f)).padding(horizontal = Spacing.xs),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(onClick = onDismiss) { Icon(Icons.Outlined.Close, contentDescription = "Tutup", tint = Color.White) }
                Text(item.name.orEmpty(), color = Color.White, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f))
            }
            Row(
                Modifier.align(Alignment.BottomCenter).fillMaxWidth().background(Color.Black.copy(alpha = 0.45f)).padding(Spacing.sm),
                horizontalArrangement = Arrangement.SpaceEvenly,
            ) {
                TextButton(onClick = onOpen) {
                    Icon(Icons.Outlined.OpenInNew, contentDescription = null, tint = Color.White)
                    Spacer(Modifier.width(Spacing.xs))
                    Text("Buka", color = Color.White)
                }
                TextButton(onClick = onSave) {
                    Icon(Icons.Outlined.Download, contentDescription = null, tint = Color.White)
                    Spacer(Modifier.width(Spacing.xs))
                    Text(if (item.kind == "raw") "Simpan RAW" else "Simpan", color = Color.White)
                }
            }
        }
    }
}
