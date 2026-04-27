import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:file_picker/file_picker.dart';
import '../../config/theme.dart';
import '../../config/api_config.dart';
import '../../providers/student_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import '../../l10n/app_localizations.dart';
import '../../widgets/loading_widget.dart';
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

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadSubjects();
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

  static const List<Color> _gradeBoxColors = [
    Color(0xFF43A047), // JN
    Color(0xFF2E7D32), // MT
    Color(0xFF1565C0), // ON
    Color(0xFF7C4DFF), // OSKI
    Color(0xFFE91E63), // TEST
    Color(0xFF00897B), // YN
  ];

  bool _isSubjectCompleted(Map<String, dynamic> subject) {
    final grades = subject['grades'] as Map<String, dynamic>? ?? {};
    return grades['oski'] != null || grades['test'] != null;
  }

  double _getSubjectTotal(Map<String, dynamic> subject) {
    final grades = subject['grades'] as Map<String, dynamic>? ?? {};
    final vals = <num>[];
    for (final k in ['jn', 'mt', 'on', 'oski', 'test']) {
      final v = grades[k];
      if (v != null && v is num) vals.add(v);
    }
    return vals.isNotEmpty ? vals.reduce((a, b) => a + b) / vals.length : 0;
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

  Map<String, dynamic>? _getBestSubject(List subjects) {
    Map<String, dynamic>? best;
    double bestGrade = 0;
    for (final s in subjects) {
      if (s is! Map<String, dynamic>) continue;
      final t = _getSubjectTotal(s);
      if (t > bestGrade) { bestGrade = t; best = s; }
    }
    return best;
  }

  List<Map<String, dynamic>> _filterSubjects(List subjects) {
    final all = subjects.whereType<Map<String, dynamic>>().toList();
    if (_selectedFilter == 1) return all.where((s) => _isSubjectCompleted(s)).toList();
    if (_selectedFilter == 2) return all.where((s) => !_isSubjectCompleted(s)).toList();
    return all;
  }

  Widget _buildGlassCard({required Widget child, required bool isDark, double borderRadius = 20, Color? cardColor}) {
    final cc = cardColor ?? const Color(0xFF0D47A1);
    final surface = isDark ? Colors.white.withOpacity(0.10) : Colors.white.withOpacity(0.7);
    final border = isDark ? Colors.white.withOpacity(0.12) : Colors.white.withOpacity(0.9);
    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
        child: Container(
          decoration: BoxDecoration(
            color: surface,
            border: Border.all(color: border),
            borderRadius: BorderRadius.circular(borderRadius),
            boxShadow: [
              BoxShadow(
                color: isDark ? Colors.black.withOpacity(0.3) : const Color(0xFF1A1340).withOpacity(0.06),
                blurRadius: 24,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Stack(
            children: [
              Positioned(
                top: -10,
                right: -10,
                child: ImageFiltered(
                  imageFilter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
                  child: Container(
                    width: 100,
                    height: 100,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: RadialGradient(
                        colors: [cc.withOpacity(isDark ? 0.35 : 0.28), cc.withOpacity(0)],
                        stops: const [0.0, 0.7],
                      ),
                    ),
                  ),
                ),
              ),
              child,
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final statusBarH = MediaQuery.of(context).padding.top;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0B1020) : const Color(0xFFFEF7F0),
      body: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: const Alignment(-1.0, -1.0),
                  radius: 1.4,
                  colors: isDark
                      ? const [Color(0xFF6366F1), Color(0xFFA855F7), Color(0xFFEC4899), Color(0xFF0B1020)]
                      : const [Color(0xFFC7D2FE), Color(0xFFFBCFE8), Color(0xFFFED7AA), Color(0xFFFEF7F0)],
                  stops: const [0.0, 0.35, 0.65, 1.0],
                ),
              ),
            ),
          ),
          Positioned(
            top: 180, right: -80,
            child: _buildBlob(isDark ? const Color(0xFFF472B6) : const Color(0xFFF9A8D4)),
          ),
          Positioned(
            top: 480, left: -80,
            child: _buildBlob(isDark ? const Color(0xFF60A5FA) : const Color(0xFFA5B4FC)),
          ),
          Consumer<StudentProvider>(
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
            final bestSubject = _getBestSubject(subjects);
            final filtered = _filterSubjects(subjects);
            final semesterName = provider.profile?['semester_name']?.toString() ?? '';

            return RefreshIndicator(
              onRefresh: () => provider.loadSubjects(),
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: Column(
                  children: [
                    // Top bar
                    Container(
                      padding: EdgeInsets.only(top: statusBarH, left: 16, right: 4),
                      height: statusBarH + 64,
                      decoration: const BoxDecoration(
                        color: Color(0xFF0D47A1),
                        borderRadius: BorderRadius.only(
                          bottomLeft: Radius.circular(18),
                          bottomRight: Radius.circular(18),
                        ),
                      ),
                      child: Row(
                        children: [
                          GestureDetector(
                            onTap: () => StudentHomeScreen.switchToHome(context),
                            child: const Icon(Icons.account_balance, color: Colors.white, size: 24),
                          ),
                          const Spacer(),
                          Text(l.grades, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: Colors.white)),
                          const Spacer(),
                          IconButton(
                            icon: const Icon(Icons.notifications_outlined, color: Colors.white, size: 22),
                            onPressed: () {},
                          ),
                          IconButton(
                            icon: const Icon(Icons.settings_outlined, color: Colors.white, size: 22),
                            onPressed: () {},
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    // Summary gradient card
                    _buildSummaryCard(semesterAvg, subjects.length, completed, waiting, semesterName, isDark),
                    const SizedBox(height: 12),
                    // Best subject
                    if (bestSubject != null) _buildBestSubjectCard(bestSubject, isDark),
                    const SizedBox(height: 16),
                    // Filter tabs
                    _buildFilterTabs(isDark, completed, waiting, subjects.length),
                    const SizedBox(height: 12),
                    // Subject cards
                    ...filtered.asMap().entries.map((e) => Padding(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                      child: _buildSubjectCard(context, e.value, e.key, isDark, l),
                    )),
                    const SizedBox(height: 100),
                  ],
                ),
              ),
            );
          },
        ),
        ],
      ),
    );
  }

  Widget _buildSummaryCard(double avg, int total, int completed, int waiting, String semester, bool isDark) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF7C4DFF), Color(0xFFAB47BC), Color(0xFFFF7043)],
          ),
          borderRadius: BorderRadius.circular(24),
          boxShadow: [
            BoxShadow(color: const Color(0xFF7C4DFF).withOpacity(0.3), blurRadius: 16, offset: const Offset(0, 6)),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              semester.isNotEmpty ? '${semester.toUpperCase()} · O\'RTACHA' : 'SEMESTR · O\'RTACHA',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.white.withOpacity(0.8), letterSpacing: 0.5),
            ),
            const SizedBox(height: 8),
            Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(avg.toStringAsFixed(1), style: const TextStyle(fontSize: 48, fontWeight: FontWeight.w900, color: Colors.white, height: 1)),
                const Padding(
                  padding: EdgeInsets.only(bottom: 8),
                  child: Text(' / 100', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w500, color: Colors.white70)),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                _buildStatBox('$total', 'Fanlar', const Color(0xFF43A047)),
                const SizedBox(width: 10),
                _buildStatBox('$completed', 'Topshirilgan', const Color(0xFF1E88E5)),
                const SizedBox(width: 10),
                _buildStatBox('$waiting', 'Kutilmoqda', const Color(0xFFFF7043)),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatBox(String value, String label, Color dotColor) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.15),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Column(
          children: [
            Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Colors.white)),
            const SizedBox(height: 2),
            Text(label, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w500, color: Colors.white.withOpacity(0.8)),
              overflow: TextOverflow.ellipsis),
          ],
        ),
      ),
    );
  }

  Widget _buildBestSubjectCard(Map<String, dynamic> subject, bool isDark) {
    final name = subject['subject_name']?.toString() ?? '';
    final grade = _getSubjectTotal(subject).round();
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: _buildGlassCard(
        isDark: isDark,
        cardColor: const Color(0xFFFF9800),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                width: 42, height: 42,
                decoration: BoxDecoration(
                  color: const Color(0xFFFFC107).withOpacity(0.15),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.star_rounded, color: Color(0xFFFFC107), size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('ENG YUQORI BAHO', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700,
                      color: isDark ? Colors.white54 : Colors.black45, letterSpacing: 0.5)),
                    const SizedBox(height: 2),
                    Text(name, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700,
                      color: isDark ? Colors.white : AppTheme.textPrimary), maxLines: 1, overflow: TextOverflow.ellipsis),
                  ],
                ),
              ),
              Text('$grade', style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w900, color: Color(0xFF43A047))),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildFilterTabs(bool isDark, int completed, int waiting, int total) {
    final labels = ['Hammasi', 'Topshirilgan', 'Kutilmoqda'];
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: _buildGlassCard(
        isDark: isDark,
        borderRadius: 16,
        cardColor: const Color(0xFF1565C0),
        child: Padding(
          padding: const EdgeInsets.all(4),
          child: Row(
            children: List.generate(3, (i) {
              final isActive = _selectedFilter == i;
              return Expanded(
                child: GestureDetector(
                  onTap: () => setState(() => _selectedFilter = i),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    decoration: BoxDecoration(
                      color: isActive ? const Color(0xFF43A047) : Colors.transparent,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      labels[i],
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: isActive ? FontWeight.w700 : FontWeight.w500,
                        color: isActive ? Colors.white : (isDark ? Colors.white70 : AppTheme.textSecondary),
                      ),
                    ),
                  ),
                ),
              );
            }),
          ),
        ),
      ),
    );
  }

  Widget _buildSubjectCard(BuildContext context, Map<String, dynamic> subject, int index, bool isDark, AppLocalizations l) {
    final grades = subject['grades'] as Map<String, dynamic>? ?? {};
    final name = subject['subject_name']?.toString() ?? '';
    final total = _getSubjectTotal(subject).round();
    final isCompleted = _isSubjectCompleted(subject);
    final attendance = _getAttendancePercent(subject);

    final gradeKeys = ['jn', 'mt', 'on', 'oski', 'test', 'total'];
    final gradeLabels = ['JN', 'MT', 'ON', 'OSKI', 'TEST', 'YN'];

    return _buildGlassCard(
      isDark: isDark,
      cardColor: _cardTextColors[index % _cardTextColors.length],
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header: name + total
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(name, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700,
                    color: isDark ? Colors.white : AppTheme.textPrimary), maxLines: 2, overflow: TextOverflow.ellipsis),
                ),
                const SizedBox(width: 12),
                Column(
                  children: [
                    Text('$total', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w900,
                      color: total >= 70 ? const Color(0xFF43A047) : total > 0 ? const Color(0xFFFF9800) : (isDark ? Colors.white38 : Colors.black26))),
                    Text('JAMI', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600,
                      color: isDark ? Colors.white38 : Colors.black38)),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 8),
            // Status badge + attendance
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: isCompleted ? const Color(0xFF43A047) : const Color(0xFFFF9800),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(isCompleted ? Icons.check : Icons.schedule, size: 12, color: Colors.white),
                      const SizedBox(width: 4),
                      Text(isCompleted ? 'Topshirilgan' : 'Kutilmoqda',
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.white)),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Text('Davomat ${attendance.toStringAsFixed(0)}%',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500,
                    color: isDark ? Colors.white54 : Colors.black45)),
              ],
            ),
            const SizedBox(height: 14),
            // Grade boxes row
            Row(
              children: List.generate(6, (i) {
                final key = gradeKeys[i];
                final value = key == 'total' ? null : grades[key];
                final hasValue = value != null;
                final color = _gradeBoxColors[i];
                return Expanded(
                  child: GestureDetector(
                    onTap: () => _onGradeCardTap(context, subject, key,
                      gradeLabels[i], _gradeFullLabels[i], value, isDark, l),
                    child: Container(
                      margin: EdgeInsets.only(right: i < 5 ? 6 : 0),
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      decoration: BoxDecoration(
                        color: hasValue
                            ? (isDark ? color.withOpacity(0.25) : color)
                            : (isDark ? Colors.white.withOpacity(0.05) : Colors.black.withOpacity(0.04)),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Column(
                        children: [
                          Text(gradeLabels[i], style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600,
                            color: hasValue ? (isDark ? color : Colors.white) : (isDark ? Colors.white30 : Colors.black26))),
                          const SizedBox(height: 2),
                          Text(hasValue ? value.toString() : '—',
                            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800,
                              color: hasValue ? (isDark ? color : Colors.white) : (isDark ? Colors.white30 : Colors.black26))),
                        ],
                      ),
                    ),
                  ),
                );
              }),
            ),
            // MT upload section
            if (subject['mt_submission'] != null) ...[
              const SizedBox(height: 14),
              _buildMtUploadSection(context, subject, isDark, l),
            ],
          ],
        ),
      ),
    );
  }

  static const List<String> _gradeFullLabels = [
    'Joriy nazorat', 'Mustaqil ta\'lim', 'Oraliq nazorat', 'OSKI', 'Test', 'Yakuniy',
  ];

  Widget _buildMtUploadSection(BuildContext context, Map<String, dynamic> subject, bool isDark, AppLocalizations l) {
    final mt = subject['mt_submission'] as Map<String, dynamic>;
    final hasSubmission = mt['has_submission'] == true;
    final canSubmit = mt['can_submit'] == true;
    final subjectId = subject['subject_id'];
    final isThisUploading = _isUploading && _uploadingSubjectId == subjectId;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: isDark ? Colors.white.withOpacity(0.04) : Colors.black.withOpacity(0.03),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Container(
            width: 36, height: 36,
            decoration: BoxDecoration(
              color: const Color(0xFF43A047).withOpacity(0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(hasSubmission ? Icons.cloud_done_rounded : Icons.cloud_upload_rounded,
              color: const Color(0xFF43A047), size: 20),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Mustaqil ta\'lim yuklash', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600,
                  color: isDark ? Colors.white : AppTheme.textPrimary)),
                Text(
                  hasSubmission ? 'Yuklangan · qayta yuklash mumkin' : 'Yuklanmagan',
                  style: TextStyle(fontSize: 11, color: isDark ? Colors.white38 : Colors.black38),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          GestureDetector(
            onTap: canSubmit && !isThisUploading ? () => _uploadMT(context, subject) : null,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                color: canSubmit ? const Color(0xFF43A047) : (isDark ? Colors.white12 : Colors.black12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: isThisUploading
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text(
                      hasSubmission ? 'Yuklash' : 'Yuklash',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700,
                        color: canSubmit ? Colors.white : (isDark ? Colors.white30 : Colors.black26)),
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
      MaterialPageRoute(
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

  Widget _buildBlob(Color color) {
    return ImageFiltered(
      imageFilter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
      child: Container(
        width: 240,
        height: 240,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [color, color.withOpacity(0)],
            stops: const [0.0, 0.7],
          ),
        ),
      ),
    );
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
      final service = StudentService(ApiService());
      final response = await service.getSubjectGrades(widget.subjectId);

      List<dynamic> grades = [];
      List<dynamic> scheduleDates = [];
      final data = response['data'];
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

  String _formatDateShort(String dateKey) {
    try {
      final date = DateTime.parse(dateKey);
      return '${date.day.toString().padLeft(2, '0')}.${date.month.toString().padLeft(2, '0')}.${date.year}';
    } catch (_) {
      return dateKey;
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkBackground : AppTheme.backgroundColor;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final secondaryText = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        title: Text(
          widget.subjectName,
          style: const TextStyle(fontSize: 15),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        centerTitle: true,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!, style: TextStyle(color: secondaryText)),
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
                          Icon(Icons.school_outlined, size: 48, color: secondaryText),
                          const SizedBox(height: 12),
                          Text('Ma\'lumot topilmadi', style: TextStyle(color: secondaryText)),
                        ],
                      ),
                    )
                  : _buildHorizontalTable(isDark, textColor, secondaryText),
    );
  }

  Widget _buildHorizontalTable(bool isDark, Color textColor, Color secondaryText) {
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);
    final headerBg = isDark ? const Color(0xFF1A1A2E) : const Color(0xFFF5F7FA);
    final cellBg = isDark ? AppTheme.darkCard : Colors.white;

    // Separate dates for amaliy and maruza
    final amaliyDates = _allDates.where((d) => _amaliyByDate.containsKey(d) || _amaliyByDate.keys.isEmpty).toList();
    final maruzaDates = _allDates.where((d) => _maruzaByDate.containsKey(d) || _maruzaByDate.keys.isEmpty).toList();

    // Use all dates for the table, showing both rows
    final hasAmaliy = _amaliyByDate.isNotEmpty;
    final hasMaruza = _maruzaByDate.isNotEmpty;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (hasAmaliy) ...[
            Text(
              'Amaliy mashg\'ulotlar',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: textColor),
            ),
            const SizedBox(height: 8),
            _buildScrollableGrid(
              _amaliyByDate,
              _allDates.where((d) =>
                _amaliyByDate.containsKey(d) ||
                !_maruzaByDate.containsKey(d)
              ).toList()..removeWhere((d) => _maruzaByDate.containsKey(d) && !_amaliyByDate.containsKey(d)),
              borderColor, headerBg, cellBg, textColor, secondaryText, isDark,
            ),
          ],
          if (hasMaruza) ...[
            const SizedBox(height: 20),
            Text(
              'Ma\'ruzalar',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: textColor),
            ),
            const SizedBox(height: 8),
            _buildScrollableGrid(
              _maruzaByDate,
              _allDates.where((d) =>
                _maruzaByDate.containsKey(d) ||
                !_amaliyByDate.containsKey(d)
              ).toList()..removeWhere((d) => _amaliyByDate.containsKey(d) && !_maruzaByDate.containsKey(d)),
              borderColor, headerBg, cellBg, textColor, secondaryText, isDark,
            ),
          ],
          if (!hasAmaliy && !hasMaruza) ...[
            _buildScrollableGrid(
              {},
              _allDates,
              borderColor, headerBg, cellBg, textColor, secondaryText, isDark,
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildScrollableGrid(
    Map<String, dynamic> gradesByDate,
    List<String> dates,
    Color borderColor,
    Color headerBg,
    Color cellBg,
    Color textColor,
    Color secondaryText,
    bool isDark,
  ) {
    if (dates.isEmpty) return const SizedBox.shrink();
    const double colWidth = 56;

    return Container(
      decoration: BoxDecoration(
        border: Border.all(color: borderColor),
        borderRadius: BorderRadius.circular(10),
      ),
      clipBehavior: Clip.antiAlias,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Column(
          children: [
            // Date header row (vertical text)
            Row(
              children: dates.map((dateKey) {
                return Container(
                  width: colWidth,
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  decoration: BoxDecoration(
                    color: headerBg,
                    border: Border(
                      right: BorderSide(color: borderColor, width: 0.5),
                    ),
                  ),
                  child: RotatedBox(
                    quarterTurns: 3,
                    child: Text(
                      _formatDateShort(dateKey),
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: textColor,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                );
              }).toList(),
            ),
            // Grade row
            Container(
              decoration: BoxDecoration(
                border: Border(top: BorderSide(color: borderColor)),
              ),
              child: Row(
                children: dates.map((dateKey) {
                  final val = gradesByDate[dateKey];
                  String text;
                  Color color;

                  if (val == null) {
                    text = '-';
                    color = secondaryText;
                  } else if (val == 'NB') {
                    text = 'NB';
                    color = AppTheme.errorColor;
                  } else if (val is int) {
                    text = val.toString();
                    color = val >= 70
                        ? const Color(0xFF43A047)
                        : val > 0
                            ? const Color(0xFF1E88E5)
                            : secondaryText;
                  } else {
                    text = '-';
                    color = secondaryText;
                  }

                  return Container(
                    width: colWidth,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    decoration: BoxDecoration(
                      color: cellBg,
                      border: Border(
                        right: BorderSide(color: borderColor, width: 0.5),
                      ),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      text,
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: color,
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
