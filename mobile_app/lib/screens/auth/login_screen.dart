import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../utils/page_transitions.dart';
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
    return Scaffold(
      backgroundColor: const Color(0xFFF7F8FB),
      body: SafeArea(
        bottom: false,
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(accent: _accent, accentSoft: _accentSoft),
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Xush kelibsiz',
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
                                    color:
                                        const Color(0xFFDC2626).withOpacity(0.25)),
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
                      _buildHemisButton(),
                      const SizedBox(height: 18),
                      Center(
                        child: Text(
                          '© TDTU ${DateTime.now().year}',
                          style: TextStyle(
                            fontSize: 11,
                            color: _ink.withOpacity(0.4),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
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
      label: _isStudent ? 'HEMIS ID' : 'XODIM ID',
      child: TextFormField(
        controller: _idCtrl,
        keyboardType: TextInputType.text,
        autofillHints: const [AutofillHints.username],
        style: const TextStyle(
          fontSize: 15,
          fontWeight: FontWeight.w600,
          color: _ink,
        ),
        validator: (v) {
          if (v == null || v.trim().isEmpty) {
            return _isStudent
                ? 'HEMIS ID kiriting'
                : 'Xodim ID kiriting';
          }
          return null;
        },
        decoration: InputDecoration(
          isDense: true,
          contentPadding: EdgeInsets.zero,
          border: InputBorder.none,
          enabledBorder: InputBorder.none,
          focusedBorder: InputBorder.none,
          errorBorder: InputBorder.none,
          focusedErrorBorder: InputBorder.none,
          hintText: _isStudent ? '304220780019' : 'tm-2024-0481',
          hintStyle: TextStyle(color: _ink.withOpacity(0.3)),
          errorStyle: const TextStyle(
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
      label: 'PAROL',
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
              autofillHints: const [AutofillHints.password],
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w600,
                color: _ink,
                letterSpacing: _showPw ? 0 : 3,
              ),
              validator: (v) {
                if (v == null || v.isEmpty) return 'Parol kiriting';
                return null;
              },
              decoration: const InputDecoration(
                isDense: true,
                contentPadding: EdgeInsets.zero,
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                errorBorder: InputBorder.none,
                focusedErrorBorder: InputBorder.none,
                hintText: '••••••••',
                errorStyle: TextStyle(
                  fontSize: 11,
                  color: Color(0xFFB91C1C),
                  height: 1.2,
                ),
              ),
            ),
          ),
          IconButton(
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(),
            iconSize: 18,
            color: _ink.withOpacity(0.55),
            icon: Icon(_showPw
                ? Icons.visibility_off_outlined
                : Icons.visibility_outlined),
            onPressed: () => setState(() => _showPw = !_showPw),
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

  Widget _buildHemisButton() {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: () => ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('HEMIS orqali kirish — OAuth oqimi shu yerga ulanadi.'),
        ),
      ),
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
              child: const Text(
                'H',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.5,
                ),
              ),
            ),
            const SizedBox(width: 10),
            Text(
              'HEMIS orqali kirish',
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
  const _Hero({required this.accent, required this.accentSoft});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: const BorderRadius.only(
        bottomLeft: Radius.circular(36),
        bottomRight: Radius.circular(36),
      ),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 220),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [accent, accentSoft],
          ),
        ),
        padding: const EdgeInsets.fromLTRB(28, 32, 28, 36),
        child: Column(
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.16),
                border: Border.all(
                    color: Colors.white.withOpacity(0.45), width: 1.5),
                borderRadius: BorderRadius.circular(16),
              ),
              child: const Icon(Icons.school_rounded,
                  color: Colors.white, size: 32),
            ),
            const SizedBox(height: 12),
            const Text(
              'TDTU · LMS',
              style: TextStyle(
                color: Colors.white,
                fontSize: 11,
                letterSpacing: 3,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              'Toshkent Davlat Tibbiyot Universiteti',
              style: TextStyle(
                color: Colors.white.withOpacity(0.7),
                fontSize: 10,
                letterSpacing: 1.2,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
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
