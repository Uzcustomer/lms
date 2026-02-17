import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/student_provider.dart';
import '../../widgets/stat_card.dart';
import '../../widgets/loading_widget.dart';

class StudentDashboardScreen extends StatefulWidget {
  const StudentDashboardScreen({super.key});

  @override
  State<StudentDashboardScreen> createState() => _StudentDashboardScreenState();
}

class _StudentDashboardScreenState extends State<StudentDashboardScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadDashboard();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        title: const Text('TDTU LMS'),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () {},
          ),
        ],
      ),
      body: Consumer<StudentProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading && provider.dashboard == null) {
            return const LoadingWidget();
          }

          final data = provider.dashboard;
          if (data == null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.error_outline, size: 48, color: AppTheme.textSecondary),
                  const SizedBox(height: 16),
                  Text(provider.error ?? 'Ma\'lumot topilmadi'),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () => provider.loadDashboard(),
                    child: const Text('Qayta yuklash'),
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
                  // Welcome
                  Text(
                    'Assalomu alaykum!',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                  if (data['student_name'] != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      data['student_name'].toString(),
                      style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                            color: AppTheme.textSecondary,
                          ),
                    ),
                  ],
                  const SizedBox(height: 20),

                  // Stats grid
                  Row(
                    children: [
                      Expanded(
                        child: StatCard(
                          title: 'GPA',
                          value: (data['gpa'] ?? 0).toString(),
                          icon: Icons.trending_up,
                          color: AppTheme.primaryColor,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: StatCard(
                          title: 'O\'rtacha baho',
                          value: (data['avg_grade'] ?? 0).toString(),
                          icon: Icons.star_outline,
                          color: AppTheme.accentColor,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: StatCard(
                          title: 'Qarzlar',
                          value: (data['debt_subjects'] ?? 0).toString(),
                          icon: Icons.warning_amber_outlined,
                          color: data['debt_subjects'] != null && data['debt_subjects'] > 0
                              ? AppTheme.errorColor
                              : AppTheme.successColor,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: StatCard(
                          title: 'Darsga kelmagan',
                          value: (data['total_absences'] ?? 0).toString(),
                          icon: Icons.event_busy_outlined,
                          color: AppTheme.warningColor,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),

                  // Recent grades
                  if (data['recent_grades'] != null) ...[
                    Text(
                      'So\'nggi baholar',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                    const SizedBox(height: 12),
                    ...((data['recent_grades'] as List).map((grade) {
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: ListTile(
                          leading: CircleAvatar(
                            backgroundColor: _gradeColor(grade['grade']),
                            child: Text(
                              (grade['grade'] ?? '-').toString(),
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                          title: Text(
                            grade['subject_name']?.toString() ?? '',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          subtitle: Text(
                            '${grade['training_type_name'] ?? ''} - ${grade['lesson_date'] ?? ''}',
                            style: const TextStyle(fontSize: 12),
                          ),
                          trailing: grade['status'] == 'pending'
                              ? Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: AppTheme.warningColor.withAlpha(25),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: const Text(
                                    'Kutilmoqda',
                                    style: TextStyle(
                                      fontSize: 11,
                                      color: AppTheme.warningColor,
                                    ),
                                  ),
                                )
                              : null,
                        ),
                      );
                    })),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Color _gradeColor(dynamic grade) {
    if (grade == null) return AppTheme.textSecondary;
    final g = grade is num ? grade.toDouble() : double.tryParse(grade.toString()) ?? 0;
    if (g >= 86) return AppTheme.successColor;
    if (g >= 71) return AppTheme.primaryColor;
    if (g >= 56) return AppTheme.warningColor;
    return AppTheme.errorColor;
  }
}
