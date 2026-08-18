import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../providers/location_provider.dart';

class PersonnelMapScreen extends StatefulWidget {
  const PersonnelMapScreen({super.key});

  @override
  State<PersonnelMapScreen> createState() => _PersonnelMapScreenState();
}

class _PersonnelMapScreenState extends State<PersonnelMapScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<LocationProvider>(context, listen: false).fetchPersonnelLocations();
    });
  }

  @override
  Widget build(BuildContext context) {
    final locProv = Provider.of<LocationProvider>(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Radar GPS Personel Realtime'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => locProv.fetchPersonnelLocations(),
          ),
        ],
      ),
      body: locProv.isLoading
          ? const Center(child: CircularProgressIndicator())
          : locProv.personnelLocations.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: const [
                      Icon(Icons.location_off_outlined, size: 48, color: Color(0xFFCBD5E1)),
                      SizedBox(height: 12),
                      Text(
                        'Belum Ada Sinyal GPS Personel Terdeteksi',
                        style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF64748B)),
                      ),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: locProv.personnelLocations.length,
                  itemBuilder: (context, index) {
                    final p = locProv.personnelLocations[index];
                    return Card(
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: AppTheme.primaryNavy.withOpacity(0.1),
                          child: const Icon(Icons.navigation, color: AppTheme.primaryNavy, size: 20),
                        ),
                        title: Text(
                          p.name,
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                        ),
                        subtitle: Text(
                          'Koordinat: , ',
                          style: const TextStyle(fontSize: 11, fontFamily: 'monospace'),
                        ),
                        trailing: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFDCFCE7),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Text(
                            'ONLINE',
                            style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: Color(0xFF15803D)),
                          ),
                        ),
                      ),
                    );
                  },
                ),
    );
  }
}