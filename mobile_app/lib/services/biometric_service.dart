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

  // ── Last used login (ID + role only, never the password) ────
  // Sessions are kept alive by the API token behind BiometricGate; after a
  // logout or token expiry the user types the password again.
  static const _kLastLogin = 'last_login_id';
  static const _kLastRole = 'last_login_role';

  Future<void> saveLastLogin({required String login, required String role}) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kLastLogin, login);
    await prefs.setString(_kLastRole, role);
  }

  Future<Map<String, String>?> getLastLogin() async {
    final prefs = await SharedPreferences.getInstance();
    final login = prefs.getString(_kLastLogin);
    if (login == null || login.isEmpty) return null;
    return {'login': login, 'role': prefs.getString(_kLastRole) ?? 'student'};
  }

  Future<void> clearLastLogin() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kLastLogin);
    await prefs.remove(_kLastRole);
  }

  // Older builds stored the password in the keystore for biometric
  // re-login. Wipe it on first run of this version.
  static const _secure = FlutterSecureStorage();
  static const _legacyKeys = ['bio_cred_login', 'bio_cred_pass', 'bio_cred_role'];

  Future<void> purgeLegacyCredentials() async {
    for (final key in _legacyKeys) {
      try {
        await _secure.delete(key: key);
      } catch (_) {}
    }
  }
}
