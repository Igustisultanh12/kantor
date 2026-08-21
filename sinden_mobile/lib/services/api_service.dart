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

      final req = http.Request('POST', url);
      req.headers.addAll(headers);
      req.body = jsonEncode(data);
      req.followRedirects = false; // AGAR TIDAK SILENT REDIRECT 302 KE HALAMAN LOGIN WEB!

      final client = http.Client();
      final streamed = await client.send(req).timeout(
        const Duration(seconds: 15),
        onTimeout: () => throw Exception('Koneksi Waktu Habis (Timeout). Periksa sambungan data/internet Anda.'),
      );
      var response = await http.Response.fromStream(streamed);

      // OTOMATIS IKUTI PENGALIHAN 301/302 METODE POST KE TARGET REDIRECT (MISAL m.sisinden.my.id)
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
    } on SocketException catch (se) {
      throw Exception('Gagal Koneksi Jaringan (SocketException): ' + se.message);
    } on FormatException catch (fe) {
      throw Exception('Format Data Server Tidak Sesuai (FormatException): ' + fe.message);
    } catch (e) {
      if (e.toString().contains('SocketException') || e.toString().contains('ClientException')) {
        throw Exception('Gagal Menghubungkan ke Server (Offline / Unreachable): ' + e.toString());
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
    final reqUrl = response.request?.url.toString() ?? 'URL Tidak Diketahui';
    final code = response.statusCode;
    final bodySnippet = response.body.length > 250 ? response.body.substring(0, 250) + '...' : response.body;

    if (response.body.contains('Just a moment') || response.body.contains('challenge-platform') || response.body.contains('cf-mitigated')) {
      throw Exception('TERHADANG CLOUDFLARE WAF\n[HTTP $code] $reqUrl\nDetail: Cloudflare mencegat koneksi HP dengan tantangan keamanan.');
    }

    if (code >= 200 && code < 300) {
      if (response.body.isEmpty) return null;
      try {
        return jsonDecode(response.body);
      } catch (e) {
        throw Exception('GAGAL PARSE JSON [HTTP $code]\nEndpoint: $reqUrl\nError: $e\nBody Mentah: $bodySnippet');
      }
    } else if (code == 301 || code == 302 || code == 303 || code == 307 || code == 308) {
      final loc = response.headers['location'] ?? 'Location Header Kosong';
      throw Exception('TERJADI PENGALIHAN HALAMAN SERVER [HTTP $code REDIRECT]\nEndpoint Awal: $reqUrl\nTarget Redirect: $loc\nDetail: Server meminta HP dialihkan ke URL lain (Bukan REST API JSON).');
    } else if (code == 401) {
      String msg = 'Kredensial tidak cocok. Silakan periksa NRP / Email dan Kata Sandi Anda.';
      try {
        final err = jsonDecode(response.body);
        if (err['message'] != null) msg = err['message'];
      } catch (_) {}
      throw Exception('GAGAL AUTENTIKASI [HTTP 401]\nEndpoint: $reqUrl\nPesan Server: $msg');
    } else if (code == 403) {
      String msg = 'Akses Ditolak. Akun belum aktif atau dibekukan oleh Admin.';
      try {
        final err = jsonDecode(response.body);
        if (err['message'] != null) msg = err['message'];
      } catch (_) {}
      throw Exception('AKSES DITOLAK [HTTP 403]\nEndpoint: $reqUrl\nPesan Server: $msg');
    } else if (code == 404) {
      throw Exception('LAYANAN SERVER TIDAK DITEMUKAN [HTTP 404]\nEndpoint: $reqUrl\nDetail: Rute API tidak tersedia pada server.');
    } else if (code >= 500) {
      throw Exception('KENDALA INTERNAL SERVER [HTTP $code]\nEndpoint: $reqUrl\nBody Respon: $bodySnippet');
    } else {
      String errMsg = 'KENDALA HTTP RESPONS [HTTP $code]';
      try {
        final errJson = jsonDecode(response.body);
        if (errJson['message'] != null && errJson['message'].toString().isNotEmpty) {
          errMsg += '\nPesan Server: ' + errJson['message'].toString();
        }
      } catch (_) {
        errMsg += '\nBody Mentah: $bodySnippet';
      }
      throw Exception('$errMsg\nEndpoint: $reqUrl');
    }
  }
}