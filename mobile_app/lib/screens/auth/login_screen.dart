import 'dart:math';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/settings_provider.dart';
import 'verify_2fa_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final _formKey = GlobalKey<FormState>();
  final _loginController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    _loginController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;

    final authProvider = context.read<AuthProvider>();
    authProvider.clearError();

    if (_tabController.index == 0) {
      await authProvider.studentLogin(
        _loginController.text.trim(),
        _passwordController.text,
      );
    } else {
      await authProvider.teacherLogin(
        _loginController.text.trim(),
        _passwordController.text,
      );
    }

    if (!mounted) return;

    if (authProvider.state == AuthState.requires2fa) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => Verify2faScreen(login: _loginController.text.trim()),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final size = MediaQuery.of(context).size;
    final headerH = size.height * 0.40;

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F8),
      resizeToAvoidBottomInset: true,
      body: SingleChildScrollView(
        child: Column(
          children: [
            // Header with multiple wave layers + patterns
            SizedBox(
              height: headerH,
              child: Stack(
                children: [
                  // Back wave layer (darker)
                  ClipPath(
                    clipper: _WaveClipper2(),
                    child: Container(
                      height: headerH,
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [
                            Color(0xFF3B3EBF),
                            Color(0xFF5048C8),
                          ],
                        ),
                      ),
                    ),
                  ),

                  // Front wave layer (main gradient)
                  ClipPath(
                    clipper: _WaveClipper1(),
                    child: Container(
                      height: headerH,
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomRight,
                          colors: [
                            Color(0xFF4A6CF7),
                            Color(0xFF6C63FF),
                            Color(0xFF7B68EE),
                          ],
                        ),
                      ),
                    ),
                  ),

                  // Dot grid pattern
                  Positioned(
                    top: 50,
                    right: 20,
                    child: CustomPaint(
                      size: const Size(80, 80),
                      painter: _DotGridPainter(
                        color: Colors.white.withOpacity(0.12),
                        dotSize: 2.5,
                        spacing: 14,
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: 70,
                    left: 15,
                    child: CustomPaint(
                      size: const Size(60, 60),
                      painter: _DotGridPainter(
                        color: Colors.white.withOpacity(0.08),
                        dotSize: 2,
                        spacing: 12,
                      ),
                    ),
                  ),

                  // Decorative rings
                  Positioned(
                    top: -40,
                    right: -40,
                    child: Container(
                      width: 160,
                      height: 160,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: Colors.white.withOpacity(0.1),
                          width: 2,
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    top: -20,
                    right: -20,
                    child: Container(
                      width: 110,
                      height: 110,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: Colors.white.withOpacity(0.06),
                          width: 1.5,
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    top: 30,
                    left: -25,
                    child: Container(
                      width: 70,
                      height: 70,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Colors.white.withOpacity(0.06),
                      ),
                    ),
                  ),

                  // Small floating circles
                  Positioned(
                    top: 80,
                    right: 60,
                    child: _circle(10, Colors.white.withOpacity(0.15)),
                  ),
                  Positioned(
                    top: 50,
                    left: 80,
                    child: _circle(6, Colors.white.withOpacity(0.12)),
                  ),
                  Positioned(
                    bottom: 90,
                    right: 100,
                    child: _circle(8, Colors.white.withOpacity(0.1)),
                  ),
                  Positioned(
                    bottom: 80,
                    left: 60,
                    child: _circle(14, Colors.white.withOpacity(0.05)),
                  ),
                  Positioned(
                    top: 110,
                    left: 40,
                    child: _circle(5, Colors.white.withOpacity(0.18)),
                  ),

                  // Diamond shapes
                  Positioned(
                    top: 65,
                    right: 130,
                    child: Transform.rotate(
                      angle: pi / 4,
                      child: Container(
                        width: 12,
                        height: 12,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: 100,
                    right: 50,
                    child: Transform.rotate(
                      angle: pi / 4,
                      child: Container(
                        width: 16,
                        height: 16,
                        decoration: BoxDecoration(
                          border: Border.all(
                            color: Colors.white.withOpacity(0.12),
                            width: 1.5,
                          ),
                          borderRadius: BorderRadius.circular(3),
                        ),
                      ),
                    ),
                  ),

                  // Plus signs
                  Positioned(
                    top: 100,
                    right: 40,
                    child: _plusSign(Colors.white.withOpacity(0.12), 10),
                  ),
                  Positioned(
                    bottom: 95,
                    left: 110,
                    child: _plusSign(Colors.white.withOpacity(0.08), 8),
                  ),

                  // Triangle outline
                  Positioned(
                    top: 75,
                    left: 120,
                    child: CustomPaint(
                      size: const Size(18, 18),
                      painter: _TrianglePainter(
                        color: Colors.white.withOpacity(0.1),
                      ),
                    ),
                  ),

                  // Logo and title
                  SafeArea(
                    child: Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const SizedBox(height: 8),
                          Container(
                            width: 72,
                            height: 72,
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.18),
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(
                                color: Colors.white.withOpacity(0.3),
                                width: 1.5,
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withOpacity(0.1),
                                  blurRadius: 16,
                                  offset: const Offset(0, 6),
                                ),
                              ],
                            ),
                            child: const Icon(
                              Icons.school_rounded,
                              size: 38,
                              color: Colors.white,
                            ),
                          ),
                          const SizedBox(height: 14),
                          const Text(
                            'TDTU LMS',
                            style: TextStyle(
                              fontSize: 26,
                              fontWeight: FontWeight.bold,
                              color: Colors.white,
                              letterSpacing: 2,
                            ),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            l.lmsSubtitle,
                            style: TextStyle(
                              fontSize: 13,
                              color: Colors.white.withOpacity(0.75),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // Form section
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 28),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 6),

                  // Tab selector
                  Container(
                    height: 50,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF4A6CF7).withOpacity(0.08),
                          blurRadius: 16,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: TabBar(
                      controller: _tabController,
                      indicator: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [
                            Color(0xFF4A6CF7),
                            Color(0xFF6C63FF),
                          ],
                        ),
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFF4A6CF7).withOpacity(0.3),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      labelColor: Colors.white,
                      unselectedLabelColor: const Color(0xFF8E8E93),
                      indicatorSize: TabBarIndicatorSize.tab,
                      dividerColor: Colors.transparent,
                      labelStyle: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                      ),
                      tabs: [
                        Tab(text: l.student),
                        Tab(text: l.teacher),
                      ],
                      onTap: (_) {
                        _loginController.clear();
                        _passwordController.clear();
                        context.read<AuthProvider>().clearError();
                      },
                    ),
                  ),
                  const SizedBox(height: 28),

                  // Login label
                  Text(
                    l.loginLabel,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF4A4A5A),
                    ),
                  ),
                  const SizedBox(height: 8),

                  // Form
                  Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _buildField(
                          controller: _loginController,
                          hint: l.loginHint,
                          icon: Icons.person_outline_rounded,
                          validator: (v) {
                            if (v == null || v.trim().isEmpty) {
                              return l.loginRequired;
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 20),

                        Text(
                          l.password,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF4A4A5A),
                          ),
                        ),
                        const SizedBox(height: 8),
                        _buildField(
                          controller: _passwordController,
                          hint: l.passwordHint,
                          icon: Icons.lock_outline_rounded,
                          obscure: _obscurePassword,
                          suffixIcon: GestureDetector(
                            onTap: () => setState(
                                () => _obscurePassword = !_obscurePassword),
                            child: Icon(
                              _obscurePassword
                                  ? Icons.visibility_off_outlined
                                  : Icons.visibility_outlined,
                              color: const Color(0xFFB0B0B8),
                              size: 20,
                            ),
                          ),
                          validator: (v) {
                            if (v == null || v.isEmpty) {
                              return l.passwordRequired;
                            }
                            return null;
                          },
                        ),

                        // Error message
                        Consumer<AuthProvider>(
                          builder: (context, auth, _) {
                            if (auth.errorMessage == null) {
                              return const SizedBox(height: 24);
                            }
                            return Padding(
                              padding:
                                  const EdgeInsets.only(top: 16, bottom: 8),
                              child: Container(
                                width: double.infinity,
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 14, vertical: 12),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFEECEC),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                    color: const Color(0xFFE53935)
                                        .withOpacity(0.2),
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    const Icon(Icons.error_outline_rounded,
                                        color: Color(0xFFE53935), size: 18),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        auth.errorMessage!,
                                        style: const TextStyle(
                                          color: Color(0xFFE53935),
                                          fontSize: 13,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),

                        // Login button
                        Consumer<AuthProvider>(
                          builder: (context, auth, _) {
                            final isLoading = auth.state == AuthState.loading;
                            return SizedBox(
                              width: double.infinity,
                              height: 54,
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  gradient: LinearGradient(
                                    colors: isLoading
                                        ? [
                                            const Color(0xFF4A6CF7)
                                                .withOpacity(0.5),
                                            const Color(0xFF6C63FF)
                                                .withOpacity(0.5),
                                          ]
                                        : const [
                                            Color(0xFF4A6CF7),
                                            Color(0xFF6C63FF),
                                          ],
                                  ),
                                  borderRadius: BorderRadius.circular(14),
                                  boxShadow: isLoading
                                      ? []
                                      : [
                                          BoxShadow(
                                            color: const Color(0xFF4A6CF7)
                                                .withOpacity(0.35),
                                            blurRadius: 16,
                                            offset: const Offset(0, 6),
                                          ),
                                        ],
                                ),
                                child: ElevatedButton(
                                  onPressed: isLoading ? null : _handleLogin,
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: Colors.transparent,
                                    shadowColor: Colors.transparent,
                                    disabledBackgroundColor: Colors.transparent,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(14),
                                    ),
                                  ),
                                  child: isLoading
                                      ? const SizedBox(
                                          width: 22,
                                          height: 22,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2.5,
                                            color: Colors.white,
                                          ),
                                        )
                                      : Row(
                                          mainAxisAlignment:
                                              MainAxisAlignment.center,
                                          children: [
                                            Text(
                                              l.signIn,
                                              style: const TextStyle(
                                                fontSize: 16,
                                                fontWeight: FontWeight.w700,
                                                color: Colors.white,
                                                letterSpacing: 0.5,
                                              ),
                                            ),
                                            const SizedBox(width: 8),
                                            const Icon(
                                              Icons.arrow_forward_rounded,
                                              color: Colors.white,
                                              size: 20,
                                            ),
                                          ],
                                        ),
                                ),
                              ),
                            );
                          },
                        ),
                        const SizedBox(height: 28),

                        // Footer
                        Center(
                          child: Text(
                            '© TDTU ${DateTime.now().year}',
                            style: const TextStyle(
                              fontSize: 12,
                              color: Color(0xFFB0B0B8),
                            ),
                          ),
                        ),
                        const SizedBox(height: 20),
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

  Widget _circle(double size, Color color) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(shape: BoxShape.circle, color: color),
    );
  }

  Widget _plusSign(Color color, double size) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(painter: _PlusPainter(color: color, strokeW: 1.5)),
    );
  }

  Widget _buildField({
    required TextEditingController controller,
    required String hint,
    required IconData icon,
    bool obscure = false,
    Widget? suffixIcon,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: controller,
      obscureText: obscure,
      style: const TextStyle(color: Color(0xFF2D2D3A), fontSize: 15),
      validator: validator,
      cursorColor: const Color(0xFF4A6CF7),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(color: Color(0xFFB0B0B8), fontSize: 14),
        prefixIcon: Icon(icon, color: const Color(0xFFB0B0B8), size: 20),
        suffixIcon: suffixIcon != null
            ? Padding(
                padding: const EdgeInsets.only(right: 12), child: suffixIcon)
            : null,
        filled: true,
        fillColor: Colors.white,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE8E8EE)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE8E8EE)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFF4A6CF7), width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE53935)),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE53935), width: 1.5),
        ),
        errorStyle: const TextStyle(color: Color(0xFFE53935), fontSize: 11),
      ),
    );
  }
}

// Front wave
class _WaveClipper1 extends CustomClipper<Path> {
  @override
  Path getClip(Size size) {
    final path = Path();
    path.lineTo(0, size.height * 0.72);

    path.cubicTo(
      size.width * 0.2, size.height * 0.92,
      size.width * 0.45, size.height * 0.68,
      size.width * 0.65, size.height * 0.78,
    );
    path.cubicTo(
      size.width * 0.85, size.height * 0.88,
      size.width * 0.95, size.height * 0.72,
      size.width, size.height * 0.80,
    );

    path.lineTo(size.width, 0);
    path.close();
    return path;
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}

// Back wave (slightly different shape)
class _WaveClipper2 extends CustomClipper<Path> {
  @override
  Path getClip(Size size) {
    final path = Path();
    path.lineTo(0, size.height * 0.78);

    path.cubicTo(
      size.width * 0.15, size.height * 0.65,
      size.width * 0.35, size.height * 0.98,
      size.width * 0.55, size.height * 0.85,
    );
    path.cubicTo(
      size.width * 0.75, size.height * 0.72,
      size.width * 0.9, size.height * 0.95,
      size.width, size.height * 0.88,
    );

    path.lineTo(size.width, 0);
    path.close();
    return path;
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}

// Dot grid painter
class _DotGridPainter extends CustomPainter {
  final Color color;
  final double dotSize;
  final double spacing;

  _DotGridPainter({
    required this.color,
    required this.dotSize,
    required this.spacing,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = color;
    for (double x = 0; x < size.width; x += spacing) {
      for (double y = 0; y < size.height; y += spacing) {
        canvas.drawCircle(Offset(x, y), dotSize / 2, paint);
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

// Plus sign painter
class _PlusPainter extends CustomPainter {
  final Color color;
  final double strokeW;

  _PlusPainter({required this.color, required this.strokeW});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = strokeW
      ..strokeCap = StrokeCap.round;
    canvas.drawLine(
      Offset(size.width / 2, 0),
      Offset(size.width / 2, size.height),
      paint,
    );
    canvas.drawLine(
      Offset(0, size.height / 2),
      Offset(size.width, size.height / 2),
      paint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

// Triangle outline painter
class _TrianglePainter extends CustomPainter {
  final Color color;

  _TrianglePainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = 1.5
      ..style = PaintingStyle.stroke;
    final path = Path()
      ..moveTo(size.width / 2, 0)
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
