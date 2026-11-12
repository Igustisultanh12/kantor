package id.my.sisinden.app.ui.attendance

import id.my.sisinden.app.core.realtime.onRealtime
import android.annotation.SuppressLint
import android.content.Context
import android.location.Location
import android.location.LocationManager
import android.os.Build
import android.os.CancellationSignal
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.data.model.AttendanceResponse
import id.my.sisinden.app.data.model.AttendanceStatus
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.UiMessages
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull
import kotlin.coroutines.resume

/** Koordinat nyata dari perangkat (tidak ada koordinat bawaan/dummy). */
data class GeoPoint(val latitude: Double, val longitude: Double, val accuracyMeters: Float?)

data class AttendanceForm(
    val status: AttendanceStatus = AttendanceStatus.HADIR,
    val notes: String = "",
    val location: GeoPoint? = null,
    val locating: Boolean = false,
    val locationError: String? = null,
    val submitting: Boolean = false,
)

class AttendanceViewModel(
    private val repository: SindenRepository,
    private val appContext: Context,
) : ViewModel() {

    private val _state = MutableStateFlow<LoadState<AttendanceResponse>>(LoadState.Loading)
    val state: StateFlow<LoadState<AttendanceResponse>> = _state.asStateFlow()

    private val _form = MutableStateFlow(AttendanceForm())
    val form: StateFlow<AttendanceForm> = _form.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    val messages = UiMessages()

    init {
        onRealtime("attendance") { load() }
        load()
    }

    fun load(pullToRefresh: Boolean = false) {
        if (pullToRefresh) _refreshing.value = true else if (_state.value !is LoadState.Ready) _state.value = LoadState.Loading
        viewModelScope.launch {
            when (val res = repository.attendances()) {
                is ApiResult.Success -> _state.value = LoadState.Ready(res.data)
                is ApiResult.Failure -> {
                    if (_state.value is LoadState.Ready) {
                        messages.post(res.error.message)
                    } else {
                        _state.value = LoadState.Failed(res.error)
                    }
                }
            }
            _refreshing.value = false
        }
    }

    fun setStatus(status: AttendanceStatus) = _form.update { it.copy(status = status) }
    fun setNotes(notes: String) = _form.update { it.copy(notes = notes.take(1000)) }
    fun clearLocation() = _form.update { it.copy(location = null, locationError = null) }

    fun locationDenied() = _form.update {
        it.copy(locating = false, locationError = "Izin lokasi ditolak. Presensi wajib GPS: izinkan lokasi untuk SISINDEN.")
    }

    /** Dipanggil setelah izin lokasi diberikan pengguna. Presensi wajib GPS aktif. */
    @SuppressLint("MissingPermission")
    fun fetchLocation() {
        if (_form.value.locating) return
        _form.update { it.copy(locating = true, locationError = null) }
        viewModelScope.launch {
            val lm = appContext.getSystemService(Context.LOCATION_SERVICE) as? LocationManager
            val gpsOn = lm != null && runCatching { lm.isProviderEnabled(LocationManager.GPS_PROVIDER) }.getOrDefault(false)
            if (!gpsOn) {
                _form.update { it.copy(locating = false, location = null, locationError = "GPS mati. Nyalakan Lokasi/GPS di HP, lalu coba lagi.") }
                return@launch
            }
            val loc = runCatching { currentLocation() }.getOrNull()
            _form.update {
                when {
                    loc == null -> it.copy(locating = false, location = null, locationError = "Lokasi GPS belum didapat. Pastikan GPS aktif dan sinyal baik, lalu coba lagi.")
                    loc.isMockLocation() -> it.copy(locating = false, location = null, locationError = "Lokasi palsu (fake GPS) terdeteksi. Matikan aplikasi pemalsu lokasi.")
                    else -> it.copy(locating = false, location = loc.toGeoPoint())
                }
            }
        }
    }

    @Suppress("DEPRECATION")
    private fun Location.isMockLocation(): Boolean =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) isMock else isFromMockProvider

    @SuppressLint("MissingPermission")
    private suspend fun currentLocation(): Location? {
        val lm = appContext.getSystemService(Context.LOCATION_SERVICE) as? LocationManager ?: return null
        val providers = listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
            .filter { runCatching { lm.isProviderEnabled(it) }.getOrDefault(false) }
        if (providers.isEmpty()) return null

        // 1) Coba lokasi segar (Android 11+), batas 12 detik.
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            for (provider in providers) {
                val fresh = withTimeoutOrNull(12_000) {
                    suspendCancellableCoroutine<Location?> { cont ->
                        val signal = CancellationSignal()
                        cont.invokeOnCancellation { signal.cancel() }
                        lm.getCurrentLocation(provider, signal, appContext.mainExecutor) { loc -> cont.resume(loc) }
                    }
                }
                if (fresh != null) return fresh
            }
        }
        // 2) Lokasi terakhir yang diketahui (maks. 5 menit) sebagai cadangan.
        val recent = providers.mapNotNull { runCatching { lm.getLastKnownLocation(it) }.getOrNull() }
            .filter { System.currentTimeMillis() - it.time < 5 * 60 * 1000 }
            .maxByOrNull { it.time }
        return recent
    }

    private fun Location.toGeoPoint() = GeoPoint(latitude, longitude, if (hasAccuracy()) accuracy else null)

    /**
     * Kirim presensi. Wajib: lokasi GPS ([AttendanceForm.location]) dan
     * [photo] selfie tersenyum dari [SmileCameraDialog].
     */
    fun submit(photo: String) {
        val f = _form.value
        if (f.submitting) return
        val loc = f.location
        if (loc == null) {
            messages.post("Presensi wajib lokasi GPS. Ambil lokasi dulu.")
            return
        }
        if (photo.isBlank()) {
            messages.post("Presensi wajib foto senyum.")
            return
        }
        _form.update { it.copy(submitting = true) }
        viewModelScope.launch {
            val res = repository.recordAttendance(
                status = f.status.code,
                lat = loc.latitude,
                lng = loc.longitude,
                notes = f.notes,
                photo = photo,
                accuracy = loc.accuracyMeters,
            )
            _form.update { it.copy(submitting = false) }
            when (res) {
                is ApiResult.Success -> {
                    messages.post(res.data.message ?: "Presensi tercatat.")
                    // Lokasi dibuang agar presensi berikutnya mengambil GPS segar.
                    _form.update { it.copy(notes = "", location = null) }
                    load()
                }
                is ApiResult.Failure -> messages.post(res.error.message)
            }
        }
    }
}
