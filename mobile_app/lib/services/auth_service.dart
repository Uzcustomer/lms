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

  Future<Map<String, dynamic>> verify2fa(String guard, int userId, String code) async {
    final endpoint = guard == 'student'
        ? ApiConfig.studentVerify2fa
        : ApiConfig.teacherVerify2fa;

    final idKey = guard == 'student' ? 'student_id' : 'teacher_id';

    final response = await _api.post(endpoint, {
      idKey: userId,
      'code': code,
    });

    if (response['token'] != null) {
      await _api.saveToken(response['token'], response['guard'] ?? guard);
    }

    return response;
  }

  Future<Map<String, dynamic>> resend2fa(String guard, int userId) async {
    final endpoint = guard == 'student'
        ? ApiConfig.studentResend2fa
        : ApiConfig.teacherResend2fa;

    final idKey = guard == 'student' ? 'student_id' : 'teacher_id';

    return await _api.post(endpoint, {idKey: userId});
  }

  Future<Map<String, dynamic>> getMe() async {
    return await _api.get(ApiConfig.me);
  }

  Future<Map<String, dynamic>> savePhone(String phone) async {
    return await _api.post(ApiConfig.studentSavePhone, {'phone': phone}, auth: true);
  }

  Future<Map<String, dynamic>> saveTelegram(String username) async {
    return await _api.post(ApiConfig.studentSaveTelegram, {'telegram_username': username}, auth: true);
  }

  Future<Map<String, dynamic>> checkTelegramVerification() async {
    return await _api.get(ApiConfig.studentCheckTelegram);
  }

  Future<void> logout() async {
    try {
      await _api.post(ApiConfig.logout, {}, auth: true);
    } catch (_) {}
    await _api.clearToken();
  }
}
