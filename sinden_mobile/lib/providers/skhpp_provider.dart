import 'package:flutter/material.dart';
import '../models/skhpp_model.dart';
import '../services/api_service.dart';

class SkhppProvider extends ChangeNotifier {
  List<SkhppModel> _skhppList = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<SkhppModel> get skhppList => _skhppList;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  Future<void> fetchSkhppList({String? status, String? category}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      String endpoint = '/api/mobile/skhpp?format=json';
      if (status != null && status.isNotEmpty) endpoint += '&status=$status';
      if (category != null && category.isNotEmpty) endpoint += '&category=$category';

      final response = await ApiService().get(endpoint);
      if (response != null && response is Map<String, dynamic>) {
        final rawList = response['data'] ?? response['skhpps'];
        if (rawList is List) {
          _skhppList = rawList
              .map((e) => SkhppModel.fromJson(e as Map<String, dynamic>))
              .toList();
        }
      } else if (response is List) {
        _skhppList = response.map((e) => SkhppModel.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (e) {
      _errorMessage = e.toString().replaceAll('Exception: ', '');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> createSkhpp(Map<String, dynamic> data) async {
    _isLoading = true;
    notifyListeners();

    try {
      await ApiService().post('/api/mobile/skhpp', data);
      await fetchSkhppList();
      return true;
    } catch (e) {
      _errorMessage = e.toString().replaceAll('Exception: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }
}