import 'package:flutter/material.dart';
import 'package:pin_code_fields/pin_code_fields.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import '../../l10n/app_localizations.dart';

class Verify2faScreen extends StatefulWidget {
  final String login;

  const Verify2faScreen({super.key, required this.login});

  @override
  State<Verify2faScreen> createState() => _Verify2faScreenState();
}

class _Verify2faScreenState extends State<Verify2faScreen> {
  final _codeController = TextEditingController();
  bool _canResend = false;
  int _resendSeconds = 60;

  @override
  void initState() {
    super.initState();
    _startResendTimer();
  }

  void _startResendTimer() {
    setState(() {
      _canResend = false;
      _resendSeconds = 60;
    });

    Future.doWhile(() async {
      await Future.delayed(const Duration(seconds: 1));
      if (!mounted) return false;
      setState(() {
        _resendSeconds--;
      });
      if (_resendSeconds <= 0) {
        setState(() {
          _canResend = true;
        });
        return false;
      }
      return true;
    });
  }

  Future<void> _verify() async {
    if (_codeController.text.length != 6) return;

    final success = await context.read<AuthProvider>().verify2fa(
          _codeController.text,
        );

    if (success && mounted) {
      Navigator.of(context).popUntil((route) => route.isFirst);
    }
  }

  Future<void> _resend() async {
    await context.read<AuthProvider>().resend2fa();
    _startResendTimer();
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkBackground : AppTheme.backgroundColor;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subTextColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final pinFillColor = isDark ? AppTheme.darkCard : Colors.white;
    final pinBorderColor = isDark ? AppTheme.darkDivider : AppTheme.dividerColor;

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        title: Text(l.verification),
        backgroundColor: Colors.transparent,
        foregroundColor: textColor,
        elevation: 0,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              const SizedBox(height: 24),
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withAlpha(isDark ? 40 : 25),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Icon(
                  Icons.telegram,
                  size: 40,
                  color: AppTheme.primaryColor,
                ),
              ),
              const SizedBox(height: 24),
              Text(
                l.telegramVerification,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: textColor,
                    ),
              ),
              const SizedBox(height: 8),
              Text(
                l.telegramCodeHint,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: subTextColor,
                    ),
              ),
              const SizedBox(height: 32),

              // PIN code input
              PinCodeTextField(
                appContext: context,
                length: 6,
                controller: _codeController,
                keyboardType: TextInputType.number,
                animationType: AnimationType.fade,
                pinTheme: PinTheme(
                  shape: PinCodeFieldShape.box,
                  borderRadius: BorderRadius.circular(12),
                  fieldHeight: 52,
                  fieldWidth: 46,
                  activeFillColor: pinFillColor,
                  inactiveFillColor: pinFillColor,
                  selectedFillColor: pinFillColor,
                  activeColor: AppTheme.primaryColor,
                  inactiveColor: pinBorderColor,
                  selectedColor: AppTheme.primaryColor,
                ),
                enableActiveFill: true,
                onCompleted: (_) => _verify(),
                onChanged: (_) {},
              ),

              const SizedBox(height: 16),

              // Error
              Consumer<AuthProvider>(
                builder: (context, auth, _) {
                  if (auth.errorMessage != null) {
                    return Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      margin: const EdgeInsets.only(bottom: 12),
                      decoration: BoxDecoration(
                        color: AppTheme.errorColor.withAlpha(25),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        auth.errorMessage!,
                        style: const TextStyle(color: AppTheme.errorColor, fontSize: 13),
                      ),
                    );
                  }
                  return const SizedBox.shrink();
                },
              ),

              // Verify button
              Consumer<AuthProvider>(
                builder: (context, auth, _) {
                  return SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton(
                      onPressed: auth.state == AuthState.loading ? null : _verify,
                      child: auth.state == AuthState.loading
                          ? const SizedBox(
                              width: 24,
                              height: 24,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Text(l.verify),
                    ),
                  );
                },
              ),

              const SizedBox(height: 16),

              // Resend
              TextButton(
                onPressed: _canResend ? _resend : null,
                child: Text(
                  _canResend
                      ? l.resendCode
                      : '${l.resendIn} ($_resendSeconds s)',
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
