package id.my.sisinden.app.data.repository

import android.content.Context
import android.os.Build
import id.my.sisinden.app.core.data.LogoutReason
import id.my.sisinden.app.core.data.SessionManager
import id.my.sisinden.app.core.data.SessionStore
import id.my.sisinden.app.core.network.ApiClient
import id.my.sisinden.app.core.network.ApiError
import id.my.sisinden.app.core.network.ApiErrors
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.network.ErrorKind
import id.my.sisinden.app.core.network.SindenApiService
import id.my.sisinden.app.core.network.safeApiCall
import id.my.sisinden.app.data.model.*
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.launch
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.File

/** Berkas foto terpilih untuk unggahan multipart. */
class UploadImage(val fileName: String, val mimeType: String, val bytes: ByteArray)

/**
 * Satu-satunya pintu akses data jaringan. ViewModel tidak memanggil Retrofit
 * langsung sehingga penanganan sesi & error seragam di seluruh aplikasi.
 */
class SindenRepository(
    private val appContext: Context,
    private val api: ApiClient,
    private val store: SessionStore,
    val session: SessionManager,
    private val appScope: CoroutineScope,
    /** Kunci PIN/biometrik (gaya M-Banking). */
    val appLock: id.my.sisinden.app.core.security.AppLockManager,
) {
    /** Jalankan pekerjaan yang harus selesai walau layar pemanggil ditutup (mis. logout). */
    fun appScopeLaunch(block: suspend () -> Unit) {
        appScope.launch { block() }
    }

    private suspend fun svc(): SindenApiService = api.service()

    private suspend fun <T> call(block: suspend SindenApiService.() -> retrofit2.Response<T>): ApiResult<T> =
        safeApiCall { svc().block() }

    // ------------------------------------------------------------- Sesi

    /**
     * @param turnstileToken token Cloudflare Turnstile dari widget login.
     * @param beforeSessionStart dijalankan setelah kredensial diterima server dan
     *   sebelum layar berpindah ke beranda (untuk animasi tahap akhir).
     */
    suspend fun login(
        username: String,
        password: String,
        turnstileToken: String? = null,
        beforeSessionStart: suspend () -> Unit = {},
    ): ApiResult<UserProfile> {
        val device = "SISINDEN Android (${Build.MANUFACTURER} ${Build.MODEL})".take(100)
        return when (val res = call { login(LoginRequest(username.trim(), password, device, turnstileToken)) }) {
            is ApiResult.Success -> {
                val token = res.data.token
                val user = res.data.user
                if (res.data.status == "success" && !token.isNullOrBlank() && user != null) {
                    beforeSessionStart()
                    // Baru saja masuk dengan kata sandi → tidak perlu PIN lagi untuk sesi ini.
                    appLock.unlock()
                    session.startSession(token, user)
                    ApiResult.Success(user)
                } else {
                    ApiResult.Failure(ApiError(ErrorKind.SERVER, res.data.message ?: "Login gagal diproses server."))
                }
            }
            is ApiResult.Failure -> res
        }
    }

    suspend fun activate(nrp: String, token: String, password: String, turnstileToken: String? = null): ApiResult<SimpleResponse> =
        call { activate(ActivateRequest(nrp.trim(), token.trim(), password, turnstileToken)) }

    /** Segarkan profil + permission dari server. */
    suspend fun refreshSession(): ApiResult<UserProfile> = when (val res = call { me() }) {
        is ApiResult.Success -> {
            val user = res.data.user
            if (user != null) {
                session.updateUser(user)
                ApiResult.Success(user)
            } else ApiResult.Failure(ApiError(ErrorKind.SERVER, "Profil tidak tersedia."))
        }
        is ApiResult.Failure -> res
    }

    /** Lupa PIN / PIN salah berulang: hapus PIN lalu keluar (wajib masuk dengan kata sandi). */
    suspend fun logoutAndResetPin() {
        appLock.store.clear()
        appLock.lock()
        logout()
    }

    suspend fun logout() {
        // Cabut token di server (best-effort), lalu hapus sesi lokal apa pun hasilnya.
        if (session.token != null) {
            val pushToken = id.my.sisinden.app.core.notification.PushManager.lastToken(appContext)
            call { logout(LogoutRequest(pushToken)) }
        }
        session.end(LogoutReason.USER)
    }

    suspend fun getConfig(): ApiResult<ConfigResponse> = call { getConfig() }.also { res ->
        // Simpan logo instansi terbaru untuk dipakai di seluruh aplikasi.
        if (res is ApiResult.Success) id.my.sisinden.app.ui.common.AgencyBrand.update(appContext, res.data.agencyLogo)
    }

    suspend fun pushTest() = call { pushTest() }

    /** Daftarkan token FCM perangkat ini ke akun yang sedang masuk. */
    suspend fun registerPushToken(token: String) = call {
        registerPush(PushRegisterRequest(token, id.my.sisinden.app.BuildConfig.VERSION_NAME, "${Build.MANUFACTURER} ${Build.MODEL}".take(100)))
    }

    // ------------------------------------------------------- Dashboard

    suspend fun dashboard(): ApiResult<DashboardResponse> {
        val res = call { dashboard() }
        if (res is ApiResult.Success) res.data.user?.let { session.updateUser(it) }
        return res
    }

    suspend fun dutyRoster(spId: Int? = null, bulan: Int? = null, tahun: Int? = null) =
        call { dutyRoster(spId, bulan, tahun) }

    // ------------------------------------------------------- Presensi

    suspend fun attendances() = call { attendances() }

    suspend fun recordAttendance(status: String, lat: Double?, lng: Double?, notes: String?, photo: String? = null) = call {
        recordAttendance(
            CreateAttendanceRequest(
                status = status,
                latitude = lat,
                longitude = lng,
                photo = photo,
                deviceModel = "${Build.MANUFACTURER} ${Build.MODEL} (Android ${Build.VERSION.RELEASE})".take(150),
                notes = notes?.takeIf { it.isNotBlank() },
            )
        )
    }

    // ----------------------------------------------------- Notifikasi

    suspend fun notifications() = call { notifications() }
    suspend fun markNotificationRead(id: Int) = call { markNotificationRead(id) }
    suspend fun markAllNotificationsRead() = call { markAllNotificationsRead() }

    // ------------------------------------------------------------ Kas

    suspend fun cashLedger(kind: CashKind) = call { if (kind == CashKind.BASE) cash() else technicalCash() }

    suspend fun saveCash(kind: CashKind, id: Int?, request: CashEntryRequest) = call {
        when {
            kind == CashKind.BASE && id == null -> createCash(request)
            kind == CashKind.BASE -> updateCash(id!!, request)
            id == null -> createTechnicalCash(request)
            else -> updateTechnicalCash(id, request)
        }
    }

    suspend fun deleteCash(kind: CashKind, id: Int) = call {
        if (kind == CashKind.BASE) deleteCash(id) else deleteTechnicalCash(id)
    }

    /** Simpan susunan struk transaksi: [keep] path lama yang dipertahankan + [files] berkas baru. */
    suspend fun syncCashReceipts(kind: CashKind, id: Int, keep: List<String>, files: List<UploadImage>): ApiResult<CashReceiptsResponse> {
        val parts = buildList {
            keep.forEach { add(MultipartBody.Part.createFormData("keep[]", it)) }
            files.forEach { f ->
                add(MultipartBody.Part.createFormData("receipt_files[]", f.fileName, f.bytes.toRequestBody(f.mimeType.toMediaType())))
            }
            // Agar request tetap multipart walau semua struk dihapus.
            if (isEmpty()) add(MultipartBody.Part.createFormData("clear", "1"))
        }
        return call { if (kind == CashKind.BASE) syncCashReceipts(id, parts) else syncTechnicalCashReceipts(id, parts) }
    }

    /** Unduh berkas dari URL server SISINDEN (mis. struk PDF) ke cache privat. */
    suspend fun downloadToCache(url: String, fileName: String, subdir: String = "receipts"): ApiResult<File> = withContext(Dispatchers.IO) {
        val dir = File(appContext.cacheDir, subdir).apply { mkdirs() }
        val safe = fileName.replace(Regex("[\\\\/:*?\"<>|\\u0000-\\u001F]+"), "_").trim().take(120).ifEmpty { "berkas" }
        val target = File(dir, safe)
        try {
            val response = svc().downloadFile(url)
            if (!response.isSuccessful) {
                val raw = try { response.errorBody()?.string() } catch (_: Exception) { null }
                return@withContext ApiResult.Failure(ApiErrors.fromHttp(response.code(), raw))
            }
            val body = response.body() ?: return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Server tidak mengirim berkas."))
            body.byteStream().use { input -> target.outputStream().use { input.copyTo(it) } }
            ApiResult.Success(target)
        } catch (c: CancellationException) {
            throw c
        } catch (t: Throwable) {
            ApiResult.Failure(ApiErrors.fromThrowable(t))
        }
    }

    // --------------------------------------------------- Agenda Surat

    suspend fun letterLogs(search: String?, categoryId: Int?) =
        call { letterLogs(search?.takeIf { it.isNotBlank() }, categoryId) }

    suspend fun categories() = call { categories() }
    suspend fun createLetterLog(request: CreateLetterLogRequest) = call { createLetterLog(request) }
    suspend fun updateLetterLog(id: Int, request: UpdateLetterLogRequest) = call { updateLetterLog(id, request) }
    suspend fun deleteLetterLog(id: Int) = call { deleteLetterLog(id) }

    // ----------------------------------------------------------- SKHPP

    suspend fun skhpp(status: String?, search: String?) =
        call { skhpp(status, search?.takeIf { it.isNotBlank() }) }

    suspend fun createSkhpp(
        fields: Map<String, String>,
        members: List<SkhppMemberInput>,
        photo1: UploadImage,
        photo2: UploadImage?,
    ): ApiResult<SimpleResponse> {
        val text = "text/plain".toMediaType()
        val parts = LinkedHashMap<String, RequestBody>()
        fields.forEach { (k, v) -> parts[k] = v.toRequestBody(text) }
        parts["has_pengikut"] = (if (members.isNotEmpty()) "1" else "0").toRequestBody(text)
        members.forEachIndexed { i, m ->
            parts["members[$i][nama]"] = m.nama.toRequestBody(text)
            parts["members[$i][pangkat_nrp_nik]"] = m.pangkatNrpNik.toRequestBody(text)
            parts["members[$i][jabatan]"] = m.jabatan.toRequestBody(text)
        }
        val files = buildList {
            add(MultipartBody.Part.createFormData("foto_1", photo1.fileName, photo1.bytes.toRequestBody(photo1.mimeType.toMediaType())))
            if (photo2 != null) {
                add(MultipartBody.Part.createFormData("foto_2", photo2.fileName, photo2.bytes.toRequestBody(photo2.mimeType.toMediaType())))
            }
        }
        return call { createSkhpp(parts, files) }
    }

    suspend fun approveSkhpp(id: Int, priority: String) = call { approveSkhpp(id, priority) }
    suspend fun rejectSkhpp(id: Int, note: String) = call { rejectSkhpp(id, note) }
    suspend fun deleteSkhpp(id: Int) = call { deleteSkhpp(id) }

    // ---------------------------------------------------- Tanda Tangan

    suspend fun signatureRequests() = call { signatureRequests() }
    suspend fun approveSignature(id: Int) = call { approveSignature(id, SignatureDecisionRequest()) }
    suspend fun rejectSignature(id: Int, note: String) = call { rejectSignature(id, SignatureDecisionRequest(note)) }

    // ----------------------------------------------- Rekening Komandan

    suspend fun commanderAccount(month: String?) = call { commanderAccount(month) }
    suspend fun createCommanderTx(request: CommanderTxRequest) = call { createCommanderTx(request) }
    suspend fun updateCommanderTx(id: Int, request: CommanderTxRequest) = call { updateCommanderTx(id, request) }
    suspend fun deleteCommanderTx(id: Int) = call { deleteCommanderTx(id) }

    // ----------------------------------------------- Rekening Ibu Beti

    suspend fun ibuBeti(month: String?, jenis: String?, search: String?) =
        call { ibuBeti(month, jenis, search?.trim()?.takeIf { it.isNotEmpty() }) }

    /** Tambah ([id] null) atau ubah mutasi; [bukti] opsional (JPG/PNG/PDF ≤ 5 MB). */
    suspend fun saveIbuBeti(id: Int?, tanggal: String, jenis: String, jumlah: Double, keterangan: String, bukti: UploadImage?): ApiResult<SimpleResponse> {
        val text = "text/plain".toMediaType()
        val fields = linkedMapOf(
            "tanggal" to tanggal.toRequestBody(text),
            "jenis" to jenis.toRequestBody(text),
            "jumlah" to java.math.BigDecimal.valueOf(jumlah).toPlainString().toRequestBody(text),
            "keterangan" to keterangan.toRequestBody(text),
        )
        val files = listOfNotNull(bukti?.let { MultipartBody.Part.createFormData("bukti", it.fileName, it.bytes.toRequestBody(it.mimeType.toMediaType())) })
        return call { if (id == null) createIbuBeti(fields, files) else updateIbuBeti(id, fields, files) }
    }

    suspend fun deleteIbuBeti(id: Int) = call { deleteIbuBeti(id) }

    /** Foto bukti transaksi (berkas privat, lewat token sesi). */
    suspend fun ibuBetiBuktiImage(id: Int): ApiResult<ByteArray> = withContext(Dispatchers.IO) {
        try {
            val response = svc().downloadFile("api/mobile/ibu-beti/$id/bukti")
            if (!response.isSuccessful) {
                val raw = try { response.errorBody()?.string() } catch (_: Exception) { null }
                return@withContext ApiResult.Failure(ApiErrors.fromHttp(response.code(), raw))
            }
            val body = response.body() ?: return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Server tidak mengirim berkas."))
            if (!body.contentType()?.type.equals("image", ignoreCase = true)) {
                body.close()
                return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Bukti bukan berkas gambar."))
            }
            ApiResult.Success(body.bytes())
        } catch (c: CancellationException) {
            throw c
        } catch (t: Throwable) {
            ApiResult.Failure(ApiErrors.fromThrowable(t))
        }
    }

    // ----------------------------------------------------------- Mitra

    suspend fun mitra(year: Int) = call { mitra(year) }
    suspend fun saveMitra(id: Int?, request: MitraRequest) = call {
        if (id == null) createMitra(request) else updateMitra(id, request)
    }
    suspend fun deleteMitra(id: Int) = call { deleteMitra(id) }
    suspend fun toggleMitraPayment(id: Int, year: Int, month: Int) = call { toggleMitraPayment(id, TogglePaymentRequest(year, month)) }

    // ----------------------------------------------------- Pelanggaran

    suspend fun violations(search: String?, status: String?) =
        call { violations(search?.takeIf { it.isNotBlank() }, status) }
    suspend fun createViolation(request: CreateViolationRequest) = call { createViolation(request) }
    suspend fun addViolationProgress(id: Int, note: String, status: String?) =
        call { addViolationProgress(id, ViolationProgressRequest(note, status)) }
    suspend fun deleteViolation(id: Int) = call { deleteViolation(id) }

    // -------------------------------------------------- Pengguna (Admin)

    suspend fun users() = call { users() }
    suspend fun createUser(request: CreateUserRequest) = call { createUser(request) }
    suspend fun setUserActive(id: Int, active: Boolean) = call { updateUserStatus(id, UpdateUserStatusRequest(active)) }
    suspend fun deleteUser(id: Int) = call { deleteUser(id) }

    // ------------------------------------------------------ Chat Dinas

    suspend fun chatHome() = call { chatHome() }
    suspend fun chatSync(uuid: String?, status: String? = null, search: String? = null) =
        call { chatSync(uuid, status, search?.takeIf { it.isNotBlank() }) }
    suspend fun chatUnread() = call { chatUnread() }
    suspend fun chatMessages(uuid: String) = call { chatMessages(uuid) }
    suspend fun chatTyping(uuid: String) = call { chatTyping(uuid) }
    suspend fun chatSearchUsers(query: String) = call { chatSearchUsers(query.trim()) }
    suspend fun chatStart(userId: Int) = call { chatStart(ChatStartRequest(userId)) }
    suspend fun chatToggleStatus(uuid: String) = call { chatToggleStatus(uuid) }
    suspend fun chatDelete(uuid: String) = call { chatDelete(uuid) }

    /** Kirim pesan teks dan/atau lampiran (maks. 20 MB total, divalidasi server). */
    suspend fun chatSend(uuid: String, text: String, files: List<UploadImage>): ApiResult<ChatSendResponse> {
        val body = text.trim().takeIf { it.isNotEmpty() }?.toRequestBody("text/plain".toMediaType())
        val parts = files.map {
            MultipartBody.Part.createFormData("attachments[]", it.fileName, it.bytes.toRequestBody(it.mimeType.toMediaType()))
        }
        return call { chatSend(uuid, body, parts) }
    }

    // ------------------------------------------------- Panggilan (Vicon)

    suspend fun incomingCall() = call { incomingCall() }
    suspend fun initiateCall(uuid: String, video: Boolean) =
        call { initiateCall(uuid, InitiateCallRequest(if (video) "video" else "audio")) }
    suspend fun callSignal(uuid: String) = call { callSignal(uuid) }
    suspend fun sendCallSignal(uuid: String, action: String, status: String) =
        call { sendCallSignal(uuid, CallSignalRequest(action, status)) }
    suspend fun endCall(uuid: String, reason: String, durationSeconds: Int = 0) =
        call { endCall(uuid, EndCallRequest(reason, durationSeconds)) }
    suspend fun agoraToken(uuid: String, uid: Int) = call { agoraToken(uuid, uid) }

    // ------------------------------------------------- TTE SP Jaga

    suspend fun pendingSpJaga() = call { pendingSpJaga() }
    suspend fun approveSpJaga(id: Int) = call { approveSpJaga(id) }

    // ---------------------------------------------------------- Backup

    suspend fun backupOverview() = call { backupOverview() }
    suspend fun backupFiles(pcId: Int, folderId: Int?, search: String?) =
        call { backupFiles(pcId, folderId, search?.trim()?.takeIf { it.isNotEmpty() }) }
    suspend fun deviceHeartbeat(body: DeviceHeartbeatRequest) = call { deviceHeartbeat(body) }
    suspend fun backupCopy(pcId: Int, ids: List<Int>, targetFolderId: Int?) = call { backupCopy(pcId, BackupBulkRequest(ids, targetFolderId)) }
    suspend fun backupMove(pcId: Int, ids: List<Int>, targetFolderId: Int?) = call { backupMove(pcId, BackupBulkRequest(ids, targetFolderId)) }
    suspend fun backupDelete(pcId: Int, ids: List<Int>) = call { backupDelete(pcId, BackupBulkRequest(ids, null)) }
    suspend fun backupRename(id: Int, name: String) = call { backupRename(id, BackupRenameRequest(name.trim())) }
    suspend fun backupRequestAccess(pcId: Int, reason: String?) =
        call { backupRequestAccess(pcId, BackupAccessReasonRequest(reason?.trim()?.takeIf { it.isNotEmpty() })) }
    suspend fun backupVerifyAccess(requestId: Int, code: String) =
        call { backupVerifyAccess(requestId, BackupAccessCodeRequest(code.filter { it.isDigit() })) }
    suspend fun backupResendCode(requestId: Int) = call { backupResendCode(requestId) }
    suspend fun backupDecide(requestId: Int, approve: Boolean, note: String?) = call {
        val body = BackupAccessNoteRequest(note?.trim()?.takeIf { it.isNotEmpty() })
        if (approve) backupApproveAccess(requestId, body) else backupRejectAccess(requestId, body)
    }
    suspend fun backupWithdrawAccess(requestId: Int) = call { backupWithdrawAccess(requestId) }

    /**
     * Gambar berkas backup (thumbnail atau pratinjau penuh) lewat token sesi.
     * Success(null) = server masih membuat thumbnail (HTTP 202), coba lagi sebentar.
     */
    suspend fun backupImage(fileId: Int, thumbnail: Boolean): ApiResult<ByteArray?> = withContext(Dispatchers.IO) {
        val path = "api/mobile/backup/files/$fileId/" + if (thumbnail) "thumbnail" else "content"
        try {
            val response = svc().downloadFile(path)
            if (!response.isSuccessful) {
                val raw = try { response.errorBody()?.string() } catch (_: Exception) { null }
                return@withContext ApiResult.Failure(ApiErrors.fromHttp(response.code(), raw))
            }
            val body = response.body() ?: return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Server tidak mengirim berkas."))
            if (response.code() == 202) {
                body.close()
                return@withContext ApiResult.Success(null)
            }
            if (!body.contentType()?.type.equals("image", ignoreCase = true)) {
                body.close()
                return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Berkas bukan gambar."))
            }
            ApiResult.Success(body.bytes())
        } catch (c: CancellationException) {
            throw c
        } catch (t: Throwable) {
            ApiResult.Failure(ApiErrors.fromThrowable(t))
        }
    }

    /**
     * Unduh berkas backup ke cache privat (folder "backup/") agar bisa dibuka
     * dengan aplikasi lain atau disimpan ke perangkat.
     */
    /** URL streaming isi berkas backup + header token (untuk pemutar video/audio). */
    suspend fun backupStreamSource(fileId: Int): Pair<String, Map<String, String>> {
        val base = store.baseUrl().trimEnd('/')
        val headers = buildMap {
            session.token?.let { put("Authorization", "Bearer $it") }
            put("User-Agent", "SISINDEN-Android/${id.my.sisinden.app.BuildConfig.VERSION_NAME}")
        }
        return "$base/api/mobile/backup/files/$fileId/content" to headers
    }

    /** Isi berkas teks (txt/csv/log/json…) untuk dibaca langsung, dibatasi [maxBytes]. */
    suspend fun backupText(fileId: Int, maxBytes: Int = 512 * 1024): ApiResult<Pair<String, Boolean>> = withContext(Dispatchers.IO) {
        try {
            val response = svc().downloadFile("api/mobile/backup/files/$fileId/content")
            if (!response.isSuccessful) {
                val raw = try { response.errorBody()?.string() } catch (_: Exception) { null }
                return@withContext ApiResult.Failure(ApiErrors.fromHttp(response.code(), raw))
            }
            val body = response.body() ?: return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Server tidak mengirim berkas."))
            body.use { b ->
                val buf = ByteArray(maxBytes + 1)
                var read = 0
                b.byteStream().use { input ->
                    while (read < buf.size) {
                        val n = input.read(buf, read, buf.size - read)
                        if (n < 0) break
                        read += n
                    }
                }
                val truncated = read > maxBytes
                ApiResult.Success(String(buf, 0, minOf(read, maxBytes), Charsets.UTF_8) to truncated)
            }
        } catch (c: CancellationException) {
            throw c
        } catch (t: Throwable) {
            ApiResult.Failure(ApiErrors.fromThrowable(t))
        }
    }

    /** PDF berkas backup: PDF asli, atau dokumen Office yang dikonversi server ([office] = true). */
    suspend fun backupPdf(fileId: Int, fileName: String, office: Boolean): ApiResult<File> {
        val res = downloadBackupFile(fileId, if (office) "$fileName.pdf" else fileName, if (office) "office-pdf" else "content")
        if (res is ApiResult.Success) {
            val head = ByteArray(4)
            val n = withContext(Dispatchers.IO) { res.data.inputStream().use { it.read(head) } }
            if (n < 4 || String(head, Charsets.US_ASCII) != "%PDF") {
                return ApiResult.Failure(ApiError(ErrorKind.SERVER, "Pratinjau dokumen belum tersedia. Unduh berkasnya untuk membuka."))
            }
        }
        return res
    }

    suspend fun downloadBackupFile(fileId: Int, fileName: String, endpoint: String = "download"): ApiResult<File> = withContext(Dispatchers.IO) {
        val dir = File(appContext.cacheDir, "backup").apply { mkdirs() }
        // Bersihkan unduhan lama (> 1 hari) agar cache tidak membengkak.
        dir.walkBottomUp().filter { it.isFile && System.currentTimeMillis() - it.lastModified() > 86_400_000L }.forEach { it.delete() }
        val safe = fileName.replace(Regex("[\\\\/:*?\"<>|\\u0000-\\u001F]+"), "_").trim().take(120).ifEmpty { "berkas" }
        val target = File(File(dir, endpoint).apply { mkdirs() }, "${fileId}_$safe")
        val temp = File(dir, target.name + ".part")
        try {
            val response = svc().downloadFile("api/mobile/backup/files/$fileId/$endpoint")
            if (!response.isSuccessful) {
                val raw = try { response.errorBody()?.string() } catch (_: Exception) { null }
                return@withContext ApiResult.Failure(ApiErrors.fromHttp(response.code(), raw))
            }
            val body = response.body() ?: return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Server tidak mengirim berkas."))
            body.byteStream().use { input -> temp.outputStream().use { output -> input.copyTo(output) } }
            if (temp.length() == 0L) {
                temp.delete()
                return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Berkas kosong atau gagal diunduh."))
            }
            if (target.exists()) target.delete()
            if (!temp.renameTo(target)) {
                temp.copyTo(target, overwrite = true)
                temp.delete()
            }
            ApiResult.Success(target)
        } catch (c: CancellationException) {
            temp.delete()
            throw c
        } catch (t: Throwable) {
            temp.delete()
            ApiResult.Failure(ApiErrors.fromThrowable(t))
        }
    }

    // ------------------------------------------------------------- PDF

    /**
     * Unduh PDF resmi dari server ke folder cache privat dan validasi isinya
     * (status HTTP, Content-Type, tanda tangan berkas "%PDF", ukuran > 0).
     */
    suspend fun downloadPdf(apiPath: String, fileName: String): ApiResult<File> = withContext(Dispatchers.IO) {
        if (!apiPath.startsWith("api/mobile/pdf/") || apiPath.contains("..")) {
            return@withContext ApiResult.Failure(ApiError(ErrorKind.FORBIDDEN, "Alamat dokumen tidak diizinkan."))
        }
        val dir = File(appContext.cacheDir, "pdf").apply { mkdirs() }
        val target = File(dir, sanitizeFileName(fileName))
        val temp = File(dir, target.name + ".part")
        try {
            val response = svc().downloadFile(apiPath)
            if (!response.isSuccessful) {
                val raw = try { response.errorBody()?.string() } catch (_: Exception) { null }
                return@withContext ApiResult.Failure(ApiErrors.fromHttp(response.code(), raw))
            }
            val body = response.body()
                ?: return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Server tidak mengirim berkas."))
            val type = body.contentType()?.toString().orEmpty()
            if (!type.contains("pdf", ignoreCase = true)) {
                body.close()
                return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Server tidak mengembalikan dokumen PDF."))
            }
            body.byteStream().use { input -> temp.outputStream().use { output -> input.copyTo(output) } }

            val header = ByteArray(4)
            val read = temp.inputStream().use { it.read(header) }
            if (temp.length() == 0L || read < 4 || String(header, Charsets.US_ASCII) != "%PDF") {
                temp.delete()
                return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Berkas PDF kosong atau rusak."))
            }
            if (target.exists()) target.delete()
            if (!temp.renameTo(target)) {
                temp.copyTo(target, overwrite = true)
                temp.delete()
            }
            ApiResult.Success(target)
        } catch (c: CancellationException) {
            temp.delete()
            throw c
        } catch (t: Throwable) {
            temp.delete()
            ApiResult.Failure(ApiErrors.fromThrowable(t))
        }
    }

    private fun sanitizeFileName(name: String): String {
        val cleaned = name.replace(Regex("[^A-Za-z0-9._-]+"), "_").trim('_').take(80)
        val base = cleaned.ifEmpty { "dokumen" }
        return if (base.endsWith(".pdf", ignoreCase = true)) base else "$base.pdf"
    }

    // ------------------------------------------------------- Preferensi

    val themeModeFlow = store.themeModeFlow
    val baseUrlFlow = store.baseUrlFlow
    val hideBalanceFlow = store.hideBalanceFlow
    suspend fun setThemeMode(mode: id.my.sisinden.app.core.ui.theme.ThemeMode) = store.setThemeMode(mode)
    suspend fun setHideBalance(hide: Boolean) = store.setHideBalance(hide)
    val textScaleFlow = store.textScaleFlow
    suspend fun setTextScale(scale: id.my.sisinden.app.core.ui.theme.TextScale) = store.setTextScale(scale)
}

enum class CashKind(val title: String, val subtitle: String) {
    BASE("Buku Kas Pangkalan", "Penerimaan & pengeluaran kas Denintel"),
    TECHNICAL("Kas Unit Teknis", "Dana operasional Dan Unit Teknis"),
}
