package id.my.sisinden.app.ui.common

import android.content.Context
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import coil.compose.AsyncImage
import id.my.sisinden.app.R
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/**
 * Logo instansi (Pengaturan web → `agency_logo`, lewat /api/mobile/config).
 * Semua logo DI DALAM aplikasi (splash, login, kunci, beranda, lanyard) memakai
 * ini; logo aplikasi SISINDEN hanya untuk ikon peluncur (mipmap/ic_launcher).
 *
 * URL terakhir disimpan agar splash/kunci tetap menampilkan logo yang sama
 * sebelum config berhasil diambil; gambar sendiri di-cache Coil. Bila belum
 * pernah ada atau gagal dimuat, dipakai lambang instansi bawaan (ic_agency_logo).
 */
object AgencyBrand {
    private const val PREFS = "sinden_brand"
    private const val KEY_LOGO = "agency_logo_url"

    private val _logoUrl = MutableStateFlow<String?>(null)
    val logoUrl: StateFlow<String?> = _logoUrl.asStateFlow()

    @Volatile private var loaded = false

    fun ensureLoaded(context: Context) {
        if (loaded) return
        loaded = true
        val saved = context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(KEY_LOGO, null)
        if (_logoUrl.value == null) _logoUrl.value = saved
    }

    fun update(context: Context, url: String?) {
        val clean = url?.trim()?.takeIf { it.isNotEmpty() } ?: return
        loaded = true
        if (_logoUrl.value == clean) return
        _logoUrl.value = clean
        context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit().putString(KEY_LOGO, clean).apply()
    }
}

@Composable
fun AgencyLogo(
    modifier: Modifier = Modifier,
    contentDescription: String? = "Logo instansi",
    contentScale: ContentScale = ContentScale.Fit,
) {
    val context = LocalContext.current
    remember { AgencyBrand.ensureLoaded(context) }
    val url by AgencyBrand.logoUrl.collectAsState()
    val bundled = painterResource(R.drawable.ic_agency_logo)
    AsyncImage(
        model = url,
        contentDescription = contentDescription,
        placeholder = bundled,
        error = bundled,
        fallback = bundled,
        contentScale = contentScale,
        modifier = modifier,
    )
}
