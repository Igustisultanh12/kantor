import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/constants.dart';
import '../../core/theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/location_provider.dart';
import '../auth/login_screen.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final loc = Provider.of<LocationProvider>(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pengaturan & Profil'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Profile Summary Card
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 30,
                    backgroundColor: AppTheme.primaryNavy.withOpacity(0.1),
                    child: const Icon(Icons.person, size: 36, color: AppTheme.primaryNavy),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          auth.user?.name ?? 'Personel SINDEN',
                          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
                        ),
                        Text(
                          auth.user?.nrp != null ? 'NRP: ${auth.user!.nrp}' : 'Detasemen Intelijen',
                          style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Peran: ${auth.user?.role?.toUpperCase() ?? "STAF"}',
                          style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: AppTheme.secondaryGold),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // System Configurations
          const Text('PENGATURAN SISTEM & PERANGKAT', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF64748B))),
          const SizedBox(height: 8),

          Card(
            child: Column(
              children: [
                SwitchListTile(
                  secondary: const Icon(Icons.location_on_outlined),
                  title: const Text('Tracking Lokasi GPS Realtime', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                  subtitle: const Text('Pelaporan koordinat dinas ke Komando', style: TextStyle(fontSize: 11)),
                  value: loc.isTrackingEnabled,
                  onChanged: (_) => loc.toggleTracking(),
                ),
                const Divider(),
                ListTile(
                  leading: const Icon(Icons.dns_outlined),
                  title: const Text('Alamat Server Backend', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                  subtitle: const Text(AppConstants.defaultBaseUrl, style: TextStyle(fontSize: 11)),
                ),
                const Divider(),
                ListTile(
                  leading: const Icon(Icons.notifications_active_outlined),
                  title: const Text('Kanal Notifikasi Suara', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                  subtitle: const Text('Peringatan Prioritas Tinggi Aktif', style: TextStyle(fontSize: 11)),
                  trailing: const Icon(Icons.check, color: Color(0xFF16A34A)),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),

          // Logout Button
          FilledButton.tonal(
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFFEE2E2),
              foregroundColor: const Color(0xFFDC2626),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
            onPressed: () async {
              await auth.logout();
              if (context.mounted) {
                Navigator.of(context).pushAndRemoveUntil(
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                  (route) => false,
                );
              }
            },
            child: const Text('KELUAR DARI APLIKASI', style: TextStyle(fontWeight: FontWeight.w800)),
          ),
        ],
      ),
    );
  }
}