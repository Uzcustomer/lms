import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../services/student_service.dart';
import '../../services/api_service.dart';
import '../../widgets/loading_widget.dart';

class StudentJournalScreen extends StatefulWidget {
  final int subjectId;
  final String subjectName;

  const StudentJournalScreen({
    super.key,
    required this.subjectId,
    required this.subjectName,
  });

  @override
  State<StudentJournalScreen> createState() => _StudentJournalScreenState();
}

class _StudentJournalScreenState extends State<StudentJournalScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final ScrollController _scrollController = ScrollController();
  bool _isLoading = true;
  String? _error;

  // Grouped grades by training type
  List<Map<String, dynamic>> _amaliyGrades = [];
  List<Map<String, dynamic>> _maruzaGrades = [];
  List<Map<String, dynamic>> _mtGrades = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _loadGrades();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _loadGrades() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final service = StudentService(ApiService());
      final response = await service.getSubjectGrades(widget.subjectId);
      final data = response['data'] as Map<String, dynamic>?;
      final grades = (data?['grades'] as List<dynamic>?) ?? [];

      final amaliy = <Map<String, dynamic>>[];
      final maruza = <Map<String, dynamic>>[];
      final mt = <Map<String, dynamic>>[];

      for (final g in grades) {
        final grade = g as Map<String, dynamic>;
        final typeCode = grade['training_type_code'];
        final typeName = grade['training_type_name']?.toString() ?? '';

        if (typeCode == 11 || typeName.contains("Ma'ruza") || typeName.contains('Maruza')) {
          maruza.add(grade);
        } else if (typeCode == 99 || typeName.contains("Mustaqil") || typeName.contains('MT')) {
          mt.add(grade);
        } else if (typeCode != 100 && typeCode != 101 && typeCode != 102) {
          amaliy.add(grade);
        }
      }

      // Sort by date ascending
      amaliy.sort((a, b) => (a['lesson_date'] ?? '').compareTo(b['lesson_date'] ?? ''));
      maruza.sort((a, b) => (a['lesson_date'] ?? '').compareTo(b['lesson_date'] ?? ''));
      mt.sort((a, b) => (a['lesson_date'] ?? '').compareTo(b['lesson_date'] ?? ''));

      setState(() {
        _amaliyGrades = amaliy;
        _maruzaGrades = maruza;
        _mtGrades = mt;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
      appBar: AppBar(
        title: Text(
          widget.subjectName,
          style: const TextStyle(fontSize: 15),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        centerTitle: true,
        bottom: TabBar(
          controller: _tabController,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          indicatorColor: Colors.white,
          indicatorWeight: 3,
          labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
          unselectedLabelStyle: const TextStyle(fontSize: 12),
          tabs: [
            Tab(text: l.practicalClasses),
            Tab(text: l.lectures),
            Tab(text: l.selfStudy),
          ],
        ),
      ),
      body: _isLoading
          ? const LoadingWidget()
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!, style: TextStyle(color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary)),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: _loadGrades,
                        child: Text(l.reload),
                      ),
                    ],
                  ),
                )
              : TabBarView(
                  controller: _tabController,
                  children: [
                    _JournalTable(grades: _amaliyGrades, isDark: isDark, label: 'JN', noDataLabel: l.noData, averageLabel: l.average),
                    _JournalTable(grades: _maruzaGrades, isDark: isDark, label: "Ma'ruza", noDataLabel: l.noData, averageLabel: l.average),
                    _JournalTable(grades: _mtGrades, isDark: isDark, label: 'MT', noDataLabel: l.noData, averageLabel: l.average),
                  ],
                ),
    );
  }
}

class _JournalTable extends StatefulWidget {
  final List<Map<String, dynamic>> grades;
  final bool isDark;
  final String label;
  final String noDataLabel;
  final String averageLabel;

  const _JournalTable({
    required this.grades,
    required this.isDark,
    required this.label,
    required this.noDataLabel,
    required this.averageLabel,
  });

  @override
  State<_JournalTable> createState() => _JournalTableState();
}

class _JournalTableState extends State<_JournalTable> {
  final ScrollController _hScrollController = ScrollController();

  @override
  void dispose() {
    _hScrollController.dispose();
    super.dispose();
  }

  void _scrollLeft() {
    _hScrollController.animateTo(
      (_hScrollController.offset - 200).clamp(0, _hScrollController.position.maxScrollExtent),
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeOut,
    );
  }

  void _scrollRight() {
    _hScrollController.animateTo(
      (_hScrollController.offset + 200).clamp(0, _hScrollController.position.maxScrollExtent),
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeOut,
    );
  }

  String _formatDate(String? dateStr) {
    if (dateStr == null || dateStr.isEmpty) return '-';
    try {
      final date = DateTime.parse(dateStr);
      return '${date.day.toString().padLeft(2, '0')}.${date.month.toString().padLeft(2, '0')}';
    } catch (_) {
      return dateStr;
    }
  }

  double _computeAverage() {
    final graded = widget.grades.where((g) {
      final grade = g['grade'];
      return grade != null && grade is num && grade > 0;
    }).toList();
    if (graded.isEmpty) return 0;
    final sum = graded.fold<double>(0, (s, g) => s + (g['grade'] as num).toDouble());
    return sum / graded.length;
  }

  @override
  Widget build(BuildContext context) {
    if (widget.grades.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.table_chart_outlined,
                size: 48,
                color: widget.isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
            const SizedBox(height: 12),
            Text(
              widget.noDataLabel,
              style: TextStyle(
                color: widget.isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
              ),
            ),
          ],
        ),
      );
    }

    final avg = _computeAverage();
    final headerBg = widget.isDark ? AppTheme.darkSurface : AppTheme.primaryColor;
    final headerText = Colors.white;
    final cellBg = widget.isDark ? AppTheme.darkCard : Colors.white;
    final cellText = widget.isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final borderColor = widget.isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);

    return Column(
      children: [
        // Navigation arrows
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(
                onPressed: _scrollLeft,
                icon: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: AppTheme.primaryColor.withAlpha(20),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.chevron_left, color: AppTheme.primaryColor, size: 20),
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Text(
                  widget.label,
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                    color: AppTheme.primaryColor,
                  ),
                ),
              ),
              IconButton(
                onPressed: _scrollRight,
                icon: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: AppTheme.primaryColor.withAlpha(20),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.chevron_right, color: AppTheme.primaryColor, size: 20),
                ),
              ),
            ],
          ),
        ),

        // Table
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 100),
            child: SingleChildScrollView(
              controller: _hScrollController,
              scrollDirection: Axis.horizontal,
              child: Table(
                border: TableBorder.all(color: borderColor, width: 1),
                defaultColumnWidth: const FixedColumnWidth(60),
                columnWidths: const {
                  0: FixedColumnWidth(40), // №
                },
                children: [
                  // Header row
                  TableRow(
                    decoration: BoxDecoration(color: headerBg),
                    children: [
                      _headerCell('№', headerText),
                      ...widget.grades.map((g) =>
                          _headerCell(_formatDate(g['lesson_date']), headerText)),
                      _headerCell(widget.averageLabel, headerText),
                    ],
                  ),
                  // Data row
                  TableRow(
                    decoration: BoxDecoration(color: cellBg),
                    children: [
                      _dataCell('1', cellText, borderColor),
                      ...widget.grades.map((g) {
                        final grade = g['grade'];
                        final retake = g['retake_grade'];
                        final reason = g['reason']?.toString();
                        final displayGrade = retake ?? grade;

                        Color? gradeColor;
                        String displayText;

                        if (reason == 'absent' && (grade == null || grade == 0)) {
                          displayText = 'NB';
                          gradeColor = AppTheme.errorColor;
                        } else if (displayGrade != null && displayGrade is num) {
                          displayText = displayGrade % 1 == 0
                              ? displayGrade.toInt().toString()
                              : displayGrade.toStringAsFixed(1);
                          final val = displayGrade.toDouble();
                          if (val >= 86) {
                            gradeColor = AppTheme.successColor;
                          } else if (val >= 71) {
                            gradeColor = AppTheme.primaryColor;
                          } else if (val >= 56) {
                            gradeColor = AppTheme.warningColor;
                          } else if (val > 0) {
                            gradeColor = AppTheme.errorColor;
                          }
                        } else {
                          displayText = '-';
                        }

                        return _dataCell(
                          displayText,
                          gradeColor ?? cellText,
                          borderColor,
                          bold: retake != null,
                        );
                      }),
                      _dataCell(
                        avg > 0 ? avg.toStringAsFixed(1) : '-',
                        avg >= 86
                            ? AppTheme.successColor
                            : avg >= 71
                                ? AppTheme.primaryColor
                                : avg >= 56
                                    ? AppTheme.warningColor
                                    : avg > 0
                                        ? AppTheme.errorColor
                                        : cellText,
                        borderColor,
                        bold: true,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _headerCell(String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
      alignment: Alignment.center,
      child: Text(
        text,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: color,
        ),
        textAlign: TextAlign.center,
      ),
    );
  }

  Widget _dataCell(String text, Color color, Color borderColor, {bool bold = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
      alignment: Alignment.center,
      child: Text(
        text,
        style: TextStyle(
          fontSize: 13,
          fontWeight: bold ? FontWeight.bold : FontWeight.w500,
          color: color,
        ),
        textAlign: TextAlign.center,
      ),
    );
  }
}
