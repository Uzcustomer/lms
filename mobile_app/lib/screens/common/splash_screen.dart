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
  static const _faint = Color(0xFF94A3B8);
  static const _green = Color(0xFF22C55E);

  late final AnimationController _fade;
  late final AnimationController _spin;
  late final AnimationController _pulse;

  @override
  void initState() {
    super.initState();
    _fade = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..forward();
    _spin = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 9000),
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
    _spin.dispose();
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
            child: Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Spacer(),
                  SizedBox(
                    width: 320,
                    height: 320,
                    child: AnimatedBuilder(
                      animation: Listenable.merge([_spin, _pulse]),
                      builder: (_, __) => CustomPaint(
                        painter: _MoleculePainter(
                          spin: _spin.value,
                          pulse: _pulse.value,
                        ),
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

/// 3D molecule: central nucleus + hexagonal ring + outer satellites.
/// The whole structure rotates around the Y axis; depth (z) is mapped to
/// atom size + brightness so it really reads as a 3D model.
class _MoleculePainter extends CustomPainter {
  final double spin;
  final double pulse;
  _MoleculePainter({required this.spin, required this.pulse});

  static const _red = Color(0xFFEF4444);
  static const _blue = Color(0xFF3B82F6);
  static const _green = Color(0xFF22C55E);
  static const _amber = Color(0xFFF59E0B);
  static const _teal = Color(0xFF14B8A6);
  static const _violet = Color(0xFFA78BFA);

  @override
  void paint(Canvas canvas, Size size) {
    final cx = size.width / 2;
    final cy = size.height / 2;
    final radius = size.width * 0.30;
    final outerR = size.width * 0.46;
    final twoPi = math.pi * 2;
    final phase = spin * twoPi;

    final atoms = <_Atom>[];

    // Central nucleus
    atoms.add(_Atom(
      pos3: _V3(0, 0, 0),
      color: _red,
      radius: 14,
      isCore: true,
    ));

    // Inner hexagonal ring (tilted)
    const ringCount = 6;
    final ringColors = [_blue, _amber, _green, _violet, _teal, _amber];
    for (int i = 0; i < ringCount; i++) {
      final t = i / ringCount;
      final a = t * twoPi + phase;
      final x = radius * math.cos(a);
      final z = radius * math.sin(a);
      // Tilt ring slightly so it looks like a 3D plane
      final y = radius * 0.18 * math.sin(t * twoPi);
      atoms.add(_Atom(
        pos3: _V3(x, y, z),
        color: ringColors[i],
        radius: 9,
      ));
    }

    // Outer satellites — different orbit, opposite direction
    const satCount = 5;
    final satColors = [_green, _amber, _blue, _violet, _red];
    for (int i = 0; i < satCount; i++) {
      final t = i / satCount;
      final a = t * twoPi - phase * 0.7 + 0.4;
      final tilt = 0.45 * math.sin(a * 1.5);
      final x = outerR * math.cos(a);
      final z = outerR * math.sin(a) * math.cos(tilt);
      final y = outerR * 0.55 * math.sin(tilt);
      atoms.add(_Atom(
        pos3: _V3(x, y, z),
        color: satColors[i],
        radius: 6.5,
      ));
    }

    // Project to 2D (orthographic, z controls depth)
    for (final a in atoms) {
      a.screen = Offset(cx + a.pos3.x, cy + a.pos3.y);
      // depth 0 = behind, 1 = in front
      a.depth = 0.5 + 0.5 * (a.pos3.z / outerR);
    }

    // Bonds: nucleus → each ring atom; ring → adjacent ring; ring → nearest satellite.
    final core = atoms[0];
    final ringAtoms = atoms.sublist(1, 1 + ringCount);
    final satellites = atoms.sublist(1 + ringCount);

    final bonds = <_Bond>[];
    for (final a in ringAtoms) {
      bonds.add(_Bond(core, a, _teal));
    }
    for (int i = 0; i < ringAtoms.length; i++) {
      bonds.add(_Bond(
        ringAtoms[i],
        ringAtoms[(i + 1) % ringAtoms.length],
        _blue,
      ));
    }
    for (final sat in satellites) {
      // attach to nearest ring atom
      _Atom? closest;
      var bestDist = double.infinity;
      for (final r in ringAtoms) {
        final dx = sat.pos3.x - r.pos3.x;
        final dy = sat.pos3.y - r.pos3.y;
        final dz = sat.pos3.z - r.pos3.z;
        final d = dx * dx + dy * dy + dz * dz;
        if (d < bestDist) {
          bestDist = d;
          closest = r;
        }
      }
      if (closest != null) {
        bonds.add(_Bond(closest, sat, _violet));
      }
    }

    // Painter's algorithm — draw back-to-front
    bonds.sort((a, b) =>
        ((a.a.pos3.z + a.b.pos3.z) / 2).compareTo((b.a.pos3.z + b.b.pos3.z) / 2));
    for (final b in bonds) {
      final d = ((b.a.depth + b.b.depth) / 2);
      final glow = 0.35 + 0.5 * d + 0.12 * pulse;
      final paint = Paint()
        ..color = b.color.withOpacity(glow.clamp(0.0, 0.9))
        ..strokeWidth = 1.2 + 0.9 * d
        ..strokeCap = StrokeCap.round;
      canvas.drawLine(b.a.screen, b.b.screen, paint);
    }

    atoms.sort((a, b) => a.pos3.z.compareTo(b.pos3.z));
    for (final a in atoms) {
      _drawAtom(canvas, a, pulse);
    }
  }

  void _drawAtom(Canvas canvas, _Atom a, double pulse) {
    final d = a.depth.clamp(0.0, 1.0);
    final scale = 0.65 + 0.6 * d;
    final r = a.radius * scale * (a.isCore ? (1.0 + 0.04 * pulse) : 1.0);

    // Outer glow
    canvas.drawCircle(
      a.screen,
      r + 6,
      Paint()
        ..color = a.color.withOpacity(0.10 + 0.18 * d)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6),
    );
    // Soft halo
    canvas.drawCircle(
      a.screen,
      r + 2,
      Paint()..color = a.color.withOpacity(0.22 * d + 0.10),
    );
    // Body
    canvas.drawCircle(
      a.screen,
      r,
      Paint()..color = a.color.withOpacity(0.55 + 0.45 * d),
    );
    // Specular highlight
    canvas.drawCircle(
      a.screen.translate(-r * 0.3, -r * 0.3),
      r * 0.45,
      Paint()..color = Colors.white.withOpacity(0.35 + 0.4 * d),
    );
  }

  @override
  bool shouldRepaint(_MoleculePainter old) =>
      old.spin != spin || old.pulse != pulse;
}

class _V3 {
  final double x, y, z;
  const _V3(this.x, this.y, this.z);
}

class _Atom {
  final _V3 pos3;
  final Color color;
  final double radius;
  final bool isCore;
  Offset screen = Offset.zero;
  double depth = 0.5;
  _Atom({
    required this.pos3,
    required this.color,
    required this.radius,
    this.isCore = false,
  });
}

class _Bond {
  final _Atom a;
  final _Atom b;
  final Color color;
  _Bond(this.a, this.b, this.color);
}
