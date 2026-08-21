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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Buat & Draf Surat', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchLetters),
        ],
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
                        ),
                      );
                    }).toList(),
                ],
              ),
            ),
    );
  }
}