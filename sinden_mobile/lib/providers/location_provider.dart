import 'package:flutter/material.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/location_service.dart';

class LocationProvider extends ChangeNotifier {
  List<UserModel> _personnelLocations = [];
  bool _isTrackingEnabled = true;
  bool _isLoading = false;

  List<UserModel> get personnelLocations => _personnelLocations;
  bool get isTrackingEnabled => _isTrackingEnabled;
  bool get isLoading => _isLoading;

  Future<void> toggleTracking() async {
    if (_isTrackingEnabled) {
      await LocationService().stopTracking();
      _isTrackingEnabled = false;
    } else {
      await LocationService().startTracking();
      _isTrackingEnabled = true;
    }
    notifyListeners();
  }

  Future<void> fetchPersonnelLocations() async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await ApiService().get('/visitor-logs?format=json');
      if (response != null && response['users'] is List) {
        _personnelLocations = (response['users'] as List)
            .map((e) => UserModel.fromJson(e))
            .where((u) => u.latitude != null && u.longitude != null)
            .toList();
      }
    } catch (_) {} finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}