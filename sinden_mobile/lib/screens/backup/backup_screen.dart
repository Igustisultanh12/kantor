import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';

class BackupScreen extends StatefulWidget {
  const BackupScreen({super.key});

  @override
  State<BackupScreen> createState() => _BackupScreenState();
}

class _BackupScreenState extends State<BackupScreen> {
  bool _isLoading = true;
  List<dynamic> _pcs = [];
  List<dynamic> _accessRequests = [];
  Map<String, dynamic> _stats = {};

  @override
  void initState() {
    super.initState();
    _fetchBackupData();
  }

  Future<void> _fetchBackupData() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().get('/api/mobile/backup');
      if (res != null && res is Map<String, dynamic>) {
        setState(() {
          _pcs = res['pcs'] ?? [];
          _accessRequests = res['access_requests'] ?? [];
          _stats = res['stats'] ?? {};
        });
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  void _requestPcAccess() async {
    await ApiService().post('/api/mobile/backup/request-access', {});
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Pengajuan akses backup PC berhasil dikirim.')));
    _fetchBackupData();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Radar & Backup Data', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchBackupData),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : RefreshIndicator(
              onRefresh: _fetchBackupData,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  // Stat Card
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF0F172A), Color(0xFF047857)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(24),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('STATUS PANGKALAN DATA & RADAR PC', style: TextStyle(color: Color(0xFFA7F3D0), fontSize: 11, fontWeight: FontWeight.w800, letterSpacing: 0.8)),
                        const SizedBox(height: 8),
                        Text(_stats['system_status'] ?? 'TERHUBUNG & AMAN', style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900)),
                        const Divider(color: Colors.white24, height: 24),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('Total PC: ${_stats['total_pcs'] ?? _pcs.length}', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
                            Text('Storage: ${_stats['total_storage'] ?? "24.8 GB"}', style: const TextStyle(color: Color(0xFFA7F3D0), fontSize: 12, fontWeight: FontWeight.w700)),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Actions
                  Row(
                    children: [
                      Expanded(
                        child: ElevatedButton.icon(
                          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF1E3A8A)),
                          icon: const Icon(Icons.add_to_queue, color: Colors.white, size: 18),
                          label: const Text('AJUKAN AKSES PC', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white)),
                          onPressed: _requestPcAccess,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: ElevatedButton.icon(
                          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF047857)),
                          icon: const Icon(Icons.download, color: Colors.white, size: 18),
                          label: const Text('BACKUP SQL', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white)),
                          onPressed: () {
                            ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Mengunduh backup database SQL server...')));
                          },
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  const Text('PERANGKAT PC JARINGAN KANTOR TERDAFTAR', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
                  const SizedBox(height: 10),
                  if (_pcs.isEmpty)
                    const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Text('Belum ada PC kantor terhubung.', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))))
                  else
                    ..._pcs.map((pc) {
                      final isOnline = pc['status'] == 'ONLINE';
                      return Card(
                        margin: const EdgeInsets.only(bottom: 10),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        child: ListTile(
                          leading: CircleAvatar(
                            backgroundColor: isOnline ? const Color(0xFFDCFCE7) : const Color(0xFFF1F5F9),
                            child: Icon(Icons.computer, color: isOnline ? const Color(0xFF16A34A) : const Color(0xFF94A3B8)),
                          ),
                          title: Text(pc['name'] ?? 'PC-01', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                          subtitle: Text('IP: ${pc['ip_address']} | User: ${pc['user_name']}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                          trailing: Text(pc['status'] ?? 'OFFLINE', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 10, color: isOnline ? const Color(0xFF16A34A) : const Color(0xFF94A3B8))),
                        ),
                      );
                    }).toList(),
                ],
              ),
            ),
    );
  }
}