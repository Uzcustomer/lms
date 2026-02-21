import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import '../../l10n/app_localizations.dart';

class CompleteProfileScreen extends StatefulWidget {
  const CompleteProfileScreen({super.key});

  @override
  State<CompleteProfileScreen> createState() => _CompleteProfileScreenState();
}

class _CompleteProfileScreenState extends State<CompleteProfileScreen> {
  final _phoneController = TextEditingController();
  final _telegramController = TextEditingController();
  bool _phoneSaved = false;
  bool _telegramSaved = false;
  bool _isLoading = false;
  Timer? _pollTimer;

  @override
  void dispose() {
    _phoneController.dispose();
    _telegramController.dispose();
    _pollTimer?.cancel();
    super.dispose();
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (_) async {
      final auth = context.read<AuthProvider>();
      final verified = await auth.checkTelegramVerification();
      if (verified && mounted) {
        _pollTimer?.cancel();
        auth.completeProfileSetup();
      }
    });
  }

  Future<void> _savePhone() async {
    final phone = _phoneController.text.trim();
    if (phone.isEmpty) return;

    // Auto-add + prefix if missing
    final formattedPhone = phone.startsWith('+') ? phone : '+$phone';

    setState(() => _isLoading = true);
    final auth = context.read<AuthProvider>();
    final success = await auth.savePhone(formattedPhone);
    setState(() => _isLoading = false);

    if (success && mounted) {
      setState(() => _phoneSaved = true);
    }
  }

  Future<void> _saveTelegram() async {
    var username = _telegramController.text.trim();
    if (username.isEmpty) return;

    // Auto-add @ prefix if missing
    if (!username.startsWith('@')) {
      username = '@$username';
    }

    setState(() => _isLoading = true);
    final auth = context.read<AuthProvider>();
    final success = await auth.saveTelegram(username);
    setState(() => _isLoading = false);

    if (success && mounted) {
      setState(() => _telegramSaved = true);
      _startPolling();
    }
  }

  Future<void> _openBot() async {
    final auth = context.read<AuthProvider>();
    final link = auth.botLink;
    if (link != null && link.isNotEmpty) {
      final uri = Uri.parse(link);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    }
  }

  void _skipTelegram() {
    context.read<AuthProvider>().completeProfileSetup();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkBackground : AppTheme.backgroundColor;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subTextColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        title: Text(l.fillProfile),
        centerTitle: true,
        automaticallyImplyLeading: false,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => context.read<AuthProvider>().logout(),
          ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Header icon
              Container(
                width: 80,
                height: 80,
                margin: const EdgeInsets.only(bottom: 16),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withAlpha(20),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  _phoneSaved ? Icons.telegram : Icons.phone_android,
                  size: 40,
                  color: AppTheme.primaryColor,
                ),
              ),

              // Title
              Text(
                _phoneSaved
                    ? 'Telegram username kiriting'
                    : 'Telefon raqamingizni kiriting',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: textColor,
                    ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                _phoneSaved
                    ? 'Telegram botimiz orqali hisobingizni tasdiqlang'
                    : 'Tizimga kirish uchun telefon raqamingiz zarur',
                style: TextStyle(color: subTextColor, fontSize: 14),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),

              // Steps indicator
              _buildStepsIndicator(isDark),
              const SizedBox(height: 24),

              // Error message
              Consumer<AuthProvider>(
                builder: (context, auth, _) {
                  if (auth.errorMessage != null) {
                    return Container(
                      padding: const EdgeInsets.all(12),
                      margin: const EdgeInsets.only(bottom: 16),
                      decoration: BoxDecoration(
                        color: AppTheme.errorColor.withAlpha(25),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppTheme.errorColor.withAlpha(76)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.error_outline, color: AppTheme.errorColor, size: 20),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              auth.errorMessage!,
                              style: const TextStyle(color: AppTheme.errorColor, fontSize: 13),
                            ),
                          ),
                        ],
                      ),
                    );
                  }
                  return const SizedBox.shrink();
                },
              ),

              // Step 1: Phone or Step 2: Telegram
              if (!_phoneSaved)
                _buildPhoneStep(cardColor, textColor, subTextColor)
              else if (!_telegramSaved)
                _buildTelegramStep(cardColor, textColor, subTextColor)
              else
                _buildVerificationStep(cardColor, textColor, subTextColor),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStepsIndicator(bool isDark) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        _buildStepDot(1, 'Telefon', _phoneSaved, !_phoneSaved, isDark),
        Container(
          width: 40,
          height: 2,
          color: _phoneSaved ? AppTheme.successColor : (isDark ? AppTheme.darkDivider : AppTheme.dividerColor),
        ),
        _buildStepDot(2, 'Telegram', _telegramSaved, _phoneSaved && !_telegramSaved, isDark),
      ],
    );
  }

  Widget _buildStepDot(int step, String label, bool completed, bool active, bool isDark) {
    final color = completed
        ? AppTheme.successColor
        : active
            ? AppTheme.primaryColor
            : (isDark ? AppTheme.darkDivider : AppTheme.dividerColor);

    return Column(
      children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: color,
            shape: BoxShape.circle,
          ),
          child: Center(
            child: completed
                ? const Icon(Icons.check, color: Colors.white, size: 18)
                : Text(
                    '$step',
                    style: TextStyle(
                      color: active ? Colors.white : (isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          style: TextStyle(
            fontSize: 11,
            color: active || completed ? color : (isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
            fontWeight: active ? FontWeight.w600 : FontWeight.normal,
          ),
        ),
      ],
    );
  }

  Widget _buildPhoneStep(Color cardColor, Color textColor, Color subTextColor) {
    return Card(
      elevation: 0,
      color: cardColor,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                labelText: 'Telefon raqam',
                hintText: '+998901234567',
                prefixIcon: const Icon(Icons.phone),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[0-9+]')),
              ],
            ),
            const SizedBox(height: 16),
            SizedBox(
              height: 52,
              child: ElevatedButton(
                onPressed: _isLoading ? null : _savePhone,
                child: _isLoading
                    ? const SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : const Text('Davom etish'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTelegramStep(Color cardColor, Color textColor, Color subTextColor) {
    final auth = context.watch<AuthProvider>();
    final daysLeft = auth.telegramDaysLeft;

    return Card(
      elevation: 0,
      color: cardColor,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Days left warning
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(
                color: daysLeft <= 2
                    ? AppTheme.errorColor.withAlpha(20)
                    : daysLeft <= 4
                        ? AppTheme.warningColor.withAlpha(20)
                        : AppTheme.primaryColor.withAlpha(20),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.timer_outlined,
                    size: 18,
                    color: daysLeft <= 2
                        ? AppTheme.errorColor
                        : daysLeft <= 4
                            ? AppTheme.warningColor
                            : AppTheme.primaryColor,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'Tasdiqlash uchun $daysLeft kun qoldi',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: daysLeft <= 2
                          ? AppTheme.errorColor
                          : daysLeft <= 4
                              ? AppTheme.warningColor
                              : AppTheme.primaryColor,
                    ),
                  ),
                ],
              ),
            ),

            TextFormField(
              controller: _telegramController,
              keyboardType: TextInputType.text,
              decoration: InputDecoration(
                labelText: 'Telegram username',
                hintText: '@username',
                prefixIcon: const Icon(Icons.alternate_email),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              height: 52,
              child: ElevatedButton(
                onPressed: _isLoading ? null : _saveTelegram,
                child: _isLoading
                    ? const SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : const Text('Tasdiqlash boshlash'),
              ),
            ),
            const SizedBox(height: 12),
            TextButton(
              onPressed: _skipTelegram,
              child: Text(
                'Keyinroq tasdiqlash',
                style: TextStyle(color: subTextColor),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildVerificationStep(Color cardColor, Color textColor, Color subTextColor) {
    final auth = context.watch<AuthProvider>();
    final code = auth.verificationCode ?? '';
    final botUsername = auth.botUsername ?? '';

    return Card(
      elevation: 0,
      color: cardColor,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Verification code display
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: AppTheme.primaryColor.withAlpha(15),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppTheme.primaryColor.withAlpha(40)),
              ),
              child: Column(
                children: [
                  Text(
                    'Tasdiqlash kodi',
                    style: TextStyle(fontSize: 13, color: subTextColor),
                  ),
                  const SizedBox(height: 8),
                  SelectableText(
                    code,
                    style: const TextStyle(
                      fontSize: 32,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 6,
                      color: AppTheme.primaryColor,
                    ),
                  ),
                  const SizedBox(height: 8),
                  InkWell(
                    onTap: () {
                      Clipboard.setData(ClipboardData(text: code));
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Kod nusxalandi'),
                          duration: Duration(seconds: 1),
                        ),
                      );
                    },
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.copy, size: 14, color: subTextColor),
                        const SizedBox(width: 4),
                        Text('Nusxalash', style: TextStyle(fontSize: 12, color: subTextColor)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Instructions
            Text(
              'Quyidagi tugma orqali Telegram botga o\'ting va tasdiqlash kodini yuboring:',
              style: TextStyle(fontSize: 13, color: subTextColor),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 16),

            // Open bot button
            SizedBox(
              height: 52,
              child: ElevatedButton.icon(
                onPressed: _openBot,
                icon: const Icon(Icons.send),
                label: Text(botUsername.isNotEmpty ? 'Botga o\'tish (@$botUsername)' : 'Telegram botga o\'tish'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0088CC),
                ),
              ),
            ),
            const SizedBox(height: 12),

            // Waiting indicator
            Container(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: subTextColor,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'Tasdiqlash kutilmoqda...',
                    style: TextStyle(fontSize: 13, color: subTextColor),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            TextButton(
              onPressed: _skipTelegram,
              child: Text(
                'Keyinroq tasdiqlash',
                style: TextStyle(color: subTextColor),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
