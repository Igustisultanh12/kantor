import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';

class MitraScreen extends StatefulWidget {
  const MitraScreen({super.key});

  @override
  State<MitraScreen> createState() => _MitraScreenState();
}

class _MitraScreenState extends State<MitraScreen> {
  bool _isLoading = true;
  List<dynamic> _mitras = [];
  int _selectedYear = DateTime.now().year;

  final List<String> _months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

  @override
  void initState() {
    super.initState();
    _fetchMitras();
  }

  Future<void> _fetchMitras() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().get('/api/mobile/mitra?tahun=$_selectedYear');
      if (res != null && res is Map<String, dynamic>) {
        setState(() {
          _mitras = res['data'] ?? [];
        });
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  void _showAddMitraDialog() {
    final formKey = GlobalKey<FormState>();
    final nameController = TextEditingController();
    final catController = TextEditingController(text: 'Reguler');
    final nominalController = TextEditingController(text: '0');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: const Text('Tambah Data Mitra', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
        content: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: nameController,
                decoration: InputDecoration(labelText: 'Nama Mitra / Instansi', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                validator: (v) => v == null || v.isEmpty ? 'Nama mitra wajib diisi' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: catController,
                decoration: InputDecoration(labelText: 'Kategori Mitra', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: nominalController,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(labelText: 'Nominal Iuran Rutin (Rp)', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('BATAL')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF2563EB), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
            onPressed: () async {
              if (formKey.currentState!.validate()) {
                await ApiService().post('/api/mobile/mitra', {
                  'nama_mitra': nameController.text.trim(),
                  'kategori': catController.text.trim(),
                  'nominal_rutin': double.tryParse(nominalController.text.trim()) ?? 0,
                });
                Navigator.of(ctx).pop();
                _fetchMitras();
              }
            },
            child: const Text('SIMPAN', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white)),
          ),
        ],
      ),
    );
  }

  void _togglePayment(int mitraId, int month) async {
    await ApiService().post('/api/mobile/mitra/$mitraId/toggle-payment', {
      'tahun': _selectedYear,
      'bulan': month,
    });
    _fetchMitras();
  }

  void _deleteMitra(int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Hapus Data Mitra?', style: TextStyle(fontWeight: FontWeight.w800)),
        content: const Text('Data mitra ini akan dihapus permanen dari sistem.'),
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
      await ApiService().delete('/api/mobile/mitra/$id');
      _fetchMitras();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pencatatan Mitra', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          DropdownButton<int>(
            value: _selectedYear,
            underline: const SizedBox(),
            icon: const Icon(Icons.arrow_drop_down, color: AppTheme.primaryNavy),
            items: [2024, 2025, 2026, 2027].map((y) => DropdownMenuItem(value: y, child: Text('$y', style: const TextStyle(fontWeight: FontWeight.bold)))).toList(),
            onChanged: (v) {
              if (v != null) {
                setState(() => _selectedYear = v);
                _fetchMitras();
              }
            },
          ),
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchMitras),
          const SizedBox(width: 8),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF2563EB),
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('TAMBAH MITRA', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white, letterSpacing: 0.5)),
        onPressed: _showAddMitraDialog,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : RefreshIndicator(
              onRefresh: _fetchMitras,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text('DAFTAR MITRA & MATRIKS STATUS PEMBAYARAN BULANAN $_selectedYear', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
                  const SizedBox(height: 12),
                  if (_mitras.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(32),
                      alignment: Alignment.center,
                      child: const Text('Belum ada mitra terdaftar.', style: TextStyle(color: Color(0xFF64748B))),
                    )
                  else
                    ..._mitras.map((m) {
                      final matrix = m['payment_matrix'] ?? {};
                      final id = m['id'];
                      return Card(
                        margin: const EdgeInsets.only(bottom: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Expanded(
                                    child: Text(m['nama_mitra'] ?? '-', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                    decoration: BoxDecoration(color: const Color(0xFFDBEAFE), borderRadius: BorderRadius.circular(6)),
                                    child: Text(m['kategori'] ?? 'Mitra', style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF1E3A8A))),
                                  ),
                                  if (id != null)
                                    IconButton(
                                      icon: const Icon(Icons.delete_outline, color: Color(0xFFDC2626), size: 20),
                                      onPressed: () => _deleteMitra(id),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              const Text('Tekan bulan untuk mengubah status pembayaran:', style: TextStyle(fontSize: 10, color: Color(0xFF64748B), fontWeight: FontWeight.w700)),
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 6,
                                runSpacing: 6,
                                children: List.generate(12, (index) {
                                  final monthNum = index + 1;
                                  final isPaid = matrix[monthNum.toString()] == true || matrix[monthNum] == true;
                                  return InkWell(
                                    onTap: id != null ? () => _togglePayment(id, monthNum) : null,
                                    borderRadius: BorderRadius.circular(8),
                                    child: Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                      decoration: BoxDecoration(
                                        color: isPaid ? const Color(0xFFDCFCE7) : const Color(0xFFF1F5F9),
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(color: isPaid ? const Color(0xFF16A34A) : const Color(0xFFCBD5E1), width: isPaid ? 1.5 : 1),
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Icon(isPaid ? Icons.check_circle : Icons.circle_outlined, size: 12, color: isPaid ? const Color(0xFF15803D) : const Color(0xFF94A3B8)),
                                          const SizedBox(width: 4),
                                          Text(
                                            _months[index],
                                            style: TextStyle(
                                              fontSize: 10,
                                              fontWeight: FontWeight.w900,
                                              color: isPaid ? const Color(0xFF15803D) : const Color(0xFF64748B),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  );
                                }),
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