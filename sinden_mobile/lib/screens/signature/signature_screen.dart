import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';
import '../common/pdf_preview_screen.dart';
import 'qr_verification_screen.dart';

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

  void _showSignPositionModal(int id, String title) {
    int targetPage = 1;
    String position = 'BAWAH_KANAN';

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          title: Text('Penempatan QR TTE Komandan', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: Color(0xFF64748B))),
              const SizedBox(height: 16),
              DropdownButtonFormField<int>(
                value: targetPage,
                decoration: InputDecoration(labelText: 'Halaman Penempatan TTE', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                items: [1, 2, 3, 4, 5].map((p) => DropdownMenuItem(value: p, child: Text('Halaman $p'))).toList(),
                onChanged: (v) => setModalState(() => targetPage = v!),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: position,
                decoration: InputDecoration(labelText: 'Letak / Posisi Stempel QR', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                items: const [
                  DropdownMenuItem(value: 'BAWAH_KANAN', child: Text('Bawah Kanan (Standar Kedinasan)')),
                  DropdownMenuItem(value: 'BAWAH_KIRI', child: Text('Bawah Kiri')),
                  DropdownMenuItem(value: 'BAWAH_TENGAH', child: Text('Bawah Tengah')),
                  DropdownMenuItem(value: 'TENGAH', child: Text('Tengah Halaman')),
                ],
                onChanged: (v) => setModalState(() => position = v!),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('BATAL')),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF059669), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
              onPressed: () async {
                double x = 0.65;
                double y = 0.75;
                if (position == 'BAWAH_KIRI') { x = 0.15; y = 0.75; }
                else if (position == 'BAWAH_TENGAH') { x = 0.40; y = 0.75; }
                else if (position == 'TENGAH') { x = 0.40; y = 0.45; }

                await ApiService().post('/api/mobile/signature-requests/$id/sign-custom', {
                  'x': x,
                  'y': y,
                  'target_page': targetPage,
                });
                Navigator.of(ctx).pop();
                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Dokumen berhasil disahkan dengan TTE Digital.')));
                _fetchSignatures();
              },
              child: const Text('SAHKAN TTE', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white)),
            ),
          ],
        ),
      ),
    );
  }

  void _verifySkhpp(int id) async {
    await ApiService().post('/api/mobile/skhpp/$id/verify', {});
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('SKHPP berhasil disahkan dan ditandatangani')));
    _fetchSignatures();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tanda Tangan Digital (TTE)', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(
            tooltip: 'Validasi QR Keaslian TTE',
            icon: const Icon(Icons.qr_code_scanner, color: AppTheme.primaryNavy),
            onPressed: () {
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const QrVerificationScreen()));
            },
          ),
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
                  // Verification Banner Button
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: const Color(0xFFDBEAFE),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFF93C5FD)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.verified_user, color: Color(0xFF1E3A8A), size: 28),
                        const SizedBox(width: 12),
                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Validasi Keaslian TTE Digital', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 12, color: Color(0xFF1E3A8A))),
                              Text('Pindai kode QR untuk mengecek keaslian dokumen.', style: TextStyle(fontSize: 10, color: Color(0xFF475569))),
                            ],
                          ),
                        ),
                        ElevatedButton(
                          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF1E3A8A), padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8)),
                          onPressed: () {
                            Navigator.of(context).push(MaterialPageRoute(builder: (_) => const QrVerificationScreen()));
                          },
                          child: const Text('SCAN QR', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.white)),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

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
                                    icon: const Icon(Icons.picture_as_pdf, size: 16),
                                    label: const Text('LIHAT PDF', style: TextStyle(fontSize: 11)),
                                    onPressed: () {
                                      Navigator.of(context).push(
                                        MaterialPageRoute(
                                          builder: (_) => PdfPreviewScreen(
                                            title: 'SURAT KETERANGAN HASIL PENELITIAN PERSONEL',
                                            documentNumber: s['nomor_skhpp'] ?? 'SKHPP-${s['id']}',
                                            recipient: s['nama'],
                                            subject: 'SKHPP ${s['peruntukan'] ?? "Kedinasan"}',
                                            verificationCode: s['verification_code'],
                                          ),
                                        ),
                                      );
                                    },
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
                  const Text('ANTREAN TTE DOKUMEN & NASKAH DINAS LAINNYA', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
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
                              Text('Status: ${r['status'] ?? 'PENDING'} | Kode: ${r['verification_code'] ?? '-'}', style: const TextStyle(fontSize: 11, color: Color(0xFF7C3AED), fontWeight: FontWeight.bold)),
                              const SizedBox(height: 10),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  OutlinedButton.icon(
                                    icon: const Icon(Icons.picture_as_pdf, size: 16),
                                    label: const Text('LIHAT PDF', style: TextStyle(fontSize: 11)),
                                    onPressed: () {
                                      Navigator.of(context).push(
                                        MaterialPageRoute(
                                          builder: (_) => PdfPreviewScreen(
                                            title: r['document_title'] ?? 'DOKUMEN KEDINASAN',
                                            documentNumber: r['letter_number'] ?? 'DOC-${r['id']}',
                                            subject: r['subject'],
                                            verificationCode: r['verification_code'],
                                          ),
                                        ),
                                      );
                                    },
                                  ),
                                  const SizedBox(width: 8),
                                  ElevatedButton.icon(
                                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF7C3AED)),
                                    icon: const Icon(Icons.place, size: 16, color: Colors.white),
                                    label: const Text('ATUR POSISI & TTE', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white)),
                                    onPressed: id != null ? () => _showSignPositionModal(id, r['subject'] ?? 'Dokumen') : null,
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