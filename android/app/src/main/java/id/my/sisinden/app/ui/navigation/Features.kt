package id.my.sisinden.app.ui.navigation

import android.net.Uri
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AccountBalance
import androidx.compose.material.icons.outlined.AccountBalanceWallet
import androidx.compose.material.icons.outlined.Create
import androidx.compose.material.icons.outlined.Build
import androidx.compose.material.icons.outlined.EventAvailable
import androidx.compose.material.icons.outlined.Gavel
import androidx.compose.material.icons.outlined.People
import androidx.compose.material.icons.outlined.HowToReg
import androidx.compose.material.icons.outlined.Group
import androidx.compose.material.icons.outlined.Email
import androidx.compose.material.icons.outlined.Forum
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.VerifiedUser
import androidx.compose.ui.graphics.vector.ImageVector
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.TileGradient
import id.my.sisinden.app.core.ui.theme.TilePalette

enum class FeatureGroup(val title: String) {
    OPERASIONAL("Operasional"),
    ADMINISTRASI("Administrasi Surat"),
    KEUANGAN("Keuangan"),
    PERSONEL("Personel & Disiplin"),
}

/**
 * Metadata tampilan modul (judul, ikon, rute). Apakah modul ditampilkan
 * ditentukan oleh [permission] yang dikirim server — bukan oleh role/nama
 * yang di-hardcode di aplikasi.
 */
enum class Feature(
    val route: String,
    val title: String,
    val description: String,
    val icon: ImageVector,
    val permission: String,
    val group: FeatureGroup,
    val tone: Tone,
) {
    DUTY(Routes.DUTY, "Daftar Jaga", "Jadwal SP Jaga Siaga Sintel", Icons.Outlined.EventAvailable, Perm.DUTY_VIEW, FeatureGroup.OPERASIONAL, Tone.INFO),
    ATTENDANCE(Routes.ATTENDANCE, "Presensi", "Lapor kehadiran harian", Icons.Outlined.HowToReg, Perm.ATTENDANCE_VIEW, FeatureGroup.OPERASIONAL, Tone.SUCCESS),
    NOTIFICATIONS(Routes.NOTIFICATIONS, "Notifikasi", "Warta & instruksi dinas", Icons.Outlined.Notifications, Perm.NOTIFICATIONS_VIEW, FeatureGroup.OPERASIONAL, Tone.WARNING),
    CHAT(Routes.CHAT, "Chat Dinas", "Pesan & panggilan video", Icons.Outlined.Forum, Perm.CHAT_VIEW, FeatureGroup.OPERASIONAL, Tone.INFO),
    LETTER_LOGS(Routes.LETTERS, "Agenda Surat", "Penomoran & rekap surat", Icons.Outlined.Email, Perm.LETTER_LOGS_VIEW, FeatureGroup.ADMINISTRASI, Tone.INFO),
    SKHPP(Routes.SKHPP, "SKHPP", "Hasil penelitian personel", Icons.Outlined.VerifiedUser, Perm.SKHPP_VIEW, FeatureGroup.ADMINISTRASI, Tone.SUCCESS),
    SIGNATURE(Routes.SIGNATURE, "Tanda Tangan", "Antrean TTE Komandan", Icons.Outlined.Create, Perm.SIGNATURE_VIEW, FeatureGroup.ADMINISTRASI, Tone.WARNING),
    CASH(Routes.CASH, "Buku Kas", "Kas pangkalan Denintel", Icons.Outlined.AccountBalanceWallet, Perm.CASH_VIEW, FeatureGroup.KEUANGAN, Tone.SUCCESS),
    TECHNICAL_CASH(Routes.TECHNICAL_CASH, "Kas Unit Teknis", "Dana Dan Unit Teknis", Icons.Outlined.Build, Perm.TECHNICAL_CASH_VIEW, FeatureGroup.KEUANGAN, Tone.INFO),
    COMMANDER(Routes.COMMANDER, "Rekening Komandan", "Mutasi rekening komandan", Icons.Outlined.AccountBalance, Perm.COMMANDER_VIEW, FeatureGroup.KEUANGAN, Tone.NEUTRAL),
    MITRA(Routes.MITRA, "Mitra", "Pembayaran mitra bulanan", Icons.Outlined.People, Perm.MITRA_VIEW, FeatureGroup.KEUANGAN, Tone.WARNING),
    VIOLATIONS(Routes.VIOLATIONS, "Pelanggaran", "Catatan disiplin prajurit", Icons.Outlined.Gavel, Perm.VIOLATIONS_VIEW, FeatureGroup.PERSONEL, Tone.DANGER),
    USERS(Routes.USERS, "Kelola Pengguna", "Akun & status personel", Icons.Outlined.Group, Perm.USERS_VIEW, FeatureGroup.PERSONEL, Tone.NEUTRAL);

    /** Warna ubin bergradien (gaya "Fitur Utama" Livin) — tiap modul punya warna sendiri. */
    val gradient: TileGradient
        get() = when (this) {
            DUTY -> TilePalette.Blue
            ATTENDANCE -> TilePalette.Teal
            NOTIFICATIONS -> TilePalette.Orange
            CHAT -> TilePalette.Violet
            LETTER_LOGS -> TilePalette.Sky
            SKHPP -> TilePalette.Violet
            SIGNATURE -> TilePalette.Orange
            CASH -> TilePalette.Teal
            TECHNICAL_CASH -> TilePalette.Lime
            COMMANDER -> TilePalette.Blue
            MITRA -> TilePalette.Sky
            VIOLATIONS -> TilePalette.Rose
            USERS -> TilePalette.Slate
        }

    companion object {
        fun permitted(permissions: PermissionSet): List<Feature> = entries.filter { permissions.has(it.permission) }
    }
}

object Routes {
    // Autentikasi
    const val LOGIN = "login"
    const val ACTIVATION = "activation"

    // Tab utama
    const val HOME = "home"
    const val DUTY = "duty"
    const val ATTENDANCE = "attendance"
    const val MENU = "menu"
    const val PROFILE = "profile"

    // Modul
    const val NOTIFICATIONS = "notifications"
    const val LETTERS = "letters"
    const val SKHPP = "skhpp"
    const val SKHPP_FORM = "skhpp_form"
    const val SIGNATURE = "signature"
    const val CASH = "cash"
    const val TECHNICAL_CASH = "technical_cash"
    const val COMMANDER = "commander"
    const val MITRA = "mitra"
    const val VIOLATIONS = "violations"
    const val VIOLATION_FORM = "violation_form"
    const val USERS = "users"
    const val SETTINGS = "settings"
    const val CHAT = "chat"

    // Berparameter — Chat & panggilan
    const val CHAT_THREAD = "chat_thread/{uuid}?title={title}"
    fun chatThread(uuid: String, title: String) = "chat_thread/${Uri.encode(uuid)}?title=${Uri.encode(title)}"

    const val CALL = "call/{uuid}?video={video}&incoming={incoming}&peer={peer}"
    fun call(uuid: String, video: Boolean, incoming: Boolean, peer: String) =
        "call/${Uri.encode(uuid)}?video=$video&incoming=$incoming&peer=${Uri.encode(peer)}"

    // Berparameter
    const val PDF = "pdf?path={path}&title={title}&file={file}&save={save}"
    fun pdf(apiPath: String, title: String, fileName: String, save: Boolean = false) =
        "pdf?path=${Uri.encode(apiPath)}&title=${Uri.encode(title)}&file=${Uri.encode(fileName)}&save=$save"

    const val DEEP_LINK_PREFIX = "sisinden://app/"

    val tabs = listOf(HOME, DUTY, ATTENDANCE, MENU, PROFILE)
}
