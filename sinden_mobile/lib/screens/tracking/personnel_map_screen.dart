import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../providers/location_provider.dart';
import '../../services/api_service.dart';

class PersonnelMapScreen extends StatefulWidget {
  const PersonnelMapScreen({super.key});

  @override
  State<PersonnelMapScreen> createState() => _PersonnelMapScreenState();
}

class _PersonnelMapScreenState extends State<PersonnelMapScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _isLoadingActivities = true;
  List<dynamic> _activities = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _fetchActivities();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<LocationProvider>(context, listen: false).fetchPersonnelLocations();
    });
  }

  Future<void> _fetchActivities() async {
    setState(() => _isLoadingActivities = true);
    try {
      final res = await ApiService().get('/api/mobile/activities');
      if (res != null && res is Map<String, dynamic>) {
        setState(() {
          _activities = res['data'] ?? res['activities'] ?? [];
        });
      }
    } catch (_) {}
    setState(() => _isLoadingActivities = false);
  }

  void _showAddActivityDialog() {
    final formKey = GlobalKey<FormState>();
    final titleController = TextEditingController();
    final locController = TextEditingController();
    final descController = TextEditingController();
    final dateController = TextEditingController(text: DateTime.now().toString().substring(0, 10));
    String category = 'Pengamanan';

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          title: const Text('Lapor Kegiatan Lapangan', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
          content: Form(
            key: formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextFormField(
                    controller: titleController,
                    decoration: InputDecoration(labelText: 'Nama / Judul Kegiatan', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    validator: (v) => v == null || v.isEmpty ? 'Judul wajib diisi' : null,
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    value: category,
                    decoration: InputDecoration(labelText: 'Kategori Kegiatan', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    items: const [
                      DropdownMenuItem(value: 'Pengamanan', child: Text('Pengamanan Wilayah (Pam)')),
                      DropdownMenuItem(value: 'Penyelidikan', child: Text('Penyelidikan (Lid)')),
                      DropdownMenuItem(value: 'Penggalangan', child: Text('Penggalangan (Gal)')),
                      DropdownMenuItem(value: 'Sosial', child: Text('Bakti Sosial / Teritorial')),
                    ],
                    onChanged: (v) => setModalState(() => category = v!),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: locController,
                    decoration: InputDecoration(labelText: 'Lokasi / Sasaran Wilayah', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    validator: (v) => v == null || v.isEmpty ? 'Lokasi wajib diisi' : null,
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: descController,
                    maxLines: 2,
                    decoration: InputDecoration(labelText: 'Uraian Hasil Kegiatan Lapangan', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                    validator: (v) => v == null || v.isEmpty ? 'Uraian wajib diisi' : null,
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: dateController,
                    decoration: InputDecoration(labelText: 'Tanggal Kegiatan (YYYY-MM-DD)', border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('BATAL')),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF3B82F6), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
              onPressed: () async {
                if (formKey.currentState!.validate()) {
                  await ApiService().post('/api/mobile/activities', {
                    'title': titleController.text.trim(),
                    'category': category,
                    'location_name': locController.text.trim(),
                    'description': descController.text.trim(),
                    'activity_date': dateController.text.trim(),
                  });
                  Navigator.of(ctx).pop();
                  _fetchActivities();
                }
              },
              child: const Text('KIRIM LAPORAN', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white)),
            ),
          ],
        ),
      ),
    );
  }

  void _deleteActivity(int id) async {
    await ApiService().delete('/api/mobile/activities/$id');
    _fetchActivities();
  }

  @override
  Widget build(BuildContext context) {
    final loc = Provider.of<LocationProvider>(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Radar Kegiatan Lapangan', style: TextStyle(fontWeight: FontWeight.w900)),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: AppTheme.primaryNavy,
          labelColor: AppTheme.primaryNavy,
          unselectedLabelColor: const Color(0xFF64748B),
          tabs: const [
            Tab(icon: Icon(Icons.radar), text: 'Radar Personel'),
            Tab(icon: Icon(Icons.list_alt), text: 'Laporan Kegiatan'),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF2563EB),
        icon: const Icon(Icons.add_location_alt, color: Colors.white),
        label: const Text('LAPOR KEGIATAN', style: TextStyle(fontWeight: FontWeight.w900, color: Colors.white, letterSpacing: 0.5)),
        onPressed: _showAddActivityDialog,
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          // Tab 1: Radar Personel
          ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFF0F172A),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Column(
                  children: [
                    const Icon(Icons.gps_fixed, size: 48, color: Color(0xFF22C55E)),
                    const SizedBox(height: 10),
                    const Text('RADAR GPS PERSONEL AKTIF', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 13)),
                    const SizedBox(height: 4),
                    Text('Total Personel Terdeteksi: ${loc.personnelLocations.length}', style: const TextStyle(color: Color(0xFF86EFAC), fontSize: 11)),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              ...loc.personnelLocations.map((p) => Card(
                margin: const EdgeInsets.only(bottom: 8),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                child: ListTile(
                  leading: const CircleAvatar(backgroundColor: Color(0xFFDCFCE7), child: Icon(Icons.person_pin_circle, color: Color(0xFF16A34A))),
                  title: Text(p.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  subtitle: Text('Posisi: ${p.latitude?.toStringAsFixed(4)}, ${p.longitude?.toStringAsFixed(4)}', style: const TextStyle(fontSize: 10, color: Color(0xFF64748B))),
                ),
              )).toList(),
            ],
          ),

          // Tab 2: Laporan Kegiatan
          _isLoadingActivities
              ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
              : RefreshIndicator(
                  onRefresh: _fetchActivities,
                  child: ListView(
                    padding: const EdgeInsets.only(left: 16, right: 16, top: 16, bottom: 80),
                    children: [
                      if (_activities.isEmpty)
                        const Padding(padding: EdgeInsets.symmetric(vertical: 32), child: Center(child: Text('Belum ada laporan kegiatan lapangan.', style: TextStyle(color: Color(0xFF64748B)))))
                      else
                        ..._activities.map((a) {
                          final id = a['id'];
                          return Card(
                            margin: const EdgeInsets.only(bottom: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Expanded(
                                        child: Text(a['title'] ?? 'Kegiatan Lapangan', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
                                      ),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                        decoration: BoxDecoration(color: const Color(0xFFDBEAFE), borderRadius: BorderRadius.circular(6)),
                                        child: Text(a['category'] ?? 'Pengamanan', style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: Color(0xFF1E3A8A))),
                                      ),
                                      if (id != null)
                                        IconButton(
                                          icon: const Icon(Icons.delete_outline, color: Color(0xFFDC2626), size: 18),
                                          onPressed: () => _deleteActivity(id),
                                        ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Text('Lokasi: ${a['location_name'] ?? '-'} | Tanggal: ${a['activity_date'] ?? '-'}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600)),
                                  const Divider(height: 16),
                                  Text(a['description'] ?? '-', style: const TextStyle(fontSize: 12, color: Color(0xFF334155), height: 1.4)),
                                ],
                              ),
                            ),
                          );
                        }).toList(),
                    ],
                  ),
                ),
        ],
      ),
    );
  }
}