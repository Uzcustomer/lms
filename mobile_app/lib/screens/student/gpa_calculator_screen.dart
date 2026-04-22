import 'package:flutter/material.dart';
import '../../config/theme.dart';

class GpaCalculatorScreen extends StatefulWidget {
  const GpaCalculatorScreen({super.key});

  @override
  State<GpaCalculatorScreen> createState() => _GpaCalculatorScreenState();
}

class _GpaCalculatorScreenState extends State<GpaCalculatorScreen> {
  final List<_SubjectEntry> _subjects = [_SubjectEntry(), _SubjectEntry()];
  bool _showInfo = false;

  static const List<_GradeOption> gradeOptions = [
    _GradeOption(5, "A'lo", '86-100', Color(0xFF4CAF50)),
    _GradeOption(4, 'Yaxshi', '71-85', Color(0xFF29B6F6)),
    _GradeOption(3, 'Qoniqarli', '56-70', Color(0xFFFF9800)),
    _GradeOption(2, 'Qoniqarsiz', '0-55', Color(0xFFE53935)),
  ];

  static const List<int> creditOptions = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

  int get _totalCredits {
    int total = 0;
    for (final s in _subjects) {
      if (s.grade != null) total += s.credits;
    }
    return total;
  }

  int get _gradedCount => _subjects.where((s) => s.grade != null).length;

  double get _gpa {
    double totalPoints = 0;
    int totalCredits = 0;
    for (final s in _subjects) {
      if (s.grade != null) {
        totalPoints += s.grade!.value * s.credits;
        totalCredits += s.credits;
      }
    }
    if (totalCredits == 0) return 0;
    return totalPoints / totalCredits;
  }

  String get _gpaLabel {
    final gpa = _gpa;
    if (gpa >= 4.5) return "A'lo";
    if (gpa >= 3.5) return 'Yaxshi';
    if (gpa >= 2.5) return 'Qoniqarli';
    if (gpa > 0) return 'Qoniqarsiz';
    return '';
  }

  Color get _gpaColor {
    final gpa = _gpa;
    if (gpa >= 4.5) return const Color(0xFF4CAF50);
    if (gpa >= 3.5) return const Color(0xFF29B6F6);
    if (gpa >= 2.5) return const Color(0xFFFF9800);
    if (gpa > 0) return const Color(0xFFE53935);
    return const Color(0xFF4A6CF7);
  }

  bool get _canPass => _gpa >= 2.4 && _gradedCount > 0;

  void _addSubject() => setState(() => _subjects.add(_SubjectEntry()));

  void _removeSubject(int i) {
    if (_subjects.length > 1) setState(() => _subjects.removeAt(i));
  }

  void _reset() {
    setState(() {
      _subjects.clear();
      _subjects.addAll([_SubjectEntry(), _SubjectEntry()]);
    });
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? AppTheme.darkBackground : const Color(0xFFF5F7FB);
    final card = isDark ? AppTheme.darkCard : Colors.white;
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        title: const Text('GPA Kalkulyator'),
        actions: [
          IconButton(
            icon: Icon(Icons.info_outline_rounded, color: sub, size: 22),
            onPressed: () => setState(() => _showInfo = !_showInfo),
          ),
          IconButton(
            icon: Icon(Icons.restart_alt_rounded, color: sub, size: 22),
            onPressed: _reset,
          ),
        ],
      ),
      body: Column(
        children: [
          // Info banner
          if (_showInfo) _buildInfoBanner(card, txt, sub, isDark),

          // GPA result
          _buildGpaResult(txt, sub, isDark),

          // Subject list
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 90),
              itemCount: _subjects.length,
              itemBuilder: (_, i) =>
                  _buildSubjectTile(i, card, txt, sub, isDark),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _addSubject,
        backgroundColor: const Color(0xFF4A6CF7),
        elevation: 4,
        icon: const Icon(Icons.add_rounded, color: Colors.white),
        label: const Text('Fan qo\'shish',
            style: TextStyle(
                color: Colors.white, fontWeight: FontWeight.w600)),
      ),
    );
  }

  Widget _buildInfoBanner(Color card, Color txt, Color sub, bool isDark) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
            color: const Color(0xFF4A6CF7).withOpacity(0.15)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.school_rounded,
                  size: 18, color: Color(0xFF4A6CF7)),
              const SizedBox(width: 8),
              Text('GPA qanday hisoblanadi?',
                  style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: txt)),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: isDark
                  ? Colors.white.withOpacity(0.04)
                  : const Color(0xFFF0F3FF),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              'GPA = (K₁×U₁ + K₂×U₂ + ... + Kₙ×Uₙ) ÷ (K₁ + K₂ + ... + Kₙ)',
              style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: txt,
                  fontFamily: 'monospace'),
            ),
          ),
          const SizedBox(height: 10),
          Text('K — fan kreditlari,  U — baho (5, 4, 3, 2)',
              style: TextStyle(fontSize: 12, color: sub)),
          const SizedBox(height: 10),
          Row(
            children: gradeOptions
                .map((g) => Expanded(
                      child: Container(
                        margin: const EdgeInsets.symmetric(horizontal: 2),
                        padding: const EdgeInsets.symmetric(vertical: 6),
                        decoration: BoxDecoration(
                          color: g.color.withOpacity(isDark ? 0.15 : 0.1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Column(
                          children: [
                            Text('${g.value}',
                                style: TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                    color: g.color)),
                            Text(g.label,
                                style: TextStyle(
                                    fontSize: 9,
                                    fontWeight: FontWeight.w600,
                                    color: g.color)),
                            const SizedBox(height: 2),
                            Text(g.range,
                                style:
                                    TextStyle(fontSize: 9, color: sub)),
                          ],
                        ),
                      ),
                    ))
                .toList(),
          ),
          const SizedBox(height: 8),
          Text('Kursdan o\'tish uchun GPA kamida 2.4 bo\'lishi kerak',
              style: TextStyle(
                  fontSize: 11,
                  color: sub,
                  fontStyle: FontStyle.italic)),
        ],
      ),
    );
  }

  Widget _buildGpaResult(Color txt, Color sub, bool isDark) {
    final hasGrades = _gradedCount > 0;

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 8),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [_gpaColor, _gpaColor.withOpacity(0.75)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: _gpaColor.withOpacity(0.25),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          // GPA number
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Sizning GPA',
                  style: TextStyle(
                      fontSize: 13,
                      color: Colors.white70,
                      fontWeight: FontWeight.w500)),
              const SizedBox(height: 2),
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    hasGrades ? _gpa.toStringAsFixed(2) : '0.00',
                    style: const TextStyle(
                        fontSize: 38,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                        height: 1.1),
                  ),
                  const Padding(
                    padding: EdgeInsets.only(bottom: 5, left: 3),
                    child: Text('/ 5.00',
                        style: TextStyle(
                            fontSize: 15, color: Colors.white54)),
                  ),
                ],
              ),
              if (hasGrades) ...[
                const SizedBox(height: 6),
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.2),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(_gpaLabel,
                          style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: Colors.white)),
                    ),
                    const SizedBox(width: 8),
                    Icon(
                      _canPass
                          ? Icons.check_circle_rounded
                          : Icons.cancel_rounded,
                      size: 16,
                      color: Colors.white.withOpacity(0.8),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      _canPass ? 'O\'tadi' : 'O\'tmaydi',
                      style: TextStyle(
                          fontSize: 11,
                          color: Colors.white.withOpacity(0.8),
                          fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ],
            ],
          ),
          const Spacer(),
          // Stats
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.15),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              children: [
                Text('$_gradedCount',
                    style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                        color: Colors.white)),
                const Text('fan',
                    style: TextStyle(
                        fontSize: 11, color: Colors.white70)),
                const SizedBox(height: 6),
                Text('$_totalCredits',
                    style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                        color: Colors.white)),
                const Text('kredit',
                    style: TextStyle(
                        fontSize: 11, color: Colors.white70)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSubjectTile(
      int index, Color card, Color txt, Color sub, bool isDark) {
    final s = _subjects[index];
    final gradeColor =
        s.grade?.color ?? (isDark ? Colors.white24 : Colors.grey.shade300);

    return Dismissible(
      key: ValueKey(s),
      direction: _subjects.length > 1
          ? DismissDirection.endToStart
          : DismissDirection.none,
      onDismissed: (_) => _removeSubject(index),
      background: Container(
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(
          color: const Color(0xFFE53935),
          borderRadius: BorderRadius.circular(14),
        ),
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        child: const Icon(Icons.delete_rounded, color: Colors.white),
      ),
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(
          color: card,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.03),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: IntrinsicHeight(
          child: Row(
            children: [
              // Color bar
              Container(
                width: 5,
                decoration: BoxDecoration(
                  color: gradeColor,
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(14),
                    bottomLeft: Radius.circular(14),
                  ),
                ),
              ),
              // Content
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    children: [
                      // Name row
                      Row(
                        children: [
                          Container(
                            width: 28,
                            height: 28,
                            decoration: BoxDecoration(
                              color: gradeColor.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(7),
                            ),
                            alignment: Alignment.center,
                            child: Text('${index + 1}',
                                style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.bold,
                                    color: gradeColor)),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: TextField(
                              onChanged: (v) => s.name = v,
                              style:
                                  TextStyle(fontSize: 14, color: txt),
                              decoration: InputDecoration(
                                hintText: 'Fan nomi (ixtiyoriy)',
                                hintStyle: TextStyle(
                                    fontSize: 13, color: sub),
                                border: InputBorder.none,
                                isDense: true,
                                contentPadding: EdgeInsets.zero,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      // Credit + Grade selectors
                      Row(
                        children: [
                          // Credits
                          Expanded(
                            flex: 4,
                            child: _dropdownBox(
                              isDark: isDark,
                              child: DropdownButtonHideUnderline(
                                child: DropdownButton<int>(
                                  value: s.credits,
                                  isExpanded: true,
                                  icon: Icon(Icons.unfold_more_rounded,
                                      size: 18, color: sub),
                                  style: TextStyle(
                                      fontSize: 13, color: txt),
                                  dropdownColor: card,
                                  items: creditOptions
                                      .map((c) => DropdownMenuItem(
                                            value: c,
                                            child: Row(
                                              children: [
                                                Icon(
                                                    Icons
                                                        .stars_rounded,
                                                    size: 14,
                                                    color: sub),
                                                const SizedBox(
                                                    width: 6),
                                                Text('$c kredit'),
                                              ],
                                            ),
                                          ))
                                      .toList(),
                                  onChanged: (v) {
                                    if (v != null) {
                                      setState(
                                          () => s.credits = v);
                                    }
                                  },
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          // Grade - chip buttons
                          Expanded(
                            flex: 6,
                            child: Row(
                              children: gradeOptions
                                  .map((g) => _gradeChip(s, g, isDark))
                                  .toList(),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _gradeChip(_SubjectEntry s, _GradeOption g, bool isDark) {
    final selected = s.grade == g;
    return Expanded(
      child: GestureDetector(
        onTap: () => setState(() => s.grade = selected ? null : g),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          margin: const EdgeInsets.symmetric(horizontal: 2),
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: selected
                ? g.color
                : g.color.withOpacity(isDark ? 0.08 : 0.06),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: selected
                  ? g.color
                  : g.color.withOpacity(isDark ? 0.2 : 0.15),
              width: selected ? 1.5 : 1,
            ),
          ),
          child: Column(
            children: [
              Text(
                '${g.value}',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: selected ? Colors.white : g.color,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _dropdownBox({required bool isDark, required Widget child}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: isDark
            ? Colors.white.withOpacity(0.05)
            : const Color(0xFFF5F7FB),
        borderRadius: BorderRadius.circular(10),
      ),
      child: child,
    );
  }
}

class _SubjectEntry {
  String name = '';
  int credits = 3;
  _GradeOption? grade;
}

class _GradeOption {
  final int value;
  final String label;
  final String range;
  final Color color;

  const _GradeOption(this.value, this.label, this.range, this.color);

  @override
  bool operator ==(Object other) =>
      other is _GradeOption && value == other.value;

  @override
  int get hashCode => value.hashCode;
}
