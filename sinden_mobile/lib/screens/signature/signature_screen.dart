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
  List<dynamic> _skhppRequests = [];

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
          _skhppRequests = res['skhpp_requests'] ?? [];
        });
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  void _signDocument(int id) async {
    await ApiService().post('/api/mobile/signature-requests/$id/sign', {});
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Dokumen berhasil ditandatangani digital')));
    _fetchSignatures();
  }

  void _verifySkhpp(int id) async {
    await ApiService().post('/api/mobile/skhpp/$id/verify', {});
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('SKHPP berhasil disahkan dan ditandatangani')));
    _fetchSignatures();
  }

  void _previewPdf(String title, String? barcode) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: Row(
          children: [
            const Icon(Icons.picture_as_pdf, color: Color(0xFFDC2626), size: 24),
            const SizedBox(width: 8),
            Expanded(child: Text('Pratinjau Berkas TTE', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15))),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: const Color(0xFFF8FAFC), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFE2E8F0))),
              child: Column(
                children: [
                  const Icon(Icons.qr_code_2, size: 80, color: Color(0xFF1E3A8A)),
                  const SizedBox(height: 8),
                  Text('Tanda Tangan Elektronik Komandan Terverifikasi', style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF059669))),
                  if (barcode != null)
                    Text('Kode: $barcode', style: const TextStyle(fontSize: 9, color: Color(0xFF64748B))),
                ],
              ),
            ),
          ],
        ),
        actions: [
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF1E3A8A)),
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
                  const Text('ANTREAN TTE PERMOHONAN SKHPP', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
                  const SizedBox(height: 10),
                  if (_skhppRequests.isEmpty)
                    const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Text('Tidak ada antrean SKHPP menunggu pengesahan.', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))))
                  else
                    ..._skhppRequests.map((s) {
                      final id = s['id'];
                      return Card(
                        margin: const EdgeInsets.only(bottom: 10),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(s['nomor_skhpp'] ?? 'SKHPP Baru', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w900)),
                              Text('${s['nama'] ?? '-'} (${s['pangkat_korps_nrp'] ?? '-'})', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                              const SizedBox(height: 10),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  OutlinedButton.icon(
                                    icon: const Icon(Icons.preview, size: 16),
                                    label: const Text('PRATINJAU', style: TextStyle(fontSize: 11)),
                                    onPressed: () => _previewPdf(s['nomor_skhpp'] ?? 'SKHPP', s['verification_code']),
                                  ),
                                  const SizedBox(width: 8),
                                  ElevatedButton.icon(
                                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF059669)),
                                    icon: const Icon(Icons.draw, size: 16, color: Colors.white),
                                    label: const Text('TTE SEKARANG', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white)),
                                    onPressed: id != null ? () => _verifySkhpp(id) : null,
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      );
                    }).toList(),

                  const SizedBox(height: 20),
                  const Text('ANTREAN PERMOHONAN TTE NASKAH LAINNYA', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
                  const SizedBox(height: 10),
                  if (_requests.isEmpty)
                    const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Text('Tidak ada antrean naskah dinas lain.', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))))
                  else
                    ..._requests.map((r) {
                      final id = r['id'];
                      return Card(
                        margin: const EdgeInsets.only(bottom: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(r['subject'] ?? r['document_title'] ?? 'Dokumen Kedinasan', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                              Text('Status: ${r['status'] ?? 'PENDING'}', style: const TextStyle(fontSize: 11, color: Color(0xFF7C3AED), fontWeight: FontWeight.bold)),
                              const SizedBox(height: 10),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  ElevatedButton.icon(
                                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF7C3AED)),
                                    icon: const Icon(Icons.draw, size: 16, color: Colors.white),
                                    label: const Text('TANDATANGANI', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white)),
                                    onPressed: id != null ? () => _signDocument(id) : null,
                                  ),
                                ],
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