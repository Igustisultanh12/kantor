import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../core/constants.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/location_service.dart';

class AuthProvider extends ChangeNotifier {
  UserModel? _user;
  String? _token;
  bool _isLoading = false;
  String? _errorMessage;

  UserModel? get user => _user;
  String? get token => _token;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  bool get isAuthenticated => _token != null && _token!.isNotEmpty;

  Future<void> checkAuthStatus() async {
    _isLoading = true;
    notifyListeners();

    try {
      final prefs = await SharedPreferences.getInstance();
      _token = prefs.getString(AppConstants.keyToken);
      final userJson = prefs.getString(AppConstants.keyUser);

      if (_token != null && userJson != null) {
        _user = UserModel.fromJson(jsonDecode(userJson));
        // Start background GPS tracking if authenticated
        LocationService().startTracking();
      }
    } catch (_) {
      _token = null;
      _user = null;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> login(String nrpOrEmail, String password) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await ApiService().post('/login', {
        'username': nrpOrEmail,
        'password': password,
      });

      if (response != null && response['token'] != null) {
        _token = response['token'];
        _user = UserModel.fromJson(response['user']);

        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(AppConstants.keyToken, _token!);
        await prefs.setString(AppConstants.keyUser, jsonEncode(_user!.toJson()));

        LocationService().startTracking();
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        throw Exception('Kredensial login tidak valid.');
      }
    } catch (e) {
      _errorMessage = e.toString().replaceAll('Exception: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    try {
      await ApiService().post('/logout', {});
    } catch (_) {}

    LocationService().stopTracking();
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(AppConstants.keyToken);
    await prefs.remove(AppConstants.keyUser);

    _token = null;
    _user = null;
    notifyListeners();
  }
}