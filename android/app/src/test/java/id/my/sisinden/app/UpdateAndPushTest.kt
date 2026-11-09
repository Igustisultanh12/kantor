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

    private fun fixture(name: String): String =
        requireNotNull(javaClass.classLoader?.getResource("fixtures/$name.json")) { "fixture $name" }.readText()

    @Test
    fun `info pembaruan dari Pengaturan web`() {
        val r = AppUpdater.parseServer(fixture("app_update"), "https://sisinden.my.id")
        assertNotNull(r)
        assertEquals("1.7.0", r!!.version)
        assertEquals(16, r.versionCode)
        assertEquals("SISINDEN-1.7.0.apk", r.apkName)
        assertEquals(14_800_000L, r.apkSize)
        assertEquals("ab".repeat(32), r.sha256)
        assertEquals("https://sisinden.my.id/api/mobile/app-update/download?v=1.7.0", r.apkUrl)
        assertTrue(r.notes.contains("Ibu Beti"))

        // Belum ada APK di server.
        assertNull(AppUpdater.parseServer(fixture("app_update_none")))
    }

    @Test
    fun `unduhan hanya dari server SISINDEN dan wajib SHA-256`() {
        val base = "https://sisinden.my.id"
        val sha = "c".repeat(64)
        // URL server lain diabaikan → dipaksa ke endpoint server sendiri.
        val foreign = AppUpdater.parseServer("""{"available":true,"version":"1.8.0","sha256":"$sha","download_url":"https://contoh.lain/x.apk"}""", base)
        assertEquals("$base/api/mobile/app-update/download?v=1.8.0", foreign!!.apkUrl)
        val relative = AppUpdater.parseServer("""{"available":true,"version":"1.8.0","sha256":"$sha","download_url":"/api/mobile/app-update/download?v=1.8.0"}""", base)
        assertEquals("$base/api/mobile/app-update/download?v=1.8.0", relative!!.apkUrl)
        // Tanpa SHA-256 / versi tidak valid → ditolak.
        assertNull(AppUpdater.parseServer("""{"available":true,"version":"1.8.0","sha256":""}""", base))
        assertNull(AppUpdater.parseServer("""{"available":true,"version":"terbaru","sha256":"$sha"}""", base))
        assertTrue(AppUpdater.reason(java.net.UnknownHostException("sisinden.my.id")).contains("internet"))
    }

    @Test
    fun `pembaruan dari GitHub Release`() {
        val sha = "d76b28ba57032945678f6ff758f4c0032044c2ccaf0455bdc2a91086beb23fe6"
        val json = """
            [
              {"tag_name":"v1.9.0-beta","draft":false,"prerelease":true,"body":"","assets":[]},
              {"tag_name":"v1.8.0","draft":false,"prerelease":false,"body":"# SISINDEN\n\n- Menu Backup\n---\n- Minta akses via WA",
               "assets":[
                 {"name":"SISINDEN-1.8.0.aab","size":1,"digest":"sha256:${"a".repeat(64)}","browser_download_url":"https://github.com/Igustisultanh12/kantor/releases/download/v1.8.0/SISINDEN-1.8.0.aab"},
                 {"name":"SISINDEN-1.8.0.apk","size":14816095,"digest":"sha256:$sha","browser_download_url":"https://github.com/Igustisultanh12/kantor/releases/download/v1.8.0/SISINDEN-1.8.0.apk"}
               ]},
              {"tag_name":"v1.7.1","draft":false,"prerelease":false,"body":"","assets":[]}
            ]
        """.trimIndent()
        val r = AppUpdater.parseGitHub(json)!!
        assertEquals("1.8.0", r.version)
        assertEquals(sha, r.sha256)
        assertEquals(AppUpdater.SOURCE_GITHUB, r.source)
        assertEquals(14_816_095L, r.apkSize)
        assertTrue(r.apkUrl.endsWith("SISINDEN-1.8.0.apk"))
        assertEquals("- Menu Backup\n- Minta akses via WA", r.notes)

        // Tanpa digest: SHA-256 diambil dari SHA256SUMS.txt.
        val noDigest = """[{"tag_name":"v1.8.1","draft":false,"prerelease":false,"body":"","assets":[
            {"name":"SISINDEN-1.8.1.apk","size":5,"browser_download_url":"https://github.com/Igustisultanh12/kantor/releases/download/v1.8.1/SISINDEN-1.8.1.apk"},
            {"name":"SHA256SUMS.txt","size":5,"browser_download_url":"https://github.com/Igustisultanh12/kantor/releases/download/v1.8.1/SHA256SUMS.txt"}]}]"""
        val sums = "${"1".repeat(64)}  SISINDEN-1.8.1-debug.apk\n${"2".repeat(64)}  SISINDEN-1.8.1.apk\n"
        assertEquals("2".repeat(64), AppUpdater.parseGitHub(noDigest) { sums }!!.sha256)
        // Tanpa SHA-256 sama sekali → tidak dipakai.
        assertNull(AppUpdater.parseGitHub(noDigest))
        // URL di luar github.com ditolak.
        assertNull(AppUpdater.parseGitHub("""[{"tag_name":"v2.0.0","assets":[{"name":"x.apk","digest":"sha256:$sha","browser_download_url":"https://contoh.lain/x.apk"}]}]"""))
    }

    @Test
    fun `versi tertinggi dipilih, server diutamakan bila sama`() {
        val server = AppUpdater.parseServer("""{"available":true,"version":"1.8.0","sha256":"${"c".repeat(64)}"}""", "https://sisinden.my.id")!!
        val github = server.copy(version = "1.8.1", source = AppUpdater.SOURCE_GITHUB)
        assertEquals("1.8.1", AppUpdater.pickLatest(server, github)!!.version)
        assertEquals(AppUpdater.SOURCE_SERVER, AppUpdater.pickLatest(server, github.copy(version = "1.8.0"))!!.source)
        assertEquals(AppUpdater.SOURCE_GITHUB, AppUpdater.pickLatest(null, github)!!.source)
        assertNull(AppUpdater.pickLatest(null, null))
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
