import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/skhpp_provider.dart';

class SkhppFormScreen extends StatefulWidget {
  const SkhppFormScreen({super.key});

  @override
  State<SkhppFormScreen> createState() => _SkhppFormScreenState();
}

class _SkhppFormScreenState extends State<SkhppFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _namaController = TextEditingController();
  final _pangkatController = TextEditingController();
  final _jabatanController = TextEditingController();
  final _kesatuanController = TextEditingController();
  final _peruntukanController = TextEditingController();
  String _kategori = 'militer';

  @override
  void dispose() {
    _namaController.dispose();
    _pangkatController.dispose();
    _jabatanController.dispose();
    _kesatuanController.dispose();
    _peruntukanController.dispose();
    super.dispose();
  }

  void _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final prov = Provider.of<SkhppProvider>(context, listen: false);
    final success = await prov.createSkhpp({
      'nama': _namaController.text.trim(),
      'pangkat_korps_nrp': _pangkatController.text.trim(),
      'jabatan': _jabatanController.text.trim(),
      'kesatuan': _kesatuanController.text.trim(),
      'peruntukan': _peruntukanController.text.trim(),
      'kategori': _kategori,
    });

    if (success && mounted) {
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Permohonan SKHPP berhasil diajukan.'),
          backgroundColor: Color(0xFF16A34A),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Formulir Pengajuan SKHPP')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TextFormField(
                controller: _namaController,
                decoration: const InputDecoration(labelText: 'Nama Lengkap'),
                validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _pangkatController,
                decoration: const InputDecoration(labelText: 'Pangkat / Korps / NRP'),
                validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _jabatanController,
                decoration: const InputDecoration(labelText: 'Jabatan'),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _kesatuanController,
                decoration: const InputDecoration(labelText: 'Kesatuan'),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _peruntukanController,
                decoration: const InputDecoration(labelText: 'Keperluan / Peruntukan SKHPP'),
                validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _submit,
                child: const Text('KIRIM PENGAJUAN SKHPP'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}