import 'dart:io';
import 'package:flutter/material.dart';
import '../models/letter_log_model.dart';
import '../services/api_service.dart';

class LetterProvider extends ChangeNotifier {
  List<LetterLogModel> _logs = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<LetterLogModel> get logs => _logs;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  Future<void> fetchLogs({String? categoryId, String? search}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      String endpoint = '/api/mobile/letter-logs?format=json';
      if (categoryId != null && categoryId.isNotEmpty) {
        endpoint += '&category_id=$categoryId';
      }
      if (search != null && search.isNotEmpty) {
        endpoint += '&search=$search';
      }

      final response = await ApiService().get(endpoint);
      if (response != null && response['data'] is List) {
        _logs = (response['data'] as List)
            .map((e) => LetterLogModel.fromJson(e))
            .toList();
      } else if (response is List) {
        _logs = response.map((e) => LetterLogModel.fromJson(e)).toList();
      }
    } catch (e) {
      _errorMessage = e.toString().replaceAll('Exception: ', '');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> bookLetterNumber({
    required int categoryId,
    required String subject,
    required String recipient,
    required String date,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await ApiService().post('/api/mobile/letter-logs', {
        'category_id': categoryId,
        'subject': subject,
        'recipient': recipient,
        'date': date,
      });

      await fetchLogs();
      return true;
    } catch (e) {
      _errorMessage = e.toString().replaceAll('Exception: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> uploadDirectLetter(int logId, File pdfFile) async {
    _isLoading = true;
    notifyListeners();

    try {
      await ApiService().uploadMultipart(
        '/letters/store-direct',
        {'letter_log_id': logId.toString()},
        [pdfFile],
        'file',
      );
      await fetchLogs();
      return true;
    } catch (e) {
      _errorMessage = e.toString().replaceAll('Exception: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }
}