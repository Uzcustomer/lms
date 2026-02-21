import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/auth_provider.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/loading_widget.dart';

class TeacherDashboardScreen extends StatefulWidget {
  const TeacherDashboardScreen({super.key});

  @override
  State<TeacherDashboardScreen> createState() => _TeacherDashboardScreenState();
}

class _TeacherDashboardScreenState extends State<TeacherDashboardScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<TeacherProvider>().loadDashboard();
    });
  }

  void _showRoleSwitcher(BuildContext context) {
    final auth = context.read<AuthProvider>();
    final isDark = Theme.of(context).brightness == Brightness.dark;

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      backgroundColor: isDark ? AppTheme.darkCard : Colors.white,
      builder: (ctx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: isDark ? AppTheme.darkDivider : Colors.grey[300],
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(height: 16),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Row(
                    children: [
                      Icon(
                        Icons.swap_horiz,
                        color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                      ),
                      const SizedBox(width: 10),
                      Text(
                        'Rolni almashtirish',
                        style: TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.bold,
                          color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                ...auth.roles.map((role) {
                  final isActive = role == auth.activeRole;
                  final label = AuthProvider.roleLabels[role] ?? role;
                  return ListTile(
                    leading: Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: isActive
                            ? AppTheme.primaryColor.withAlpha(25)
                            : (isDark ? AppTheme.darkSurface : const Color(0xFFF5F5F5)),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(
                        _getRoleIcon(role),
                        size: 20,
                        color: isActive
                            ? AppTheme.primaryColor
                            : (isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                      ),
                    ),
                    title: Text(
                      label,
                      style: TextStyle(
                        fontWeight: isActive ? FontWeight.w700 : FontWeight.w500,
                        color: isActive
                            ? AppTheme.primaryColor
                            : (isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary),
                      ),
                    ),
                    trailing: isActive
                        ? const Icon(Icons.check_circle, color: AppTheme.primaryColor, size: 22)
                        : null,
                    onTap: () {
                      auth.setActiveRole(role);
                      Navigator.pop(ctx);
                    },
                  );
                }),
                const SizedBox(height: 8),
              ],
            ),
          ),
        );
      },
    );
  }

  IconData _getRoleIcon(String role) {
    switch (role) {
      case 'superadmin':
      case 'admin':
      case 'kichik_admin':
        return Icons.admin_panel_settings;
      case 'registrator_ofisi':
        return Icons.assignment_ind;
      case 'dekan':
        return Icons.school;
      case 'oqituvchi':
        return Icons.menu_book;
      case 'kafedra_mudiri':
        return Icons.account_balance;
      case 'fan_masuli':
        return Icons.science;
      case 'inspeksiya':
        return Icons.policy;
      case 'oquv_prorektori':
        return Icons.workspace_premium;
      case 'oquv_bolimi':
        return Icons.domain;
      case 'buxgalteriya':
        return Icons.account_balance_wallet;
      case 'test_markazi':
        return Icons.quiz;
      case 'tyutor':
        return Icons.support_agent;
      case 'manaviyat':
        return Icons.volunteer_activism;
      default:
        return Icons.person;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
      appBar: AppBar(
        leading: const Padding(
          padding: EdgeInsets.all(12),
          child: Icon(Icons.account_balance, size: 28),
        ),
        title: Text(l.appTitle),
        actions: [
          Consumer<AuthProvider>(
            builder: (context, auth, _) {
              if (auth.roles.length > 1) {
                return IconButton(
                  icon: const Icon(Icons.swap_horiz),
                  tooltip: 'Rolni almashtirish',
                  onPressed: () => _showRoleSwitcher(context),
                );
              }
              return const SizedBox.shrink();
            },
          ),
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () {},
          ),
        ],
      ),
      body: Consumer2<TeacherProvider, AuthProvider>(
        builder: (context, provider, auth, _) {
          if (provider.isLoading && provider.dashboard == null) {
            return const LoadingWidget();
          }

          final data = provider.dashboard;
          if (data == null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.error_outline, size: 48, color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                  const SizedBox(height: 16),
                  Text(provider.error ?? l.noData),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () => provider.loadDashboard(),
                    child: Text(l.reload),
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () => provider.loadDashboard(),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    l.greeting,
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                  if (data['teacher_name'] != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      data['teacher_name'].toString(),
                      style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                            color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                          ),
                    ),
                  ],
                  if (auth.roles.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: AppTheme.primaryColor.withAlpha(20),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            _getRoleIcon(auth.activeRole ?? ''),
                            size: 16,
                            color: AppTheme.primaryColor,
                          ),
                          const SizedBox(width: 6),
                          Text(
                            auth.activeRoleLabel,
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: AppTheme.primaryColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 20),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
