import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:intl/intl.dart';
import 'dart:async';
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
  Timer? _clockTimer;
  List<dynamic> _todayLessons = [];
  Map<String, dynamic>? _nextDayLesson;
  DateTime _now = DateTime.now();

  @override
  void initState() {
    super.initState();
    _clockTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted) setState(() => _now = DateTime.now());
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<StudentProvider>();
      provider.loadDashboard();
      provider.loadProfile();
      provider.loadContract();
      _loadTodaySchedule();
    });
  }

  @override
  void dispose() {
    _clockTimer?.cancel();
    super.dispose();
  }

  Future<void> _loadTodaySchedule() async {
    try {
      final provider = context.read<StudentProvider>();
      await provider.loadSchedule();
      if (!mounted) return;
      final schedule = provider.schedule;
      if (schedule == null) return;
      final today = DateFormat('yyyy-MM-dd').format(DateTime.now());
      final scheduleList = schedule['schedule'];
      if (scheduleList == null || scheduleList is! List) return;

      // First try to find today's lessons
      for (final day in scheduleList) {
        if (day is! Map<String, dynamic>) continue;
        if (day['date']?.toString() == today) {
          final lessons = day['lessons'];
          if (lessons is List && lessons.isNotEmpty) {
            setState(() {
              _todayLessons = lessons;
              _nextDayLesson = null;
            });
            return;
          }
        }
      }

      // No lessons today — find the first lesson from the next available day
      setState(() => _todayLessons = []);
      for (final day in scheduleList) {
        if (day is! Map<String, dynamic>) continue;
        final dateStr = day['date']?.toString() ?? '';
        if (dateStr.isEmpty) continue;
        final dayDate = DateTime.tryParse(dateStr);
        if (dayDate == null || !dayDate.isAfter(DateTime.now())) continue;
        final lessons = day['lessons'];
        if (lessons is! List || lessons.isEmpty) continue;
        final firstLesson = lessons.first;
        if (firstLesson is! Map<String, dynamic>) continue;
        setState(() {
          _nextDayLesson = {
            ...firstLesson,
            '_date': dateStr,
            '_day_date': dayDate,
          };
        });
        return;
      }
      setState(() => _nextDayLesson = null);
    } catch (_) {
      if (mounted) setState(() {
        _todayLessons = [];
        _nextDayLesson = null;
      });
    }
  }

  Map<String, dynamic>? _getCurrentOrNextLesson() {
    if (_todayLessons.isEmpty) return null;
    final now = _now;
    Map<String, dynamic>? nextLesson;

    for (final lesson in _todayLessons) {
      if (lesson is! Map<String, dynamic>) continue;
      final startStr = lesson['lesson_pair_start_time']?.toString() ?? '';
      final endStr = lesson['lesson_pair_end_time']?.toString() ?? '';
      if (startStr.isEmpty || endStr.isEmpty) continue;

      final startParts = startStr.split(':');
      final endParts = endStr.split(':');
      if (startParts.length < 2 || endParts.length < 2) continue;

      final startH = int.tryParse(startParts[0]);
      final startM = int.tryParse(startParts[1]);
      final endH = int.tryParse(endParts[0]);
      final endM = int.tryParse(endParts[1]);
      if (startH == null || startM == null || endH == null || endM == null) continue;

      final start = DateTime(now.year, now.month, now.day, startH, startM);
      final end = DateTime(now.year, now.month, now.day, endH, endM);

      if (now.isAfter(start.subtract(const Duration(minutes: 1))) && now.isBefore(end)) {
        return {...lesson, '_is_active': true, '_end': end, '_start': start};
      }
      if (now.isBefore(start)) {
        if (nextLesson == null) {
          nextLesson = {...lesson, '_is_active': false, '_start': start, '_end': end};
        }
      }
    }
    return nextLesson;
  }

  String? _buildImageUrl(String? imagePath) {
    if (imagePath == null || imagePath.isEmpty) return null;
    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
      final imageHost = Uri.parse(imagePath).host;
      final apiHost = Uri.parse(ApiConfig.baseUrl).host;
      if (imageHost != apiHost) {
        // Proxy cross-origin images through backend to avoid CORS
        final encoded = Uri.encodeComponent(imagePath);
        return '${ApiConfig.baseUrl}${ApiConfig.imageProxy}?url=$encoded';
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
                provider.loadContract(),
              ]);
              _loadTodaySchedule();
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
                        _buildLiveClassCard(),
                        _buildGpaRow(data, profile, l),
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
                        _buildTuitionFeeSection(context, profile, provider.contract, provider.contractList, l, isDark),
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
                  _buildMiniStat(l.enrollmentYear, yearOfEnter.isNotEmpty ? yearOfEnter : '-', subTextColor, AppTheme.successColor),
                  _buildVerticalDivider(divColor),
                  _buildMiniStat(l.educationYear, educationYear.isNotEmpty ? educationYear : '-', subTextColor, AppTheme.successColor),
                  _buildVerticalDivider(divColor),
                  _buildMiniStat(l.semester, semesterName.isNotEmpty ? semesterName : '-', subTextColor, AppTheme.successColor),
                  _buildVerticalDivider(divColor),
                  _buildMiniStat(l.course, course.isNotEmpty ? '$course-kurs' : '-', subTextColor, AppTheme.successColor),
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
    final isDk = Theme.of(context).brightness == Brightness.dark;
    final unselectedBg = isDk ? AppTheme.darkSurface : Colors.grey[200];
    final unselectedFg = isDk ? AppTheme.darkTextSecondary : Colors.grey[600];

    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            color: isSelected ? AppTheme.primaryColor : unselectedBg,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Column(
            children: [
              Icon(icon, color: isSelected ? Colors.white : unselectedFg, size: 28),
              const SizedBox(height: 6),
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: isSelected ? Colors.white : unselectedFg,
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
    final isDk = Theme.of(context).brightness == Brightness.dark;
    final unselectedBg = isDk ? AppTheme.darkSurface : Colors.grey[200];
    final unselectedBorder = isDk ? AppTheme.darkDivider : Colors.grey[300]!;
    final unselectedCodeColor = isDk ? AppTheme.darkTextPrimary : Colors.grey[700];
    final unselectedLabelColor = isDk ? AppTheme.darkTextSecondary : Colors.grey[500];

    return Expanded(
      child: GestureDetector(
        onTap: () {
          settings.setLocale(Locale(langCode));
          Navigator.pop(context);
        },
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: isSelected ? AppTheme.primaryColor : unselectedBg,
            borderRadius: BorderRadius.circular(14),
            border: isSelected ? null : Border.all(color: unselectedBorder),
          ),
          child: Column(
            children: [
              Text(
                code,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: isSelected ? Colors.white : unselectedCodeColor,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  color: isSelected ? Colors.white70 : unselectedLabelColor,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMiniStat(String label, String value, Color labelColor, Color valueColor) {
    return Expanded(
      child: Column(
        children: [
          Text(
            label,
            style: TextStyle(fontSize: 10, color: labelColor),
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.bold,
              color: valueColor,
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

  String _formatMoney(num amount) {
    final str = amount.toInt().toString();
    final buf = StringBuffer();
    for (var i = 0; i < str.length; i++) {
      if (i > 0 && (str.length - i) % 3 == 0) buf.write(' ');
      buf.write(str[i]);
    }
    return buf.toString();
  }

  Widget _buildTuitionFeeSection(
    BuildContext context,
    Map<String, dynamic>? profile,
    Map<String, dynamic>? contractData,
    List<dynamic>? contractList,
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

    final summary = contractData?['summary'] as Map<String, dynamic>?;
    final totalAmount = (summary?['total_amount'] ?? 0).toDouble();
    final paidAmount = (summary?['paid_amount'] ?? 0).toDouble();
    final remainingAmount = (summary?['remaining_amount'] ?? 0).toDouble();
    final progress = totalAmount > 0 ? (paidAmount / totalAmount).clamp(0.0, 1.0) : 0.0;

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
                      '${_formatMoney(paidAmount)} / ${_formatMoney(totalAmount)} so\'m',
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
                    value: progress,
                    backgroundColor: isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0),
                    valueColor: AlwaysStoppedAnimation<Color>(
                      remainingAmount <= 0 ? AppTheme.successColor : AppTheme.warningColor,
                    ),
                    minHeight: 6,
                  ),
                ),
                const SizedBox(height: 12),
                // Remaining
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(l.remaining, style: TextStyle(fontSize: 11, color: subTextColor)),
                          const SizedBox(height: 2),
                          Text(
                            '${_formatMoney(remainingAmount)} so\'m',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              color: remainingAmount <= 0 ? AppTheme.successColor : AppTheme.warningColor,
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
                            contractData?['education_year']?.toString() ?? '--',
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
        // Contract list section
        if (isContract && contractList != null && contractList.isNotEmpty) ...[
          const SizedBox(height: 20),
          Text(
            l.contractList,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
          ),
          const SizedBox(height: 12),
          ...contractList.map((contract) {
            final c = contract as Map<String, dynamic>;
            final cAmount = (c['contract_amount'] ?? 0).toDouble();
            final cPaid = (c['paid_amount'] ?? 0).toDouble();
            final cUnpaid = (c['unpaid_amount'] ?? 0).toDouble();
            final cStatus = c['status']?.toString() ?? '';
            final educYear = c['education_year']?.toString() ?? '';
            final isPaid = cStatus == 'paid';

            return Container(
              width: double.infinity,
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: cardColor,
                borderRadius: BorderRadius.circular(14),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withAlpha(isDark ? 30 : 8),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Education year & status row
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      if (educYear.isNotEmpty)
                        Text(
                          educYear,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: textColor,
                          ),
                        ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: isPaid
                              ? AppTheme.successColor.withAlpha(25)
                              : AppTheme.errorColor.withAlpha(25),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          isPaid ? l.statusPaid : l.statusUnpaid,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: isPaid ? AppTheme.successColor : AppTheme.errorColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  // Contract amount
                  _buildContractRow(
                    l.contractAmount,
                    '${_formatMoney(cAmount)} so\'m',
                    subTextColor,
                    textColor,
                  ),
                  const SizedBox(height: 6),
                  // Paid amount
                  _buildContractRow(
                    l.paidAmount,
                    '${_formatMoney(cPaid)} so\'m',
                    subTextColor,
                    AppTheme.successColor,
                  ),
                  const SizedBox(height: 6),
                  // Unpaid amount
                  _buildContractRow(
                    l.unpaidAmount,
                    '${_formatMoney(cUnpaid)} so\'m',
                    subTextColor,
                    cUnpaid > 0 ? AppTheme.errorColor : AppTheme.successColor,
                  ),
                ],
              ),
            );
          }),
        ],
      ],
    );
  }

  Widget _buildContractRow(String label, String value, Color labelColor, Color valueColor) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(fontSize: 12, color: labelColor),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: valueColor,
          ),
        ),
      ],
    );
  }

  Widget _buildLiveClassCard() {
    final lesson = _getCurrentOrNextLesson();

    // If no today lesson, show next day's first lesson
    if (lesson == null) {
      if (_nextDayLesson == null) return const SizedBox.shrink();
      return _buildNextDayCard(_nextDayLesson!);
    }

    final isActive = lesson['_is_active'] == true;
    final subjectName = lesson['subject_name']?.toString() ?? '';
    final startTime = lesson['lesson_pair_start_time']?.toString() ?? '';
    final endTime = lesson['lesson_pair_end_time']?.toString() ?? '';
    final room = lesson['auditorium_name']?.toString() ?? '';
    final start = lesson['_start'] as DateTime;
    final end = lesson['_end'] as DateTime;

    final Duration remaining;
    final String statusText;
    final Color bgColor;

    if (isActive) {
      remaining = end.difference(_now);
      statusText = 'HOZIR DAVOM ETMOQDA';
      bgColor = const Color(0xFF4CAF50);
    } else {
      remaining = start.difference(_now);
      statusText = 'KEYINGI DARS';
      bgColor = const Color(0xFFFFA726);
    }

    final hours = remaining.inHours;
    final minutes = remaining.inMinutes % 60;
    String timeLeft;
    if (hours > 0) {
      timeLeft = '$hours soat $minutes daqiqa qoldi';
    } else {
      timeLeft = '${minutes > 0 ? minutes : 1} daqiqa qoldi';
    }

    final progress = isActive
        ? 1.0 - (remaining.inSeconds / end.difference(start).inSeconds).clamp(0.0, 1.0)
        : 0.0;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: isActive
                ? [const Color(0xFF43A047), const Color(0xFF66BB6A)]
                : [const Color(0xFFF57C00), const Color(0xFFFFA726)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(18),
          boxShadow: [
            BoxShadow(
              color: bgColor.withAlpha(60),
              blurRadius: 16,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 8, height: 8,
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  statusText,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: Colors.white.withAlpha(220),
                    letterSpacing: 1.2,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              subjectName,
              style: const TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: Colors.white,
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(Icons.access_time, size: 16, color: Colors.white.withAlpha(200)),
                const SizedBox(width: 4),
                Text(
                  '$startTime–$endTime',
                  style: TextStyle(fontSize: 14, color: Colors.white.withAlpha(220), fontWeight: FontWeight.w500),
                ),
                if (room.isNotEmpty) ...[
                  const SizedBox(width: 12),
                  Text('·', style: TextStyle(fontSize: 16, color: Colors.white.withAlpha(180), fontWeight: FontWeight.w700)),
                  const SizedBox(width: 6),
                  Text(room, style: TextStyle(fontSize: 14, color: Colors.white.withAlpha(220), fontWeight: FontWeight.w500)),
                ],
              ],
            ),
            const SizedBox(height: 12),
            if (isActive) ...[
              ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: LinearProgressIndicator(
                  value: progress,
                  minHeight: 4,
                  backgroundColor: Colors.white.withAlpha(50),
                  valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
                ),
              ),
              const SizedBox(height: 6),
            ],
            Text(
              timeLeft,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Colors.white.withAlpha(200),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _weekdayName(int weekday) {
    const days = ['', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba', 'Yakshanba'];
    return days[weekday.clamp(1, 7)];
  }

  Widget _buildNextDayCard(Map<String, dynamic> lesson) {
    final subjectName = lesson['subject_name']?.toString() ?? '';
    final startTime = lesson['lesson_pair_start_time']?.toString() ?? '';
    final endTime = lesson['lesson_pair_end_time']?.toString() ?? '';
    final room = lesson['auditorium_name']?.toString() ?? '';
    final dayDate = lesson['_day_date'] as DateTime?;
    final dateStr = lesson['_date']?.toString() ?? '';

    String dayLabel = '';
    if (dayDate != null) {
      final diff = dayDate.difference(DateTime(
        _now.year, _now.month, _now.day,
      )).inDays;
      if (diff == 1) {
        dayLabel = 'Ertaga';
      } else {
        dayLabel = '${_weekdayName(dayDate.weekday)}, ${DateFormat('d-MMMM').format(dayDate)}';
      }
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF5C6BC0), Color(0xFF7986CB)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(18),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF5C6BC0).withAlpha(60),
              blurRadius: 16,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.event, size: 16, color: Colors.white70),
                const SizedBox(width: 6),
                Text(
                  dayLabel.isNotEmpty ? dayLabel : dateStr,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: Colors.white.withAlpha(220),
                    letterSpacing: 1.0,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              subjectName,
              style: const TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: Colors.white,
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(Icons.access_time, size: 16, color: Colors.white.withAlpha(200)),
                const SizedBox(width: 4),
                Text(
                  '$startTime–$endTime',
                  style: TextStyle(fontSize: 14, color: Colors.white.withAlpha(220), fontWeight: FontWeight.w500),
                ),
                if (room.isNotEmpty) ...[
                  const SizedBox(width: 12),
                  Text('·', style: TextStyle(fontSize: 16, color: Colors.white.withAlpha(180), fontWeight: FontWeight.w700)),
                  const SizedBox(width: 6),
                  Text(room, style: TextStyle(fontSize: 14, color: Colors.white.withAlpha(220), fontWeight: FontWeight.w500)),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  double _toDouble(dynamic val) {
    if (val == null) return 0;
    if (val is num) return val.toDouble();
    return double.tryParse(val.toString()) ?? 0;
  }

  Color _gradeColor(dynamic grade) {
    if (grade == null) return AppTheme.textSecondary;
    final g = grade is num ? grade.toDouble() : double.tryParse(grade.toString()) ?? 0;
    if (g >= 86) return AppTheme.successColor;
    if (g >= 71) return AppTheme.primaryColor;
    if (g >= 56) return AppTheme.warningColor;
    return AppTheme.errorColor;
  }

  Widget _buildGpaRow(Map<String, dynamic>? data, Map<String, dynamic>? profile, AppLocalizations l) {
    final gpa = _toDouble(data?['gpa'] ?? profile?['avg_gpa']);
    final avgGrade = _toDouble(data?['avg_grade'] ?? profile?['avg_grade']);
    final currentSemAvg = _toDouble(data?['current_semester_avg']);
    final prevSemAvg = _toDouble(data?['prev_semester_avg']);
    final recentGrades = data?['recent_grades'] as List<dynamic>? ?? [];

    final sparkPoints = recentGrades
        .where((g) => g is Map<String, dynamic>)
        .map((g) => _toDouble((g as Map<String, dynamic>)['grade']))
        .toList()
        .reversed
        .toList();

    final gradeDiff = (prevSemAvg > 0) ? currentSemAvg - prevSemAvg : null;
    final gpaDiff = (prevSemAvg > 0) ? (currentSemAvg - prevSemAvg) * 4.0 / 100.0 : null;

    return Row(
      children: [
        Expanded(
          child: _buildSparkCard(
            icon: Icons.trending_up,
            value: gpa.toStringAsFixed(2),
            subtitle: 'GPA · 4.00+',
            diff: gpaDiff,
            sparkData: sparkPoints.map((g) => g * 4.0 / 100.0).toList(),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _buildSparkCard(
            icon: Icons.star_outline,
            value: avgGrade.toStringAsFixed(1),
            subtitle: l.avgGrade,
            diff: gradeDiff,
            sparkData: sparkPoints,
          ),
        ),
      ],
    );
  }

  Widget _buildSparkCard({
    required IconData icon,
    required String value,
    required String subtitle,
    required List<double> sparkData,
    double? diff,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isUp = diff != null && diff >= 0;
    final trendColor = (diff == null) ? AppTheme.accentColor : (isUp ? const Color(0xFF4CAF50) : const Color(0xFFE53935));
    final bgColor = isDark ? const Color(0xFF1A1A2E) : Colors.white;
    final textColor = isDark ? Colors.white : AppTheme.textPrimary;
    final subTextColor = isDark ? Colors.white.withAlpha(120) : AppTheme.textSecondary;

    final spots = <FlSpot>[];
    if (sparkData.length >= 2) {
      for (int i = 0; i < sparkData.length; i++) {
        spots.add(FlSpot(i.toDouble(), sparkData[i]));
      }
    } else {
      spots.addAll([
        const FlSpot(0, 0.3), const FlSpot(1, 0.5), const FlSpot(2, 0.4),
        const FlSpot(3, 0.7), const FlSpot(4, 0.6), const FlSpot(5, 0.8),
      ]);
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(18),
        boxShadow: isDark ? null : [
          BoxShadow(
            color: Colors.black.withAlpha(12),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: trendColor.withAlpha(25),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: trendColor, size: 20),
              ),
              const Spacer(),
              if (diff != null)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: trendColor.withAlpha(20),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        isUp ? Icons.arrow_upward : Icons.arrow_downward,
                        size: 12,
                        color: trendColor,
                      ),
                      const SizedBox(width: 2),
                      Text(
                        '${isUp ? "+" : ""}${diff.toStringAsFixed(1)}',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: trendColor,
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            value,
            style: TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w800,
              color: textColor,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            subtitle,
            style: TextStyle(fontSize: 12, color: subTextColor),
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 32,
            child: LineChart(
              LineChartData(
                gridData: const FlGridData(show: false),
                titlesData: const FlTitlesData(show: false),
                borderData: FlBorderData(show: false),
                lineTouchData: const LineTouchData(enabled: false),
                clipData: const FlClipData.all(),
                lineBarsData: [
                  LineChartBarData(
                    spots: spots,
                    isCurved: true,
                    curveSmoothness: 0.3,
                    color: trendColor,
                    barWidth: 2,
                    isStrokeCapRound: true,
                    dotData: const FlDotData(show: false),
                    belowBarData: BarAreaData(
                      show: true,
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          trendColor.withAlpha(60),
                          trendColor.withAlpha(5),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
