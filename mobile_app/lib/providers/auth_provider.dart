import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';

enum AuthState { initial, loading, authenticated, profileIncomplete, unauthenticated, requires2fa, error }

class AuthProvider extends ChangeNotifier {
  final AuthService _authService;
  final ApiService _apiService;

  AuthState _state = AuthState.initial;
  Map<String, dynamic>? _user;
  String? _errorMessage;
  String? _guard;
  String? _pendingLogin;
  int? _pendingUserId;
  bool _profileComplete = true;
  bool _telegramVerified = false;
  int _telegramDaysLeft = 0;
  String? _botUsername;
  String? _verificationCode;
  String? _botLink;

  AuthProvider(this._authService, this._apiService);

  AuthState get state => _state;
  Map<String, dynamic>? get user => _user;
  String? get errorMessage => _errorMessage;
  String? get guard => _guard;
  bool get isStudent => _guard == 'student';
  bool get isTeacher => _guard == 'teacher';
  bool get profileComplete => _profileComplete;
  bool get telegramVerified => _telegramVerified;
  int get telegramDaysLeft => _telegramDaysLeft;
  String? get botUsername => _botUsername;
  String? get verificationCode => _verificationCode;
  String? get botLink => _botLink;

  Future<void> checkAuth() async {
    final isLoggedIn = await _apiService.isLoggedIn();
    if (isLoggedIn) {
      try {
        final response = await _authService.getMe();
        _user = response['user'] as Map<String, dynamic>?;
        _guard = await _apiService.getGuard();
        _state = AuthState.authenticated;
      } catch (_) {
        await _apiService.clearToken();
        _state = AuthState.unauthenticated;
      }
    } else {
      _state = AuthState.unauthenticated;
    }
    notifyListeners();
  }

  void _handleLoginResponse(Map<String, dynamic> response, String guard) {
    _user = response['user'] as Map<String, dynamic>?;
    _guard = guard;
    _profileComplete = response['profile_complete'] == true;
    _telegramVerified = response['telegram_verified'] == true;
    _telegramDaysLeft = response['telegram_days_left'] as int? ?? 0;
    _botUsername = response['bot_username'] as String?;

    if (!_profileComplete) {
      _state = AuthState.profileIncomplete;
    } else {
      _state = AuthState.authenticated;
    }
  }

  Future<bool> studentLogin(String login, String password) async {
    _state = AuthState.loading;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _authService.studentLogin(login, password);

      if (response['requires_2fa'] == true) {
        _state = AuthState.requires2fa;
        _guard = 'student';
        _pendingLogin = login;
        _pendingUserId = response['student_id'] as int?;
        notifyListeners();
        return false;
      }

      _handleLoginResponse(response, 'student');
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = AuthState.error;
      notifyListeners();
      return false;
    } catch (e) {
      _errorMessage = 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
      _state = AuthState.error;
      notifyListeners();
      return false;
    }
  }

  Future<bool> teacherLogin(String login, String password) async {
    _state = AuthState.loading;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _authService.teacherLogin(login, password);

      if (response['requires_2fa'] == true) {
        _state = AuthState.requires2fa;
        _guard = 'teacher';
        _pendingLogin = login;
        _pendingUserId = response['teacher_id'] as int?;
        notifyListeners();
        return false;
      }

      _user = response['user'] as Map<String, dynamic>?;
      _guard = 'teacher';
      _state = AuthState.authenticated;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = AuthState.error;
      notifyListeners();
      return false;
    } catch (e) {
      _errorMessage = 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
      _state = AuthState.error;
      notifyListeners();
      return false;
    }
  }

  Future<bool> verify2fa(String code) async {
    _state = AuthState.loading;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _authService.verify2fa(_guard!, _pendingUserId!, code);
      _handleLoginResponse(response, _guard!);
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = AuthState.requires2fa;
      notifyListeners();
      return false;
    } catch (e) {
      _errorMessage = 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
      _state = AuthState.requires2fa;
      notifyListeners();
      return false;
    }
  }

  Future<void> resend2fa() async {
    try {
      await _authService.resend2fa(_guard!, _pendingUserId!);
    } on ApiException catch (e) {
      _errorMessage = e.message;
      notifyListeners();
    }
  }

  Future<bool> savePhone(String phone) async {
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _authService.savePhone(phone);
      _profileComplete = response['profile_complete'] == true;
      _telegramDaysLeft = response['telegram_days_left'] as int? ?? 0;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      notifyListeners();
      return false;
    } catch (e) {
      _errorMessage = 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
      notifyListeners();
      return false;
    }
  }

  Future<bool> saveTelegram(String username) async {
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _authService.saveTelegram(username);
      _verificationCode = response['verification_code'] as String?;
      _botLink = response['bot_link'] as String?;
      _botUsername = response['bot_username'] as String?;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      notifyListeners();
      return false;
    } catch (e) {
      _errorMessage = 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
      notifyListeners();
      return false;
    }
  }

  Future<bool> checkTelegramVerification() async {
    try {
      final response = await _authService.checkTelegramVerification();
      _telegramVerified = response['verified'] == true;
      _telegramDaysLeft = response['telegram_days_left'] as int? ?? 0;
      notifyListeners();
      return _telegramVerified;
    } catch (_) {
      return false;
    }
  }

  void completeProfileSetup() {
    _state = AuthState.authenticated;
    notifyListeners();
  }

  Future<void> logout() async {
    _state = AuthState.loading;
    notifyListeners();

    await _authService.logout();
    _user = null;
    _guard = null;
    _pendingLogin = null;
    _pendingUserId = null;
    _profileComplete = true;
    _telegramVerified = false;
    _verificationCode = null;
    _botLink = null;
    _state = AuthState.unauthenticated;
    notifyListeners();
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }
}
