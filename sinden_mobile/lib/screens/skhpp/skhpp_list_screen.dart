import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../models/skhpp_model.dart';
import '../../providers/skhpp_provider.dart';
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

  @override
  Widget build(BuildContext context) {
    final skhppProv = Provider.of<SkhppProvider>(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Penerbitan SKHPP'),
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
        label: const Text('Pengajuan SKHPP', style: TextStyle(fontWeight: FontWeight.w800)),
      ),
      body: skhppProv.isLoading
          ? const Center(child: CircularProgressIndicator())
          : skhppProv.skhppList.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: const [
                      Icon(Icons.verified_user_outlined, size: 48, color: Color(0xFFCBD5E1)),
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
                    padding: const EdgeInsets.only(left: 16, right: 16, top: 8, bottom: 80),
                    itemCount: skhppProv.skhppList.length,
                    itemBuilder: (context, index) {
                      final item = skhppProv.skhppList[index];
                      return _SkhppCard(item: item);
                    },
                  ),
                ),
    );
  }
}

class _SkhppCard extends StatelessWidget {
  final SkhppModel item;

  const _SkhppCard({required this.item});

  @override
  Widget build(BuildContext context) {
    StatusPill pill;
    if (item.status == 'approved') {
      pill = StatusPill.success('Tervalidasi TTE');
    } else if (item.status == 'rejected') {
      pill = StatusPill.danger('Ditolak');
    } else {
      pill = StatusPill.warning('Menunggu Approval');
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  item.noSkhpp ?? 'Draf Pengajuan',
                  style: const TextStyle(
                    fontFamily: 'monospace',
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                    color: AppTheme.primaryNavy,
                  ),
                ),
                pill,
              ],
            ),
            const SizedBox(height: 8),
            Text(
              item.nama,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
            ),
            if (item.pangkatKorpsNrp != null)
              Text(
                item.pangkatKorpsNrp!,
                style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
              ),
            const SizedBox(height: 6),
            Text(
              'Peruntukan: ',
              style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
            ),
          ],
        ),
      ),
    );
  }
}