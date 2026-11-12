package id.my.sisinden.app.ui.common

import id.my.sisinden.app.core.ui.components.SindenLoader
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.webkit.MimeTypeMap
import android.widget.MediaController
import android.widget.VideoView
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.gestures.rememberTransformableState
import androidx.compose.foundation.gestures.transformable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Close
import androidx.compose.material.icons.rounded.GraphicEq
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.core.content.FileProvider
import id.my.sisinden.app.core.security.expectExternalActivity
import id.my.sisinden.app.core.ui.components.LivelyIconTile
import id.my.sisinden.app.core.ui.components.floating
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.ui.theme.TilePalette
import id.my.sisinden.app.ui.pdf.PdfDocument
import id.my.sisinden.app.ui.pdf.PdfPager
import java.io.File

/** Jenis MIME dari nama berkas. */
fun mimeOf(name: String?): String {
    val ext = name.orEmpty().substringAfterLast('.', "").lowercase()
    if (ext == "arw") return "image/x-sony-arw"
    return MimeTypeMap.getSingleton().getMimeTypeFromExtension(ext) ?: "application/octet-stream"
}

/** Buka berkas lokal (cache) dengan aplikasi lain. Mengembalikan pesan bila gagal. */
fun openFileExternally(context: Context, file: File, name: String?): String? {
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    val intent = Intent(Intent.ACTION_VIEW).apply {
        setDataAndType(uri, mimeOf(name ?: file.name))
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

/**
 * Kerangka layar pratinjau penuh: bilah judul + tombol aksi di atas, isi di tengah.
 * [dark] untuk foto/video (latar hitam), terang untuk PDF & teks.
 */
@Composable
fun FullscreenViewer(
    title: String,
    onDismiss: () -> Unit,
    dark: Boolean = true,
    actions: @Composable RowScope.() -> Unit = {},
    content: @Composable BoxScope.() -> Unit,
) {
    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false)) {
        val bg = if (dark) Color.Black else MaterialTheme.colorScheme.surfaceVariant
        val fg = if (dark) Color.White else MaterialTheme.colorScheme.onSurface
        Column(Modifier.fillMaxSize().background(bg)) {
            Row(
                Modifier.fillMaxWidth()
                    .background(if (dark) Color.Black.copy(alpha = 0.6f) else MaterialTheme.colorScheme.surface)
                    .statusBarsPadding()
                    .padding(horizontal = Spacing.xs, vertical = Spacing.xxs),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(onClick = onDismiss) { Icon(Icons.Rounded.Close, contentDescription = "Tutup", tint = fg) }
                Text(title, color = fg, style = MaterialTheme.typography.titleSmall, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f))
                androidx.compose.runtime.CompositionLocalProvider(androidx.compose.material3.LocalContentColor provides fg) { actions() }
            }
            Box(Modifier.weight(1f).fillMaxWidth().navigationBarsPadding(), content = content)
        }
    }
}

/** Isi yang bisa dicubit-zoom, digeser, dan ketuk dua kali untuk memperbesar. */
@Composable
fun Zoomable(modifier: Modifier = Modifier, content: @Composable BoxScope.() -> Unit) {
    var scale by remember { mutableFloatStateOf(1f) }
    var offsetX by remember { mutableFloatStateOf(0f) }
    var offsetY by remember { mutableFloatStateOf(0f) }
    val transform = rememberTransformableState { zoom, pan, _ ->
        scale = (scale * zoom).coerceIn(1f, 6f)
        if (scale > 1f) { offsetX += pan.x; offsetY += pan.y } else { offsetX = 0f; offsetY = 0f }
    }
    Box(
        modifier
            .fillMaxSize()
            .pointerInput(Unit) { detectTapGestures(onDoubleTap = { scale = if (scale > 1f) 1f else 2.5f; offsetX = 0f; offsetY = 0f }) }
            .transformable(transform)
            .graphicsLayer(scaleX = scale, scaleY = scale, translationX = offsetX, translationY = offsetY),
        contentAlignment = Alignment.Center,
        content = content,
    )
}

/** Status muat berkas pratinjau (loading / gagal / siap) dengan transisi halus. */
sealed interface PreviewLoad<out T> {
    data object Loading : PreviewLoad<Nothing>
    data class Ready<T>(val value: T) : PreviewLoad<T>
    data class Failed(val message: String) : PreviewLoad<Nothing>
}

@Composable
fun <T> PreviewStateContent(state: PreviewLoad<T>, dark: Boolean, content: @Composable (T) -> Unit) {
    AnimatedContent(targetState = state, transitionSpec = { fadeIn() togetherWith fadeOut() }, label = "preview") { s ->
        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            when (s) {
                PreviewLoad.Loading -> SindenLoader(
                    tint = if (dark) Color.White else MaterialTheme.colorScheme.primary,
                    textColor = if (dark) Color.White.copy(alpha = 0.8f) else MaterialTheme.colorScheme.onSurfaceVariant,
                    messages = listOf("Mengunduh pratinjau", "Mendekripsi berkas", "Menyiapkan tampilan"),
                )
                is PreviewLoad.Failed -> Text(
                    s.message,
                    color = if (dark) Color.White else MaterialTheme.colorScheme.onSurface,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.padding(Spacing.xl),
                )
                is PreviewLoad.Ready -> content(s.value)
            }
        }
    }
}

/** Penampil PDF lokal (memakai penampil halaman yang sama dengan dokumen resmi). */
@Composable
fun PdfFileViewer(file: File) {
    val doc by produceState<PreviewLoad<PdfDocument>>(PreviewLoad.Loading, file) {
        value = try {
            PreviewLoad.Ready(PdfDocument.open(file))
        } catch (_: Exception) {
            PreviewLoad.Failed("Dokumen PDF tidak dapat dibuka (rusak atau terenkripsi).")
        }
    }
    DisposableEffect(doc) { onDispose { (doc as? PreviewLoad.Ready)?.value?.close() } }
    PreviewStateContent(doc, dark = false) { PdfPager(it) }
}

/** Pemutar video/audio streaming dengan header token (bisa digeser via HTTP Range). */
@Composable
fun StreamPlayer(url: String, headers: Map<String, String>, audioOnly: Boolean) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        if (audioOnly) {
            Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.align(Alignment.Center).padding(bottom = 120.dp)) {
                LivelyIconTile(Icons.Rounded.GraphicEq, TilePalette.Violet, size = 96.dp, iconSize = 48.dp, corner = 32.dp, modifier = Modifier.floating())
                Spacer(Modifier.height(Spacing.md))
                Text("Memutar audio", color = Color.White.copy(alpha = 0.8f))
            }
        }
        AndroidView(
            factory = { ctx ->
                VideoView(ctx).apply {
                    val controller = MediaController(ctx)
                    controller.setAnchorView(this)
                    setMediaController(controller)
                    setVideoURI(Uri.parse(url), headers)
                    setOnPreparedListener { mp ->
                        mp.isLooping = false
                        start()
                        controller.show(4000)
                    }
                }
            },
            onRelease = { it.stopPlayback() },
            modifier = if (audioOnly) Modifier.fillMaxWidth().size(1.dp).align(Alignment.BottomCenter) else Modifier.fillMaxWidth(),
        )
    }
}

/** Penampil teks polos (txt/csv/log/json), bisa digulir & disalin. */
@Composable
fun TextFileViewer(text: String, truncated: Boolean) {
    Column(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.surface)) {
        if (truncated) {
            Text(
                "Menampilkan 512 KB pertama. Unduh berkas untuk melihat seluruh isinya.",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.fillMaxWidth().background(MaterialTheme.colorScheme.surfaceVariant).padding(Spacing.sm),
            )
        }
        SelectionContainer(Modifier.weight(1f)) {
            Text(
                text,
                fontFamily = FontFamily.Monospace,
                style = MaterialTheme.typography.bodySmall,
                modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).horizontalScroll(rememberScrollState()).padding(Spacing.md),
            )
        }
    }
}
