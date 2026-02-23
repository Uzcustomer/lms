import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/auth_provider.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/loading_widget.dart';
import 'teacher_journal_screen.dart';

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
      final provider = context.read<TeacherProvider>();
      provider.loadDashboard();
      provider.loadActiveSubjects();
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

  void _openJournal(Map<String, dynamic> subject) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => TeacherJournalScreen(
          groupId: subject['group_id'] as int,
          groupName: subject['group_name']?.toString() ?? '',
          subjectId: subject['subject_id'].toString(),
          semesterCode: subject['semester_code'].toString(),
          subjectName: subject['subject_name']?.toString() ?? '',
        ),
      ),
    );
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
                    onPressed: () {
                      provider.loadDashboard();
                      provider.loadActiveSubjects();
                    },
                    child: Text(l.reload),
                  ),
                ],
              ),
            );
          }

          final activeSubjects = provider.activeSubjects ?? [];

          return RefreshIndicator(
            onRefresh: () async {
              await Future.wait([
                provider.loadDashboard(),
                provider.loadActiveSubjects(),
              ]);
            },
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Greeting & teacher name
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
                  const SizedBox(height: 24),

                  // Active subjects section header
                  Row(
                    children: [
                      Icon(
                        Icons.menu_book,
                        size: 22,
                        color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        'Joriy semestr fanlari',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                          color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                        ),
                      ),
                      const Spacer(),
                      if (activeSubjects.isNotEmpty)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: AppTheme.primaryColor.withAlpha(20),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            '${activeSubjects.length}',
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: AppTheme.primaryColor,
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  // Active subjects list
                  if (provider.isLoading && activeSubjects.isEmpty)
                    const Center(
                      child: Padding(
                        padding: EdgeInsets.all(32),
                        child: CircularProgressIndicator(),
                      ),
                    )
                  else if (activeSubjects.isEmpty)
                    Center(
                      child: Padding(
                        padding: const EdgeInsets.all(32),
                        child: Column(
                          children: [
                            Icon(
                              Icons.school_outlined,
                              size: 48,
                              color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                            ),
                            const SizedBox(height: 12),
                            Text(
                              l.noSubjects,
                              style: TextStyle(
                                color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    )
                  else
                    ...activeSubjects.map((subject) {
                      final s = subject as Map<String, dynamic>;
                      return _SubjectCard(
                        subject: s,
                        isDark: isDark,
                        onTap: () => _openJournal(s),
                      );
                    }),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _SubjectCard extends StatelessWidget {
  final Map<String, dynamic> subject;
  final bool isDark;
  final VoidCallback onTap;

  const _SubjectCard({
    required this.subject,
    required this.isDark,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final subjectName = subject['subject_name']?.toString() ?? '-';
    final groupName = subject['group_name']?.toString() ?? '-';
    final semesterName = subject['semester_name']?.toString() ?? '-';
    final credit = subject['credit']?.toString() ?? '-';
    final facultyName = subject['faculty_name']?.toString() ?? '';
    final kafedraName = subject['kafedra_name']?.toString() ?? '';
    final specialtyName = subject['specialty_name']?.toString() ?? '';
    final levelName = subject['level_name']?.toString() ?? '';
    final educationType = subject['education_type_name']?.toString() ?? '';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.darkCard : Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: (isDark ? Colors.black : Colors.grey).withAlpha(isDark ? 60 : 30),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Subject name + group + credit
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: AppTheme.primaryColor.withAlpha(20),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(
                        Icons.book_outlined,
                        size: 22,
                        color: AppTheme.primaryColor,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            subjectName,
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Row(
                            children: [
                              _InfoChip(
                                icon: Icons.groups_outlined,
                                label: groupName,
                                isDark: isDark,
                              ),
                              const SizedBox(width: 8),
                              _InfoChip(
                                icon: Icons.star_outline,
                                label: '$credit kr',
                                isDark: isDark,
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    Icon(
                      Icons.chevron_right,
                      color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                // Details
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: isDark
                        ? AppTheme.darkSurface.withAlpha(120)
                        : AppTheme.backgroundColor,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Column(
                    children: [
                      if (semesterName.isNotEmpty)
                        _DetailRow(label: 'Semestr', value: semesterName, isDark: isDark),
                      if (levelName.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        _DetailRow(label: 'Kurs', value: levelName, isDark: isDark),
                      ],
                      if (facultyName.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        _DetailRow(label: 'Fakultet', value: facultyName, isDark: isDark),
                      ],
                      if (specialtyName.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        _DetailRow(label: 'Yo\'nalish', value: specialtyName, isDark: isDark),
                      ],
                      if (kafedraName.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        _DetailRow(label: 'Kafedra', value: kafedraName, isDark: isDark),
                      ],
                      if (educationType.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        _DetailRow(label: 'Ta\'lim turi', value: educationType, isDark: isDark),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool isDark;

  const _InfoChip({
    required this.icon,
    required this.label,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: isDark
            ? AppTheme.darkSurface.withAlpha(120)
            : AppTheme.backgroundColor,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            icon,
            size: 14,
            color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
          ),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
            ),
          ),
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;
  final bool isDark;

  const _DetailRow({
    required this.label,
    required this.value,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 80,
          child: Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
            ),
          ),
        ),
      ],
    );
  }
}
