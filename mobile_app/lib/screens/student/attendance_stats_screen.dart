import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/student_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import '../../widgets/clinic_header.dart';

class AttendanceStatsScreen extends StatefulWidget {
  const AttendanceStatsScreen({super.key});

  @override
  State<AttendanceStatsScreen> createState() => _AttendanceStatsScreenState();
}

class _AttendanceStatsScreenState extends State<AttendanceStatsScreen> {
  static const _green = Color(0xFF15803D);
  static const _blue = Color(0xFF1D4ED8);
  static const _amber = Color(0xFFB45309);
  static const _red = Color(0xFFBE123C);

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

  Color _gradeColor(double v) {
    if (v >= 86) return _green;
    if (v >= 71) return _blue;
    if (v >= 56) return _amber;
    return _red;
  }

  @override
  Widget build(BuildContext context) {
    final muted = ClinicTheme.mutedOf(context);

    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: 'FOYDALI',
            title: 'Davomat statistikasi',
            onBack: () => Navigator.pop(context),
          ),
          if (_loadingSubjects)
            const Expanded(child: Center(child: CircularProgressIndicator()))
          else if (_subjects.isEmpty)
            Expanded(
              child: Center(
                child: Text('Fanlar topilmadi',
                    style: TextStyle(color: muted, fontSize: 15)),
              ),
            )
          else ...[
            _buildSubjectDropdown(),
            if (_selectedSubjectId != null)
              Expanded(
                child: _loadingGrades
                    ? const Center(child: CircularProgressIndicator())
                    : RefreshIndicator(
                        onRefresh: () => _loadGrades(_selectedSubjectId!),
                        child: _buildContent(),
                      ),
              ),
            if (_selectedSubjectId == null)
              Expanded(
                child: Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.touch_app_outlined,
                          size: 48, color: ClinicTheme.faint),
                      const SizedBox(height: 12),
                      Text('Fanni tanlang',
                          style: TextStyle(
                              color: muted,
                              fontSize: 15,
                              fontWeight: FontWeight.w600)),
                    ],
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }

  Widget _buildSubjectDropdown() {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);
    final surface = ClinicTheme.surfaceOf(context);

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 14, 14, 8),
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          value: _selectedSubjectId,
          hint: Text('Fanni tanlang', style: TextStyle(color: muted, fontSize: 14)),
          isExpanded: true,
          icon: Icon(Icons.keyboard_arrow_down_rounded, color: muted),
          dropdownColor: surface,
          borderRadius: BorderRadius.circular(14),
          style: TextStyle(fontSize: 14, color: ink),
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
                            fontWeight: FontWeight.w600,
                            color: ink)),
                  ),
                  if (hasAbsence)
                    Container(
                      margin: const EdgeInsets.only(left: 8),
                      padding:
                          const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: _red,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text('${davPercent.toStringAsFixed(0)}%',
                          style: const TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                              color: Colors.white)),
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
    final rawMap = <String, List<_PairGrade>>{};

    for (final g in _grades) {
      final ttCode = g['training_type_code'];
      if (ttCode == 11 ||
          ttCode == 99 ||
          ttCode == 100 ||
          ttCode == 101 ||
          ttCode == 102 ||
          ttCode == 103) {
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

      final key = '$dateKey|$pairNum';
      rawMap.putIfAbsent(key, () => []);
      rawMap[key]!.add(_PairGrade(pairNum, type, value));
    }

    final dayMap = <String, List<_PairGrade>>{};
    for (final entry in rawMap.entries) {
      final dateKey = entry.key.split('|')[0];
      final items = entry.value;
      dayMap.putIfAbsent(dateKey, () => []);
      dayMap[dateKey]!.add(_mergePairGrades(items));
    }

    final sorted = dayMap.keys.toList()..sort();
    return sorted.map((dateKey) {
      final pairs = dayMap[dateKey]!;
      pairs.sort((a, b) => a.pair.compareTo(b.pair));
      return _DayData(dateKey, pairs);
    }).toList();
  }

  _PairGrade _mergePairGrades(List<_PairGrade> items) {
    final pair = items.first.pair;
    final graded = items
        .where((p) => p.type == _CellType.graded && p.value != null)
        .toList();
    if (graded.isNotEmpty) {
      final avg =
          graded.map((p) => p.value!).reduce((a, b) => a + b) / graded.length;
      return _PairGrade(pair, _CellType.graded, avg);
    }
    final retake = items
        .where((p) => p.type == _CellType.retake && p.value != null)
        .toList();
    if (retake.isNotEmpty) {
      return _PairGrade(pair, _CellType.retake, retake.last.value);
    }
    if (items.any((p) => p.type == _CellType.absent)) {
      return _PairGrade(pair, _CellType.absent, null);
    }
    return _PairGrade(pair, _CellType.empty, null);
  }

  Widget _buildContent() {
    final days = _buildDays();

    if (days.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.event_note_outlined, size: 48, color: ClinicTheme.faint),
            const SizedBox(height: 12),
            Text('Ma\'lumot topilmadi',
                style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 14)),
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
        _buildStatsBar(totalPairs, attended, absentPairs, percent, avgGrade),
        Expanded(
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 20),
            itemCount: days.length,
            itemBuilder: (_, i) => _buildDayCard(days[i]),
          ),
        ),
      ],
    );
  }

  Widget _buildStatsBar(
      int total, int attended, int absent, double percent, double avgGrade) {
    final percentColor =
        percent >= 85 ? _green : (percent >= 70 ? _amber : _red);

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 4, 14, 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: percentColor,
            ),
            child: Center(
              child: Text('${percent.round()}%',
                  style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                      color: Colors.white)),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                _chip('Jami', '$total', ClinicTheme.mutedOf(context)),
                _chip('Bor', '$attended', _green),
                _chip('NB', '$absent', _red),
                if (avgGrade > 0)
                  _chip('O\'rtacha', avgGrade.toStringAsFixed(1), _blue),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _chip(String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text('$label: $value',
          style: const TextStyle(
              fontSize: 10.5, fontWeight: FontWeight.w800, color: Colors.white)),
    );
  }

  Widget _buildDayCard(_DayData day) {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);
    final hasAbsent = day.pairs.any((p) => p.type == _CellType.absent);
    final hasRetake = day.pairs.any((p) => p.type == _CellType.retake);

    String dateStr;
    String weekDay;
    try {
      final dt = DateTime.parse(day.date);
      dateStr =
          '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')}';
      const wds = [
        'Dushanba',
        'Seshanba',
        'Chorshanba',
        'Payshanba',
        'Juma',
        'Shanba',
        'Yakshanba'
      ];
      weekDay = wds[dt.weekday - 1];
    } catch (_) {
      dateStr = day.date;
      weekDay = '';
    }

    final gradedPairs = day.pairs
        .where((p) =>
            p.type == _CellType.graded || p.type == _CellType.retake)
        .toList();
    double? dayAvg;
    if (gradedPairs.isNotEmpty) {
      final vals =
          gradedPairs.where((p) => p.value != null).map((p) => p.value!);
      if (vals.isNotEmpty) {
        dayAvg = vals.reduce((a, b) => a + b) / vals.length;
      }
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: hasAbsent
              ? _red
              : hasRetake
                  ? _amber
                  : ClinicTheme.dividerOf(context),
          width: hasAbsent || hasRetake ? 1.5 : 1,
        ),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                  decoration: BoxDecoration(
                    color: ClinicTheme.teal,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(dateStr,
                      style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: Colors.white)),
                ),
                const SizedBox(width: 10),
                Text(weekDay,
                    style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: muted)),
                const Spacer(),
                if (dayAvg != null) _buildAvgBadge(dayAvg),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: day.pairs.map(_buildPairChip).toList(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAvgBadge(double avg) {
    final color = _gradeColor(avg);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(avg.toStringAsFixed(1),
          style: const TextStyle(
              fontSize: 12, fontWeight: FontWeight.w900, color: Colors.white)),
    );
  }

  Widget _buildPairChip(_PairGrade p) {
    final muted = ClinicTheme.mutedOf(context);

    if (p.type == _CellType.retake) {
      return Column(
        children: [
          SizedBox(
            width: 68,
            height: 36,
            child: CustomPaint(
              painter: _DiagonalCellPainter(
                gradeText: p.value != null
                    ? (p.value! % 1 == 0
                        ? p.value!.toInt().toString()
                        : p.value!.toStringAsFixed(1))
                    : '',
              ),
            ),
          ),
          const SizedBox(height: 2),
          Text('${p.pair}-juftlik', style: TextStyle(fontSize: 9.5, color: muted)),
        ],
      );
    }

    Color bgColor;
    String displayText;
    Color textColor;

    if (p.type == _CellType.absent) {
      bgColor = _red;
      displayText = 'NB';
      textColor = Colors.white;
    } else if (p.type == _CellType.empty) {
      bgColor = ClinicTheme.dividerOf(context);
      displayText = '—';
      textColor = ClinicTheme.faint;
    } else {
      final v = p.value ?? 0;
      bgColor = v > 0 ? _gradeColor(v) : ClinicTheme.dividerOf(context);
      displayText = v % 1 == 0 ? v.toInt().toString() : v.toStringAsFixed(1);
      textColor = v > 0 ? Colors.white : ClinicTheme.faint;
    }

    return Column(
      children: [
        Container(
          width: 68,
          height: 36,
          decoration: BoxDecoration(
            color: bgColor,
            borderRadius: BorderRadius.circular(10),
          ),
          alignment: Alignment.center,
          child: Text(displayText,
              style: TextStyle(
                  fontSize: 15, fontWeight: FontWeight.w900, color: textColor)),
        ),
        const SizedBox(height: 2),
        Text('${p.pair}-juftlik', style: TextStyle(fontSize: 9.5, color: muted)),
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

/// A pair cell that was missed then retaken — red lower-left triangle (NB)
/// and a green upper-right triangle with the retake grade.
class _DiagonalCellPainter extends CustomPainter {
  final String gradeText;

  _DiagonalCellPainter({required this.gradeText});

  static const _red = Color(0xFFBE123C);
  static const _green = Color(0xFF15803D);

  @override
  void paint(Canvas canvas, Size size) {
    final rect = RRect.fromRectAndRadius(
        Rect.fromLTWH(0, 0, size.width, size.height),
        const Radius.circular(10));
    canvas.save();
    canvas.clipRRect(rect);

    // Lower-left triangle (NB).
    final lower = Path()
      ..moveTo(0, 0)
      ..lineTo(0, size.height)
      ..lineTo(size.width, size.height)
      ..close();
    canvas.drawPath(lower, Paint()..color = _red);

    // Upper-right triangle (retake grade).
    final upper = Path()
      ..moveTo(0, 0)
      ..lineTo(size.width, 0)
      ..lineTo(size.width, size.height)
      ..close();
    canvas.drawPath(upper, Paint()..color = _green);

    canvas.drawLine(
      Offset(0, size.height),
      Offset(size.width, 0),
      Paint()
        ..color = Colors.white.withOpacity(0.6)
        ..strokeWidth = 1,
    );

    final nbPainter = TextPainter(
      text: const TextSpan(
        text: 'NB',
        style: TextStyle(
            fontSize: 9, fontWeight: FontWeight.w800, color: Colors.white),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    nbPainter.paint(canvas, Offset(4, size.height - nbPainter.height - 3));

    final gradePainter = TextPainter(
      text: TextSpan(
        text: gradeText,
        style: const TextStyle(
            fontSize: 12, fontWeight: FontWeight.w900, color: Colors.white),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    gradePainter.paint(canvas, Offset(size.width - gradePainter.width - 4, 3));

    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
