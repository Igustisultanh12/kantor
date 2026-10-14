package id.my.sisinden.app.ui.navigation

import android.content.Intent
import androidx.compose.animation.Crossfade
import androidx.compose.animation.core.tween
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import id.my.sisinden.app.ui.lock.LockScreen
import id.my.sisinden.app.ui.lock.PinSetupScreen
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import id.my.sisinden.app.core.data.SessionState
import id.my.sisinden.app.core.notification.DutyReminders
import id.my.sisinden.app.core.ui.components.GlassBackground
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.auth.ActivationScreen
import id.my.sisinden.app.ui.auth.LoginScreen
import id.my.sisinden.app.ui.splash.SplashScreen

/**
 * Akar UI: memilih Splash → Autentikasi → Aplikasi utama berdasarkan status sesi.
 * Saat sesi berakhir (logout/token kedaluwarsa/akun dinonaktifkan), seluruh
 * tumpukan navigasi utama dibuang sehingga tidak ada layar data yang tersisa.
 */
@Composable
fun SindenRoot(
    repository: SindenRepository,
    newIntent: Intent?,
    onIntentHandled: () -> Unit,
) {
    val session by repository.session.state.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) {
        // Pulihkan sesi lokal, lalu segarkan permission dari server (jika terjangkau).
        if (repository.session.restore()) {
            repository.refreshSession()
        }
    }

    // Keluar akun → hapus pengingat jaga milik akun sebelumnya.
    val context = LocalContext.current
    LaunchedEffect(session is SessionState.LoggedOut) {
        if (session is SessionState.LoggedOut) DutyReminders.cancelAll(context)
    }

    val stage = when (session) {
        is SessionState.Restoring -> 0
        is SessionState.LoggedOut -> 1
        is SessionState.LoggedIn -> 2
    }

    Crossfade(targetState = stage, animationSpec = tween(350), label = "rootStage") { s ->
        when (s) {
            0 -> SplashScreen()
            1 -> AuthNavigation(repository, (session as? SessionState.LoggedOut))
            else -> {
                val loggedIn = session as? SessionState.LoggedIn
                if (loggedIn != null) {
                    SecuredMain(repository, loggedIn, newIntent, onIntentHandled)
                } else {
                    SplashScreen()
                }
            }
        }
    }
}

/**
 * Gerbang PIN/biometrik gaya M-Banking: buat PIN setelah login pertama,
 * lalu aplikasi terkunci saat dibuka ulang atau setelah 5 menit tidak aktif.
 * Saat terkunci, layar data dibuang dari komposisi (tidak tersisa di memori UI).
 */
@Composable
private fun SecuredMain(
    repository: SindenRepository,
    session: SessionState.LoggedIn,
    newIntent: Intent?,
    onIntentHandled: () -> Unit,
) {
    val appLock = repository.appLock
    val locked by appLock.locked.collectAsStateWithLifecycle()
    val scope = rememberCoroutineScope()
    val lifecycle = LocalLifecycleOwner.current.lifecycle
    val userId = session.user.id
    var pinVersion by remember { mutableIntStateOf(0) }
    val hasPin = remember(userId, pinVersion) { appLock.store.hasPin(userId) }

    // Pemeriksa "diam 5 menit" selama aplikasi tampil.
    LaunchedEffect(Unit) {
        lifecycle.repeatOnLifecycle(Lifecycle.State.STARTED) {
            while (true) {
                delay(10_000)
                appLock.checkIdle()
            }
        }
    }

    val resetAndLogout: () -> Unit = { scope.launch { repository.logoutAndResetPin() } }
    when {
        !hasPin -> PinSetupScreen(
            user = session.user,
            appLock = appLock,
            onDone = { pinVersion++ },
            onCancelLogout = { scope.launch { repository.logout() } },
        )
        locked -> LockScreen(
            user = session.user,
            appLock = appLock,
            onForgotPin = resetAndLogout,
            onTooManyAttempts = resetAndLogout,
        )
        else -> MainNavigation(
            repository = repository,
            session = session,
            newIntent = newIntent,
            onIntentHandled = onIntentHandled,
        )
    }
}

@Composable
private fun AuthNavigation(repository: SindenRepository, state: SessionState.LoggedOut?) {
    val nav = rememberNavController()
    // Latar untuk layar autentikasi yang memakai SindenScaffold transparan (mis. Aktivasi).
    GlassBackground {
    NavHost(navController = nav, startDestination = Routes.LOGIN) {
        composable(Routes.LOGIN) {
            LoginScreen(
                repository = repository,
                logoutReason = state?.reason,
                onOpenActivation = { nav.navigate(Routes.ACTIVATION) },
            )
        }
        composable(Routes.ACTIVATION) {
            ActivationScreen(
                repository = repository,
                onBack = { nav.popBackStack() },
                onActivated = { nav.popBackStack() },
            )
        }
    }
    }
}
