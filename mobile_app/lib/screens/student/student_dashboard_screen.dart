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
      final provider = context.read<StudentProvider>();
      provider.loadDashboard();
      provider.loadProfile();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      body: Consumer<StudentProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading && provider.dashboard == null && provider.profile == null) {
            return const LoadingWidget();
          }

          final data = provider.dashboard;
          final profile = provider.profile;

          if (data == null && profile == null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.error_outline, size: 48, color: AppTheme.textSecondary),
                  const SizedBox(height: 16),
                  Text(provider.error ?? 'Ma\'lumot topilmadi'),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () {
                      provider.loadDashboard();
                      provider.loadProfile();
                    },
                    child: const Text('Qayta yuklash'),
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () async {
              await Future.wait([
                provider.loadDashboard(),
                provider.loadProfile(),
              ]);
            },
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              child: Column(
                children: [
                  // Profile header section
                  _buildProfileHeader(context, data, profile),
                  const SizedBox(height: 16),

                  // Stats & cards section
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Stat cards grid
                        Row(
                          children: [
                            Expanded(
                              child: StatCard(
                                title: 'GPA',
                                value: (data?['gpa'] ?? profile?['gpa'] ?? 0).toString(),
                                icon: Icons.trending_up,
                                color: AppTheme.primaryColor,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: StatCard(
                                title: 'O\'rtacha baho',
                                value: (data?['avg_grade'] ?? 0).toString(),
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
                                value: (data?['debt_subjects'] ?? 0).toString(),
                                icon: Icons.warning_amber_outlined,
                                color: data?['debt_subjects'] != null && data!['debt_subjects'] > 0
                                    ? AppTheme.errorColor
                                    : AppTheme.successColor,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: StatCard(
                                title: 'Darsga kelmagan',
                                value: (data?['total_absences'] ?? 0).toString(),
                                icon: Icons.event_busy_outlined,
                                color: AppTheme.warningColor,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 24),

                        // Recent grades
                        if (data?['recent_grades'] != null) ...[
                          Text(
                            'So\'nggi baholar',
                            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.bold,
                                ),
                          ),
                          const SizedBox(height: 12),
                          ...((data!['recent_grades'] as List).map((grade) {
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
                        const SizedBox(height: 16),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildProfileHeader(
    BuildContext context,
    Map<String, dynamic>? data,
    Map<String, dynamic>? profile,
  ) {
    final fullName = profile?['full_name']?.toString() ??
        data?['student_name']?.toString() ??
        '';
    final studentId = profile?['student_id_number']?.toString() ?? '';
    final faculty = profile?['department_name']?.toString() ?? '';
    final specialty = profile?['specialty_name']?.toString() ?? '';
    final photoUrl = profile?['photo']?.toString();
    final course = profile?['course']?.toString() ?? profile?['level']?.toString() ?? '';
    final gpa = (data?['gpa'] ?? profile?['gpa'] ?? '').toString();
    final avgGrade = (data?['avg_grade'] ?? '').toString();
    final enrollDate = profile?['enrollment_date']?.toString() ??
        profile?['created_at']?.toString() ??
        '';

    return Stack(
      clipBehavior: Clip.none,
      children: [
        // Dark blue curved background
        Container(
          width: double.infinity,
          padding: EdgeInsets.only(
            top: MediaQuery.of(context).padding.top + 12,
            bottom: 70,
          ),
          decoration: const BoxDecoration(
            color: AppTheme.primaryColor,
            borderRadius: BorderRadius.only(
              bottomLeft: Radius.circular(30),
              bottomRight: Radius.circular(30),
            ),
          ),
          child: Column(
            children: [
              // Top bar: logo + settings
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Icon(
                      Icons.account_balance,
                      color: Colors.white,
                      size: 28,
                    ),
                    IconButton(
                      icon: const Icon(Icons.settings, color: Colors.white, size: 24),
                      onPressed: () {},
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),

        // Profile photo (overlapping header and white area)
        Positioned(
          top: MediaQuery.of(context).padding.top + 60,
          left: 0,
          right: 0,
          child: Center(
            child: Stack(
              children: [
                Container(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 4),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withAlpha(30),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: CircleAvatar(
                    radius: 50,
                    backgroundColor: AppTheme.primaryColor.withAlpha(30),
                    backgroundImage:
                        photoUrl != null && photoUrl.isNotEmpty ? NetworkImage(photoUrl) : null,
                    child: photoUrl == null || photoUrl.isEmpty
                        ? Text(
                            _getInitials(fullName),
                            style: const TextStyle(
                              fontSize: 32,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.primaryColor,
                            ),
                          )
                        : null,
                  ),
                ),
                // Camera icon overlay
                Positioned(
                  bottom: 4,
                  right: 4,
                  child: Container(
                    width: 28,
                    height: 28,
                    decoration: BoxDecoration(
                      color: AppTheme.primaryColor,
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 2),
                    ),
                    child: const Icon(
                      Icons.camera_alt,
                      color: Colors.white,
                      size: 14,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),

        // White card with student info + mini stats
        Container(
          width: double.infinity,
          margin: EdgeInsets.only(
            top: MediaQuery.of(context).padding.top + 155,
            left: 16,
            right: 16,
          ),
          padding: const EdgeInsets.only(top: 20, bottom: 16, left: 16, right: 16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withAlpha(15),
                blurRadius: 15,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: Column(
            children: [
              // Name
              Text(
                fullName,
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                  color: AppTheme.textPrimary,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 4),

              // Student ID
              Text(
                studentId,
                style: const TextStyle(
                  fontSize: 14,
                  color: AppTheme.textSecondary,
                ),
              ),
              const SizedBox(height: 6),

              // Faculty (orange)
              if (faculty.isNotEmpty)
                Text(
                  faculty,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppTheme.warningColor,
                  ),
                  textAlign: TextAlign.center,
                ),
              if (specialty.isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(
                  specialty,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: AppTheme.textSecondary,
                  ),
                  textAlign: TextAlign.center,
                ),
              ],
              const SizedBox(height: 12),

              // "Profil to'ldirish" button
              OutlinedButton(
                onPressed: () {},
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppTheme.textSecondary,
                  side: const BorderSide(color: AppTheme.dividerColor),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(20),
                  ),
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
                ),
                child: const Text(
                  'Profilni to\'ldirish',
                  style: TextStyle(fontSize: 13),
                ),
              ),
              const SizedBox(height: 16),

              // Divider
              const Divider(height: 1, color: AppTheme.dividerColor),
              const SizedBox(height: 12),

              // Mini stats row (4 columns)
              Row(
                children: [
                  _buildMiniStat(
                    'Ro\'yxat sanasi',
                    enrollDate.isNotEmpty ? enrollDate : '-',
                  ),
                  _buildVerticalDivider(),
                  _buildMiniStat('Kurs', course.isNotEmpty ? course : '-'),
                  _buildVerticalDivider(),
                  _buildMiniStat(
                    'O\'rtacha',
                    avgGrade.isNotEmpty && avgGrade != '0' ? avgGrade : '-',
                  ),
                  _buildVerticalDivider(),
                  _buildMiniStat(
                    'GPA',
                    gpa.isNotEmpty && gpa != '0' ? gpa : '-',
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildMiniStat(String label, String value) {
    return Expanded(
      child: Column(
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              color: AppTheme.textSecondary,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.bold,
              color: AppTheme.primaryColor,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildVerticalDivider() {
    return Container(
      width: 1,
      height: 35,
      color: AppTheme.dividerColor,
    );
  }

  String _getInitials(String name) {
    final parts = name.split(' ');
    if (parts.length >= 2) {
      return '${parts[0][0]}${parts[1][0]}';
    }
    return name.isNotEmpty ? name[0] : '?';
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
