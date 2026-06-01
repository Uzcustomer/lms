import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:file_picker/file_picker.dart';
import '../../config/theme.dart';
import '../../config/api_config.dart';
import '../../providers/student_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_data_cache.dart';
import '../../services/student_service.dart';
import '../../l10n/app_localizations.dart';
import '../../widgets/loading_widget.dart';
import '../../utils/page_transitions.dart';
import '../../widgets/scale_tap.dart';
import '../../widgets/settings_sheet.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/clinic_header.dart';
import 'student_home_screen.dart';

class StudentGradesScreen extends StatefulWidget {
  const StudentGradesScreen({super.key});

  @override
  State<StudentGradesScreen> createState() => _StudentGradesScreenState();
}

class _StudentGradesScreenState extends State<StudentGradesScreen> {
  int _selectedFilter = 0;
  bool _isUploading = false;
  int _uploadingSubjectId = -1;
  // GlobalKey per subject card so we can scroll to it when the dashboard
  // asks us to via StudentHomeScreen.pendingSubjectScroll.
  final Map<int, GlobalKey> _subjectKeys = {};
  int? _highlightedSubjectId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<StudentProvider>();
      provider.loadSubjects();
      provider.loadDashboard();
    });
    StudentHomeScreen.pendingSubjectScroll.addListener(_onScrollRequest);
  }

  @override
  void dispose() {
    StudentHomeScreen.pendingSubjectScroll.removeListener(_onScrollRequest);
    super.dispose();
  }

  void _onScrollRequest() {
    final id = StudentHomeScreen.pendingSubjectScroll.value;
    if (id == null) return;
    // Reset early so repeated taps on the same subject still trigger.
    StudentHomeScreen.pendingSubjectScroll.value = null;
    // Force the filter to "All" so the target card is in the list.
    if (_selectedFilter != 0) {
      setState(() => _selectedFilter = 0);
    }
    if (mounted) setState(() => _highlightedSubjectId = id);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final key = _subjectKeys[id];
      final ctx = key?.currentContext;
      if (ctx != null) {
        await Scrollable.ensureVisible(
          ctx,
          duration: const Duration(milliseconds: 450),
          curve: Curves.easeOutCubic,
          alignment: 0.1,
        );
      }
      // Clear the highlight after a moment so it doesn't linger forever.
      Future.delayed(const Duration(milliseconds: 1600), () {
        if (mounted && _highlightedSubjectId == id) {
          setState(() => _highlightedSubjectId = null);
        }
      });
    });
  }

  static const List<Color> _cardColors = [
    Color(0xFFE3F2FD),
    Color(0xFFE8F5E9),
    Color(0xFFFFF3E0),
    Color(0xFFF3E5F5),
    Color(0xFFFCE4EC),
    Color(0xFFE0F2F1),
  ];

  static const List<Color> _cardTextColors = [
    Color(0xFF1565C0),
    Color(0xFF2E7D32),
    Color(0xFFE65100),
    Color(0xFF7B1FA2),
    Color(0xFFC62828),
    Color(0xFF00695C),
  ];

  static const List<IconData> _cardIcons = [
    Icons.assignment,
    Icons.menu_book,
    Icons.quiz,
    Icons.school,
    Icons.fact_check,
    Icons.emoji_events,
  ];

  // Saturated grade-chip colours, one per column (JN, MT, ON, OSKI, TEST, YN).
  static const List<Color> _gradeColors = [
    Color(0xFF15803D), // JN   — green
    Color(0xFF0F766E), // MT   — teal
    Color(0xFFB45309), // ON   — amber
    Color(0xFF1D4ED8), // OSKI — blue
    Color(0xFFBE123C), // TEST — rose
    Color(0xFF6D28D9), // YN   — violet
  ];

  // ── Clinic-calm palette ──────────────────────────────
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
          color: const Color(0xFF0F172A).withOpacity(0.14),
          blurRadius: 5,
          offset: const Offset(0, 2),
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

  bool _isSubjectCompleted(Map<String, dynamic> subject) {
    final grades = subject['grades'] as Map<String, dynamic>? ?? {};
    return grades['oski'] != null || grades['test'] != null;
  }

  /// Total grade for averaging purposes — reads only what the LMS API
  /// supplies, never computes locally. Returns 0 when no YN is set yet.
  double _getSubjectTotal(Map<String, dynamic> subject) {
    final v = _getYn(subject);
    return v ?? 0;
  }

  /// Final (YN) grade for a subject — comes from the LMS journal via the
  /// API. The app never computes YN locally; until the journal sets a YN
  /// for the subject, this returns null and the UI shows an empty cell.
  double? _getYn(Map<String, dynamic> subject) {
    final grades = subject['grades'] as Map<String, dynamic>? ?? {};
    final raw = grades['yn'];
    if (raw is num) {
      final d = raw.toDouble();
      return d > 0 ? d : null;
    }
    if (raw is String) {
      final parsed = double.tryParse(raw);
      if (parsed != null && parsed > 0) return parsed;
    }
    return null;
  }

  double _calculateSemesterAvg(List subjects) {
    double sum = 0;
    int count = 0;
    for (final s in subjects) {
      if (s is! Map<String, dynamic>) continue;
      final t = _getSubjectTotal(s);
      if (t > 0) { sum += t; count++; }
    }
    return count > 0 ? sum / count : 0;
  }

  List<Map<String, dynamic>> _filterSubjects(List subjects) {
    final all = subjects.whereType<Map<String, dynamic>>().toList();
    if (_selectedFilter == 1) return all.where((s) => _isSubjectCompleted(s)).toList();
    if (_selectedFilter == 2) return all.where((s) => !_isSubjectCompleted(s)).toList();
    return all;
  }

  Map<String, dynamic>? _getBestSubject(List subjects) {
    Map<String, dynamic>? best;
    double bestGrade = 0;
    for (final s in subjects) {
      if (s is! Map<String, dynamic>) continue;
      final yn = _getYn(s);
      if (yn != null && yn > bestGrade) {
        bestGrade = yn;
        best = s;
      }
    }
    return best;
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : Colors.white,
      body: Consumer<StudentProvider>(
          builder: (context, provider, _) {
            if (provider.isLoading && provider.subjects == null) {
              return const Center(child: LoadingWidget());
            }

            final subjects = provider.subjects;
            if (subjects == null || subjects.isEmpty) {
              return Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.school_outlined, size: 64, color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                    const SizedBox(height: 16),
                    Text(provider.error ?? l.get('no_subjects'),
                      style: TextStyle(color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary)),
                    const SizedBox(height: 16),
                    ElevatedButton(onPressed: () => provider.loadSubjects(), child: Text(l.reload)),
                  ],
                ),
              );
            }

            final semesterAvg = _calculateSemesterAvg(subjects);
            final completed = subjects.whereType<Map<String, dynamic>>().where((s) => _isSubjectCompleted(s)).length;
            final waiting = subjects.length - completed;
            final filtered = _filterSubjects(subjects);
            final bestSubject = _getBestSubject(subjects);
            // If the dashboard asked us to focus a specific subject, move it
            // to the top of the list so it's the first card the user sees.
            if (_highlightedSubjectId != null) {
              final idx = filtered.indexWhere((s) {
                final raw = s['subject_id'];
                final id = raw is int
                    ? raw
                    : (raw == null ? null : int.tryParse(raw.toString()));
                return id == _highlightedSubjectId;
              });
              if (idx > 0) {
                final pinned = filtered.removeAt(idx);
                filtered.insert(0, pinned);
              }
            }
            final semesterName = provider.profile?['semester_name']?.toString() ?? '';

            return RefreshIndicator(
              onRefresh: () => provider.refreshAll(),
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: Column(
                  children: [
                    _buildHeader(context, l, semesterName),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(14, 12, 14, 0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _buildSummaryCard(semesterAvg, subjects.length,
                              completed, waiting, semesterName, provider),
                          const SizedBox(height: 12),
                          if (bestSubject != null) ...[
                            _buildBestSubjectCard(bestSubject),
                            const SizedBox(height: 12),
                          ],
                          _buildFilterTabs(completed, waiting, subjects.length),
                          const SizedBox(height: 12),
                          ...filtered.asMap().entries.map((e) {
                            final subj = e.value;
                            final rawId = subj['subject_id'];
                            final sid = rawId is int
                                ? rawId
                                : (rawId == null ? null : int.tryParse(rawId.toString()));
                            final key = sid != null
                                ? _subjectKeys.putIfAbsent(sid, () => GlobalKey())
                                : null;
                            final highlighted =
                                sid != null && sid == _highlightedSubjectId;
                            return Padding(
                              key: key,
                              padding: const EdgeInsets.only(bottom: 12),
                              child: AnimatedContainer(
                                duration: const Duration(milliseconds: 300),
                                decoration: highlighted
                                    ? BoxDecoration(
                                        borderRadius: BorderRadius.circular(16),
                                        boxShadow: [
                                          BoxShadow(
                                            color: _calmTeal.withOpacity(0.45),
                                            blurRadius: 18,
                                            spreadRadius: 1,
                                          ),
                                        ],
                                      )
                                    : null,
                                child: _buildSubjectCard(context, subj, l),
                              ),
                            );
                          }),
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
  Widget _buildHeader(BuildContext context, AppLocalizations l, String semester) {
    final statusBarH = MediaQuery.of(context).padding.top;
    return Container(
      padding: EdgeInsets.fromLTRB(14, statusBarH + 10, 14, 12),
      decoration: BoxDecoration(
        color: _surface,
        border: Border(bottom: BorderSide(color: _divider, width: 1)),
      ),
      child: Row(
        children: [
          _headerIconButton(
            child: IconButton(
              padding: EdgeInsets.zero,
              icon: Icon(Icons.arrow_back_rounded, color: _ink, size: 20),
              onPressed: () => StudentHomeScreen.switchToHome(context),
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  semester.isNotEmpty
                      ? 'BAHOLAR · ${semester.toUpperCase()}'
                      : 'BAHOLAR',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.5,
                    color: _muted,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Akademik baholar',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: _ink),
                ),
              ],
            ),
          ),
          _headerIconButton(child: NotificationBell(iconColor: _ink, iconSize: 18)),
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

  // ── Summary card ─────────────────────────────────────
  Widget _buildSummaryCard(double avg, int total, int completed, int waiting,
      String semester, StudentProvider provider) {
    final dash = provider.dashboard;
    final cur = dash?['current_semester_avg'];
    final prev = dash?['prev_semester_avg'];
    double? trend;
    if (cur is num && prev is num) trend = cur.toDouble() - prev.toDouble();

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0D9488).withOpacity(0.30),
            blurRadius: 16,
            offset: const Offset(0, 7),
          ),
        ],
      ),
      child: ShinySweep(
        radius: 16,
        child: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF0D9488), Color(0xFF1E3A8A)],
            ),
          ),
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      semester.isNotEmpty
                          ? '${semester.toUpperCase()} · O\'RTACHA'
                          : 'SEMESTR · O\'RTACHA',
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.5,
                        color: Colors.white.withOpacity(0.8),
                      ),
                    ),
                  ),
                  if (trend != null && trend != 0) _trendChip(trend),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                crossAxisAlignment: CrossAxisAlignment.baseline,
                textBaseline: TextBaseline.alphabetic,
                children: [
                  Text(
                    avg.toStringAsFixed(1),
                    style: const TextStyle(
                      fontSize: 40,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -1,
                      color: Colors.white,
                      height: 1,
                    ),
                  ),
                  const SizedBox(width: 4),
                  Text('/ 100',
                      style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: Colors.white.withOpacity(0.7))),
                ],
              ),
              const SizedBox(height: 12),
              ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: TweenAnimationBuilder<double>(
                  tween: Tween(begin: 0, end: (avg / 100).clamp(0.0, 1.0)),
                  duration: const Duration(milliseconds: 1100),
                  curve: Curves.easeOutCubic,
                  builder: (_, v, __) => LinearProgressIndicator(
                    value: v,
                    minHeight: 7,
                    backgroundColor: Colors.white.withOpacity(0.18),
                    valueColor:
                        const AlwaysStoppedAnimation<Color>(Colors.white),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Divider(height: 1, color: Colors.white.withOpacity(0.22)),
              const SizedBox(height: 12),
              Row(
                children: [
                  _buildStatCell('$total', 'Fanlar'),
                  _statDivider(),
                  _buildStatCell('$completed', 'Topshirilgan'),
                  _statDivider(),
                  _buildStatCell('$waiting', 'Kutilmoqda'),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _trendChip(double trend) {
    final up = trend > 0;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(up ? Icons.arrow_upward_rounded : Icons.arrow_downward_rounded,
            size: 11, color: Colors.white),
        const SizedBox(width: 1),
        Text(
          '${up ? '+' : ''}${trend.toStringAsFixed(1)}',
          style: const TextStyle(
              fontSize: 11, fontWeight: FontWeight.w800, color: Colors.white),
        ),
      ],
    );
  }

  Widget _statDivider() =>
      Container(width: 1, height: 30, color: Colors.white.withOpacity(0.22));

  Widget _buildStatCell(String value, String label) {
    return Expanded(
      child: Column(
        children: [
          Text(value,
              style: const TextStyle(
                  fontSize: 19,
                  fontWeight: FontWeight.w900,
                  color: Colors.white)),
          const SizedBox(height: 3),
          Text(
            label,
            style: TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w600,
                color: Colors.white.withOpacity(0.75)),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }

  // ── Best subject ─────────────────────────────────────
  Widget _buildBestSubjectCard(Map<String, dynamic> subject) {
    final name = subject['subject_name']?.toString() ?? '';
    final grade = _getYn(subject)?.round() ?? 0;
    const gold = Color(0xFFD97706);
    return _calmCard(
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: gold.withOpacity(0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.star_rounded, color: gold, size: 24),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'ENG YUQORI BAHO',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.5,
                    color: _muted,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  name,
                  style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w800, color: _ink),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Text(
            '$grade',
            style: const TextStyle(
                fontSize: 26, fontWeight: FontWeight.w900, color: Color(0xFF15803D)),
          ),
        ],
      ),
    );
  }

  // ── Filter tabs ──────────────────────────────────────
  Widget _buildFilterTabs(int completed, int waiting, int total) {
    final labels = ['Hammasi', 'Topshirilgan', 'Kutilmoqda'];
    final counts = [total, completed, waiting];
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Row(
      children: List.generate(3, (i) {
        final isActive = _selectedFilter == i;
        return Expanded(
          child: GestureDetector(
            onTap: () => setState(() => _selectedFilter = i),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              margin: EdgeInsets.only(right: i < 2 ? 8 : 0),
              padding: const EdgeInsets.symmetric(vertical: 9, horizontal: 6),
              decoration: BoxDecoration(
                color: isActive
                    ? _calmTeal
                    : (isDark ? Colors.white.withOpacity(0.06) : const Color(0xFFF1F5F9)),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Flexible(
                    child: Text(
                      labels[i],
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: isActive ? FontWeight.w800 : FontWeight.w600,
                        color: isActive ? Colors.white : _muted,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 5),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                    decoration: BoxDecoration(
                      color: isActive
                          ? Colors.white.withOpacity(0.25)
                          : (isDark ? Colors.white12 : Colors.white),
                      borderRadius: BorderRadius.circular(7),
                    ),
                    child: Text(
                      '${counts[i]}',
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                        color: isActive ? Colors.white : _ink,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      }),
    );
  }

  // ── Subject card ─────────────────────────────────────
  Widget _buildSubjectCard(BuildContext context, Map<String, dynamic> subject, AppLocalizations l) {
    final grades = subject['grades'] as Map<String, dynamic>? ?? {};
    final name = subject['subject_name']?.toString() ?? '';
    final computedYn = _getYn(subject);
    final total = computedYn?.round() ?? 0;
    final isCompleted = _isSubjectCompleted(subject);
    final attendance = _getAttendancePercent(subject);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final gradeKeys = ['jn', 'mt', 'on', 'oski', 'test', 'total'];
    final gradeLabels = ['JN', 'MT', 'ON', 'OSKI', 'TEST', 'YN'];

    final totalColor = total >= 71
        ? _calmBlue
        : total > 0
            ? const Color(0xFFB45309)
            : _calmFaint;

    return _calmCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header: name + JAMI
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  name,
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: _ink),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    total > 0 ? '$total' : '—',
                    style: TextStyle(
                        fontSize: 22, fontWeight: FontWeight.w900, color: totalColor, height: 1),
                  ),
                  const SizedBox(height: 2),
                  Text('JAMI',
                      style: TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.5,
                          color: _calmFaint)),
                ],
              ),
            ],
          ),
          const SizedBox(height: 10),
          // Status + attendance
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                decoration: BoxDecoration(
                  color: isCompleted ? const Color(0xFFF0FDF4) : const Color(0xFFFFF7ED),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(isCompleted ? Icons.check_circle_rounded : Icons.schedule_rounded,
                        size: 12,
                        color: isCompleted ? _calmGreen : const Color(0xFFB45309)),
                    const SizedBox(width: 4),
                    Text(
                      isCompleted ? 'Topshirilgan' : 'Kutilmoqda',
                      style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          color: isCompleted ? _calmGreen : const Color(0xFFB45309)),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Text.rich(TextSpan(children: [
                TextSpan(
                    text: 'Davomat ',
                    style: TextStyle(fontSize: 11.5, color: _muted, fontWeight: FontWeight.w500)),
                TextSpan(
                    text: '${attendance.toStringAsFixed(0)}%',
                    style: TextStyle(fontSize: 11.5, color: _ink, fontWeight: FontWeight.w800)),
              ])),
            ],
          ),
          const SizedBox(height: 12),
          // Grade chips
          Row(
            children: List.generate(6, (i) {
              final key = gradeKeys[i];
              final value = key == 'total' ? computedYn?.round() : grades[key];
              final hasValue = value != null;
              final color = _gradeColors[i];
              return Expanded(
                child: ScaleTap(
                  scaleDown: 0.92,
                  onTap: () => _onGradeCardTap(context, subject, key,
                      gradeLabels[i], _gradeFullLabels[i], value, isDark, l),
                  child: Container(
                    margin: EdgeInsets.only(right: i < 5 ? 6 : 0),
                    padding: const EdgeInsets.symmetric(vertical: 7),
                    decoration: BoxDecoration(
                      color: hasValue ? color : _divider,
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: Column(
                      children: [
                        Text(
                          hasValue ? value.toString() : '—',
                          style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w900,
                              color: hasValue ? Colors.white : _calmFaint),
                        ),
                        const SizedBox(height: 1),
                        Text(
                          gradeLabels[i],
                          style: TextStyle(
                              fontSize: 8.5,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 0.3,
                              color: hasValue ? Colors.white.withOpacity(0.85) : _calmFaint),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
          ),
          // MT upload section
          if (subject['mt_submission'] != null) ...[
            const SizedBox(height: 12),
            _buildMtUploadSection(context, subject, l),
          ],
        ],
      ),
    );
  }

  static const List<String> _gradeFullLabels = [
    'Joriy nazorat', 'Mustaqil ta\'lim', 'Oraliq nazorat', 'OSKI', 'Test', 'Yakuniy',
  ];

  Widget _buildMtUploadSection(BuildContext context, Map<String, dynamic> subject, AppLocalizations l) {
    final mt = subject['mt_submission'] as Map<String, dynamic>;
    final hasSubmission = mt['has_submission'] == true;
    final canSubmit = mt['can_submit'] == true;
    final subjectId = subject['subject_id'];
    final isThisUploading = _isUploading && _uploadingSubjectId == subjectId;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: isDark ? Colors.white.withOpacity(0.04) : const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _divider, width: 1),
      ),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: _calmTeal.withOpacity(0.12),
              borderRadius: BorderRadius.circular(9),
            ),
            child: Icon(hasSubmission ? Icons.cloud_done_rounded : Icons.cloud_upload_rounded,
                color: _calmTeal, size: 18),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Mustaqil ta\'lim',
                    style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800, color: _ink)),
                const SizedBox(height: 1),
                Text(
                  hasSubmission
                      ? (canSubmit ? 'Yuklangan · ko\'rib chiqilmoqda' : 'Yuklangan')
                      : 'Yuklanmagan',
                  style: TextStyle(fontSize: 10.5, color: _muted, fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          GestureDetector(
            onTap: canSubmit && !isThisUploading ? () => _uploadMT(context, subject) : null,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: canSubmit
                    ? _calmTeal
                    : (isDark ? Colors.white12 : const Color(0xFFE2E8F0)),
                borderRadius: BorderRadius.circular(9),
              ),
              child: isThisUploading
                  ? const SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text(
                      'Yangilash',
                      style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w800,
                          color: canSubmit ? Colors.white : _calmFaint),
                    ),
            ),
          ),
        ],
      ),
    );
  }

  void _onGradeCardTap(
    BuildContext context,
    Map<String, dynamic> subject,
    String key,
    String label,
    String fullLabel,
    dynamic value,
    bool isDark,
    AppLocalizations l,
  ) {
    if (key == 'jn') {
      _showJnBottomSheet(context, subject, label, fullLabel, isDark, l);
    } else if (key == 'mt') {
      _showMtBottomSheet(context, subject, label, fullLabel, value, isDark, l);
    } else {
      _showGenericBottomSheet(context, label, fullLabel, value, isDark, l);
    }
  }

  void _showJnBottomSheet(
    BuildContext context,
    Map<String, dynamic> subject,
    String label,
    String fullLabel,
    bool isDark,
    AppLocalizations l,
  ) {
    final subjectId = subject['subject_id'];
    if (subjectId == null) return;
    final subjectName = subject['subject_name']?.toString() ?? '';

    Navigator.push(
      context,
      SlideFadePageRoute(
        builder: (_) => _JnGradesPage(
          subjectId: subjectId is int ? subjectId : int.parse(subjectId.toString()),
          subjectName: subjectName,
          fullLabel: fullLabel,
        ),
      ),
    );
  }

  void _showMtBottomSheet(
    BuildContext context,
    Map<String, dynamic> subject,
    String label,
    String fullLabel,
    dynamic value,
    bool isDark,
    AppLocalizations l,
  ) {
    final mt = subject['mt_submission'] as Map<String, dynamic>?;
    final bgColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final secondaryText = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        decoration: BoxDecoration(
          color: bgColor,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40, height: 4,
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(
                color: isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Row(
              children: [
                Container(
                  width: 44, height: 44,
                  decoration: BoxDecoration(
                    color: _cardColors[1].withAlpha(isDark ? 40 : 255),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(_cardIcons[1], color: _cardTextColors[1], size: 24),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(fullLabel, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: textColor)),
                      Text('$label: ${value?.toString() ?? '-'}',
                        style: TextStyle(fontSize: 14, color: _cardTextColors[1], fontWeight: FontWeight.w600)),
                    ],
                  ),
                ),
              ],
            ),
            if (mt != null) ...[
              const SizedBox(height: 16),
              _buildMtDetailRow(Icons.calendar_today, '${l.get("mt_deadline")}:', '${mt['deadline'] ?? '-'} ${mt['deadline_time'] ?? ''}', secondaryText, textColor),
              if (mt['grade'] != null)
                _buildMtDetailRow(Icons.grade, '${l.get("mt_graded")}:', mt['grade'].toString(), secondaryText, _cardTextColors[1]),
              if (mt['file_name'] != null)
                _buildMtDetailRow(Icons.attach_file, '${l.get("file")}:', mt['file_name'].toString(), secondaryText, textColor),
              if (mt['remaining_attempts'] != null)
                _buildMtDetailRow(Icons.replay, '${l.get("mt_remaining")}:', mt['remaining_attempts'].toString(), secondaryText, textColor),
              _buildMtDetailRow(
                mt['has_submission'] == true ? Icons.check_circle : Icons.cancel,
                'Status:',
                mt['has_submission'] == true
                    ? l.get('mt_uploaded')
                    : mt['is_overdue'] == true
                        ? l.get('mt_overdue')
                        : l.get('mt_not_uploaded'),
                secondaryText,
                mt['has_submission'] == true ? AppTheme.successColor : AppTheme.errorColor,
              ),
            ] else
              Padding(
                padding: const EdgeInsets.only(top: 16),
                child: Text(l.noData, style: TextStyle(color: secondaryText, fontSize: 14)),
              ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }

  Widget _buildMtDetailRow(IconData icon, String label, String value, Color labelColor, Color valueColor) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Icon(icon, size: 18, color: labelColor),
          const SizedBox(width: 10),
          Text(label, style: TextStyle(fontSize: 13, color: labelColor)),
          const SizedBox(width: 8),
          Expanded(
            child: Text(value, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: valueColor),
              textAlign: TextAlign.end, maxLines: 2, overflow: TextOverflow.ellipsis),
          ),
        ],
      ),
    );
  }

  void _showGenericBottomSheet(
    BuildContext context,
    String label,
    String fullLabel,
    dynamic value,
    bool isDark,
    AppLocalizations l,
  ) {
    final bgColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final idx = ['JN', 'MT', 'ON', 'OSKI', 'TEST', 'YN'].indexOf(label);
    final colorIdx = idx >= 0 ? idx : 0;

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        decoration: BoxDecoration(
          color: bgColor,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40, height: 4,
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(
                color: isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Container(
              width: 64, height: 64,
              decoration: BoxDecoration(
                color: _cardColors[colorIdx].withAlpha(isDark ? 40 : 255),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Icon(_cardIcons[colorIdx], color: _cardTextColors[colorIdx], size: 32),
            ),
            const SizedBox(height: 12),
            Text(fullLabel, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: textColor)),
            const SizedBox(height: 8),
            Text(
              value?.toString() ?? '-',
              style: TextStyle(fontSize: 36, fontWeight: FontWeight.bold, color: _cardTextColors[colorIdx]),
            ),
            const SizedBox(height: 6),
            Text(label, style: TextStyle(fontSize: 14, color: _cardTextColors[colorIdx].withAlpha(180))),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  double _getAttendancePercent(Map<String, dynamic> subject) {
    final davPercent = (subject['dav_percent'] is num)
        ? (subject['dav_percent'] as num).toDouble()
        : 0.0;
    return (100 - davPercent).clamp(0.0, 100.0);
  }

  Color _getAttendanceColor(double percent) {
    if (percent >= 85) return AppTheme.successColor;
    if (percent >= 70) return AppTheme.warningColor;
    return AppTheme.errorColor;
  }

  bool _canUploadMT(Map<String, dynamic> subject) {
    final mt = subject['mt_submission'] as Map<String, dynamic>?;
    if (mt == null) return false;
    return mt['can_submit'] == true;
  }

  bool _hasMtSubmission(Map<String, dynamic> subject) {
    final mt = subject['mt_submission'] as Map<String, dynamic>?;
    if (mt == null) return false;
    return mt['has_submission'] == true;
  }

  Widget _buildMtInfo(BuildContext context, Map<String, dynamic> mt, bool isDark, AppLocalizations l) {
    final hasSubmission = mt['has_submission'] == true;
    final isOverdue = mt['is_overdue'] == true;
    final gradeLocked = mt['grade_locked'] == true;
    final grade = mt['grade'];
    final deadline = mt['deadline']?.toString() ?? '';
    final fileName = mt['file_name']?.toString();
    final remaining = mt['remaining_attempts'] ?? 0;

    Color statusColor;
    String statusText;
    IconData statusIcon;

    if (gradeLocked) {
      statusColor = AppTheme.successColor;
      statusText = '${l.get("mt_graded")}: $grade';
      statusIcon = Icons.check_circle;
    } else if (hasSubmission && grade != null) {
      statusColor = AppTheme.warningColor;
      statusText = '${l.get("mt_graded")}: $grade (${l.get("mt_remaining")}: $remaining)';
      statusIcon = Icons.warning;
    } else if (hasSubmission) {
      statusColor = AppTheme.accentColor;
      statusText = l.get('mt_uploaded');
      statusIcon = Icons.cloud_done;
    } else if (isOverdue) {
      statusColor = AppTheme.errorColor;
      statusText = l.get('mt_overdue');
      statusIcon = Icons.error;
    } else {
      statusColor = AppTheme.textSecondary;
      statusText = l.get('mt_not_uploaded');
      statusIcon = Icons.cloud_upload_outlined;
    }

    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: statusColor.withAlpha(isDark ? 30 : 20),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: statusColor.withAlpha(60)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(statusIcon, size: 16, color: statusColor),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  statusText,
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: statusColor),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            '${l.get("mt_deadline")}: $deadline ${mt['deadline_time'] ?? ''}',
            style: TextStyle(fontSize: 11, color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
          ),
          if (fileName != null)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text(
                fileName,
                style: TextStyle(fontSize: 11, color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _uploadMT(BuildContext context, Map<String, dynamic> subject) async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar'],
        withData: true,
      );

      if (result == null || result.files.isEmpty) return;

      final file = result.files.first;
      if (file.bytes == null) return;

      final subjectId = subject['subject_id'];
      setState(() { _isUploading = true; _uploadingSubjectId = subjectId is int ? subjectId : -1; });

      await ApiService().uploadFile(
        '${ApiConfig.studentSubjects}/$subjectId/mt-upload',
        file.bytes!,
        file.name,
      );

      if (mounted) {
        setState(() => _isUploading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(AppLocalizations.of(context).get('upload_success')),
            backgroundColor: AppTheme.successColor,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isUploading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString()),
            backgroundColor: AppTheme.errorColor,
          ),
        );
      }
    }
  }

}

class _JnGradesPage extends StatefulWidget {
  final int subjectId;
  final String subjectName;
  final String fullLabel;

  const _JnGradesPage({
    required this.subjectId,
    required this.subjectName,
    required this.fullLabel,
  });

  @override
  State<_JnGradesPage> createState() => _JnGradesPageState();
}

class _JnGradesPageState extends State<_JnGradesPage> {
  bool _isLoading = true;
  String? _error;
  // All unique sorted dates from schedule
  List<String> _allDates = [];
  // Grade map: dateKey -> grade value (int or 'NB')
  Map<String, dynamic> _amaliyByDate = {};
  Map<String, dynamic> _maruzaByDate = {};

  @override
  void initState() {
    super.initState();
    _loadGrades();
  }

  Future<void> _loadGrades() async {
    try {
      final response = await StudentDataCache().getOrFetch(
        key: '${StudentDataCache.kSubjectGradesPrefix}${widget.subjectId}',
        fetcher: () =>
            StudentService(ApiService()).getSubjectGrades(widget.subjectId),
      );

      List<dynamic> grades = [];
      List<dynamic> scheduleDates = [];
      final data = response?['data'];
      if (data is Map<String, dynamic>) {
        grades = (data['grades'] as List<dynamic>?) ?? [];
        scheduleDates = (data['schedule_dates'] as List<dynamic>?) ?? [];
      } else if (data is List) {
        grades = data;
      }

      final amaliyRaw = <Map<String, dynamic>>[];
      final maruzaRaw = <Map<String, dynamic>>[];

      for (final g in grades) {
        if (g is! Map<String, dynamic>) continue;
        final typeCode = g['training_type_code'];
        final typeName = g['training_type_name']?.toString() ?? '';

        if (typeCode == 11 || typeName.contains("Ma'ruza") || typeName.contains('Maruza')) {
          maruzaRaw.add(g);
        } else if (typeCode != 99 && typeCode != 100 && typeCode != 101 && typeCode != 102 && typeCode != 103) {
          amaliyRaw.add(g);
        }
      }

      // Collect all dates from schedule
      final amaliyDates = <String>{};
      final maruzaDates = <String>{};
      for (final s in scheduleDates) {
        if (s is! Map<String, dynamic>) continue;
        final dateStr = s['lesson_date']?.toString() ?? '';
        if (dateStr.isEmpty) continue;
        final dateKey = dateStr.substring(0, 10);
        final typeCode = s['training_type_code'];
        final typeName = s['training_type_name']?.toString() ?? '';
        if (typeCode == 11 || typeName.contains("Ma'ruza") || typeName.contains('Maruza')) {
          maruzaDates.add(dateKey);
        } else if (typeCode != 99 && typeCode != 100 && typeCode != 101 && typeCode != 102 && typeCode != 103) {
          amaliyDates.add(dateKey);
        }
      }

      // Also add dates from grades that might not be in schedule
      for (final g in amaliyRaw) {
        final d = g['lesson_date']?.toString() ?? '';
        if (d.length >= 10) amaliyDates.add(d.substring(0, 10));
      }
      for (final g in maruzaRaw) {
        final d = g['lesson_date']?.toString() ?? '';
        if (d.length >= 10) maruzaDates.add(d.substring(0, 10));
      }

      final allDates = <String>{...amaliyDates, ...maruzaDates};
      final sortedDates = allDates.toList()..sort();

      if (mounted) {
        setState(() {
          _allDates = sortedDates;
          _amaliyByDate = _computeDailyMap(amaliyRaw, amaliyDates);
          _maruzaByDate = _computeDailyMap(maruzaRaw, maruzaDates);
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  Map<String, dynamic> _computeDailyMap(List<Map<String, dynamic>> grades, Set<String> dates) {
    final byDate = <String, List<num>>{};
    final absentDates = <String>{};

    for (final g in grades) {
      final dateRaw = g['lesson_date']?.toString() ?? '';
      if (dateRaw.length < 10) continue;
      final dateKey = dateRaw.substring(0, 10);

      final retake = g['retake_grade'];
      final grade = g['grade'];
      final reason = g['reason']?.toString();
      final status = g['status']?.toString();

      if (status == 'pending' && reason == 'low_grade' && grade is num) {
        byDate.putIfAbsent(dateKey, () => []).add(grade);
      } else if (status == 'pending') {
        continue;
      } else if (retake != null && retake is num && retake > 0) {
        byDate.putIfAbsent(dateKey, () => []).add(retake);
      } else if (reason == 'absent' && (grade == null || grade == 0)) {
        absentDates.add(dateKey);
        byDate.putIfAbsent(dateKey, () => []);
      } else if (grade != null && grade is num) {
        byDate.putIfAbsent(dateKey, () => []).add(grade);
      }
    }

    final result = <String, dynamic>{};
    for (final dateKey in dates) {
      if (byDate.containsKey(dateKey)) {
        final vals = byDate[dateKey]!;
        if (vals.isNotEmpty) {
          result[dateKey] = (vals.reduce((a, b) => a + b) / vals.length).round();
        } else {
          result[dateKey] = 'NB';
        }
      }
      // dates without grades: leave absent from map (will show '-')
    }
    return result;
  }

  // ── Clinic-calm palette ──────────────────────────────
  static const _calmInk = Color(0xFF0F172A);
  static const _calmMuted = Color(0xFF64748B);
  static const _calmFaint = Color(0xFF94A3B8);
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

  Widget _calmCard({required Widget child}) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: _surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _divider, width: 1),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0F172A).withOpacity(0.14),
            blurRadius: 5,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: child,
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final statusBarH = MediaQuery.of(context).padding.top;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : Colors.white,
      body: Column(
        children: [
          Container(
            padding: EdgeInsets.fromLTRB(14, statusBarH + 10, 14, 12),
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
                    color: isDark
                        ? Colors.white.withOpacity(0.06)
                        : const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: IconButton(
                    padding: EdgeInsets.zero,
                    icon: Icon(Icons.arrow_back_rounded, color: _ink, size: 20),
                    onPressed: () => Navigator.pop(context),
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        widget.fullLabel.toUpperCase(),
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 0.5,
                          color: _muted,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        widget.subjectName,
                        style: TextStyle(
                            fontSize: 15, fontWeight: FontWeight.w700, color: _ink),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(_error!, style: TextStyle(color: _muted)),
                            const SizedBox(height: 16),
                            ElevatedButton(
                              onPressed: _loadGrades,
                              child: const Text('Qayta yuklash'),
                            ),
                          ],
                        ),
                      )
                    : _allDates.isEmpty
                        ? Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(Icons.school_outlined, size: 48, color: _muted),
                                const SizedBox(height: 12),
                                Text('Ma\'lumot topilmadi', style: TextStyle(color: _muted)),
                              ],
                            ),
                          )
                        : _buildVerticalTable(),
          ),
        ],
      ),
    );
  }

  Widget _buildVerticalTable() {
    final hasAmaliy = _amaliyByDate.isNotEmpty;
    final hasMaruza = _maruzaByDate.isNotEmpty;

    final amaliyDates = _allDates
        .where((d) => _amaliyByDate.containsKey(d) || !_maruzaByDate.containsKey(d))
        .toList()
      ..removeWhere((d) => _maruzaByDate.containsKey(d) && !_amaliyByDate.containsKey(d));
    final maruzaDates = _allDates
        .where((d) => _maruzaByDate.containsKey(d) || !_amaliyByDate.containsKey(d))
        .toList()
      ..removeWhere((d) => _amaliyByDate.containsKey(d) && !_maruzaByDate.containsKey(d));

    return RefreshIndicator(
      onRefresh: _loadGrades,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (hasAmaliy) ...[
              _buildSectionCard(
                title: 'Amaliy mashg\'ulotlar',
                icon: Icons.assignment_turned_in_rounded,
                hueColor: const Color(0xFF15803D),
                gradesByDate: _amaliyByDate,
                dates: amaliyDates,
              ),
              const SizedBox(height: 12),
            ],
            if (hasMaruza) ...[
              _buildSectionCard(
                title: 'Ma\'ruzalar',
                icon: Icons.menu_book_rounded,
                hueColor: const Color(0xFF1D4ED8),
                gradesByDate: _maruzaByDate,
                dates: maruzaDates,
              ),
            ],
            if (!hasAmaliy && !hasMaruza)
              _buildSectionCard(
                title: 'Baholar',
                icon: Icons.school_outlined,
                hueColor: const Color(0xFF6D28D9),
                gradesByDate: const {},
                dates: _allDates,
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionCard({
    required String title,
    required IconData icon,
    required Color hueColor,
    required Map<String, dynamic> gradesByDate,
    required List<String> dates,
  }) {
    return _calmCard(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: hueColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(icon, color: hueColor, size: 20),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    title,
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: _ink),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: hueColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(7),
                  ),
                  child: Text(
                    '${dates.length}',
                    style: TextStyle(
                        fontSize: 11, fontWeight: FontWeight.w800, color: hueColor),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            if (dates.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text('Ma\'lumot yo\'q', style: TextStyle(fontSize: 13, color: _muted)),
              )
            else
              ...dates.asMap().entries.map((e) {
                final isLast = e.key == dates.length - 1;
                return _buildGradeRow(
                  dateKey: e.value,
                  value: gradesByDate[e.value],
                  showDivider: !isLast,
                );
              }),
          ],
        ),
      ),
    );
  }

  Widget _buildGradeRow({
    required String dateKey,
    required dynamic value,
    required bool showDivider,
  }) {
    String text;
    Color valueColor;
    Color chipBg;

    if (value == 'NB') {
      text = 'NB';
      valueColor = Colors.white;
      chipBg = const Color(0xFFBE123C);
    } else if (value is int && value >= 70) {
      text = value.toString();
      valueColor = Colors.white;
      chipBg = const Color(0xFF15803D);
    } else if (value is int && value > 0) {
      text = value.toString();
      valueColor = Colors.white;
      chipBg = const Color(0xFFB45309);
    } else {
      text = '—';
      valueColor = _calmFaint;
      chipBg = _divider;
    }

    return Container(
      decoration: BoxDecoration(
        border: showDivider ? Border(bottom: BorderSide(color: _divider)) : null,
      ),
      padding: const EdgeInsets.symmetric(vertical: 11),
      child: Row(
        children: [
          Icon(Icons.event_outlined, size: 16, color: _calmFaint),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              _formatDateLong(dateKey),
              style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600, color: _ink),
            ),
          ),
          Container(
            constraints: const BoxConstraints(minWidth: 46),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: chipBg,
              borderRadius: BorderRadius.circular(9),
            ),
            child: Text(
              text,
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w900, color: valueColor),
            ),
          ),
        ],
      ),
    );
  }

  String _formatDateLong(String dateKey) {
    try {
      final date = DateTime.parse(dateKey);
      const months = ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyn', 'Iyl', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'];
      final m = months[date.month - 1];
      return '${date.day.toString().padLeft(2, '0')} $m ${date.year}';
    } catch (_) {
      return dateKey;
    }
  }
}
