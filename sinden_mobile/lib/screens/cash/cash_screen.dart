import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../../core/theme.dart';
import '../../providers/cash_provider.dart';
import 'cash_form_dialog.dart';

class CashScreen extends StatefulWidget {
  const CashScreen({super.key});

  @override
  State<CashScreen> createState() => _CashScreenState();
}

class _CashScreenState extends State<CashScreen> {
  final currencyFormat = NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<CashProvider>(context, listen: false).fetchCashData();
    });
  }

  @override
  Widget build(BuildContext context) {
    final cashProv = Provider.of<CashProvider>(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Buku Kas Unit Teknis'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => cashProv.fetchCashData(),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          showDialog(
            context: context,
            builder: (_) => const CashFormDialog(),
          );
        },
        backgroundColor: const Color(0xFF16A34A),
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('Catat Kas', style: TextStyle(fontWeight: FontWeight.w800)),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Card(
              color: AppTheme.primaryNavy,
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'TOTAL SALDO KAS TERSEDIA',
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF93C5FD)),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      currencyFormat.format(cashProv.balance),
                      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: Colors.white),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Masuk: ${currencyFormat.format(cashProv.totalDebit)}',
                          style: const TextStyle(fontSize: 11, color: Color(0xFF86EFAC), fontWeight: FontWeight.w700),
                        ),
                        Text(
                          'Keluar: ${currencyFormat.format(cashProv.totalCredit)}',
                          style: const TextStyle(fontSize: 11, color: Color(0xFFFCA5A5), fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
          Expanded(
            child: cashProv.isLoading
                ? const Center(child: CircularProgressIndicator())
                : cashProv.transactions.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: const [
                            Icon(Icons.account_balance_wallet_outlined, size: 48, color: Color(0xFFCBD5E1)),
                            SizedBox(height: 12),
                            Text(
                              'Belum Ada Catatan Transaksi',
                              style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF64748B)),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () => cashProv.fetchCashData(),
                        child: ListView.builder(
                          padding: const EdgeInsets.only(left: 16, right: 16, bottom: 80),
                          itemCount: cashProv.transactions.length,
                          itemBuilder: (context, index) {
                            final tx = cashProv.transactions[index];
                            final isDebit = tx.debit > 0;
                            return Card(
                              child: ListTile(
                                leading: CircleAvatar(
                                  backgroundColor: isDebit ? const Color(0xFFDCFCE7) : const Color(0xFFFEE2E2),
                                  child: Icon(
                                    isDebit ? Icons.arrow_downward : Icons.arrow_upward,
                                    color: isDebit ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                                    size: 18,
                                  ),
                                ),
                                title: Text(
                                  tx.description,
                                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                                ),
                                subtitle: Text(
                                  tx.date,
                                  style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                ),
                                trailing: Text(
                                  currencyFormat.format(isDebit ? tx.debit : tx.credit),
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w900,
                                    color: isDebit ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}