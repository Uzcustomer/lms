import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../services/biometric_service.dart';
import '../../utils/page_transitions.dart';
import '../../widgets/clinic_header.dart';
import 'face_login_screen.dart';
import 'verify_2fa_screen.dart';

enum _Role { student, staff }

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  _Role _role = _Role.student;
  final _formKey = GlobalKey<FormState>();
  final _idCtrl = TextEditingController();
  final _pwCtrl = TextEditingController();
  bool _showPw = false;
  bool _remember = true;

  static const _ink = Color(0xFF0F1B3D);
  static const _accent = Color(0xFF0D9488); // teal
  static const _accentDeep = Color(0xFF1E3A8A); // navy

  bool get _isStudent => _role == _Role.student;

  final _bio = BiometricService();
  bool _bioReady = false;
  bool _bioBusy = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _checkBiometric();
    });
  }

  @override
  void dispose() {
    _idCtrl.dispose();
    _pwCtrl.dispose();
    super.dispose();
  }

  Future<void> _checkBiometric({bool autoPrompt = false}) async {
    final ready = await _bio.isEnabled() &&
        await _bio.isAvailable() &&
        await _bio.hasCredentials();
    if (!mounted) return;
    setState(() => _bioReady = ready);
    if (ready && autoPrompt) _biometricLogin();
  }

  /// Re-logs in using the stored credentials behind a biometric check.
  Future<void> _biometricLogin() async {
    if (_bioBusy) return;
    final creds = await _bio.getCredentials();
    if (creds == null || !mounted) return;

    _bioBusy = true;
    final ok = await _bio.authenticate(
      reason: 'Ilovaga kirish uchun qurilma himoyasini tasdiqlang',
    );
    _bioBusy = false;
    if (!ok || !mounted) return;

    final auth = context.read<AuthProvider>();
    auth.clearError();
    final login = creds['login']!;
    if (creds['role'] == 'staff') {
      await auth.teacherLogin(login, creds['password']!);
    } else {
      await auth.studentLogin(login, creds['password']!);
    }
    if (!mounted) return;
    if (auth.state == AuthState.requires2fa) {
      Navigator.of(context).push(
        SlideFadePageRoute(builder: (_) => Verify2faScreen(login: login)),
      );
    }
  }

  Future<void> _submit() async {
    final auth = context.read<AuthProvider>();
    auth.clearError();
    if (!_formKey.currentState!.validate()) return;

    final login = _idCtrl.text.trim();
    final password = _pwCtrl.text;

    if (_isStudent) {
      await auth.studentLogin(login, password);
    } else {
      await auth.teacherLogin(login, password);
    }

    if (!mounted) return;

    if (auth.state == AuthState.authenticated ||
        auth.state == AuthState.profileIncomplete) {
      if (_remember) {
        // Stash the latest account so biometric re-login cannot reuse an old one.
        await _bio.saveCredentials(
          login: login,
          password: password,
          role: _isStudent ? 'student' : 'staff',
        );
      } else {
        await _bio.clearCredentials();
      }
    }

    if (!mounted) return;

    if (auth.state == AuthState.requires2fa) {
      Navigator.of(context).push(
        SlideFadePageRoute(
          builder: (_) => Verify2faScreen(login: login),
        ),
      );
    }
  }

  Future<void> _faceIdLogin() async {
    // If device biometric login is set up, use it.
    if (_bioReady) {
      _biometricLogin();
      return;
    }

    if (!_isStudent) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tezkor kirish faqat talabalar uchun')),
      );
      return;
    }

    final login = _idCtrl.text.trim();
    if (login.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Avval Login (talaba ID) ni kiriting')),
      );
      return;
    }

    Navigator.of(context).push(
      SlideFadePageRoute(
        builder: (_) => FaceLoginScreen(login: login),
      ),
    );
  }

  void _onRoleChanged(_Role r) {
    if (_role == r) return;
    setState(() {
      _role = r;
      _idCtrl.clear();
      _pwCtrl.clear();
    });
    context.read<AuthProvider>().clearError();
  }

  @override
  Widget build(BuildContext context) {
    final safeTop = MediaQuery.of(context).padding.top;
    final safeBottom = MediaQuery.of(context).padding.bottom;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F8FB),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Hero(topPadding: safeTop),
            Padding(
              padding: const EdgeInsets.fromLTRB(22, 18, 22, 0),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const SizedBox(height: 4),
                    _buildRoleTabs(),
                    const SizedBox(height: 14),
                    _buildIdField(),
                    const SizedBox(height: 10),
                    _buildPasswordField(),
                    const SizedBox(height: 6),
                    Align(
                      alignment: Alignment.centerRight,
                      child: GestureDetector(
                        onTap: () => ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                            content: Text(
                                "Parolni tiklash uchun universitet IT-bo'limiga murojaat qiling."),
                          ),
                        ),
                        child: const Padding(
                          padding: EdgeInsets.symmetric(vertical: 4, horizontal: 2),
                          child: Text(
                            'Parolni unutdingizmi?',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: _accent,
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    _buildRememberCheckbox(),
                    Consumer<AuthProvider>(
                      builder: (context, auth, _) {
                        if (auth.errorMessage == null) {
                          return const SizedBox(height: 14);
                        }
                        return Padding(
                          padding: const EdgeInsets.only(top: 12, bottom: 14),
                          child: Container(
                            width: double.infinity,
                            padding: const EdgeInsets.symmetric(
                                horizontal: 12, vertical: 10),
                            decoration: BoxDecoration(
                              color: const Color(0xFFDC2626).withOpacity(0.08),
                              border: Border.all(
                                  color: const Color(0xFFDC2626)
                                      .withOpacity(0.25)),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              auth.errorMessage!,
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFFB91C1C),
                              ),
                            ),
                          ),
                        );
                      },
                    ),
                    _buildSubmitButton(),
                    const SizedBox(height: 16),
                    _buildOrDivider(),
                    const SizedBox(height: 16),
                    _buildFaceIdButton(),
                    SizedBox(height: 16 + safeBottom),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRoleTabs() {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: const Color(0xFFEAEEF6),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _ink.withOpacity(0.06)),
      ),
      child: Row(
        children: [
          _tabButton('Talaba', Icons.school_outlined, _Role.student),
          const SizedBox(width: 4),
          _tabButton('Xodim', Icons.badge_outlined, _Role.staff),
        ],
      ),
    );
  }

  Widget _tabButton(String label, IconData icon, _Role r) {
    final on = _role == r;
    return Expanded(
      child: GestureDetector(
        onTap: () => _onRoleChanged(r),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(vertical: 14),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: on ? Colors.white : Colors.transparent,
            borderRadius: BorderRadius.circular(9),
            boxShadow: on
                ? [
                    BoxShadow(
                      color: _ink.withOpacity(0.06),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ]
                : null,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon,
                  size: 20, color: on ? _accent : _ink.withOpacity(0.5)),
              const SizedBox(width: 8),
              Text(
                label,
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: on ? _accent : _ink.withOpacity(0.55),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildIdField() {
    return _FieldShell(
      icon: Icons.person_outline_rounded,
      trailing: _idCtrl.text.trim().isNotEmpty
          ? Container(
              width: 20,
              height: 20,
              decoration: const BoxDecoration(
                color: _accent,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.check, size: 13, color: Colors.white),
            )
          : null,
      child: TextFormField(
        controller: _idCtrl,
        keyboardType: TextInputType.visiblePassword,
        autocorrect: false,
        enableSuggestions: false,
        cursorColor: _accent,
        onChanged: (_) => setState(() {}),
        style: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w700,
          color: _ink,
        ),
        validator: (v) {
          if (v == null || v.trim().isEmpty) return 'Login kiriting';
          return null;
        },
        decoration: _inputDecoration.copyWith(hintText: 'ID raqam'),
      ),
    );
  }

  Widget _buildPasswordField() {
    return _FieldShell(
      icon: Icons.lock_outline_rounded,
      trailing: GestureDetector(
        onTap: () => setState(() => _showPw = !_showPw),
        child: Icon(
          _showPw
              ? Icons.visibility_off_outlined
              : Icons.visibility_outlined,
          size: 19,
          color: _ink.withOpacity(0.5),
        ),
      ),
      child: TextFormField(
        controller: _pwCtrl,
        obscureText: !_showPw,
        autocorrect: false,
        enableSuggestions: false,
        cursorColor: _accent,
        style: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w700,
          color: _ink,
        ),
        validator: (v) {
          if (v == null || v.isEmpty) return 'Parol kiriting';
          return null;
        },
        decoration: _inputDecoration.copyWith(hintText: 'Parol'),
      ),
    );
  }

  static final _inputDecoration = InputDecoration(
    isDense: true,
    filled: true,
    fillColor: Colors.white,
    contentPadding: EdgeInsets.zero,
    border: InputBorder.none,
    enabledBorder: InputBorder.none,
    focusedBorder: InputBorder.none,
    errorBorder: InputBorder.none,
    focusedErrorBorder: InputBorder.none,
    hintStyle: TextStyle(
      fontSize: 13.5,
      fontWeight: FontWeight.w500,
      color: const Color(0xFF0F1B3D).withOpacity(0.35),
    ),
    errorStyle: const TextStyle(
      fontSize: 11,
      color: Color(0xFFB91C1C),
      height: 1.2,
    ),
  );

  Widget _buildRememberCheckbox() {
    return Row(
      children: [
        GestureDetector(
          onTap: () => setState(() => _remember = !_remember),
          behavior: HitTestBehavior.opaque,
          child: Row(
            children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                width: 18,
                height: 18,
                decoration: BoxDecoration(
                  color: _remember ? _accent : Colors.white,
                  borderRadius: BorderRadius.circular(5),
                  border: _remember
                      ? null
                      : Border.all(color: _ink.withOpacity(0.25), width: 1.5),
                ),
                child: _remember
                    ? const Icon(Icons.check, size: 12, color: Colors.white)
                    : null,
              ),
              const SizedBox(width: 8),
              const Text(
                'Meni eslab qol',
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: _ink,
                ),
              ),
            ],
          ),
        ),
        const Spacer(),
        Text(
          '30 kun davomida',
          style: TextStyle(fontSize: 11, color: _ink.withOpacity(0.45)),
        ),
      ],
    );
  }

  Widget _buildSubmitButton() {
    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        final loading = auth.state == AuthState.loading;
        return Opacity(
          opacity: loading ? 0.75 : 1,
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: loading ? null : _submit,
            child: Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(14),
                boxShadow: [
                  BoxShadow(
                    color: _accent.withOpacity(0.33),
                    blurRadius: 22,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(14),
                child: Stack(
                  children: [
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(vertical: 15),
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(
                          colors: [_accent, _accentDeep],
                          begin: Alignment.centerLeft,
                          end: Alignment.centerRight,
                        ),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          if (loading) ...[
                            const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.4,
                                valueColor:
                                    AlwaysStoppedAnimation(Colors.white),
                              ),
                            ),
                            const SizedBox(width: 8),
                          ],
                          Text(
                            loading ? 'Tekshirilmoqda…' : 'Tizimga kirish',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.3,
                            ),
                          ),
                          if (!loading) ...[
                            const SizedBox(width: 6),
                            const Icon(Icons.arrow_forward_rounded,
                                color: Colors.white, size: 17),
                          ],
                        ],
                      ),
                    ),
                    const Positioned.fill(
                      child: ShineOverlay(opacity: 0.28),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildOrDivider() {
    return Row(
      children: [
        Expanded(child: Container(height: 1, color: _ink.withOpacity(0.10))),
        const SizedBox(width: 10),
        Text(
          'YOKI',
          style: TextStyle(
            fontSize: 10.5,
            fontWeight: FontWeight.w800,
            letterSpacing: 1,
            color: _ink.withOpacity(0.4),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(child: Container(height: 1, color: _ink.withOpacity(0.10))),
      ],
    );
  }

  Widget _buildFaceIdButton() {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: _faceIdLogin,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 13, horizontal: 14),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(color: _ink.withOpacity(0.12), width: 1.4),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 26,
              height: 26,
              decoration: BoxDecoration(
                color: _accent.withOpacity(0.12),
                borderRadius: BorderRadius.circular(8),
              ),
              alignment: Alignment.center,
              child: const Icon(Icons.face_outlined, color: _accent, size: 17),
            ),
            const SizedBox(width: 10),
            const Text(
              'Biometrik kirish',
              style: TextStyle(
                color: _ink,
                fontSize: 13.5,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }

}

// ─────────────────────────────────────────────────────
// Hero header with an animated heart + EKG logo
// ─────────────────────────────────────────────────────
class _Hero extends StatelessWidget {
  final double topPadding;
  const _Hero({required this.topPadding});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: const BorderRadius.only(
        bottomLeft: Radius.circular(34),
        bottomRight: Radius.circular(34),
      ),
      child: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF0D9488), Color(0xFF1E3A8A)],
          ),
        ),
        child: Stack(
          alignment: Alignment.topCenter,
          children: [
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: CustomPaint(
                size: Size(MediaQuery.of(context).size.width, 380),
                painter: _BuildingPainter(color: Colors.white.withOpacity(0.13)),
              ),
            ),
            // Shimmer sweeps over the header, under the logo + texts.
            const Positioned.fill(child: ShineOverlay()),
            Padding(
              padding: EdgeInsets.fromLTRB(24, topPadding + 26, 24, 30),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const _HeartLogo(),
                  const SizedBox(height: 16),
                  const Text(
                    'TASHMEDUNITF · LMS',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 17,
                      letterSpacing: 2.2,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Text(
                    'Toshkent Davlat Tibbiyot Universiteti',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.92),
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    'Termiz filiali · 2018',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.8),
                      fontSize: 12.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// White heart logo with a realistic, looping ECG sweep.
class _HeartLogo extends StatefulWidget {
  const _HeartLogo();

  @override
  State<_HeartLogo> createState() => _HeartLogoState();
}

class _HeartLogoState extends State<_HeartLogo> with TickerProviderStateMixin {
  static const double _box = 156;
  static const double _heart = 112;

  late final AnimationController _beat;
  late final AnimationController _sweep;
  late final AnimationController _wave;
  late final Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _beat = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1300),
    )..repeat();
    _scale = TweenSequence<double>([
      TweenSequenceItem(tween: ConstantTween(1.0), weight: 14),
      TweenSequenceItem(
          tween: Tween(begin: 1.0, end: 1.20)
              .chain(CurveTween(curve: Curves.easeOut)),
          weight: 8),
      TweenSequenceItem(
          tween: Tween(begin: 1.20, end: 1.0)
              .chain(CurveTween(curve: Curves.easeIn)),
          weight: 10),
      TweenSequenceItem(
          tween: Tween(begin: 1.0, end: 1.10)
              .chain(CurveTween(curve: Curves.easeOut)),
          weight: 7),
      TweenSequenceItem(
          tween: Tween(begin: 1.10, end: 1.0)
              .chain(CurveTween(curve: Curves.easeIn)),
          weight: 9),
      TweenSequenceItem(tween: ConstantTween(1.0), weight: 52),
    ]).animate(_beat);
    _sweep = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1700),
    )..repeat();
    _wave = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2600),
    )..repeat();
  }

  @override
  void dispose() {
    _beat.dispose();
    _sweep.dispose();
    _wave.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: _box,
      height: _box,
      child: AnimatedBuilder(
        animation: Listenable.merge([_scale, _sweep, _wave]),
        builder: (_, __) {
          return Stack(
            alignment: Alignment.center,
            children: [
              // Expanding heart-shaped waves.
              for (int i = 0; i < 2; i++)
                _waveHeart((_wave.value + i / 2) % 1.0),
              // Beating white heart with the ECG sweep on top.
              Transform.scale(
                scale: _scale.value,
                child: SizedBox(
                  width: _heart + 10,
                  height: _heart,
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      Icon(
                        Icons.favorite_rounded,
                        color: Colors.white,
                        size: _heart,
                        shadows: [
                          Shadow(
                            color: Colors.black.withOpacity(0.25),
                            blurRadius: 14,
                            offset: const Offset(0, 6),
                          ),
                        ],
                      ),
                      Positioned.fill(
                        child: CustomPaint(painter: _EkgPainter(_sweep.value)),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _waveHeart(double t) {
    final opacity = (1 - t) * 0.4;
    if (opacity <= 0) return const SizedBox.shrink();
    return Transform.scale(
      scale: 0.94 + t * 0.55,
      child: Icon(
        Icons.favorite_border_rounded,
        color: Colors.white.withOpacity(opacity),
        size: _heart,
      ),
    );
  }
}

/// Realistic looping ECG monitor sweep — the trace is drawn left-to-right
/// by a glowing head, bright near the head and fading behind it.
class _EkgPainter extends CustomPainter {
  final double progress;
  const _EkgPainter(this.progress);

  static const Color _red = Color(0xFFE53935);

  // Flat baseline with a small P wave then a sharp QRS complex.
  static const List<Offset> _pts = [
    Offset(0.02, 0.52), Offset(0.30, 0.52),
    Offset(0.36, 0.45), Offset(0.41, 0.58),
    Offset(0.46, 0.14), Offset(0.52, 0.90), Offset(0.57, 0.42),
    Offset(0.64, 0.52), Offset(0.98, 0.52),
  ];

  Path _buildPath(Size size) {
    final path = Path();
    for (var i = 0; i < _pts.length; i++) {
      final x = _pts[i].dx * size.width;
      final y = _pts[i].dy * size.height;
      i == 0 ? path.moveTo(x, y) : path.lineTo(x, y);
    }
    return path;
  }

  @override
  void paint(Canvas canvas, Size size) {
    final path = _buildPath(size);
    final metrics = path.computeMetrics().toList();
    if (metrics.isEmpty) return;
    final m = metrics.first;
    final len = m.length;
    final head = (progress * len).clamp(0.0, len);

    // Faint already-drawn trace.
    if (head > 0) {
      canvas.drawPath(
        m.extractPath(0, head),
        Paint()
          ..color = _red.withOpacity(0.26)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 2.6
          ..strokeCap = StrokeCap.round
          ..strokeJoin = StrokeJoin.round,
      );
    }
    // Bright recent segment behind the head.
    final brightTail = (head - len * 0.32).clamp(0.0, len);
    if (head > brightTail) {
      canvas.drawPath(
        m.extractPath(brightTail, head),
        Paint()
          ..color = _red
          ..style = PaintingStyle.stroke
          ..strokeWidth = 3.4
          ..strokeCap = StrokeCap.round
          ..strokeJoin = StrokeJoin.round,
      );
    }
    // Glowing sweep head.
    final tan = m.getTangentForOffset(head);
    if (tan != null) {
      canvas.drawCircle(
          tan.position, 7, Paint()..color = _red.withOpacity(0.22));
      canvas.drawCircle(tan.position, 3.4, Paint()..color = _red);
    }
  }

  @override
  bool shouldRepaint(_EkgPainter old) => old.progress != progress;
}

/// Classical medical-university facade — stepped base, columns, an
/// architrave and a triangular pediment.
class _BuildingPainter extends CustomPainter {
  final Color color;
  _BuildingPainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = color;
    final w = size.width;
    final h = size.height;
    final cx = w / 2;

    Rect centered(double width, double top, double height) =>
        Rect.fromLTWH(cx - width / 2, top, width, height);

    // ── Stepped base (widest ~92% of the width) ──
    final stepH = h * 0.03;
    const stepFrac = [0.92, 0.86, 0.80];
    for (int i = 0; i < 3; i++) {
      canvas.drawRect(
        centered(w * stepFrac[i], h - stepH * (i + 1), stepH),
        paint,
      );
    }
    final stepsTop = h - stepH * 3;

    // ── Columns ──
    const colCount = 9;
    final colH = h * 0.46;
    final colsTop = stepsTop - colH;
    final colSpan = w * 0.74;
    final colsLeft = cx - colSpan / 2;
    final gap = colSpan / colCount;
    final colW = gap * 0.56;
    for (int i = 0; i < colCount; i++) {
      final x = colsLeft + gap * i + (gap - colW) / 2;
      canvas.drawRect(
          Rect.fromLTWH(x, colsTop + colH * 0.08, colW, colH * 0.84), paint);
      // capital
      canvas.drawRect(
          Rect.fromLTWH(x - colW * 0.18, colsTop, colW * 1.36, colH * 0.08),
          paint);
      // base
      canvas.drawRect(
          Rect.fromLTWH(
              x - colW * 0.18, colsTop + colH * 0.92, colW * 1.36, colH * 0.08),
          paint);
    }

    // ── Architrave (beam) ──
    final beamH = h * 0.05;
    final beamW = w * 0.80;
    final beamY = colsTop - beamH;
    canvas.drawRect(centered(beamW, beamY, beamH), paint);

    // ── Triangular pediment ──
    final pedH = h * 0.19;
    final roof = Path()
      ..moveTo(cx - beamW / 2 - w * 0.012, beamY)
      ..lineTo(cx, beamY - pedH)
      ..lineTo(cx + beamW / 2 + w * 0.012, beamY)
      ..close();
    canvas.drawPath(roof, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _FieldShell extends StatelessWidget {
  final IconData icon;
  final String? label;
  final Widget? labelTrailing;
  final Widget child;
  final Widget? trailing;
  const _FieldShell({
    required this.icon,
    required this.child,
    this.label,
    this.labelTrailing,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    const ink = Color(0xFF0F1B3D);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: ink.withOpacity(0.10)),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        children: [
          Icon(icon, size: 22, color: ink.withOpacity(0.5)),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (labelTrailing != null)
                  Align(
                    alignment: Alignment.centerRight,
                    child: labelTrailing!,
                  ),
                child,
              ],
            ),
          ),
          if (trailing != null) ...[
            const SizedBox(width: 10),
            trailing!,
          ],
        ],
      ),
    );
  }
}
