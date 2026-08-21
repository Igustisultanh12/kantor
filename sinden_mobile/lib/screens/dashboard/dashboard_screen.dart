import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/location_provider.dart';
import '../../providers/notification_provider.dart';
import '../notifications/notifications_screen.dart';
import '../tracking/personnel_map_screen.dart';
import '../letter_logs/letter_logs_screen.dart';
import '../skhpp/skhpp_list_screen.dart';
import '../cash/cash_screen.dart';
import '../settings/settings_screen.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  void _showAccessDeniedDialog(BuildContext context, String menuName) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.lock_outline, color: Color(0xFFDC2626), size: 26),
            SizedBox(width: 10),
            Text('Akses Dibatasi', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          ],
        ),
        content: Text(
          'Fitur "$menuName" memerlukan izin khusus dari Admin / Komandan SINDEN. Silakan hubungi Admin untuk membuka matriks otoritas akun Anda.',
          style: const TextStyle(fontSize: 12, color: Color(0xFF334155), height: 1.4),
        ),
        actions: [
          ElevatedButton(
            onPressed: () => Navigator.of(ctx).pop(),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0F172A),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text('MENGERTI', style: TextStyle(fontWeight: FontWeight.w800, color: Colors.white)),
          ),
        ],
      ),
    );
  }

  void _showModuleDialog(BuildContext context, String title, String description, IconData icon, Color color) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: Row(
          children: [
            Icon(icon, color: color, size: 28),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF0F172A)),
              ),
            ),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              description,
              style: const TextStyle(fontSize: 12, color: Color(0xFF475569), height: 1.4),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Row(
                children: [
                  Icon(Icons.check_circle_outline, color: Color(0xFF16A34A), size: 18),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Sinkronisasi Otomatis 100% dengan Server SINDEN Web Pusat.',
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          ElevatedButton(
            onPressed: () => Navigator.of(ctx).pop(),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0F172A),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text('TUTUP', style: TextStyle(fontWeight: FontWeight.w800, color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final loc = Provider.of<LocationProvider>(context);
    final notif = Provider.of<NotificationProvider>(context);
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'SI SINDEN',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: AppTheme.primaryNavy),
            ),
            Text(
              user?.jabatan ?? 'Detasemen Intelijen Kodaeral V',
              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: AppTheme.secondaryGold),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: Badge(
              isLabelVisible: notif.unreadCount > 0,
              label: Text('${notif.unreadCount}'),
              child: const Icon(Icons.notifications_outlined),
            ),
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const NotificationsScreen()),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.map_outlined),
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const PersonnelMapScreen()),
              );
            },
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await notif.fetchNotifications();
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // User Header Profile Card
            Card(
              color: Colors.white,
              elevation: 2,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 28,
                      backgroundColor: AppTheme.primaryNavy.withOpacity(0.1),
                      child: const Icon(Icons.person, size: 32, color: AppTheme.primaryNavy),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: user?.isAdmin == true
                                      ? const Color(0xFFDC2626)
                                      : (user?.isKomandan == true ? const Color(0xFF7C3AED) : const Color(0xFF2563EB)),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  user?.role?.toUpperCase() ?? 'PERSONEL',
                                  style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: Colors.white),
                                ),
                              ),
                              const SizedBox(width: 6),
                              if (user?.pangkat != null)
                                Text(
                                  user!.pangkat!,
                                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF64748B)),
                                ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            user?.name ?? 'Personel SINDEN',
                            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          Text(
                            user?.nrp != null ? 'NRP: ${user!.nrp}' : 'Denintel Kodaeral V',
                            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),

            // GPS Tracking Card
            Card(
              color: loc.isTrackingEnabled ? const Color(0xFFF0FDF4) : const Color(0xFFFEF2F2),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: BorderSide(
                  color: loc.isTrackingEnabled ? const Color(0xFFBBF7D0) : const Color(0xFFFECACA),
                ),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                child: Row(
                  children: [
                    Icon(
                      loc.isTrackingEnabled ? Icons.gps_fixed : Icons.gps_off,
                      color: loc.isTrackingEnabled ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                      size: 22,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            loc.isTrackingEnabled ? 'PELACAKAN GPS DUDUKAN DINAS AKTIF' : 'GPS POSITIONING OFF',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w900,
                              color: loc.isTrackingEnabled ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                            ),
                          ),
                          Text(
                            loc.isTrackingEnabled ? 'Posisi dinas terdeteksi & terkirim ke Komando' : 'Aktifkan GPS untuk pelaporan posisi',
                            style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                          ),
                        ],
                      ),
                    ),
                    Switch(
                      value: loc.isTrackingEnabled,
                      activeColor: const Color(0xFF16A34A),
                      onChanged: (_) => loc.toggleTracking(),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // CATEGORY 1: SURAT & NASKAH
            _buildCategoryHeader('SURAT & NASKAH DINAS'),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 10,
              children: [
                _buildMenuItem(
                  context: context,
                  title: 'Agenda Surat',
                  icon: Icons.mark_email_read_outlined,
                  color: const Color(0xFFD97706),
                  hasAccess: user?.canAccessAgenda ?? true,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LetterLogsScreen())),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Buat & Draf',
                  icon: Icons.edit_note_outlined,
                  color: const Color(0xFFB45309),
                  hasAccess: user?.canAccessLetters ?? true,
                  onTap: () => _showModuleDialog(context, 'Buat & Draf Surat', 'Fitur pembuatan naskah dinas, konseptor surat, dan pengajuan persetujuan otomatis terintegrasi.', Icons.edit_note_outlined, const Color(0xFFB45309)),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Cetak SKHPP',
                  icon: Icons.verified_user_outlined,
                  color: const Color(0xFF16A34A),
                  hasAccess: user?.canAccessSkhpp ?? true,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SkhppListScreen())),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Kategori Surat',
                  icon: Icons.category_outlined,
                  color: const Color(0xFF0284C7),
                  hasAccess: user?.canAccessCategories ?? true,
                  onTap: () => _showModuleDialog(context, 'Kategori Surat', 'Manajemen klasifikasi naskah dinas, nomor urut otomatis, dan kode arsip instansi.', Icons.category_outlined, const Color(0xFF0284C7)),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // CATEGORY 2: VALIDASI & TTE
            _buildCategoryHeader('VALIDASI & TANDA TANGAN DIGITAL'),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 10,
              children: [
                _buildMenuItem(
                  context: context,
                  title: 'TTE Digital',
                  icon: Icons.draw_outlined,
                  color: const Color(0xFF7C3AED),
                  hasAccess: user?.canAccessSignature ?? true,
                  onTap: () => _showModuleDialog(context, 'Tanda Tangan Digital (TTE)', 'Pengesahan berkas dinas, verifikasi barcode QR spesimen Komandan, dan pengarsipan otomatis.', Icons.draw_outlined, const Color(0xFF7C3AED)),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // CATEGORY 3: LOGISTIK & KEUANGAN
            _buildCategoryHeader('LOGISTIK & KEUANGAN UNIT'),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 10,
              children: [
                _buildMenuItem(
                  context: context,
                  title: 'Buku Kas Unit',
                  icon: Icons.account_balance_wallet_outlined,
                  color: const Color(0xFF059669),
                  hasAccess: user?.canAccessCash ?? false,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const CashScreen())),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Rekening Dan',
                  icon: Icons.credit_card_outlined,
                  color: const Color(0xFF047857),
                  hasAccess: user?.canAccessCommander ?? false,
                  onTap: () => _showModuleDialog(context, 'Rekening Komandan', 'Pencatatan saldo, penerimaan dana dinas, serta laporan transaksi Rekening Komandan.', Icons.credit_card_outlined, const Color(0xFF047857)),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Catatan Mitra',
                  icon: Icons.handshake_outlined,
                  color: const Color(0xFF2563EB),
                  hasAccess: user?.canAccessMitra ?? false,
                  onTap: () => _showModuleDialog(context, 'Pencatatan Mitra', 'Data daftar mitra kerja, transaksi dukungan operasional, dan riwayat kerjasama dinas.', Icons.handshake_outlined, const Color(0xFF2563EB)),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Kas Dan Unit',
                  icon: Icons.settings_suggest_outlined,
                  color: const Color(0xFF0891B2),
                  hasAccess: user?.canAccessTechnicalCash ?? false,
                  onTap: () => _showModuleDialog(context, 'Buku Kas Dan Unit Teknis', 'Pembukuan keuangan khusus Dan Unit Teknis dan pertanggungjawaban dana lapangan.', Icons.settings_suggest_outlined, const Color(0xFF0891B2)),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Backup Data',
                  icon: Icons.backup_outlined,
                  color: const Color(0xFF10B981),
                  hasAccess: user?.isAdmin ?? false,
                  onTap: () => _showModuleDialog(context, 'Explorer Backup', 'Manajemen cadangan database, ekspor arsip berkas dinas, dan pemulihan sistem.', Icons.backup_outlined, const Color(0xFF10B981)),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // CATEGORY 4: PENGAMANAN & OPR
            _buildCategoryHeader('PENGAMANAN & RADAR LAPANGAN'),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 10,
              children: [
                _buildMenuItem(
                  context: context,
                  title: 'Pelanggaran',
                  icon: Icons.gavel_outlined,
                  color: const Color(0xFFE11D48),
                  hasAccess: user?.canAccessViolations ?? true,
                  onTap: () => _showModuleDialog(context, 'Catatan Pelanggaran Prajurit', 'Rekam jejak disiplin prajurit, catatan sanksi, dan riwayat tindakan penegakan hukum.', Icons.gavel_outlined, const Color(0xFFE11D48)),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Radar Kegiatan',
                  icon: Icons.radar_outlined,
                  color: const Color(0xFF3B82F6),
                  hasAccess: user?.canAccessActivities ?? true,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PersonnelMapScreen())),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // CATEGORY 5: SISTEM & OTORITAS ADMIN
            _buildCategoryHeader('SISTEM & OTORITAS ADMIN'),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 10,
              children: [
                _buildMenuItem(
                  context: context,
                  title: 'Kelola Pengguna',
                  icon: Icons.people_alt_outlined,
                  color: const Color(0xFF475569),
                  hasAccess: user?.isAdmin ?? false,
                  onTap: () => _showModuleDialog(context, 'Kelola Pengguna & Otoritas', 'Manajemen akun personel, pendaftaran massal, matriks otorisasi fitur, dan cetak PDF verifikasi.', Icons.people_alt_outlined, const Color(0xFF475569)),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Log & Audit',
                  icon: Icons.manage_history_outlined,
                  color: const Color(0xFF64748B),
                  hasAccess: user?.isAdmin == true || user?.isKomandan == true,
                  onTap: () => _showModuleDialog(context, 'Log Pengunjung & Audit', 'Pencatatan jejak audit sistem, aktivitas pengguna, dan log aktivitas real-time.', Icons.manage_history_outlined, const Color(0xFF64748B)),
                ),
                _buildMenuItem(
                  context: context,
                  title: 'Pengaturan',
                  icon: Icons.settings_outlined,
                  color: const Color(0xFF334155),
                  hasAccess: user?.isAdmin ?? false,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SettingsScreen())),
                ),
              ],
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _buildCategoryHeader(String title) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Text(
        title,
        style: const TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w900,
          color: Color(0xFF64748B),
          letterSpacing: 0.8,
        ),
      ),
    );
  }

    Widget _buildMenuItem({
    required BuildContext context,
    required String title,
    required IconData icon,
    required Color color,
    required bool hasAccess,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: hasAccess ? onTap : () => _showAccessDeniedDialog(context, title),
        borderRadius: BorderRadius.circular(20),
        splashColor: color.withOpacity(0.15),
        highlightColor: color.withOpacity(0.08),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOutCubic,
          decoration: BoxDecoration(
            color: hasAccess ? Colors.white : const Color(0xFFF8FAFC),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: hasAccess ? const Color(0xFFE2E8F0) : const Color(0xFFF1F5F9),
              width: 1,
            ),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Stack(
                alignment: Alignment.center,
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 300),
                    curve: Curves.easeOutCubic,
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: hasAccess ? color.withOpacity(0.12) : const Color(0xFFE2E8F0),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      icon,
                      size: 22,
                      color: hasAccess ? color : const Color(0xFF94A3B8),
                    ),
                  ),
                  if (!hasAccess)
                    Positioned(
                      right: 0,
                      bottom: 0,
                      child: Container(
                        padding: const EdgeInsets.all(3),
                        decoration: const BoxDecoration(
                          color: Color(0xFFEF4444),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.lock, size: 10, color: Colors.white),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                title,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: hasAccess ? FontWeight.w800 : FontWeight.w600,
                  color: hasAccess ? const Color(0xFF1E293B) : const Color(0xFF94A3B8),
                  height: 1.15,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}