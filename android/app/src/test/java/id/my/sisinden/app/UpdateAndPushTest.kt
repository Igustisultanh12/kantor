package id.my.sisinden.app

import com.google.gson.Gson
import id.my.sisinden.app.core.update.AppUpdater
import id.my.sisinden.app.data.model.ConfigResponse
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class UpdateAndPushTest {

    @Test
    fun `perbandingan versi numerik`() {
        assertTrue(AppUpdater.compareVersions("1.10.0", "1.9.2") > 0)
        assertTrue(AppUpdater.compareVersions("v1.5.0", "1.4.0") > 0)
        assertEquals(0, AppUpdater.compareVersions("1.5", "1.5.0"))
        assertEquals(0, AppUpdater.compareVersions("1.5.0-debug", "1.5.0"))
        assertTrue(AppUpdater.compareVersions("1.4.9", "1.5.0") < 0)
    }

    @Test
    fun `memilih apk rilis resmi dan sha256`() {
        val json = """
            {"tag_name":"v1.6.0","draft":false,"prerelease":false,"body":"Catatan rilis",
             "assets":[
               {"name":"SISINDEN-1.6.0.aab","browser_download_url":"https://github.com/x/1.6.0.aab","size":10},
               {"name":"SISINDEN-1.6.0-debug.apk","browser_download_url":"https://github.com/x/debug.apk","size":11},
               {"name":"SISINDEN-1.6.0.apk","browser_download_url":"https://github.com/x/SISINDEN-1.6.0.apk","size":13920859},
               {"name":"SHA256SUMS.txt","browser_download_url":"https://github.com/x/SHA256SUMS.txt","size":261}
             ]}
        """.trimIndent()
        val r = AppUpdater.parseRelease(json)
        assertNotNull(r)
        assertEquals("1.6.0", r!!.version)
        assertEquals("https://github.com/x/SISINDEN-1.6.0.apk", r.apkUrl)
        assertEquals(13920859L, r.apkSize)
        assertEquals("https://github.com/x/SHA256SUMS.txt", r.sumsUrl)

        val sums = "abc123  SISINDEN-1.6.0.aab\nDEF456  SISINDEN-1.6.0.apk\n"
        assertEquals("def456", AppUpdater.shaFor(sums, "SISINDEN-1.6.0.apk"))
        assertNull(AppUpdater.shaFor(sums, "lain.apk"))

        // Rilis draft/prerelease atau tanpa APK resmi diabaikan.
        assertNull(AppUpdater.parseRelease("""{"tag_name":"v2.0.0","prerelease":true,"assets":[]}"""))
        assertNull(AppUpdater.parseRelease("""{"tag_name":"v2.0.0","assets":[{"name":"SISINDEN-2.0.0-debugsigned.apk","browser_download_url":"u"}]}"""))
    }

    @Test
    fun `konfigurasi push dari server`() {
        val cfg = Gson().fromJson(
            """{"status":"success","push":{"api_key":"AIza","app_id":"1:1:android:a","project_id":"p","sender_id":"1"},"push_enabled":true}""",
            ConfigResponse::class.java,
        )
        assertTrue(cfg.push!!.isComplete)
        assertEquals(true, cfg.pushEnabled)
        val none = Gson().fromJson("""{"status":"success","push":null,"push_enabled":false}""", ConfigResponse::class.java)
        assertNull(none.push)
        assertFalse(none.pushEnabled!!)
    }
}
