import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with TickerProviderStateMixin {
  static const _bgTop = Color(0xFF0A1426);
  static const _bgBottom = Color(0xFF050C1A);
  static const _faint = Color(0xFF94A3B8);
  static const _teal = Color(0xFF2DD4BF);
  static const _green = Color(0xFF22C55E);

  late final AnimationController _fade;
  late final AnimationController _pulse;
  late final Animation<Offset> _rise;
  String _version = '';

  /// Keep the splash on screen at least this long so the wordmark
  /// animation isn't cut off on fast networks; the auth check runs
  /// concurrently.
  static const _minDisplay = Duration(milliseconds: 1200);

  @override
  void initState() {
    super.initState();
    _loadVersion();
    _fade = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..forward();
    _rise = Tween<Offset>(begin: const Offset(0, 0.05), end: Offset.zero)
        .animate(CurvedAnimation(parent: _fade, curve: Curves.easeOutCubic));
    _pulse = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat(reverse: true);

    WidgetsBinding.instance.addPostFrameCallback((_) => _checkAuth());
  }

  Future<void> _loadVersion() async {
    try {
      final info = await PackageInfo.fromPlatform();
      if (mounted) {
        setState(() => _version = 'v ${info.version} · build ${info.buildNumber}');
      }
    } catch (_) {}
  }

  Future<void> _checkAuth() async {
    final auth = context.read<AuthProvider>();
    await Future.wait([
      Future.delayed(_minDisplay),
      auth.checkAuth().catchError((_) {}),
    ]);
  }

  @override
  void dispose() {
    _fade.dispose();
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    return Scaffold(
      backgroundColor: _bgBottom,
      body: Stack(
        children: [
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [_bgTop, _bgBottom],
              ),
            ),
          ),
          Positioned.fill(
            child: IgnorePointer(
              child: Center(
                child: Container(
                  width: size.width * 0.85,
                  height: size.height * 0.55,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: RadialGradient(
                      colors: [
                        Color(0x550D9488),
                        Color(0x1A0D9488),
                        Colors.transparent,
                      ],
                      stops: [0.0, 0.45, 1.0],
                    ),
                  ),
                ),
              ),
            ),
          ),
          FadeTransition(
            opacity: _fade,
            child: SlideTransition(
              position: _rise,
              child: Column(
                children: [
                  const Spacer(),
                  _wordmark(),
                  const Spacer(flex: 2),
                  _buildFooter(),
                  const SizedBox(height: 22),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// The university name set as a wordmark: faint first line, the two
  /// heavy lines in white, and the branch picked out in teal between two
  /// short rules.
  Widget _wordmark() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text(
            'TOSHKENT DAVLAT',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: _faint,
              letterSpacing: 4.5,
            ),
          ),
          const SizedBox(height: 12),
          _heavyLine('TIBBIYOT'),
          _heavyLine('UNIVERSITETI'),
          const SizedBox(height: 20),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              _rule(),
              const SizedBox(width: 12),
              const Text(
                'TERMIZ FILIALI',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: _teal,
                  letterSpacing: 4,
                ),
              ),
              const SizedBox(width: 12),
              _rule(),
            ],
          ),
          const SizedBox(height: 30),
          const Text(
            "TA'LIM BOSHQARUV TIZIMI",
            style: TextStyle(
              fontSize: 10.5,
              fontWeight: FontWeight.w500,
              color: _faint,
              letterSpacing: 3.6,
            ),
          ),
        ],
      ),
    );
  }

  /// One line of the heavy part, shrunk on narrow phones instead of wrapping.
  Widget _heavyLine(String text) {
    return FittedBox(
      fit: BoxFit.scaleDown,
      child: Text(
        text,
        style: const TextStyle(
          fontSize: 34,
          height: 1.12,
          fontWeight: FontWeight.w800,
          color: Colors.white,
          letterSpacing: 2,
        ),
      ),
    );
  }

  Widget _rule() => Container(
        width: 34,
        height: 1,
        color: _teal.withValues(alpha: 0.55),
      );

  Widget _buildFooter() {
    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            AnimatedBuilder(
              animation: _pulse,
              builder: (_, _) {
                final t = _pulse.value;
                return Container(
                  width: 6,
                  height: 6,
                  decoration: BoxDecoration(
                    color: _green,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: _green.withValues(alpha: 0.4 + 0.4 * t),
                        blurRadius: 4 + 4 * t,
                        spreadRadius: 0.5 + 1.5 * t,
                      ),
                    ],
                  ),
                );
              },
            ),
            const SizedBox(width: 8),
            const Text(
              'TASHKENT MEDICAL UNIVERSITY · TERMEZ BRANCH · 2018',
              style: TextStyle(
                fontSize: 9,
                fontWeight: FontWeight.w600,
                color: _faint,
                letterSpacing: 1.3,
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Text(
          _version,
          style: TextStyle(
            fontSize: 9,
            color: _faint.withValues(alpha: 0.55),
            letterSpacing: 1.1,
          ),
        ),
      ],
    );
  }
}
