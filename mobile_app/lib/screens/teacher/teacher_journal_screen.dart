import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../services/teacher_service.dart';
import '../../services/api_service.dart';
import '../../widgets/loading_widget.dart';

class TeacherJournalScreen extends StatefulWidget {
  final int groupId;
  final String groupName;
  final int semesterId;
  final int subjectId;
  final String subjectName;

  const TeacherJournalScreen({
    super.key,
    required this.groupId,
    required this.groupName,
    required this.semesterId,
    required this.subjectId,
    required this.subjectName,
  });

  @override
  State<TeacherJournalScreen> createState() => _TeacherJournalScreenState();
}

class _TeacherJournalScreenState extends State<TeacherJournalScreen> {
  final TeacherService _service = TeacherService(ApiService());
  final ScrollController _horizontalController = ScrollController();
  final ScrollController _verticalController = ScrollController();
  final ScrollController _frozenVerticalController = ScrollController();

  bool _isLoading = true;
  String? _error;
  List<Map<String, dynamic>> _students = [];
  String _groupLabel = '';
  String _semesterLabel = '';
  String _subjectLabel = '';

  @override
  void initState() {
    super.initState();
    _syncVerticalScroll();
    _loadData();
  }

  void _syncVerticalScroll() {
    _verticalController.addListener(() {
      if (_frozenVerticalController.hasClients &&
          _frozenVerticalController.offset != _verticalController.offset) {
        _frozenVerticalController.jumpTo(_verticalController.offset);
      }
    });
    _frozenVerticalController.addListener(() {
      if (_verticalController.hasClients &&
          _verticalController.offset != _frozenVerticalController.offset) {
        _verticalController.jumpTo(_frozenVerticalController.offset);
      }
    });
  }

  @override
  void dispose() {
    _horizontalController.dispose();
    _verticalController.dispose();
    _frozenVerticalController.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final response = await _service.getGroupStudentGrades(
        groupId: widget.groupId,
        semesterId: widget.semesterId,
        subjectId: widget.subjectId,
      );
      final data = response['data'] as Map<String, dynamic>? ?? {};
      final students = (data['students'] as List<dynamic>?) ?? [];

      setState(() {
        _students = students.cast<Map<String, dynamic>>();
        _groupLabel = data['group']?.toString() ?? widget.groupName;
        _semesterLabel = data['semester']?.toString() ?? '';
        _subjectLabel = data['subject']?.toString() ?? widget.subjectName;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  Color _gradeColor(double val) {
    if (val >= 86) return AppTheme.successColor;
    if (val >= 71) return AppTheme.primaryColor;
    if (val >= 56) return AppTheme.warningColor;
    if (val > 0) return AppTheme.errorColor;
    return AppTheme.textSecondary;
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
      ),
      body: _isLoading
          ? const LoadingWidget()
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.error_outline, size: 48,
                          color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                      const SizedBox(height: 12),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 32),
                        child: Text(_error!, textAlign: TextAlign.center),
                      ),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: _loadData,
                        child: Text(l.reload),
                      ),
                    ],
                  ),
                )
              : Column(
                  children: [
                    // Info header
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                      color: isDark ? AppTheme.darkSurface : const Color(0xFFF5F5F5),
                      child: Row(
                        children: [
                          _infoBadge(_groupLabel, Icons.groups, isDark),
                          const SizedBox(width: 8),
                          _infoBadge(_semesterLabel, Icons.calendar_today, isDark),
                          const Spacer(),
                          Text(
                            '${_students.length} ${l.get("student").toLowerCase()}',
                            style: TextStyle(
                              fontSize: 12,
                              color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),

                    // Table
                    Expanded(
                      child: _students.isEmpty
                          ? Center(child: Text(l.noData))
                          : _buildTable(isDark, l),
                    ),
                  ],
                ),
    );
  }

  Widget _infoBadge(String text, IconData icon, bool isDark) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: AppTheme.primaryColor.withAlpha(isDark ? 30 : 15),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: AppTheme.primaryColor),
          const SizedBox(width: 4),
          Text(
            text,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppTheme.primaryColor,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTable(bool isDark, AppLocalizations l) {
    final headerBg = isDark ? AppTheme.darkSurface : AppTheme.primaryColor;
    const headerText = Colors.white;
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);
    final cellBg = isDark ? AppTheme.darkCard : Colors.white;
    final altCellBg = isDark ? AppTheme.darkSurface : const Color(0xFFFAFAFA);
    final cellTextColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;

    const double numberW = 36;
    const double nameW = 170;
    const double frozenW = numberW + nameW;
    const double dataColW = 70;

    final scrollableCols = [
      {'label': l.get('avg_grade'), 'key': 'average_grade'},
      {'label': l.get('total_grades'), 'key': 'total_grades'},
    ];

    return Row(
      children: [
        // Frozen columns (№ + Name)
        SizedBox(
          width: frozenW,
          child: Column(
            children: [
              // Header
              Container(
                height: 44,
                decoration: BoxDecoration(
                  color: headerBg,
                  border: Border(
                    bottom: BorderSide(color: borderColor),
                    right: BorderSide(color: borderColor),
                  ),
                ),
                child: Row(
                  children: [
                    SizedBox(
                      width: numberW,
                      child: const Center(
                        child: Text('№', style: TextStyle(
                          fontSize: 12, fontWeight: FontWeight.w600, color: headerText,
                        )),
                      ),
                    ),
                    Container(width: 1, color: borderColor.withAlpha(80)),
                    Expanded(
                      child: Center(
                        child: Text('F.I.Sh', style: TextStyle(
                          fontSize: 12, fontWeight: FontWeight.w600, color: headerText,
                        )),
                      ),
                    ),
                  ],
                ),
              ),
              // Data rows
              Expanded(
                child: ListView.builder(
                  controller: _frozenVerticalController,
                  itemCount: _students.length,
                  itemBuilder: (context, i) {
                    final student = _students[i];
                    final bg = i % 2 == 0 ? cellBg : altCellBg;
                    return Container(
                      height: 40,
                      decoration: BoxDecoration(
                        color: bg,
                        border: Border(
                          bottom: BorderSide(color: borderColor, width: 0.5),
                          right: BorderSide(color: borderColor),
                        ),
                      ),
                      child: Row(
                        children: [
                          SizedBox(
                            width: numberW,
                            child: Center(
                              child: Text(
                                '${i + 1}',
                                style: TextStyle(
                                  fontSize: 12, color: cellTextColor,
                                ),
                              ),
                            ),
                          ),
                          Container(width: 0.5, color: borderColor),
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 8),
                              child: Text(
                                student['full_name']?.toString() ?? '',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                  color: cellTextColor,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),

        // Scrollable columns
        Expanded(
          child: Column(
            children: [
              // Header row
              SizedBox(
                height: 44,
                child: SingleChildScrollView(
                  controller: _horizontalController,
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: scrollableCols.map((col) {
                      return Container(
                        width: dataColW,
                        height: 44,
                        decoration: BoxDecoration(
                          color: headerBg,
                          border: Border(
                            bottom: BorderSide(color: borderColor),
                            right: BorderSide(color: borderColor.withAlpha(80)),
                          ),
                        ),
                        alignment: Alignment.center,
                        child: Text(
                          col['label']!,
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: headerText,
                          ),
                          textAlign: TextAlign.center,
                        ),
                      );
                    }).toList(),
                  ),
                ),
              ),
              // Data rows
              Expanded(
                child: ListView.builder(
                  controller: _verticalController,
                  itemCount: _students.length,
                  itemBuilder: (context, i) {
                    final student = _students[i];
                    final bg = i % 2 == 0 ? cellBg : altCellBg;

                    return SizedBox(
                      height: 40,
                      child: SingleChildScrollView(
                        controller: ScrollController(
                          initialScrollOffset: _horizontalController.hasClients
                              ? _horizontalController.offset
                              : 0,
                        ),
                        scrollDirection: Axis.horizontal,
                        child: Row(
                          children: scrollableCols.map((col) {
                            final val = student[col['key']];
                            final isAvg = col['key'] == 'average_grade';
                            final numVal = val is num ? val.toDouble() : 0.0;
                            String display;
                            if (val == null) {
                              display = '-';
                            } else if (isAvg && val is num) {
                              display = numVal % 1 == 0
                                  ? numVal.toInt().toString()
                                  : numVal.toStringAsFixed(1);
                            } else {
                              display = val.toString();
                            }

                            return Container(
                              width: dataColW,
                              height: 40,
                              decoration: BoxDecoration(
                                color: bg,
                                border: Border(
                                  bottom: BorderSide(color: borderColor, width: 0.5),
                                  right: BorderSide(color: borderColor.withAlpha(80)),
                                ),
                              ),
                              alignment: Alignment.center,
                              child: Text(
                                display,
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: isAvg ? FontWeight.bold : FontWeight.w500,
                                  color: isAvg && numVal > 0
                                      ? _gradeColor(numVal)
                                      : cellTextColor,
                                ),
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
