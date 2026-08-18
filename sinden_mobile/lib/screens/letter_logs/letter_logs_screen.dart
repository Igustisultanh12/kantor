import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../models/letter_log_model.dart';
import '../../providers/letter_provider.dart';
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

  @override
  Widget build(BuildContext context) {
    final letterProv = Provider.of<LetterProvider>(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Agenda Penomoran Surat'),
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
          // Search Box
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

          // List Items
          Expanded(
            child: letterProv.isLoading
                ? const Center(child: CircularProgressIndicator())
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
                            return _LetterLogCard(log: log);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}

class _LetterLogCard extends StatelessWidget {
  final LetterLogModel log;

  const _LetterLogCard({required this.log});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    log.fullNumber,
                    style: const TextStyle(
                      fontFamily: 'monospace',
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
                      color: AppTheme.primaryNavy,
                    ),
                  ),
                ),
                log.isArchived
                    ? StatusPill.success('Terarsip')
                    : StatusPill.warning('Pending PDF'),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              log.subject,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                const Icon(Icons.send_outlined, size: 14, color: Color(0xFF64748B)),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    'Tujuan: $log.recipient',
                    style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                  ),
                ),
                Text(
                  log.date,
                  style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}