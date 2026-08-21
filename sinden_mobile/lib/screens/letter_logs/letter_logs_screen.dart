import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../models/letter_log_model.dart';
import '../../providers/letter_provider.dart';
import '../../services/api_service.dart';
import '../../widgets/status_pill.dart';
import 'book_letter_dialog.dart';

class LetterLogsScreen extends StatefulWidget {
  const LetterLogsScreen({super.key});

  @override
  State<LetterLogsScreen> createState() => _LetterLogsScreenState();
}

class _LetterLogsScreenState extends State<LetterLogsScreen> {
  final _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<LetterProvider>(context, listen: false).fetchLogs();
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _previewLetter(LetterLogModel log) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: const Row(
          children: [
            Icon(Icons.picture_as_pdf, color: Color(0xFFDC2626), size: 24),
            SizedBox(width: 8),
            Expanded(child: Text('Pratinjau Agenda Surat', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15))),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(log.fullNumber, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: AppTheme.primaryNavy)),
            const SizedBox(height: 8),
            Text('Perihal: ${log.subject}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
            const SizedBox(height: 4),
            Text('Tujuan / Penerima: ${log.recipient}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
            const SizedBox(height: 4),
            Text('Tanggal: ${log.date}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(10)),
              child: const Row(
                children: [
                  Icon(Icons.verified, color: Color(0xFF059669), size: 18),
                  SizedBox(width: 8),
                  Text('Terekam di Buku Agenda Resmi Server', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF059669))),
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

  void _deleteLetter(int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Hapus Agenda Surat?', style: TextStyle(fontWeight: FontWeight.w800)),
        content: const Text('Data nomor agenda surat ini akan dihapus permanen.'),
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
      await ApiService().delete('/api/mobile/letter-logs/$id');
      if (mounted) {
        Provider.of<LetterProvider>(context, listen: false).fetchLogs();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final letterProv = Provider.of<LetterProvider>(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Agenda Penomoran Surat', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => letterProv.fetchLogs(),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          showDialog(
            context: context,
            builder: (_) => const BookLetterDialog(),
          );
        },
        backgroundColor: AppTheme.primaryNavy,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('Booking Nomor', style: TextStyle(fontWeight: FontWeight.w800)),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Cari nomor surat, perihal, atau alamat tujuan...',
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          letterProv.fetchLogs();
                        },
                      )
                    : null,
              ),
              onSubmitted: (val) => letterProv.fetchLogs(search: val),
            ),
          ),
          Expanded(
            child: letterProv.isLoading
                ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
                : letterProv.logs.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: const [
                            Icon(Icons.mark_email_unread_outlined, size: 48, color: Color(0xFFCBD5E1)),
                            SizedBox(height: 12),
                            Text(
                              'Belum Ada Agenda Surat',
                              style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF64748B)),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () => letterProv.fetchLogs(),
                        child: ListView.builder(
                          padding: const EdgeInsets.only(left: 16, right: 16, bottom: 80),
                          itemCount: letterProv.logs.length,
                          itemBuilder: (context, index) {
                            final log = letterProv.logs[index];
                            return Card(
                              margin: const EdgeInsets.only(bottom: 12),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                              child: ListTile(
                                contentPadding: const EdgeInsets.all(16),
                                leading: CircleAvatar(
                                  radius: 20,
                                  backgroundColor: AppTheme.primaryNavy.withOpacity(0.1),
                                  child: Text(
                                    '${log.number}',
                                    style: const TextStyle(fontWeight: FontWeight.w900, color: AppTheme.primaryNavy, fontSize: 13),
                                  ),
                                ),
                                title: Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        log.fullNumber,
                                        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                    StatusPill.info(log.categoryName ?? 'Umum'),
                                  ],
                                ),
                                subtitle: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const SizedBox(height: 4),
                                    Text(
                                      log.subject,
                                      style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF334155), fontSize: 12),
                                    ),
                                    const SizedBox(height: 4),
                                    Row(
                                      children: [
                                        const Icon(Icons.send_outlined, size: 12, color: Color(0xFF64748B)),
                                        const SizedBox(width: 4),
                                        Expanded(
                                          child: Text(
                                            'Kepada: ${log.recipient}',
                                            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                                trailing: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    IconButton(
                                      icon: const Icon(Icons.picture_as_pdf, color: Color(0xFFDC2626), size: 20),
                                      onPressed: () => _previewLetter(log),
                                    ),
                                    IconButton(
                                      icon: const Icon(Icons.delete_outline, color: Color(0xFF64748B), size: 20),
                                      onPressed: () => _deleteLetter(log.id),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}