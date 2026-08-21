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
          const SizedBox(width: 12),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : RefreshIndicator(
              onRefresh: _fetchMitras,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text('DAFTAR MITRA & MATRIKS BULANAN $_selectedYear', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
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
                                ],
                              ),
                              const SizedBox(height: 12),
                              const Text('Status Pembayaran 12 Bulan:', style: TextStyle(fontSize: 10, color: Color(0xFF64748B), fontWeight: FontWeight.w700)),
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 6,
                                runSpacing: 6,
                                children: List.generate(12, (index) {
                                  final monthNum = index + 1;
                                  final isPaid = matrix[monthNum.toString()] == true || matrix[monthNum] == true;
                                  return Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: isPaid ? const Color(0xFFDCFCE7) : const Color(0xFFF1F5F9),
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(color: isPaid ? const Color(0xFF86EFAC) : const Color(0xFFE2E8F0)),
                                    ),
                                    child: Text(
                                      _months[index],
                                      style: TextStyle(
                                        fontSize: 9.5,
                                        fontWeight: FontWeight.w800,
                                        color: isPaid ? const Color(0xFF15803D) : const Color(0xFF94A3B8),
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
                ],
              ),
            ),
    );
  }
}