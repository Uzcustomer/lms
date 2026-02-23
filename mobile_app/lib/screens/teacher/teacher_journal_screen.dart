import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../services/teacher_service.dart';
import '../../services/api_service.dart';
import '../../widgets/loading_widget.dart';

class TeacherJournalScreen extends StatefulWidget {
  final int groupId;
  final String groupName;
  final String subjectId;
  final String semesterCode;
  final String subjectName;

  const TeacherJournalScreen({
    super.key,
    required this.groupId,
    required this.groupName,
    required this.subjectId,
    required this.semesterCode,
    required this.subjectName,
  });

  @override
  State<TeacherJournalScreen> createState() => _TeacherJournalScreenState();
}

class _TeacherJournalScreenState extends State<TeacherJournalScreen>
    with SingleTickerProviderStateMixin {
  final TeacherService _service = TeacherService(ApiService());
  late TabController _tabController;

  bool _isLoading = true;
  String? _error;

  // Data from API
  Map<String, dynamic> _data = {};
  List<Map<String, dynamic>> _students = [];
  List<Map<String, dynamic>> _amaliyColumns = [];
  List<Map<String, dynamic>> _mtColumns = [];
  List<Map<String, dynamic>> _lectureColumns = [];
  List<String> _activeOpenedDates = [];
  Map<String, dynamic> _lessonOpenings = {};
  String _groupHemisId = '';
  int _minimumLimit = 60;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _loadData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final response = await _service.getJournal(
        groupId: widget.groupId,
        subjectId: widget.subjectId,
        semesterCode: widget.semesterCode,
      );
      final data = response['data'] as Map<String, dynamic>? ?? {};

      final columns = data['columns'] as Map<String, dynamic>? ?? {};
      final studentsRaw = (data['students'] as List<dynamic>?) ?? [];

      setState(() {
        _data = data;
        _students = studentsRaw.cast<Map<String, dynamic>>();
        _amaliyColumns = _parseColumns(columns['amaliy']);
        _mtColumns = _parseColumns(columns['mt']);
        _lectureColumns = _parseColumns(columns['lecture']);
        _activeOpenedDates = ((data['active_opened_dates'] as List<dynamic>?) ?? [])
            .map((e) => e.toString())
            .toList();
        _lessonOpenings = data['lesson_openings'] as Map<String, dynamic>? ?? {};
        _groupHemisId = data['group']?['group_hemis_id']?.toString() ?? '';
        _minimumLimit = data['minimum_limit'] as int? ?? 60;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  List<Map<String, dynamic>> _parseColumns(dynamic raw) {
    if (raw == null) return [];
    return (raw as List<dynamic>).map((e) => Map<String, dynamic>.from(e as Map)).toList();
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
          style: const TextStyle(fontSize: 14),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        centerTitle: true,
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.white,
          indicatorWeight: 3,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
          unselectedLabelStyle: const TextStyle(fontSize: 12),
          tabs: [
            Tab(text: l.lectures),
            Tab(text: l.practicalClasses),
            Tab(text: l.selfStudy),
          ],
        ),
      ),
      body: _isLoading
          ? const LoadingWidget()
          : _error != null
              ? _buildError(l, isDark)
              : TabBarView(
                  controller: _tabController,
                  children: [
                    _LectureTab(
                      students: _students,
                      columns: _lectureColumns,
                      isDark: isDark,
                      l: l,
                    ),
                    _AmaliyTab(
                      students: _students,
                      columns: _amaliyColumns,
                      activeOpenedDates: _activeOpenedDates,
                      lessonOpenings: _lessonOpenings,
                      groupHemisId: _groupHemisId,
                      subjectId: widget.subjectId,
                      semesterCode: widget.semesterCode,
                      service: _service,
                      isDark: isDark,
                      l: l,
                      onGradeSaved: _loadData,
                    ),
                    _MtTab(
                      students: _students,
                      columns: _mtColumns,
                      minimumLimit: _minimumLimit,
                      subjectId: widget.subjectId,
                      semesterCode: widget.semesterCode,
                      service: _service,
                      isDark: isDark,
                      l: l,
                      onGradeSaved: _loadData,
                    ),
                  ],
                ),
    );
  }

  Widget _buildError(AppLocalizations l, bool isDark) {
    return Center(
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
          ElevatedButton(onPressed: _loadData, child: Text(l.reload)),
        ],
      ),
    );
  }
}

// ==================== SHARED TABLE HELPERS ====================

const double _numberW = 36;
const double _nameW = 140;
const double _frozenW = _numberW + _nameW;
const double _colW = 56;

String _formatDate(String date) {
  if (date.length >= 10) {
    return '${date.substring(8, 10)}.${date.substring(5, 7)}';
  }
  return date;
}

Color _gradeColor(num val) {
  if (val >= 86) return AppTheme.successColor;
  if (val >= 71) return AppTheme.primaryColor;
  if (val >= 56) return AppTheme.warningColor;
  if (val > 0) return AppTheme.errorColor;
  return AppTheme.textSecondary;
}

Widget _buildFrozenHeader(bool isDark) {
  final headerBg = isDark ? AppTheme.darkSurface : AppTheme.primaryColor;
  final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);
  return Container(
    height: 48,
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
          width: _numberW,
          child: const Center(
            child: Text('№', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.white)),
          ),
        ),
        Container(width: 0.5, color: Colors.white24),
        const Expanded(
          child: Center(
            child: Text('F.I.Sh', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.white)),
          ),
        ),
      ],
    ),
  );
}

Widget _buildFrozenRow(int index, Map<String, dynamic> student, bool isDark) {
  final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);
  final cellBg = isDark
      ? (index % 2 == 0 ? AppTheme.darkCard : AppTheme.darkSurface)
      : (index % 2 == 0 ? Colors.white : const Color(0xFFFAFAFA));
  final cellTextColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;

  return Container(
    height: 40,
    decoration: BoxDecoration(
      color: cellBg,
      border: Border(
        bottom: BorderSide(color: borderColor, width: 0.5),
        right: BorderSide(color: borderColor),
      ),
    ),
    child: Row(
      children: [
        SizedBox(
          width: _numberW,
          child: Center(
            child: Text('${index + 1}', style: TextStyle(fontSize: 11, color: cellTextColor)),
          ),
        ),
        Container(width: 0.5, color: borderColor),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6),
            child: Text(
              student['full_name']?.toString() ?? '',
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.w500, color: cellTextColor),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ),
      ],
    ),
  );
}

// ==================== LECTURE TAB ====================

class _LectureTab extends StatefulWidget {
  final List<Map<String, dynamic>> students;
  final List<Map<String, dynamic>> columns;
  final bool isDark;
  final AppLocalizations l;

  const _LectureTab({
    required this.students,
    required this.columns,
    required this.isDark,
    required this.l,
  });

  @override
  State<_LectureTab> createState() => _LectureTabState();
}

class _LectureTabState extends State<_LectureTab> with AutomaticKeepAliveClientMixin {
  final ScrollController _horizontalController = ScrollController();
  final ScrollController _verticalController = ScrollController();
  final ScrollController _frozenVerticalController = ScrollController();

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _syncScroll();
  }

  void _syncScroll() {
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

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final isDark = widget.isDark;

    if (widget.students.isEmpty) {
      return Center(child: Text(widget.l.noData));
    }
    if (widget.columns.isEmpty) {
      return Center(child: Text(widget.l.noData));
    }

    final headerBg = isDark ? AppTheme.darkSurface : AppTheme.primaryColor;
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);

    return Row(
      children: [
        // Frozen columns
        SizedBox(
          width: _frozenW,
          child: Column(
            children: [
              _buildFrozenHeader(isDark),
              Expanded(
                child: ListView.builder(
                  controller: _frozenVerticalController,
                  itemCount: widget.students.length,
                  itemBuilder: (_, i) => _buildFrozenRow(i, widget.students[i], isDark),
                ),
              ),
            ],
          ),
        ),
        // Scrollable columns
        Expanded(
          child: Column(
            children: [
              // Header
              SizedBox(
                height: 48,
                child: SingleChildScrollView(
                  controller: _horizontalController,
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: widget.columns.map((col) {
                      return Container(
                        width: _colW,
                        height: 48,
                        decoration: BoxDecoration(
                          color: headerBg,
                          border: Border(
                            bottom: BorderSide(color: borderColor),
                            right: BorderSide(color: borderColor.withAlpha(80)),
                          ),
                        ),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              _formatDate(col['date']?.toString() ?? ''),
                              style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w600, color: Colors.white),
                            ),
                            Text(
                              col['pair']?.toString() ?? '',
                              style: const TextStyle(fontSize: 8, color: Colors.white70),
                            ),
                          ],
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
                  itemCount: widget.students.length,
                  itemBuilder: (_, i) {
                    final student = widget.students[i];
                    final lectureData = (student['lecture'] as List<dynamic>?) ?? [];
                    final cellBg = isDark
                        ? (i % 2 == 0 ? AppTheme.darkCard : AppTheme.darkSurface)
                        : (i % 2 == 0 ? Colors.white : const Color(0xFFFAFAFA));

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
                          children: List.generate(widget.columns.length, (ci) {
                            final att = ci < lectureData.length ? lectureData[ci] : null;
                            final status = att?['status']?.toString();
                            final isAbsent = status == 'NB';

                            return Container(
                              width: _colW,
                              height: 40,
                              decoration: BoxDecoration(
                                color: isAbsent
                                    ? AppTheme.errorColor.withAlpha(isDark ? 40 : 20)
                                    : cellBg,
                                border: Border(
                                  bottom: BorderSide(color: borderColor, width: 0.5),
                                  right: BorderSide(color: borderColor.withAlpha(80)),
                                ),
                              ),
                              alignment: Alignment.center,
                              child: Text(
                                status ?? '',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                  color: isAbsent
                                      ? AppTheme.errorColor
                                      : (status == '+' ? AppTheme.successColor : (isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary)),
                                ),
                              ),
                            );
                          }),
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

// ==================== AMALIY TAB ====================

class _AmaliyTab extends StatefulWidget {
  final List<Map<String, dynamic>> students;
  final List<Map<String, dynamic>> columns;
  final List<String> activeOpenedDates;
  final Map<String, dynamic> lessonOpenings;
  final String groupHemisId;
  final String subjectId;
  final String semesterCode;
  final TeacherService service;
  final bool isDark;
  final AppLocalizations l;
  final VoidCallback onGradeSaved;

  const _AmaliyTab({
    required this.students,
    required this.columns,
    required this.activeOpenedDates,
    required this.lessonOpenings,
    required this.groupHemisId,
    required this.subjectId,
    required this.semesterCode,
    required this.service,
    required this.isDark,
    required this.l,
    required this.onGradeSaved,
  });

  @override
  State<_AmaliyTab> createState() => _AmaliyTabState();
}

class _AmaliyTabState extends State<_AmaliyTab> with AutomaticKeepAliveClientMixin {
  final ScrollController _horizontalController = ScrollController();
  final ScrollController _verticalController = ScrollController();
  final ScrollController _frozenVerticalController = ScrollController();

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _syncScroll();
  }

  void _syncScroll() {
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

  bool _isDateOpened(String date) {
    return widget.activeOpenedDates.contains(date);
  }

  Future<void> _showGradeDialog(Map<String, dynamic> student, Map<String, dynamic> col) async {
    final date = col['date']?.toString() ?? '';
    final pair = col['pair']?.toString() ?? '';

    if (!_isDateOpened(date)) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(widget.l.lessonNotOpened), backgroundColor: AppTheme.warningColor),
      );
      return;
    }

    final controller = TextEditingController();
    final result = await showDialog<double>(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          title: Text(
            '${student['full_name']}',
            style: const TextStyle(fontSize: 14),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${_formatDate(date)} | ${widget.l.get('lesson_unit')}: $pair',
                style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: controller,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'^\d{0,3}\.?\d{0,2}'))],
                decoration: InputDecoration(
                  labelText: widget.l.enterGrade,
                  hintText: '0 - 100',
                  suffixText: '/ 100',
                ),
                autofocus: true,
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: Text(widget.l.cancel),
            ),
            ElevatedButton(
              onPressed: () {
                final val = double.tryParse(controller.text);
                if (val != null && val >= 0 && val <= 100) {
                  Navigator.pop(ctx, val);
                }
              },
              child: Text(widget.l.save),
            ),
          ],
        );
      },
    );

    if (result == null || !mounted) return;

    try {
      await widget.service.saveOpenedLessonGrade(
        studentHemisId: student['hemis_id']?.toString() ?? '',
        subjectId: widget.subjectId,
        semesterCode: widget.semesterCode,
        lessonDate: date,
        lessonPairCode: pair,
        grade: result,
        groupHemisId: widget.groupHemisId,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(widget.l.gradeSaved), backgroundColor: AppTheme.successColor),
      );
      widget.onGradeSaved();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppTheme.errorColor),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final isDark = widget.isDark;

    if (widget.students.isEmpty) {
      return Center(child: Text(widget.l.noData));
    }
    if (widget.columns.isEmpty) {
      return Center(child: Text(widget.l.noData));
    }

    final headerBg = isDark ? AppTheme.darkSurface : AppTheme.primaryColor;
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);

    // Add avg column at the end
    final colCount = widget.columns.length + 1;

    return Row(
      children: [
        SizedBox(
          width: _frozenW,
          child: Column(
            children: [
              _buildFrozenHeader(isDark),
              Expanded(
                child: ListView.builder(
                  controller: _frozenVerticalController,
                  itemCount: widget.students.length,
                  itemBuilder: (_, i) => _buildFrozenRow(i, widget.students[i], isDark),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: Column(
            children: [
              // Header
              SizedBox(
                height: 48,
                child: SingleChildScrollView(
                  controller: _horizontalController,
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      ...widget.columns.map((col) {
                        final date = col['date']?.toString() ?? '';
                        final isOpened = _isDateOpened(date);
                        return Container(
                          width: _colW,
                          height: 48,
                          decoration: BoxDecoration(
                            color: isOpened
                                ? (isDark ? AppTheme.successColor.withAlpha(60) : AppTheme.successColor.withAlpha(180))
                                : headerBg,
                            border: Border(
                              bottom: BorderSide(color: borderColor),
                              right: BorderSide(color: borderColor.withAlpha(80)),
                            ),
                          ),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                _formatDate(date),
                                style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w600, color: Colors.white),
                              ),
                              Text(
                                col['pair']?.toString() ?? '',
                                style: const TextStyle(fontSize: 8, color: Colors.white70),
                              ),
                              if (isOpened)
                                Container(
                                  margin: const EdgeInsets.only(top: 1),
                                  width: 6,
                                  height: 6,
                                  decoration: const BoxDecoration(
                                    color: Colors.white,
                                    shape: BoxShape.circle,
                                  ),
                                ),
                            ],
                          ),
                        );
                      }),
                      // Avg column header
                      Container(
                        width: _colW,
                        height: 48,
                        decoration: BoxDecoration(
                          color: isDark ? AppTheme.accentColor.withAlpha(80) : AppTheme.accentColor,
                          border: Border(
                            bottom: BorderSide(color: borderColor),
                          ),
                        ),
                        alignment: Alignment.center,
                        child: Text(
                          widget.l.average,
                          style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              // Data
              Expanded(
                child: ListView.builder(
                  controller: _verticalController,
                  itemCount: widget.students.length,
                  itemBuilder: (_, i) {
                    final student = widget.students[i];
                    final amaliyData = (student['amaliy'] as List<dynamic>?) ?? [];
                    final avg = student['amaliy_avg'];
                    final cellBg = isDark
                        ? (i % 2 == 0 ? AppTheme.darkCard : AppTheme.darkSurface)
                        : (i % 2 == 0 ? Colors.white : const Color(0xFFFAFAFA));
                    final cellTextColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;

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
                          children: [
                            ...List.generate(widget.columns.length, (ci) {
                              final item = ci < amaliyData.length ? amaliyData[ci] : null;
                              final grade = item?['grade'];
                              final hasGrade = item?['has_grade'] == true;
                              final isAbsent = item?['is_absent'] == true;
                              final isRetake = item?['is_retake'] == true;
                              final date = widget.columns[ci]['date']?.toString() ?? '';
                              final isOpened = _isDateOpened(date);
                              final canInput = isOpened && !hasGrade && !isAbsent;

                              String display = '';
                              Color? textColor;
                              if (hasGrade && grade != null) {
                                display = grade is num
                                    ? (grade % 1 == 0 ? grade.toInt().toString() : grade.toStringAsFixed(1))
                                    : grade.toString();
                                textColor = _gradeColor(grade is num ? grade : 0);
                              } else if (isAbsent) {
                                display = 'NB';
                                textColor = AppTheme.errorColor;
                              }

                              return GestureDetector(
                                onTap: canInput
                                    ? () => _showGradeDialog(student, widget.columns[ci])
                                    : null,
                                child: Container(
                                  width: _colW,
                                  height: 40,
                                  decoration: BoxDecoration(
                                    color: canInput
                                        ? (isDark
                                            ? AppTheme.successColor.withAlpha(15)
                                            : AppTheme.successColor.withAlpha(8))
                                        : cellBg,
                                    border: Border(
                                      bottom: BorderSide(color: borderColor, width: 0.5),
                                      right: BorderSide(color: borderColor.withAlpha(80)),
                                    ),
                                  ),
                                  alignment: Alignment.center,
                                  child: hasGrade || isAbsent
                                      ? Text(
                                          display,
                                          style: TextStyle(
                                            fontSize: 12,
                                            fontWeight: FontWeight.w600,
                                            color: textColor ?? cellTextColor,
                                            decoration: isRetake ? TextDecoration.underline : null,
                                          ),
                                        )
                                      : canInput
                                          ? Icon(Icons.add_circle_outline,
                                              size: 16,
                                              color: AppTheme.successColor.withAlpha(150))
                                          : null,
                                ),
                              );
                            }),
                            // Avg cell
                            Container(
                              width: _colW,
                              height: 40,
                              decoration: BoxDecoration(
                                color: cellBg,
                                border: Border(
                                  bottom: BorderSide(color: borderColor, width: 0.5),
                                ),
                              ),
                              alignment: Alignment.center,
                              child: Text(
                                avg != null
                                    ? (avg is num
                                        ? (avg % 1 == 0
                                            ? avg.toInt().toString()
                                            : (avg as num).toStringAsFixed(1))
                                        : avg.toString())
                                    : '-',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                  color: avg != null && avg is num ? _gradeColor(avg) : cellTextColor,
                                ),
                              ),
                            ),
                          ],
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

// ==================== MT TAB ====================

class _MtTab extends StatefulWidget {
  final List<Map<String, dynamic>> students;
  final List<Map<String, dynamic>> columns;
  final int minimumLimit;
  final String subjectId;
  final String semesterCode;
  final TeacherService service;
  final bool isDark;
  final AppLocalizations l;
  final VoidCallback onGradeSaved;

  const _MtTab({
    required this.students,
    required this.columns,
    required this.minimumLimit,
    required this.subjectId,
    required this.semesterCode,
    required this.service,
    required this.isDark,
    required this.l,
    required this.onGradeSaved,
  });

  @override
  State<_MtTab> createState() => _MtTabState();
}

class _MtTabState extends State<_MtTab> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  Future<void> _showMtGradeDialog(Map<String, dynamic> student, {bool regrade = false}) async {
    final controller = TextEditingController();
    final hasSubmission = student['mt_has_submission'] == true;

    if (!hasSubmission) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(widget.l.noFile), backgroundColor: AppTheme.warningColor),
      );
      return;
    }

    final result = await showDialog<double>(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          title: Text(
            '${student['full_name']}',
            style: const TextStyle(fontSize: 14),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (regrade)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  margin: const EdgeInsets.only(bottom: 8),
                  decoration: BoxDecoration(
                    color: AppTheme.warningColor.withAlpha(30),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    widget.l.regrade,
                    style: const TextStyle(fontSize: 11, color: AppTheme.warningColor, fontWeight: FontWeight.w600),
                  ),
                ),
              Text(
                widget.l.selfStudy,
                style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: controller,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'^\d{0,3}\.?\d{0,2}'))],
                decoration: InputDecoration(
                  labelText: widget.l.enterGrade,
                  hintText: '0 - 100',
                  suffixText: '/ 100',
                ),
                autofocus: true,
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: Text(widget.l.cancel),
            ),
            ElevatedButton(
              onPressed: () {
                final val = double.tryParse(controller.text);
                if (val != null && val >= 0 && val <= 100) {
                  Navigator.pop(ctx, val);
                }
              },
              child: Text(widget.l.save),
            ),
          ],
        );
      },
    );

    if (result == null || !mounted) return;

    try {
      await widget.service.saveMtGrade(
        studentHemisId: student['hemis_id']?.toString() ?? '',
        subjectId: widget.subjectId,
        semesterCode: widget.semesterCode,
        grade: result,
        regrade: regrade,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(widget.l.gradeSaved), backgroundColor: AppTheme.successColor),
      );
      widget.onGradeSaved();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppTheme.errorColor),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final isDark = widget.isDark;

    if (widget.students.isEmpty) {
      return Center(child: Text(widget.l.noData));
    }

    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: widget.students.length,
      itemBuilder: (_, i) {
        final student = widget.students[i];
        final mtGrade = student['mt_manual_grade'];
        final hasSubmission = student['mt_has_submission'] == true;
        final isLocked = student['mt_locked'] == true;
        final canRegrade = student['mt_can_regrade'] == true;
        final waitingResubmit = student['mt_waiting_resubmit'] == true;
        final mtHistory = (student['mt_history'] as List<dynamic>?) ?? [];

        // Status
        String statusText;
        Color statusColor;
        IconData statusIcon;
        if (isLocked) {
          statusText = widget.l.locked;
          statusColor = AppTheme.successColor;
          statusIcon = Icons.lock;
        } else if (canRegrade) {
          statusText = widget.l.regrade;
          statusColor = AppTheme.warningColor;
          statusIcon = Icons.refresh;
        } else if (waitingResubmit) {
          statusText = widget.l.waitingResubmit;
          statusColor = AppTheme.textSecondary;
          statusIcon = Icons.hourglass_empty;
        } else if (mtGrade != null) {
          statusText = '${widget.l.get('grade_saved')}';
          statusColor = AppTheme.warningColor;
          statusIcon = Icons.edit;
        } else if (hasSubmission) {
          statusText = widget.l.mtUploaded;
          statusColor = AppTheme.accentColor;
          statusIcon = Icons.file_present;
        } else {
          statusText = widget.l.noFile;
          statusColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
          statusIcon = Icons.file_upload_off;
        }

        final canGrade = hasSubmission && !isLocked && !waitingResubmit;

        return Card(
          margin: const EdgeInsets.only(bottom: 8),
          color: isDark ? AppTheme.darkCard : Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          child: InkWell(
            borderRadius: BorderRadius.circular(12),
            onTap: canGrade ? () => _showMtGradeDialog(student, regrade: canRegrade) : null,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Row 1: number + name + grade
                  Row(
                    children: [
                      Container(
                        width: 28,
                        height: 28,
                        decoration: BoxDecoration(
                          color: AppTheme.primaryColor.withAlpha(isDark ? 40 : 20),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Center(
                          child: Text(
                            '${i + 1}',
                            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppTheme.primaryColor),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          student['full_name']?.toString() ?? '',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 8),
                      // Grade badge
                      if (mtGrade != null)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: _gradeColor(mtGrade is num ? mtGrade : 0).withAlpha(isDark ? 50 : 25),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: _gradeColor(mtGrade is num ? mtGrade : 0).withAlpha(100),
                            ),
                          ),
                          child: Text(
                            mtGrade.toString(),
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              color: _gradeColor(mtGrade is num ? mtGrade : 0),
                            ),
                          ),
                        )
                      else if (canGrade)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: AppTheme.accentColor.withAlpha(isDark ? 30 : 15),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppTheme.accentColor.withAlpha(80)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.add, size: 14, color: AppTheme.accentColor),
                              const SizedBox(width: 2),
                              Text(
                                widget.l.enterGrade,
                                style: const TextStyle(fontSize: 11, color: AppTheme.accentColor, fontWeight: FontWeight.w600),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  // Row 2: status + history
                  Row(
                    children: [
                      Icon(statusIcon, size: 14, color: statusColor),
                      const SizedBox(width: 4),
                      Text(
                        statusText,
                        style: TextStyle(fontSize: 11, color: statusColor, fontWeight: FontWeight.w500),
                      ),
                      if (mtHistory.isNotEmpty) ...[
                        const SizedBox(width: 12),
                        ...mtHistory.map((h) {
                          final attempt = h['attempt'];
                          final grade = h['grade'];
                          return Container(
                            margin: const EdgeInsets.only(right: 4),
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: (isDark ? AppTheme.darkSurface : const Color(0xFFF0F0F0)),
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              '#$attempt: $grade',
                              style: TextStyle(
                                fontSize: 10,
                                color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                              ),
                            ),
                          );
                        }),
                      ],
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
