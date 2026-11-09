package id.my.sisinden.app.data.model

import com.google.gson.annotations.SerializedName

// ================================================================ Backup
// Kontrak /api/mobile/backup/* (App\Http\Controllers\Mobile\MobileBackupController).

data class BackupPersonDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String? = null,
    @SerializedName("pangkat") val pangkat: String? = null,
    @SerializedName("nrp") val nrp: String? = null,
) {
    val label: String get() = listOfNotNull(pangkat?.takeIf { it.isNotBlank() }, name).joinToString(" ")
}

data class BackupPcDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String? = null,
    @SerializedName("owner") val owner: BackupPersonDto? = null,
    @SerializedName("usage") val usage: Long = 0,
    @SerializedName("quota") val quota: Long = 0,
    @SerializedName("usage_human") val usageHuman: String? = null,
    @SerializedName("quota_human") val quotaHuman: String? = null,
    @SerializedName("usage_percentage") val usagePercentage: Double = 0.0,
    /** owner | admin | granted | pending | verifying | rejected | none */
    @SerializedName("access_state") val accessState: String? = null,
    @SerializedName("access_request_id") val accessRequestId: Int? = null,
) {
    val canOpen: Boolean get() = accessState in setOf(BackupAccessState.OWNER, BackupAccessState.ADMIN, BackupAccessState.GRANTED)
}

object BackupAccessState {
    const val OWNER = "owner"
    const val ADMIN = "admin"
    const val GRANTED = "granted"
    const val PENDING = "pending"
    const val VERIFYING = "verifying"
    const val REJECTED = "rejected"
    const val NONE = "none"
}

data class BackupAccessRequestDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("status") val status: String? = null,
    @SerializedName("pc_id") val pcId: Int = 0,
    @SerializedName("pc_name") val pcName: String? = null,
    @SerializedName("owner_name") val ownerName: String? = null,
    @SerializedName("requester") val requester: BackupPersonDto? = null,
    @SerializedName("reason") val reason: String? = null,
    @SerializedName("decision_note") val decisionNote: String? = null,
    @SerializedName("phone_hint") val phoneHint: String? = null,
    @SerializedName("otp_expires_in") val otpExpiresIn: Int = 0,
    @SerializedName("resend_in") val resendIn: Int = 0,
    @SerializedName("created_human") val createdHuman: String? = null,
    @SerializedName("decided_human") val decidedHuman: String? = null,
)

data class BackupOverviewResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("is_admin") val isAdmin: Boolean = false,
    @SerializedName("my_pcs") val myPcs: List<BackupPcDto>? = null,
    @SerializedName("other_pcs") val otherPcs: List<BackupPcDto>? = null,
    @SerializedName("incoming_requests") val incomingRequests: List<BackupAccessRequestDto>? = null,
    @SerializedName("active_grants") val activeGrants: List<BackupAccessRequestDto>? = null,
)

data class BackupItemDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String? = null,
    @SerializedName("is_folder") val isFolder: Boolean = false,
    /** folder | image | raw | pdf | video | audio | document | archive | file */
    @SerializedName("kind") val kind: String? = null,
    @SerializedName("extension") val extension: String? = null,
    @SerializedName("size") val size: Long = 0,
    @SerializedName("size_human") val sizeHuman: String? = null,
    @SerializedName("child_count") val childCount: Int? = null,
    @SerializedName("has_thumbnail") val hasThumbnail: Boolean = false,
    @SerializedName("date_human") val dateHuman: String? = null,
) {
    val isImage: Boolean get() = kind == "image" || kind == "raw"
}

data class BackupCrumbDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String? = null,
)

data class BackupFilesResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("pc") val pc: BackupPcDto? = null,
    @SerializedName("can_manage") val canManage: Boolean = false,
    @SerializedName("folder_id") val folderId: Int? = null,
    @SerializedName("search") val search: String? = null,
    @SerializedName("breadcrumbs") val breadcrumbs: List<BackupCrumbDto>? = null,
    @SerializedName("items") val items: List<BackupItemDto>? = null,
)

data class BackupAccessResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("request") val request: BackupAccessRequestDto? = null,
)

data class BackupAccessReasonRequest(@SerializedName("reason") val reason: String?)
data class BackupAccessCodeRequest(@SerializedName("code") val code: String)
data class BackupAccessNoteRequest(@SerializedName("note") val note: String?)
