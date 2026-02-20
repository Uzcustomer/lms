import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../config/api_config.dart';
import '../../providers/student_provider.dart';
import '../../providers/settings_provider.dart';
import '../../l10n/app_localizations.dart';
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

  String? _buildImageUrl(String? imagePath) {
    if (imagePath == null || imagePath.isEmpty) return null;
    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
      // Web da tashqi domainlardan rasm yuklash CORS xatosi beradi
      if (kIsWeb) {
        final imageHost = Uri.parse(imagePath).host;
        final apiHost = Uri.parse(ApiConfig.baseUrl).host;
        if (imageHost != apiHost) return null;
      }
      return imagePath;
    }
    final baseHost = Uri.parse(ApiConfig.baseUrl).origin;
    final path = imagePath.startsWith('/') ? imagePath : '/$imagePath';
    return '$baseHost$path';
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
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
                  Text(provider.error ?? l.noData),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () {
                      provider.loadDashboard();
                      provider.loadProfile();
                    },
                    child: Text(l.reload),
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
                  _buildProfileHeader(context, data, profile, l, isDark),
                  const SizedBox(height: 16),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: StatCard(
                                title: l.gpa,
                                value: (data?['gpa'] ?? profile?['avg_gpa'] ?? 0).toString(),
                                icon: Icons.trending_up,
                                color: AppTheme.primaryColor,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: StatCard(
                                title: l.avgGrade,
                                value: (data?['avg_grade'] ?? profile?['avg_grade'] ?? 0).toString(),
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
                                title: l.debts,
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
                                title: l.absences,
                                value: (data?['total_absences'] ?? 0).toString(),
                                icon: Icons.event_busy_outlined,
                                color: AppTheme.warningColor,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 24),
                        _buildTuitionFeeSection(context, profile, l, isDark),
                        const SizedBox(height: 100),
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
    AppLocalizations l,
    bool isDark,
  ) {
    final fullName = profile?['full_name']?.toString() ??
        data?['student_name']?.toString() ??
        '';
    final studentId = profile?['student_id_number']?.toString() ?? '';
    final faculty = profile?['department_name']?.toString() ?? '';
    final specialty = profile?['specialty_name']?.toString() ?? '';
    final imageUrl = _buildImageUrl(profile?['image']?.toString());
    // Use calculated course from backend (not level_code)
    final course = profile?['course']?.toString() ?? '';
    final yearOfEnter = profile?['year_of_enter']?.toString() ?? '';
    final educationYear = profile?['education_year_name']?.toString() ?? '';
    final semesterName = profile?['semester_name']?.toString() ?? '';

    final statusBarHeight = MediaQuery.of(context).padding.top;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subTextColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final divColor = isDark ? AppTheme.darkDivider : AppTheme.dividerColor;

    return Stack(
      children: [
        // Dark blue curved background
        Container(
          width: double.infinity,
          padding: EdgeInsets.only(
            top: statusBarHeight + 12,
            bottom: 200,
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
              // Top bar
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.account_balance, color: Colors.white, size: 28),
                        const SizedBox(width: 10),
                        Text(
                          l.appTitle,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                    Row(
                      children: [
                        IconButton(
                          icon: const Icon(Icons.notifications_outlined, color: Colors.white, size: 24),
                          onPressed: () {},
                        ),
                        IconButton(
                          icon: const Icon(Icons.settings, color: Colors.white, size: 24),
                          onPressed: () => _showSettingsSheet(context),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // Profile photo
              _buildProfileAvatar(imageUrl, fullName),
            ],
          ),
        ),

        // White card
        Container(
          width: double.infinity,
          margin: EdgeInsets.only(
            top: statusBarHeight + 210,
            left: 16,
            right: 16,
          ),
          padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
          decoration: BoxDecoration(
            color: cardColor,
            borderRadius: BorderRadius.circular(20),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withAlpha(20),
                blurRadius: 15,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: Column(
            children: [
              Text(
                fullName,
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                  color: textColor,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 3),
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withAlpha(20),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  studentId,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppTheme.primaryColor,
                  ),
                ),
              ),
              const SizedBox(height: 8),
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
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: subTextColor,
                  ),
                  textAlign: TextAlign.center,
                ),
              ],
              const SizedBox(height: 16),
              Divider(height: 1, color: divColor),
              const SizedBox(height: 12),
              Row(
                children: [
                  _buildMiniStat(l.enrollmentYear, yearOfEnter.isNotEmpty ? yearOfEnter : '-', textColor, subTextColor),
                  _buildVerticalDivider(divColor),
                  _buildMiniStat(l.educationYear, educationYear.isNotEmpty ? educationYear : '-', textColor, subTextColor),
                  _buildVerticalDivider(divColor),
                  _buildMiniStat(l.semester, semesterName.isNotEmpty ? semesterName : '-', textColor, subTextColor),
                  _buildVerticalDivider(divColor),
                  _buildMiniStat(l.course, course.isNotEmpty ? '$course-kurs' : '-', textColor, subTextColor),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildProfileAvatar(String? imageUrl, String fullName) {
    return Stack(
      children: [
        Container(
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white, width: 4),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withAlpha(40),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: imageUrl != null && imageUrl.isNotEmpty
              ? ClipOval(
                  child: CachedNetworkImage(
                    imageUrl: imageUrl,
                    width: 100,
                    height: 100,
                    fit: BoxFit.cover,
                    placeholder: (context, url) => Container(
                      width: 100,
                      height: 100,
                      color: Colors.white.withAlpha(50),
                      child: const Center(
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      ),
                    ),
                    errorWidget: (context, url, error) => CircleAvatar(
                      radius: 50,
                      backgroundColor: Colors.white.withAlpha(50),
                      child: Text(
                        _getInitials(fullName),
                        style: const TextStyle(
                          fontSize: 32,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ),
                )
              : CircleAvatar(
                  radius: 50,
                  backgroundColor: Colors.white.withAlpha(50),
                  child: Text(
                    _getInitials(fullName),
                    style: const TextStyle(
                      fontSize: 32,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                ),
        ),
        Positioned(
          bottom: 4,
          right: 4,
          child: Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
              border: Border.all(color: AppTheme.primaryColor, width: 2),
            ),
            child: const Icon(Icons.camera_alt, color: AppTheme.primaryColor, size: 14),
          ),
        ),
      ],
    );
  }

  void _showSettingsSheet(BuildContext context) {
    final l = AppLocalizations.of(context);
    final settings = context.read<SettingsProvider>();

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setSheetState) {
            final isDark = settings.isDark;
            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Handle bar
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: Colors.grey[400],
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Text(
                    l.settings,
                    style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 20),

                  // Theme toggle
                  Text(
                    l.theme,
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      _buildThemeOption(
                        ctx,
                        icon: Icons.light_mode,
                        label: l.lightMode,
                        isSelected: !isDark,
                        onTap: () {
                          settings.setThemeMode(ThemeMode.light);
                          setSheetState(() {});
                        },
                      ),
                      const SizedBox(width: 12),
                      _buildThemeOption(
                        ctx,
                        icon: Icons.dark_mode,
                        label: l.darkMode,
                        isSelected: isDark,
                        onTap: () {
                          settings.setThemeMode(ThemeMode.dark);
                          setSheetState(() {});
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Language selection
                  Text(
                    l.language,
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      _buildLangOption(ctx, 'UZ', l.uzbek, 'uz', settings),
                      const SizedBox(width: 8),
                      _buildLangOption(ctx, 'RU', l.russian, 'ru', settings),
                      const SizedBox(width: 8),
                      _buildLangOption(ctx, 'EN', l.english, 'en', settings),
                    ],
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildThemeOption(
    BuildContext context, {
    required IconData icon,
    required String label,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            color: isSelected ? AppTheme.primaryColor : Colors.grey[200],
            borderRadius: BorderRadius.circular(14),
          ),
          child: Column(
            children: [
              Icon(icon, color: isSelected ? Colors.white : Colors.grey[600], size: 28),
              const SizedBox(height: 6),
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: isSelected ? Colors.white : Colors.grey[600],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildLangOption(
    BuildContext context,
    String code,
    String label,
    String langCode,
    SettingsProvider settings,
  ) {
    final isSelected = settings.languageCode == langCode;
    return Expanded(
      child: GestureDetector(
        onTap: () {
          settings.setLocale(Locale(langCode));
          Navigator.pop(context);
        },
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: isSelected ? AppTheme.primaryColor : Colors.grey[200],
            borderRadius: BorderRadius.circular(14),
            border: isSelected ? null : Border.all(color: Colors.grey[300]!),
          ),
          child: Column(
            children: [
              Text(
                code,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: isSelected ? Colors.white : Colors.grey[700],
                ),
              ),
              const SizedBox(height: 2),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  color: isSelected ? Colors.white70 : Colors.grey[500],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMiniStat(String label, String value, Color textColor, Color subTextColor) {
    return Expanded(
      child: Column(
        children: [
          Text(
            label,
            style: TextStyle(fontSize: 10, color: subTextColor),
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.bold,
              color: AppTheme.primaryColor,
            ),
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }

  Widget _buildVerticalDivider(Color color) {
    return Container(width: 1, height: 35, color: color);
  }

  String _getInitials(String name) {
    final parts = name.split(' ');
    if (parts.length >= 2) {
      return '${parts[0][0]}${parts[1][0]}';
    }
    return name.isNotEmpty ? name[0] : '?';
  }

  Widget _buildTuitionFeeSection(
    BuildContext context,
    Map<String, dynamic>? profile,
    AppLocalizations l,
    bool isDark,
  ) {
    final paymentFormName = profile?['payment_form_name']?.toString() ?? '';
    final isContract = paymentFormName.toLowerCase().contains('kontrakt') ||
        paymentFormName.toLowerCase().contains('shartnoma') ||
        (profile?['payment_form_code']?.toString() ?? '') == '12';
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subTextColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          l.tuitionFee,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
              ),
        ),
        const SizedBox(height: 12),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: cardColor,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withAlpha(isDark ? 40 : 12),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Payment form badge
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: isContract
                          ? AppTheme.warningColor.withAlpha(25)
                          : AppTheme.successColor.withAlpha(25),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          isContract ? Icons.receipt_long : Icons.school,
                          size: 14,
                          color: isContract ? AppTheme.warningColor : AppTheme.successColor,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          paymentFormName.isNotEmpty
                              ? paymentFormName
                              : (isContract ? l.contractStudent : l.grantStudent),
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: isContract ? AppTheme.warningColor : AppTheme.successColor,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              if (isContract) ...[
                const SizedBox(height: 16),
                // Payment progress
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      l.paid,
                      style: TextStyle(fontSize: 13, color: subTextColor),
                    ),
                    Text(
                      '-- / -- so\'m',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: textColor,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: 0,
                    backgroundColor: isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0),
                    valueColor: const AlwaysStoppedAnimation<Color>(AppTheme.successColor),
                    minHeight: 6,
                  ),
                ),
                const SizedBox(height: 12),
                // Remaining & deadline
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(l.remaining, style: TextStyle(fontSize: 11, color: subTextColor)),
                          const SizedBox(height: 2),
                          Text(
                            '-- so\'m',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.warningColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(l.deadline, style: TextStyle(fontSize: 11, color: subTextColor)),
                          const SizedBox(height: 2),
                          Text(
                            '--',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              color: textColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ] else ...[
                const SizedBox(height: 12),
                Text(
                  paymentFormName.isNotEmpty ? paymentFormName : l.grantStudent,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: textColor,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
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
