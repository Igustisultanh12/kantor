import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';

class LettersDraftScreen extends StatefulWidget {
  const LettersDraftScreen({super.key});

  @override
  State<LettersDraftScreen> createState() => _LettersDraftScreenState();
}

class _LettersDraftScreenState extends State<LettersDraftScreen> {
  bool _isLoading = true;
  List<dynamic> _letters = [];

  @override
  void initState() {
    super.initState();
    _fetchLetters();
  }

  Future<void> _fetchLetters() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().get('/api/mobile/letters');
      if (res != null && res is Map<String, dynamic>) {
        setState(() {
          _letters = res['data'] ?? res['letters'] ?? [];
        });
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  void _showAddDraftDialog() {
    final formKey = GlobalKey<FormState>();
    final subjectController = TextEditingController();
    final recipientController = TextEditingController();
    final numberController = TextEditingController(text: 'DRAF/${DateTime.now().millisecondsSinceEpoch}');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: const Text('Buat Draf Naskah Dinas', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
        content: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: subjectController,
                decoration: InputDecoration(labelText: 'Perihal Naskah Dinas', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                validator: (v) => v == null || v.isEmpty ? 'Perihal wajib diisi' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: recipientController,
                decoration: InputDecoration(labelText: 'Penerima / Alamat Tujuan', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                validator: (v) => v == null || v.isEmpty ? 'Penerima wajib diisi' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: numberController,
                decoration: InputDecoration(labelText: 'Nomor Registrasi Draf', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('BATAL')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFB45309), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
            onPressed: () async {
              if (formKey.currentState!.validate()) {
                await ApiService().post('/api/mobile/letters', {
                  'subject': subjectController.text.trim(),
                  'recipient': recipientController.text.trim(),
                  'letter_number': numberController.text.trim(),
                });
                Navigator.of(ctx).pop();
                _fetchLetters();
              }
            },
            child: const Text('SIMPAN DRAF', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white)),
          ),
        ],
      ),
    );
  }

  void _previewLetterPdf(dynamic letter) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: const Row(
          children: [
            Icon(Icons.picture_as_pdf, color: Color(0xFFDC2626), size: 24),
            SizedBox(width: 8),
            Expanded(child: Text('Pratinjau Draf Naskah', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15))),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(letter['subject'] ?? 'Naskah Dinas', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: AppTheme.primaryNavy)),
            const SizedBox(height: 8),
            Text('Nomor Draf: ${letter['letter_number'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
            Text('Tujuan: ${letter['recipient'] ?? '-'}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
            Text('Tanggal: ${letter['date'] ?? '-'}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
          ],
        ),
        actions: [
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primaryNavy),
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('TUTUP', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Buat & Draf Surat', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchLetters),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFFB45309),
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('BUAT DRAF BARU', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white, letterSpacing: 0.5)),
        onPressed: _showAddDraftDialog,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : RefreshIndicator(
              onRefresh: _fetchLetters,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const Text('DAFTAR ARSIP DRAF NASKAH DINAS', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
                  const SizedBox(height: 12),
                  if (_letters.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(32),
                      alignment: Alignment.center,
                      child: const Text('Belum ada draf naskah dinas dibuat.', style: TextStyle(color: Color(0xFF64748B))),
                    )
                  else
                    ..._letters.map((l) {
                      return Card(
                        margin: const EdgeInsets.only(bottom: 10),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        child: ListTile(
                          leading: const CircleAvatar(
                            backgroundColor: Color(0xFFFEF3C7),
                            child: Icon(Icons.edit_document, color: Color(0xFFB45309)),
                          ),
                          title: Text(l['subject'] ?? l['title'] ?? 'Naskah Dinas', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                          subtitle: Text('Nomor: ${l['letter_number'] ?? '-'} | Kepada: ${l['recipient'] ?? '-'}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                          trailing: IconButton(
                            icon: const Icon(Icons.picture_as_pdf, color: Color(0xFFDC2626), size: 20),
                            onPressed: () => _previewLetterPdf(l),
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