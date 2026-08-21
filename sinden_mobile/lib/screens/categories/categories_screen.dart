import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';

class CategoriesScreen extends StatefulWidget {
  const CategoriesScreen({super.key});

  @override
  State<CategoriesScreen> createState() => _CategoriesScreenState();
}

class _CategoriesScreenState extends State<CategoriesScreen> {
  bool _isLoading = true;
  List<dynamic> _categories = [];

  @override
  void initState() {
    super.initState();
    _fetchCategories();
  }

  Future<void> _fetchCategories() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().get('/api/mobile/categories');
      if (res != null && res is Map<String, dynamic>) {
        setState(() {
          _categories = res['data'] ?? [];
        });
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Kategori Surat', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchCategories),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(strokeCap: StrokeCap.round))
          : RefreshIndicator(
              onRefresh: _fetchCategories,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const Text('MASTER KLASIFIKASI & KODE ARSIP SURAT', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Color(0xFF64748B), letterSpacing: 0.8)),
                  const SizedBox(height: 12),
                  ..._categories.map((c) {
                    return Card(
                      margin: const EdgeInsets.only(bottom: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      child: ListTile(
                        leading: const CircleAvatar(
                          backgroundColor: Color(0xFFE0F2FE),
                          child: Icon(Icons.folder, color: Color(0xFF0284C7)),
                        ),
                        title: Text(c['name'] ?? '-', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                        subtitle: Text('Kode: ${c['code'] ?? '-'} | Nomor Mulai: ${c['start_number'] ?? 1}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                      ),
                    );
                  }).toList(),
                ],
              ),
            ),
    );
  }
}