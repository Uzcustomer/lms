import 'dart:convert';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../config/api_config.dart';

class ApiService {
  final FlutterSecureStorage _secureStorage = const FlutterSecureStorage();
  static const String _tokenKey = 'auth_token';
  static const String _guardKey = 'auth_guard';

  // Web da shared_preferences, mobile da flutter_secure_storage ishlatamiz
  Future<String?> _read(String key) async {
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(key);
    }
    return await _secureStorage.read(key: key);
  }

  Future<void> _write(String key, String value) async {
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(key, value);
    } else {
      await _secureStorage.write(key: key, value: value);
    }
  }

  Future<void> _deleteAll() async {
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_tokenKey);
      await prefs.remove(_guardKey);
    } else {
      await _secureStorage.deleteAll();
    }
  }

  Future<String?> getToken() async {
    return await _read(_tokenKey);
  }

  Future<String?> getGuard() async {
    return await _read(_guardKey);
  }

  Future<void> saveToken(String token, String guard) async {
    await _write(_tokenKey, token);
    await _write(_guardKey, guard);
  }

  Future<void> clearToken() async {
    await _deleteAll();
  }

  Future<bool> isLoggedIn() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  Map<String, String> _headers(String? token) {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (token != null) {
      headers['Authorization'] = 'Bearer $token';
    }
    return headers;
  }

  Future<Map<String, dynamic>> post(String endpoint, Map<String, dynamic> body, {bool auth = false}) async {
    String? token;
    if (auth) {
      token = await getToken();
    }

    final response = await http.post(
      Uri.parse('${ApiConfig.baseUrl}$endpoint'),
      headers: _headers(token),
      body: jsonEncode(body),
    );

    return _handleResponse(response);
  }

  Future<Map<String, dynamic>> get(String endpoint, {Map<String, String>? queryParams, bool auth = true}) async {
    String? token;
    if (auth) {
      token = await getToken();
    }

    var uri = Uri.parse('${ApiConfig.baseUrl}$endpoint');
    if (queryParams != null && queryParams.isNotEmpty) {
      uri = uri.replace(queryParameters: queryParams);
    }

    final response = await http.get(
      uri,
      headers: _headers(token),
    );

    return _handleResponse(response);
  }

  Map<String, dynamic> _handleResponse(http.Response response) {
    final body = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return body;
    } else if (response.statusCode == 401) {
      clearToken();
      throw ApiException('Sessiya tugagan. Qayta kiring.', response.statusCode);
    } else if (response.statusCode == 422) {
      final errors = body['errors'] as Map<String, dynamic>?;
      final message = errors?.values.first is List
          ? (errors!.values.first as List).first.toString()
          : body['message']?.toString() ?? 'Xatolik yuz berdi';
      throw ApiException(message, response.statusCode);
    } else {
      throw ApiException(
        body['message']?.toString() ?? 'Server xatoligi',
        response.statusCode,
      );
    }
  }
}

class ApiException implements Exception {
  final String message;
  final int statusCode;

  ApiException(this.message, this.statusCode);

  @override
  String toString() => message;
}
