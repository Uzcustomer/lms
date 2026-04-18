import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import 'absence_excuse_list_screen.dart';

class StudentServicesScreen extends StatelessWidget {
  const StudentServicesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkBackground : AppTheme.backgroundColor;

    final services = [
      _ServiceItem(
        icon: Icons.description_outlined,
        title: l.absenceExcuse,
        subtitle: l.absenceExcuseDesc,
        color: AppTheme.primaryColor,
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const AbsenceExcuseListScreen()),
        ),
      ),
    ];

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        title: Text(l.services),
        centerTitle: true,
        leading: Navigator.canPop(context)
            ? IconButton(
                icon: const Icon(Icons.arrow_back),
                onPressed: () => Navigator.pop(context),
              )
            : const Padding(
                padding: EdgeInsets.all(12),
                child: Icon(Icons.account_balance, size: 28),
              ),
      ),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: GridView.builder(
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: 1.0,
          ),
          itemCount: services.length,
          itemBuilder: (context, index) {
            final item = services[index];
            return _ServiceCard(item: item, isDark: isDark);
          },
        ),
      ),
    );
  }
}

class _ServiceItem {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  const _ServiceItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });
}

class _ServiceCard extends StatelessWidget {
  final _ServiceItem item;
  final bool isDark;

  const _ServiceCard({required this.item, required this.isDark});

  @override
  Widget build(BuildContext context) {
    final cardColor = isDark ? AppTheme.darkCard : AppTheme.surfaceColor;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Material(
      color: cardColor,
      borderRadius: BorderRadius.circular(16),
      elevation: isDark ? 0 : 2,
      child: InkWell(
        onTap: item.onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: item.color.withAlpha(25),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(item.icon, size: 28, color: item.color),
              ),
              const SizedBox(height: 12),
              Text(
                item.title,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: textColor,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                item.subtitle,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 11,
                  color: subColor,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
