import 'dart:async';
import 'package:geolocator/geolocator.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';
import '../core/constants.dart';

class LocationService {
  static final LocationService _instance = LocationService._internal();
  factory LocationService() => _instance;
  LocationService._internal();

  Timer? _trackingTimer;
  bool _isTracking = false;

  bool get isTracking => _isTracking;

  Future<bool> checkPermission() async {
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      return false;
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        return false;
      }
    }

    if (permission == LocationPermission.deniedForever) {
      return false;
    }

    return true;
  }

  Future<void> startTracking() async {
    final hasPerm = await checkPermission();
    if (!hasPerm) return;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(AppConstants.keyGpsTracking, true);
    _isTracking = true;

    // Send location immediately
    await _sendCurrentLocation();

    // Periodic GPS transmission every 60 seconds
    _trackingTimer?.cancel();
    _trackingTimer = Timer.periodic(const Duration(seconds: 60), (_) async {
      await _sendCurrentLocation();
    });
  }

  Future<void> stopTracking() async {
    _trackingTimer?.cancel();
    _trackingTimer = null;
    _isTracking = false;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(AppConstants.keyGpsTracking, false);
  }

  Future<void> _sendCurrentLocation() async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 10),
        ),
      );

      await ApiService().post('/update-location', {
        'latitude': position.latitude,
        'longitude': position.longitude,
        'speed': position.speed * 3.6, // km/h
      });
    } catch (_) {}
  }
}