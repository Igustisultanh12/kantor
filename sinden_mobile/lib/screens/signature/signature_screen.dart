import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';

class SignatureScreen extends StatefulWidget {
  const SignatureScreen({super.key});

  @override
  State<SignatureScreen> createState() => _SignatureScreenState();
}

class _SignatureScreenState extends State<SignatureScreen> {
  bool _isLoading = true;
  List<dynamic> _requests = [];

  @override
  void initState() {
    super.initState();
    _fetchSignatures();
  }

  Future<void> _fetchSignatures() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().get('/api/mobile/signature-requests');
      if (res != null && res is Map<String, dynamic>) {
        setState(() {
          _requests = res['requests'] ?? res['data'] ?? [];
        });
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tanda Tangan Digital (TTE)', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchSignatures),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : RefreshIndicator(
              onRefresh: _fetchSignatures,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const Text('ANTREAN PERMOHONAN TTE DIGITAL', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
                  const SizedBox(height: 12),
                  if (_requests.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(32),
                      alignment: Alignment.center,
                      child: const Text('Tidak ada antrean permohonan TTE.', style: TextStyle(color: Color(0xFF64748B))),
                    )
                  else
                    ..._requests.map((r) {
                      return Card(
                        margin: const EdgeInsets.only(bottom: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
                        child: ListTile(
                          leading: const CircleAvatar(
                            backgroundColor: Color(0xFFEDE9FE),
                            child: Icon(Icons.draw, color: Color(0xFF7C3AED)),
                          ),
                          title: Text(r['subject'] ?? r['document_title'] ?? 'Dokumen Kedinasan', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                          subtitle: Text('Status: ${r['status'] ?? 'PENDING'}', style: const TextStyle(fontSize: 11, color: Color(0xFF7C3AED), fontWeight: FontWeight.bold)),
                        ),
                      );
                    }).toList(),
                ],
              ),
            ),
    );
  }
}