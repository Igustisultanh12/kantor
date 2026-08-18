import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/letter_provider.dart';

class BookLetterDialog extends StatefulWidget {
  const BookLetterDialog({super.key});

  @override
  State<BookLetterDialog> createState() => _BookLetterDialogState();
}

class _BookLetterDialogState extends State<BookLetterDialog> {
  final _formKey = GlobalKey<FormState>();
  final _subjectController = TextEditingController();
  final _recipientController = TextEditingController();
  int _selectedCategoryId = 1;
  DateTime _selectedDate = DateTime.now();

  @override
  void dispose() {
    _subjectController.dispose();
    _recipientController.dispose();
    super.dispose();
  }

  void _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final prov = Provider.of<LetterProvider>(context, listen: false);
    final dateStr = '--';

    final success = await prov.bookLetterNumber(
      categoryId: _selectedCategoryId,
      subject: _subjectController.text.trim(),
      recipient: _recipientController.text.trim(),
      date: dateStr,
    );

    if (success && mounted) {
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Nomor surat berhasil dibooking.'),
          backgroundColor: Color(0xFF16A34A),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Booking Nomor Surat Baru', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
      content: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: _subjectController,
                decoration: const InputDecoration(labelText: 'Perihal Surat'),
                validator: (v) => v == null || v.isEmpty ? 'Masukkan perihal surat' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _recipientController,
                decoration: const InputDecoration(labelText: 'Alamat Tujuan / Kepada'),
                validator: (v) => v == null || v.isEmpty ? 'Masukkan alamat tujuan' : null,
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(
          onPressed: _submit,
          child: const Text('Booking Nomor'),
        ),
      ],
    );
  }
}