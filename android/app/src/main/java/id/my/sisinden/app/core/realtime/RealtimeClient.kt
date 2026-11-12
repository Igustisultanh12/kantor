package id.my.sisinden.app.core.realtime

import id.my.sisinden.app.BuildConfig
import android.util.Log
import com.pusher.client.Pusher
import com.pusher.client.PusherOptions
import com.pusher.client.channel.PrivateChannelEventListener
import com.pusher.client.channel.PusherEvent
import com.pusher.client.connection.ConnectionEventListener
import com.pusher.client.connection.ConnectionState
import com.pusher.client.connection.ConnectionStateChange
import com.pusher.client.util.HttpChannelAuthorizer
import id.my.sisinden.app.data.model.RealtimeConfigDto
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import org.json.JSONObject

/**
 * Realtime SISINDEN di aplikasi (Laravel Reverb, protokol Pusher).
 *
 * Server menyiarkan nama modul yang datanya berubah ke kanal privat
 * `private-sinden`; layar yang sedang dibuka lalu memuat ulang datanya
 * (lihat LoadableViewModel). Isi data tidak pernah lewat kanal ini.
 */
object RealtimeClient {
    private const val TAG = "SindenRealtime"

    private val _changes = MutableSharedFlow<Set<String>>(extraBufferCapacity = 16)
    /** Modul yang berubah, mis. {"letters"}. */
    val changes: SharedFlow<Set<String>> = _changes.asSharedFlow()

    private var pusher: Pusher? = null
    private var activeKey: String? = null

    /** Hubungkan (aman dipanggil berulang; tidak tersambung ulang bila konfigurasi & token sama). */
    @Synchronized
    fun start(config: RealtimeConfigDto?, baseUrl: String, token: String?) {
        if (config == null || !config.isComplete || token.isNullOrBlank()) {
            stop()
            return
        }
        val key = "${config.key}|${config.host}|${config.port}|$token"
        if (key == activeKey && pusher != null) return
        stop()

        // Audit R04: selalu TLS (soket mentah tidak tercakup usesCleartextTraffic=false) dan
        // endpoint otorisasi wajib path relatif di host yang sama (token tidak boleh terkirim ke host lain).
        val https = true
        val port = config.port ?: 443
        val authPath = config.authEndpoint?.takeIf { it.startsWith("/") && !it.startsWith("//") && !it.contains("@") }
            ?: "/api/mobile/broadcasting/auth"
        val authorizer = HttpChannelAuthorizer(baseUrl.trimEnd('/') + authPath).apply {
            setHeaders(mapOf("Authorization" to "Bearer $token", "Accept" to "application/json"))
        }
        val options = PusherOptions()
            .setHost(config.host)
            .setWsPort(port)
            .setWssPort(port)
            .setUseTLS(https)
            .setChannelAuthorizer(authorizer)
            .setActivityTimeout(60_000)

        val client = Pusher(config.key, options)
        client.connect(object : ConnectionEventListener {
            override fun onConnectionStateChange(change: ConnectionStateChange) {
                if (BuildConfig.DEBUG) Log.d(TAG, "Sambungan: ${change.previousState} -> ${change.currentState}")
            }

            override fun onError(message: String?, code: String?, e: Exception?) {
                Log.w(TAG, "Realtime error $code: $message")
            }
        }, ConnectionState.ALL)

        client.subscribePrivate(config.channel ?: "private-sinden", object : PrivateChannelEventListener {
            override fun onEvent(event: PusherEvent) {
                val modules = runCatching {
                    val arr = JSONObject(event.data).optJSONArray("modules")
                    (0 until (arr?.length() ?: 0)).map { arr!!.getString(it) }.toSet()
                }.getOrDefault(emptySet())
                if (modules.isNotEmpty()) _changes.tryEmit(modules)
            }

            override fun onSubscriptionSucceeded(channelName: String) {
                if (BuildConfig.DEBUG) Log.d(TAG, "Berlangganan $channelName")
            }

            override fun onAuthenticationFailure(message: String?, e: Exception?) {
                Log.w(TAG, "Otorisasi kanal gagal: $message")
            }
        }, config.event ?: "changed")

        pusher = client
        activeKey = key
    }

    /** Minta semua layar memuat ulang (mis. kembali dari latar belakang, sinyal yang terlewat). */
    fun nudge() {
        _changes.tryEmit(setOf("*"))
    }

    @Synchronized
    fun stop() {
        runCatching { pusher?.disconnect() }
        pusher = null
        activeKey = null
    }
}
