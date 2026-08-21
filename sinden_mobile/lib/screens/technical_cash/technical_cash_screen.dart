import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';

class TechnicalCashScreen extends StatefulWidget {
  const TechnicalCashScreen({super.key});

  @override
  State<TechnicalCashScreen> createState() => _TechnicalCashScreenState();
}

class _TechnicalCashScreenState extends State<TechnicalCashScreen> {
  bool _isLoading = true;
  double _balance = 0;
  double _totalDebit = 0;
  double _totalCredit = 0;
  List<dynamic> _transactions = [];

  final currencyFormatter = NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0);

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().get('/api/mobile/technical-cash');
      if (res != null && res is Map<String, dynamic>) {
        setState(() {
          _balance = double.tryParse(res['balance']?.toString() ?? '0') ?? 0;
          _totalDebit = double.tryParse(res['total_debit']?.toString() ?? '0') ?? 0;
          _totalCredit = double.tryParse(res['total_credit']?.toString() ?? '0') ?? 0;
          _transactions = res['cashes'] ?? res['transactions'] ?? res['data'] ?? [];
        });
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  void _showAddTransactionDialog() {
    final formKey = GlobalKey<FormState>();
    String jenis = 'PENERIMAAN';
    final amountController = TextEditingController();
    final descController = TextEditingController();
    final dateController = TextEditingController(text: DateFormat('yyyy-MM-dd').format(DateTime.now()));

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          title: const Text('Tambah Transaksi Kas Dan Unit', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
          content: Form(
            key: formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<String>(
                    value: jenis,
                    decoration: InputDecoration(
                      labelText: 'Jenis Mutasi',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    items: const [
                      DropdownMenuItem(value: 'PENERIMAAN', child: Text('PENERIMAAN KAS (DEBIT)', style: TextStyle(color: Color(0xFF0891B2), fontWeight: FontWeight.bold))),
                      DropdownMenuItem(value: 'PENGELUARAN', child: Text('PENGELUARAN KAS (KREDIT)', style: TextStyle(color: Color(0xFFDC2626), fontWeight: FontWeight.bold))),
                    ],
                    onChanged: (v) => setModalState(() => jenis = v!),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: amountController,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: 'Nominal (Rp)',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    validator: (v) => v == null || v.isEmpty ? 'Nominal wajib diisi' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: descController,
                    decoration: InputDecoration(
                      labelText: 'Uraian Kas Dan Unit Teknis',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    validator: (v) => v == null || v.isEmpty ? 'Uraian wajib diisi' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: dateController,
                    decoration: InputDecoration(
                      labelText: 'Tanggal (YYYY-MM-DD)',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('BATAL')),
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0891B2),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              onPressed: () async {
                if (formKey.currentState!.validate()) {
                  final amount = double.tryParse(amountController.text.trim()) ?? 0;
                  final body = {
                    'debit': jenis == 'PENERIMAAN' ? amount : 0,
                    'credit': jenis == 'PENGELUARAN' ? amount : 0,
                    'description': descController.text.trim(),
                    'date': dateController.text.trim(),
                  };
                  await ApiService().post('/api/mobile/technical-cash', body);
                  Navigator.of(ctx).pop();
                  _fetchData();
                }
              },
              child: const Text('SIMPAN', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white)),
            ),
          ],
        ),
      ),
    );
  }

  void _deleteTransaction(int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Hapus Transaksi Kas Dan Unit?', style: TextStyle(fontWeight: FontWeight.w800)),
        content: const Text('Data transaksi buku kas Dan Unit Teknis ini akan dihapus permanen.'),
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
      await ApiService().delete('/api/mobile/technical-cash/$id');
      _fetchData();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Buku Kas Dan Unit Teknis', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchData),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF0891B2),
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('TRANSAKSI KAS DAN UNIT', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white, letterSpacing: 0.5)),
        onPressed: _showAddTransactionDialog,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : RefreshIndicator(
              onRefresh: _fetchData,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  // Saldo Card Dan Unit Teknis
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF164E63), Color(0xFF0891B2)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: [
                        BoxShadow(color: const Color(0xFF0891B2).withOpacity(0.3), blurRadius: 12, offset: const Offset(0, 4))
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('SALDO BERJALAN KAS DAN UNIT TEKNIS', style: TextStyle(color: Color(0xFFA5F3FC), fontSize: 11, fontWeight: FontWeight.w800, letterSpacing: 0.8)),
                        const SizedBox(height: 8),
                        Text(currencyFormatter.format(_balance), style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
                        const Divider(color: Colors.white24, height: 24),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Total Debit (Masuk)', style: TextStyle(color: Color(0xFFA5F3FC), fontSize: 10)),
                                Text(currencyFormatter.format(_totalDebit), style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w800)),
                              ],
                            ),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                const Text('Total Kredit (Keluar)', style: TextStyle(color: Color(0xFFFECACA), fontSize: 10)),
                                Text(currencyFormatter.format(_totalCredit), style: const TextStyle(color: Color(0xFFFCA5A5), fontSize: 13, fontWeight: FontWeight.w800)),
                              ],
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text('MUTASI PEMBUKUAN DAN UNIT TEKNIS', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
                  const SizedBox(height: 10),
                  if (_transactions.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(32),
                      alignment: Alignment.center,
                      child: const Column(
                        children: [
                          Icon(Icons.account_balance_wallet_outlined, size: 48, color: Color(0xFF94A3B8)),
                          SizedBox(height: 8),
                          Text('Belum ada transaksi kas Dan Unit Teknis tercatat.', style: TextStyle(color: Color(0xFF64748B), fontSize: 12)),
                        ],
                      ),
                    )
                  else
                    ..._transactions.map((tx) {
                      final isDebit = (double.tryParse(tx['debit']?.toString() ?? '0') ?? 0) > 0;
                      final amount = isDebit
                          ? (double.tryParse(tx['debit']?.toString() ?? '0') ?? 0)
                          : (double.tryParse(tx['credit']?.toString() ?? '0') ?? 0);
                      final id = tx['id'];

                      return Card(
                        margin: const EdgeInsets.only(bottom: 10),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        child: ListTile(
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          leading: CircleAvatar(
                            backgroundColor: isDebit ? const Color(0xFFCFFAFE) : const Color(0xFFFEE2E2),
                            child: Icon(isDebit ? Icons.arrow_downward : Icons.arrow_upward, color: isDebit ? const Color(0xFF0891B2) : const Color(0xFFDC2626), size: 20),
                          ),
                          title: Text(tx['description'] ?? '-', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                          subtitle: Text(tx['date'] ?? '', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                          trailing: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                (isDebit ? '+ ' : '- ') + currencyFormatter.format(amount),
                                style: TextStyle(fontWeight: FontWeight.w900, fontSize: 13, color: isDebit ? const Color(0xFF0891B2) : const Color(0xFFDC2626)),
                              ),
                              if (id != null)
                                IconButton(
                                  icon: const Icon(Icons.delete_outline, color: Color(0xFFDC2626), size: 20),
                                  onPressed: () => _deleteTransaction(id),
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