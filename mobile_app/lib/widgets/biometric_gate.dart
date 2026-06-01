import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
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
    final ok = await _bio.authenticate(
      reason: 'Ilovaga kirish uchun qurilma himoyasini tasdiqlang',
    );
    _authing = false;
    if (ok && mounted) setState(() => _locked = false);
  }

  Future<void> _offerEnable() async {
    if (!mounted) return;
    final yes = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Tezkor kirish'),
        content: const Text(
            'Keyingi safar ilovaga barmoq izi, Face ID yoki qurilma paroli bilan kirishni xohlaysizmi?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Hozir emas'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Yoqish'),
          ),
        ],
      ),
    );
    if (yes == true) {
      final ok = await _bio.authenticate(
        reason: 'Tasdiqlash uchun qurilma himoyasini tekshiring',
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
                const Text(
                  'Ilova qulflangan',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Davom etish uchun barmoq izi, Face ID\nyoki qurilma paroli bilan tasdiqlang',
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
                    label: const Text('Ochish'),
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
                    'Parol bilan kirish',
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
