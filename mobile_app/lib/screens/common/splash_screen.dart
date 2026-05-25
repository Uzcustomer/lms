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

/// Organic molecule (benzene ring + side chain + hydrogens), rotated
/// around the Y axis. Built in 3D first, then projected — so the ring
/// reads as an ellipse, not a flat horizontal line.
class _MoleculePainter extends CustomPainter {
  final double spin;
  final double pulse;
  _MoleculePainter({required this.spin, required this.pulse});

  static const _carbon = Color(0xFFEF4444); // red
  static const _hydrogen = Color(0xFF3B82F6); // blue
  static const _oxygen = Color(0xFF22C55E); // green
  static const _nitrogen = Color(0xFFA78BFA); // violet
  static const _bond = Color(0xFFE2E8F0);

  @override
  void paint(Canvas canvas, Size size) {
    final cx = size.width / 2;
    final cy = size.height / 2;
    final unit = size.width * 0.085;

    final atoms = <_Atom>[];
    final bonds = <List<int>>[];

    int add(_Atom a) {
      atoms.add(a);
      return atoms.length - 1;
    }

    void bond(int i, int j) => bonds.add([i, j]);

    // ── Benzene ring in the XY plane, centered around (-1.4*unit, 0, 0) ──
    final ringCenter = _V3(-1.6 * unit, 0, 0);
    const ringR = 1.0;
    final ringIdx = <int>[];
    for (int i = 0; i < 6; i++) {
      final a = i * math.pi / 3 + math.pi / 6;
      final x = ringCenter.x + ringR * unit * math.cos(a);
      final y = ringCenter.y + ringR * unit * math.sin(a);
      const z = 0.0;
      ringIdx.add(add(_Atom(
        pos3: _V3(x, y, z),
        color: _carbon,
        radius: 11,
      )));
    }
    for (int i = 0; i < 6; i++) {
      bond(ringIdx[i], ringIdx[(i + 1) % 6]);
    }

    // Hydrogens sticking out from each ring atom (radially outward)
    for (int i = 0; i < 6; i++) {
      final a = i * math.pi / 3 + math.pi / 6;
      final hx = ringCenter.x + 1.9 * unit * math.cos(a);
      final hy = ringCenter.y + 1.9 * unit * math.sin(a);
      // skip the ring atom we'll attach the side chain to
      if (i == 0) continue;
      final hIdx = add(_Atom(
        pos3: _V3(hx, hy, 0),
        color: _hydrogen,
        radius: 5.5,
      ));
      bond(ringIdx[i], hIdx);
    }

    // Two oxygens on the lower-left of the ring (catechol-like)
    final o1 = add(_Atom(
      pos3: _V3(ringCenter.x - 1.7 * unit, -1.5 * unit, 0.3 * unit),
      color: _oxygen,
      radius: 9,
    ));
    bond(ringIdx[3], o1);
    final o2 = add(_Atom(
      pos3: _V3(ringCenter.x - 0.6 * unit, -2.0 * unit, -0.3 * unit),
      color: _oxygen,
      radius: 9,
    ));
    bond(ringIdx[4], o2);

    // ── Side chain extending to the right ──
    // First carbon attached to ring atom 0 (rightmost)
    final c1Pos = _V3(ringCenter.x + 2.0 * unit, 0.4 * unit, 0.4 * unit);
    final c1 = add(_Atom(pos3: c1Pos, color: _carbon, radius: 11));
    bond(ringIdx[0], c1);

    // Second carbon
    final c2Pos = _V3(c1Pos.x + 1.1 * unit, -0.3 * unit, -0.3 * unit);
    final c2 = add(_Atom(pos3: c2Pos, color: _carbon, radius: 11));
    bond(c1, c2);

    // Nitrogen end group
    final nPos = _V3(c2Pos.x + 1.2 * unit, 0.3 * unit, 0.4 * unit);
    final n = add(_Atom(pos3: nPos, color: _nitrogen, radius: 11));
    bond(c2, n);

    // Hydrogens around side-chain carbons
    void attachH(int parent, _V3 pos) {
      final h = add(_Atom(pos3: pos, color: _hydrogen, radius: 5.5));
      bond(parent, h);
    }

    attachH(c1, _V3(c1Pos.x + 0.2 * unit, c1Pos.y + 1.0 * unit, c1Pos.z));
    attachH(c1, _V3(c1Pos.x, c1Pos.y - 0.4 * unit, c1Pos.z - 1.0 * unit));
    attachH(c2, _V3(c2Pos.x - 0.2 * unit, c2Pos.y + 1.0 * unit, c2Pos.z + 0.2 * unit));
    attachH(c2, _V3(c2Pos.x + 0.1 * unit, c2Pos.y - 0.9 * unit, c2Pos.z - 0.3 * unit));

    // Hydrogens around nitrogen
    attachH(n, _V3(nPos.x + 1.0 * unit, nPos.y + 0.5 * unit, nPos.z));
    attachH(n, _V3(nPos.x + 0.4 * unit, nPos.y - 0.6 * unit, nPos.z + 0.9 * unit));
    attachH(n, _V3(nPos.x + 0.3 * unit, nPos.y - 0.5 * unit, nPos.z - 0.9 * unit));

    // ── Rotate every atom around the Y axis ──
    final phase = spin * math.pi * 2;
    final cosP = math.cos(phase);
    final sinP = math.sin(phase);

    double maxAbsZ = 0.1;
    for (final a in atoms) {
      final x = a.pos3.x * cosP + a.pos3.z * sinP;
      final z = -a.pos3.x * sinP + a.pos3.z * cosP;
      a.rot = _V3(x, a.pos3.y, z);
      if (z.abs() > maxAbsZ) maxAbsZ = z.abs();
    }
    // Tilt slightly forward around X axis so the ring shows as an ellipse
    const tilt = 0.32;
    final cosT = math.cos(tilt);
    final sinT = math.sin(tilt);
    for (final a in atoms) {
      final y = a.rot.y * cosT - a.rot.z * sinT;
      final z = a.rot.y * sinT + a.rot.z * cosT;
      a.rot = _V3(a.rot.x, y, z);
    }

    // Perspective projection
    const focal = 600.0;
    for (final a in atoms) {
      final dist = focal + a.rot.z;
      final scale = focal / dist;
      a.screen = Offset(cx + a.rot.x * scale, cy + a.rot.y * scale);
      a.depth = (0.5 + 0.5 * (a.rot.z / (maxAbsZ + 1))).clamp(0.0, 1.0);
      a.scale = scale;
    }

    // Bonds, sorted by midpoint depth
    final orderedBonds = List.of(bonds);
    orderedBonds.sort((a, b) {
      final za = (atoms[a[0]].rot.z + atoms[a[1]].rot.z) / 2;
      final zb = (atoms[b[0]].rot.z + atoms[b[1]].rot.z) / 2;
      return za.compareTo(zb);
    });
    for (final b in orderedBonds) {
      final a1 = atoms[b[0]];
      final a2 = atoms[b[1]];
      final d = ((a1.depth + a2.depth) / 2);
      final paint = Paint()
        ..color = _bond.withOpacity((0.18 + 0.55 * d).clamp(0.0, 0.85))
        ..strokeWidth = 1.6 + 1.0 * d
        ..strokeCap = StrokeCap.round;
      canvas.drawLine(a1.screen, a2.screen, paint);
    }

    // Atoms, sorted back-to-front
    final orderedAtoms = List.of(atoms);
    orderedAtoms.sort((a, b) => a.rot.z.compareTo(b.rot.z));
    for (final a in orderedAtoms) {
      _drawAtom(canvas, a, pulse);
    }
  }

  void _drawAtom(Canvas canvas, _Atom a, double pulse) {
    final d = a.depth;
    final r = a.radius * a.scale * (a.isCore ? (1.0 + 0.04 * pulse) : 1.0);

    canvas.drawCircle(
      a.screen,
      r + 6,
      Paint()
        ..color = a.color.withOpacity(0.08 + 0.14 * d)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 5),
    );
    canvas.drawCircle(
      a.screen,
      r + 1.5,
      Paint()..color = a.color.withOpacity(0.22 * d + 0.08),
    );
    canvas.drawCircle(
      a.screen,
      r,
      Paint()..color = a.color.withOpacity(0.65 + 0.35 * d),
    );
    canvas.drawCircle(
      a.screen.translate(-r * 0.32, -r * 0.32),
      r * 0.42,
      Paint()..color = Colors.white.withOpacity(0.35 + 0.45 * d),
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
  _V3 rot = const _V3(0, 0, 0);
  Offset screen = Offset.zero;
  double depth = 0.5;
  double scale = 1.0;
  _Atom({
    required this.pos3,
    required this.color,
    required this.radius,
    this.isCore = false,
  });
}

