import 'package:flutter/material.dart';
import '../models/notification_model.dart';
import '../services/api_service.dart';
import '../services/notification_service.dart';

class NotificationProvider extends ChangeNotifier {
  List<AppNotificationModel> _notifications = [];
  int _unreadCount = 0;
  bool _isLoading = false;

  List<AppNotificationModel> get notifications => _notifications;
  int get unreadCount => _unreadCount;
  bool get isLoading => _isLoading;

  Future<void> fetchNotifications() async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await ApiService().get('/api/notifications');
      if (response != null && response['notifications'] is List) {
        final List<AppNotificationModel> fetched = (response['notifications'] as List)
            .map((e) => AppNotificationModel.fromJson(e))
            .toList();

        // Check if new unread notification arrived -> trigger audible alarm/sound
        final newUnread = fetched.where((n) => !n.isRead).toList();
        if (newUnread.length > _unreadCount && newUnread.isNotEmpty) {
          final latest = newUnread.first;
          NotificationService().showNotification(
            id: latest.id,
            title: latest.title,
            body: latest.message,
          );
        }

        _notifications = fetched;
        _unreadCount = response['unread_count'] ?? newUnread.length;
      }
    } catch (_) {} finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> markAsRead(int id) async {
    try {
      await ApiService().post('/notifications/$id/read', {});
      await fetchNotifications();
    } catch (_) {}
  }

  Future<void> markAllAsRead() async {
    try {
      await ApiService().post('/notifications/read-all', {});
      await fetchNotifications();
    } catch (_) {}
  }
}