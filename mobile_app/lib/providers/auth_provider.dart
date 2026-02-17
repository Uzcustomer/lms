import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';

enum AuthState { initial, loading, authenticated, unauthenticated, requires2fa, error }

class AuthProvider extends ChangeNotifier {
  final AuthService _authService;
  final ApiService _apiService;

  AuthState _state = AuthState.initial;
  Map<String, dynamic>? _user;
  String? _errorMessage;
  String? _guard;
  String? _pendingLogin;

  AuthProvider(this._authService, this._apiService);

  AuthState get state => _state;
  Map<String, dynamic>? get user => _user;
  String? get errorMessage => _errorMessage;
  String? get guard => _guard;
  bool get isStudent => _guard == 'student';
  bool get isTeacher => _guard == 'teacher';

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
        notifyListeners();
        return false;
      }

      _user = response['user'] as Map<String, dynamic>?;
      _guard = 'student';
      _state = AuthState.authenticated;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
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
    }
  }

  Future<bool> verify2fa(String code) async {
    _state = AuthState.loading;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _authService.verify2fa(_guard!, _pendingLogin!, code);
      _user = response['user'] as Map<String, dynamic>?;
      _state = AuthState.authenticated;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = AuthState.requires2fa;
      notifyListeners();
      return false;
    }
  }

  Future<void> resend2fa() async {
    try {
      await _authService.resend2fa(_guard!, _pendingLogin!);
    } on ApiException catch (e) {
      _errorMessage = e.message;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    _state = AuthState.loading;
    notifyListeners();

    await _authService.logout();
    _user = null;
    _guard = null;
    _pendingLogin = null;
    _state = AuthState.unauthenticated;
    notifyListeners();
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }
}
