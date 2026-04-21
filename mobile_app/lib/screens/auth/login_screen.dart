import 'dart:ui';
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
    final bottom = MediaQuery.of(context).viewInsets.bottom;

    return Scaffold(
      resizeToAvoidBottomInset: false,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // Background image
          Image.asset(
            'assets/images/bg_image.png',
            fit: BoxFit.cover,
          ),

          // Dark gradient overlay
          Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Colors.black.withOpacity(0.1),
                  Colors.black.withOpacity(0.6),
                  Colors.black.withOpacity(0.85),
                ],
                stops: const [0.0, 0.45, 1.0],
              ),
            ),
          ),

          // Content
          SafeArea(
            child: Column(
              children: [
                const Spacer(),

                // Logo & Title
                Container(
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: Colors.white.withOpacity(0.3),
                      width: 1.5,
                    ),
                  ),
                  child: const Icon(
                    Icons.school_rounded,
                    size: 40,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 12),
                const Text(
                  'TDTU LMS',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                    letterSpacing: 1.5,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  l.lmsSubtitle,
                  style: TextStyle(
                    fontSize: 13,
                    color: Colors.white.withOpacity(0.7),
                  ),
                ),
                const SizedBox(height: 28),

                // Glass card with form
                Padding(
                  padding: EdgeInsets.only(
                    left: 20,
                    right: 20,
                    bottom: bottom > 0 ? bottom + 12 : 32,
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(20),
                    child: BackdropFilter(
                      filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
                      child: Container(
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.12),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: Colors.white.withOpacity(0.2),
                          ),
                        ),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            // Tab selector
                            Container(
                              decoration: BoxDecoration(
                                color: Colors.white.withOpacity(0.08),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: TabBar(
                                controller: _tabController,
                                indicator: BoxDecoration(
                                  gradient: const LinearGradient(
                                    colors: [
                                      Color(0xFFFF8C00),
                                      Color(0xFFFF6B00),
                                    ],
                                  ),
                                  borderRadius: BorderRadius.circular(11),
                                ),
                                labelColor: Colors.white,
                                unselectedLabelColor:
                                    Colors.white.withOpacity(0.5),
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
                            const SizedBox(height: 20),

                            // Login form
                            Form(
                              key: _formKey,
                              child: Column(
                                children: [
                                  _buildTextField(
                                    controller: _loginController,
                                    label: l.loginLabel,
                                    hint: l.loginHint,
                                    icon: Icons.person_outline,
                                    validator: (value) {
                                      if (value == null ||
                                          value.trim().isEmpty) {
                                        return l.loginRequired;
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 14),
                                  _buildTextField(
                                    controller: _passwordController,
                                    label: l.password,
                                    hint: l.passwordHint,
                                    icon: Icons.lock_outline,
                                    obscure: _obscurePassword,
                                    suffixIcon: IconButton(
                                      icon: Icon(
                                        _obscurePassword
                                            ? Icons.visibility_off_outlined
                                            : Icons.visibility_outlined,
                                        color: Colors.white.withOpacity(0.5),
                                        size: 20,
                                      ),
                                      onPressed: () {
                                        setState(() {
                                          _obscurePassword = !_obscurePassword;
                                        });
                                      },
                                    ),
                                    validator: (value) {
                                      if (value == null || value.isEmpty) {
                                        return l.passwordRequired;
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 10),

                                  // Error message
                                  Consumer<AuthProvider>(
                                    builder: (context, auth, _) {
                                      if (auth.errorMessage != null) {
                                        return Container(
                                          width: double.infinity,
                                          padding: const EdgeInsets.all(10),
                                          margin:
                                              const EdgeInsets.only(bottom: 10),
                                          decoration: BoxDecoration(
                                            color: Colors.red.withOpacity(0.15),
                                            borderRadius:
                                                BorderRadius.circular(10),
                                            border: Border.all(
                                              color:
                                                  Colors.red.withOpacity(0.3),
                                            ),
                                          ),
                                          child: Row(
                                            children: [
                                              Icon(Icons.error_outline,
                                                  color: Colors.red.shade300,
                                                  size: 18),
                                              const SizedBox(width: 8),
                                              Expanded(
                                                child: Text(
                                                  auth.errorMessage!,
                                                  style: TextStyle(
                                                    color: Colors.red.shade300,
                                                    fontSize: 12,
                                                  ),
                                                ),
                                              ),
                                            ],
                                          ),
                                        );
                                      }
                                      return const SizedBox.shrink();
                                    },
                                  ),

                                  // Login button
                                  Consumer<AuthProvider>(
                                    builder: (context, auth, _) {
                                      return SizedBox(
                                        width: double.infinity,
                                        height: 50,
                                        child: DecoratedBox(
                                          decoration: BoxDecoration(
                                            gradient: const LinearGradient(
                                              colors: [
                                                Color(0xFFFF8C00),
                                                Color(0xFFFF5722),
                                              ],
                                            ),
                                            borderRadius:
                                                BorderRadius.circular(14),
                                            boxShadow: [
                                              BoxShadow(
                                                color: const Color(0xFFFF6B00)
                                                    .withOpacity(0.4),
                                                blurRadius: 12,
                                                offset: const Offset(0, 4),
                                              ),
                                            ],
                                          ),
                                          child: ElevatedButton(
                                            onPressed:
                                                auth.state == AuthState.loading
                                                    ? null
                                                    : _handleLogin,
                                            style: ElevatedButton.styleFrom(
                                              backgroundColor:
                                                  Colors.transparent,
                                              shadowColor: Colors.transparent,
                                              shape: RoundedRectangleBorder(
                                                borderRadius:
                                                    BorderRadius.circular(14),
                                              ),
                                            ),
                                            child: auth.state ==
                                                    AuthState.loading
                                                ? const SizedBox(
                                                    width: 22,
                                                    height: 22,
                                                    child:
                                                        CircularProgressIndicator(
                                                      strokeWidth: 2,
                                                      color: Colors.white,
                                                    ),
                                                  )
                                                : Text(
                                                    l.signIn,
                                                    style: const TextStyle(
                                                      fontSize: 16,
                                                      fontWeight:
                                                          FontWeight.bold,
                                                      color: Colors.white,
                                                      letterSpacing: 0.5,
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
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String label,
    required String hint,
    required IconData icon,
    bool obscure = false,
    Widget? suffixIcon,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: controller,
      obscureText: obscure,
      style: const TextStyle(color: Colors.white, fontSize: 14),
      validator: validator,
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        labelStyle: TextStyle(
          color: Colors.white.withOpacity(0.6),
          fontSize: 13,
        ),
        hintStyle: TextStyle(
          color: Colors.white.withOpacity(0.3),
          fontSize: 13,
        ),
        prefixIcon: Icon(icon, color: Colors.white.withOpacity(0.5), size: 20),
        suffixIcon: suffixIcon,
        filled: true,
        fillColor: Colors.white.withOpacity(0.08),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.white.withOpacity(0.15)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.white.withOpacity(0.15)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFFF8C00), width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.red.shade300),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.red.shade300, width: 1.5),
        ),
        errorStyle: TextStyle(color: Colors.red.shade300, fontSize: 11),
      ),
    );
  }
}
