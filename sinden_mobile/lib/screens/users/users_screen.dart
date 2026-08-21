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

  void _showAddUserDialog() {
    final formKey = GlobalKey<FormState>();
    final nameController = TextEditingController();
    final emailController = TextEditingController();
    final nrpController = TextEditingController();
    final pangkatController = TextEditingController(text: 'Letda Laut (P)');
    final korpsController = TextEditingController(text: 'Pelaut');
    final jabatanController = TextEditingController(text: 'Paur Intel');
    final passController = TextEditingController(text: '12345678');
    String role = 'user';

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          title: const Text('Tambah Akun Personel', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
          content: Form(
            key: formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextFormField(
                    controller: nameController,
                    decoration: InputDecoration(labelText: 'Nama Lengkap', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    validator: (v) => v == null || v.isEmpty ? 'Nama wajib diisi' : null,
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: nrpController,
                    decoration: InputDecoration(labelText: 'NRP', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    validator: (v) => v == null || v.isEmpty ? 'NRP wajib diisi' : null,
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: emailController,
                    decoration: InputDecoration(labelText: 'Email Kedinasan', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    validator: (v) => v == null || v.isEmpty ? 'Email wajib diisi' : null,
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: pangkatController,
                    decoration: InputDecoration(labelText: 'Pangkat', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: jabatanController,
                    decoration: InputDecoration(labelText: 'Jabatan', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    value: role,
                    decoration: InputDecoration(labelText: 'Peran / Role Otoritas', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    items: const [
                      DropdownMenuItem(value: 'user', child: Text('Personel Satuan')),
                      DropdownMenuItem(value: 'admin', child: Text('Administrator Sistem')),
                      DropdownMenuItem(value: 'komandan', child: Text('Komandan (Approver)')),
                    ],
                    onChanged: (v) => setModalState(() => role = v!),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: passController,
                    decoration: InputDecoration(labelText: 'Kata Sandi Awal', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('BATAL')),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF1E3A8A), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
              onPressed: () async {
                if (formKey.currentState!.validate()) {
                  await ApiService().post('/api/mobile/users', {
                    'name': nameController.text.trim(),
                    'nrp': nrpController.text.trim(),
                    'email': emailController.text.trim(),
                    'pangkat': pangkatController.text.trim(),
                    'korps': korpsController.text.trim(),
                    'jabatan': jabatanController.text.trim(),
                    'role': role,
                    'password': passController.text.trim(),
                  });
                  Navigator.of(ctx).pop();
                  _fetchUsers();
                }
              },
              child: const Text('SIMPAN', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white)),
            ),
          ],
        ),
      ),
    );
  }

  void _toggleActive(int id, bool currentActive) async {
    await ApiService().put('/api/mobile/users/$id', {'is_active': !currentActive});
    _fetchUsers();
  }

  void _deleteUser(int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Hapus Akun Personel?', style: TextStyle(fontWeight: FontWeight.w800)),
        content: const Text('Akun personel ini akan dihapus permanen dari sistem.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('BATAL')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFDC2626)),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('HAPUS', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirm == true) {
      await ApiService().delete('/api/mobile/users/$id');
      _fetchUsers();
    }
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
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF1E3A8A),
        icon: const Icon(Icons.person_add, color: Colors.white),
        label: const Text('TAMBAH PERSONEL', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white, letterSpacing: 0.5)),
        onPressed: _showAddUserDialog,
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
                    final id = u['id'];
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
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            InkWell(
                              onTap: id != null ? () => _toggleActive(id, isActive) : null,
                              child: Container(
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
                            if (id != null)
                              IconButton(
                                icon: const Icon(Icons.delete_outline, color: Color(0xFFDC2626), size: 20),
                                onPressed: () => _deleteUser(id),
                              ),
                          ],
                        ),
                      ),
                    );
                  }).toList(),
                  const SizedBox(height: 80),
                ],
              ),
            ),
    );
  }
}