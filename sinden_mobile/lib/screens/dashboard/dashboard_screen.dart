import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/location_provider.dart';
import '../../providers/notification_provider.dart';
import '../notifications/notifications_screen.dart';
import '../tracking/personnel_map_screen.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final loc = Provider.of<LocationProvider>(context);
    final notif = Provider.of<NotificationProvider>(context);

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'SI SINDEN',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: AppTheme.primaryNavy),
            ),
            const Text(
              'Detasemen Intelijen Kodaeral V',
              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: AppTheme.secondaryGold),
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
            Card(
              color: Colors.white,
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 28,
                      backgroundColor: AppTheme.primaryNavy.withOpacity(0.1),
                      child: const Icon(Icons.person_outline, size: 30, color: AppTheme.primaryNavy),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFDBEAFE),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              auth.user?.role?.toUpperCase() ?? 'PERSONEL',
                              style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: AppTheme.primaryNavy),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            auth.user?.name ?? 'Personel SINDEN',
                            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                          ),
                          Text(
                            auth.user?.nrp != null ? 'NRP: ${auth.user!.nrp}' : 'Detasemen Intelijen',
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

            Card(
              color: loc.isTrackingEnabled ? const Color(0xFFF0FDF4) : const Color(0xFFFEF2F2),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: BorderSide(
                  color: loc.isTrackingEnabled ? const Color(0xFFBBF7D0) : const Color(0xFFFECACA),
                ),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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
                            loc.isTrackingEnabled ? 'PELACAKAN GPS AKTIF' : 'GPS NON-AKTIF',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: loc.isTrackingEnabled ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                            ),
                          ),
                          Text(
                            loc.isTrackingEnabled
                                ? 'Lokasi dinas terkirim berkala ke Komando'
                                : 'Aktifkan GPS untuk pelaporan posisi',
                            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
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

            const Text(
              'RINGKASAN METRIK OPERASIONAL',
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF64748B), letterSpacing: 0.8),
            ),
            const SizedBox(height: 8),

            Row(
              children: const [
                Expanded(
                  child: _StatCard(
                    title: 'AGENDA SURAT',
                    count: 'Aktif',
                    subtitle: 'Nomor Surat Terbit',
                    icon: Icons.mark_email_read_outlined,
                    iconColor: AppTheme.primaryNavy,
                  ),
                ),
                SizedBox(width: 12),
                Expanded(
                  child: _StatCard(
                    title: 'CLEARANCE',
                    count: 'Terdaftar',
                    subtitle: 'Penerbitan SKHPP',
                    icon: Icons.verified_user_outlined,
                    iconColor: AppTheme.secondaryGold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: const [
                Expanded(
                  child: _StatCard(
                    title: 'BUKU KAS',
                    count: 'Tervalidasi',
                    subtitle: 'Kas Unit Teknis',
                    icon: Icons.account_balance_wallet_outlined,
                    iconColor: Color(0xFF16A34A),
                  ),
                ),
                SizedBox(width: 12),
                Expanded(
                  child: _StatCard(
                    title: 'SINKRONISASI',
                    count: 'Online',
                    subtitle: 'Koneksi Server Pusat',
                    icon: Icons.cloud_done_outlined,
                    iconColor: Color(0xFF6366F1),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String title;
  final String count;
  final String subtitle;
  final IconData icon;
  final Color iconColor;

  const _StatCard({
    required this.title,
    required this.count,
    required this.subtitle,
    required this.icon,
    required this.iconColor,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8)),
                ),
                Icon(icon, size: 20, color: iconColor),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              count,
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
            ),
            Text(
              subtitle,
              style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)),
            ),
          ],
        ),
      ),
    );
  }
}