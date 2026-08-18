import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../core/constants.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  Future<String> getBaseUrl() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(AppConstants.keyBaseUrl) ?? AppConstants.defaultBaseUrl;
  }

  Future<Map<String, String>> _getHeaders({bool isMultipart = false}) async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(AppConstants.keyToken);

    return {
      'Accept': 'application/json',
      if (!isMultipart) 'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  Future<dynamic> get(String endpoint) async {
    final baseUrl = await getBaseUrl();
    final url = Uri.parse('$baseUrl$endpoint');
    final headers = await _getHeaders();

    final response = await http.get(url, headers: headers).timeout(
      const Duration(seconds: 15),
      onTimeout: () => throw Exception('Koneksi timeout. Periksa jaringan internet data Anda.'),
    );

    return _handleResponse(response);
  }

  Future<dynamic> post(String endpoint, Map<String, dynamic> data) async {
    final baseUrl = await getBaseUrl();
    final url = Uri.parse('$baseUrl$endpoint');
    final headers = await _getHeaders();

    final response = await http.post(
      url,
      headers: headers,
      body: jsonEncode(data),
    ).timeout(
      const Duration(seconds: 15),
      onTimeout: () => throw Exception('Koneksi timeout. Periksa jaringan internet data Anda.'),
    );

    return _handleResponse(response);
  }

  Future<dynamic> uploadMultipart(
    String endpoint,
    Map<String, String> fields,
    List<File> files,
    String fileFieldKey,
  ) async {
    final baseUrl = await getBaseUrl();
    final url = Uri.parse('$baseUrl$endpoint');
    final headers = await _getHeaders(isMultipart: true);

    final request = http.MultipartRequest('POST', url);
    request.headers.addAll(headers);
    request.fields.addAll(fields);

    for (var file in files) {
      if (await file.exists()) {
        request.files.add(await http.MultipartFile.fromPath(fileFieldKey, file.path));
      }
    }

    final streamedResponse = await request.send();
    final response = await http.Response.fromStream(streamedResponse);
    return _handleResponse(response);
  }

  dynamic _handleResponse(http.Response response) {
    if (response.statusCode >= 200 && response.statusCode < 300) {
      if (response.body.isEmpty) return null;
      return jsonDecode(response.body);
    } else if (response.statusCode == 401) {
      throw Exception('Sesi telah berakhir. Silakan login kembali.');
    } else {
      String errMsg = 'Terjadi kesalahan sistem ()';
      try {
        final errJson = jsonDecode(response.body);
        if (errJson['message'] != null) errMsg = errJson['message'];
      } catch (_) {}
      throw Exception(errMsg);
    }
  }
}