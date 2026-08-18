import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/cash_provider.dart';

class CashFormDialog extends StatefulWidget {
  const CashFormDialog({super.key});

  @override
  State<CashFormDialog> createState() => _CashFormDialogState();
}

class _CashFormDialogState extends State<CashFormDialog> {
  final _formKey = GlobalKey<FormState>();
  final _descController = TextEditingController();
  final _amountController = TextEditingController();
  bool _isDebit = true;

  @override
  void dispose() {
    _descController.dispose();
    _amountController.dispose();
    super.dispose();
  }

  void _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final prov = Provider.of<CashProvider>(context, listen: false);
    final amount = double.tryParse(_amountController.text.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0.0;
    final now = DateTime.now();
    final dateStr = '--';

    final success = await prov.addTransaction(
      date: dateStr,
      description: _descController.text.trim(),
      debit: _isDebit ? amount : 0,
      credit: !_isDebit ? amount : 0,
    );

    if (success && mounted) {
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Transaksi kas berhasil dicatat.'),
          backgroundColor: Color(0xFF16A34A),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Catat Transaksi Kas', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
      content: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Expanded(
                  child: FilterChip(
                    label: const Text('Debit (Masuk)'),
                    selected: _isDebit,
                    onSelected: (v) => setState(() => _isDebit = true),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: FilterChip(
                    label: const Text('Kredit (Keluar)'),
                    selected: !_isDebit,
                    onSelected: (v) => setState(() => _isDebit = false),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _descController,
              decoration: const InputDecoration(labelText: 'Uraian Keterangan'),
              validator: (v) => v == null || v.isEmpty ? 'Masukkan uraian keterangan' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _amountController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Nominal Rupiah (Rp)'),
              validator: (v) => v == null || v.isEmpty ? 'Masukkan nominal' : null,
            ),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Batal')),
        FilledButton(onPressed: _submit, child: const Text('Simpan')),
      ],
    );
  }
}