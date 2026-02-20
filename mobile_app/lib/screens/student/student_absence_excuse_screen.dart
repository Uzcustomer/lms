import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:table_calendar/table_calendar.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../services/student_service.dart';
import '../../services/api_service.dart';
import '../../widgets/loading_widget.dart';

class StudentAbsenceExcuseScreen extends StatefulWidget {
  const StudentAbsenceExcuseScreen({super.key});

  @override
  State<StudentAbsenceExcuseScreen> createState() =>
      _StudentAbsenceExcuseScreenState();
}

class _StudentAbsenceExcuseScreenState
    extends State<StudentAbsenceExcuseScreen> {
  final StudentService _service = StudentService(ApiService());

  bool _isLoadingReasons = true;
  bool _isLoadingExcuses = true;
  bool _isSubmitting = false;
  String? _error;

  List<Map<String, dynamic>> _reasons = [];
  List<Map<String, dynamic>> _excuses = [];

  // Form state
  String? _selectedReasonKey;
  Map<String, dynamic>? _selectedReason;
  DateTime? _rangeStart;
  DateTime? _rangeEnd;
  DateTime _focusedDay = DateTime.now();
  String _description = '';
  PlatformFile? _selectedFile;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoadingReasons = true;
      _isLoadingExcuses = true;
      _error = null;
    });

    try {
      final results = await Future.wait([
        _service.getAbsenceExcuseReasons(),
        _service.getAbsenceExcuses(),
      ]);

      final reasonsData = results[0]['data'] as List<dynamic>? ?? [];
      final excusesData = results[1]['data'] as List<dynamic>? ?? [];

      setState(() {
        _reasons = reasonsData.cast<Map<String, dynamic>>();
        _excuses = excusesData.cast<Map<String, dynamic>>();
        _isLoadingReasons = false;
        _isLoadingExcuses = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoadingReasons = false;
        _isLoadingExcuses = false;
      });
    }
  }

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
      withData: true,
    );

    if (result != null && result.files.isNotEmpty) {
      setState(() {
        _selectedFile = result.files.first;
      });
    }
  }

  Future<void> _submitForm() async {
    final l = AppLocalizations.of(context);

    if (_selectedReasonKey == null) {
      _showError(l.absenceExcuseSelectReason);
      return;
    }
    if (_rangeStart == null || _rangeEnd == null) {
      _showError(l.absenceExcuseSelectDates);
      return;
    }
    if (_selectedFile == null || _selectedFile!.bytes == null) {
      _showError(l.absenceExcuseSelectFile);
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final startDate =
          '${_rangeStart!.year}-${_rangeStart!.month.toString().padLeft(2, '0')}-${_rangeStart!.day.toString().padLeft(2, '0')}';
      final endDate =
          '${_rangeEnd!.year}-${_rangeEnd!.month.toString().padLeft(2, '0')}-${_rangeEnd!.day.toString().padLeft(2, '0')}';

      await _service.storeAbsenceExcuse(
        reason: _selectedReasonKey!,
        startDate: startDate,
        endDate: endDate,
        description: _description.isNotEmpty ? _description : null,
        fileBytes: _selectedFile!.bytes!,
        fileName: _selectedFile!.name,
      );

      if (mounted) {
        setState(() => _isSubmitting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l.absenceExcuseSuccess),
            backgroundColor: AppTheme.successColor,
          ),
        );
        // Reset form & reload
        setState(() {
          _selectedReasonKey = null;
          _selectedReason = null;
          _rangeStart = null;
          _rangeEnd = null;
          _description = '';
          _selectedFile = null;
        });
        _loadData();
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isSubmitting = false);
        _showError(e.toString());
      }
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: AppTheme.errorColor,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
      appBar: AppBar(
        title: Text(l.absenceExcuseTitle),
        centerTitle: true,
      ),
      body: _isLoadingReasons
          ? const LoadingWidget()
          : _error != null && _reasons.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!,
                          style: TextStyle(
                              color: isDark
                                  ? AppTheme.darkTextSecondary
                                  : AppTheme.textSecondary)),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: _loadData,
                        child: Text(l.reload),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadData,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(12, 12, 12, 100),
                    children: [
                      // === CREATE FORM SECTION ===
                      _buildFormSection(context, isDark, l),
                      const SizedBox(height: 24),
                      // === MY APPLICATIONS LIST ===
                      _buildExcusesList(context, isDark, l),
                    ],
                  ),
                ),
    );
  }

  Widget _buildFormSection(
      BuildContext context, bool isDark, AppLocalizations l) {
    return Container(
      decoration: BoxDecoration(
        color: isDark ? AppTheme.darkCard : Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(isDark ? 30 : 10),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: AppTheme.primaryColor,
              borderRadius:
                  const BorderRadius.vertical(top: Radius.circular(16)),
            ),
            child: Row(
              children: [
                const Icon(Icons.add_circle_outline,
                    color: Colors.white, size: 22),
                const SizedBox(width: 10),
                Text(
                  l.absenceExcuseCreate,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Colors.white,
                  ),
                ),
              ],
            ),
          ),

          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Reason dropdown
                _buildReasonSelector(isDark, l),
                const SizedBox(height: 16),

                // Reason info card
                if (_selectedReason != null) ...[
                  _buildReasonInfoCard(isDark, l),
                  const SizedBox(height: 16),
                ],

                // Calendar with date range selection
                _buildCalendarSection(isDark, l),
                const SizedBox(height: 16),

                // Selected range display
                if (_rangeStart != null && _rangeEnd != null) ...[
                  _buildSelectedRangeCard(isDark, l),
                  const SizedBox(height: 16),
                ],

                // Description
                _buildDescriptionField(isDark, l),
                const SizedBox(height: 16),

                // File picker
                _buildFilePicker(isDark, l),
                const SizedBox(height: 20),

                // Submit button
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: _isSubmitting ? null : _submitForm,
                    icon: _isSubmitting
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child:
                                CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.send, size: 20),
                    label: Text(
                      _isSubmitting ? '...' : l.absenceExcuseSubmit,
                      style: const TextStyle(fontSize: 15),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primaryColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildReasonSelector(bool isDark, AppLocalizations l) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          l.absenceExcuseReason,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
          ),
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: isDark ? AppTheme.darkSurface : const Color(0xFFF5F5F5),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: isDark ? AppTheme.darkDivider : AppTheme.dividerColor,
            ),
          ),
          child: DropdownButtonFormField<String>(
            value: _selectedReasonKey,
            decoration: const InputDecoration(
              contentPadding:
                  EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              border: InputBorder.none,
            ),
            hint: Text(
              l.absenceExcuseSelectReason,
              style: TextStyle(
                color:
                    isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                fontSize: 14,
              ),
            ),
            isExpanded: true,
            icon: Icon(Icons.keyboard_arrow_down,
                color: isDark
                    ? AppTheme.darkTextSecondary
                    : AppTheme.textSecondary),
            dropdownColor: isDark ? AppTheme.darkCard : Colors.white,
            style: TextStyle(
              color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
              fontSize: 14,
            ),
            items: _reasons.map((reason) {
              return DropdownMenuItem<String>(
                value: reason['key'] as String,
                child: Text(
                  reason['label'] as String,
                  style: const TextStyle(fontSize: 13),
                  overflow: TextOverflow.ellipsis,
                  maxLines: 2,
                ),
              );
            }).toList(),
            onChanged: (value) {
              setState(() {
                _selectedReasonKey = value;
                _selectedReason =
                    _reasons.firstWhere((r) => r['key'] == value);
                // Reset dates if max_days changed
                _rangeStart = null;
                _rangeEnd = null;
              });
            },
          ),
        ),
      ],
    );
  }

  Widget _buildReasonInfoCard(bool isDark, AppLocalizations l) {
    final maxDays = _selectedReason!['max_days'];
    final document = _selectedReason!['document'] as String? ?? '';
    final note = _selectedReason!['note'] as String?;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppTheme.accentColor.withAlpha(isDark ? 30 : 15),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.accentColor.withAlpha(60)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (maxDays != null) ...[
            Row(
              children: [
                Icon(Icons.timer_outlined,
                    size: 16, color: AppTheme.accentColor),
                const SizedBox(width: 6),
                Text(
                  '${l.absenceExcuseMaxDays}: $maxDays ${l.days}',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: AppTheme.accentColor,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
          ],
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.description_outlined,
                  size: 16, color: AppTheme.accentColor),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  '${l.absenceExcuseRequiredDoc}: $document',
                  style: TextStyle(
                    fontSize: 12,
                    color: isDark
                        ? AppTheme.darkTextSecondary
                        : AppTheme.textSecondary,
                  ),
                ),
              ),
            ],
          ),
          if (note != null) ...[
            const SizedBox(height: 6),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.info_outline,
                    size: 16, color: AppTheme.warningColor),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    note,
                    style: TextStyle(
                      fontSize: 11,
                      color: AppTheme.warningColor,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildCalendarSection(bool isDark, AppLocalizations l) {
    final maxDays = _selectedReason?['max_days'] as int?;

    // Determine selectable date bounds:
    // Allow selecting dates up to 6 months in the past and 1 month in the future
    final now = DateTime.now();
    final firstDay = DateTime(now.year, now.month - 6, now.day);
    final lastDay = DateTime(now.year, now.month + 1, now.day);

    // Determine which days are "available" (weekdays, not Sundays)
    bool isAvailableDate(DateTime day) {
      return day.weekday != DateTime.sunday;
    }

    // Compute the max selectable end date based on reason's max_days
    DateTime? maxEndDate;
    if (_rangeStart != null && maxDays != null) {
      maxEndDate = _rangeStart!.add(Duration(days: maxDays - 1));
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              l.absenceExcuseSelectDates,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
              ),
            ),
            const Spacer(),
            // Legend
            Row(
              children: [
                Container(
                  width: 10,
                  height: 10,
                  decoration: const BoxDecoration(
                    color: Color(0xFF4CAF50),
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 4),
                Text(
                  l.absenceExcuseAvailableDates,
                  style: TextStyle(
                    fontSize: 10,
                    color: isDark
                        ? AppTheme.darkTextSecondary
                        : AppTheme.textSecondary,
                  ),
                ),
              ],
            ),
          ],
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: AppTheme.primaryColor,
            borderRadius: BorderRadius.circular(16),
          ),
          child: TableCalendar(
            firstDay: firstDay,
            lastDay: lastDay,
            focusedDay: _focusedDay,
            calendarFormat: CalendarFormat.month,
            startingDayOfWeek: StartingDayOfWeek.monday,
            rangeStartDay: _rangeStart,
            rangeEndDay: _rangeEnd,
            rangeSelectionMode: RangeSelectionMode.toggledOn,
            onRangeSelected: (start, end, focusedDay) {
              setState(() {
                _rangeStart = start;
                _focusedDay = focusedDay;

                if (start != null && end != null) {
                  // Enforce max_days limit
                  if (maxDays != null) {
                    final diff = end.difference(start).inDays + 1;
                    if (diff > maxDays) {
                      _rangeEnd =
                          start.add(Duration(days: maxDays - 1));
                    } else {
                      _rangeEnd = end;
                    }
                  } else {
                    _rangeEnd = end;
                  }
                } else {
                  _rangeEnd = end;
                }
              });
            },
            onPageChanged: (focusedDay) {
              _focusedDay = focusedDay;
            },
            enabledDayPredicate: (day) {
              // Disable Sundays
              return day.weekday != DateTime.sunday;
            },
            calendarBuilders: CalendarBuilders(
              defaultBuilder: (context, date, focusedDay) {
                final available = isAvailableDate(date);
                if (available) {
                  return Container(
                    margin: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: const Color(0xFF4CAF50).withAlpha(60),
                      shape: BoxShape.circle,
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      '${date.day}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  );
                }
                return null;
              },
              rangeStartBuilder: (context, date, focusedDay) {
                return Container(
                  margin: const EdgeInsets.all(4),
                  decoration: const BoxDecoration(
                    color: Color(0xFFFF9800),
                    shape: BoxShape.circle,
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    '${date.day}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                );
              },
              rangeEndBuilder: (context, date, focusedDay) {
                return Container(
                  margin: const EdgeInsets.all(4),
                  decoration: const BoxDecoration(
                    color: Color(0xFFFF9800),
                    shape: BoxShape.circle,
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    '${date.day}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                );
              },
              withinRangeBuilder: (context, date, focusedDay) {
                return Container(
                  margin: const EdgeInsets.all(4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFF9800).withAlpha(80),
                    shape: BoxShape.circle,
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    '${date.day}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                );
              },
            ),
            calendarStyle: CalendarStyle(
              outsideDaysVisible: false,
              todayDecoration: BoxDecoration(
                color: Colors.white.withAlpha(40),
                shape: BoxShape.circle,
              ),
              todayTextStyle: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.bold,
              ),
              rangeHighlightColor: const Color(0xFFFF9800).withAlpha(40),
              rangeStartDecoration: const BoxDecoration(
                color: Color(0xFFFF9800),
                shape: BoxShape.circle,
              ),
              rangeEndDecoration: const BoxDecoration(
                color: Color(0xFFFF9800),
                shape: BoxShape.circle,
              ),
              withinRangeTextStyle: const TextStyle(
                color: Colors.white,
              ),
              defaultTextStyle: const TextStyle(color: Colors.white),
              weekendTextStyle: TextStyle(
                color: Colors.white.withAlpha(150),
              ),
              disabledTextStyle: TextStyle(
                color: Colors.white.withAlpha(50),
              ),
              cellMargin: const EdgeInsets.all(4),
            ),
            headerStyle: const HeaderStyle(
              formatButtonVisible: false,
              titleCentered: true,
              leftChevronIcon: Icon(
                Icons.chevron_left,
                color: Colors.white,
              ),
              rightChevronIcon: Icon(
                Icons.chevron_right,
                color: Colors.white,
              ),
              titleTextStyle: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: Colors.white,
              ),
            ),
            daysOfWeekStyle: DaysOfWeekStyle(
              weekdayStyle: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: Colors.white.withAlpha(180),
              ),
              weekendStyle: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: Colors.white.withAlpha(130),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildSelectedRangeCard(bool isDark, AppLocalizations l) {
    final start = _rangeStart!;
    final end = _rangeEnd!;
    final days = end.difference(start).inDays + 1;

    String formatDate(DateTime d) =>
        '${d.day.toString().padLeft(2, '0')}.${d.month.toString().padLeft(2, '0')}.${d.year}';

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFF9800).withAlpha(isDark ? 30 : 15),
        borderRadius: BorderRadius.circular(12),
        border:
            Border.all(color: const Color(0xFFFF9800).withAlpha(60)),
      ),
      child: Row(
        children: [
          const Icon(Icons.date_range,
              size: 20, color: Color(0xFFFF9800)),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  l.absenceExcuseSelectedRange,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: isDark
                        ? AppTheme.darkTextPrimary
                        : AppTheme.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${formatDate(start)} - ${formatDate(end)} ($days ${l.days})',
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFFFF9800),
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.close, size: 18, color: Color(0xFFFF9800)),
            onPressed: () {
              setState(() {
                _rangeStart = null;
                _rangeEnd = null;
              });
            },
          ),
        ],
      ),
    );
  }

  Widget _buildDescriptionField(bool isDark, AppLocalizations l) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          l.absenceExcuseDescription,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
          ),
        ),
        const SizedBox(height: 8),
        TextField(
          maxLines: 3,
          maxLength: 1000,
          onChanged: (value) => _description = value,
          decoration: InputDecoration(
            hintText: l.absenceExcuseDescription,
            hintStyle: TextStyle(
              color:
                  isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
              fontSize: 13,
            ),
            filled: true,
            fillColor: isDark ? AppTheme.darkSurface : const Color(0xFFF5F5F5),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(
                color: isDark ? AppTheme.darkDivider : AppTheme.dividerColor,
              ),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(
                color: isDark ? AppTheme.darkDivider : AppTheme.dividerColor,
              ),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: AppTheme.primaryColor, width: 2),
            ),
            counterStyle: TextStyle(
              color:
                  isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
              fontSize: 11,
            ),
          ),
          style: TextStyle(
            color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
            fontSize: 14,
          ),
        ),
      ],
    );
  }

  Widget _buildFilePicker(bool isDark, AppLocalizations l) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          l.absenceExcuseFile,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
          ),
        ),
        const SizedBox(height: 8),
        InkWell(
          onTap: _pickFile,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 14),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.darkSurface : const Color(0xFFF5F5F5),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: isDark ? AppTheme.darkDivider : AppTheme.dividerColor,
                style: _selectedFile == null
                    ? BorderStyle.solid
                    : BorderStyle.solid,
              ),
            ),
            child: Row(
              children: [
                Icon(
                  _selectedFile != null
                      ? Icons.check_circle
                      : Icons.upload_file_outlined,
                  size: 22,
                  color: _selectedFile != null
                      ? AppTheme.successColor
                      : (isDark
                          ? AppTheme.darkTextSecondary
                          : AppTheme.textSecondary),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    _selectedFile?.name ?? l.absenceExcuseSelectFile,
                    style: TextStyle(
                      fontSize: 13,
                      color: _selectedFile != null
                          ? (isDark
                              ? AppTheme.darkTextPrimary
                              : AppTheme.textPrimary)
                          : (isDark
                              ? AppTheme.darkTextSecondary
                              : AppTheme.textSecondary),
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                if (_selectedFile != null)
                  GestureDetector(
                    onTap: () => setState(() => _selectedFile = null),
                    child: Icon(Icons.close, size: 18,
                        color: isDark
                            ? AppTheme.darkTextSecondary
                            : AppTheme.textSecondary),
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          'PDF, JPG, PNG, DOC, DOCX (max 10MB)',
          style: TextStyle(
            fontSize: 11,
            color:
                isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
          ),
        ),
      ],
    );
  }

  Widget _buildExcusesList(
      BuildContext context, bool isDark, AppLocalizations l) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
          child: Row(
            children: [
              Icon(Icons.history,
                  size: 20,
                  color: isDark
                      ? AppTheme.darkTextPrimary
                      : AppTheme.textPrimary),
              const SizedBox(width: 8),
              Text(
                l.absenceExcuseMyApplications,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: isDark
                      ? AppTheme.darkTextPrimary
                      : AppTheme.textPrimary,
                ),
              ),
            ],
          ),
        ),
        if (_isLoadingExcuses)
          const Padding(
            padding: EdgeInsets.all(32),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_excuses.isEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(32),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.darkCard : Colors.white,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              children: [
                Icon(Icons.inbox_outlined,
                    size: 48,
                    color: isDark
                        ? AppTheme.darkTextSecondary
                        : AppTheme.textSecondary),
                const SizedBox(height: 12),
                Text(
                  l.absenceExcuseNoApplications,
                  style: TextStyle(
                    color: isDark
                        ? AppTheme.darkTextSecondary
                        : AppTheme.textSecondary,
                  ),
                ),
              ],
            ),
          )
        else
          ...(_excuses.map((excuse) => _buildExcuseCard(excuse, isDark, l))),
      ],
    );
  }

  Widget _buildExcuseCard(
      Map<String, dynamic> excuse, bool isDark, AppLocalizations l) {
    final status = excuse['status'] as String? ?? 'pending';
    final statusLabel = excuse['status_label'] as String? ?? status;
    final reasonLabel = excuse['reason_label'] as String? ?? '';
    final startDate = excuse['start_date'] as String? ?? '';
    final endDate = excuse['end_date'] as String? ?? '';
    final rejectionReason = excuse['rejection_reason'] as String?;

    Color statusColor;
    IconData statusIcon;
    switch (status) {
      case 'approved':
        statusColor = AppTheme.successColor;
        statusIcon = Icons.check_circle;
        break;
      case 'rejected':
        statusColor = AppTheme.errorColor;
        statusIcon = Icons.cancel;
        break;
      default:
        statusColor = AppTheme.warningColor;
        statusIcon = Icons.hourglass_bottom;
    }

    String formatApiDate(String date) {
      try {
        final parts = date.split('-');
        return '${parts[2]}.${parts[1]}.${parts[0]}';
      } catch (_) {
        return date;
      }
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.darkCard : Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(isDark ? 20 : 8),
            blurRadius: 4,
            offset: const Offset(0, 1),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    reasonLabel,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: isDark
                          ? AppTheme.darkTextPrimary
                          : AppTheme.textPrimary,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: statusColor.withAlpha(isDark ? 40 : 20),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(statusIcon, size: 14, color: statusColor),
                      const SizedBox(width: 4),
                      Text(
                        statusLabel,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: statusColor,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(Icons.date_range,
                    size: 14,
                    color: isDark
                        ? AppTheme.darkTextSecondary
                        : AppTheme.textSecondary),
                const SizedBox(width: 4),
                Text(
                  '${formatApiDate(startDate)} - ${formatApiDate(endDate)}',
                  style: TextStyle(
                    fontSize: 12,
                    color: isDark
                        ? AppTheme.darkTextSecondary
                        : AppTheme.textSecondary,
                  ),
                ),
              ],
            ),
            if (rejectionReason != null && rejectionReason.isNotEmpty) ...[
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppTheme.errorColor.withAlpha(isDark ? 25 : 12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.info_outline,
                        size: 14, color: AppTheme.errorColor),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        rejectionReason,
                        style: TextStyle(
                          fontSize: 11,
                          color: AppTheme.errorColor,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
