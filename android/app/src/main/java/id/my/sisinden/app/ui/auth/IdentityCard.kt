package id.my.sisinden.app.ui.auth

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Verified
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage

/** Data kartu identitas yang tampil saat kredensial diterima server. */
data class IdentityCardInfo(
    val name: String,
    val pangkat: String?,
    val nrp: String?,
    val jabatan: String?,
    val avatarUrl: String?,
)

private val CardNavy = Color(0xFF0B1B3F)
private val CardBlue = Color(0xFF1E40AF)
private val Accent = Color(0xFF60A5FA)
private val Ok = Color(0xFF34D399)

/**
 * Kartu identitas personel bergaya lanyard:
 *  - sebelum terverifikasi: kartu kosong dengan garis pemindai yang menyapu naik-turun;
 *  - setelah kredensial diterima ([info] terisi & [verified]): kartu berputar 3D
 *    menampilkan foto, nama, pangkat/NRP, lalu bilah verifikasi terisi.
 */
@Composable
fun IdentityCard(
    verified: Boolean,
    info: IdentityCardInfo?,
    modifier: Modifier = Modifier,
    reduceMotion: Boolean = false,
) {
    val flip = remember { Animatable(0f) }
    val fill = remember { Animatable(0f) }
    LaunchedEffect(verified, info) {
        if (verified) {
            if (reduceMotion) flip.snapTo(180f) else flip.animateTo(180f, tween(700, easing = FastOutSlowInEasing))
            fill.animateTo(1f, tween(if (reduceMotion) 200 else 650, easing = LinearEasing))
        } else {
            flip.snapTo(0f); fill.snapTo(0f)
        }
    }

    val scan = rememberInfiniteTransition(label = "scan")
    val scanY by scan.animateFloat(
        initialValue = 0f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(1400, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "scanY",
    )
    val shimmer by scan.animateFloat(
        initialValue = -1f, targetValue = 2f,
        animationSpec = infiniteRepeatable(tween(1600, easing = LinearEasing)),
        label = "shimmer",
    )
    val density = LocalDensity.current.density
    val shape = RoundedCornerShape(20.dp)

    Box(
        modifier
            .widthIn(max = 320.dp)
            .fillMaxWidth()
            .aspectRatio(1.58f)
            .graphicsLayer {
                rotationY = flip.value
                cameraDistance = 14f * density
            },
    ) {
        val showBack = flip.value > 90f
        Box(
            Modifier
                .fillMaxSize()
                .graphicsLayer { if (showBack) rotationY = 180f }
                .clip(shape)
                .background(Brush.linearGradient(listOf(CardNavy, CardBlue)))
                .border(1.dp, Color.White.copy(alpha = 0.16f), shape),
        ) {
            // Pita lanyard di tepi atas
            Box(Modifier.fillMaxWidth().height(6.dp).background(Brush.horizontalGradient(listOf(Accent, Ok))))

            if (!showBack) {
                // Sisi depan: kerangka kosong + pemindai
                Row(Modifier.fillMaxSize().padding(18.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(64.dp).clip(CircleShape).skeleton(shimmer))
                    Spacer(Modifier.width(16.dp))
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.weight(1f)) {
                        Box(Modifier.fillMaxWidth(0.9f).height(14.dp).clip(RoundedCornerShape(7.dp)).skeleton(shimmer))
                        Box(Modifier.fillMaxWidth(0.65f).height(10.dp).clip(RoundedCornerShape(5.dp)).skeleton(shimmer))
                        Box(Modifier.fillMaxWidth(0.45f).height(10.dp).clip(RoundedCornerShape(5.dp)).skeleton(shimmer))
                    }
                }
                if (!reduceMotion) {
                    Box(
                        Modifier.fillMaxSize().drawWithContent {
                            val y = size.height * scanY
                            drawRect(
                                Brush.verticalGradient(
                                    listOf(Color.Transparent, Accent.copy(alpha = 0.35f), Color.Transparent),
                                    startY = y - 28f, endY = y + 28f,
                                ),
                            )
                            drawRect(Accent.copy(alpha = 0.9f), topLeft = androidx.compose.ui.geometry.Offset(0f, y), size = androidx.compose.ui.geometry.Size(size.width, 2f))
                        },
                    )
                }
            } else {
                // Sisi belakang: identitas terverifikasi
                Column(Modifier.fillMaxSize().padding(start = 18.dp, end = 18.dp, top = 20.dp, bottom = 14.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
                        Avatar(info)
                        Spacer(Modifier.width(14.dp))
                        Column(Modifier.weight(1f)) {
                            Text(
                                info?.name?.uppercase() ?: "AKUN TERVERIFIKASI",
                                color = Color.White, fontWeight = FontWeight.Black, fontSize = 15.sp,
                                maxLines = 2, overflow = TextOverflow.Ellipsis,
                            )
                            val sub = listOfNotNull(info?.pangkat?.takeIf { it.isNotBlank() }, info?.nrp?.takeIf { it.isNotBlank() }?.let { "NRP $it" })
                            if (sub.isNotEmpty()) {
                                Text(sub.joinToString(" / "), color = Color.White.copy(alpha = 0.78f), fontSize = 11.sp, fontFamily = FontFamily.Monospace, maxLines = 1, overflow = TextOverflow.Ellipsis)
                            }
                            info?.jabatan?.takeIf { it.isNotBlank() }?.let {
                                Text(it, color = Accent, fontSize = 10.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                            }
                        }
                        Icon(Icons.Filled.Verified, contentDescription = null, tint = Ok, modifier = Modifier.size(26.dp))
                    }
                    Text("VERIFIKASI", color = Color.White.copy(alpha = 0.6f), fontSize = 9.sp, fontWeight = FontWeight.Bold, letterSpacing = 2.sp)
                    Spacer(Modifier.height(4.dp))
                    Box(Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(3.dp)).background(Color.White.copy(alpha = 0.12f))) {
                        Box(
                            Modifier.fillMaxHeight().fillMaxWidth(fill.value)
                                .background(Brush.horizontalGradient(listOf(Accent, Ok))),
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun Avatar(info: IdentityCardInfo?) {
    val initials = info?.name?.split(' ')?.filter { it.isNotBlank() }?.take(2)?.joinToString("") { it.first().uppercase() } ?: "?"
    Box(
        Modifier.size(64.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.12f)).border(2.dp, Ok, CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        Text(initials, color = Color.White, fontWeight = FontWeight.Black, fontSize = 20.sp)
        if (!info?.avatarUrl.isNullOrBlank()) {
            AsyncImage(
                model = info?.avatarUrl,
                contentDescription = null,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
            )
        }
    }
}

/** Kerangka abu-abu dengan kilau bergerak. */
private fun Modifier.skeleton(progress: Float): Modifier = this
    .background(Color.White.copy(alpha = 0.10f))
    .drawWithContent {
        drawContent()
        val w = size.width
        val x = w * progress
        drawRect(
            Brush.horizontalGradient(
                listOf(Color.Transparent, Color.White.copy(alpha = 0.18f), Color.Transparent),
                startX = x - w * 0.4f, endX = x + w * 0.4f,
            ),
        )
    }
