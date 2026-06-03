import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/services.dart';
import 'package:local_auth/local_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class BiometricService {
  static const String _enabledKey = 'biometric_enabled';
  final LocalAuthentication _auth = LocalAuthentication();

  /// True if the device has *any* lock screen — biometric, PIN, pattern,
  /// or password. We accept the device PIN as a fallback for users whose
  /// fingerprint/Face ID isn't recognising them.
  Future<bool> isAvailable() async {
    if (kIsWeb) return false;
    try {
      // isDeviceSupported() is true whenever the OS lock screen is set up
      // (PIN/pattern/password OR biometrics). canCheckBiometrics narrows it
      // to biometric sensors only — we don't require it.
      return await _auth.isDeviceSupported();
    } on PlatformException {
      return false;
    }
  }

  /// True only when there is at least one enrolled biometric — used for the
  /// settings tile that asks "Yoqamizmi?"
  Future<bool> hasBiometric() async {
    if (kIsWeb) return false;
    try {
      final canCheck = await _auth.canCheckBiometrics;
      if (!canCheck) return false;
      final list = await _auth.getAvailableBiometrics();
      return list.isNotEmpty;
    } on PlatformException {
      return false;
    }
  }

  Future<bool> hasFaceId() async {
    if (kIsWeb) return false;
    try {
      final list = await _auth.getAvailableBiometrics();
      return list.contains(BiometricType.face) ||
          list.contains(BiometricType.strong);
    } on PlatformException {
      return false;
    }
  }

  /// Prompt the user for their biometric. If the sensor fails, isn't
  /// enrolled, or doesn't recognise them, the OS automatically falls back
  /// to the device PIN / pattern / password.
  Future<bool> authenticate({
    String reason = 'Tizimga kirish uchun qurilma himoyasini tasdiqlang',
  }) async {
    if (kIsWeb) return false;
    try {
      return await _auth.authenticate(
        localizedReason: reason,
        options: const AuthenticationOptions(
          biometricOnly: false,
          stickyAuth: true,
          useErrorDialogs: true,
        ),
      );
    } on PlatformException {
      return false;
    }
  }

  Future<bool> isEnabled() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_enabledKey) ?? false;
  }

  Future<void> setEnabled(bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_enabledKey, value);
  }

  // ── Stored credentials for biometric re-login ────────
  // Kept in the OS keystore/keychain so the student can log back in
  // with a fingerprint / Face ID even after a logout or token expiry.
  static const _secure = FlutterSecureStorage();
  static const _kLogin = 'bio_cred_login';
  static const _kPass = 'bio_cred_pass';
  static const _kRole = 'bio_cred_role';

  Future<void> saveCredentials({
    required String login,
    required String password,
    required String role,
  }) async {
    try {
      await _secure.write(key: _kLogin, value: login);
      await _secure.write(key: _kPass, value: password);
      await _secure.write(key: _kRole, value: role);
    } catch (_) {}
  }

  Future<bool> hasCredentials() async {
    try {
      return (await _secure.read(key: _kLogin)) != null;
    } catch (_) {
      return false;
    }
  }

  Future<Map<String, String>?> getCredentials() async {
    try {
      final login = await _secure.read(key: _kLogin);
      final pass = await _secure.read(key: _kPass);
      final role = await _secure.read(key: _kRole);
      if (login != null && pass != null) {
        return {'login': login, 'password': pass, 'role': role ?? 'student'};
      }
    } catch (_) {}
    return null;
  }

  Future<void> clearCredentials() async {
    try {
      await _secure.delete(key: _kLogin);
      await _secure.delete(key: _kPass);
      await _secure.delete(key: _kRole);
    } catch (_) {}
  }
}
