import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import 'dart:async';
import '../../config/theme.dart';
import '../../config/api_config.dart';
import '../../providers/student_provider.dart';
import '../../l10n/app_localizations.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/settings_sheet.dart';
import '../../widgets/notification_bell.dart';
import 'student_home_screen.dart';

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
      provider.loadSubjects();
      // Use cached schedule immediately, then refresh in background
      _parseSchedule(provider.schedule);
      _loadTodaySchedule();
    });
  }

  @override
  void dispose() {
    _clockTimer?.cancel();
    super.dispose();
  }

  void _parseSchedule(Map<String, dynamic>? schedule) {
    if (schedule == null) {
      debugPrint('[SCHEDULE] schedule is null');
      return;
    }
    try {
      final today = DateFormat('yyyy-MM-dd').format(DateTime.now());
      debugPrint('[SCHEDULE] today=$today');
      final scheduleList = schedule['schedule'];
      if (scheduleList == null || scheduleList is! List) {
        debugPrint('[SCHEDULE] scheduleList is null or not List, keys=${schedule.keys.toList()}');
        return;
      }
      debugPrint('[SCHEDULE] days count=${scheduleList.length}');
      for (final day in scheduleList) {
        if (day is! Map<String, dynamic>) continue;
        final d = day['date']?.toString() ?? '?';
        final les = day['lessons'];
        final count = (les is List) ? les.length : 0;
        debugPrint('[SCHEDULE] day=$d lessons=$count');
      }

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

      setState(() => _todayLessons = []);
      final todayDate = DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);
      // Collect future days with lessons, then sort to find nearest
      final futureDays = <Map<String, dynamic>>[];
      for (final day in scheduleList) {
        if (day is! Map<String, dynamic>) continue;
        final dateStr = day['date']?.toString() ?? '';
        if (dateStr.isEmpty) continue;
        final dayDate = DateTime.tryParse(dateStr);
        if (dayDate == null) continue;
        final dayOnly = DateTime(dayDate.year, dayDate.month, dayDate.day);
        if (!dayOnly.isAfter(todayDate)) continue;
        final lessons = day['lessons'];
        if (lessons is! List || lessons.isEmpty) continue;
        futureDays.add(day);
      }
      if (futureDays.isNotEmpty) {
        futureDays.sort((a, b) => a['date'].toString().compareTo(b['date'].toString()));
        final nearest = futureDays.first;
        final firstLesson = (nearest['lessons'] as List).first;
        if (firstLesson is Map<String, dynamic>) {
          final dStr = nearest['date'].toString();
          setState(() {
            _nextDayLesson = {
              ...firstLesson,
              '_date': dStr,
              '_day_date': DateTime.parse(dStr),
            };
          });
          return;
        }
      }
      setState(() => _nextDayLesson = null);
    } catch (_) {}
  }

  Future<void> _loadTodaySchedule() async {
    try {
      final provider = context.read<StudentProvider>();
      await provider.loadSchedule();
      if (!mounted) return;
      _parseSchedule(provider.schedule);
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

  // ── Clinic-calm palette ──────────────────────────────
  static const _calmBg = Color(0xFFFFFFFF);
  static const _calmInk = Color(0xFF0F172A);
  static const _calmMuted = Color(0xFF64748B);
  static const _calmFaint = Color(0xFF94A3B8);
  static const _calmTeal = Color(0xFF0D9488);
  static const _calmBlue = Color(0xFF1E3A8A);
  static const _calmGreen = Color(0xFF047857);
  static const _calmLine = Color(0xFFE2E8F0);

  Color get _ink => Theme.of(context).brightness == Brightness.dark
      ? Colors.white
      : _calmInk;
  Color get _muted => Theme.of(context).brightness == Brightness.dark
      ? AppTheme.darkTextSecondary
      : _calmMuted;
  Color get _surface => Theme.of(context).brightness == Brightness.dark
      ? AppTheme.darkCard
      : Colors.white;
  Color get _divider => Theme.of(context).brightness == Brightness.dark
      ? Colors.white.withOpacity(0.08)
      : _calmLine;

  List<BoxShadow> get _cardShadow => [
        BoxShadow(
          color: const Color(0xFF0F172A).withOpacity(0.06),
          blurRadius: 10,
          offset: const Offset(0, 3),
        ),
      ];

  Widget _calmCard({required Widget child, EdgeInsets? padding, double radius = 16}) {
    return Container(
      width: double.infinity,
      padding: padding ?? const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _surface,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: _divider, width: 1),
        boxShadow: _cardShadow,
      ),
      child: child,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : _calmBg,
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
                  Icon(Icons.error_outline, size: 48, color: _muted),
                  const SizedBox(height: 16),
                  Text(provider.error ?? l.noData, style: TextStyle(color: _ink)),
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
                provider.loadSubjects(),
              ]);
              _loadTodaySchedule();
            },
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              child: Column(
                children: [
                  _buildHeader(context, l),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(14, 10, 14, 0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _buildProfileCard(data, profile),
                        const SizedBox(height: 8),
                        _buildGpaRow(data, profile, l),
                        const SizedBox(height: 8),
                        _buildWeeklyActivity(data),
                        const SizedBox(height: 8),
                        _buildLiveClassCard(),
                        _buildSubjectsOverview(provider.subjects, isDark, l),
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

  // ── Header ───────────────────────────────────────────
  Widget _buildHeader(BuildContext context, AppLocalizations l) {
    final statusBarH = MediaQuery.of(context).padding.top;
    return Container(
      padding: EdgeInsets.fromLTRB(16, statusBarH + 10, 16, 14),
      decoration: BoxDecoration(
        color: _surface,
        border: Border(bottom: BorderSide(color: _divider, width: 1)),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: _calmTeal,
              borderRadius: BorderRadius.circular(11),
            ),
            child: const Icon(Icons.account_balance_rounded, color: Colors.white, size: 20),
          ),
          const SizedBox(width: 11),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'MED · UNIVERSITY',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w600,
                  letterSpacing: 0.5,
                  color: _muted,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                l.home,
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: _ink,
                ),
              ),
            ],
          ),
          const Spacer(),
          _headerIconButton(
            child: NotificationBell(iconColor: _ink, iconSize: 18),
          ),
          const SizedBox(width: 8),
          _headerIconButton(
            child: IconButton(
              padding: EdgeInsets.zero,
              icon: Icon(Icons.settings_outlined, color: _ink, size: 18),
              onPressed: () => showSettingsSheet(context),
            ),
          ),
        ],
      ),
    );
  }

  Widget _headerIconButton({required Widget child}) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: isDark ? Colors.white.withOpacity(0.06) : const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(11),
      ),
      child: child,
    );
  }


  // Kept for tuition/contract sections — now renders a plain clinic-calm card.
  Widget _buildGlassCard({required Widget child, required bool isDark, double borderRadius = 16, Color? cardColor}) {
    return Container(
      decoration: BoxDecoration(
        color: _surface,
        borderRadius: BorderRadius.circular(borderRadius),
        border: Border.all(color: _divider, width: 1),
        boxShadow: _cardShadow,
      ),
      child: child,
    );
  }

  // ── Profile card ─────────────────────────────────────
  Widget _buildProfileCard(Map<String, dynamic>? data, Map<String, dynamic>? profile) {
    final fullName = profile?['full_name']?.toString() ??
        data?['student_name']?.toString() ??
        '';
    final studentId = profile?['student_id_number']?.toString() ?? '';
    final imageUrl = _buildImageUrl(profile?['image']?.toString());
    final course = profile?['course']?.toString() ?? '';
    final yearOfEnter = profile?['year_of_enter']?.toString() ?? '';
    final educationYear = profile?['education_year_name']?.toString() ?? '';
    final semesterName = profile?['semester_name']?.toString() ?? '';
    final paymentFormName = profile?['payment_form_name']?.toString() ?? '';

    return _calmCard(
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: const BoxDecoration(
                  color: _calmTeal,
                  shape: BoxShape.circle,
                ),
                clipBehavior: Clip.antiAlias,
                child: imageUrl != null && imageUrl.isNotEmpty
                    ? CachedNetworkImage(
                        imageUrl: imageUrl,
                        width: 52,
                        height: 52,
                        fit: BoxFit.cover,
                        placeholder: (_, __) => _avatarInitials(fullName),
                        errorWidget: (_, __, ___) => _avatarInitials(fullName),
                      )
                    : _avatarInitials(fullName),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      fullName.toUpperCase(),
                      style: TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.2,
                        color: _ink,
                        height: 1.25,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text.rich(
                      TextSpan(
                        children: [
                          TextSpan(
                            text: 'ID · ',
                            style: TextStyle(
                                fontSize: 11, color: _muted, fontWeight: FontWeight.w400),
                          ),
                          TextSpan(
                            text: studentId,
                            style: TextStyle(
                                fontSize: 11, color: _ink, fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 6),
                    if (paymentFormName.isNotEmpty)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF0FDF4),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 6,
                              height: 6,
                              decoration: const BoxDecoration(
                                  color: Color(0xFF10B981), shape: BoxShape.circle),
                            ),
                            const SizedBox(width: 5),
                            Text(
                              paymentFormName,
                              style: const TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.w600,
                                color: _calmGreen,
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Divider(height: 1, color: _divider),
          const SizedBox(height: 14),
          Row(
            children: [
              _buildStatCell('YIL', yearOfEnter.isNotEmpty ? yearOfEnter : '—'),
              _statDivider(),
              _buildStatCell('KURS', course.isNotEmpty ? '$course-kurs' : '—'),
              _statDivider(),
              _buildStatCell('SEMESTR', semesterName.isNotEmpty ? semesterName : '—'),
              _statDivider(),
              _buildStatCell('O\'QUV YIL', educationYear.isNotEmpty ? educationYear : '—'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _avatarInitials(String name) {
    return Container(
      color: _calmTeal,
      alignment: Alignment.center,
      child: Text(
        _getInitials(name).toUpperCase(),
        style: const TextStyle(
          fontSize: 19,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.5,
          color: Colors.white,
        ),
      ),
    );
  }

  Widget _statDivider() => Container(width: 1, height: 30, color: _divider);

  Widget _buildStatCell(String label, String value) {
    return Expanded(
      child: Column(
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.4,
              color: _calmFaint,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: _ink),
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }

  // ── Weekly activity ──────────────────────────────────
  Widget _buildWeeklyActivity(Map<String, dynamic>? data) {
    // Attendance streak — consecutive days since the last absence (API).
    final streakRaw = data?['attendance_streak_days'];
    final streak = streakRaw is num ? streakRaw.toInt() : 0;
    final isGood = streak >= 7;
    final accent = isGood ? _calmTeal : AppTheme.warningColor;

    return _calmCard(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.favorite_rounded, size: 16, color: _calmTeal),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'HAFTALIK FAOLLIK',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 0.5,
                        color: _muted,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '$streak kun · ketma-ket',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: _ink,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: isGood
                      ? const Color(0xFFF0FDF4)
                      : AppTheme.warningColor.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  isGood ? 'NORMA' : 'PAST',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    color: isGood ? _calmGreen : AppTheme.warningColor,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 46,
            width: double.infinity,
            child: _EcgLine(accent),
          ),
        ],
      ),
    );
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

  // ── Fanlar ───────────────────────────────────────────
  Widget _buildSubjectsOverview(List<dynamic>? subjects, bool isDark, AppLocalizations l) {
    if (subjects == null || subjects.isEmpty) return const SizedBox.shrink();

    final items = <Map<String, dynamic>>[];
    for (final s in subjects) {
      if (s is! Map<String, dynamic>) continue;
      final grades = s['grades'] as Map<String, dynamic>? ?? {};
      final jn = grades['jn'];
      final jnVal = jn != null
          ? (jn is num ? jn.toDouble() : double.tryParse(jn.toString()))
          : null;
      final absentHours = _toDouble(s['absent_hours']);
      final totalHours = _toDouble(s['auditorium_hours']);
      items.add({
        'subject_id': s['subject_id'],
        'name': s['subject_name']?.toString() ?? '',
        'jn': jnVal,
        'absent': absentHours.round(),
        'total': totalHours.round(),
      });
    }
    if (items.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 4),
        Row(
          children: [
            Text(
              'Fanlar',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: _ink),
            ),
            const Spacer(),
            GestureDetector(
              onTap: () => StudentHomeScreen.switchToGrades(context),
              child: Row(
                children: [
                  Text(
                    'Barchasi',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: _calmTeal,
                    ),
                  ),
                  const SizedBox(width: 2),
                  const Icon(Icons.arrow_forward_rounded, size: 13, color: _calmTeal),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        _calmCard(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Column(
            children: [
              for (int i = 0; i < items.length; i++) ...[
                if (i > 0)
                  Divider(height: 1, indent: 16, endIndent: 16, color: _divider),
                _buildSubjectRow(items[i]),
              ],
            ],
          ),
        ),
        const SizedBox(height: 4),
      ],
    );
  }

  Widget _buildSubjectRow(Map<String, dynamic> item) {
    final jn = item['jn'] as double?;
    final name = item['name'] as String;
    final absent = item['absent'] as int;
    final total = item['total'] as int;

    Color badgeColor;
    if (jn == null) {
      badgeColor = _calmMuted;
    } else if (jn >= 86) {
      badgeColor = const Color(0xFF059669); // a'lo
    } else if (jn >= 71) {
      badgeColor = _calmBlue; // yaxshi
    } else if (jn >= 56) {
      badgeColor = AppTheme.warningColor; // qoniqarli
    } else {
      badgeColor = AppTheme.errorColor; // qoniqarsiz
    }

    final rawId = item['subject_id'];
    final subjectId = rawId is int
        ? rawId
        : (rawId == null ? null : int.tryParse(rawId.toString()));
    final progress = total > 0 ? ((total - absent) / total).clamp(0.0, 1.0) : 0.0;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => subjectId != null
            ? StudentHomeScreen.openSubject(context, subjectId)
            : StudentHomeScreen.switchToGrades(context),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: badgeColor.withOpacity(0.14),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  jn != null ? jn.round().toString() : '—',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: badgeColor,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: _ink,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 7),
                    Row(
                      children: [
                        Expanded(
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(3),
                            child: LinearProgressIndicator(
                              value: progress,
                              minHeight: 4,
                              backgroundColor: _divider,
                              valueColor: AlwaysStoppedAnimation<Color>(_calmTeal),
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '$absent/$total soat',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: _muted,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              Icon(Icons.chevron_right_rounded, size: 18, color: _calmFaint),
            ],
          ),
        ),
      ),
    );
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
    final textColor = isDark ? Colors.white : AppTheme.textPrimary;
    final subTextColor = isDark ? Colors.white70 : AppTheme.textSecondary;

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
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w800,
            color: textColor,
            letterSpacing: 0.3,
          ),
        ),
        const SizedBox(height: 12),
        _buildGlassCard(
          isDark: isDark,
          borderRadius: 16,
          cardColor: const Color(0xFF00897B),
          child: Padding(
            padding: const EdgeInsets.all(16),
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
        ),
        // Contract list section
        if (isContract && contractList != null && contractList.isNotEmpty) ...[
          const SizedBox(height: 20),
          Text(
            l.contractList,
            style: TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.w800,
              color: textColor,
              letterSpacing: 0.3,
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

            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _buildGlassCard(
                isDark: isDark,
                borderRadius: 14,
                cardColor: const Color(0xFF4A6CF7),
                child: Padding(
                  padding: const EdgeInsets.all(14),
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
            ),
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

    if (isActive) {
      remaining = end.difference(_now);
      statusText = 'HOZIR DAVOM ETMOQDA';
    } else {
      remaining = start.difference(_now);
      statusText = 'KEYINGI DARS';
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

    final gradientColors = isActive
        ? [const Color(0xFF2E7D32), const Color(0xFF43A047), const Color(0xFF66BB6A)]
        : [const Color(0xFFE65100), const Color(0xFFF57C00), const Color(0xFFFFA726)];
    final shadowColor = isActive ? const Color(0xFF43A047) : const Color(0xFFF57C00);

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Container(
        width: double.infinity,
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: gradientColors,
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(22),
          boxShadow: [
            BoxShadow(
              color: shadowColor.withAlpha(70),
              blurRadius: 20,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Stack(
          children: [
            Positioned(
              right: -30,
              top: -30,
              child: Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withAlpha(15),
                ),
              ),
            ),
            Positioned(
              right: 20,
              bottom: -20,
              child: Container(
                width: 70,
                height: 70,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withAlpha(10),
                ),
              ),
            ),
            Positioned(
              left: -15,
              bottom: -15,
              child: Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withAlpha(8),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      if (isActive)
                        _buildBlinkingDot()
                      else
                        Container(
                          width: 8, height: 8,
                          decoration: BoxDecoration(
                            color: Colors.white.withAlpha(200),
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
                      Icon(Icons.access_time_rounded, size: 16, color: Colors.white.withAlpha(200)),
                      const SizedBox(width: 4),
                      Text(
                        '$startTime–$endTime',
                        style: TextStyle(fontSize: 14, color: Colors.white.withAlpha(230), fontWeight: FontWeight.w500),
                      ),
                      if (room.isNotEmpty) ...[
                        const SizedBox(width: 10),
                        Text('·', style: TextStyle(fontSize: 18, color: Colors.white.withAlpha(180), fontWeight: FontWeight.w700)),
                        const SizedBox(width: 6),
                        Text(room, style: TextStyle(fontSize: 12, color: Colors.white.withAlpha(230), fontWeight: FontWeight.w500)),
                      ],
                    ],
                  ),
                  const SizedBox(height: 14),
                  if (isActive) ...[
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: LinearProgressIndicator(
                        value: progress,
                        minHeight: 5,
                        backgroundColor: Colors.white.withAlpha(40),
                        valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
                      ),
                    ),
                    const SizedBox(height: 8),
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
          ],
        ),
      ),
    );
  }

  Widget _buildBlinkingDot() {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0.3, end: 1.0),
      duration: const Duration(milliseconds: 800),
      builder: (context, val, child) {
        return Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Colors.white.withAlpha((val * 255).toInt()),
            boxShadow: [
              BoxShadow(
                color: Colors.white.withAlpha((val * 120).toInt()),
                blurRadius: 6,
                spreadRadius: 1,
              ),
            ],
          ),
        );
      },
      onEnd: () {
        if (mounted) setState(() {});
      },
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
      padding: const EdgeInsets.only(bottom: 8),
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
                  Text(room, style: TextStyle(fontSize: 12, color: Colors.white.withAlpha(220), fontWeight: FontWeight.w500)),
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

  // ── GPA + O'rtacha cards ─────────────────────────────
  Widget _buildGpaRow(Map<String, dynamic>? data, Map<String, dynamic>? profile, AppLocalizations l) {
    final gpa = _toDouble(data?['gpa'] ?? profile?['avg_gpa']);
    final avgGrade = _toDouble(data?['avg_grade'] ?? profile?['avg_grade']);

    // O'rtacha trend = current vs previous semester average (real data).
    final curAvg = data?['current_semester_avg'];
    final prevAvg = data?['prev_semester_avg'];
    double? avgTrend;
    if (curAvg is num && prevAvg is num) {
      avgTrend = curAvg.toDouble() - prevAvg.toDouble();
    }

    // GPA trend = current vs previous semester GPA (real data).
    final curGpa = data?['current_semester_gpa'];
    final prevGpa = data?['prev_semester_gpa'];
    double? gpaTrend;
    if (curGpa is num && prevGpa is num) {
      final diff = curGpa.toDouble() - prevGpa.toDouble();
      // Guard against non-GPA-scale data — GPA shifts are small.
      if (curGpa <= 5 && prevGpa <= 5 && diff.abs() <= 1.5) {
        gpaTrend = diff;
      }
    }

    return Row(
      children: [
        Expanded(
          child: _buildStatCard(
            label: 'GPA',
            value: gpa.toStringAsFixed(2),
            maxLabel: '/ 5',
            progress: (gpa / 5.0).clamp(0.0, 1.0),
            accent: _calmTeal,
            trend: gpaTrend,
            trendDigits: 2,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _buildStatCard(
            label: 'O\'RTACHA',
            value: avgGrade.toStringAsFixed(1),
            maxLabel: '/ 100',
            progress: (avgGrade / 100.0).clamp(0.0, 1.0),
            accent: _calmBlue,
            trend: avgTrend,
            trendDigits: 1,
          ),
        ),
      ],
    );
  }

  Widget _buildStatCard({
    required String label,
    required String value,
    required String maxLabel,
    required double progress,
    required Color accent,
    double? trend,
    int trendDigits = 1,
  }) {
    return _calmCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  label,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.5,
                    color: _muted,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (trend != null && trend != 0)
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      trend > 0 ? Icons.arrow_upward_rounded : Icons.arrow_downward_rounded,
                      size: 10,
                      color: trend > 0 ? _calmGreen : AppTheme.errorColor,
                    ),
                    const SizedBox(width: 1),
                    Text(
                      '${trend > 0 ? '+' : ''}${trend.toStringAsFixed(trendDigits)}',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: trend > 0 ? _calmGreen : AppTheme.errorColor,
                      ),
                    ),
                  ],
                ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(
                value,
                style: TextStyle(
                  fontSize: 32,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.5,
                  color: accent,
                  height: 1,
                ),
              ),
              const SizedBox(width: 4),
              Text(
                maxLabel,
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: _muted),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: TweenAnimationBuilder<double>(
              tween: Tween(begin: 0, end: progress),
              duration: const Duration(milliseconds: 1100),
              curve: Curves.easeOutCubic,
              builder: (_, v, __) => LinearProgressIndicator(
                value: v,
                minHeight: 6,
                backgroundColor: accent.withOpacity(0.12),
                valueColor: AlwaysStoppedAnimation<Color>(accent),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Animated ECG / heartbeat line for the weekly-activity card — a bright
/// pulse sweeps along a faint baseline trace, like a heart monitor.
class _EcgLine extends StatefulWidget {
  final Color color;
  const _EcgLine(this.color);

  @override
  State<_EcgLine> createState() => _EcgLineState();
}

class _EcgLineState extends State<_EcgLine> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2600),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (_, __) => CustomPaint(
        size: Size.infinite,
        painter: _EcgLinePainter(widget.color, _controller.value),
      ),
    );
  }
}

class _EcgLinePainter extends CustomPainter {
  final Color color;
  final double progress;
  const _EcgLinePainter(this.color, this.progress);

  // Normalized (0–1) points of an ECG trace — flat baseline with QRS spikes.
  static const List<Offset> _points = [
    Offset(0.000, 0.5), Offset(0.111, 0.5), Offset(0.153, 0.5),
    Offset(0.181, 0.2), Offset(0.208, 0.8), Offset(0.236, 0.5),
    Offset(0.333, 0.5), Offset(0.375, 0.5), Offset(0.403, 0.16),
    Offset(0.431, 0.84), Offset(0.458, 0.5), Offset(0.583, 0.5),
    Offset(0.625, 0.5), Offset(0.653, 0.24), Offset(0.681, 0.76),
    Offset(0.708, 0.5), Offset(0.833, 0.5), Offset(0.875, 0.5),
    Offset(0.903, 0.2), Offset(0.931, 0.8), Offset(0.958, 0.5),
    Offset(1.000, 0.5),
  ];

  @override
  void paint(Canvas canvas, Size size) {
    final path = Path();
    for (var i = 0; i < _points.length; i++) {
      final x = _points[i].dx * size.width;
      final y = _points[i].dy * size.height;
      i == 0 ? path.moveTo(x, y) : path.lineTo(x, y);
    }

    // Faint full baseline trace.
    canvas.drawPath(
      path,
      Paint()
        ..color = color.withOpacity(0.18)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.8
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );

    final metrics = path.computeMetrics().toList();
    if (metrics.isEmpty) return;
    final metric = metrics.first;
    final len = metric.length;
    final head = progress * len;
    final tail = (head - len * 0.32).clamp(0.0, len);

    // Bright pulse segment sweeping along the trace.
    canvas.drawPath(
      metric.extractPath(tail, head.clamp(0.0, len)),
      Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.2
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );

    // Leading pulse dot.
    final tan = metric.getTangentForOffset(head.clamp(0.0, len));
    if (tan != null) {
      canvas.drawCircle(tan.position, 3.4, Paint()..color = color);
    }
  }

  @override
  bool shouldRepaint(_EcgLinePainter old) =>
      old.progress != progress || old.color != color;
}
