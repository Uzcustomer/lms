import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../providers/auth_provider.dart';
import '../../providers/student_provider.dart';
import '../../l10n/app_localizations.dart';
import '../../services/api_service.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/settings_sheet.dart';
import '../../widgets/clinic_header.dart';
import 'student_home_screen.dart';

class StudentProfileScreen extends StatefulWidget {
  const StudentProfileScreen({super.key});

  @override
  State<StudentProfileScreen> createState() => _StudentProfileScreenState();
}

class _StudentProfileScreenState extends State<StudentProfileScreen> {
  final _telegramController = TextEditingController();
  String? _verificationCode;
  String? _botUsername;
  String? _botLink;
  bool _isSavingTelegram = false;
  bool _isCheckingVerification = false;
  Timer? _verificationTimer;
  String? _telegramError;

  static const _telegramBlue = Color(0xFF0088CC);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadProfile();
    });
  }

  @override
  void dispose() {
    _verificationTimer?.cancel();
    _telegramController.dispose();
    super.dispose();
  }

  void _startVerificationPolling() {
    _verificationTimer?.cancel();
    _verificationTimer = Timer.periodic(const Duration(seconds: 3), (_) async {
      if (!mounted) {
        _verificationTimer?.cancel();
        return;
      }
      try {
        setState(() => _isCheckingVerification = true);
        final provider = context.read<StudentProvider>();
        final result = await provider.checkTelegramVerification();
        if (!mounted) return;
        if (result['verified'] == true) {
          _verificationTimer?.cancel();
          setState(() {
            _verificationCode = null;
            _isCheckingVerification = false;
          });
          await provider.loadProfile(force: true);
        } else {
          setState(() => _isCheckingVerification = false);
        }
      } catch (_) {
        if (mounted) setState(() => _isCheckingVerification = false);
      }
    });
  }

  Future<void> _saveTelegram() async {
    final username = _telegramController.text.trim();
    if (username.isEmpty) return;

    final formatted = username.startsWith('@') ? username : '@$username';
    if (!RegExp(r'^@[a-zA-Z0-9_]{5,32}$').hasMatch(formatted)) {
      setState(() => _telegramError =
          'Username @username formatida bo\'lishi kerak (kamida 5 belgi)');
      return;
    }

    setState(() {
      _isSavingTelegram = true;
      _telegramError = null;
    });

    try {
      final provider = context.read<StudentProvider>();
      final result = await provider.saveTelegram(formatted);
      if (!mounted) return;
      setState(() {
        _verificationCode = result['verification_code']?.toString();
        _botUsername = result['bot_username']?.toString();
        _botLink = result['bot_link']?.toString();
        _isSavingTelegram = false;
      });
      _startVerificationPolling();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _telegramError = e.message;
        _isSavingTelegram = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _telegramError = 'Xatolik yuz berdi';
        _isSavingTelegram = false;
      });
    }
  }

  Widget _card({required Widget child, EdgeInsets? padding}) {
    return Container(
      width: double.infinity,
      padding: padding ?? const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: child,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: 'PROFIL · TALABA',
            title: 'Mening hisobim',
            onBack: () => StudentHomeScreen.switchToHome(context),
            actions: [
              ClinicIconButton(
                icon: Icons.settings_outlined,
                onTap: () => showSettingsSheet(context),
              ),
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: isDark
                      ? const Color(0xFFDC2626).withOpacity(0.15)
                      : const Color(0xFFFEE2E2),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: IconButton(
                  padding: EdgeInsets.zero,
                  icon: const Icon(Icons.logout_rounded,
                      color: Color(0xFFDC2626), size: 18),
                  onPressed: () => _showLogoutDialog(context),
                ),
              ),
            ],
          ),
          Expanded(
            child: Consumer<StudentProvider>(
              builder: (context, provider, _) {
                if (provider.isLoading && provider.profile == null) {
                  return const LoadingWidget();
                }
                final profile = provider.profile;
                if (profile == null) {
                  return Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(provider.error ?? l.profileNotFound),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: () => provider.loadProfile(),
                          child: Text(l.reload),
                        ),
                      ],
                    ),
                  );
                }

                return RefreshIndicator(
                  onRefresh: () => provider.refreshAll(),
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(14, 14, 14, 100),
                    children: [
                      _buildProfileCard(context, profile),
                      const SizedBox(height: 12),
                      _buildTelegramCard(context, profile),
                      const SizedBox(height: 14),
                      _buildPersonalInfo(context, profile),
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  // ── Profile card ─────────────────────────────────────
  Widget _buildProfileCard(BuildContext context, Map<String, dynamic> profile) {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);
    final fullName = profile['full_name']?.toString() ?? '';
    final studentId = profile['student_id_number']?.toString() ?? '';
    final faculty = profile['department_name']?.toString() ?? '';
    final major = profile['specialty_name']?.toString() ?? '';
    final photoUrl = _buildImageUrl(profile['image']?.toString());
    final year = profile['year_of_enter']?.toString() ?? '';
    final course = profile['course']?.toString() ?? '';
    final semester = profile['semester_name']?.toString() ?? '';
    final payment = profile['payment_form_name']?.toString() ?? '';

    return _card(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          Stack(
            children: [
              Container(
                width: 84,
                height: 84,
                decoration: const BoxDecoration(
                  color: ClinicTheme.teal,
                  shape: BoxShape.circle,
                ),
                clipBehavior: Clip.antiAlias,
                child: photoUrl != null && photoUrl.isNotEmpty
                    ? Image.network(photoUrl, fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => _avatarInitials(fullName))
                    : _avatarInitials(fullName),
              ),
              Positioned(
                right: 0,
                bottom: 2,
                child: Container(
                  width: 24,
                  height: 24,
                  decoration: BoxDecoration(
                    color: ClinicTheme.teal,
                    shape: BoxShape.circle,
                    border: Border.all(
                        color: ClinicTheme.surfaceOf(context), width: 2.5),
                  ),
                  child: const Icon(Icons.check, size: 12, color: Colors.white),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            fullName.toUpperCase(),
            textAlign: TextAlign.center,
            style: TextStyle(
                fontSize: 15, fontWeight: FontWeight.w800, color: ink),
          ),
          const SizedBox(height: 7),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 5),
            decoration: BoxDecoration(
              color: const Color(0xFFF0FDF4),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              'ID · $studentId',
              style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  color: ClinicTheme.green),
            ),
          ),
          const SizedBox(height: 14),
          Divider(height: 1, color: ClinicTheme.dividerOf(context)),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _facultyCol(
                    Icons.account_balance_rounded, 'FAKULTET', faculty),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _facultyCol(
                    Icons.school_rounded, 'YO\'NALISH', major),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (year.isNotEmpty) _chip(year),
              if (course.isNotEmpty) _chip('$course-kurs'),
              if (semester.isNotEmpty) _chip(semester),
              if (payment.isNotEmpty) _chip(payment),
            ],
          ),
        ],
      ),
    );
  }

  Widget _avatarInitials(String name) {
    return Center(
      child: Text(
        _getInitials(name).toUpperCase(),
        style: const TextStyle(
            fontSize: 30, fontWeight: FontWeight.w800, color: Colors.white),
      ),
    );
  }

  Widget _facultyCol(IconData icon, String label, String value) {
    final muted = ClinicTheme.mutedOf(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, size: 12, color: muted),
            const SizedBox(width: 4),
            Text(
              label,
              style: TextStyle(
                fontSize: 9.5,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.4,
                color: muted,
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          value.isEmpty ? '—' : value,
          style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w800,
              color: ClinicTheme.inkOf(context)),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
      ],
    );
  }

  Widget _chip(String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
      decoration: BoxDecoration(
        color: Theme.of(context).brightness == Brightness.dark
            ? Colors.white.withOpacity(0.05)
            : const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
      ),
      child: Text(
        text,
        style: TextStyle(
            fontSize: 11.5,
            fontWeight: FontWeight.w700,
            color: ClinicTheme.inkOf(context)),
      ),
    );
  }

  // ── Telegram card ────────────────────────────────────
  Widget _buildTelegramCard(BuildContext context, Map<String, dynamic> profile) {
    final l = AppLocalizations.of(context);
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);
    final telegramVerified = profile['telegram_verified'] == true;
    final telegramUsername = profile['telegram_username']?.toString() ?? '';

    if (telegramVerified && telegramUsername.isNotEmpty) {
      return Container(
        decoration: BoxDecoration(
          color: ClinicTheme.surfaceOf(context),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
          boxShadow: ClinicTheme.cardShadow,
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(width: 4, color: ClinicTheme.teal),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(13),
                    child: Row(
                      children: [
                        Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: _telegramBlue.withOpacity(0.12),
                            borderRadius: BorderRadius.circular(11),
                          ),
                          child: const Icon(Icons.telegram,
                              color: _telegramBlue, size: 24),
                        ),
                        const SizedBox(width: 11),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Text(
                                    l.telegramVerified,
                                    style: TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w800,
                                        color: ink),
                                  ),
                                  const SizedBox(width: 4),
                                  const Icon(Icons.verified_rounded,
                                      size: 14, color: ClinicTheme.green),
                                ],
                              ),
                              const SizedBox(height: 1),
                              Text(
                                telegramUsername,
                                style: TextStyle(fontSize: 12, color: muted),
                              ),
                            ],
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

    // Not verified — registration flow.
    return _card(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
            decoration: const BoxDecoration(
              color: _telegramBlue,
              borderRadius: BorderRadius.only(
                topLeft: Radius.circular(15),
                topRight: Radius.circular(15),
              ),
            ),
            child: Row(
              children: [
                const Icon(Icons.telegram, color: Colors.white, size: 20),
                const SizedBox(width: 8),
                Text(
                  l.connectTelegram,
                  style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 13,
                      color: Colors.white),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: _verificationCode != null
                ? _buildVerificationStep(context)
                : _buildUsernameStep(context),
          ),
        ],
      ),
    );
  }

  Widget _buildUsernameStep(BuildContext context) {
    final l = AppLocalizations.of(context);
    final ink = ClinicTheme.inkOf(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          l.telegramUsername,
          style: TextStyle(
              fontSize: 13, fontWeight: FontWeight.w700, color: ink),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _telegramController,
          style: TextStyle(fontSize: 14, color: ink),
          decoration: InputDecoration(
            hintText: l.telegramUsernameHint,
            prefixIcon: const Icon(Icons.alternate_email, size: 20),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(color: ClinicTheme.dividerOf(context)),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(color: ClinicTheme.dividerOf(context)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: _telegramBlue, width: 2),
            ),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            filled: true,
            fillColor: Theme.of(context).brightness == Brightness.dark
                ? Colors.white.withOpacity(0.04)
                : const Color(0xFFF1F5F9),
          ),
        ),
        if (_telegramError != null) ...[
          const SizedBox(height: 6),
          Text(_telegramError!,
              style: const TextStyle(color: Color(0xFFDC2626), fontSize: 12)),
        ],
        const SizedBox(height: 12),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: _isSavingTelegram ? null : _saveTelegram,
            style: ElevatedButton.styleFrom(
              backgroundColor: _telegramBlue,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 12),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
            ),
            child: _isSavingTelegram
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                        strokeWidth: 2, color: Colors.white),
                  )
                : Text(l.telegramSave,
                    style: const TextStyle(fontWeight: FontWeight.w700)),
          ),
        ),
      ],
    );
  }

  Widget _buildVerificationStep(BuildContext context) {
    final l = AppLocalizations.of(context);
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l.telegramVerificationCode,
            style: TextStyle(
                fontSize: 13, fontWeight: FontWeight.w700, color: ink)),
        const SizedBox(height: 8),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 16),
          decoration: BoxDecoration(
            color: Theme.of(context).brightness == Brightness.dark
                ? Colors.white.withOpacity(0.04)
                : const Color(0xFFF1F5F9),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _telegramBlue.withOpacity(0.3)),
          ),
          child: Column(
            children: [
              SelectableText(
                _verificationCode!,
                style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 6,
                    color: ink),
              ),
              const SizedBox(height: 4),
              InkWell(
                onTap: () {
                  Clipboard.setData(ClipboardData(text: _verificationCode!));
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                        content: Text('Kod nusxalandi'),
                        duration: Duration(seconds: 1)),
                  );
                },
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.copy, size: 14, color: muted),
                    const SizedBox(width: 4),
                    Text('Nusxalash',
                        style: TextStyle(fontSize: 12, color: muted)),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Text(l.telegramSendCodeToBot,
            style: TextStyle(fontSize: 13, color: muted)),
        const SizedBox(height: 12),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: _botLink != null
                ? () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text('Telegram botga o\'ting: $_botLink'),
                        duration: const Duration(seconds: 5),
                        action: SnackBarAction(label: 'OK', onPressed: () {}),
                      ),
                    );
                  }
                : null,
            icon: const Icon(Icons.telegram, size: 20),
            label: Text(
              _botUsername != null && _botUsername!.isNotEmpty
                  ? '${l.telegramOpenBot} (@$_botUsername)'
                  : l.telegramOpenBot,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: _telegramBlue,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 12),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
            ),
          ),
        ),
        const SizedBox(height: 12),
        if (_isCheckingVerification)
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const SizedBox(
                width: 14,
                height: 14,
                child: CircularProgressIndicator(
                    strokeWidth: 2, color: _telegramBlue),
              ),
              const SizedBox(width: 8),
              Text(l.telegramChecking,
                  style: TextStyle(fontSize: 12, color: muted)),
            ],
          ),
        const SizedBox(height: 8),
        Center(
          child: TextButton(
            onPressed: () {
              _verificationTimer?.cancel();
              setState(() {
                _verificationCode = null;
                _botUsername = null;
                _botLink = null;
              });
            },
            child: Text('Username o\'zgartirish',
                style: TextStyle(fontSize: 12, color: muted)),
          ),
        ),
      ],
    );
  }

  // ── Personal info ────────────────────────────────────
  Widget _buildPersonalInfo(BuildContext context, Map<String, dynamic> profile) {
    final l = AppLocalizations.of(context);
    final ink = ClinicTheme.inkOf(context);
    final gender = profile['gender'];
    final province = profile['province_name']?.toString();
    final district = profile['district_name']?.toString();
    final educationType = profile['education_type_name']?.toString();
    final educationForm = profile['education_form_name']?.toString();
    final groupName = profile['group_name']?.toString();
    final phone = profile['phone']?.toString();
    final telegramUsername = profile['telegram_username']?.toString();
    final telegramVerified = profile['telegram_verified'] == true;

    final rows = <Widget>[];
    void add(String label, String value, {bool copyable = false, bool verified = false}) {
      if (value.isEmpty) return;
      if (rows.isNotEmpty) {
        rows.add(Divider(height: 1, color: ClinicTheme.dividerOf(context)));
      }
      rows.add(_infoRow(label, value, copyable: copyable, verified: verified));
    }

    add(l.get('phone'), phone ?? '', copyable: true);
    add('Telegram', telegramUsername ?? '', verified: telegramVerified);
    add(l.group, groupName ?? '');
    if (gender != null) add(l.gender, gender == 11 ? l.male : l.female);
    add(l.educationForm, educationForm ?? '');
    add(l.educationType, educationType ?? '');
    add(l.province, province ?? '');
    add(l.district, district ?? '');

    if (rows.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(2, 0, 2, 10),
          child: Text(
            l.personalInfo.toUpperCase(),
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.6,
              color: ClinicTheme.mutedOf(context),
            ),
          ),
        ),
        _card(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          child: Column(children: rows),
        ),
      ],
    );
  }

  Widget _infoRow(String label, String value,
      {bool copyable = false, bool verified = false}) {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 13),
      child: Row(
        children: [
          SizedBox(
            width: 96,
            child: Text(
              label,
              style: TextStyle(fontSize: 12.5, color: muted),
            ),
          ),
          Expanded(
            child: Row(
              children: [
                Flexible(
                  child: Text(
                    value,
                    style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        color: ink),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                if (verified) ...[
                  const SizedBox(width: 4),
                  const Icon(Icons.verified_rounded,
                      size: 14, color: ClinicTheme.green),
                ],
              ],
            ),
          ),
          if (copyable)
            GestureDetector(
              onTap: () {
                Clipboard.setData(ClipboardData(text: value));
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                      content: Text('Nusxalandi'),
                      duration: Duration(seconds: 1)),
                );
              },
              child: Icon(Icons.copy_rounded, size: 16, color: muted),
            ),
        ],
      ),
    );
  }

  String? _buildImageUrl(String? imagePath) {
    if (imagePath == null || imagePath.isEmpty) return null;
    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
      final imageHost = Uri.parse(imagePath).host;
      final apiHost = Uri.parse(ApiConfig.baseUrl).host;
      if (imageHost != apiHost) {
        final encoded = Uri.encodeComponent(imagePath);
        return '${ApiConfig.baseUrl}${ApiConfig.imageProxy}?url=$encoded';
      }
      return imagePath;
    }
    final baseHost = Uri.parse(ApiConfig.baseUrl).origin;
    final path = imagePath.startsWith('/') ? imagePath : '/$imagePath';
    return '$baseHost$path';
  }

  String _getInitials(String name) {
    final parts = name.split(' ');
    if (parts.length >= 2) {
      return '${parts[0][0]}${parts[1][0]}';
    }
    return name.isNotEmpty ? name[0] : '?';
  }

  void _showLogoutDialog(BuildContext context) {
    final l = AppLocalizations.of(context);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: ClinicTheme.surfaceOf(context),
        title: Text(l.logout),
        content: Text(l.logoutConfirm),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: Text(l.cancel),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              context.read<AuthProvider>().logout();
            },
            style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFDC2626)),
            child: Text(l.logout),
          ),
        ],
      ),
    );
  }
}
