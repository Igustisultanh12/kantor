package id.my.sisinden.app.ui.cash

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import android.provider.OpenableColumns
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.AttachFile
import androidx.compose.material.icons.rounded.Close
import androidx.compose.material.icons.rounded.OpenInNew
import androidx.compose.material.icons.rounded.PhotoCamera
import androidx.compose.material.icons.rounded.PictureAsPdf
import androidx.compose.material.icons.rounded.ReceiptLong
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import coil.compose.AsyncImage
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.security.expectExternalActivity
import id.my.sisinden.app.core.ui.components.LivelyIconTile
import id.my.sisinden.app.core.ui.components.appear
import id.my.sisinden.app.core.ui.components.bouncyPress
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.ui.theme.TilePalette
import id.my.sisinden.app.data.model.CashReceiptDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.data.repository.UploadImage
import id.my.sisinden.app.ui.common.FullscreenViewer
import id.my.sisinden.app.ui.common.PdfFileViewer
import id.my.sisinden.app.ui.common.PreviewLoad
import id.my.sisinden.app.ui.common.PreviewStateContent
import id.my.sisinden.app.ui.common.Zoomable
import id.my.sisinden.app.ui.common.openFileExternally
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream
import java.io.File
import java.io.FileNotFoundException

const val MAX_RECEIPTS = 10
private const val MAX_PDF_BYTES = 20L * 1024 * 1024

/** Struk baru yang dipilih pengguna (belum diunggah). */
class PickedReceipt(val upload: UploadImage, val preview: ImageBitmap?) {
    val isPdf: Boolean get() = upload.mimeType == "application/pdf"
}

private fun decodeSampled(bytes: ByteArray, maxSide: Int): Bitmap? {
    val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
    BitmapFactory.decodeByteArray(bytes, 0, bytes.size, bounds)
    if (bounds.outWidth <= 0) return null
    var sample = 1
    while (bounds.outWidth / sample > maxSide || bounds.outHeight / sample > maxSide) sample *= 2
    return BitmapFactory.decodeByteArray(bytes, 0, bytes.size, BitmapFactory.Options().apply { inSampleSize = sample })
}

/**
 * Baca struk dari kamera / pemilih berkas. Foto diperkecil (sisi terpanjang 2048 px,
 * JPEG 85%) agar unggahan ringan; PDF dikirim apa adanya (maks. 20 MB).
 */
suspend fun readReceipt(context: Context, uri: Uri, fallbackName: String): Result<PickedReceipt> = withContext(Dispatchers.IO) {
    runCatching {
        val resolver = context.contentResolver
        var name: String? = null
        var size = -1L
        runCatching {
            resolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE), null, null, null)?.use { c ->
                if (c.moveToFirst()) {
                    name = c.getString(0)
                    size = if (c.isNull(1)) -1 else c.getLong(1)
                }
            }
        }
        val mime = resolver.getType(uri) ?: if (name.orEmpty().lowercase().endsWith(".pdf")) "application/pdf" else "image/jpeg"
        val base = (name ?: fallbackName).substringBeforeLast('.').replace(Regex("[^A-Za-z0-9._-]+"), "_").take(60).ifEmpty { "struk" }
        if (mime == "application/pdf") {
            if (size > MAX_PDF_BYTES) error("PDF terlalu besar (maks. 20 MB).")
            val bytes = resolver.openInputStream(uri)?.use { it.readBytes() } ?: throw FileNotFoundException()
            if (bytes.size > MAX_PDF_BYTES) error("PDF terlalu besar (maks. 20 MB).")
            PickedReceipt(UploadImage("$base.pdf", "application/pdf", bytes), null)
        } else {
            if (!mime.startsWith("image/")) error("Struk harus berupa foto atau PDF.")
            val raw = resolver.openInputStream(uri)?.use { it.readBytes() } ?: throw FileNotFoundException()
            val bmp = decodeSampled(raw, 2048) ?: error("Foto tidak dapat dibaca.")
            val out = ByteArrayOutputStream()
            bmp.compress(Bitmap.CompressFormat.JPEG, 85, out)
            val thumb = Bitmap.createScaledBitmap(bmp, (bmp.width * 240 / maxOf(bmp.width, bmp.height)).coerceAtLeast(1), (bmp.height * 240 / maxOf(bmp.width, bmp.height)).coerceAtLeast(1), true)
            PickedReceipt(UploadImage("$base.jpg", "image/jpeg", out.toByteArray()), thumb.asImageBitmap())
        }
    }
}

/**
 * Bagian "Struk / nota" di formulir transaksi: struk lama (bisa dihapus),
 * struk baru, tombol foto kamera & pilih berkas.
 */
@Composable
fun ReceiptEditor(
    existing: List<CashReceiptDto>,
    picked: List<PickedReceipt>,
    onRemoveExisting: (CashReceiptDto) -> Unit,
    onRemovePicked: (PickedReceipt) -> Unit,
    onAdd: (PickedReceipt) -> Unit,
    onError: (String) -> Unit,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val total = existing.size + picked.size
    var cameraFile by remember { mutableStateOf<File?>(null) }
    var reading by remember { mutableStateOf(false) }

    fun add(uri: Uri, fallback: String) {
        reading = true
        scope.launch {
            readReceipt(context, uri, fallback)
                .onSuccess(onAdd)
                .onFailure { onError(it.message ?: "Berkas tidak dapat dibaca.") }
            reading = false
        }
    }

    val camera = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { ok ->
        val file = cameraFile
        if (ok && file != null) add(Uri.fromFile(file), "struk_kamera")
    }
    fun launchCamera() {
        val dir = File(context.cacheDir, "camera").apply { mkdirs() }
        val file = File(dir, "struk_${System.currentTimeMillis()}.jpg")
        cameraFile = file
        context.expectExternalActivity()
        camera.launch(FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file))
    }
    val cameraPermission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) launchCamera() else onError("Izin kamera diperlukan untuk memotret struk.")
    }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.OpenMultipleDocuments()) { uris ->
        uris.take(MAX_RECEIPTS - total).forEach { add(it, "struk") }
        if (uris.size > MAX_RECEIPTS - total) onError("Maksimal $MAX_RECEIPTS struk per transaksi.")
    }

    Column(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Struk / nota", style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
            Text("$total/$MAX_RECEIPTS", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        Text("Foto JPG/PNG atau PDF, opsional.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Spacer(Modifier.height(Spacing.sm))
        if (total > 0 || reading) {
            LazyRow(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                itemsIndexed(existing, key = { _, it -> "old-${it.path ?: it.url}" }) { i, r ->
                    ReceiptThumb(Modifier.appear(i), onRemove = { onRemoveExisting(r) }) {
                        if (r.isPdf) PdfTile(r.name) else AsyncImage(r.url, null, contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
                    }
                }
                itemsIndexed(picked, key = { _, it -> "new-${System.identityHashCode(it)}" }) { i, p ->
                    ReceiptThumb(Modifier.appear(i), onRemove = { onRemovePicked(p) }, isNew = true) {
                        val preview = p.preview
                        if (p.isPdf || preview == null) PdfTile(p.upload.fileName) else Image(preview, null, contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
                    }
                }
                if (reading) item { Box(Modifier.size(84.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator(Modifier.size(24.dp), strokeWidth = 2.dp) } }
            }
            Spacer(Modifier.height(Spacing.sm))
        }
        Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            OutlinedButton(
                onClick = {
                    if (ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) launchCamera()
                    else cameraPermission.launch(Manifest.permission.CAMERA)
                },
                enabled = total < MAX_RECEIPTS && !reading,
                modifier = Modifier.weight(1f).bouncyPress(),
            ) {
                Icon(Icons.Rounded.PhotoCamera, contentDescription = null)
                Spacer(Modifier.width(Spacing.xs))
                Text("Foto struk")
            }
            OutlinedButton(
                onClick = { context.expectExternalActivity(); picker.launch(arrayOf("image/*", "application/pdf")) },
                enabled = total < MAX_RECEIPTS && !reading,
                modifier = Modifier.weight(1f).bouncyPress(),
            ) {
                Icon(Icons.Rounded.AttachFile, contentDescription = null)
                Spacer(Modifier.width(Spacing.xs))
                Text("Pilih berkas")
            }
        }
    }
}

@Composable
private fun ReceiptThumb(modifier: Modifier, onRemove: () -> Unit, isNew: Boolean = false, content: @Composable () -> Unit) {
    Box(modifier.size(84.dp)) {
        Box(
            Modifier.size(84.dp).clip(RoundedCornerShape(16.dp))
                .border(if (isNew) 2.dp else 1.dp, if (isNew) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(16.dp))
                .background(MaterialTheme.colorScheme.surfaceVariant),
            contentAlignment = Alignment.Center,
        ) { content() }
        Box(
            Modifier.align(Alignment.TopEnd).padding(4.dp).size(24.dp).clip(CircleShape)
                .background(Color.Black.copy(alpha = 0.55f)).clickable(onClick = onRemove),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.Rounded.Close, contentDescription = "Hapus struk", tint = Color.White, modifier = Modifier.size(16.dp)) }
    }
}

@Composable
private fun PdfTile(name: String?) {
    Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(6.dp)) {
        LivelyIconTile(Icons.Rounded.PictureAsPdf, TilePalette.Rose, size = 36.dp, iconSize = 20.dp, corner = 12.dp)
        Spacer(Modifier.height(4.dp))
        Text(name.orEmpty(), style = MaterialTheme.typography.labelSmall, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

/** Galeri struk di detail transaksi; ketuk untuk melihat. */
@Composable
fun ReceiptGallery(receipts: List<CashReceiptDto>, onOpen: (CashReceiptDto) -> Unit) {
    Column(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Rounded.ReceiptLong, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(Spacing.xs))
            Text("Struk / nota (${receipts.size})", style = MaterialTheme.typography.titleSmall)
        }
        Spacer(Modifier.height(Spacing.sm))
        LazyRow(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            itemsIndexed(receipts, key = { _, it -> it.url ?: it.path.orEmpty() }) { i, r ->
                Box(
                    Modifier.appear(i).bouncyPress().size(96.dp).clip(RoundedCornerShape(18.dp))
                        .background(MaterialTheme.colorScheme.surfaceVariant)
                        .clickable { onOpen(r) },
                    contentAlignment = Alignment.Center,
                ) {
                    if (r.isPdf) PdfTile(r.name) else AsyncImage(r.url, "Struk", contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
                }
            }
        }
    }
}

/** Penampil struk: foto (zoom) atau PDF (di dalam aplikasi). */
@Composable
fun ReceiptViewer(receipt: CashReceiptDto, repository: SindenRepository, onDismiss: () -> Unit) {
    val context = LocalContext.current
    val pdf by produceState<PreviewLoad<File>>(PreviewLoad.Loading, receipt.url) {
        if (!receipt.isPdf) return@produceState
        value = when (val res = repository.downloadToCache(receipt.url.orEmpty(), receipt.name ?: "struk.pdf")) {
            is ApiResult.Success -> PreviewLoad.Ready(res.data)
            is ApiResult.Failure -> PreviewLoad.Failed(res.error.message)
        }
    }
    FullscreenViewer(
        title = receipt.name ?: "Struk",
        onDismiss = onDismiss,
        dark = !receipt.isPdf,
        actions = {
            val file = (pdf as? PreviewLoad.Ready)?.value
            if (file != null) {
                IconButton(onClick = { openFileExternally(context, file, receipt.name) }) {
                    Icon(Icons.Rounded.OpenInNew, contentDescription = "Buka dengan aplikasi lain")
                }
            }
        },
    ) {
        if (receipt.isPdf) {
            PreviewStateContent(pdf, dark = false) { PdfFileViewer(it) }
        } else {
            Zoomable { AsyncImage(receipt.url, "Struk", contentScale = ContentScale.Fit, modifier = Modifier.fillMaxSize()) }
        }
    }
}
