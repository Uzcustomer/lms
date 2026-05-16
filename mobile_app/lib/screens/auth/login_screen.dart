import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../utils/page_transitions.dart';
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

  Color get _accent =>
      _role == _Role.student ? const Color(0xFF1E3A8A) : const Color(0xFF0F766E);
  Color get _accentSoft =>
      _role == _Role.student ? const Color(0xFF2950C8) : const Color(0xFF14B8A6);
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
    final screenH = MediaQuery.of(context).size.height;
    final heroH = screenH * 0.36;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F8FB),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Hero(
              accent: _accent,
              accentSoft: _accentSoft,
              topPadding: safeTop,
              height: heroH,
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 14, 24, 0),
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
                        fontWeight: FontWeight.w700,
                        letterSpacing: -0.6,
                        color: _ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _isStudent
                          ? 'Talaba portaliga kirish'
                          : 'Xodimlar portaliga kirish',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                          fontSize: 13, color: _ink.withOpacity(0.6)),
                    ),
                    const SizedBox(height: 16),
                    _buildRoleTabs(),
                    const SizedBox(height: 16),
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
                    const SizedBox(height: 14),
                    _buildOrDivider(),
                    const SizedBox(height: 14),
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
          _tabButton('Talaba', _Role.student),
          const SizedBox(width: 4),
          _tabButton('Xodim', _Role.staff),
        ],
      ),
    );
  }

  Widget _tabButton(String label, _Role r) {
    final on = _role == r;
    final color =
        r == _Role.student ? const Color(0xFF1E3A8A) : const Color(0xFF0F766E);
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
          child: Text(
            label,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: on ? color : _ink.withOpacity(0.55),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildIdField() {
    return _FieldShell(
      label: 'LOGIN',
      child: TextFormField(
        controller: _idCtrl,
        keyboardType: TextInputType.visiblePassword,
        autocorrect: false,
        enableSuggestions: false,
        cursorColor: _accent,
        style: const TextStyle(
          fontSize: 15,
          fontWeight: FontWeight.w600,
          color: _ink,
        ),
        validator: (v) {
          if (v == null || v.trim().isEmpty) return 'Login kiriting';
          return null;
        },
        decoration: const InputDecoration(
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
        ),
      ),
    );
  }

  Widget _buildPasswordField() {
    return _FieldShell(
      label: 'PASSWORD',
      trailing: GestureDetector(
        onTap: () => ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
                "Parolni tiklash uchun universitet IT-bo'limiga murojaat qiling."),
          ),
        ),
        child: Text(
          'Unutdingizmi?',
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            color: _accent,
          ),
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: TextFormField(
              controller: _pwCtrl,
              obscureText: !_showPw,
              autocorrect: false,
              enableSuggestions: false,
              cursorColor: _accent,
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w600,
                color: _ink,
              ),
              validator: (v) {
                if (v == null || v.isEmpty) return 'Parol kiriting';
                return null;
              },
              decoration: const InputDecoration(
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
              ),
            ),
          ),
          GestureDetector(
            onTap: () => setState(() => _showPw = !_showPw),
            child: Icon(
              _showPw
                  ? Icons.visibility_off_outlined
                  : Icons.visibility_outlined,
              size: 18,
              color: _ink.withOpacity(0.55),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRememberCheckbox() {
    return GestureDetector(
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
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: _ink,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSubmitButton() {
    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        final loading = auth.state == AuthState.loading;
        return InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: loading ? null : _submit,
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 14),
            decoration: BoxDecoration(
              color: _accent.withOpacity(loading ? 0.7 : 1),
              borderRadius: BorderRadius.circular(14),
              boxShadow: [
                BoxShadow(
                  color: _accent.withOpacity(0.33),
                  blurRadius: 24,
                  offset: const Offset(0, 10),
                ),
              ],
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
                      valueColor: AlwaysStoppedAnimation(Colors.white),
                    ),
                  ),
                  const SizedBox(width: 8),
                ],
                Text(
                  loading ? 'Tekshirilmoqda…' : 'Tizimga kirish',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.3,
                  ),
                ),
              ],
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
            fontWeight: FontWeight.w700,
            letterSpacing: 1,
            color: _ink.withOpacity(0.45),
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
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(color: _accent, width: 1.5),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 24,
              height: 24,
              decoration: BoxDecoration(
                color: _accent,
                borderRadius: BorderRadius.circular(6),
              ),
              alignment: Alignment.center,
              child: const Icon(
                Icons.face_outlined,
                color: Colors.white,
                size: 16,
              ),
            ),
            const SizedBox(width: 10),
            Text(
              'Face ID orqali kirish',
              style: TextStyle(
                color: _accent,
                fontSize: 13.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  final Color accent;
  final Color accentSoft;
  final double topPadding;
  final double height;
  const _Hero({
    required this.accent,
    required this.accentSoft,
    required this.topPadding,
    required this.height,
  });

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: const BorderRadius.only(
        bottomLeft: Radius.circular(36),
        bottomRight: Radius.circular(36),
      ),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 220),
        height: height,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [accent, accentSoft],
          ),
        ),
        child: Stack(
          children: [
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: CustomPaint(
                size: Size(MediaQuery.of(context).size.width, height * 0.82),
                painter: _BuildingPainter(
                  color: Colors.white.withOpacity(0.10),
                ),
              ),
            ),
            Positioned.fill(
              child: Padding(
                padding: EdgeInsets.fromLTRB(24, topPadding + 24, 24, 28),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.start,
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    const SizedBox(height: 12),
                    Container(
                      width: 72,
                      height: 72,
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.16),
                        border: Border.all(
                            color: Colors.white.withOpacity(0.45), width: 1.5),
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: const Icon(Icons.school_rounded,
                          color: Colors.white, size: 36),
                    ),
                    const SizedBox(height: 18),
                    const Text(
                      'TASHMEDUNITF - LMS',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        letterSpacing: 2.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Toshkent Davlat Tibbiyot Universiteti\nTermiz filiali',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.75),
                        fontSize: 11.5,
                        letterSpacing: 1,
                        fontWeight: FontWeight.w500,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _BuildingPainter extends CustomPainter {
  final Color color;
  _BuildingPainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = color;
    const pillarCount = 11;
    final pillarW = size.width * 0.042;
    final gapX = size.width * 0.027;
    final totalW = pillarCount * pillarW + (pillarCount - 1) * gapX;
    final startX = (size.width - totalW) / 2;

    final pillarH = size.height * 0.62;
    final pillarTopY = size.height - pillarH;

    final beamH = size.height * 0.045;
    final beamGap = size.height * 0.04;
    final beamY = pillarTopY - beamGap - beamH;

    final roofPeakY = beamY - size.height * 0.20;

    for (int i = 0; i < pillarCount; i++) {
      final x = startX + i * (pillarW + gapX);
      final rect = RRect.fromRectAndRadius(
        Rect.fromLTWH(x, pillarTopY, pillarW, pillarH),
        const Radius.circular(2),
      );
      canvas.drawRRect(rect, paint);
    }

    final beamRect = Rect.fromLTWH(
      startX - gapX * 2,
      beamY,
      totalW + gapX * 4,
      beamH,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(beamRect, const Radius.circular(2)),
      paint,
    );

    final roofPath = Path()
      ..moveTo(startX - gapX * 2, beamY)
      ..lineTo(startX + totalW / 2, roofPeakY)
      ..lineTo(startX + totalW + gapX * 2, beamY)
      ..close();
    canvas.drawPath(roofPath, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _FieldShell extends StatelessWidget {
  final String label;
  final Widget child;
  final Widget? trailing;
  const _FieldShell({required this.label, required this.child, this.trailing});

  @override
  Widget build(BuildContext context) {
    const ink = Color(0xFF0F1B3D);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: ink.withOpacity(0.10)),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  color: ink.withOpacity(0.5),
                  letterSpacing: 1,
                ),
              ),
              if (trailing != null) trailing!,
            ],
          ),
          const SizedBox(height: 2),
          child,
        ],
      ),
    );
  }
}
