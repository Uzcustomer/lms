import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../l10n/app_localizations.dart';
import '../providers/auth_provider.dart';
import '../services/biometric_service.dart';

/// Wraps the authenticated app with a biometric lock.
///
/// When the student has enabled biometric login, the app is locked on a
/// cold start (a session restored from a stored token) and whenever it
/// returns from the background — the user must pass Face ID / fingerprint
/// to continue. A fresh manual login does not trigger an immediate lock.
class BiometricGate extends StatefulWidget {
  final Widget child;
  const BiometricGate({super.key, required this.child});

  @override
  State<BiometricGate> createState() => _BiometricGateState();
}

class _BiometricGateState extends State<BiometricGate>
    with WidgetsBindingObserver {
  final _bio = BiometricService();
  bool _enabled = false;
  bool _available = false;
  bool _locked = false;
  bool _authing = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _init());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> _init() async {
    _available = await _bio.isAvailable();
    final enabled = await _bio.isEnabled();
    _enabled = enabled && _available;
    final viaLogin =
        mounted ? context.read<AuthProvider>().viaLogin : false;

    if (_enabled && !viaLogin) {
      if (mounted) setState(() => _locked = true);
      _prompt();
    } else if (viaLogin && _available && !enabled) {
      _offerEnable();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!_enabled) return;
    if (state == AppLifecycleState.paused) {
      if (!_authing && mounted) setState(() => _locked = true);
    } else if (state == AppLifecycleState.resumed) {
      if (_locked && !_authing) _prompt();
    }
  }

  Future<void> _prompt() async {
    if (_authing) return;
    _authing = true;
    final l = AppLocalizations.of(context);
    final ok = await _bio.authenticate(
      reason: l.pick(
        uz: 'Ilovaga kirish uchun qurilma himoyasini tasdiqlang',
        ru: 'Подтвердите вход в приложение защитой устройства',
        en: 'Confirm with your device security to open the app',
      ),
    );
    _authing = false;
    if (ok && mounted) setState(() => _locked = false);
  }

  Future<void> _offerEnable() async {
    if (!mounted) return;
    final l = AppLocalizations.of(context);
    final yes = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l.pick(
          uz: 'Tezkor kirish',
          ru: 'Быстрый вход',
          en: 'Quick access',
        )),
        content: Text(l.pick(
          uz: 'Keyingi safar ilovaga barmoq izi, Face ID yoki qurilma paroli bilan kirishni xohlaysizmi?',
          ru: 'Хотите в следующий раз входить в приложение по отпечатку, Face ID или паролю устройства?',
          en: 'Would you like to use fingerprint, Face ID, or device passcode next time?',
        )),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(l.pick(
              uz: 'Hozir emas',
              ru: 'Не сейчас',
              en: 'Not now',
            )),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(l.pick(
              uz: 'Yoqish',
              ru: 'Включить',
              en: 'Enable',
            )),
          ),
        ],
      ),
    );
    if (yes == true) {
      final ok = await _bio.authenticate(
        reason: l.pick(
          uz: 'Tasdiqlash uchun qurilma himoyasini tekshiring',
          ru: 'Подтвердите действие защитой устройства',
          en: 'Confirm with your device security',
        ),
      );
      if (ok) {
        await _bio.setEnabled(true);
        _enabled = true;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        widget.child,
        if (_locked) Positioned.fill(child: _LockScreen(onUnlock: _prompt)),
      ],
    );
  }
}

class _LockScreen extends StatelessWidget {
  final VoidCallback onUnlock;
  const _LockScreen({required this.onUnlock});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Material(
      child: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF0D9488), Color(0xFF1E3A8A)],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 28),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 96,
                  height: 96,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.16),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white.withOpacity(0.4)),
                  ),
                  child: const Icon(Icons.fingerprint_rounded,
                      color: Colors.white, size: 52),
                ),
                const SizedBox(height: 22),
                Text(
                  l.pick(
                    uz: 'Ilova qulflangan',
                    ru: 'Приложение заблокировано',
                    en: 'App locked',
                  ),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  l.pick(
                    uz: 'Davom etish uchun barmoq izi, Face ID\nyoki qurilma paroli bilan tasdiqlang',
                    ru: 'Для продолжения подтвердите отпечатком,\nFace ID или паролем устройства',
                    en: 'Confirm with fingerprint, Face ID,\nor device passcode to continue',
                  ),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.85),
                    fontSize: 13.5,
                    height: 1.5,
                  ),
                ),
                const SizedBox(height: 26),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: onUnlock,
                    icon: const Icon(Icons.lock_open_rounded, size: 18),
                    label: Text(l.pick(
                      uz: 'Ochish',
                      ru: 'Открыть',
                      en: 'Unlock',
                    )),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: const Color(0xFF0D9488),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      textStyle: const TextStyle(
                          fontSize: 14, fontWeight: FontWeight.w800),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                TextButton(
                  onPressed: () => context.read<AuthProvider>().logout(),
                  child: Text(
                    l.pick(
                      uz: 'Parol bilan kirish',
                      ru: 'Войти по паролю',
                      en: 'Sign in with password',
                    ),
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.9),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
