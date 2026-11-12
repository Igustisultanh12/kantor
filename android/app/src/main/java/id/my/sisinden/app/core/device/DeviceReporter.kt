package id.my.sisinden.app.core.device

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationManager
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.BatteryManager
import android.os.Build
import android.provider.Settings
import androidx.core.content.ContextCompat
import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.core.notification.PushManager
import id.my.sisinden.app.data.model.DeviceHeartbeatRequest

/**
 * Info perangkat untuk halaman web Profil > Perangkat & sesi.
 *
 * IMEI tidak dikirim karena sejak Android 10 aplikasi biasa tidak diizinkan
 * membacanya; sebagai gantinya dipakai ANDROID_ID (unik per HP untuk aplikasi
 * ini). Lokasi hanya diambil bila izin lokasi sudah diberikan (mis. untuk
 * absensi) — laporan ini tidak pernah meminta izin baru.
 */
object DeviceReporter {

    fun snapshot(context: Context): DeviceHeartbeatRequest {
        val app = context.applicationContext
        val battery = app.registerReceiver(null, IntentFilter(Intent.ACTION_BATTERY_CHANGED))
        val level = battery?.let {
            val l = it.getIntExtra(BatteryManager.EXTRA_LEVEL, -1)
            val s = it.getIntExtra(BatteryManager.EXTRA_SCALE, -1)
            if (l >= 0 && s > 0) l * 100 / s else null
        }
        val status = battery?.getIntExtra(BatteryManager.EXTRA_STATUS, -1)
        val charging = status?.let { it == BatteryManager.BATTERY_STATUS_CHARGING || it == BatteryManager.BATTERY_STATUS_FULL }
        val location = lastLocation(app)
        val metrics = app.resources.displayMetrics

        return DeviceHeartbeatRequest(
            deviceId = runCatching { Settings.Secure.getString(app.contentResolver, Settings.Secure.ANDROID_ID) }.getOrNull(),
            manufacturer = Build.MANUFACTURER,
            model = Build.MODEL,
            osVersion = Build.VERSION.RELEASE,
            sdk = Build.VERSION.SDK_INT,
            appVersion = BuildConfig.VERSION_NAME,
            battery = level,
            charging = charging,
            network = networkLabel(app),
            screen = "${metrics.widthPixels}x${metrics.heightPixels}",
            lat = location?.latitude,
            lng = location?.longitude,
            accuracy = location?.takeIf { it.hasAccuracy() }?.accuracy,
            locationAt = location?.time,
            pushToken = PushManager.lastToken(app),
        )
    }

    private fun networkLabel(context: Context): String? {
        val cm = context.getSystemService(ConnectivityManager::class.java) ?: return null
        val caps = runCatching { cm.getNetworkCapabilities(cm.activeNetwork) }.getOrNull() ?: return "Tidak ada koneksi"
        return when {
            caps.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) -> "WiFi"
            caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) -> "Data seluler"
            caps.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET) -> "Ethernet"
            caps.hasTransport(NetworkCapabilities.TRANSPORT_VPN) -> "VPN"
            else -> "Lainnya"
        }
    }

    @SuppressLint("MissingPermission")
    private fun lastLocation(context: Context): Location? {
        val fine = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
        val coarse = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
        if (!fine && !coarse) return null
        val lm = context.getSystemService(LocationManager::class.java) ?: return null
        return runCatching {
            lm.getProviders(true).mapNotNull { lm.getLastKnownLocation(it) }.maxByOrNull { it.time }
        }.getOrNull()
    }
}
