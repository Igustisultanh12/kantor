import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';

class CommanderAccountScreen extends StatefulWidget {
  const CommanderAccountScreen({super.key});

  @override
  State<CommanderAccountScreen> createState() => _CommanderAccountScreenState();
}

class _CommanderAccountScreenState extends State<CommanderAccountScreen> {
  bool _isLoading = true;
  double _totalMasuk = 0;
  double _totalKeluar = 0;
  double _saldoAkhir = 0;
  List<dynamic> _logs = [];

  final currencyFormatter = NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0);

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().get('/api/mobile/commander-account');
      if (res != null && res is Map<String, dynamic>) {
        setState(() {
          _totalMasuk = double.tryParse(res['total_masuk']?.toString() ?? '0') ?? 0;
          _totalKeluar = double.tryParse(res['total_keluar']?.toString() ?? '0') ?? 0;
          _saldoAkhir = double.tryParse(res['saldo_akhir']?.toString() ?? '0') ?? 0;
          _logs = res['data'] ?? res['logs'] ?? [];
        });
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Rekening Komandan', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchData),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : RefreshIndicator(
              onRefresh: _fetchData,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  // Saldo Card
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF064E3B), Color(0xFF047857)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: [
                        BoxShadow(color: const Color(0xFF047857).withOpacity(0.3), blurRadius: 12, offset: const Offset(0, 4))
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('SALDO AKHIR REKENING KOMANDAN', style: TextStyle(color: Color(0xFFA7F3D0), fontSize: 11, fontWeight: FontWeight.w800, letterSpacing: 0.8)),
                        const SizedBox(height: 8),
                        Text(currencyFormatter.format(_saldoAkhir), style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
                        const Divider(color: Colors.white24, height: 24),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Total Masuk', style: TextStyle(color: Color(0xFFA7F3D0), fontSize: 10)),
                                Text(currencyFormatter.format(_totalMasuk), style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w800)),
                              ],
                            ),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                const Text('Total Keluar', style: TextStyle(color: Color(0xFFFECACA), fontSize: 10)),
                                Text(currencyFormatter.format(_totalKeluar), style: const TextStyle(color: Color(0xFFFCA5A5), fontSize: 13, fontWeight: FontWeight.w800)),
                              ],
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text('MUTASI OPERASIONAL KOMANDAN', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
                  const SizedBox(height: 10),
                  if (_logs.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(32),
                      alignment: Alignment.center,
                      child: const Column(
                        children: [
                          Icon(Icons.receipt_long_outlined, size: 48, color: Color(0xFF94A3B8)),
                          SizedBox(height: 8),
                          Text('Belum ada transaksi operasional tercatat.', style: TextStyle(color: Color(0xFF64748B), fontSize: 12)),
                        ],
                      ),
                    )
                  else
                    ..._logs.map((log) {
                      final isMasuk = log['jenis'] == 'MASUK';
                      final amount = double.tryParse(log['jumlah']?.toString() ?? '0') ?? 0;
                      return Card(
                        margin: const EdgeInsets.only(bottom: 10),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        child: ListTile(
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          leading: CircleAvatar(
                            backgroundColor: isMasuk ? const Color(0xFFD1FAE5) : const Color(0xFFFEE2E2),
                            child: Icon(isMasuk ? Icons.arrow_downward : Icons.arrow_upward, color: isMasuk ? const Color(0xFF059669) : const Color(0xFFDC2626), size: 20),
                          ),
                          title: Text(log['keterangan'] ?? '-', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                          subtitle: Text(log['tanggal'] ?? '', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                          trailing: Text(
                            (isMasuk ? '+ ' : '- ') + currencyFormatter.format(amount),
                            style: TextStyle(fontWeight: FontWeight.w900, fontSize: 13, color: isMasuk ? const Color(0xFF059669) : const Color(0xFFDC2626)),
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