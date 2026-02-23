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
      final data = response['data'] is Map ? Map<String, dynamic>.from(response['data'] as Map) : <String, dynamic>{};

      final columnsRaw = data['columns'];
      final columns = columnsRaw is Map ? Map<String, dynamic>.from(columnsRaw) : <String, dynamic>{};
      final studentsRaw = (data['students'] is List) ? (data['students'] as List<dynamic>) : <dynamic>[];

      final openingsRaw = data['lesson_openings'];
      final openings = openingsRaw is Map ? Map<String, dynamic>.from(openingsRaw) : <String, dynamic>{};

      final groupRaw = data['group'];
      final groupMap = groupRaw is Map ? Map<String, dynamic>.from(groupRaw) : <String, dynamic>{};

      setState(() {
        _students = studentsRaw.map((e) => e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{}).toList();
        _amaliyColumns = _parseColumns(columns['amaliy']);
        _mtColumns = _parseColumns(columns['mt']);
        _lectureColumns = _parseColumns(columns['lecture']);
        _activeOpenedDates = (data['active_opened_dates'] is List)
            ? (data['active_opened_dates'] as List<dynamic>).map((e) => e.toString()).toList()
            : <String>[];
        _lessonOpenings = openings;
        _groupHemisId = groupMap['group_hemis_id']?.toString() ?? '';
        _minimumLimit = (data['minimum_limit'] is int) ? data['minimum_limit'] as int : 60;
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
    if (raw == null || raw is! List) return [];
    return raw.map((e) => e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{}).toList();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
      appBar: AppBar(
        title: Column(
          children: [
            Text(
              widget.subjectName,
              style: const TextStyle(fontSize: 14),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            Text(
              widget.groupName,
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w400),
            ),
          ],
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
            Tab(text: l.practicalClasses),
            Tab(text: l.lectures),
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
                    _LectureTab(
                      students: _students,
                      columns: _lectureColumns,
                      isDark: isDark,
                      l: l,
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

const double _numberW = 40;
const double _nameW = 180;
const double _frozenW = _numberW + _nameW;
const double _dateColW = 68;
const double _summaryColW = 60;
const double _rowH = 44;
const double _headerH = 52;

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

String _formatGrade(dynamic val) {
  if (val == null) return '-';
  if (val is num) {
    return val % 1 == 0 ? val.toInt().toString() : val.toStringAsFixed(1);
  }
  return val.toString();
}

// ==================== SYNCED TABLE WIDGET ====================

class _SyncedTable extends StatefulWidget {
  final List<Map<String, dynamic>> students;
  final bool isDark;
  final Widget Function(bool isDark) headerBuilder;
  final Widget Function(int index, Map<String, dynamic> student, bool isDark) rowBuilder;
  final double scrollableWidth;

  const _SyncedTable({
    required this.students,
    required this.isDark,
    required this.headerBuilder,
    required this.rowBuilder,
    required this.scrollableWidth,
  });

  @override
  State<_SyncedTable> createState() => _SyncedTableState();
}

class _SyncedTableState extends State<_SyncedTable> {
  final ScrollController _frozenVertical = ScrollController();
  final ScrollController _dataVertical = ScrollController();
  final ScrollController _horizontal = ScrollController();
  bool _syncingFrozen = false;
  bool _syncingData = false;

  @override
  void initState() {
    super.initState();
    _frozenVertical.addListener(_onFrozenScroll);
    _dataVertical.addListener(_onDataScroll);
  }

  void _onFrozenScroll() {
    if (_syncingFrozen) return;
    _syncingData = true;
    if (_dataVertical.hasClients) {
      _dataVertical.jumpTo(_frozenVertical.offset);
    }
    _syncingData = false;
  }

  void _onDataScroll() {
    if (_syncingData) return;
    _syncingFrozen = true;
    if (_frozenVertical.hasClients) {
      _frozenVertical.jumpTo(_dataVertical.offset);
    }
    _syncingFrozen = false;
  }

  @override
  void dispose() {
    _frozenVertical.removeListener(_onFrozenScroll);
    _dataVertical.removeListener(_onDataScroll);
    _frozenVertical.dispose();
    _dataVertical.dispose();
    _horizontal.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = widget.isDark;
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);
    final headerBg = isDark ? AppTheme.darkSurface : AppTheme.primaryColor;

    return Row(
      children: [
        // Frozen left columns (№ + Name)
        SizedBox(
          width: _frozenW,
          child: Column(
            children: [
              // Frozen header
              Container(
                height: _headerH,
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
                        child: Text('№',
                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Colors.white)),
                      ),
                    ),
                    Container(width: 0.5, color: Colors.white24),
                    const Expanded(
                      child: Padding(
                        padding: EdgeInsets.symmetric(horizontal: 8),
                        child: Text('F.I.Sh',
                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Colors.white)),
                      ),
                    ),
                  ],
                ),
              ),
              // Frozen rows
              Expanded(
                child: ListView.builder(
                  controller: _frozenVertical,
                  itemCount: widget.students.length,
                  itemBuilder: (_, i) {
                    final student = widget.students[i];
                    final cellBg = isDark
                        ? (i % 2 == 0 ? AppTheme.darkCard : AppTheme.darkSurface)
                        : (i % 2 == 0 ? Colors.white : const Color(0xFFF8F9FA));
                    final cellTextColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;

                    return Container(
                      height: _rowH,
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
                              child: Text('${i + 1}',
                                  style: TextStyle(fontSize: 12, color: cellTextColor, fontWeight: FontWeight.w500)),
                            ),
                          ),
                          Container(width: 0.5, color: borderColor),
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 8),
                              child: Text(
                                student['full_name']?.toString() ?? '',
                                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: cellTextColor),
                                maxLines: 2,
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
        // Scrollable right area (horizontal + vertical)
        Expanded(
          child: SingleChildScrollView(
            controller: _horizontal,
            scrollDirection: Axis.horizontal,
            child: SizedBox(
              width: widget.scrollableWidth,
              child: Column(
                children: [
                  // Scrollable header
                  SizedBox(
                    height: _headerH,
                    child: widget.headerBuilder(isDark),
                  ),
                  // Scrollable data rows
                  Expanded(
                    child: ListView.builder(
                      controller: _dataVertical,
                      itemCount: widget.students.length,
                      itemBuilder: (_, i) => SizedBox(
                        height: _rowH,
                        child: widget.rowBuilder(i, widget.students[i], isDark),
                      ),
                    ),
                  ),
                ],
              ),
            ),
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
  @override
  bool get wantKeepAlive => true;

  // Summary column labels: JB O'rt, ON, OSKI, Test, NB
  static const _summaryLabels = ['JB %', 'ON', 'OSKI', 'Test', 'NB'];

  bool _isDateOpened(String date) {
    return widget.activeOpenedDates.contains(date);
  }

  double get _totalScrollableWidth {
    return widget.columns.length * _dateColW + _summaryLabels.length * _summaryColW;
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
    if (widget.students.isEmpty || widget.columns.isEmpty) {
      return Center(child: Text(widget.l.noData));
    }

    return _SyncedTable(
      students: widget.students,
      isDark: widget.isDark,
      scrollableWidth: _totalScrollableWidth,
      headerBuilder: (isDark) => _buildHeader(isDark),
      rowBuilder: (i, student, isDark) => _buildRow(i, student, isDark),
    );
  }

  Widget _buildHeader(bool isDark) {
    final headerBg = isDark ? AppTheme.darkSurface : AppTheme.primaryColor;
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);
    final accentBg = isDark ? AppTheme.accentColor.withAlpha(80) : AppTheme.accentColor;

    return Row(
      children: [
        // Date columns
        ...widget.columns.map((col) {
          final date = col['date']?.toString() ?? '';
          final isOpened = _isDateOpened(date);
          return Container(
            width: _dateColW,
            height: _headerH,
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
                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white),
                ),
                const SizedBox(height: 1),
                Text(
                  col['pair']?.toString() ?? '',
                  style: const TextStyle(fontSize: 9, color: Colors.white70),
                ),
                if (isOpened)
                  Container(
                    margin: const EdgeInsets.only(top: 2),
                    width: 6,
                    height: 6,
                    decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                  ),
              ],
            ),
          );
        }),
        // Summary columns
        ..._summaryLabels.map((label) {
          return Container(
            width: _summaryColW,
            height: _headerH,
            decoration: BoxDecoration(
              color: accentBg,
              border: Border(
                bottom: BorderSide(color: borderColor),
                right: BorderSide(color: borderColor.withAlpha(80)),
              ),
            ),
            alignment: Alignment.center,
            child: Text(
              label,
              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white),
              textAlign: TextAlign.center,
            ),
          );
        }),
      ],
    );
  }

  Widget _buildRow(int index, Map<String, dynamic> student, bool isDark) {
    final amaliyData = (student['amaliy'] as List<dynamic>?) ?? [];
    final avg = student['amaliy_avg'];
    final on_ = student['on'];
    final oski = student['oski'];
    final test = student['test'];
    final absentCount = student['absent_count'];
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);
    final cellBg = isDark
        ? (index % 2 == 0 ? AppTheme.darkCard : AppTheme.darkSurface)
        : (index % 2 == 0 ? Colors.white : const Color(0xFFF8F9FA));
    final cellTextColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;

    return Row(
      children: [
        // Date columns
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
            display = _formatGrade(grade);
            textColor = _gradeColor(grade is num ? grade : 0);
          } else if (isAbsent) {
            display = 'NB';
            textColor = AppTheme.errorColor;
          }

          return GestureDetector(
            onTap: canInput ? () => _showGradeDialog(student, widget.columns[ci]) : null,
            child: Container(
              width: _dateColW,
              height: _rowH,
              decoration: BoxDecoration(
                color: canInput
                    ? (isDark ? AppTheme.successColor.withAlpha(15) : AppTheme.successColor.withAlpha(8))
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
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: textColor ?? cellTextColor,
                        decoration: isRetake ? TextDecoration.underline : null,
                      ),
                    )
                  : canInput
                      ? Icon(Icons.add_circle_outline, size: 16, color: AppTheme.successColor.withAlpha(150))
                      : null,
            ),
          );
        }),
        // Summary cells: JB %, ON, OSKI, Test, NB
        _buildSummaryCell(avg, cellBg, cellTextColor, borderColor, isGrade: true),
        _buildSummaryCell(on_, cellBg, cellTextColor, borderColor, isGrade: true),
        _buildSummaryCell(oski, cellBg, cellTextColor, borderColor, isGrade: true),
        _buildSummaryCell(test, cellBg, cellTextColor, borderColor, isGrade: true),
        _buildSummaryCell(absentCount, cellBg, cellTextColor, borderColor, isGrade: false),
      ],
    );
  }

  Widget _buildSummaryCell(dynamic value, Color cellBg, Color cellTextColor, Color borderColor, {bool isGrade = true}) {
    final display = _formatGrade(value);
    Color textColor = cellTextColor;
    if (isGrade && value is num && value > 0) {
      textColor = _gradeColor(value);
    } else if (!isGrade && value is num && value > 0) {
      textColor = AppTheme.errorColor;
    }

    return Container(
      width: _summaryColW,
      height: _rowH,
      decoration: BoxDecoration(
        color: cellBg,
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
          fontWeight: FontWeight.bold,
          color: textColor,
        ),
      ),
    );
  }
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
  @override
  bool get wantKeepAlive => true;

  // Summary: NB soni
  double get _totalScrollableWidth {
    return widget.columns.length * _dateColW + _summaryColW;
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    if (widget.students.isEmpty || widget.columns.isEmpty) {
      return Center(child: Text(widget.l.noData));
    }

    return _SyncedTable(
      students: widget.students,
      isDark: widget.isDark,
      scrollableWidth: _totalScrollableWidth,
      headerBuilder: (isDark) => _buildHeader(isDark),
      rowBuilder: (i, student, isDark) => _buildRow(i, student, isDark),
    );
  }

  Widget _buildHeader(bool isDark) {
    final headerBg = isDark ? AppTheme.darkSurface : AppTheme.primaryColor;
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);
    final accentBg = isDark ? AppTheme.accentColor.withAlpha(80) : AppTheme.accentColor;

    return Row(
      children: [
        ...widget.columns.map((col) {
          return Container(
            width: _dateColW,
            height: _headerH,
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
                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white),
                ),
                const SizedBox(height: 1),
                Text(
                  col['pair']?.toString() ?? '',
                  style: const TextStyle(fontSize: 9, color: Colors.white70),
                ),
              ],
            ),
          );
        }),
        // NB column
        Container(
          width: _summaryColW,
          height: _headerH,
          decoration: BoxDecoration(
            color: accentBg,
            border: Border(
              bottom: BorderSide(color: borderColor),
            ),
          ),
          alignment: Alignment.center,
          child: const Text(
            'NB',
            style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white),
          ),
        ),
      ],
    );
  }

  Widget _buildRow(int index, Map<String, dynamic> student, bool isDark) {
    final lectureData = (student['lecture'] as List<dynamic>?) ?? [];
    final absentCount = student['absent_count'];
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);
    final cellBg = isDark
        ? (index % 2 == 0 ? AppTheme.darkCard : AppTheme.darkSurface)
        : (index % 2 == 0 ? Colors.white : const Color(0xFFF8F9FA));
    final cellTextColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;

    return Row(
      children: [
        ...List.generate(widget.columns.length, (ci) {
          final att = ci < lectureData.length ? lectureData[ci] : null;
          final status = att?['status']?.toString();
          final isAbsent = status == 'NB';

          return Container(
            width: _dateColW,
            height: _rowH,
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
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: isAbsent
                    ? AppTheme.errorColor
                    : (status == '+' ? AppTheme.successColor : (isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary)),
              ),
            ),
          );
        }),
        // NB count cell
        Container(
          width: _summaryColW,
          height: _rowH,
          decoration: BoxDecoration(
            color: cellBg,
            border: Border(
              bottom: BorderSide(color: borderColor, width: 0.5),
            ),
          ),
          alignment: Alignment.center,
          child: Text(
            absentCount != null && absentCount != 0 ? absentCount.toString() : '-',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.bold,
              color: absentCount is num && absentCount > 0 ? AppTheme.errorColor : cellTextColor,
            ),
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
          statusText = widget.l.get('grade_saved');
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
