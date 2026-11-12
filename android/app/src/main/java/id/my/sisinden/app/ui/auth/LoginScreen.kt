package id.my.sisinden.app.ui.auth

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Badge
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Shield
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material.icons.outlined.VisibilityOff
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.core.data.LogoutReason
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.toneColors
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.BrandBackdrop
import id.my.sisinden.app.ui.common.FloatingLogo
import id.my.sisinden.app.ui.common.Slate300
import id.my.sisinden.app.ui.common.Slate400
import id.my.sisinden.app.ui.common.Slate900
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.togetherWith
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.spring
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import id.my.sisinden.app.core.ui.components.appear
import id.my.sisinden.app.core.ui.components.bouncyPress
import id.my.sisinden.app.core.ui.components.rememberReduceMotion
import java.time.Year

/** Gradien tombol "Masuk" Aurora (biru → indigo); teks putih ≥ 4,5:1 di seluruh rentang. */
private val SignInGradient = listOf(Color(0xFF2563EB), Color(0xFF4F46E5))

/**
 * Halaman masuk "Aurora" (selaras dengan login web): latar aurora bergerak,
 * logo muncul dengan pantulan & blur-in, kartu kaca naik dengan spring gaya
 * iOS, kartu bergetar halus saat login ditolak. Logika masuk tidak berubah.
 * Alamat server tidak dapat diubah dari aplikasi.
 */
@Composable
fun LoginScreen(
    repository: SindenRepository,
    logoutReason: LogoutReason?,
    onOpenActivation: () -> Unit,
) {
    val vm: LoginViewModel = viewModel { LoginViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val config by vm.config.collectAsStateWithLifecycle()
    val focus = LocalFocusManager.current
    var showPassword by rememberSaveable { mutableStateOf(false) }
    val appName = config?.appName?.takeIf { it.isNotBlank() } ?: "SI SINDEN"
    val reduce = rememberReduceMotion()

    // Getar halus saat pesan error baru muncul (umpan balik yang jelas, bukan sekadar teks merah).
    val shake = remember { Animatable(0f) }
    LaunchedEffect(state.errorMessage) {
        if (state.errorMessage != null && !reduce) {
            for (x in listOf(-14f, 12f, -8f, 5f, 0f)) shake.animateTo(x, spring(dampingRatio = 0.4f, stiffness = 2200f))
        }
    }

    // Dikeluarkan dari Profil > Perangkat & sesi oleh pemilik akun: beri tahu lewat popup (sekali).
    var revokedNotice by rememberSaveable(logoutReason) { mutableStateOf(logoutReason == LogoutReason.REVOKED) }
    if (revokedNotice) {
        androidx.compose.material3.AlertDialog(
            onDismissRequest = { revokedNotice = false },
            icon = { Icon(Icons.Outlined.Info, contentDescription = null) },
            title = { Text("Maaf, Anda telah keluar") },
            text = { Text("Perangkat ini telah dikeluarkan dari akun Anda. Silakan masuk kembali.") },
            confirmButton = { TextButton(onClick = { revokedNotice = false }) { Text("Mengerti") } },
        )
    }

    BrandBackdrop(backgroundUrl = config?.loginBackground) {
        Column(
            Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .navigationBarsPadding()
                .imePadding()
                .padding(horizontal = Spacing.xxl),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Spacer(Modifier.height(Spacing.xxl))
            FloatingLogo(size = 120.dp, modifier = Modifier.appear(index = 0, distance = 30f))
            Text(
                appName.uppercase(),
                style = MaterialTheme.typography.headlineLarge,
                color = Color.White,
                textAlign = TextAlign.Center,
                modifier = Modifier.appear(index = 1),
            )
            Spacer(Modifier.height(Spacing.xs))
            Text(
                "Sistem Informasi Detasemen Intelijen",
                style = MaterialTheme.typography.bodyMedium,
                color = Slate300,
                textAlign = TextAlign.Center,
                modifier = Modifier.appear(index = 2),
            )
            Spacer(Modifier.height(Spacing.xxl))

            // Kartu kaca Aurora
            val cardShape = RoundedCornerShape(30.dp)
            Column(
                Modifier
                    .appear(index = 3, distance = 80f)
                    .graphicsLayer { translationX = shake.value * density }
                    .widthIn(max = 460.dp)
                    .fillMaxWidth()
                    .shadow(30.dp, cardShape, clip = false, ambientColor = Color.Black, spotColor = Color.Black)
                    .clip(cardShape)
                    .background(Brush.linearGradient(listOf(Color(0xFF1E2946).copy(alpha = 0.82f), Color(0xFF0A1124).copy(alpha = 0.9f))))
                    .drawBehind {
                        // garis cahaya tipis di tepi atas + kilau lembut
                        drawRect(Brush.verticalGradient(0f to Color.White.copy(alpha = 0.08f), 0.3f to Color.Transparent))
                        drawLine(
                            Brush.horizontalGradient(listOf(Color.Transparent, Color(0xFF93C5FD).copy(alpha = 0.8f), Color.Transparent)),
                            start = Offset(size.width * 0.12f, 1f),
                            end = Offset(size.width * 0.88f, 1f),
                            strokeWidth = 2f,
                        )
                    }
                    .border(1.dp, Brush.verticalGradient(listOf(Color.White.copy(alpha = 0.18f), Color.White.copy(alpha = 0.04f))), cardShape)
                    .padding(Spacing.xxl),
                verticalArrangement = Arrangement.spacedBy(Spacing.md),
            ) {
                Column {
                    Text("Masuk Akun", style = MaterialTheme.typography.headlineSmall, color = Color.White)
                    Spacer(Modifier.height(Spacing.xs))
                    Text(
                        "Gunakan akun internal Anda untuk mengakses SISINDEN.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Slate300,
                    )
                }

                if (logoutReason == LogoutReason.EXPIRED || logoutReason == LogoutReason.DISABLED) {
                    Banner(
                        text = if (logoutReason == LogoutReason.EXPIRED) "Sesi Anda telah berakhir. Silakan masuk kembali."
                        else "Akun Anda dinonaktifkan oleh Admin.",
                        tone = Tone.WARNING,
                    )
                }
                AnimatedVisibility(visible = state.errorMessage != null) {
                    Banner(text = state.errorMessage.orEmpty(), tone = Tone.DANGER)
                }
                AnimatedVisibility(visible = state.otpRequired && state.infoMessage != null) {
                    Banner(text = state.infoMessage.orEmpty(), tone = Tone.WARNING)
                }
                if (state.otpRequired) {
                    DarkField(
                        value = state.otp,
                        onValueChange = vm::onOtp,
                        label = "Kode OTP WhatsApp (6 digit)",
                        error = state.otpError,
                        leading = { Icon(Icons.Outlined.Shield, contentDescription = null) },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword, imeAction = ImeAction.Done),
                        keyboardActions = KeyboardActions(onDone = {
                            focus.clearFocus()
                            vm.submit()
                        }),
                    )
                    TextButton(onClick = vm::cancelOtp, modifier = Modifier.align(Alignment.CenterHorizontally)) {
                        Text("Batal & ubah akun", style = MaterialTheme.typography.labelMedium, color = Color(0xFF93C5FD))
                    }
                }

                if (!state.otpRequired) DarkField(
                    value = state.identifier,
                    onValueChange = vm::onIdentifier,
                    label = "NRP, Username, atau Email",
                    error = state.identifierError,
                    leading = { Icon(Icons.Outlined.Badge, contentDescription = null) },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next),
                )
                if (!state.otpRequired) DarkField(
                    value = state.password,
                    onValueChange = vm::onPassword,
                    label = "Kata sandi",
                    error = state.passwordError,
                    leading = { Icon(Icons.Outlined.Lock, contentDescription = null) },
                    trailing = {
                        IconButton(onClick = { showPassword = !showPassword }) {
                            Icon(
                                if (showPassword) Icons.Outlined.VisibilityOff else Icons.Outlined.Visibility,
                                contentDescription = if (showPassword) "Sembunyikan kata sandi" else "Tampilkan kata sandi",
                            )
                        }
                    },
                    visualTransformation = if (showPassword) VisualTransformation.None else PasswordVisualTransformation(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done),
                    keyboardActions = KeyboardActions(onDone = {
                        focus.clearFocus()
                        vm.submit()
                    }),
                )

                val btnShape = RoundedCornerShape(18.dp)
                Button(
                    onClick = { focus.clearFocus(); vm.submit() },
                    enabled = !state.busy,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(58.dp)
                        .bouncyPress(0.96f)
                        .shadow(14.dp, btnShape, clip = false, ambientColor = Color.Black, spotColor = Color.Black)
                        .clip(btnShape)
                        .background(Brush.linearGradient(SignInGradient))
                        .drawBehind { drawRect(Brush.verticalGradient(0f to Color.White.copy(alpha = 0.25f), 0.5f to Color.Transparent)) },
                    shape = btnShape,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Color.Transparent,
                        contentColor = Color.White,
                        disabledContainerColor = Color.Black.copy(alpha = 0.25f),
                        disabledContentColor = Color.White.copy(alpha = 0.85f),
                    ),
                ) {
                    AnimatedContent(
                        targetState = state.busy,
                        // label lama hilang dulu, baru label baru muncul (tidak bertumpuk)
                        transitionSpec = {
                            androidx.compose.animation.fadeIn(androidx.compose.animation.core.tween(150, delayMillis = 90)) togetherWith
                                androidx.compose.animation.fadeOut(androidx.compose.animation.core.tween(90))
                        },
                        label = "signInLabel",
                    ) { busy ->
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            if (busy) {
                                CircularProgressIndicator(Modifier.size(20.dp), color = Color.White, strokeWidth = 2.dp)
                                Spacer(Modifier.width(Spacing.sm))
                                Text("Memverifikasi...", style = MaterialTheme.typography.labelLarge)
                            } else {
                                Icon(Icons.Outlined.Shield, contentDescription = null, modifier = Modifier.size(20.dp))
                                Spacer(Modifier.width(Spacing.sm))
                                Text(if (state.otpRequired) "Verifikasi OTP" else "Masuk", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Black)
                            }
                        }
                    }
                }

                TextButton(onClick = onOpenActivation, modifier = Modifier.align(Alignment.CenterHorizontally)) {
                    Text(
                        "Belum aktif? Aktivasi akun dengan token Admin",
                        style = MaterialTheme.typography.labelMedium,
                        color = Color(0xFF93C5FD),
                        textAlign = TextAlign.Center,
                    )
                }
            }

            Spacer(Modifier.height(Spacing.xxl))
            Text(
                "© ${Year.now().value} $appName · Versi ${BuildConfig.VERSION_NAME}",
                style = MaterialTheme.typography.labelSmall,
                color = Slate400,
                textAlign = TextAlign.Center,
                modifier = Modifier.appear(index = 5),
            )
            Spacer(Modifier.height(Spacing.lg))
        }

        SecurityCheckOverlay(
            step = state.step,
            labels = listOf("Memeriksa keamanan sistem", "Memeriksa sesi login", "Masuk ke beranda"),
            action = TurnstileAction.LOGIN,
            widgetKey = state.widgetKey,
            showWidget = state.showWidget,
            onToken = vm::onTurnstileToken,
            onWidgetError = vm::onTurnstileError,
            onCancel = vm::cancelCheck,
        )
    }
}

/** Field teks bergaya web (bg-white/10, border-white/10, teks putih). */
@Composable
private fun DarkField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    error: String?,
    leading: @Composable () -> Unit,
    keyboardOptions: KeyboardOptions,
    trailing: (@Composable () -> Unit)? = null,
    visualTransformation: VisualTransformation = VisualTransformation.None,
    keyboardActions: KeyboardActions = KeyboardActions.Default,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        modifier = Modifier.fillMaxWidth(),
        label = { Text(label) },
        isError = error != null,
        supportingText = error?.let { { Text(it) } },
        singleLine = true,
        leadingIcon = leading,
        trailingIcon = trailing,
        visualTransformation = visualTransformation,
        keyboardOptions = keyboardOptions,
        keyboardActions = keyboardActions,
        shape = RoundedCornerShape(18.dp),
        textStyle = MaterialTheme.typography.bodyLarge,
        colors = OutlinedTextFieldDefaults.colors(
            focusedTextColor = Color.White,
            unfocusedTextColor = Color.White,
            focusedContainerColor = Color.White.copy(alpha = 0.10f),
            unfocusedContainerColor = Color.White.copy(alpha = 0.08f),
            errorContainerColor = Color.White.copy(alpha = 0.08f),
            focusedBorderColor = Color(0xFF60A5FA),
            unfocusedBorderColor = Color.White.copy(alpha = 0.14f),
            focusedLabelColor = Color(0xFF93C5FD),
            unfocusedLabelColor = Slate300,
            focusedLeadingIconColor = Color(0xFF93C5FD),
            unfocusedLeadingIconColor = Slate300,
            focusedTrailingIconColor = Color.White,
            unfocusedTrailingIconColor = Slate300,
            cursorColor = Color(0xFF93C5FD),
            errorTextColor = Color.White,
            errorLabelColor = Color(0xFFFCA5A5),
            errorBorderColor = Color(0xFFF87171),
            errorSupportingTextColor = Color(0xFFFCA5A5),
            errorLeadingIconColor = Color(0xFFFCA5A5),
        ),
    )
}

@Composable
internal fun Banner(text: String, tone: Tone) {
    val c = toneColors(tone)
    Row(
        Modifier.fillMaxWidth().clip(MaterialTheme.shapes.small).background(c.bg).padding(Spacing.md),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Outlined.Info, contentDescription = null, tint = c.fg, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(Spacing.sm))
        Text(text, color = c.fg, style = MaterialTheme.typography.bodySmall)
    }
}
