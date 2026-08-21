import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../models/skhpp_model.dart';
import '../../providers/skhpp_provider.dart';
import '../../services/api_service.dart';
import '../../widgets/status_pill.dart';
import 'skhpp_form_screen.dart';

class SkhppListScreen extends StatefulWidget {
  const SkhppListScreen({super.key});

  @override
  State<SkhppListScreen> createState() => _SkhppListScreenState();
}

class _SkhppListScreenState extends State<SkhppListScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<SkhppProvider>(context, listen: false).fetchSkhppList();
    });
  }

  void _previewSkhppPdf(SkhppModel skhpp) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: const Row(
          children: [
            Icon(Icons.picture_as_pdf, color: Color(0xFFDC2626), size: 24),
            SizedBox(width: 8),
            Expanded(child: Text('Pratinjau Dokumen SKHPP', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15))),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(skhpp.noSkhpp ?? 'SKHPP', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: AppTheme.primaryNavy)),
            const SizedBox(height: 8),
            Text('Nama Personel: ${skhpp.nama}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
            Text('Pangkat/Korps/NRP: ${skhpp.pangkatKorpsNrp ?? '-'}', style: const TextStyle(fontSize: 11, color: Color(0xFF334155))),
            Text('Jabatan: ${skhpp.jabatan ?? '-'}', style: const TextStyle(fontSize: 11, color: Color(0xFF334155))),
            Text('Peruntukan: ${skhpp.peruntukan ?? '-'}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(10)),
              child: Column(
                children: [
                  const Icon(Icons.qr_code_2, size: 60, color: AppTheme.primaryNavy),
                  const SizedBox(height: 6),
                  Text('Status: ${skhpp.status.toUpperCase()}', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: skhpp.isApproved ? const Color(0xFF059669) : const Color(0xFFD97706))),
                ],
              ),
            ),
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

  void _verifySkhpp(int id) async {
    await ApiService().post('/api/mobile/skhpp/$id/verify', {});
    if (mounted) {
      Provider.of<SkhppProvider>(context, listen: false).fetchSkhppList();
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('SKHPP berhasil diverifikasi & disahkan')));
    }
  }

  void _deleteSkhpp(int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Hapus SKHPP?', style: TextStyle(fontWeight: FontWeight.w800)),
        content: const Text('Data pengajuan SKHPP ini akan dihapus permanen.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('BATAL')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFDC2626)),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('HAPUS', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirm == true) {
      await ApiService().delete('/api/mobile/skhpp/$id');
      if (mounted) {
        Provider.of<SkhppProvider>(context, listen: false).fetchSkhppList();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final skhppProv = Provider.of<SkhppProvider>(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Penerbitan SKHPP', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => skhppProv.fetchSkhppList(),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const SkhppFormScreen()),
          );
        },
        backgroundColor: AppTheme.secondaryGold,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('Buat SKHPP Baru', style: TextStyle(fontWeight: FontWeight.w800)),
      ),
      body: skhppProv.isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : skhppProv.skhppList.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: const [
                      Icon(Icons.folder_open_outlined, size: 48, color: Color(0xFFCBD5E1)),
                      SizedBox(height: 12),
                      Text(
                        'Belum Ada Pengajuan SKHPP',
                        style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF64748B)),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: () => skhppProv.fetchSkhppList(),
                  child: ListView.builder(
                    padding: const EdgeInsets.only(left: 16, right: 16, top: 16, bottom: 80),
                    itemCount: skhppProv.skhppList.length,
                    itemBuilder: (context, index) {
                      final skhpp = skhppProv.skhppList[index];
                      return Card(
                        margin: const EdgeInsets.only(bottom: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      skhpp.noSkhpp ?? 'SKHPP',
                                      style: const TextStyle(fontWeight: FontWeight.w900, color: AppTheme.primaryNavy, fontSize: 13),
                                    ),
                                  ),
                                  if (skhpp.isApproved)
                                    StatusPill.success(skhpp.status)
                                  else if (skhpp.isRejected)
                                    StatusPill.danger(skhpp.status)
                                  else
                                    StatusPill.warning(skhpp.status),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                skhpp.nama,
                                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF0F172A)),
                              ),
                              Text(
                                '${skhpp.pangkatKorpsNrp ?? '-'} - ${skhpp.jabatan ?? '-'}',
                                style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                'Peruntukan: ${skhpp.peruntukan ?? '-'}',
                                style: const TextStyle(fontSize: 11, color: Color(0xFF334155), fontStyle: FontStyle.italic),
                              ),
                              const Divider(height: 18),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    skhpp.tanggalSurat ?? '',
                                    style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8), fontWeight: FontWeight.w600),
                                  ),
                                  Row(
                                    children: [
                                      OutlinedButton.icon(
                                        icon: const Icon(Icons.picture_as_pdf, size: 14, color: Color(0xFFDC2626)),
                                        label: const Text('PDF', style: TextStyle(fontSize: 11)),
                                        onPressed: () => _previewSkhppPdf(skhpp),
                                      ),
                                      if (!skhpp.isApproved) ...[
                                        const SizedBox(width: 6),
                                        ElevatedButton.icon(
                                          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF059669)),
                                          icon: const Icon(Icons.check, size: 14, color: Colors.white),
                                          label: const Text('VERIFIKASI', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.white)),
                                          onPressed: () => _verifySkhpp(skhpp.id),
                                        ),
                                      ],
                                      const SizedBox(width: 6),
                                      IconButton(
                                        icon: const Icon(Icons.delete_outline, color: Color(0xFFDC2626), size: 18),
                                        onPressed: () => _deleteSkhpp(skhpp.id),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}