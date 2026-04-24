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
                    _buildSubjectDropdown(isDark, txt, sub),
                    if (_selectedSubjectId != null)
                      Expanded(
                        child: _loadingGrades
                            ? const Center(child: CircularProgressIndicator())
                            : _buildContent(isDark, txt, sub),
                      ),
                    if (_selectedSubjectId == null)
                      Expanded(
                        child: Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.touch_app_outlined,
                                  size: 48, color: sub.withOpacity(0.5)),
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

  Widget _buildSubjectDropdown(bool isDark, Color txt, Color sub) {
    final cardBg = isDark ? AppTheme.darkCard : Colors.white;
    final borderColor = isDark ? Colors.white12 : Colors.grey.shade300;

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 12, 14, 8),
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: cardBg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: borderColor),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          value: _selectedSubjectId,
          hint: Text('Fanni tanlang',
              style: TextStyle(color: sub, fontSize: 14)),
          isExpanded: true,
          icon: Icon(Icons.keyboard_arrow_down_rounded, color: sub),
          dropdownColor: cardBg,
          borderRadius: BorderRadius.circular(14),
          style: TextStyle(fontSize: 14, color: txt),
          items: _subjects.map<DropdownMenuItem<int>>((s) {
            final id = s['subject_id'] as int? ?? 0;
            final name = s['subject_name']?.toString() ?? '';
            final davPercent = _toDouble(s['dav_percent']);
            final hasAbsence = davPercent > 0;

            return DropdownMenuItem<int>(
              value: id,
              child: Row(
                children: [
                  Expanded(
                    child: Text(name,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            fontSize: 13.5,
                            fontWeight: _selectedSubjectId == id
                                ? FontWeight.w600
                                : FontWeight.w400,
                            color: txt)),
                  ),
                  if (hasAbsence)
                    Container(
                      margin: const EdgeInsets.only(left: 8),
                      padding: const EdgeInsets.symmetric(
                          horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppTheme.errorColor.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text('${davPercent.toStringAsFixed(0)}%',
                          style: const TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w600,
                              color: AppTheme.errorColor)),
                    ),
                ],
              ),
            );
          }).toList(),
          onChanged: (id) {
            if (id == null) return;
            final s = _subjects.firstWhere(
                (s) => (s['subject_id'] as int? ?? 0) == id,
                orElse: () => null);
            setState(() {
              _selectedSubjectId = id;
              _selectedSubjectName = s?['subject_name']?.toString() ?? '';
            });
            _loadGrades(id);
          },
        ),
      ),
    );
  }

  List<_DayData> _buildDays() {
    final dayMap = <String, List<_PairGrade>>{};

    for (final g in _grades) {
      final ttCode = g['training_type_code'];
      if (ttCode == 11 || ttCode == 99 || ttCode == 100 ||
          ttCode == 101 || ttCode == 102 || ttCode == 103) {
        continue;
      }

      final dateRaw = g['lesson_date']?.toString() ?? '';
      if (dateRaw.length < 10) continue;
      final dateKey = dateRaw.substring(0, 10);

      final pairRaw = g['lesson_pair_name']?.toString() ?? '';
      final pairNum = _extractPairNum(pairRaw);
      final reason = g['reason']?.toString();
      final status = g['status']?.toString();
      final grade = g['grade'];
      final retakeGrade = g['retake_grade'];

      _CellType type;
      double? value;

      if (reason == 'absent' && (grade == null || grade == 0)) {
        if (retakeGrade != null && retakeGrade is num && retakeGrade > 0) {
          type = _CellType.retake;
          value = retakeGrade.toDouble();
        } else {
          type = _CellType.absent;
        }
      } else if (status == 'pending' && reason != 'low_grade') {
        type = _CellType.empty;
      } else if (retakeGrade != null && retakeGrade is num && retakeGrade > 0) {
        type = _CellType.graded;
        value = retakeGrade.toDouble();
      } else if (grade != null && grade is num) {
        type = _CellType.graded;
        value = grade.toDouble();
      } else {
        type = _CellType.empty;
      }

      dayMap.putIfAbsent(dateKey, () => []);
      dayMap[dateKey]!.add(_PairGrade(pairNum, type, value));
    }

    final sorted = dayMap.keys.toList()..sort();
    return sorted.map((dateKey) {
      final pairs = dayMap[dateKey]!;
      pairs.sort((a, b) => a.pair.compareTo(b.pair));
      return _DayData(dateKey, pairs);
    }).toList();
  }

  Widget _buildContent(bool isDark, Color txt, Color sub) {
    final days = _buildDays();

    if (days.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.event_note_outlined,
                size: 48, color: sub.withOpacity(0.5)),
            const SizedBox(height: 12),
            Text('Ma\'lumot topilmadi',
                style: TextStyle(color: sub, fontSize: 14)),
          ],
        ),
      );
    }

    int totalPairs = 0;
    int absentPairs = 0;
    double gradeSum = 0;
    int gradeCount = 0;
    for (final d in days) {
      for (final p in d.pairs) {
        if (p.type == _CellType.empty) continue;
        totalPairs++;
        if (p.type == _CellType.absent) {
          absentPairs++;
        } else if (p.value != null) {
          gradeSum += p.value!;
          gradeCount++;
        }
      }
    }
    final attended = totalPairs - absentPairs;
    final percent = totalPairs > 0 ? (attended / totalPairs * 100) : 100.0;
    final avgGrade = gradeCount > 0 ? gradeSum / gradeCount : 0.0;

    return Column(
      children: [
        _buildStatsBar(
            totalPairs, attended, absentPairs, percent, avgGrade, isDark, txt),
        Expanded(
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 20),
            itemCount: days.length,
            itemBuilder: (_, i) =>
                _buildDayCard(days[i], isDark, txt, sub),
          ),
        ),
      ],
    );
  }

  Widget _buildStatsBar(int total, int attended, int absent, double percent,
      double avgGrade, bool isDark, Color txt) {
    final percentColor = percent >= 85
        ? AppTheme.successColor
        : percent >= 70
            ? AppTheme.warningColor
            : AppTheme.errorColor;

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 4, 14, 10),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: [
          percentColor.withOpacity(0.1),
          percentColor.withOpacity(0.04),
        ]),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: percentColor.withOpacity(0.2)),
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: percentColor.withOpacity(0.15),
            ),
            child: Center(
              child: Text('${percent.round()}%',
                  style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: percentColor)),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Wrap(
              spacing: 6,
              runSpacing: 4,
              children: [
                _chip('Jami', '$total', Colors.blueGrey),
                _chip('Bor', '$attended', AppTheme.successColor),
                _chip('NB', '$absent', AppTheme.errorColor),
                if (avgGrade > 0)
                  _chip('O\'rtacha', avgGrade.toStringAsFixed(1),
                      const Color(0xFF1E88E5)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _chip(String label, String value, Color color) {
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

  Widget _buildDayCard(_DayData day, bool isDark, Color txt, Color sub) {
    final hasAbsent = day.pairs.any((p) => p.type == _CellType.absent);
    final hasRetake = day.pairs.any((p) => p.type == _CellType.retake);
    final cardBg = isDark ? AppTheme.darkCard : Colors.white;
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);

    String dateStr;
    String weekDay;
    try {
      final dt = DateTime.parse(day.date);
      dateStr =
          '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')}';
      const wds = ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba', 'Yakshanba'];
      weekDay = wds[dt.weekday - 1];
    } catch (_) {
      dateStr = day.date;
      weekDay = '';
    }

    final gradedPairs = day.pairs.where((p) =>
        p.type == _CellType.graded || p.type == _CellType.retake).toList();
    double? dayAvg;
    if (gradedPairs.isNotEmpty) {
      final vals = gradedPairs.where((p) => p.value != null).map((p) => p.value!);
      if (vals.isNotEmpty) {
        dayAvg = vals.reduce((a, b) => a + b) / vals.length;
      }
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: cardBg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: hasAbsent
              ? AppTheme.errorColor.withOpacity(0.25)
              : hasRetake
                  ? AppTheme.warningColor.withOpacity(0.25)
                  : borderColor,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: const Color(0xFF4A6CF7).withOpacity(0.08),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(dateStr,
                      style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: txt)),
                ),
                const SizedBox(width: 10),
                Text(weekDay,
                    style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                        color: sub)),
                const Spacer(),
                if (dayAvg != null) _buildAvgBadge(dayAvg),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: day.pairs.map((p) {
                return _buildPairChip(p, isDark, txt, sub);
              }).toList(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAvgBadge(double avg) {
    final color = avg >= 86
        ? AppTheme.successColor
        : avg >= 71
            ? const Color(0xFF1E88E5)
            : avg >= 56
                ? AppTheme.warningColor
                : AppTheme.errorColor;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Text(avg.toStringAsFixed(1),
          style: TextStyle(
              fontSize: 12, fontWeight: FontWeight.w700, color: color)),
    );
  }

  Widget _buildPairChip(_PairGrade p, bool isDark, Color txt, Color sub) {
    if (p.type == _CellType.retake) {
      return Container(
        width: 68,
        height: 52,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(10),
        ),
        child: Column(
          children: [
            SizedBox(
              width: 68,
              height: 36,
              child: CustomPaint(
                painter: _DiagonalCellPainter(
                  nbText: 'NB',
                  gradeText: p.value != null
                      ? (p.value! % 1 == 0
                          ? p.value!.toInt().toString()
                          : p.value!.toStringAsFixed(1))
                      : '',
                  isDark: isDark,
                ),
              ),
            ),
            const SizedBox(height: 2),
            Text('${p.pair}-juftlik',
                style: TextStyle(fontSize: 9.5, color: sub)),
          ],
        ),
      );
    }

    Color bgColor;
    Color borderCol;
    String displayText;
    Color textColor;

    if (p.type == _CellType.absent) {
      bgColor = AppTheme.errorColor.withOpacity(0.08);
      borderCol = AppTheme.errorColor.withOpacity(0.25);
      displayText = 'NB';
      textColor = AppTheme.errorColor;
    } else if (p.type == _CellType.empty) {
      bgColor = Colors.grey.withOpacity(0.05);
      borderCol = Colors.grey.withOpacity(0.15);
      displayText = '—';
      textColor = sub;
    } else {
      final v = p.value ?? 0;
      final gradeColor = v >= 86
          ? AppTheme.successColor
          : v >= 71
              ? const Color(0xFF1E88E5)
              : v >= 56
                  ? AppTheme.warningColor
                  : v > 0
                      ? AppTheme.errorColor
                      : sub;
      bgColor = gradeColor.withOpacity(0.08);
      borderCol = gradeColor.withOpacity(0.25);
      displayText =
          v % 1 == 0 ? v.toInt().toString() : v.toStringAsFixed(1);
      textColor = gradeColor;
    }

    return Column(
      children: [
        Container(
          width: 68,
          height: 36,
          decoration: BoxDecoration(
            color: bgColor,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: borderCol),
          ),
          alignment: Alignment.center,
          child: Text(displayText,
              style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: textColor)),
        ),
        const SizedBox(height: 2),
        Text('${p.pair}-juftlik',
            style: TextStyle(fontSize: 9.5, color: sub)),
      ],
    );
  }

  String _extractPairNum(String raw) {
    final match = RegExp(r'(\d+)').firstMatch(raw);
    return match?.group(1) ?? raw;
  }

  double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }
}

enum _CellType { empty, graded, absent, retake }

class _PairGrade {
  final String pair;
  final _CellType type;
  final double? value;
  _PairGrade(this.pair, this.type, this.value);
}

class _DayData {
  final String date;
  final List<_PairGrade> pairs;
  _DayData(this.date, this.pairs);
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

    canvas.drawRRect(
        rect, Paint()..color = AppTheme.warningColor.withOpacity(0.08));
    canvas.drawRRect(
        rect,
        Paint()
          ..color = AppTheme.warningColor.withOpacity(0.25)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 1);

    canvas.save();
    canvas.clipRRect(rect);
    canvas.drawLine(Offset(0, size.height), Offset(size.width, 0),
        Paint()
          ..color = AppTheme.warningColor.withOpacity(0.35)
          ..strokeWidth = 1);

    final nbPainter = TextPainter(
      text: TextSpan(
          text: nbText,
          style: TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.w700,
              color: AppTheme.errorColor.withOpacity(0.7))),
      textDirection: TextDirection.ltr,
    )..layout();
    nbPainter.paint(canvas, Offset(4, size.height - nbPainter.height - 3));

    final gradePainter = TextPainter(
      text: TextSpan(
          text: gradeText,
          style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w800,
              color: AppTheme.successColor)),
      textDirection: TextDirection.ltr,
    )..layout();
    gradePainter.paint(
        canvas, Offset(size.width - gradePainter.width - 4, 3));

    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
