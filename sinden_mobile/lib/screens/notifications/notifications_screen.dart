import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/notification_provider.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final notifProv = Provider.of<NotificationProvider>(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pusat Pemberitahuan'),
        actions: [
          if (notifProv.unreadCount > 0)
            TextButton(
              onPressed: () => notifProv.markAllAsRead(),
              child: const Text('Tandai Semua Dibaca'),
            ),
        ],
      ),
      body: notifProv.notifications.isEmpty
          ? Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: const [
                  Icon(Icons.notifications_none_outlined, size: 48, color: Color(0xFFCBD5E1)),
                  SizedBox(height: 12),
                  Text(
                    'Tidak Ada Pemberitahuan Baru',
                    style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF64748B)),
                  ),
                ],
              ),
            )
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: notifProv.notifications.length,
              itemBuilder: (context, index) {
                final notif = notifProv.notifications[index];
                return Card(
                  color: notif.isRead ? Colors.white : const Color(0xFFEFF6FF),
                  child: ListTile(
                    leading: Icon(
                      Icons.notifications_active_outlined,
                      color: notif.isRead ? const Color(0xFF94A3B8) : const Color(0xFF1D4ED8),
                    ),
                    title: Text(
                      notif.title,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: notif.isRead ? FontWeight.w600 : FontWeight.w800,
                      ),
                    ),
                    subtitle: Text(
                      notif.message,
                      style: const TextStyle(fontSize: 11),
                    ),
                    trailing: notif.isRead
                        ? null
                        : IconButton(
                            icon: const Icon(Icons.check_circle_outline, size: 18),
                            onPressed: () => notifProv.markAsRead(notif.id),
                          ),
                  ),
                );
              },
            ),
    );
  }
}