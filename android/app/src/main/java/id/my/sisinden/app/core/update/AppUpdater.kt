package id.my.sisinden.app.core.update

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageInfo
import android.content.pm.PackageInstaller
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import com.google.gson.JsonParser
import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.MainActivity
import id.my.sisinden.app.R
import id.my.sisinden.app.core.notification.CommsState
import id.my.sisinden.app.core.notification.NotificationHelper
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import java.io.File
import java.security.MessageDigest
import java.util.concurrent.TimeUnit

/** Rilis terbaru di GitHub Releases. */
data class ReleaseInfo(
    val version: String,
    val apkUrl: String,
    val apkName: String,
    val apkSize: Long,
    val sumsUrl: String?,
    val notes: String,
)

/** Status pembaruan untuk UI Pengaturan. */
sealed interface UpdateState {
    data object Idle : UpdateState
    data object Checking : UpdateState
    data class UpToDate(val version: String) : UpdateState
    data class Downloading(val version: String, val percent: Int) : UpdateState
    data class Installing(val version: String) : UpdateState
    data class Failed(val message: String) : UpdateState
}

/**
 * Pembaruan dari dalam aplikasi: cek rilis terbaru di GitHub Releases,
 * unduh APK, cocokkan SHA-256 (SHA256SUMS.txt) & tanda tangan, lalu pasang
 * lewat PackageInstaller. Di Android 12+ pembaruan berikutnya dapat terpasang
 * otomatis tanpa konfirmasi (setelah sekali dipasang dari aplikasi ini);
 * selain itu Android menampilkan dialog "Perbarui" satu ketukan.
 */
object AppUpdater {
    private const val REPO = "Igustisultanh12/kantor"
    private const val LATEST_URL = "https://api.github.com/repos/$REPO/releases/latest"
    private const val PREFS = "sinden_update"
    private const val K_AUTO = "auto_update"
    private const val K_LAST_CHECK = "last_check"
    const val NOTIFICATION_ID = 0x0BDA7E

    private val _state = MutableStateFlow<UpdateState>(UpdateState.Idle)
    val state: StateFlow<UpdateState> = _state.asStateFlow()

    private val http by lazy {
        OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(60, TimeUnit.SECONDS)
            .followRedirects(true)
            .build()
    }

    private fun prefs(context: Context) = context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun isAutoEnabled(context: Context) = prefs(context).getBoolean(K_AUTO, true)
    fun setAutoEnabled(context: Context, enabled: Boolean) {
        prefs(context).edit().putBoolean(K_AUTO, enabled).apply()
        if (enabled) schedule(context) else WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME)
    }

    // ------------------------------------------------------------------ versi

    /** Bandingkan "1.10.0" vs "1.9.2" secara numerik; awalan "v" & akhiran "-debug" diabaikan. */
    fun compareVersions(a: String, b: String): Int {
        fun parts(v: String) = v.trim().removePrefix("v").substringBefore('-').split('.').map { it.toIntOrNull() ?: 0 }
        val x = parts(a)
        val y = parts(b)
        for (i in 0 until maxOf(x.size, y.size)) {
            val c = (x.getOrElse(i) { 0 }).compareTo(y.getOrElse(i) { 0 })
            if (c != 0) return c
        }
        return 0
    }

    /** Ambil SHA-256 untuk [fileName] dari isi SHA256SUMS.txt. */
    fun shaFor(sums: String, fileName: String): String? = sums.lineSequence()
        .map { it.trim().split(Regex("\\s+")) }
        .firstOrNull { it.size >= 2 && it.last().removePrefix("*") == fileName }
        ?.first()?.lowercase()

    /** Pilih APK rilis bertanda tangan dari daftar aset ("SISINDEN-1.5.0.apk"). */
    fun parseRelease(json: String): ReleaseInfo? {
        val obj = JsonParser.parseString(json).asJsonObject
        if (obj.get("draft")?.asBoolean == true || obj.get("prerelease")?.asBoolean == true) return null
        val version = obj.get("tag_name")?.asString?.removePrefix("v") ?: return null
        var apk: Triple<String, String, Long>? = null
        var sums: String? = null
        obj.getAsJsonArray("assets")?.forEach { el ->
            val a = el.asJsonObject
            val name = a.get("name")?.asString ?: return@forEach
            val url = a.get("browser_download_url")?.asString ?: return@forEach
            when {
                name == "SISINDEN-$version.apk" -> apk = Triple(name, url, a.get("size")?.asLong ?: 0L)
                name == "SHA256SUMS.txt" -> sums = url
            }
        }
        val chosen = apk ?: return null
        return ReleaseInfo(version, chosen.second, chosen.first, chosen.third, sums, obj.get("body")?.takeIf { !it.isJsonNull }?.asString.orEmpty())
    }

    // ------------------------------------------------------------------ alur

    suspend fun fetchLatest(): ReleaseInfo? = withContext(Dispatchers.IO) {
        val req = Request.Builder().url(LATEST_URL)
            .header("Accept", "application/vnd.github+json")
            .header("User-Agent", "SISINDEN-Android/${BuildConfig.VERSION_NAME}")
            .build()
        http.newCall(req).execute().use { res ->
            if (!res.isSuccessful) throw IllegalStateException("Server rilis menjawab ${res.code}")
            parseRelease(res.body?.string().orEmpty())
        }
    }

    /**
     * Cek → unduh → pasang. [interactive] = dipicu pengguna (tampilkan status
     * "sudah terbaru"); jika false hanya bertindak saat ada versi baru.
     */
    suspend fun checkAndUpdate(context: Context, interactive: Boolean): UpdateState {
        val app = context.applicationContext
        if (_state.value is UpdateState.Downloading || _state.value is UpdateState.Installing) return _state.value
        _state.value = UpdateState.Checking
        val result = try {
            val latest = fetchLatest()
            prefs(app).edit().putLong(K_LAST_CHECK, System.currentTimeMillis()).apply()
            if (latest == null || compareVersions(latest.version, BuildConfig.VERSION_NAME) <= 0) {
                UpdateState.UpToDate(BuildConfig.VERSION_NAME)
            } else {
                val apk = download(app, latest)
                verify(app, apk, latest)
                if (CommsState.activeCallThread != null) {
                    UpdateState.Failed("Pembaruan ditunda karena sedang dalam panggilan.")
                } else {
                    install(app, apk, latest.version)
                    UpdateState.Installing(latest.version)
                }
            }
        } catch (e: UpdateException) {
            UpdateState.Failed(e.message ?: "Pembaruan gagal.")
        } catch (e: Exception) {
            UpdateState.Failed("Tidak dapat memeriksa pembaruan. Periksa koneksi internet.")
        }
        _state.value = if (!interactive && result is UpdateState.UpToDate) UpdateState.Idle else result
        return result
    }

    /** Cek otomatis saat aplikasi dibuka (paling sering tiap 6 jam). */
    suspend fun autoCheckOnOpen(context: Context) {
        if (!isAutoEnabled(context) || BuildConfig.DEBUG) return
        val last = prefs(context).getLong(K_LAST_CHECK, 0L)
        if (System.currentTimeMillis() - last < 6 * 3_600_000L) return
        checkAndUpdate(context, interactive = false)
    }

    class UpdateException(message: String) : Exception(message)

    private suspend fun download(context: Context, info: ReleaseInfo): File = withContext(Dispatchers.IO) {
        val dir = File(context.cacheDir, "updates").apply { mkdirs() }
        dir.listFiles()?.filter { it.name != info.apkName }?.forEach { it.delete() }
        val target = File(dir, info.apkName)
        val part = File(dir, info.apkName + ".part")
        val req = Request.Builder().url(info.apkUrl).header("User-Agent", "SISINDEN-Android/${BuildConfig.VERSION_NAME}").build()
        http.newCall(req).execute().use { res ->
            if (!res.isSuccessful) throw UpdateException("Unduhan pembaruan gagal (${res.code}).")
            val body = res.body ?: throw UpdateException("Berkas pembaruan kosong.")
            val total = body.contentLength().takeIf { it > 0 } ?: info.apkSize
            var done = 0L
            var lastPercent = -1
            body.byteStream().use { input ->
                part.outputStream().use { out ->
                    val buf = ByteArray(64 * 1024)
                    while (true) {
                        val n = input.read(buf)
                        if (n < 0) break
                        out.write(buf, 0, n)
                        done += n
                        val percent = if (total > 0) ((done * 100) / total).toInt().coerceIn(0, 100) else 0
                        if (percent != lastPercent) {
                            lastPercent = percent
                            _state.value = UpdateState.Downloading(info.version, percent)
                        }
                    }
                }
            }
        }
        if (target.exists()) target.delete()
        if (!part.renameTo(target)) throw UpdateException("Berkas pembaruan tidak dapat disimpan.")
        target
    }

    private suspend fun verify(context: Context, apk: File, info: ReleaseInfo) = withContext(Dispatchers.IO) {
        // 1) Integritas berkas terhadap SHA256SUMS.txt rilis.
        info.sumsUrl?.let { url ->
            val sums = http.newCall(Request.Builder().url(url).build()).execute().use { it.body?.string().orEmpty() }
            val expected = shaFor(sums, info.apkName)
            if (expected != null && expected != sha256(apk)) {
                apk.delete()
                throw UpdateException("Berkas pembaruan rusak (SHA-256 tidak cocok). Coba lagi.")
            }
        }
        // 2) Paket & tanda tangan harus sama dengan aplikasi terpasang.
        val pm = context.packageManager
        val archive = archiveInfo(pm, apk.absolutePath) ?: throw UpdateException("Berkas pembaruan tidak valid.")
        if (archive.packageName != context.packageName) throw UpdateException("Berkas pembaruan bukan untuk aplikasi ini.")
        val installed = installedInfo(pm, context.packageName)
        if (installed != null && signerDigests(archive).intersect(signerDigests(installed)).isEmpty()) {
            throw UpdateException(
                "Versi ${info.version} memakai kunci tanda tangan resmi yang baru. Untuk sekali ini, uninstall SiNDEN lalu pasang APK ${info.version} dari GitHub Releases. Pembaruan berikutnya otomatis."
            )
        }
    }

    private fun sha256(file: File): String {
        val md = MessageDigest.getInstance("SHA-256")
        file.inputStream().use { input ->
            val buf = ByteArray(64 * 1024)
            while (true) {
                val n = input.read(buf)
                if (n < 0) break
                md.update(buf, 0, n)
            }
        }
        return md.digest().joinToString("") { "%02x".format(it) }
    }

    @Suppress("DEPRECATION")
    private fun archiveInfo(pm: PackageManager, path: String): PackageInfo? {
        val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) PackageManager.GET_SIGNING_CERTIFICATES else PackageManager.GET_SIGNATURES
        return pm.getPackageArchiveInfo(path, flags)
    }

    @Suppress("DEPRECATION")
    private fun installedInfo(pm: PackageManager, pkg: String): PackageInfo? = try {
        val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) PackageManager.GET_SIGNING_CERTIFICATES else PackageManager.GET_SIGNATURES
        pm.getPackageInfo(pkg, flags)
    } catch (_: PackageManager.NameNotFoundException) {
        null
    }

    @Suppress("DEPRECATION")
    private fun signerDigests(info: PackageInfo): Set<String> {
        val sigs = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            val si = info.signingInfo ?: return emptySet()
            if (si.hasMultipleSigners()) si.apkContentsSigners else si.signingCertificateHistory
        } else {
            info.signatures
        }
        return sigs.orEmpty().map { sig ->
            MessageDigest.getInstance("SHA-256").digest(sig.toByteArray()).joinToString("") { "%02x".format(it) }
        }.toSet()
    }

    private suspend fun install(context: Context, apk: File, version: String) = withContext(Dispatchers.IO) {
        val installer = context.packageManager.packageInstaller
        val params = PackageInstaller.SessionParams(PackageInstaller.SessionParams.MODE_FULL_INSTALL).apply {
            setAppPackageName(context.packageName)
            setSize(apk.length())
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                // Tanpa konfirmasi bila Android mengizinkan (aplikasi ini pemasang sebelumnya).
                setRequireUserAction(PackageInstaller.SessionParams.USER_ACTION_NOT_REQUIRED)
            }
        }
        val sessionId = installer.createSession(params)
        installer.openSession(sessionId).use { session ->
            apk.inputStream().use { input ->
                session.openWrite("base.apk", 0, apk.length()).use { out ->
                    input.copyTo(out, 64 * 1024)
                    session.fsync(out)
                }
            }
            val intent = Intent(context, UpdateInstallReceiver::class.java).putExtra(UpdateInstallReceiver.EXTRA_VERSION, version)
            val flags = PendingIntent.FLAG_UPDATE_CURRENT or
                (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) PendingIntent.FLAG_MUTABLE else 0)
            val pi = PendingIntent.getBroadcast(context, sessionId, intent, flags)
            session.commit(pi.intentSender)
        }
    }

    internal fun setState(value: UpdateState) {
        _state.value = value
    }

    internal fun notify(context: Context, title: String, text: String, intent: PendingIntent?) {
        NotificationHelper.createNotificationChannel(context)
        val n = NotificationCompat.Builder(context, NotificationHelper.CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_sinden)
            .setContentTitle(title)
            .setContentText(text)
            .setStyle(NotificationCompat.BigTextStyle().bigText(text))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .apply { if (intent != null) setContentIntent(intent) }
            .build()
        try {
            context.getSystemService(NotificationManager::class.java).notify(NOTIFICATION_ID, n)
        } catch (_: SecurityException) {
        }
    }

    // ------------------------------------------------------------------ latar belakang

    private const val WORK_NAME = "sinden_app_update"

    fun schedule(context: Context) {
        if (!isAutoEnabled(context) || BuildConfig.DEBUG) return
        val req = PeriodicWorkRequestBuilder<UpdateWorker>(12, TimeUnit.HOURS)
            .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.UNMETERED).build())
            .build()
        WorkManager.getInstance(context).enqueueUniquePeriodicWork(WORK_NAME, ExistingPeriodicWorkPolicy.KEEP, req)
    }

    /** Dipanggil setelah aplikasi diperbarui (MY_PACKAGE_REPLACED). */
    fun onPackageReplaced(context: Context) {
        File(context.cacheDir, "updates").listFiles()?.forEach { it.delete() }
        val open = PendingIntent.getActivity(
            context, NOTIFICATION_ID,
            Intent(context, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        notify(context, "SiNDEN diperbarui", "Versi ${BuildConfig.VERSION_NAME} sudah terpasang. Ketuk untuk membuka.", open)
    }
}

/** Unduh & pasang pembaruan di latar belakang (Wi-Fi), tiap ±12 jam. */
class UpdateWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result {
        AppUpdater.checkAndUpdate(applicationContext, interactive = false)
        return Result.success()
    }
}

/** Hasil sesi PackageInstaller. */
class UpdateInstallReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val version = intent.getStringExtra(EXTRA_VERSION).orEmpty()
        when (intent.getIntExtra(PackageInstaller.EXTRA_STATUS, PackageInstaller.STATUS_FAILURE)) {
            PackageInstaller.STATUS_PENDING_USER_ACTION -> {
                @Suppress("DEPRECATION")
                val confirm: Intent = (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                    intent.getParcelableExtra(Intent.EXTRA_INTENT, Intent::class.java)
                } else {
                    intent.getParcelableExtra(Intent.EXTRA_INTENT)
                }) ?: return
                confirm.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                if (CommsState.appForeground) {
                    try {
                        context.startActivity(confirm)
                        return
                    } catch (_: Exception) {
                        // jatuh ke notifikasi
                    }
                }
                val pi = PendingIntent.getActivity(context, AppUpdater.NOTIFICATION_ID, confirm, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
                AppUpdater.notify(context, "Pembaruan SiNDEN $version siap", "Ketuk untuk memasang pembaruan.", pi)
            }
            PackageInstaller.STATUS_SUCCESS -> AppUpdater.setState(UpdateState.UpToDate(version))
            else -> {
                val msg = intent.getStringExtra(PackageInstaller.EXTRA_STATUS_MESSAGE).orEmpty()
                val status = intent.getIntExtra(PackageInstaller.EXTRA_STATUS, PackageInstaller.STATUS_FAILURE)
                val text = when (status) {
                    PackageInstaller.STATUS_FAILURE_ABORTED -> "Pemasangan dibatalkan."
                    PackageInstaller.STATUS_FAILURE_CONFLICT ->
                        "Pembaruan bentrok dengan aplikasi terpasang (tanda tangan berbeda). Uninstall sekali, lalu pasang versi $version."
                    PackageInstaller.STATUS_FAILURE_STORAGE -> "Ruang penyimpanan HP tidak cukup."
                    else -> "Pemasangan gagal${if (msg.isNotBlank()) ": $msg" else "."}"
                }
                AppUpdater.setState(UpdateState.Failed(text))
                if (!CommsState.appForeground && status != PackageInstaller.STATUS_FAILURE_ABORTED) {
                    AppUpdater.notify(context, "Pembaruan SiNDEN gagal", text, null)
                }
            }
        }
    }

    companion object {
        const val EXTRA_VERSION = "version"
    }
}
