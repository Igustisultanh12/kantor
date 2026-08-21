import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';

class QrVerificationScreen extends StatefulWidget {
  const QrVerificationScreen({super.key});

  @override
  State<QrVerificationScreen> createState() => _QrVerificationScreenState();
}

class _QrVerificationScreenState extends State<QrVerificationScreen> {
  final _codeController = TextEditingController();
  bool _isChecking = false;
  Map<String, dynamic>? _result;

  Future<void> _verifyCode(String rawInput) async {
    final code = rawInput.trim().split('/').last.trim();
    if (code.isEmpty) return;

    setState(() {
      _isChecking = true;
      _result = null;
    });

    try {
      final res = await ApiService().get('/api/mobile/verify-signature?code=$code');
      if (res != null && res is Map<String, dynamic>) {
        setState(() => _result = res);
      } else {
        setState(() => _result = {'status': 'invalid', 'valid': false, 'message': 'Kode tidak ditemukan.'});
      }
    } catch (e) {
      setState(() => _result = {'status': 'invalid', 'valid': false, 'message': e.toString()});
    }

    setState(() => _isChecking = false);
  }

  @override
  Widget build(BuildContext context) {
    final isValid = _result?['valid'] == true;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Verifikasi Keaslian TTE', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            // Scanner Header Card
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF0F172A), Color(0xFF1E3A8A)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(24),
                boxShadow: [
                  BoxShadow(color: const Color(0xFF1E3A8A).withOpacity(0.3), blurRadius: 16, offset: const Offset(0, 8))
                ],
              ),
              child: Column(
                children: [
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.1),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.qr_code_scanner, size: 56, color: Color(0xFFFBBF24)),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'VALIDATOR TTE DIGITAL KOMANDAN',
                    style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w900, letterSpacing: 0.8),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Pindai QR Code atau masukkan kode TTE dokumen untuk memverifikasi keabsahan tanda tangan digital.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 11, height: 1.4),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Input Code / URL
            TextField(
              controller: _codeController,
              decoration: InputDecoration(
                labelText: 'Kode Verifikasi / URL TTE',
                hintText: 'Contoh: DOC-A1B2C3D4E5 atau tempel tautan TTE',
                prefixIcon: const Icon(Icons.security, color: AppTheme.primaryNavy),
                suffixIcon: IconButton(
                  icon: const Icon(Icons.search, color: AppTheme.primaryNavy),
                  onPressed: () => _verifyCode(_codeController.text),
                ),
              ),
              onSubmitted: _verifyCode,
            ),
            const SizedBox(height: 14),

            SizedBox(
              width: double.infinity,
              height: 48,
              child: ElevatedButton.icon(
                style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primaryNavy),
                icon: _isChecking
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Icon(Icons.verified_user, color: Colors.white),
                label: const Text('PERIKSA KEASLIAN SEKARANG', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white)),
                onPressed: _isChecking ? null : () => _verifyCode(_codeController.text),
              ),
            ),
            const SizedBox(height: 24),

            // Verification Result Card
            if (_result != null)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: isValid ? const Color(0xFFF0FDF4) : const Color(0xFFFEF2F2),
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: isValid ? const Color(0xFF86EFAC) : const Color(0xFFFCA5A5), width: 1.5),
                  boxShadow: [
                    BoxShadow(color: (isValid ? Colors.green : Colors.red).withOpacity(0.1), blurRadius: 12, offset: const Offset(0, 4))
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(isValid ? Icons.check_circle : Icons.warning_amber_rounded, size: 28, color: isValid ? const Color(0xFF16A34A) : const Color(0xFFDC2626)),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            isValid ? 'DOKUMEN DINAS ASLI & TERVERIFIKASI' : 'PERINGATAN: TTE TIDAK VALID / PALSU',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w900,
                              color: isValid ? const Color(0xFF15803D) : const Color(0xFFB91C1C),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 24),
                    if (isValid) ...[
                      _buildInfoRow('Jenis Dokumen', _result!['document_type'] ?? '-'),
                      _buildInfoRow('Judul / Perihal', _result!['title'] ?? '-'),
                      _buildInfoRow('Nomor Registrasi', _result!['number'] ?? '-'),
                      _buildInfoRow('Nama Personel', _result!['person_name'] ?? '-'),
                      _buildInfoRow('Pangkat / NRP', _result!['pangkat_nrp'] ?? '-'),
                      _buildInfoRow('Penandatangan', _result!['signed_by'] ?? 'KOMANDAN DENINTEL'),
                      _buildInfoRow('Waktu Pengesahan', _result!['signed_at'] ?? '-'),
                      _buildInfoRow('Kode Verifikasi', _result!['verification_code'] ?? '-'),
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(8)),
                        child: Text(
                          'SHA-256 Hash: ${_result!['hash_sha256'] ?? '-'}',
                          style: const TextStyle(fontSize: 8, color: Color(0xFF64748B), fontFamily: 'monospace'),
                        ),
                      ),
                    ] else ...[
                      Text(
                        _result!['message'] ?? 'Kode TTE ini tidak terdaftar dalam pangkalan data resmi SINDEN atau berkas telah mengalami manipulasi/pemalsuan.',
                        style: const TextStyle(fontSize: 12, color: Color(0xFF991B1B), height: 1.4, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 110, child: Text(label, style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.bold, color: Color(0xFF64748B)))),
          const Text(': ', style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.bold)),
          Expanded(child: Text(value, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)))),
        ],
      ),
    );
  }
}