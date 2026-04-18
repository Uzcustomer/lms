import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';

class AbsenceExcuseScreen extends StatelessWidget {
  const AbsenceExcuseScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkBackground : AppTheme.backgroundColor;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final cardColor = isDark ? AppTheme.darkCard : AppTheme.surfaceColor;

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        title: Text(l.absenceExcuse),
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withAlpha(25),
                  borderRadius: BorderRadius.circular(60),
                ),
                child: Icon(
                  Icons.construction_rounded,
                  size: 56,
                  color: AppTheme.primaryColor.withAlpha(150),
                ),
              ),
              const SizedBox(height: 24),
              Text(
                l.comingSoon,
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                  color: textColor,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                l.comingSoonDesc,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 15,
                  color: subColor,
                  height: 1.5,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
