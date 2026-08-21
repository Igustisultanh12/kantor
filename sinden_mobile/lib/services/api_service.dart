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
      'User-Agent': 'Mozilla/5.0 (Linux; Android 13; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 SINDEN-Mobile/1.0',
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

      final response = await http.post(
        url,
        headers: headers,
        body: jsonEncode(data),
      ).timeout(
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

  Future<dynamic> uploadMultipart(
    String endpoint,
    Map<String, String> fields,
    List<File> files,
    String fileFieldKey,
  ) async {
    try {
      final baseUrl = await getBaseUrl();
      final cleanEndpoint = endpoint.startsWith('/') ? endpoint : '/$endpoint';
      final url = Uri.parse('$baseUrl$cleanEndpoint');
      final headers = await _getHeaders(isMultipart: true);

      final request = http.MultipartRequest('POST', url);
      request.headers.addAll(headers);
      request.fields.addAll(fields);

      for (var file in files) {
        if (await file.exists()) {
          request.files.add(await http.MultipartFile.fromPath(fileFieldKey, file.path));
        }
      }

      final streamedResponse = await request.send().timeout(
        const Duration(seconds: 30),
        onTimeout: () => throw Exception('Unggah Berkas Waktu Habis. Periksa jaringan Anda.'),
      );
      final response = await http.Response.fromStream(streamedResponse);
      return _handleResponse(response);
    } on SocketException catch (_) {
      throw Exception('Gagal Unggah: Jaringan terputus.');
    } catch (e) {
      rethrow;
    }
  }

  dynamic _handleResponse(http.Response response) {
    if (response.body.contains('Just a moment') || response.body.contains('challenge-platform') || response.body.contains('cf-mitigated')) {
      throw Exception('Akses Terhadang Proteksi Cloudflare. Silakan atur Cloudflare WAF Security Rule pada domain sisinden.my.id.');
    }
    if (response.statusCode >= 200 && response.statusCode < 300) {
      if (response.body.isEmpty) return null;
      try {
        return jsonDecode(response.body);
      } catch (_) {
        return response.body;
      }
    } else if (response.statusCode == 401) {
      throw Exception('Sesi Akses Telah Berakhir. Silakan Login Kembali.');
    } else if (response.statusCode == 403) {
      String msg = 'Akses Ditolak. Akun belum aktif atau dibekukan oleh Admin.';
      try {
        final err = jsonDecode(response.body);
        if (err['message'] != null) msg = err['message'];
      } catch (_) {}
      throw Exception(msg);
    } else if (response.statusCode == 404) {
      throw Exception('Layanan Server Tidak Ditemukan (404).');
    } else if (response.statusCode >= 500) {
      throw Exception('Server Mengalami Kendala Internal (${response.statusCode}). Silakan Coba Beberapa Saat Lagi.');
    } else {
      String errMsg = 'Terjadi Kesalahan (${response.statusCode})';
      try {
        final errJson = jsonDecode(response.body);
        if (errJson['message'] != null && errJson['message'].toString().isNotEmpty) {
          errMsg = errJson['message'];
        } else if (errJson['errors'] != null) {
          final errors = errJson['errors'] as Map<String, dynamic>;
          if (errors.isNotEmpty) {
            final firstErrList = errors.values.first;
            if (firstErrList is List && firstErrList.isNotEmpty) {
              errMsg = firstErrList.first.toString();
            } else {
              errMsg = firstErrList.toString();
            }
          }
        }
      } catch (_) {}
      throw Exception(errMsg);
    }
  }
}