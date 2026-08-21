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
    final saved = prefs.getString(AppConstants.keyBaseUrl);
    if (saved == null || saved.trim().isEmpty || !saved.trim().startsWith('http')) {
      return AppConstants.defaultBaseUrl;
    }
    String cleaned = saved.trim();
    if (cleaned.endsWith('/')) {
      cleaned = cleaned.substring(0, cleaned.length - 1);
    }
    return cleaned;
  }

  Future<Map<String, String>> _getHeaders({bool isMultipart = false}) async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(AppConstants.keyToken);

    return {
      'Accept': 'application/json',
      'User-Agent': 'SINDEN-App/1.0 (Kedinasan REST API; SINDEN Client)',
      if (!isMultipart) 'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  Future<dynamic> get(String endpoint) async {
    try {
      final baseUrl = await getBaseUrl();
      final cleanEndpoint = endpoint.startsWith('/') ? endpoint : '/$endpoint';
      final url = Uri.parse('$baseUrl$cleanEndpoint');
      final headers = await _getHeaders();

      final response = await http.get(url, headers: headers).timeout(
        const Duration(seconds: 15),
        onTimeout: () => throw Exception('Koneksi Waktu Habis (Timeout). Periksa sambungan data/internet Anda.'),
      );

      return _handleResponse(response);
    } on SocketException catch (_) {
      throw Exception('Gagal Koneksi Jaringan. Periksa sambungan kuota/WiFi HP Anda.');
    } on HttpException catch (_) {
      throw Exception('Protokol Layanan Server Mengalami Gangguan.');
    } on FormatException catch (_) {
      throw Exception('Format Data Server Tidak Sesuai.');
    } catch (e) {
      if (e.toString().contains('SocketException') || e.toString().contains('ClientException')) {
        throw Exception('Gagal Menghubungkan ke Server (Offline). Coba beberapa saat lagi.');
      }
      rethrow;
    }
  }

  Future<dynamic> post(String endpoint, Map<String, dynamic> data) async {
    try {
      final baseUrl = await getBaseUrl();
      final cleanEndpoint = endpoint.startsWith('/') ? endpoint : '/$endpoint';
      final url = Uri.parse('$baseUrl$cleanEndpoint');
      final headers = await _getHeaders();

      final req = http.Request('POST', url);
      req.headers.addAll(headers);
      req.body = jsonEncode(data);
      req.followRedirects = false;

      final client = http.Client();
      final streamed = await client.send(req).timeout(
        const Duration(seconds: 15),
        onTimeout: () => throw Exception('Koneksi Waktu Habis (Timeout). Periksa sambungan data/internet Anda.'),
      );
      var response = await http.Response.fromStream(streamed);

      if (response.statusCode == 301 || response.statusCode == 302 || response.statusCode == 307 || response.statusCode == 308) {
        final redirectUrl = response.headers['location'];
        if (redirectUrl != null && redirectUrl.trim().isNotEmpty) {
          final targetUri = Uri.parse(redirectUrl.trim());
          final req2 = http.Request('POST', targetUri);
          req2.headers.addAll(headers);
          req2.body = jsonEncode(data);
          req2.followRedirects = false;

          final streamed2 = await client.send(req2).timeout(const Duration(seconds: 15));
          response = await http.Response.fromStream(streamed2);
        }
      }

      client.close();
      return _handleResponse(response);
    } on SocketException catch (_) {
      throw Exception('Gagal Koneksi Jaringan. Periksa sambungan kuota/WiFi HP Anda.');
    } on HttpException catch (_) {
      throw Exception('Protokol Layanan Server Mengalami Gangguan.');
    } catch (e) {
      if (e.toString().contains('SocketException') || e.toString().contains('ClientException')) {
        throw Exception('Gagal Menghubungkan ke Server (Offline). Coba beberapa saat lagi.');
      }
      rethrow;
    }
  }

  Future<dynamic> put(String endpoint, Map<String, dynamic> data) async {
    try {
      final baseUrl = await getBaseUrl();
      final cleanEndpoint = endpoint.startsWith('/') ? endpoint : '/$endpoint';
      final url = Uri.parse('$baseUrl$cleanEndpoint');
      final headers = await _getHeaders();

      final req = http.Request('PUT', url);
      req.headers.addAll(headers);
      req.body = jsonEncode(data);
      req.followRedirects = false;

      final client = http.Client();
      final streamed = await client.send(req).timeout(const Duration(seconds: 15));
      final response = await http.Response.fromStream(streamed);
      client.close();

      return _handleResponse(response);
    } catch (e) {
      rethrow;
    }
  }

  Future<dynamic> delete(String endpoint) async {
    try {
      final baseUrl = await getBaseUrl();
      final cleanEndpoint = endpoint.startsWith('/') ? endpoint : '/$endpoint';
      final url = Uri.parse('$baseUrl$cleanEndpoint');
      final headers = await _getHeaders();

      final req = http.Request('DELETE', url);
      req.headers.addAll(headers);
      req.followRedirects = false;

      final client = http.Client();
      final streamed = await client.send(req).timeout(const Duration(seconds: 15));
      final response = await http.Response.fromStream(streamed);
      client.close();

      return _handleResponse(response);
    } catch (e) {
      rethrow;
    }
  }

  dynamic _handleResponse(http.Response response) {
    if (response.statusCode >= 200 && response.statusCode < 300) {
      if (response.body.isEmpty) return {'status': 'success'};
      try {
        return jsonDecode(response.body);
      } catch (_) {
        return {'status': 'success', 'data': response.body};
      }
    } else {
      String msg = 'Gagal memproses data (${response.statusCode})';
      try {
        final decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic> && decoded.containsKey('message')) {
          msg = decoded['message'];
        }
      } catch (_) {}
      throw Exception(msg);
    }
  }
}