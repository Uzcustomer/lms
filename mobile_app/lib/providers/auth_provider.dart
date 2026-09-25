import 'dart:io';

import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';
import '../services/student_data_cache.dart';
import '../l10n/app_localizations.dart';

enum AuthState { initial, loading, authenticated, profileIncomplete, unauthenticated, requires2fa, error }

class AuthProvider extends ChangeNotifier {
  final AuthService _authService;
  final ApiService _apiService;

  AuthState _state = AuthState.initial;
  Map<String, dynamic>? _user;
  String? _errorMessage;
  String? _guard;
  int? _pendingUserId;
  bool _profileComplete = true;
  bool _telegramVerified = false;
  int _telegramDaysLeft = 0;
  String? _botUsername;
  String? _verificationCode;
  String? _botLink;
  List<String> _roles = [];
  String? _activeRole;

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
  List<String> get roles => _roles;
  String? get activeRole => _activeRole;

  /// Display name of a staff role in the current app language.
  static String roleLabel(String? role) {
    final l = AppLocalizations.current;
    return switch (role) {
      'superadmin' => 'Superadmin',
      'admin' => 'Admin',
      'kichik_admin' => l.pick(uz: 'Kichik admin', ru: 'Младший админ', en: 'Junior admin'),
      'inspeksiya' => l.pick(uz: 'Inspeksiya', ru: 'Инспекция', en: 'Inspection'),
      'oquv_prorektori' => l.pick(uz: "O'quv prorektori", ru: 'Проректор по учебной работе', en: 'Vice-rector for academic affairs'),
      'registrator_ofisi' => l.pick(uz: 'Registrator ofisi', ru: 'Офис регистратора', en: "Registrar's office"),
      'oquv_bolimi' => l.pick(uz: "O'quv bo'limi", ru: 'Учебный отдел', en: 'Academic office'),
      'oquv_bolimi_boshligi' => l.pick(uz: "O'quv bo'limi boshlig'i", ru: 'Начальник учебного отдела', en: 'Head of academic office'),
      'buxgalteriya' => l.pick(uz: 'Buxgalteriya', ru: 'Бухгалтерия', en: 'Accounting'),
      'manaviyat' => l.pick(uz: "Ma'naviyat", ru: 'Отдел духовности', en: 'Spirituality office'),
      'tyutor' => l.pick(uz: 'Tyutor', ru: 'Тьютор', en: 'Tutor'),
      'dekan' => l.pick(uz: 'Dekan', ru: 'Декан', en: 'Dean'),
      'kafedra_mudiri' => l.pick(uz: 'Kafedra mudiri', ru: 'Заведующий кафедрой', en: 'Head of department'),
      'fan_masuli' => l.pick(uz: "Fan mas'uli", ru: 'Ответственный по предмету', en: 'Subject lead'),
      'oqituvchi' => l.teacher,
      'test_markazi' => l.pick(uz: 'Test markazi', ru: 'Тестовый центр', en: 'Test centre'),
      'talaba' => l.student,
      _ => role ?? '',
    };
  }

  String get activeRoleLabel => roleLabel(_activeRole);

  void setActiveRole(String role) {
    if (_roles.contains(role)) {
      _activeRole = role;
      notifyListeners();
    }
  }

  void _parseRoles(Map<String, dynamic> response) {
    final rolesData = response['roles'];
    if (rolesData is List) {
      _roles = rolesData.map((e) => e.toString()).toList();
      if (_roles.isNotEmpty && (_activeRole == null || !_roles.contains(_activeRole))) {
        _activeRole = _roles.first;
      }
    }
  }

  Future<void> checkAuth() async {
    _viaLogin = false;
    try {
      final isLoggedIn = await _apiService.isLoggedIn();
      if (isLoggedIn) {
        try {
          final response = await _authService.getMe();
          _user = response['user'] as Map<String, dynamic>?;
          _guard = await _apiService.getGuard();
          _parseRoles(response);
          _applyProfileFlags(response);
          _state = _profileComplete
              ? AuthState.authenticated
              : AuthState.profileIncomplete;
        } catch (_) {
          await _apiService.clearToken();
          _state = AuthState.unauthenticated;
        }
      } else {
        _state = AuthState.unauthenticated;
      }
    } catch (_) {
      _state = AuthState.unauthenticated;
    }
    notifyListeners();
  }

  /// True when the session was opened via an explicit login this run
  /// (as opposed to a cold start with a stored token). Used by the
  /// biometric gate to skip locking right after a manual login.
  bool _viaLogin = false;
  bool get viaLogin => _viaLogin;

  /// Reads the profile-completion flags shared by the login and /me
  /// responses. Keys absent from the payload leave the current values alone.
  void _applyProfileFlags(Map<String, dynamic> response) {
    if (response.containsKey('profile_complete')) {
      _profileComplete = response['profile_complete'] == true;
    }
    if (response.containsKey('telegram_verified')) {
      _telegramVerified = response['telegram_verified'] == true;
    }
    _telegramDaysLeft = response['telegram_days_left'] as int? ?? _telegramDaysLeft;
    _botUsername = response['bot_username'] as String? ?? _botUsername;
  }

  void _handleLoginResponse(Map<String, dynamic> response, String guard) {
    _viaLogin = true;
    _loggedOut = false;
    _user = response['user'] as Map<String, dynamic>?;
    _guard = guard;
    _parseRoles(response);
    _applyProfileFlags(response);

    if (!_profileComplete) {
      _state = AuthState.profileIncomplete;
    } else {
      _state = AuthState.authenticated;
    }
  }

  Future<bool> studentFaceLogin(String login, File photo) async {
    _state = AuthState.loading;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _authService.studentFaceLogin(login, photo);
      await StudentDataCache().clear();
      _handleLoginResponse(response, 'student');
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = AuthState.error;
      notifyListeners();
      return false;
    } catch (e) {
      _errorMessage = AppLocalizations.current.networkError;
      _state = AuthState.error;
      notifyListeners();
      return false;
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
        _pendingUserId = response['student_id'] as int?;
        notifyListeners();
        return false;
      }

      await StudentDataCache().clear();
      _handleLoginResponse(response, 'student');
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = AuthState.error;
      notifyListeners();
      return false;
    } catch (e) {
      _errorMessage = AppLocalizations.current.networkError;
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
        _pendingUserId = response['teacher_id'] as int?;
        notifyListeners();
        return false;
      }

      _user = response['user'] as Map<String, dynamic>?;
      _guard = 'teacher';
      _parseRoles(response);
      _state = AuthState.authenticated;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = AuthState.error;
      notifyListeners();
      return false;
    } catch (e) {
      _errorMessage = AppLocalizations.current.networkError;
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
      if (_guard == 'student') {
        await StudentDataCache().clear();
      }
      _handleLoginResponse(response, _guard!);
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = AuthState.requires2fa;
      notifyListeners();
      return false;
    } catch (e) {
      _errorMessage = AppLocalizations.current.networkError;
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
      _errorMessage = AppLocalizations.current.networkError;
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
      _errorMessage = AppLocalizations.current.networkError;
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

  /// True after an explicit in-session logout — the login screen uses
  /// this to avoid auto-prompting biometric right after the user chose
  /// to sign out.
  bool _loggedOut = false;
  bool get loggedOut => _loggedOut;

  Future<void> logout() async {
    _state = AuthState.loading;
    _loggedOut = true;
    notifyListeners();

    await _authService.logout();
    await StudentDataCache().clear();
    _user = null;
    _guard = null;
    _pendingUserId = null;
    _profileComplete = true;
    _telegramVerified = false;
    _verificationCode = null;
    _botLink = null;
    _roles = [];
    _activeRole = null;
    _state = AuthState.unauthenticated;
    notifyListeners();
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }
}
