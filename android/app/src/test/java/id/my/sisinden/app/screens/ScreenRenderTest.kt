package id.my.sisinden.app.screens

import android.app.Application
import androidx.activity.ComponentActivity
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.test.junit4.createAndroidComposeRule
import androidx.compose.ui.test.onRoot
import androidx.test.core.app.ApplicationProvider
import com.github.takahirom.roborazzi.captureRoboImage
import id.my.sisinden.app.core.data.SessionManager
import id.my.sisinden.app.core.data.SessionState
import id.my.sisinden.app.core.data.SessionStore
import id.my.sisinden.app.core.network.ApiClient
import id.my.sisinden.app.core.security.AppLockManager
import id.my.sisinden.app.core.security.AppLockStore
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.core.ui.theme.TextScale
import id.my.sisinden.app.core.ui.theme.ThemeMode
import id.my.sisinden.app.data.model.UserProfile
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.attendance.AttendanceScreen
import id.my.sisinden.app.ui.chat.ChatHomeScreen
import id.my.sisinden.app.ui.dashboard.DashboardScreen
import id.my.sisinden.app.ui.duty.DutyRosterScreen
import id.my.sisinden.app.ui.letters.LetterLogsScreen
import id.my.sisinden.app.ui.menu.MenuScreen
import id.my.sisinden.app.ui.navigation.AppShell
import id.my.sisinden.app.ui.navigation.Routes
import id.my.sisinden.app.ui.navigation.novaTabs
import id.my.sisinden.app.ui.notifications.NotificationsScreen
import id.my.sisinden.app.ui.profile.ProfileScreen
import id.my.sisinden.app.ui.settings.SettingsScreen
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import okhttp3.Interceptor
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.Protocol
import okhttp3.Response
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assume.assumeTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.RuntimeEnvironment
import org.robolectric.annotation.Config
import org.robolectric.annotation.GraphicsMode
import java.io.File
import java.time.LocalDate

/**
 * Render layar utama ke PNG (terang & gelap) untuk memeriksa tata letak tanpa HP.
 * Hanya berjalan dengan: gradle testDebugUnitTest -Pscreens --tests "id.my.sisinden.app.screens.ScreenRenderTest"
 * Hasil: folder android/build/screens (PNG). Data contoh fiktif, tanpa jaringan.
 */
@RunWith(RobolectricTestRunner::class)
@GraphicsMode(GraphicsMode.Mode.NATIVE)
@Config(sdk = [34], qualifiers = "w393dp-h851dp-xxhdpi")
class ScreenRenderTest {

    @get:Rule
    val rule = createAndroidComposeRule<ComponentActivity>()

    private val outDir = File(System.getProperty("sinden.screens.out") ?: "build/screens")

    @Before
    fun gate() {
        assumeTrue(System.getProperty("sinden.screens") == "true")
        outDir.mkdirs()
    }

    // ------------------------------------------------------------------ data contoh

    private val allPerms = listOf(
        Perm.DASHBOARD_VIEW, Perm.PROFILE_VIEW, Perm.NOTIFICATIONS_VIEW, Perm.CHAT_VIEW,
        Perm.ATTENDANCE_VIEW, Perm.ATTENDANCE_CREATE, Perm.DUTY_VIEW, Perm.DUTY_PDF,
        Perm.LETTER_LOGS_VIEW, Perm.LETTER_LOGS_CREATE, Perm.LETTER_LOGS_PDF,
        Perm.SKHPP_VIEW, Perm.SIGNATURE_VIEW, Perm.CASH_VIEW, Perm.COMMANDER_VIEW,
        Perm.IBU_BETI_VIEW, Perm.MITRA_VIEW, Perm.TECHNICAL_CASH_VIEW, Perm.VIOLATIONS_VIEW,
        Perm.USERS_VIEW, Perm.BACKUP_VIEW, Perm.CATEGORIES_VIEW,
    )

    private val user = UserProfile(
        id = 1,
        name = "Bagus Pratama Wicaksono",
        nrp = "21345/P",
        role = "admin",
        pangkat = "Kapten Laut (P)",
        jabatan = "Kepala Urusan Administrasi Intelijen",
        permissions = allPerms,
    )

    private val today = LocalDate.now()

    private fun json(path: String): String = when {
        path.endsWith("/dashboard") -> """
            {"status":"success","summary":{
              "attendance":{"today":null,"total_attended_today":18,"total_active_personnel":42},
              "notifications_unread":5,"letter_logs_total":1284,"letter_logs_this_month":37,
              "skhpp_pending":4,"signature_pending":2,"cash_balance":125750000,"technical_cash_balance":8450000},
             "duty":{"sources":[{"id":1,"status":"published","periode_label":"November 2026"}],
              "my_dates":["${today.plusDays(2)}","${today.plusDays(9)}","${today.plusDays(16)}"],
              "upcoming":[{"date":"${today.plusDays(2)}","entries":[{"name":"Bagus","is_me":true,"role":"Perwira Jaga"}]}]}}
        """
        path.endsWith("/attendances") -> """
            {"status":"success","today_attendance":null,"total_attended_today":18,"total_active_personnel":42,
             "attendances":[
              {"id":3,"attendance_date":"${today.minusDays(1)}","time_in":"07:12:00","status":"hadir","location_name":"Mako Denintel"},
              {"id":2,"attendance_date":"${today.minusDays(2)}","time_in":"07:05:00","status":"dinas_luar","location_name":"Kantor Lantamal V Surabaya, Jl. Perak Barat"},
              {"id":1,"attendance_date":"${today.minusDays(3)}","time_in":"06:58:00","status":"piket"}]}
        """
        path.endsWith("/letter-logs") -> """
            {"status":"success","data":[
              {"id":1,"full_number":"B/1284/XI/2026","subject":"Laporan perkembangan situasi wilayah dan pengamanan pangkalan bulan November","recipient":"Danlantamal V","date":"$today","category_name":"Surat Biasa"},
              {"id":2,"full_number":"R/77/XI/2026","subject":"Permohonan dukungan kendaraan dinas","recipient":"Kadisharkan","date":"${today.minusDays(1)}","category_name":"Surat Rahasia","sub_category_name":"Internal"},
              {"id":3,"full_number":"ST/12/XI/2026","subject":"Surat telegram","recipient":"Seluruh unit","date":"${today.minusDays(2)}","category_name":"Surat Telegram"}]}
        """
        path.endsWith("/categories") -> """{"status":"success","data":[{"id":1,"name":"Surat Biasa","code":"B"},{"id":2,"name":"Surat Rahasia","code":"R"},{"id":3,"name":"Surat Telegram","code":"ST"}]}"""
        path.endsWith("/notifications") -> """
            {"status":"success","unreadCount":2,"notifications":[
              {"id":1,"title":"Dokumen menunggu TTE","message":"SKHPP a.n. Serda Ahmad Fauzi menunggu tanda tangan elektronik Anda.","type":"signature","is_read":false,"created_at":"${today}T08:10:00Z"},
              {"id":2,"title":"SP Jaga terbit","message":"SP Jaga periode November 2026 telah diterbitkan.","type":"duty","is_read":true,"created_at":"${today.minusDays(1)}T09:00:00Z"}]}
        """
        path.endsWith("/chat") || path.contains("/chat/sync") -> """
            {"is_operator":true,"threads":[
              {"id":1,"uuid":"a1","subject":"Koordinasi pengamanan","status":"OPEN","last_message_at":"5 menit yang lalu","unread_admin":3,"is_online":true,
               "user":{"id":2,"name":"Ahmad Fauzi","pangkat":"Serda"},"latest_message":{"sender_name":"Ahmad Fauzi","message":"Siap, laporan sudah saya kirim lewat agenda surat."}},
              {"id":2,"uuid":"a2","subject":"Pengajuan cuti","status":"CLOSED","last_message_at":"kemarin",
               "user":{"id":3,"name":"Rina Kartika Dewi Lestari","pangkat":"Letda Laut (K/W)"},"latest_message":{"sender_name":"Anda","message":"Baik, terima kasih."}}]}
        """
        path.endsWith("/duty-roster") -> """
            {"status":"success","sp":{"id":1,"nomor_sprin":"Sprin/45/XI/2026","bulan":${today.monthValue},"tahun":${today.year},"periode_label":"November 2026","status":"published","total_personel":24,"penandatangan":"Komandan Denintel"},
             "my_dates":["${today.plusDays(2)}"],"days":[
              {"date":"$today","is_today":true,"entries":[{"name":"Ahmad Fauzi","pangkat_korps":"Serda Mes","role":"Bintara Jaga"},{"name":"Rina Kartika","pangkat_korps":"Letda Laut (K/W)","role":"Perwira Jaga"}]},
              {"date":"${today.plusDays(2)}","has_me":true,"entries":[{"name":"Bagus Pratama Wicaksono","pangkat_korps":"Kapten Laut (P)","role":"Perwira Jaga","is_me":true}]}]}
        """
        path.endsWith("/me") -> """{"status":"success","user":${com.google.gson.Gson().toJson(user)}}"""
        else -> "{}"
    }

    private val fixtures = Interceptor { chain ->
        val req = chain.request()
        Response.Builder()
            .request(req)
            .protocol(Protocol.HTTP_1_1)
            .code(200)
            .message("OK")
            .body(json(req.url.encodedPath).trimIndent().toResponseBody("application/json".toMediaType()))
            .build()
    }

    private lateinit var repo: SindenRepository
    private lateinit var session: SessionState.LoggedIn

    private fun setup() {
        val app = ApplicationProvider.getApplicationContext<Application>()
        val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
        val store = SessionStore(app)
        val sm = SessionManager(store, scope)
        val lock = AppLockManager(AppLockStore(app))
        repo = SindenRepository(app, ApiClient(sm, store, fixtures), store, sm, scope, lock)
        session = SessionState.LoggedIn(user, user.permissionSet())
    }

    // ------------------------------------------------------------------ render

    private fun shot(name: String, tall: Boolean = false, tab: String? = null, scale: TextScale = TextScale.NORMAL, content: @Composable () -> Unit) {
        if (tall) RuntimeEnvironment.setQualifiers("+h1700dp")
        setup()
        var dark = false
        val mode = androidx.compose.runtime.mutableStateOf(ThemeMode.LIGHT)
        rule.mainClock.autoAdvance = false
        rule.setContent {
            SindenTheme(themeMode = mode.value, textScale = scale) {
                val tabs = novaTabs(session.permissions)
                AppShell(tabs = tabs, currentRoute = tab, showBottomBar = tab != null, onSelectTab = {}) { content() }
            }
        }
        for (m in listOf(ThemeMode.LIGHT, ThemeMode.DARK)) {
            mode.value = m
            repeat(30) {
                rule.mainClock.advanceTimeBy(200)
                Thread.sleep(30)
            }
            dark = m == ThemeMode.DARK
            rule.onRoot().captureRoboImage(File(outDir, "$name-${if (dark) "dark" else "light"}.png").path)
        }
    }

    @Test fun dashboard() = shot("01-beranda", tall = true, tab = Routes.HOME) {
        DashboardScreen(repo, session, onNavigate = {})
    }

    @Test fun dashboardFold() = shot("01b-beranda-layar", tab = Routes.HOME) {
        DashboardScreen(repo, session, onNavigate = {})
    }

    @Test fun attendance() = shot("02-absen", tall = true, tab = Routes.ATTENDANCE) {
        AttendanceScreen(repository = repo, canSubmit = true)
    }

    @Test fun menu() = shot("03-lainnya", tall = true, tab = Routes.MENU) {
        MenuScreen(
            permissions = session.permissions,
            user = user,
            onNavigate = {},
            dockRoutes = novaTabs(session.permissions).map { it.route }.toSet(),
        )
    }

    @Test fun letters() = shot("04-surat", tab = Routes.LETTERS) {
        LetterLogsScreen(repo, session.permissions, onBack = null, onOpenPdf = { _, _, _ -> })
    }

    @Test fun chat() = shot("05-pesan", tab = Routes.CHAT) {
        ChatHomeScreen(repository = repo, myUserId = user.id, onBack = null, onOpenThread = { _, _ -> }, onCall = { _, _, _ -> })
    }

    @Test fun profile() = shot("06-profil", tall = true) {
        ProfileScreen(repository = repo, session = session, onOpenSettings = {}, onOpenNotifications = {})
    }

    @Test fun settings() = shot("07-pengaturan", tall = true) {
        SettingsScreen(repo, onBack = {})
    }

    @Test fun duty() = shot("08-jaga", tall = true) {
        DutyRosterScreen(repository = repo, permissions = session.permissions, onOpenPdf = { _, _, _ -> }, onDownloadPdf = { _, _, _ -> })
    }

    @Test fun notifications() = shot("09-warta") {
        NotificationsScreen(repo, onBack = {})
    }

    // Ukuran teks "Sangat besar" (mode ramah lansia) — paling rawan bertabrakan.
    @Test fun dashboardXL() = shot("10-beranda-xl", tall = true, tab = Routes.HOME, scale = TextScale.EXTRA_LARGE) {
        DashboardScreen(repo, session, onNavigate = {})
    }

    @Test fun attendanceXL() = shot("11-absen-xl", tall = true, tab = Routes.ATTENDANCE, scale = TextScale.EXTRA_LARGE) {
        AttendanceScreen(repository = repo, canSubmit = true)
    }

    @Test fun menuXL() = shot("12-lainnya-xl", tall = true, tab = Routes.MENU, scale = TextScale.EXTRA_LARGE) {
        MenuScreen(
            permissions = session.permissions,
            user = user,
            onNavigate = {},
            dockRoutes = novaTabs(session.permissions).map { it.route }.toSet(),
        )
    }
}