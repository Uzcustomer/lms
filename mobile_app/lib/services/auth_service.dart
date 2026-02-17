import '../config/api_config.dart';
import 'api_service.dart';

class AuthService {
  final ApiService _api;

  AuthService(this._api);

  Future<Map<String, dynamic>> studentLogin(String login, String password) async {
    final response = await _api.post(ApiConfig.studentLogin, {
      'login': login,
      'password': password,
    });

    if (response['requires_2fa'] == true) {
      return response;
    }

    if (response['token'] != null) {
      await _api.saveToken(response['token'], response['guard'] ?? 'student');
    }

    return response;
  }

  Future<Map<String, dynamic>> teacherLogin(String login, String password) async {
    final response = await _api.post(ApiConfig.teacherLogin, {
      'login': login,
      'password': password,
    });

    if (response['requires_2fa'] == true) {
      return response;
    }

    if (response['token'] != null) {
      await _api.saveToken(response['token'], response['guard'] ?? 'teacher');
    }

    return response;
  }

  Future<Map<String, dynamic>> verify2fa(String guard, String login, String code) async {
    final endpoint = guard == 'student'
        ? ApiConfig.studentVerify2fa
        : ApiConfig.teacherVerify2fa;

    final response = await _api.post(endpoint, {
      'login': login,
      'code': code,
    });

    if (response['token'] != null) {
      await _api.saveToken(response['token'], response['guard'] ?? guard);
    }

    return response;
  }

  Future<Map<String, dynamic>> resend2fa(String guard, String login) async {
    final endpoint = guard == 'student'
        ? ApiConfig.studentResend2fa
        : ApiConfig.teacherResend2fa;

    return await _api.post(endpoint, {'login': login});
  }

  Future<Map<String, dynamic>> getMe() async {
    return await _api.get(ApiConfig.me);
  }

  Future<void> logout() async {
    try {
      await _api.post(ApiConfig.logout, {}, auth: true);
    } catch (_) {}
    await _api.clearToken();
  }
}
