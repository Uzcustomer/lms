import 'package:flutter/material.dart';
import '../../config/theme.dart';

class GpaCalculatorScreen extends StatefulWidget {
  const GpaCalculatorScreen({super.key});

  @override
  State<GpaCalculatorScreen> createState() => _GpaCalculatorScreenState();
}

class _GpaCalculatorScreenState extends State<GpaCalculatorScreen> {
  final List<_SubjectEntry> _subjects = [_SubjectEntry()];

  static const List<_GradeOption> gradeOptions = [
    _GradeOption('A+', 4.0, '97-100'),
    _GradeOption('A', 4.0, '93-96'),
    _GradeOption('A-', 3.7, '90-92'),
    _GradeOption('B+', 3.3, '87-89'),
    _GradeOption('B', 3.0, '83-86'),
    _GradeOption('B-', 2.7, '80-82'),
    _GradeOption('C+', 2.3, '77-79'),
    _GradeOption('C', 2.0, '73-76'),
    _GradeOption('C-', 1.7, '70-72'),
    _GradeOption('D+', 1.3, '67-69'),
    _GradeOption('D', 1.0, '60-66'),
    _GradeOption('F', 0.0, '0-59'),
  ];

  static const List<int> creditOptions = [1, 2, 3, 4, 5, 6, 7, 8];

  double get _totalCredits {
    double total = 0;
    for (final s in _subjects) {
      if (s.grade != null) total += s.credits;
    }
    return total;
  }

  double get _gpa {
    double totalPoints = 0;
    double totalCredits = 0;
    for (final s in _subjects) {
      if (s.grade != null) {
        totalPoints += s.grade!.points * s.credits;
        totalCredits += s.credits;
      }
    }
    if (totalCredits == 0) return 0;
    return totalPoints / totalCredits;
  }

  String get _gpaStatus {
    final gpa = _gpa;
    if (gpa >= 3.7) return 'A\'lo';
    if (gpa >= 3.0) return 'Yaxshi';
    if (gpa >= 2.0) return 'Qoniqarli';
    if (gpa > 0) return 'Past';
    return '';
  }

  Color get _gpaColor {
    final gpa = _gpa;
    if (gpa >= 3.7) return const Color(0xFF4CAF50);
    if (gpa >= 3.0) return const Color(0xFF29B6F6);
    if (gpa >= 2.0) return const Color(0xFFFF9800);
    if (gpa > 0) return const Color(0xFFE53935);
    return const Color(0xFF9E9E9E);
  }

  void _addSubject() {
    setState(() => _subjects.add(_SubjectEntry()));
  }

  void _removeSubject(int index) {
    if (_subjects.length > 1) {
      setState(() => _subjects.removeAt(index));
    }
  }

  void _reset() {
    setState(() {
      _subjects.clear();
      _subjects.add(_SubjectEntry());
    });
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkBackground : const Color(0xFFF5F7FB);
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        title: const Text('GPA Kalkulyator'),
        actions: [
          TextButton.icon(
            onPressed: _reset,
            icon: Icon(Icons.refresh_rounded, size: 18, color: subColor),
            label: Text('Tozalash',
                style: TextStyle(fontSize: 13, color: subColor)),
          ),
        ],
      ),
      body: Column(
        children: [
          // GPA result card
          _buildGpaCard(cardColor, textColor, subColor, isDark),

          // Subject list
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
              itemCount: _subjects.length,
              itemBuilder: (context, index) => _buildSubjectCard(
                  index, cardColor, textColor, subColor, isDark),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _addSubject,
        backgroundColor: const Color(0xFF4A6CF7),
        icon: const Icon(Icons.add_rounded, color: Colors.white),
        label: const Text('Fan qo\'shish',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
      ),
    );
  }

  Widget _buildGpaCard(
      Color cardColor, Color textColor, Color subColor, bool isDark) {
    final hasGrades = _subjects.any((s) => s.grade != null);

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: hasGrades
              ? [_gpaColor, _gpaColor.withOpacity(0.7)]
              : [const Color(0xFF4A6CF7), const Color(0xFF6C63FF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: (hasGrades ? _gpaColor : const Color(0xFF4A6CF7))
                .withOpacity(0.3),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Sizning GPA',
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.white70,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 4),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      hasGrades ? _gpa.toStringAsFixed(2) : '0.00',
                      style: const TextStyle(
                        fontSize: 36,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                    const Padding(
                      padding: EdgeInsets.only(bottom: 6, left: 4),
                      child: Text(
                        '/ 4.00',
                        style: TextStyle(
                          fontSize: 16,
                          color: Colors.white60,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
                if (hasGrades && _gpaStatus.isNotEmpty)
                  Container(
                    margin: const EdgeInsets.only(top: 6),
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.2),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      _gpaStatus,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: Colors.white,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              _statItem('Fanlar', '${_subjects.length}'),
              const SizedBox(height: 8),
              _statItem('Kreditlar', '${_totalCredits.toInt()}'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _statItem(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Text(label,
            style:
                const TextStyle(fontSize: 11, color: Colors.white60)),
        Text(value,
            style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: Colors.white)),
      ],
    );
  }

  Widget _buildSubjectCard(int index, Color cardColor, Color textColor,
      Color subColor, bool isDark) {
    final subject = _subjects[index];

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(14),
        border: subject.grade != null
            ? Border(
                left: BorderSide(
                  color: _gradeColor(subject.grade!.points),
                  width: 3.5,
                ),
              )
            : null,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        children: [
          // Subject name + delete
          Row(
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: const Color(0xFF4A6CF7).withOpacity(isDark ? 0.2 : 0.08),
                  borderRadius: BorderRadius.circular(8),
                ),
                alignment: Alignment.center,
                child: Text(
                  '${index + 1}',
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF4A6CF7),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: TextField(
                  onChanged: (v) => subject.name = v,
                  style: TextStyle(fontSize: 14, color: textColor),
                  decoration: InputDecoration(
                    hintText: 'Fan nomi',
                    hintStyle: TextStyle(fontSize: 14, color: subColor),
                    border: InputBorder.none,
                    isDense: true,
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
              ),
              if (_subjects.length > 1)
                GestureDetector(
                  onTap: () => _removeSubject(index),
                  child: Icon(Icons.close_rounded, size: 18, color: subColor),
                ),
            ],
          ),

          const SizedBox(height: 12),

          // Credits and Grade dropdowns
          Row(
            children: [
              // Credits
              Expanded(
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 0),
                  decoration: BoxDecoration(
                    color: isDark
                        ? Colors.white.withOpacity(0.05)
                        : const Color(0xFFF5F7FB),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<int>(
                      value: subject.credits,
                      isExpanded: true,
                      icon: Icon(Icons.expand_more_rounded,
                          size: 20, color: subColor),
                      style: TextStyle(fontSize: 13, color: textColor),
                      dropdownColor: cardColor,
                      hint: Text('Kredit', style: TextStyle(color: subColor)),
                      items: creditOptions
                          .map((c) => DropdownMenuItem(
                                value: c,
                                child: Text('$c kredit'),
                              ))
                          .toList(),
                      onChanged: (v) {
                        if (v != null) setState(() => subject.credits = v);
                      },
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              // Grade
              Expanded(
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 0),
                  decoration: BoxDecoration(
                    color: isDark
                        ? Colors.white.withOpacity(0.05)
                        : const Color(0xFFF5F7FB),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<_GradeOption>(
                      value: subject.grade,
                      isExpanded: true,
                      icon: Icon(Icons.expand_more_rounded,
                          size: 20, color: subColor),
                      style: TextStyle(fontSize: 13, color: textColor),
                      dropdownColor: cardColor,
                      hint: Text('Baho', style: TextStyle(color: subColor)),
                      items: gradeOptions
                          .map((g) => DropdownMenuItem(
                                value: g,
                                child: Row(
                                  children: [
                                    Container(
                                      width: 6,
                                      height: 6,
                                      decoration: BoxDecoration(
                                        shape: BoxShape.circle,
                                        color: _gradeColor(g.points),
                                      ),
                                    ),
                                    const SizedBox(width: 6),
                                    Text(g.letter),
                                    const SizedBox(width: 4),
                                    Text('(${g.points})',
                                        style: TextStyle(
                                            fontSize: 11, color: subColor)),
                                  ],
                                ),
                              ))
                          .toList(),
                      onChanged: (v) {
                        setState(() => subject.grade = v);
                      },
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Color _gradeColor(double points) {
    if (points >= 3.7) return const Color(0xFF4CAF50);
    if (points >= 3.0) return const Color(0xFF29B6F6);
    if (points >= 2.0) return const Color(0xFFFF9800);
    return const Color(0xFFE53935);
  }
}

class _SubjectEntry {
  String name = '';
  int credits = 3;
  _GradeOption? grade;
}

class _GradeOption {
  final String letter;
  final double points;
  final String range;

  const _GradeOption(this.letter, this.points, this.range);

  @override
  bool operator ==(Object other) =>
      other is _GradeOption && letter == other.letter;

  @override
  int get hashCode => letter.hashCode;
}
