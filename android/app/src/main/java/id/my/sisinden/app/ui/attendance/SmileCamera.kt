package id.my.sisinden.app.ui.attendance

import android.graphics.Bitmap
import android.graphics.Matrix
import android.util.Base64
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.core.content.ContextCompat
import com.google.mlkit.common.MlKitException
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.face.FaceDetection
import com.google.mlkit.vision.face.FaceDetectorOptions
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream
import java.util.concurrent.Executors
import java.util.concurrent.atomic.AtomicBoolean

/** Batas peluang senyum ML Kit (0..1) dan jumlah bingkai berturut-turut. */
private const val SMILE_THRESHOLD = 0.75f
private const val SMILE_FRAMES = 4
/** Wajah minimal selebar 28% bingkai agar foto jelas dan tidak dari jauh. */
private const val MIN_FACE_RATIO = 0.28f

/**
 * Kamera depan untuk presensi: foto baru diambil otomatis setelah ML Kit
 * mendeteksi tepat satu wajah yang TERSENYUM beberapa bingkai berturut-turut
 * (sekadar mengetuk tombol tidak cukup). Hasilnya JPEG data URL (≤720 px)
 * yang dikirim sebagai `photo` ke /api/mobile/attendances.
 */
@Composable
fun SmileCameraDialog(onCaptured: (String) -> Unit, onDismiss: () -> Unit) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()

    var hint by remember { mutableStateOf("Menyiapkan kamera…") }
    var smileProgress by remember { mutableFloatStateOf(0f) }
    var faceOk by remember { mutableStateOf(false) }
    var processing by remember { mutableStateOf(false) }

    val previewView = remember {
        PreviewView(context).apply {
            implementationMode = PreviewView.ImplementationMode.COMPATIBLE
            scaleType = PreviewView.ScaleType.FILL_CENTER
        }
    }

    DisposableEffect(lifecycleOwner) {
        val executor = Executors.newSingleThreadExecutor()
        val detector = FaceDetection.getClient(
            FaceDetectorOptions.Builder()
                .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_FAST)
                .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
                .setMinFaceSize(0.2f)
                .build()
        )
        val captured = AtomicBoolean(false)
        val busy = AtomicBoolean(false)
        var streak = 0
        var provider: ProcessCameraProvider? = null
        val preview = Preview.Builder().build()
        val analysis = ImageAnalysis.Builder()
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .setOutputImageFormat(ImageAnalysis.OUTPUT_IMAGE_FORMAT_RGBA_8888)
            .build()

        analysis.setAnalyzer(executor) { proxy ->
            if (captured.get() || !busy.compareAndSet(false, true)) {
                proxy.close(); return@setAnalyzer
            }
            val rotation = proxy.imageInfo.rotationDegrees
            val bitmap = runCatching { proxy.toBitmap() }.getOrNull()
            proxy.close()
            if (bitmap == null) { busy.set(false); return@setAnalyzer }
            val frameWidth = if (rotation % 180 == 0) bitmap.width else bitmap.height

            detector.process(InputImage.fromBitmap(bitmap, rotation))
                .addOnSuccessListener { faces ->
                    if (captured.get()) return@addOnSuccessListener
                    val face = faces.singleOrNull()
                    val smile = face?.smilingProbability ?: 0f
                    val bigEnough = face != null && face.boundingBox.width() >= frameWidth * MIN_FACE_RATIO
                    faceOk = face != null && bigEnough
                    when {
                        faces.isEmpty() -> { streak = 0; hint = "Arahkan wajah Anda ke dalam lingkaran" }
                        faces.size > 1 -> { streak = 0; hint = "Hanya satu wajah yang boleh terlihat" }
                        !bigEnough -> { streak = 0; hint = "Dekatkan wajah ke kamera" }
                        smile < SMILE_THRESHOLD -> { streak = 0; hint = "Silakan tersenyum" }
                        else -> { streak++; hint = "Tahan senyum Anda…" }
                    }
                    smileProgress = (streak.toFloat() / SMILE_FRAMES).coerceAtMost(1f)
                    if (streak >= SMILE_FRAMES && captured.compareAndSet(false, true)) {
                        hint = "Senyum terdeteksi"
                        processing = true
                        scope.launch {
                            val dataUrl = withContext(Dispatchers.Default) { bitmap.toAttendanceJpeg(rotation) }
                            onCaptured(dataUrl)
                        }
                    }
                }
                .addOnFailureListener { e ->
                    hint = if (e is MlKitException && e.errorCode == MlKitException.UNAVAILABLE)
                        "Modul deteksi wajah sedang diunduh Google Play services. Pastikan internet aktif, lalu tunggu sebentar."
                    else "Deteksi wajah gagal, coba lagi."
                }
                .addOnCompleteListener { busy.set(false) }
        }

        val future = ProcessCameraProvider.getInstance(context)
        future.addListener({
            runCatching {
                val p = future.get()
                provider = p
                preview.setSurfaceProvider(previewView.surfaceProvider)
                p.unbind(preview, analysis)
                p.bindToLifecycle(lifecycleOwner, CameraSelector.DEFAULT_FRONT_CAMERA, preview, analysis)
                hint = "Arahkan wajah Anda ke dalam lingkaran"
            }.onFailure { hint = "Kamera depan tidak dapat dibuka." }
        }, ContextCompat.getMainExecutor(context))

        onDispose {
            runCatching { provider?.unbind(preview, analysis) }
            analysis.clearAnalyzer()
            detector.close()
            executor.shutdown()
        }
    }

    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false),
    ) {
        Box(Modifier.fillMaxSize().background(Color.Black)) {
            AndroidView(factory = { previewView }, modifier = Modifier.fillMaxSize())

            // Lingkaran panduan: putih → hijau saat wajah pas & tersenyum.
            val ringColor = when {
                smileProgress > 0f -> Color(0xFF22C55E)
                faceOk -> Color.White
                else -> Color.White.copy(alpha = 0.6f)
            }
            Canvas(Modifier.fillMaxSize()) {
                val r = size.minDimension * 0.38f
                val c = Offset(size.width / 2f, size.height * 0.42f)
                drawCircle(ringColor, radius = r, center = c, style = Stroke(width = 4.dp.toPx()))
                if (smileProgress > 0f) {
                    drawArc(
                        Color(0xFF22C55E), startAngle = -90f, sweepAngle = 360f * smileProgress, useCenter = false,
                        topLeft = Offset(c.x - r - 10.dp.toPx(), c.y - r - 10.dp.toPx()),
                        size = Size((r + 10.dp.toPx()) * 2, (r + 10.dp.toPx()) * 2),
                        style = Stroke(width = 6.dp.toPx()),
                    )
                }
            }

            IconButton(
                onClick = onDismiss,
                modifier = Modifier.statusBarsPadding().padding(12.dp).align(Alignment.TopEnd)
                    .clip(CircleShape).background(Color.Black.copy(alpha = 0.45f)),
            ) { Icon(Icons.Outlined.Close, contentDescription = "Batal", tint = Color.White) }

            Column(
                Modifier.align(Alignment.BottomCenter).fillMaxWidth()
                    .background(Color.Black.copy(alpha = 0.55f))
                    .navigationBarsPadding()
                    .padding(horizontal = 24.dp, vertical = 20.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text("Foto presensi", style = MaterialTheme.typography.titleMedium, color = Color.White)
                Spacer(Modifier.height(6.dp))
                Text(hint, style = MaterialTheme.typography.bodyLarge, color = Color.White, textAlign = TextAlign.Center)
                Spacer(Modifier.height(12.dp))
                if (processing) {
                    CircularProgressIndicator(Modifier.size(28.dp), color = Color.White, strokeWidth = 3.dp)
                } else {
                    LinearProgressIndicator(
                        progress = { smileProgress },
                        modifier = Modifier.fillMaxWidth(0.6f),
                        color = Color(0xFF22C55E),
                        trackColor = Color.White.copy(alpha = 0.25f),
                    )
                }
                Spacer(Modifier.height(8.dp))
                Text(
                    "Foto diambil otomatis saat Anda tersenyum.",
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.White.copy(alpha = 0.75f),
                    textAlign = TextAlign.Center,
                )
            }
        }
    }
}

/** Putar tegak, cerminkan (kamera depan), kecilkan ke ≤720 px, lalu JPEG 80 → data URL. */
private fun Bitmap.toAttendanceJpeg(rotationDegrees: Int): String {
    val scale = (720f / maxOf(width, height)).coerceAtMost(1f)
    val m = Matrix().apply {
        postRotate(rotationDegrees.toFloat())
        postScale(-scale, scale) // cermin seperti pratinjau kamera depan
    }
    val out = Bitmap.createBitmap(this, 0, 0, width, height, m, true)
    val bytes = ByteArrayOutputStream().use { s ->
        out.compress(Bitmap.CompressFormat.JPEG, 80, s)
        s.toByteArray()
    }
    return "data:image/jpeg;base64," + Base64.encodeToString(bytes, Base64.NO_WRAP)
}
