import 'dart:math' as math;
import 'package:flutter/material.dart';
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
  static const _glow = Color(0xFF0D9488);
  static const _faint = Color(0xFF94A3B8);
  static const _green = Color(0xFF22C55E);

  late final AnimationController _fade;
  late final AnimationController _rotate;
  late final AnimationController _pulse;

  @override
  void initState() {
    super.initState();
    _fade = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..forward();
    _rotate = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 5500),
    )..repeat();
    _pulse = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat(reverse: true);

    WidgetsBinding.instance.addPostFrameCallback((_) => _checkAuth());
  }

  Future<void> _checkAuth() async {
    await Future.delayed(const Duration(seconds: 2));
    if (!mounted) return;
    try {
      await context.read<AuthProvider>().checkAuth();
    } catch (_) {}
  }

  @override
  void dispose() {
    _fade.dispose();
    _rotate.dispose();
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
          // Background gradient
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [_bgTop, _bgBottom],
              ),
            ),
          ),
          // Soft teal glow centered behind the helix
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
          // Helix + content
          FadeTransition(
            opacity: _fade,
            child: Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Spacer(),
                  SizedBox(
                    width: 230,
                    height: 360,
                    child: AnimatedBuilder(
                      animation: _rotate,
                      builder: (_, __) => CustomPaint(
                        painter: _DnaPainter(phase: _rotate.value),
                      ),
                    ),
                  ),
                  const SizedBox(height: 36),
                  const Text(
                    'TASHMEDUNI·TF',
                    style: TextStyle(
                      fontSize: 26,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                      letterSpacing: 1.2,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    "TA'LIM BOSHQARUV TIZIMI",
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w500,
                      color: _faint,
                      letterSpacing: 3.6,
                    ),
                  ),
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

  Widget _buildFooter() {
    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            AnimatedBuilder(
              animation: _pulse,
              builder: (_, __) {
                final t = _pulse.value;
                return Container(
                  width: 6,
                  height: 6,
                  decoration: BoxDecoration(
                    color: _green,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: _green.withOpacity(0.4 + 0.4 * t),
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
          'v 2.4.1 · build 26.05.2026',
          style: TextStyle(
            fontSize: 9,
            color: _faint.withOpacity(0.55),
            letterSpacing: 1.1,
          ),
        ),
      ],
    );
  }
}

class _DnaPainter extends CustomPainter {
  final double phase;
  _DnaPainter({required this.phase});

  static const _red = Color(0xFFEF4444);
  static const _blue = Color(0xFF3B82F6);
  static const _green = Color(0xFF22C55E);
  static const _amber = Color(0xFFF59E0B);
  static const _strandA = Color(0xFFB91C1C);
  static const _strandB = Color(0xFF1D4ED8);

  @override
  void paint(Canvas canvas, Size size) {
    final cx = size.width / 2;
    final amp = size.width * 0.36;
    final turns = 5.0;
    final twoPi = math.pi * 2;
    final phaseRad = phase * twoPi;

    // Sample points along the helix
    const samples = 220;
    final pointsA = <Offset>[];
    final pointsB = <Offset>[];
    final depthA = <double>[]; // 0..1, 1 = front
    final depthB = <double>[];

    for (int i = 0; i <= samples; i++) {
      final t = i / samples;
      final y = t * size.height;
      final angle = t * turns * twoPi + phaseRad;
      final xa = cx + amp * math.sin(angle);
      final xb = cx + amp * math.sin(angle + math.pi);
      pointsA.add(Offset(xa, y));
      pointsB.add(Offset(xb, y));
      depthA.add(0.5 + 0.5 * math.cos(angle));
      depthB.add(0.5 + 0.5 * math.cos(angle + math.pi));
    }

    // Draw rungs first (so strands sit on top at crossing points)
    const rungCount = 18;
    final rungPaintCache = <int, Color>{
      0: _red,
      1: _blue,
      2: _green,
      3: _amber,
    };
    for (int i = 0; i < rungCount; i++) {
      final t = (i + 0.5) / rungCount;
      final y = t * size.height;
      final angle = t * turns * twoPi + phaseRad;
      final xa = cx + amp * math.sin(angle);
      final xb = cx + amp * math.sin(angle + math.pi);
      // Skip near-crossings so rungs aren't drawn flat through the center
      final dx = (xa - xb).abs();
      if (dx < amp * 0.25) continue;

      final color = rungPaintCache[i % 4]!;
      final paint = Paint()
        ..color = color.withOpacity(0.85)
        ..strokeWidth = 1.4
        ..strokeCap = StrokeCap.round;
      canvas.drawLine(Offset(xa, y), Offset(xb, y), paint);
    }

    // Draw strands as thin curves with depth-based opacity
    _drawStrand(canvas, pointsA, depthA, _strandA);
    _drawStrand(canvas, pointsB, depthB, _strandB);

    // Draw colored beads at peaks (where strand crosses extreme positions)
    final beadColors = [_red, _blue, _green, _amber];
    const beadsPerStrand = 9;
    for (int i = 0; i < beadsPerStrand; i++) {
      final t = (i + 0.5) / beadsPerStrand;
      final y = t * size.height;
      final angle = t * turns * twoPi + phaseRad;

      final xa = cx + amp * math.sin(angle);
      final xb = cx + amp * math.sin(angle + math.pi);
      final da = 0.5 + 0.5 * math.cos(angle);
      final db = 0.5 + 0.5 * math.cos(angle + math.pi);

      final colorA = beadColors[i % beadColors.length];
      final colorB = beadColors[(i + 2) % beadColors.length];

      _drawBead(canvas, Offset(xa, y), colorA, da);
      _drawBead(canvas, Offset(xb, y), colorB, db);
    }
  }

  void _drawStrand(
      Canvas canvas, List<Offset> pts, List<double> depths, Color color) {
    for (int i = 0; i < pts.length - 1; i++) {
      final d = (depths[i] + depths[i + 1]) / 2;
      final paint = Paint()
        ..color = color.withOpacity(0.25 + 0.55 * d)
        ..strokeWidth = 1.4 + 0.9 * d
        ..strokeCap = StrokeCap.round;
      canvas.drawLine(pts[i], pts[i + 1], paint);
    }
  }

  void _drawBead(Canvas canvas, Offset p, Color color, double depth) {
    final r = 3.4 + 2.2 * depth;
    // Outer halo
    canvas.drawCircle(
      p,
      r + 2.5,
      Paint()..color = color.withOpacity(0.18 * depth),
    );
    // Bead body
    canvas.drawCircle(
      p,
      r,
      Paint()..color = color.withOpacity(0.55 + 0.45 * depth),
    );
    // Hot center
    canvas.drawCircle(
      p,
      r * 0.45,
      Paint()..color = Colors.white.withOpacity(0.55 * depth),
    );
  }

  @override
  bool shouldRepaint(_DnaPainter old) => old.phase != phase;
}
