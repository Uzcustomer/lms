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
    final bottom = MediaQuery.of(context).viewInsets.bottom;

    return Scaffold(
      resizeToAvoidBottomInset: false,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // Gradient background
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  Color(0xFF0A1628),
                  Color(0xFF0D3D6D),
                  Color(0xFF144E8A),
                  Color(0xFF0A1628),
                ],
                stops: [0.0, 0.35, 0.65, 1.0],
              ),
            ),
          ),

          // Decorative circles
          Positioned(
            top: -size.width * 0.3,
            right: -size.width * 0.2,
            child: Container(
              width: size.width * 0.7,
              height: size.width * 0.7,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    const Color(0xFF26A69A).withOpacity(0.15),
                    const Color(0xFF26A69A).withOpacity(0.0),
                  ],
                ),
              ),
            ),
          ),
          Positioned(
            bottom: -size.width * 0.25,
            left: -size.width * 0.25,
            child: Container(
              width: size.width * 0.6,
              height: size.width * 0.6,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    const Color(0xFF0D3D6D).withOpacity(0.3),
                    const Color(0xFF0D3D6D).withOpacity(0.0),
                  ],
                ),
              ),
            ),
          ),
          Positioned(
            top: size.height * 0.35,
            left: -40,
            child: Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    const Color(0xFF26A69A).withOpacity(0.08),
                    const Color(0xFF26A69A).withOpacity(0.0),
                  ],
                ),
              ),
            ),
          ),

          // Floating dots pattern
          ..._buildFloatingDots(size),

          // Main content
          SafeArea(
            child: SingleChildScrollView(
              padding: EdgeInsets.only(
                bottom: bottom > 0 ? bottom + 16 : 0,
              ),
              child: SizedBox(
                height: size.height -
                    MediaQuery.of(context).padding.top -
                    MediaQuery.of(context).padding.bottom,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 28),
                  child: Column(
                    children: [
                      const Spacer(flex: 3),

                      // Logo
                      Container(
                        width: 80,
                        height: 80,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(22),
                          gradient: const LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [
                              Color(0xFF26A69A),
                              Color(0xFF0D3D6D),
                            ],
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFF26A69A).withOpacity(0.3),
                              blurRadius: 20,
                              offset: const Offset(0, 8),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.school_rounded,
                          size: 42,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 16),
                      const Text(
                        'TDTU LMS',
                        style: TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                          letterSpacing: 2,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        l.lmsSubtitle,
                        style: TextStyle(
                          fontSize: 14,
                          color: Colors.white.withOpacity(0.5),
                          letterSpacing: 0.5,
                        ),
                      ),
                      const SizedBox(height: 36),

                      // Tab selector
                      Container(
                        height: 48,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.07),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: Colors.white.withOpacity(0.1),
                          ),
                        ),
                        child: TabBar(
                          controller: _tabController,
                          indicator: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [
                                Color(0xFF26A69A),
                                Color(0xFF2CB5A8),
                              ],
                            ),
                            borderRadius: BorderRadius.circular(12),
                            boxShadow: [
                              BoxShadow(
                                color:
                                    const Color(0xFF26A69A).withOpacity(0.35),
                                blurRadius: 8,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          labelColor: Colors.white,
                          unselectedLabelColor: Colors.white.withOpacity(0.4),
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
                      const SizedBox(height: 24),

                      // Form fields
                      Form(
                        key: _formKey,
                        child: Column(
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
                            const SizedBox(height: 14),
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
                                      ? Icons.visibility_off_rounded
                                      : Icons.visibility_rounded,
                                  color: Colors.white.withOpacity(0.3),
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

                            // Error
                            Consumer<AuthProvider>(
                              builder: (context, auth, _) {
                                if (auth.errorMessage == null) {
                                  return const SizedBox(height: 20);
                                }
                                return Padding(
                                  padding:
                                      const EdgeInsets.only(top: 14, bottom: 6),
                                  child: Container(
                                    width: double.infinity,
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 14, vertical: 10),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFE53935)
                                          .withOpacity(0.12),
                                      borderRadius: BorderRadius.circular(10),
                                      border: Border.all(
                                        color: const Color(0xFFE53935)
                                            .withOpacity(0.25),
                                      ),
                                    ),
                                    child: Row(
                                      children: [
                                        const Icon(Icons.error_outline_rounded,
                                            color: Color(0xFFEF5350),
                                            size: 18),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: Text(
                                            auth.errorMessage!,
                                            style: const TextStyle(
                                              color: Color(0xFFEF5350),
                                              fontSize: 12,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),

                            // Sign in button
                            Consumer<AuthProvider>(
                              builder: (context, auth, _) {
                                final isLoading =
                                    auth.state == AuthState.loading;
                                return SizedBox(
                                  width: double.infinity,
                                  height: 52,
                                  child: DecoratedBox(
                                    decoration: BoxDecoration(
                                      gradient: LinearGradient(
                                        colors: isLoading
                                            ? [
                                                const Color(0xFF26A69A)
                                                    .withOpacity(0.5),
                                                const Color(0xFF2CB5A8)
                                                    .withOpacity(0.5),
                                              ]
                                            : const [
                                                Color(0xFF26A69A),
                                                Color(0xFF2CB5A8),
                                              ],
                                      ),
                                      borderRadius: BorderRadius.circular(14),
                                      boxShadow: isLoading
                                          ? []
                                          : [
                                              BoxShadow(
                                                color: const Color(0xFF26A69A)
                                                    .withOpacity(0.4),
                                                blurRadius: 16,
                                                offset: const Offset(0, 6),
                                              ),
                                            ],
                                    ),
                                    child: ElevatedButton(
                                      onPressed:
                                          isLoading ? null : _handleLogin,
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: Colors.transparent,
                                        shadowColor: Colors.transparent,
                                        disabledBackgroundColor:
                                            Colors.transparent,
                                        shape: RoundedRectangleBorder(
                                          borderRadius:
                                              BorderRadius.circular(14),
                                        ),
                                      ),
                                      child: isLoading
                                          ? const SizedBox(
                                              width: 22,
                                              height: 22,
                                              child:
                                                  CircularProgressIndicator(
                                                strokeWidth: 2.5,
                                                color: Colors.white,
                                              ),
                                            )
                                          : Text(
                                              l.signIn,
                                              style: const TextStyle(
                                                fontSize: 16,
                                                fontWeight: FontWeight.w700,
                                                color: Colors.white,
                                                letterSpacing: 0.8,
                                              ),
                                            ),
                                    ),
                                  ),
                                );
                              },
                            ),
                          ],
                        ),
                      ),

                      const Spacer(flex: 2),

                      // Footer
                      Padding(
                        padding: const EdgeInsets.only(bottom: 16),
                        child: Text(
                          '© TDTU ${DateTime.now().year}',
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.white.withOpacity(0.2),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
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
      style: const TextStyle(color: Colors.white, fontSize: 15),
      validator: validator,
      cursorColor: const Color(0xFF26A69A),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(
          color: Colors.white.withOpacity(0.25),
          fontSize: 14,
        ),
        prefixIcon:
            Icon(icon, color: Colors.white.withOpacity(0.35), size: 20),
        suffixIcon: suffixIcon != null
            ? Padding(
                padding: const EdgeInsets.only(right: 12),
                child: suffixIcon,
              )
            : null,
        filled: true,
        fillColor: Colors.white.withOpacity(0.06),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: Colors.white.withOpacity(0.1)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: Colors.white.withOpacity(0.1)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide:
              const BorderSide(color: Color(0xFF26A69A), width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFFEF5350)),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide:
              const BorderSide(color: Color(0xFFEF5350), width: 1.5),
        ),
        errorStyle:
            const TextStyle(color: Color(0xFFEF5350), fontSize: 11),
      ),
    );
  }

  List<Widget> _buildFloatingDots(Size size) {
    final random = Random(42);
    return List.generate(12, (i) {
      final top = random.nextDouble() * size.height;
      final left = random.nextDouble() * size.width;
      final dotSize = 2.0 + random.nextDouble() * 3;
      final opacity = 0.05 + random.nextDouble() * 0.1;
      return Positioned(
        top: top,
        left: left,
        child: Container(
          width: dotSize,
          height: dotSize,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Colors.white.withOpacity(opacity),
          ),
        ),
      );
    });
  }
}
