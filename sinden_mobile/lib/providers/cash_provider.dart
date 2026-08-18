import 'dart:io';
import 'package:flutter/material.dart';
import '../models/cash_model.dart';
import '../services/api_service.dart';

class CashProvider extends ChangeNotifier {
  List<CashTransactionModel> _transactions = [];
  double _totalDebit = 0;
  double _totalCredit = 0;
  double _balance = 0;
  bool _isLoading = false;
  String? _errorMessage;

  List<CashTransactionModel> get transactions => _transactions;
  double get totalDebit => _totalDebit;
  double get totalCredit => _totalCredit;
  double get balance => _balance;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  Future<void> fetchCashData({String? startDate, String? endDate}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      String endpoint = '/technical-cash?format=json';
      if (startDate != null && endDate != null) {
        endpoint += '&start_date=$startDate&end_date=$endDate';
      }

      final response = await ApiService().get(endpoint);
      if (response != null) {
        if (response['transactions'] is List) {
          _transactions = (response['transactions'] as List)
              .map((e) => CashTransactionModel.fromJson(e))
              .toList();
        }
        _totalDebit = double.tryParse(response['total_debit']?.toString() ?? '0') ?? 0;
        _totalCredit = double.tryParse(response['total_credit']?.toString() ?? '0') ?? 0;
        _balance = double.tryParse(response['balance']?.toString() ?? '0') ?? 0;
      }
    } catch (e) {
      _errorMessage = e.toString().replaceAll('Exception: ', '');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> addTransaction({
    required String date,
    required String description,
    required double debit,
    required double credit,
    List<File>? receiptFiles,
  }) async {
    _isLoading = true;
    notifyListeners();

    try {
      if (receiptFiles != null && receiptFiles.isNotEmpty) {
        await ApiService().uploadMultipart(
          '/technical-cash',
          {
            'date': date,
            'description': description,
            'debit': debit.toString(),
            'credit': credit.toString(),
          },
          receiptFiles,
          'receipt_files[]',
        );
      } else {
        await ApiService().post('/technical-cash', {
          'date': date,
          'description': description,
          'debit': debit,
          'credit': credit,
        });
      }

      await fetchCashData();
      return true;
    } catch (e) {
      _errorMessage = e.toString().replaceAll('Exception: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }
}