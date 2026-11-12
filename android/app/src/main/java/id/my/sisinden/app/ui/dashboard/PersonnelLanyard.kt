package id.my.sisinden.app.ui.dashboard

import android.provider.Settings
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectHorizontalDragGestures
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.TransformOrigin
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.onClick
import androidx.compose.ui.semantics.role
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import id.my.sisinden.app.R
import id.my.sisinden.app.data.model.UserProfile
import kotlinx.coroutines.launch

private const val AGENCY = "DENINTEL KODAERAL V"
private val Navy = Color(0xFF1E3A5F)
private val NavyDeep = Color(0xFF0F2240)
private val NavyNight = Color(0xFF0B1A2E)

/**
 * Lanyard kartu identitas personel (padanan PersonnelLanyard.vue di web):
 * tali bertuliskan nama satuan, kartu menggantung yang bisa digeser untuk
 * berayun (pegas teredam) dan diketuk untuk dibalik. Animasi dimatikan bila
 * pengguna mematikan animasi sistem.
 */
@Composable
fun PersonnelLanyard(user: UserProfile, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val reduced = remember {
        Settings.Global.getFloat(context.contentResolver, Settings.Global.ANIMATOR_DURATION_SCALE, 1f) == 0f
    }
    val scope = rememberCoroutineScope()
    val angle = remember { Animatable(0f) }
    var flipped by rememberSaveable { mutableStateOf(false) }
    val flip by animateFloatAsState(if (flipped) 180f else 0f, if (reduced) tween(0) else tween(600), label = "flip")
    val swingBack = spring<Float>(dampingRatio = 0.22f, stiffness = Spring.StiffnessVeryLow)
    val density = LocalDensity.current

    // Satu ayunan sambutan saat dashboard dibuka.
    LaunchedEffect(Unit) {
        if (!reduced) {
            angle.snapTo(14f)
            angle.animateTo(0f, swingBack)
        }
    }

    val jabatan = user.jabatan?.takeIf { it.isNotBlank() } ?: user.roleLabel
    val a11y = "Kartu identitas: ${user.pangkat.orEmpty()} ${user.displayName}, NRP ${user.nrp ?: "-"}, $jabatan." +
        if (flipped) " Sisi belakang." else ""

    Box(modifier.fillMaxWidth().padding(top = 8.dp).height(540.dp), contentAlignment = Alignment.TopCenter) {
        Column(
            Modifier
                .width(236.dp)
                .graphicsLayer {
                    rotationZ = angle.value
                    transformOrigin = TransformOrigin(0.5f, 0f)
                },
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Strap()
            Clip()
            Box(
                Modifier
                    .offset(y = (-2).dp)
                    .size(width = 220.dp, height = 346.dp)
                    .semantics {
                        role = Role.Button
                        contentDescription = a11y
                        stateDescription = if (flipped) "Sisi belakang" else "Sisi depan"
                        onClick(label = "Balik kartu") { flipped = !flipped; true }
                    }
                    .pointerInput(Unit) { detectTapGestures(onTap = { flipped = !flipped }) }
                    .pointerInput(reduced) {
                        if (reduced) return@pointerInput
                        detectHorizontalDragGestures(
                            onDragEnd = { scope.launch { angle.animateTo(0f, swingBack) } },
                            onDragCancel = { scope.launch { angle.animateTo(0f, swingBack) } },
                        ) { change, dx ->
                            change.consume()
                            val next = (angle.value + with(density) { dx.toDp().value } * 0.22f).coerceIn(-38f, 38f)
                            scope.launch { angle.snapTo(next) }
                        }
                    }
                    .graphicsLayer {
                        rotationY = flip
                        cameraDistance = 14f * density.density
                    },
            ) {
                // Kartu berukuran tetap: skala huruf sistem dibatasi agar isi tidak meluber.
                CompositionLocalProvider(LocalDensity provides Density(density.density, density.fontScale.coerceAtMost(1.1f))) {
                    if (flip <= 90f) CardFront(user, jabatan) else Box(Modifier.fillMaxSize().graphicsLayer { rotationY = 180f }) { CardBack() }
                }
            }
        }
    }
}

@Composable
private fun Strap() {
    Canvas(Modifier.size(width = 200.dp, height = 160.dp)) {
        val sx = size.width / 200f
        val sy = size.height / 150f
        val band = 18.dp.toPx()
        val left = Offset(62f * sx, -4f * sy) to Offset(96f * sx, 146f * sy)
        val right = Offset(138f * sx, -4f * sy) to Offset(104f * sx, 146f * sy)
        drawLine(Navy, left.first, left.second, strokeWidth = band, cap = StrokeCap.Butt)
        drawLine(Color(0xFF23446F), right.first, right.second, strokeWidth = band, cap = StrokeCap.Butt)
        val paint = android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG).apply {
            color = Color(0xFFCBD5E1).toArgb()
            textSize = 7.5.sp.toPx()
            letterSpacing = 0.12f
            isFakeBoldText = true
            textAlign = android.graphics.Paint.Align.CENTER
        }
        val baseline = (paint.descent() + paint.ascent()) / 2f
        for ((from, to) in listOf(left, right)) {
            val path = android.graphics.Path().apply { moveTo(from.x, from.y); lineTo(to.x, to.y) }
            // Align.CENTER sudah memusatkan teks di sepanjang tali.
            drawContext.canvas.nativeCanvas.drawTextOnPath(AGENCY, path, 0f, -baseline, paint)
        }
    }
}

@Composable
private fun Clip() {
    Box(Modifier.offset(y = (-8).dp).size(width = 34.dp, height = 30.dp), contentAlignment = Alignment.TopCenter) {
        Box(Modifier.size(20.dp).border(3.dp, Color(0xFF9CA3AF), CircleShape))
        Box(
            Modifier
                .offset(y = 16.dp)
                .size(width = 14.dp, height = 16.dp)
                .clip(RoundedCornerShape(3.dp))
                .background(Brush.verticalGradient(listOf(Color(0xFFD1D5DB), Color(0xFF6B7280)))),
        )
    }
}

private val CardShape = RoundedCornerShape(16.dp)

@Composable
private fun CardSurface(background: Brush, borderColor: Color, content: @Composable () -> Unit) {
    Box(
        Modifier
            .fillMaxSize()
            .shadow(18.dp, CardShape, ambientColor = Color(0x7309090B), spotColor = Color(0x7309090B))
            .clip(CardShape)
            .background(background)
            .border(1.dp, borderColor, CardShape),
    ) {
        content()
        // Lubang slot tali
        Box(
            Modifier
                .align(Alignment.TopCenter)
                .padding(top = 9.dp)
                .size(width = 38.dp, height = 7.dp)
                .clip(RoundedCornerShape(4.dp))
                .background(NavyNight.copy(alpha = 0.9f)),
        )
    }
}

@Composable
private fun CardFront(user: UserProfile, jabatan: String) {
    CardSurface(Brush.verticalGradient(listOf(Color.White, Color.White)), Color(0xFFE4E4E7)) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally) {
            // Kepala navy + foto yang menumpang 30dp di tepi bawahnya.
            Box(Modifier.fillMaxWidth().height(116.dp)) {
            Row(
                Modifier
                    .fillMaxWidth()
                    .height(76.dp)
                    .background(Brush.linearGradient(listOf(Navy, NavyDeep)))
                    .padding(start = 14.dp, end = 14.dp, top = 22.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.Top,
            ) {
                Box(Modifier.size(26.dp).clip(RoundedCornerShape(7.dp)).background(Color.White.copy(alpha = 0.92f)).padding(3.dp)) {
                    Image(painterResource(R.drawable.ic_sinden_mark), contentDescription = null, modifier = Modifier.fillMaxSize())
                }
                Spacer(Modifier.width(8.dp))
                Text(AGENCY, color = Color.White, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 0.9.sp, lineHeight = 13.sp)
            }
            Box(
                Modifier
                    .align(Alignment.BottomCenter)
                    .size(72.dp)
                    .shadow(8.dp, RoundedCornerShape(18.dp))
                    .clip(RoundedCornerShape(18.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFF2563EB), Color(0xFF7C3AED))))
                    .border(3.dp, Color.White, RoundedCornerShape(18.dp)),
                contentAlignment = Alignment.Center,
            ) {
                Text(user.initials, color = Color.White, fontSize = 26.sp, fontWeight = FontWeight.Bold)
                if (!user.avatarUrl.isNullOrBlank()) {
                    AsyncImage(model = user.avatarUrl, contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
                }
            }
            }
            Column(
                // weight: info boleh menyusut, baris status di bawah selalu terlihat.
                Modifier.weight(1f).padding(start = 14.dp, end = 14.dp, top = 6.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(user.pangkat?.takeIf { it.isNotBlank() } ?: "Pangkat belum diisi", color = Color(0xFF1D4ED8), fontSize = 12.sp, lineHeight = 15.sp, fontWeight = FontWeight.SemiBold)
                Text(
                    user.displayName,
                    color = Color(0xFF09090B),
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    lineHeight = 20.sp,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    textAlign = TextAlign.Center,
                )
                Spacer(Modifier.height(6.dp))
                Text("NRP", color = Color(0xFF6B6B74), fontSize = 11.sp, lineHeight = 13.sp)
                Text(user.nrp?.takeIf { it.isNotBlank() } ?: "—", color = Color(0xFF27272A), fontSize = 14.sp, lineHeight = 18.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                Box(Modifier.padding(vertical = 8.dp).width(150.dp).height(1.dp).background(Color(0xFFE4E4E7)))
                Text("Jabatan", color = Color(0xFF6B6B74), fontSize = 11.sp, lineHeight = 13.sp)
                Text(jabatan, color = Color(0xFF27272A), fontSize = 13.sp, lineHeight = 16.sp, fontWeight = FontWeight.SemiBold, maxLines = 2, overflow = TextOverflow.Ellipsis, textAlign = TextAlign.Center)
            }
            Row(
                Modifier.fillMaxWidth().background(Color(0xFFFAFAFA)).padding(horizontal = 12.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(Modifier.size(7.dp).clip(CircleShape).background(Color(0xFF16A34A)))
                Spacer(Modifier.width(5.dp))
                Text("Aktif", color = Color(0xFF15803D), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.weight(1f))
                Text("Kartu identitas digital", color = Color(0xFF6B6B74), fontSize = 11.sp)
            }
        }
    }
}

@Composable
private fun CardBack() {
    CardSurface(Brush.linearGradient(listOf(Navy, NavyNight)), Navy) {
        Column(
            Modifier.fillMaxSize().padding(start = 18.dp, end = 18.dp, top = 28.dp, bottom = 18.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            Spacer(Modifier.weight(1f))
            Box(Modifier.size(72.dp).clip(RoundedCornerShape(18.dp)).background(Color.White.copy(alpha = 0.92f)).padding(8.dp)) {
                Image(painterResource(R.drawable.ic_sinden_mark), contentDescription = null, modifier = Modifier.fillMaxSize())
            }
            Spacer(Modifier.height(12.dp))
            Text(AGENCY, color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.9.sp, textAlign = TextAlign.Center)
            Box(Modifier.padding(vertical = 10.dp).width(150.dp).height(1.dp).background(Color.White.copy(alpha = 0.15f)))
            Text(
                "Kartu ini sah selama pemegang masih aktif bertugas dan hanya untuk penggunaan internal.",
                color = Color(0xFFCBD5E1),
                fontSize = 12.sp,
                lineHeight = 17.sp,
                textAlign = TextAlign.Center,
            )
            Spacer(Modifier.weight(1f))
            Text("Ketuk untuk membalik", color = Color(0xFF94A3B8), fontSize = 11.sp)
        }
    }
}
