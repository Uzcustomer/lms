import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
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

  @override
  void dispose() {
    _idCtrl.dispose();
    _pwCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final auth = context.read<AuthProvider>();
    auth.clearError();
    if (!_formKey.currentState!.validate()) return;

    if (_isStudent) {
      await auth.studentLogin(_idCtrl.text.trim(), _pwCtrl.text);
    } else {
      await auth.teacherLogin(_idCtrl.text.trim(), _pwCtrl.text);
    }

    if (!mounted) return;

    if (auth.state == AuthState.requires2fa) {
      Navigator.of(context).push(
        SlideFadePageRoute(
          builder: (_) => Verify2faScreen(login: _idCtrl.text.trim()),
        ),
      );
    }
  }

  Future<void> _faceIdLogin() async {
    if (!_isStudent) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Face ID faqat talabalar uchun')),
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
                    const Text(
                      'Xush kelibsiz',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.6,
                        color: _ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Hisobingizga kirib davom eting',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                          fontSize: 13, color: _ink.withOpacity(0.55)),
                    ),
                    const SizedBox(height: 18),
                    _buildRoleTabs(),
                    const SizedBox(height: 14),
                    _buildIdField(),
                    const SizedBox(height: 10),
                    _buildPasswordField(),
                    const SizedBox(height: 12),
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
                    const SizedBox(height: 18),
                    _buildFooter(),
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
          padding: const EdgeInsets.symmetric(vertical: 10),
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
                  size: 16, color: on ? _accent : _ink.withOpacity(0.5)),
              const SizedBox(width: 6),
              Text(
                label,
                style: TextStyle(
                  fontSize: 13,
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
      label: 'LOGIN · ID RAQAM',
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
          fontSize: 15,
          fontWeight: FontWeight.w700,
          color: _ink,
        ),
        validator: (v) {
          if (v == null || v.trim().isEmpty) return 'Login kiriting';
          return null;
        },
        decoration: _inputDecoration,
      ),
    );
  }

  Widget _buildPasswordField() {
    return _FieldShell(
      icon: Icons.lock_outline_rounded,
      label: 'PAROL',
      labelTrailing: GestureDetector(
        onTap: () => ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
                "Parolni tiklash uchun universitet IT-bo'limiga murojaat qiling."),
          ),
        ),
        child: const Text(
          'Unutdingizmi?',
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            color: _accent,
          ),
        ),
      ),
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
          fontSize: 15,
          fontWeight: FontWeight.w700,
          color: _ink,
        ),
        validator: (v) {
          if (v == null || v.isEmpty) return 'Parol kiriting';
          return null;
        },
        decoration: _inputDecoration,
      ),
    );
  }

  static const _inputDecoration = InputDecoration(
    isDense: true,
    filled: true,
    fillColor: Colors.white,
    contentPadding: EdgeInsets.zero,
    border: InputBorder.none,
    enabledBorder: InputBorder.none,
    focusedBorder: InputBorder.none,
    errorBorder: InputBorder.none,
    focusedErrorBorder: InputBorder.none,
    errorStyle: TextStyle(
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
              'Face ID orqali kirish',
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

  Widget _buildFooter() {
    return Column(
      children: [
        Text(
          "v2.4.1 · 256-bit SSL · Maxfiy ma'lumotlar himoyalangan",
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 10,
            fontWeight: FontWeight.w500,
            color: _ink.withOpacity(0.35),
          ),
        ),
        const SizedBox(height: 8),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            _footerLink('Yordam'),
            _footerDot(),
            _footerLink('Foydalanish shartlari'),
            _footerDot(),
            _footerLink('Maxfiylik'),
          ],
        ),
      ],
    );
  }

  Widget _footerLink(String text) => Text(
        text,
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: _accent,
        ),
      );

  Widget _footerDot() => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8),
        child: Text('·',
            style: TextStyle(fontSize: 11, color: _ink.withOpacity(0.35))),
      );
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
            colors: [Color(0xFF0B4A47), Color(0xFF0D9488), Color(0xFF19B6A6)],
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
                size: Size(MediaQuery.of(context).size.width, 300),
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
                    'TASHMEDUNI · LMS',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 14,
                      letterSpacing: 2.4,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Toshkent Davlat Tibbiyot Universiteti',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.9),
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Termiz filiali · 1991',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.7),
                      fontSize: 11,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 12, vertical: 5),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.16),
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: Colors.white.withOpacity(0.25)),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.favorite_rounded,
                            size: 11, color: Color(0xFF7DF0C8)),
                        SizedBox(width: 6),
                        Text(
                          'TIZIM ONLAYN',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            letterSpacing: 1.2,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
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

/// Heart-shaped white logo with a red border and a red EKG impulse.
class _HeartLogo extends StatefulWidget {
  const _HeartLogo();

  @override
  State<_HeartLogo> createState() => _HeartLogoState();
}

/// Heart outline that fills a [w]×[h] box (origin at 0,0).
Path _heartPath(double w, double h) {
  return Path()
    ..moveTo(w * 0.50, h * 0.96)
    ..cubicTo(w * 0.12, h * 0.70, w * 0.02, h * 0.34, w * 0.26, h * 0.17)
    ..cubicTo(w * 0.41, h * 0.05, w * 0.50, h * 0.14, w * 0.50, h * 0.30)
    ..cubicTo(w * 0.50, h * 0.14, w * 0.59, h * 0.05, w * 0.74, h * 0.17)
    ..cubicTo(w * 0.98, h * 0.34, w * 0.88, h * 0.70, w * 0.50, h * 0.96)
    ..close();
}

class _HeartLogoState extends State<_HeartLogo> with TickerProviderStateMixin {
  static const double _box = 150;

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
          tween: Tween(begin: 1.0, end: 1.22)
              .chain(CurveTween(curve: Curves.easeOut)),
          weight: 8),
      TweenSequenceItem(
          tween: Tween(begin: 1.22, end: 1.0)
              .chain(CurveTween(curve: Curves.easeIn)),
          weight: 10),
      TweenSequenceItem(
          tween: Tween(begin: 1.0, end: 1.12)
              .chain(CurveTween(curve: Curves.easeOut)),
          weight: 7),
      TweenSequenceItem(
          tween: Tween(begin: 1.12, end: 1.0)
              .chain(CurveTween(curve: Curves.easeIn)),
          weight: 9),
      TweenSequenceItem(tween: ConstantTween(1.0), weight: 52),
    ]).animate(_beat);
    _sweep = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2200),
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
      child: Stack(
        alignment: Alignment.center,
        children: [
          // Heart-shaped waves radiating outward.
          Positioned.fill(
            child: AnimatedBuilder(
              animation: _wave,
              builder: (_, __) => CustomPaint(
                painter: _WavePainter(_wave.value),
              ),
            ),
          ),
          // Beating heart logo.
          AnimatedBuilder(
            animation: Listenable.merge([_scale, _sweep]),
            builder: (_, __) => Transform.scale(
              scale: _scale.value,
              child: CustomPaint(
                size: const Size(108, 100),
                painter: _HeartLogoPainter(_sweep.value),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Heart-shaped waves expanding out from the logo.
class _WavePainter extends CustomPainter {
  final double progress;
  const _WavePainter(this.progress);

  @override
  void paint(Canvas canvas, Size size) {
    final cx = size.width / 2;
    final cy = size.height / 2;
    const count = 2;
    for (int i = 0; i < count; i++) {
      final t = (progress + i / count) % 1.0;
      final opacity = (1 - t) * 0.4;
      if (opacity <= 0) continue;
      final scale = 0.66 + t * 0.4;
      final hw = size.width * scale;
      final hh = size.height * scale;
      final heart =
          _heartPath(hw, hh).shift(Offset(cx - hw / 2, cy - hh / 2));
      canvas.drawPath(
        heart,
        Paint()
          ..color = Colors.white.withOpacity(opacity)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 2,
      );
    }
  }

  @override
  bool shouldRepaint(_WavePainter old) => old.progress != progress;
}

/// White heart with a red border and a single red EKG impulse inside.
class _HeartLogoPainter extends CustomPainter {
  final double sweep;
  const _HeartLogoPainter(this.sweep);

  static const Color _red = Color(0xFFE53935);

  @override
  void paint(Canvas canvas, Size size) {
    const pad = 5.0;
    final hw = size.width - pad * 2;
    final hh = size.height - pad * 2;
    final heart = _heartPath(hw, hh).shift(const Offset(pad, pad));

    canvas.drawShadow(heart, Colors.black, 5, false);
    canvas.drawPath(heart, Paint()..color = Colors.white);
    canvas.drawPath(
      heart,
      Paint()
        ..color = _red
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3.6
        ..strokeJoin = StrokeJoin.round,
    );

    // EKG impulse crossing the middle, clipped inside the heart.
    canvas.save();
    canvas.clipPath(heart);
    final baseY = pad + hh * 0.55;
    const pts = [
      Offset(0.00, 0.0), Offset(0.30, 0.0),
      Offset(0.39, -0.13), Offset(0.46, 0.24),
      Offset(0.53, -0.36), Offset(0.61, 0.15),
      Offset(0.70, 0.0), Offset(1.00, 0.0),
    ];
    final ekg = Path();
    for (var i = 0; i < pts.length; i++) {
      final x = pad + pts[i].dx * hw;
      final y = baseY + pts[i].dy * hh;
      i == 0 ? ekg.moveTo(x, y) : ekg.lineTo(x, y);
    }
    canvas.drawPath(
      ekg,
      Paint()
        ..color = _red
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );
    // Bright running highlight along the impulse.
    final metrics = ekg.computeMetrics().toList();
    if (metrics.isNotEmpty) {
      final m = metrics.first;
      final len = m.length;
      final head = sweep * len;
      final tail = (head - len * 0.26).clamp(0.0, len);
      canvas.drawPath(
        m.extractPath(tail, head.clamp(0.0, len)),
        Paint()
          ..color = const Color(0xFFFF7A7A)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 3.6
          ..strokeCap = StrokeCap.round
          ..strokeJoin = StrokeJoin.round,
      );
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(_HeartLogoPainter old) => old.sweep != sweep;
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
  final String label;
  final Widget? labelTrailing;
  final Widget child;
  final Widget? trailing;
  const _FieldShell({
    required this.icon,
    required this.label,
    required this.child,
    this.labelTrailing,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    const ink = Color(0xFF0F1B3D);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: ink.withOpacity(0.10)),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        children: [
          Icon(icon, size: 19, color: ink.withOpacity(0.4)),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      label,
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        color: ink.withOpacity(0.45),
                        letterSpacing: 0.8,
                      ),
                    ),
                    if (labelTrailing != null) ...[
                      const Spacer(),
                      labelTrailing!,
                    ],
                  ],
                ),
                const SizedBox(height: 1),
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
