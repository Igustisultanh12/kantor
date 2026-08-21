import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../core/constants.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/location_service.dart';

class AuthProvider with ChangeNotifier {
  UserModel? _user;
  String? _token;
  bool _isInitialLoading = true;
  bool _isLoading = false;
  String? _errorMessage;

  UserModel? get user => _user;
  String? get token => _token;
  bool get isInitialLoading => _isInitialLoading;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  bool get isAuthenticated => _token != null && _token!.isNotEmpty;

  AuthProvider() {
    _loadAuthData();
  }

  Future<void> checkAuthStatus() async {
    await _loadAuthData();
  }

  Future<void> _loadAuthData() async {
    _isInitialLoading = true;
    notifyListeners();

    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(AppConstants.keyToken);
    final userStr = prefs.getString(AppConstants.keyUser);
    if (userStr != null) {
      try {
        _user = UserModel.fromJson(jsonDecode(userStr));
      } catch (_) {}
    }

    _isInitialLoading = false;
    notifyListeners();
  }

  Future<bool> login(String nrpOrEmail, String password) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await ApiService().post('/api/mobile/login', {
        'username': nrpOrEmail,
        'email': nrpOrEmail,
        'password': password,
      });

      if (response != null && response['user'] != null) {
        _token = response['token'] ?? 'sinden_auth_token_active';
        _user = UserModel.fromJson(response['user']);

        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(AppConstants.keyToken, _token!);
        await prefs.setString(AppConstants.keyUser, jsonEncode(_user!.toJson()));

        LocationService().startTracking();
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        throw Exception(response?['message'] ?? 'Respons login tidak valid dari server.');
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

    _token = null;
    _user = null;
    LocationService().stopTracking();

    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(AppConstants.keyToken);
    await prefs.remove(AppConstants.keyUser);

    notifyListeners();
  }
}