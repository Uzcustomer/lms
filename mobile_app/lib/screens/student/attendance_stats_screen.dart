import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/student_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';

class AttendanceStatsScreen extends StatefulWidget {
  const AttendanceStatsScreen({super.key});

  @override
  State<AttendanceStatsScreen> createState() => _AttendanceStatsScreenState();
}

class _AttendanceStatsScreenState extends State<AttendanceStatsScreen> {
  List<dynamic> _subjects = [];
  bool _loadingSubjects = true;
  int? _selectedSubjectId;
  String _selectedSubjectName = '';

  List<dynamic> _grades = [];
  List<dynamic> _scheduleDates = [];
  bool _loadingGrades = false;

  @override
  void initState() {
    super.initState();
    _loadSubjects();
  }

  Future<void> _loadSubjects() async {
    final provider = context.read<StudentProvider>();
    if (provider.subjects != null && provider.subjects!.isNotEmpty) {
      setState(() {
        _subjects = provider.subjects!;
        _loadingSubjects = false;
      });
      return;
    }
    try {
      await provider.loadSubjects();
      if (mounted) {
        setState(() {
          _subjects = provider.subjects ?? [];
          _loadingSubjects = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loadingSubjects = false);
    }
  }

  Future<void> _loadGrades(int subjectId) async {
    setState(() => _loadingGrades = true);
    try {
      final api = ApiService();
      final service = StudentService(api);
      final res = await service.getSubjectGrades(subjectId);
      if (mounted) {
        final data = res['data'] as Map<String, dynamic>? ?? {};
        setState(() {
          _grades = (data['grades'] as List<dynamic>? ?? []);
          _scheduleDates = (data['schedule_dates'] as List<dynamic>? ?? []);
          _loadingGrades = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loadingGrades = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? AppTheme.darkBackground : const Color(0xFFF0F4F8);
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(title: const Text('Davomat statistikasi')),
      body: _loadingSubjects
          ? const Center(child: CircularProgressIndicator())
          : _subjects.isEmpty
              ? Center(
                  child: Text('Fanlar topilmadi',
                      style: TextStyle(color: sub, fontSize: 15)))
              : Column(
                  children: [
                    _buildSubjectSelector(isDark, txt, sub),
                    if (_selectedSubjectId != null)
                      Expanded(
                        child: _loadingGrades
                            ? const Center(child: CircularProgressIndicator())
                            : _buildGradeTable(isDark, txt, sub),
                      ),
                    if (_selectedSubjectId == null)
                      Expanded(
                        child: Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.touch_app_outlined,
                                  size: 48,
                                  color: sub.withOpacity(0.5)),
                              const SizedBox(height: 12),
                              Text('Fanni tanlang',
                                  style: TextStyle(
                                      color: sub,
                                      fontSize: 15,
                                      fontWeight: FontWeight.w500)),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
    );
  }

  Widget _buildSubjectSelector(bool isDark, Color txt, Color sub) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: _subjects.map<Widget>((s) {
            final id = s['subject_id'] as int? ?? 0;
            final name = s['subject_name']?.toString() ?? '';
            final isSelected = _selectedSubjectId == id;
            final davPercent = _toDouble(s['dav_percent']);
            final hasAbsence = davPercent > 0;

            return Padding(
              padding: const EdgeInsets.only(right: 8),
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () {
                    setState(() {
                      _selectedSubjectId = id;
                      _selectedSubjectName = name;
                    });
                    _loadGrades(id);
                  },
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 14, vertical: 10),
                    decoration: BoxDecoration(
                      gradient: isSelected
                          ? const LinearGradient(
                              colors: [Color(0xFF4A6CF7), Color(0xFF6C63FF)])
                          : null,
                      color: isSelected
                          ? null
                          : isDark
                              ? AppTheme.darkCard
                              : Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: isSelected
                          ? null
                          : Border.all(
                              color: hasAbsence
                                  ? AppTheme.errorColor.withOpacity(0.3)
                                  : isDark
                                      ? Colors.white12
                                      : Colors.grey.shade300),
                      boxShadow: isSelected
                          ? [
                              BoxShadow(
                                color: const Color(0xFF4A6CF7).withOpacity(0.3),
                                blurRadius: 6,
                                offset: const Offset(0, 2),
                              )
                            ]
                          : null,
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          name.length > 25
                              ? '${name.substring(0, 25)}...'
                              : name,
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight:
                                isSelected ? FontWeight.w600 : FontWeight.w500,
                            color: isSelected ? Colors.white : txt,
                          ),
                        ),
                        if (hasAbsence) ...[
                          const SizedBox(width: 6),
                          Container(
                            width: 7,
                            height: 7,
                            decoration: const BoxDecoration(
                              color: AppTheme.errorColor,
                              shape: BoxShape.circle,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
            );
          }).toList(),
        ),
      ),
    );
  }

  Widget _buildGradeTable(bool isDark, Color txt, Color sub) {
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);
    final headerBg = isDark ? const Color(0xFF1A1A2E) : const Color(0xFFF5F7FA);
    final cellBg = isDark ? AppTheme.darkCard : Colors.white;

    final processedRows = _buildRows();

    if (processedRows.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.event_note_outlined, size: 48, color: sub.withOpacity(0.5)),
            const SizedBox(height: 12),
            Text('Ma\'lumot topilmadi',
                style: TextStyle(color: sub, fontSize: 14)),
          ],
        ),
      );
    }

    final absentCount =
        processedRows.where((r) => r['type'] == 'absent').length;
    final retakeCount =
        processedRows.where((r) => r['type'] == 'retake').length;
    final totalLessons = processedRows.length;
    final attendedCount = totalLessons - absentCount;

    return Column(
      children: [
        _buildStatsRow(
            totalLessons, attendedCount, absentCount, retakeCount, isDark, txt),
        Expanded(
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 20),
            itemCount: processedRows.length,
            itemBuilder: (_, i) => _buildRow(
                processedRows[i], i, borderColor, headerBg, cellBg, txt, sub, isDark),
          ),
        ),
      ],
    );
  }

  Widget _buildStatsRow(int total, int attended, int absent, int retake,
      bool isDark, Color txt) {
    final percent = total > 0 ? (attended / total * 100) : 100.0;
    final percentColor = percent >= 85
        ? AppTheme.successColor
        : percent >= 70
            ? AppTheme.warningColor
            : AppTheme.errorColor;

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 4, 14, 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [percentColor.withOpacity(0.1), percentColor.withOpacity(0.05)],
        ),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: percentColor.withOpacity(0.2)),
      ),
      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: percentColor.withOpacity(0.15),
            ),
            child: Center(
              child: Text(
                '${percent.round()}%',
                style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: percentColor),
              ),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_selectedSubjectName,
                    style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: txt),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis),
                const SizedBox(height: 4),
                Row(
                  children: [
                    _statChip('Jami', '$total', Colors.blueGrey),
                    const SizedBox(width: 8),
                    _statChip('Bor', '$attended', AppTheme.successColor),
                    const SizedBox(width: 8),
                    _statChip('NB', '$absent', AppTheme.errorColor),
                    if (retake > 0) ...[
                      const SizedBox(width: 8),
                      _statChip('Retake', '$retake', AppTheme.warningColor),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _statChip(String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text('$label: $value',
          style: TextStyle(
              fontSize: 10.5, fontWeight: FontWeight.w600, color: color)),
    );
  }

  List<Map<String, dynamic>> _buildRows() {
    final gradeMap = <String, List<Map<String, dynamic>>>{};

    for (final g in _grades) {
      final dateRaw = g['lesson_date']?.toString() ?? '';
      if (dateRaw.length < 10) continue;
      final dateKey = dateRaw.substring(0, 10);
      final ttCode = g['training_type_code'];
      if (ttCode == 99 || ttCode == 100 || ttCode == 101 || ttCode == 102) {
        continue;
      }
      gradeMap.putIfAbsent(dateKey, () => []).add(Map<String, dynamic>.from(g));
    }

    final dateSet = <String>{};
    for (final s in _scheduleDates) {
      final dateRaw = s['lesson_date']?.toString() ?? '';
      if (dateRaw.length < 10) continue;
      final ttCode = s['training_type_code'];
      if (ttCode == 99 || ttCode == 100 || ttCode == 101 || ttCode == 102) {
        continue;
      }
      dateSet.add(dateRaw.substring(0, 10));
    }
    for (final k in gradeMap.keys) {
      dateSet.add(k);
    }

    final sortedDates = dateSet.toList()..sort();
    final rows = <Map<String, dynamic>>[];

    for (final dateKey in sortedDates) {
      final dayGrades = gradeMap[dateKey];
      if (dayGrades == null || dayGrades.isEmpty) {
        rows.add({
          'date': dateKey,
          'type': 'no_data',
          'grade': null,
          'retake_grade': null,
          'training_type': '',
          'pair': '',
        });
        continue;
      }

      for (final g in dayGrades) {
        final reason = g['reason']?.toString();
        final status = g['status']?.toString();
        final grade = g['grade'];
        final retakeGrade = g['retake_grade'];

        String type;
        if (reason == 'absent' && (grade == null || grade == 0)) {
          if (retakeGrade != null && retakeGrade is num && retakeGrade > 0) {
            type = 'retake';
          } else {
            type = 'absent';
          }
        } else if (status == 'pending') {
          type = 'pending';
        } else {
          type = 'graded';
        }

        rows.add({
          'date': dateKey,
          'type': type,
          'grade': grade,
          'retake_grade': retakeGrade,
          'training_type': g['training_type_name']?.toString() ?? '',
          'pair': g['lesson_pair_name']?.toString() ?? '',
          'start_time': g['lesson_pair_start_time']?.toString() ?? '',
        });
      }
    }

    return rows;
  }

  Widget _buildRow(Map<String, dynamic> row, int index, Color borderColor,
      Color headerBg, Color cellBg, Color txt, Color sub, bool isDark) {
    final dateKey = row['date'] as String;
    final type = row['type'] as String;
    final grade = row['grade'];
    final retakeGrade = row['retake_grade'];
    final pair = row['pair'] as String;
    final startTime = row['start_time']?.toString() ?? '';
    final trainingType = row['training_type'] as String;

    String dateStr;
    try {
      final dt = DateTime.parse(dateKey);
      dateStr =
          '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')}';
    } catch (_) {
      dateStr = dateKey;
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      decoration: BoxDecoration(
        color: cellBg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: type == 'absent'
              ? AppTheme.errorColor.withOpacity(0.3)
              : type == 'retake'
                  ? AppTheme.warningColor.withOpacity(0.3)
                  : borderColor,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        child: Row(
          children: [
            SizedBox(
              width: 44,
              child: Column(
                children: [
                  Text(dateStr,
                      style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: txt)),
                  if (startTime.isNotEmpty)
                    Text(startTime,
                        style: TextStyle(fontSize: 10, color: sub)),
                ],
              ),
            ),
            Container(
              width: 1,
              height: 36,
              margin: const EdgeInsets.symmetric(horizontal: 10),
              color: borderColor,
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    trainingType.isNotEmpty ? trainingType : '—',
                    style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w500,
                        color: txt),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (pair.isNotEmpty)
                    Text('$pair-para',
                        style: TextStyle(fontSize: 10.5, color: sub)),
                ],
              ),
            ),
            const SizedBox(width: 8),
            _buildGradeCell(type, grade, retakeGrade, isDark),
          ],
        ),
      ),
    );
  }

  Widget _buildGradeCell(
      String type, dynamic grade, dynamic retakeGrade, bool isDark) {
    if (type == 'retake') {
      return SizedBox(
        width: 48,
        height: 48,
        child: CustomPaint(
          painter: _DiagonalCellPainter(
            nbText: 'NB',
            gradeText: retakeGrade is num
                ? (retakeGrade % 1 == 0
                    ? retakeGrade.toInt().toString()
                    : retakeGrade.toStringAsFixed(1))
                : retakeGrade?.toString() ?? '',
            isDark: isDark,
          ),
        ),
      );
    }

    if (type == 'absent') {
      return Container(
        width: 48,
        height: 36,
        decoration: BoxDecoration(
          color: AppTheme.errorColor.withOpacity(0.1),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppTheme.errorColor.withOpacity(0.3)),
        ),
        alignment: Alignment.center,
        child: const Text('NB',
            style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: AppTheme.errorColor)),
      );
    }

    if (type == 'pending') {
      return Container(
        width: 48,
        height: 36,
        decoration: BoxDecoration(
          color: Colors.grey.withOpacity(0.1),
          borderRadius: BorderRadius.circular(10),
        ),
        alignment: Alignment.center,
        child: Text('—',
            style: TextStyle(
                fontSize: 14,
                color: isDark
                    ? AppTheme.darkTextSecondary
                    : AppTheme.textSecondary)),
      );
    }

    if (type == 'no_data') {
      return Container(
        width: 48,
        height: 36,
        decoration: BoxDecoration(
          color: Colors.grey.withOpacity(0.05),
          borderRadius: BorderRadius.circular(10),
        ),
        alignment: Alignment.center,
        child: Text('-',
            style: TextStyle(
                fontSize: 14,
                color: isDark
                    ? AppTheme.darkTextSecondary
                    : AppTheme.textSecondary)),
      );
    }

    final gradeVal =
        grade is num ? grade.toDouble() : double.tryParse(grade?.toString() ?? '') ?? 0;
    final gradeColor = gradeVal >= 86
        ? AppTheme.successColor
        : gradeVal >= 71
            ? const Color(0xFF1E88E5)
            : gradeVal >= 56
                ? AppTheme.warningColor
                : gradeVal > 0
                    ? AppTheme.errorColor
                    : (isDark
                        ? AppTheme.darkTextSecondary
                        : AppTheme.textSecondary);

    final displayText = gradeVal % 1 == 0
        ? gradeVal.toInt().toString()
        : gradeVal.toStringAsFixed(1);

    return Container(
      width: 48,
      height: 36,
      decoration: BoxDecoration(
        color: gradeColor.withOpacity(0.1),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: gradeColor.withOpacity(0.3)),
      ),
      alignment: Alignment.center,
      child: Text(displayText,
          style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: gradeColor)),
    );
  }

  double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }
}

class _DiagonalCellPainter extends CustomPainter {
  final String nbText;
  final String gradeText;
  final bool isDark;

  _DiagonalCellPainter({
    required this.nbText,
    required this.gradeText,
    required this.isDark,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final rect = RRect.fromRectAndRadius(
        Rect.fromLTWH(0, 0, size.width, size.height),
        const Radius.circular(10));

    final bgPaint = Paint()
      ..color = AppTheme.warningColor.withOpacity(0.1);
    canvas.drawRRect(rect, bgPaint);

    final borderPaint = Paint()
      ..color = AppTheme.warningColor.withOpacity(0.3)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1;
    canvas.drawRRect(rect, borderPaint);

    canvas.save();
    canvas.clipRRect(rect);

    final linePaint = Paint()
      ..color = AppTheme.warningColor.withOpacity(0.4)
      ..strokeWidth = 1;
    canvas.drawLine(
        Offset(0, size.height), Offset(size.width, 0), linePaint);

    final nbPainter = TextPainter(
      text: TextSpan(
        text: nbText,
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w700,
          color: AppTheme.errorColor.withOpacity(0.7),
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    nbPainter.paint(
        canvas,
        Offset(
            size.width * 0.08, size.height * 0.55));

    final gradePainter = TextPainter(
      text: TextSpan(
        text: gradeText,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w800,
          color: AppTheme.successColor,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    gradePainter.paint(
        canvas,
        Offset(
            size.width - gradePainter.width - size.width * 0.1,
            size.height * 0.08));

    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
