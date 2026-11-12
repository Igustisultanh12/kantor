package id.my.sisinden.app.ui.chat

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Description
import androidx.compose.material.icons.rounded.Download
import androidx.compose.material.icons.rounded.OpenInNew
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import android.widget.Toast
import coil.compose.AsyncImage
import id.my.sisinden.app.core.network.ApiError
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.network.ErrorKind
import id.my.sisinden.app.core.security.expectExternalActivity
import id.my.sisinden.app.core.data.SessionStore
import id.my.sisinden.app.core.ui.components.LivelyIconTile
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.ui.theme.TilePalette
import id.my.sisinden.app.data.model.ChatAttachmentDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.FullscreenViewer
import id.my.sisinden.app.ui.common.PdfFileViewer
import id.my.sisinden.app.ui.common.PreviewLoad
import id.my.sisinden.app.ui.common.PreviewStateContent
import id.my.sisinden.app.ui.common.TextFileViewer
import id.my.sisinden.app.ui.common.Zoomable
import id.my.sisinden.app.ui.common.openFileExternally
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File

private enum class AttachmentKind { IMAGE, PDF, TEXT, OTHER }

private fun kindOf(a: ChatAttachmentDto): AttachmentKind {
    val ext = (a.originalName ?: a.filePath).orEmpty().substringAfterLast('.', "").lowercase()
    return when {
        a.isImage || ext in setOf("jpg", "jpeg", "png", "webp") -> AttachmentKind.IMAGE
        ext == "pdf" -> AttachmentKind.PDF
        ext == "txt" -> AttachmentKind.TEXT
        else -> AttachmentKind.OTHER
    }
}

/**
 * Pratinjau lampiran chat di dalam aplikasi: gambar (bisa dizoom), PDF, dan teks
 * ditampilkan langsung; jenis lain bisa disimpan ke perangkat. Tidak pernah
 * membuka tautan berkas di peramban.
 */
@Composable
fun ChatAttachmentPreview(repository: SindenRepository, a: ChatAttachmentDto, onDismiss: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val url = remember(a) { a.publicUrl(SessionStore.DEFAULT_BASE_URL) }
    val name = a.originalName ?: "lampiran"
    val kind = remember(a) { kindOf(a) }
    var busy by remember { mutableStateOf(false) }
    val toast = { msg: String -> Toast.makeText(context, msg, Toast.LENGTH_SHORT).show() }

    suspend fun fetch(): ApiResult<File> =
        if (url == null) ApiResult.Failure(ApiError(ErrorKind.SERVER, "Berkas lampiran sudah dihapus."))
        else repository.downloadToCache(url, name, subdir = "chat")

    var pendingSave by remember { mutableStateOf<File?>(null) }
    val saveLauncher = rememberLauncherForActivityResult(ActivityResultContracts.CreateDocument("*/*")) { uri ->
        val src = pendingSave
        pendingSave = null
        if (uri == null || src == null) return@rememberLauncherForActivityResult
        scope.launch {
            val ok = withContext(Dispatchers.IO) {
                runCatching { context.contentResolver.openOutputStream(uri)?.use { out -> src.inputStream().use { it.copyTo(out) } } != null }.getOrDefault(false)
            }
            toast(if (ok) "Lampiran tersimpan di perangkat." else "Gagal menyimpan. Coba pilih lokasi lain.")
        }
    }

    val save: () -> Unit = {
        if (!busy) {
            busy = true
            scope.launch {
                when (val res = fetch()) {
                    is ApiResult.Success -> {
                        pendingSave = res.data
                        context.expectExternalActivity()
                        saveLauncher.launch(name)
                    }
                    is ApiResult.Failure -> toast(res.error.message)
                }
                busy = false
            }
        }
    }

    FullscreenViewer(
        title = name,
        onDismiss = onDismiss,
        dark = kind == AttachmentKind.IMAGE,
        actions = {
            if (busy) {
                CircularProgressIndicator(Modifier.size(20.dp).padding(end = 4.dp), strokeWidth = 2.dp)
            } else {
                IconButton(onClick = save) { Icon(Icons.Rounded.Download, contentDescription = "Simpan ke perangkat") }
            }
        },
    ) {
        when (kind) {
            AttachmentKind.IMAGE -> Zoomable {
                AsyncImage(model = url, contentDescription = name, contentScale = ContentScale.Fit, modifier = Modifier.fillMaxSize())
            }
            AttachmentKind.PDF -> {
                val load by produceState<PreviewLoad<File>>(PreviewLoad.Loading, a) {
                    value = when (val res = fetch()) {
                        is ApiResult.Success -> PreviewLoad.Ready(res.data)
                        is ApiResult.Failure -> PreviewLoad.Failed(res.error.message)
                    }
                }
                PreviewStateContent(load, dark = false) { PdfFileViewer(it) }
            }
            AttachmentKind.TEXT -> {
                val load by produceState<PreviewLoad<Pair<String, Boolean>>>(PreviewLoad.Loading, a) {
                    value = when (val res = fetch()) {
                        is ApiResult.Success -> withContext(Dispatchers.IO) {
                            val bytes = res.data.readBytes()
                            val limit = 512 * 1024
                            PreviewLoad.Ready(String(bytes, 0, minOf(bytes.size, limit), Charsets.UTF_8) to (bytes.size > limit))
                        }
                        is ApiResult.Failure -> PreviewLoad.Failed(res.error.message)
                    }
                }
                PreviewStateContent(load, dark = false) { (text, truncated) -> TextFileViewer(text, truncated) }
            }
            AttachmentKind.OTHER -> Column(
                Modifier.fillMaxSize().padding(Spacing.xl),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center,
            ) {
                LivelyIconTile(Icons.Rounded.Description, TilePalette.Blue, size = 72.dp, iconSize = 36.dp, corner = 24.dp)
                Spacer(Modifier.height(Spacing.md))
                Text("Berkas ini belum bisa dipratinjau di aplikasi", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold, textAlign = TextAlign.Center)
                Spacer(Modifier.height(Spacing.xs))
                Text(
                    "Simpan ke perangkat, atau buka dengan aplikasi lain yang terpasang.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                )
                Spacer(Modifier.height(Spacing.lg))
                Button(onClick = save, enabled = !busy, modifier = Modifier.fillMaxWidth()) {
                    Icon(Icons.Rounded.Download, contentDescription = null)
                    Spacer(Modifier.width(Spacing.sm))
                    Text("Simpan ke perangkat")
                }
                Spacer(Modifier.height(Spacing.sm))
                OutlinedButton(
                    onClick = {
                        if (!busy) {
                            busy = true
                            scope.launch {
                                when (val res = fetch()) {
                                    is ApiResult.Success -> openFileExternally(context, res.data, name)?.let(toast)
                                    is ApiResult.Failure -> toast(res.error.message)
                                }
                                busy = false
                            }
                        }
                    },
                    enabled = !busy,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Icon(Icons.Rounded.OpenInNew, contentDescription = null)
                    Spacer(Modifier.width(Spacing.sm))
                    Text("Buka dengan aplikasi lain")
                }
            }
        }
    }
}
