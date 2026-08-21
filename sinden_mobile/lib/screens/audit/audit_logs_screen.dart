import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';

class AuditLogsScreen extends StatefulWidget {
  const AuditLogsScreen({super.key});

  @override
  State<AuditLogsScreen> createState() => _AuditLogsScreenState();
}

class _AuditLogsScreenState extends State<AuditLogsScreen> {
  bool _isLoading = true;
  List<dynamic> _visitorLogs = [];

  @override
  void initState() {
    super.initState();
    _fetchLogs();
  }

  Future<void> _fetchLogs() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().get('/api/mobile/audit-logs');
      if (res != null && res is Map<String, dynamic>) {
        setState(() {
          _visitorLogs = res['visitor_logs'] ?? res['data'] ?? [];
        });
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Log & Audit Sistem', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchLogs),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : RefreshIndicator(
              onRefresh: _fetchLogs,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const Text('JEJAK RIWAYAT AKTIVITAS & PENGUNJUNG', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
                  const SizedBox(height: 12),
                  if (_visitorLogs.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(32),
                      alignment: Alignment.center,
                      child: const Text('Belum ada log pengunjung terekam.', style: TextStyle(color: Color(0xFF64748B))),
                    )
                  else
                    ..._visitorLogs.map((log) {
                      return Card(
                        margin: const EdgeInsets.only(bottom: 10),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        child: ListTile(
                          leading: const CircleAvatar(
                            backgroundColor: Color(0xFFF1F5F9),
                            child: Icon(Icons.history, color: Color(0xFF475569)),
                          ),
                          title: Text(log['user_name'] ?? log['user']?['name'] ?? 'Tamu / Guest', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                          subtitle: Text('IP: ${log['ip_address'] ?? '-'} | ${log['created_at'] ?? ''}', style: const TextStyle(fontSize: 10, color: Color(0xFF64748B))),
                        ),
                      );
                    }).toList(),
                ],
              ),
            ),
    );
  }
}