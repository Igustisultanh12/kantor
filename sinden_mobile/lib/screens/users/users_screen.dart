import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';

class UsersScreen extends StatefulWidget {
  const UsersScreen({super.key});

  @override
  State<UsersScreen> createState() => _UsersScreenState();
}

class _UsersScreenState extends State<UsersScreen> {
  bool _isLoading = true;
  List<dynamic> _users = [];

  @override
  void initState() {
    super.initState();
    _fetchUsers();
  }

  Future<void> _fetchUsers() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().get('/api/mobile/users');
      if (res != null && res is Map<String, dynamic>) {
        setState(() {
          _users = res['data'] ?? res['users'] ?? [];
        });
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Kelola Pengguna', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchUsers),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : RefreshIndicator(
              onRefresh: _fetchUsers,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text('TOTAL PERSONEL TERDAFTAR: ${_users.length}', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
                  const SizedBox(height: 12),
                  ..._users.map((u) {
                    final isAdmin = u['role'] == 'admin';
                    final isActive = u['is_active'] == true;
                    return Card(
                      margin: const EdgeInsets.only(bottom: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: isAdmin ? const Color(0xFFFEE2E2) : const Color(0xFFDBEAFE),
                          child: Icon(Icons.person, color: isAdmin ? const Color(0xFFDC2626) : const Color(0xFF1E3A8A)),
                        ),
                        title: Text(u['name'] ?? '-', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                        subtitle: Text('${u['pangkat'] ?? 'Prajurit'} ${u['korps'] ?? ''} - NRP ${u['nrp'] ?? '-'}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                        trailing: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: isActive ? const Color(0xFFDCFCE7) : const Color(0xFFFEE2E2),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            isActive ? 'AKTIF' : 'NONAKTIF',
                            style: TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: isActive ? const Color(0xFF16A34A) : const Color(0xFFDC2626)),
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                ],
              ),
            ),
    );
  }
}