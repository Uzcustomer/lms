import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:file_picker/file_picker.dart';
import 'package:intl/intl.dart';
import 'package:table_calendar/table_calendar.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/student_provider.dart';
import '../../services/api_service.dart';

class AbsenceExcuseCreateScreen extends StatefulWidget {
  const AbsenceExcuseCreateScreen({super.key});

  @override
  State<AbsenceExcuseCreateScreen> createState() => _AbsenceExcuseCreateScreenState();
}

class _AbsenceExcuseCreateScreenState extends State<AbsenceExcuseCreateScreen> {
  final _formKey = GlobalKey<FormState>();
  final _docNumberController = TextEditingController();
  final _descriptionController = TextEditingController();

  String? _selectedReason;
  DateTime? _startDate;
  DateTime? _endDate;
  Uint8List? _fileBytes;
  String? _fileName;
  bool _isSubmitting = false;
  String? _submitError;

  List<dynamic> _reasons = [];
  Map<String, dynamic>? _selectedReasonData;

  List<dynamic> _missedAssessments = [];
  bool _isLoadingAssessments = false;
  bool _assessmentsLoaded = false;
  int _excuseDays = 0;
  final Map<int, Map<String, String>> _makeupSelections = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadReasons();
    });
  }

  Future<void> _loadReasons() async {
    final provider = context.read<StudentProvider>();
    await provider.loadExcuseReasons();
    if (mounted) {
      setState(() {
        _reasons = provider.excuseReasons ?? [];
      });
    }
  }

  Future<void> _pickDateRange() async {
    final now = DateTime.now();
    final result = await showModalBottomSheet<DateTimeRange>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _CalendarPicker(
        firstDate: now.subtract(const Duration(days: 40)),
        lastDate: now.add(const Duration(days: 30)),
        initialStart: _startDate,
        initialEnd: _endDate,
      ),
    );
    if (result != null && mounted) {
      final daysSinceEnd = DateTime.now().difference(result.end).inDays;
      if (daysSinceEnd > 10) {
        showDialog(
          context: context,
          builder: (ctx) => AlertDialog(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            icon: const Icon(Icons.warning_amber_rounded, color: AppTheme.errorColor, size: 48),
            title: const Text(
              'Muddat tugagan',
              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18),
            ),
            content: const Text(
              'Sababli ariza topshirish muddati tugagan. Ariza faqat 10 kun ichida topshirilishi kerak.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 14),
            ),
            actions: [
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => Navigator.pop(ctx),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text('Tushunarli'),
                ),
              ),
            ],
          ),
        );
        return;
      }
      setState(() {
        _startDate = result.start;
        _endDate = result.end;
        _missedAssessments = [];
        _assessmentsLoaded = false;
        _makeupSelections.clear();
      });
      _fetchMissedAssessments();
    }
  }

  Future<void> _fetchMissedAssessments() async {
    setState(() {
      _isLoadingAssessments = true;
      _missedAssessments = [];
      _assessmentsLoaded = false;
      _makeupSelections.clear();
    });

    try {
      final provider = context.read<StudentProvider>();
      final dateFormat = DateFormat('yyyy-MM-dd');
      final response = await provider.getMissedAssessments(
        dateFormat.format(_startDate!),
        dateFormat.format(_endDate!),
      );
      if (mounted) {
        final list = List<dynamic>.from(response['data'] as List<dynamic>? ?? []);
        const typeOrder = {'jn': 0, 'mt': 1, 'oski': 2, 'test': 3};
        list.sort((a, b) {
          final aType = (a as Map<String, dynamic>)['assessment_type'] as String? ?? '';
          final bType = (b as Map<String, dynamic>)['assessment_type'] as String? ?? '';
          return (typeOrder[aType] ?? 9).compareTo(typeOrder[bType] ?? 9);
        });
        setState(() {
          _missedAssessments = list;
          _excuseDays = response['excuse_days'] as int? ?? 15;
          _assessmentsLoaded = true;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _submitError = e.message);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _submitError = e.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _isLoadingAssessments = false);
      }
    }
  }

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg'],
      withData: true,
    );
    if (result != null && result.files.single.bytes != null) {
      setState(() {
        _fileBytes = result.files.single.bytes;
        _fileName = result.files.single.name;
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_startDate == null || _endDate == null) {
      setState(() => _submitError = 'Sanalarni tanlang');
      return;
    }
    if (_fileBytes == null) {
      setState(() => _submitError = 'Fayl yuklang');
      return;
    }
    if (_missedAssessments.isNotEmpty && !_allDatesSelected()) {
      final l = AppLocalizations.of(context);
      setState(() => _submitError = l.allDatesRequired);
      return;
    }

    setState(() {
      _isSubmitting = true;
      _submitError = null;
    });

    List<Map<String, dynamic>>? makeupDates;
    if (_missedAssessments.isNotEmpty) {
      makeupDates = [];
      for (int i = 0; i < _missedAssessments.length; i++) {
        final a = _missedAssessments[i] as Map<String, dynamic>;
        final sel = _makeupSelections[i] ?? {};
        makeupDates.add({
          'subject_name': a['subject_name'] ?? '',
          'subject_id': a['subject_id']?.toString() ?? '',
          'assessment_type': a['assessment_type'] ?? '',
          'assessment_type_code': a['assessment_type_code'] ?? '',
          'original_date': a['original_date'] ?? '',
          'makeup_date': sel['makeup_date'] ?? '',
          'makeup_start': sel['makeup_start'] ?? '',
          'makeup_end': sel['makeup_end'] ?? '',
        });
      }
    }

    try {
      final provider = context.read<StudentProvider>();
      await provider.submitExcuse(
        reason: _selectedReason!,
        docNumber: _docNumberController.text,
        startDate: DateFormat('yyyy-MM-dd').format(_startDate!),
        endDate: DateFormat('yyyy-MM-dd').format(_endDate!),
        description: _descriptionController.text.isNotEmpty ? _descriptionController.text : null,
        fileBytes: _fileBytes!,
        fileName: _fileName!,
        makeupDates: makeupDates,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(AppLocalizations.of(context).excuseSubmitted),
            backgroundColor: AppTheme.successColor,
          ),
        );
        Navigator.pop(context, true);
      }
    } on ApiException catch (e) {
      setState(() => _submitError = e.message);
    } catch (e) {
      setState(() => _submitError = e.toString());
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  String _getTypeLabel(String type) {
    switch (type) {
      case 'jn': return 'Joriy nazorat';
      case 'mt': return 'Mustaqil ta\'lim';
      case 'oski': return 'YN (OSKE)';
      case 'test': return 'YN (Test)';
      default: return type.toUpperCase();
    }
  }

  int _selectedCount() {
    int count = 0;
    for (int i = 0; i < _missedAssessments.length; i++) {
      final sel = _makeupSelections[i];
      if (sel == null) continue;
      if (sel['status'] == 'submitted' || sel['status'] == 'on_time') {
        count++;
        continue;
      }
      final type = (_missedAssessments[i] as Map<String, dynamic>)['assessment_type'];
      if (type == 'jn') {
        if ((sel['makeup_start'] ?? '').isNotEmpty && (sel['makeup_end'] ?? '').isNotEmpty) count++;
      } else {
        if ((sel['makeup_date'] ?? '').isNotEmpty) count++;
      }
    }
    return count;
  }

  bool _allDatesSelected() {
    if (_missedAssessments.isEmpty) return true;
    return _selectedCount() == _missedAssessments.length;
  }

  DateTime _calcMaxDate() {
    final now = DateTime.now();
    var maxDate = DateTime(now.year, now.month, now.day);
    int daysAdded = 0;
    while (daysAdded < _excuseDays) {
      maxDate = maxDate.add(const Duration(days: 1));
      if (maxDate.weekday != DateTime.sunday) daysAdded++;
    }
    return maxDate;
  }

  List<DateTimeRange> _getJnBlockedRanges() {
    final ranges = <DateTimeRange>[];
    for (int i = 0; i < _missedAssessments.length; i++) {
      final a = _missedAssessments[i] as Map<String, dynamic>;
      if (a['assessment_type'] != 'jn') continue;
      final sel = _makeupSelections[i];
      if (sel == null || sel['status'] != 'retake') continue;
      final start = sel['makeup_start'] ?? '';
      final end = sel['makeup_end'] ?? '';
      if (start.isNotEmpty && end.isNotEmpty) {
        ranges.add(DateTimeRange(
          start: DateTime.parse(start),
          end: DateTime.parse(end),
        ));
      }
    }
    return ranges;
  }

  Future<void> _pickMakeupDate(int index) async {
    final a = _missedAssessments[index] as Map<String, dynamic>;
    final type = a['assessment_type'] as String? ?? '';
    final jnRanges = (type != 'jn') ? _getJnBlockedRanges() : <DateTimeRange>[];

    final usedDates = <DateTime>[];
    DateTime? latestOski;

    if (type == 'oski' || type == 'test') {
      for (int i = 0; i < _missedAssessments.length; i++) {
        if (i == index) continue;
        final m = _missedAssessments[i] as Map<String, dynamic>;
        final mType = m['assessment_type'] as String? ?? '';
        if (mType != 'oski' && mType != 'test') continue;
        final sel = _makeupSelections[i];
        if (sel == null || sel['status'] != 'retake') continue;
        final d = sel['makeup_date'] ?? '';
        if (d.isEmpty) continue;
        final date = DateTime.parse(d);
        usedDates.add(DateTime(date.year, date.month, date.day));
        if (mType == 'oski' && (latestOski == null || date.isAfter(latestOski))) {
          latestOski = date;
        }
      }
    }

    final now = DateTime.now();
    final maxDate = _calcMaxDate();
    final picked = await showModalBottomSheet<DateTime>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _CalendarPicker(
        firstDate: now,
        lastDate: maxDate,
        isRange: false,
        excludeSundays: true,
        blockedRanges: jnRanges.isNotEmpty ? jnRanges : null,
        blockedDates: usedDates.isNotEmpty ? usedDates : null,
        minSelectableDate: (type == 'test' && latestOski != null)
            ? latestOski!.add(const Duration(days: 1))
            : null,
      ),
    );
    if (picked != null && mounted) {
      setState(() {
        _makeupSelections[index] = {
          'status': 'retake',
          'makeup_date': DateFormat('yyyy-MM-dd').format(picked),
        };
      });
    }
  }

  Future<void> _pickMakeupDateRange(int index) async {
    final now = DateTime.now();
    final maxDate = _calcMaxDate();
    final result = await showModalBottomSheet<DateTimeRange>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _CalendarPicker(
        firstDate: now,
        lastDate: maxDate,
        isRange: true,
        excludeSundays: true,
      ),
    );
    if (result != null && mounted) {
      setState(() {
        _makeupSelections[index] = {
          'status': 'retake',
          'makeup_start': DateFormat('yyyy-MM-dd').format(result.start),
          'makeup_end': DateFormat('yyyy-MM-dd').format(result.end),
        };
      });
    }
  }

  void _markSubmitted(int index) {
    setState(() {
      final a = _missedAssessments[index] as Map<String, dynamic>;
      final originalDate = a['original_date'] as String? ?? '';
      if (a['assessment_type'] == 'jn') {
        _makeupSelections[index] = {
          'status': 'submitted',
          'makeup_start': originalDate,
          'makeup_end': originalDate,
        };
      } else {
        _makeupSelections[index] = {
          'status': 'submitted',
          'makeup_date': originalDate,
        };
      }
    });
  }

  void _markOnTime(int index) {
    setState(() {
      final a = _missedAssessments[index] as Map<String, dynamic>;
      final originalDate = a['original_date'] as String? ?? '';
      _makeupSelections[index] = {
        'status': 'on_time',
        'makeup_date': originalDate,
      };
    });
  }

  Color _assessmentColor(String type) {
    switch (type) {
      case 'jn':
        return AppTheme.primaryColor;
      case 'mt':
        return Colors.orange;
      case 'oski':
        return Colors.purple;
      case 'test':
        return Colors.teal;
      default:
        return AppTheme.primaryColor;
    }
  }

  @override
  void dispose() {
    _docNumberController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkBackground : AppTheme.backgroundColor;
    final cardColor = isDark ? AppTheme.darkCard : AppTheme.surfaceColor;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final dateFormat = DateFormat('dd.MM.yyyy');

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        title: Text(l.newExcuse),
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Reason dropdown
            Container(
              decoration: BoxDecoration(
                color: cardColor,
                borderRadius: BorderRadius.circular(14),
              ),
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l.selectReason, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: textColor)),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    value: _selectedReason,
                    isExpanded: true,
                    decoration: InputDecoration(
                      filled: true,
                      fillColor: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    ),
                    hint: Text(l.selectReason, style: TextStyle(color: subColor)),
                    items: _reasons.map((r) {
                      final reason = r as Map<String, dynamic>;
                      return DropdownMenuItem<String>(
                        value: reason['key'] as String,
                        child: Text(reason['label'] as String, style: TextStyle(fontSize: 13, color: textColor)),
                      );
                    }).toList(),
                    onChanged: (val) {
                      setState(() {
                        _selectedReason = val;
                        _selectedReasonData = _reasons.firstWhere((r) => r['key'] == val) as Map<String, dynamic>?;
                      });
                    },
                    validator: (val) => val == null ? l.selectReason : null,
                  ),
                  if (_selectedReasonData != null) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppTheme.primaryColor.withAlpha(15),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: AppTheme.primaryColor.withAlpha(50)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Icon(Icons.info_outline, size: 16, color: AppTheme.primaryColor),
                              const SizedBox(width: 6),
                              Text(l.requiredDocument, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.primaryColor)),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            _selectedReasonData!['document'] ?? '',
                            style: TextStyle(fontSize: 12, color: textColor),
                          ),
                          if (_selectedReasonData!['max_days'] != null) ...[
                            const SizedBox(height: 6),
                            Text(
                              '${l.maxDays}: ${_selectedReasonData!['max_days']}',
                              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: subColor),
                            ),
                          ],
                          if (_selectedReasonData!['note'] != null) ...[
                            const SizedBox(height: 4),
                            Text(
                              _selectedReasonData!['note'],
                              style: TextStyle(fontSize: 11, color: subColor, fontStyle: FontStyle.italic),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),

            const SizedBox(height: 12),

            // Doc number
            Container(
              decoration: BoxDecoration(color: cardColor, borderRadius: BorderRadius.circular(14)),
              padding: const EdgeInsets.all(16),
              child: TextFormField(
                controller: _docNumberController,
                style: TextStyle(color: textColor),
                decoration: InputDecoration(
                  labelText: l.docNumber,
                  labelStyle: TextStyle(color: subColor),
                  prefixIcon: Icon(Icons.numbers, color: subColor),
                  filled: true,
                  fillColor: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                ),
                validator: (val) => val == null || val.isEmpty ? l.docNumber : null,
              ),
            ),

            const SizedBox(height: 12),

            // File picker
            Container(
              decoration: BoxDecoration(color: cardColor, borderRadius: BorderRadius.circular(14)),
              padding: const EdgeInsets.all(16),
              child: InkWell(
                onTap: _pickFile,
                borderRadius: BorderRadius.circular(12),
                child: Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    border: Border.all(
                      color: _fileName != null ? AppTheme.successColor : (isDark ? AppTheme.darkDivider : AppTheme.dividerColor),
                      width: _fileName != null ? 2 : 1,
                    ),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        _fileName != null ? Icons.check_circle : Icons.upload_file,
                        color: _fileName != null ? AppTheme.successColor : subColor,
                      ),
                      const SizedBox(width: 8),
                      Flexible(
                        child: Text(
                          _fileName ?? l.selectFile,
                          style: TextStyle(
                            color: _fileName != null ? textColor : subColor,
                            fontWeight: _fileName != null ? FontWeight.w500 : FontWeight.normal,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),

            const SizedBox(height: 12),

            // Date range
            InkWell(
              onTap: _pickDateRange,
              borderRadius: BorderRadius.circular(14),
              child: Container(
                decoration: BoxDecoration(color: cardColor, borderRadius: BorderRadius.circular(14)),
                padding: const EdgeInsets.all(16),
                child: _startDate != null && _endDate != null
                    ? Row(
                        children: [
                          Icon(Icons.calendar_today, size: 18, color: AppTheme.primaryColor),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              '${dateFormat.format(_startDate!)} — ${dateFormat.format(_endDate!)} ($_excuseDays kun)',
                              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: textColor),
                            ),
                          ),
                          GestureDetector(
                            onTap: () {
                              setState(() {
                                _startDate = null;
                                _endDate = null;
                                _missedAssessments = [];
                                _assessmentsLoaded = false;
                                _makeupSelections.clear();
                                _excuseDays = 0;
                              });
                            },
                            child: Text(l.clear, style: TextStyle(fontSize: 12, color: AppTheme.primaryColor)),
                          ),
                          const SizedBox(width: 8),
                          Icon(Icons.edit_calendar, size: 18, color: AppTheme.primaryColor),
                        ],
                      )
                    : Row(
                        children: [
                          Icon(Icons.calendar_today, size: 18, color: subColor),
                          const SizedBox(width: 10),
                          Text(
                            '${l.startDate} — ${l.endDate}',
                            style: TextStyle(fontSize: 14, color: subColor),
                          ),
                          const Spacer(),
                          Icon(Icons.edit_calendar, size: 18, color: subColor),
                        ],
                      ),
              ),
            ),

            // Missed assessments section
            if (_isLoadingAssessments) ...[
              const SizedBox(height: 12),
              Container(
                decoration: BoxDecoration(color: cardColor, borderRadius: BorderRadius.circular(14)),
                padding: const EdgeInsets.all(20),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: AppTheme.primaryColor,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Text(l.loadingAssessments, style: TextStyle(fontSize: 13, color: subColor)),
                  ],
                ),
              ),
            ],

            if (_assessmentsLoaded && _missedAssessments.isEmpty) ...[
              const SizedBox(height: 12),
              Container(
                decoration: BoxDecoration(
                  color: AppTheme.successColor.withAlpha(15),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppTheme.successColor.withAlpha(50)),
                ),
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Icon(Icons.check_circle_outline, size: 20, color: AppTheme.successColor),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        l.noMissedAssessments,
                        style: TextStyle(fontSize: 13, color: AppTheme.successColor),
                      ),
                    ),
                  ],
                ),
              ),
            ],

            if (_assessmentsLoaded && _missedAssessments.isNotEmpty) ...[
              const SizedBox(height: 12),
              _buildMissedAssessmentsSection(cardColor, textColor, subColor, l),
            ],

            const SizedBox(height: 12),

            // Description
            Container(
              decoration: BoxDecoration(color: cardColor, borderRadius: BorderRadius.circular(14)),
              padding: const EdgeInsets.all(16),
              child: TextFormField(
                controller: _descriptionController,
                style: TextStyle(color: textColor),
                maxLines: 3,
                maxLength: 1000,
                decoration: InputDecoration(
                  labelText: l.description,
                  labelStyle: TextStyle(color: subColor),
                  alignLabelWithHint: true,
                  filled: true,
                  fillColor: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                ),
              ),
            ),

            if (_submitError != null) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppTheme.errorColor.withAlpha(20),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.error_outline, color: AppTheme.errorColor, size: 20),
                    const SizedBox(width: 8),
                    Expanded(child: Text(_submitError!, style: const TextStyle(color: AppTheme.errorColor, fontSize: 13))),
                  ],
                ),
              ),
            ],

            const SizedBox(height: 20),

            // Submit button
            SizedBox(
              height: 52,
              child: ElevatedButton(
                onPressed: _isSubmitting ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primaryColor,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _isSubmitting
                    ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : Text(l.submitExcuse, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
              ),
            ),

            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }

  Widget _buildMissedAssessmentsSection(Color cardColor, Color textColor, Color subColor, AppLocalizations l) {
    final grouped = <String, List<MapEntry<int, Map<String, dynamic>>>>{};
    for (int i = 0; i < _missedAssessments.length; i++) {
      final assessment = _missedAssessments[i] as Map<String, dynamic>;
      final subject = assessment['subject_name'] as String? ?? '';
      grouped.putIfAbsent(subject, () => []);
      grouped[subject]!.add(MapEntry(i, assessment));
    }

    return Container(
      decoration: BoxDecoration(color: cardColor, borderRadius: BorderRadius.circular(14)),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.assignment_late, size: 20, color: AppTheme.warningColor),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l.missedAssessments,
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: textColor),
                ),
              ),
              Text(
                '${_selectedCount()}/${_missedAssessments.length}',
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppTheme.primaryColor),
              ),
              const SizedBox(width: 4),
              Text(l.selected, style: TextStyle(fontSize: 12, color: subColor)),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            l.selectMakeupDates,
            style: TextStyle(fontSize: 11, color: subColor, fontStyle: FontStyle.italic),
          ),
          const SizedBox(height: 12),
          ...grouped.entries.toList().asMap().entries.map((groupEntry) {
            final subjectIndex = groupEntry.key;
            final entry = groupEntry.value;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.only(bottom: 6, top: 4),
                  child: Row(
                    children: [
                      Container(
                        width: 22, height: 22,
                        decoration: BoxDecoration(color: AppTheme.primaryColor.withAlpha(20), borderRadius: BorderRadius.circular(6)),
                        child: Center(child: Text('${subjectIndex + 1}', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppTheme.primaryColor))),
                      ),
                      const SizedBox(width: 8),
                      Expanded(child: Text(entry.key, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: textColor))),
                    ],
                  ),
                ),
                ...entry.value.map((indexedAssessment) => _buildAssessmentCard(indexedAssessment.key, indexedAssessment.value, textColor, subColor, l)),
                const SizedBox(height: 8),
              ],
            );
          }),
        ],
      ),
    );
  }

  Widget _buildAssessmentCard(int index, Map<String, dynamic> assessment, Color textColor, Color subColor, AppLocalizations l) {
    final type = assessment['assessment_type'] as String? ?? '';
    final color = _assessmentColor(type);
    final originalDate = assessment['original_date'] as String? ?? '';
    final isFuture = assessment['is_future'] == true;
    final sel = _makeupSelections[index];
    final isJn = type == 'jn';
    final dateFormat = DateFormat('dd.MM.yyyy');
    final status = sel?['status'] ?? '';

    bool isSelected = false;
    String dateDisplay = '';
    if (sel != null) {
      if (status == 'submitted' || status == 'on_time') {
        isSelected = true;
      } else if (isJn) {
        final start = sel['makeup_start'] ?? '';
        final end = sel['makeup_end'] ?? '';
        if (start.isNotEmpty && end.isNotEmpty) {
          isSelected = true;
          dateDisplay = '${dateFormat.format(DateTime.parse(start))} — ${dateFormat.format(DateTime.parse(end))}';
        }
      } else {
        final d = sel['makeup_date'] ?? '';
        if (d.isNotEmpty) {
          isSelected = true;
          dateDisplay = dateFormat.format(DateTime.parse(d));
        }
      }
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withAlpha(10),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: isSelected ? AppTheme.successColor.withAlpha(80) : color.withAlpha(40)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: color.withAlpha(25), borderRadius: BorderRadius.circular(6)),
                child: Text(_getTypeLabel(type), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: color)),
              ),
              const SizedBox(width: 8),
              Text(originalDate, style: TextStyle(fontSize: 12, color: subColor)),
              if (isFuture) ...[
                const SizedBox(width: 6),
                Icon(Icons.info_outline, size: 13, color: AppTheme.warningColor),
              ],
            ],
          ),
          if (isFuture)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                'Joriy nazoratdan keyingi test kunlari',
                style: TextStyle(fontSize: 11, color: AppTheme.warningColor),
              ),
            ),
          const SizedBox(height: 8),
          if (isFuture)
            _buildFutureButtons(index, status, textColor, subColor, l)
          else
            _buildPastButtons(index, isJn, status, dateDisplay, textColor, subColor, l),
        ],
      ),
    );
  }

  Widget _buildPastButtons(int index, bool isJn, String status, String dateDisplay, Color textColor, Color subColor, AppLocalizations l) {
    return Row(
      children: [
        Expanded(
          child: _ActionChip(
            label: l.submitted,
            icon: Icons.check_circle,
            isActive: status == 'submitted',
            activeColor: AppTheme.successColor,
            onTap: () {
              if (status == 'submitted') {
                setState(() => _makeupSelections.remove(index));
              } else {
                _markSubmitted(index);
              }
            },
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: InkWell(
            onTap: () => isJn ? _pickMakeupDateRange(index) : _pickMakeupDate(index),
            borderRadius: BorderRadius.circular(8),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: BoxDecoration(
                color: status == 'retake' ? AppTheme.primaryColor.withAlpha(15) : Colors.transparent,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: status == 'retake' ? AppTheme.primaryColor.withAlpha(60) : subColor.withAlpha(40)),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.calendar_today, size: 14, color: status == 'retake' ? AppTheme.primaryColor : subColor),
                  const SizedBox(width: 4),
                  Flexible(
                    child: Text(
                      status == 'retake' ? dateDisplay : (isJn ? l.selectDateRange : l.selectDate),
                      style: TextStyle(fontSize: 11, color: status == 'retake' ? textColor : subColor),
                      overflow: TextOverflow.ellipsis,
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

  Widget _buildFutureButtons(int index, String status, Color textColor, Color subColor, AppLocalizations l) {
    return Row(
      children: [
        Expanded(
          child: _ActionChip(
            label: l.onTime,
            icon: Icons.access_time,
            isActive: status == 'on_time',
            activeColor: AppTheme.successColor,
            onTap: () {
              if (status == 'on_time') {
                setState(() => _makeupSelections.remove(index));
              } else {
                _markOnTime(index);
              }
            },
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _ActionChip(
            label: l.retake,
            icon: Icons.calendar_today,
            isActive: status == 'retake',
            activeColor: AppTheme.primaryColor,
            onTap: () => _pickMakeupDate(index),
          ),
        ),
      ],
    );
  }
}

class _ActionChip extends StatelessWidget {
  final String label;
  final IconData icon;
  final bool isActive;
  final Color activeColor;
  final VoidCallback onTap;

  const _ActionChip({
    required this.label,
    required this.icon,
    required this.isActive,
    required this.activeColor,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(
          color: isActive ? activeColor.withAlpha(15) : Colors.transparent,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: isActive ? activeColor.withAlpha(60) : subColor.withAlpha(40)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 14, color: isActive ? activeColor : subColor),
            const SizedBox(width: 4),
            Flexible(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: isActive ? FontWeight.w600 : FontWeight.normal,
                  color: isActive ? activeColor : subColor,
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CalendarPicker extends StatefulWidget {
  final DateTime firstDate;
  final DateTime lastDate;
  final DateTime? initialStart;
  final DateTime? initialEnd;
  final bool isRange;
  final bool excludeSundays;
  final List<DateTimeRange>? blockedRanges;
  final List<DateTime>? blockedDates;
  final DateTime? minSelectableDate;

  const _CalendarPicker({
    required this.firstDate,
    required this.lastDate,
    this.initialStart,
    this.initialEnd,
    this.isRange = true,
    this.excludeSundays = false,
    this.blockedRanges,
    this.blockedDates,
    this.minSelectableDate,
  });

  @override
  State<_CalendarPicker> createState() => _CalendarPickerState();
}

class _CalendarPickerState extends State<_CalendarPicker> {
  late DateTime _focusedDay;
  DateTime? _selectedDay;
  DateTime? _rangeStart;
  DateTime? _rangeEnd;

  @override
  void initState() {
    super.initState();
    if (widget.isRange) {
      _rangeStart = widget.initialStart;
      _rangeEnd = widget.initialEnd;
      _focusedDay = widget.initialStart ?? DateTime.now();
    } else {
      _focusedDay = DateTime.now();
    }
    if (_focusedDay.isBefore(widget.firstDate)) _focusedDay = widget.firstDate;
    if (_focusedDay.isAfter(widget.lastDate)) _focusedDay = widget.lastDate;
  }

  bool get _canConfirm {
    if (widget.isRange) return _rangeStart != null && _rangeEnd != null;
    return _selectedDay != null;
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Container(
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Center(
            child: Container(
              margin: const EdgeInsets.only(top: 12, bottom: 4),
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: subColor.withAlpha(80),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          TableCalendar(
            firstDay: widget.firstDate,
            lastDay: widget.lastDate,
            focusedDay: _focusedDay,
            startingDayOfWeek: StartingDayOfWeek.monday,
            rangeStartDay: widget.isRange ? _rangeStart : null,
            rangeEndDay: widget.isRange ? _rangeEnd : null,
            rangeSelectionMode: widget.isRange
                ? RangeSelectionMode.enforced
                : RangeSelectionMode.disabled,
            selectedDayPredicate: widget.isRange
                ? null
                : (day) => isSameDay(_selectedDay, day),
            enabledDayPredicate: (day) {
              if (widget.excludeSundays && day.weekday == DateTime.sunday) return false;
              final d = DateTime(day.year, day.month, day.day);
              if (widget.blockedRanges != null) {
                for (final range in widget.blockedRanges!) {
                  final s = DateTime(range.start.year, range.start.month, range.start.day);
                  final e = DateTime(range.end.year, range.end.month, range.end.day);
                  if (!d.isBefore(s) && !d.isAfter(e)) return false;
                }
              }
              if (widget.blockedDates != null) {
                for (final bd in widget.blockedDates!) {
                  if (d.year == bd.year && d.month == bd.month && d.day == bd.day) return false;
                }
              }
              if (widget.minSelectableDate != null && d.isBefore(widget.minSelectableDate!)) return false;
              return true;
            },
            onDaySelected: widget.isRange
                ? null
                : (selectedDay, focusedDay) {
                    setState(() {
                      _selectedDay = selectedDay;
                      _focusedDay = focusedDay;
                    });
                  },
            onRangeSelected: widget.isRange
                ? (start, end, focusedDay) {
                    setState(() {
                      _rangeStart = start;
                      _rangeEnd = end;
                      _focusedDay = focusedDay;
                    });
                  }
                : null,
            onPageChanged: (focusedDay) => _focusedDay = focusedDay,
            calendarBuilders: CalendarBuilders(
              disabledBuilder: widget.blockedRanges != null
                  ? (context, day, focusedDay) {
                      final d = DateTime(day.year, day.month, day.day);
                      for (final range in widget.blockedRanges!) {
                        final s = DateTime(range.start.year, range.start.month, range.start.day);
                        final e = DateTime(range.end.year, range.end.month, range.end.day);
                        if (!d.isBefore(s) && !d.isAfter(e)) {
                          return Container(
                            margin: const EdgeInsets.all(4),
                            decoration: BoxDecoration(
                              color: Colors.amber.withAlpha(40),
                              shape: BoxShape.circle,
                            ),
                            child: Center(
                              child: Text(
                                '${day.day}',
                                style: TextStyle(
                                  color: Colors.amber[800],
                                  fontSize: 14,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ),
                          );
                        }
                      }
                      return null;
                    }
                  : null,
            ),
            rowHeight: 42,
            daysOfWeekHeight: 28,
            calendarStyle: CalendarStyle(
              outsideDaysVisible: false,
              cellMargin: const EdgeInsets.all(4),
              rangeHighlightColor: AppTheme.primaryColor.withAlpha(30),
              rangeStartDecoration: const BoxDecoration(
                color: AppTheme.primaryColor,
                shape: BoxShape.circle,
              ),
              rangeEndDecoration: const BoxDecoration(
                color: AppTheme.primaryColor,
                shape: BoxShape.circle,
              ),
              withinRangeTextStyle: TextStyle(color: textColor),
              selectedDecoration: const BoxDecoration(
                color: AppTheme.primaryColor,
                shape: BoxShape.circle,
              ),
              todayDecoration: BoxDecoration(
                color: Colors.transparent,
                shape: BoxShape.circle,
                border: Border.all(color: AppTheme.primaryColor, width: 1.5),
              ),
              todayTextStyle: TextStyle(
                color: AppTheme.primaryColor,
                fontWeight: FontWeight.w600,
              ),
              defaultTextStyle: TextStyle(color: textColor, fontSize: 14),
              weekendTextStyle: TextStyle(color: subColor, fontSize: 14),
              disabledTextStyle: TextStyle(
                color: subColor.withAlpha(80),
                fontSize: 14,
              ),
            ),
            headerStyle: HeaderStyle(
              formatButtonVisible: false,
              titleCentered: true,
              titleTextStyle: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: textColor,
              ),
              leftChevronIcon: Icon(Icons.chevron_left, color: textColor, size: 24),
              rightChevronIcon: Icon(Icons.chevron_right, color: textColor, size: 24),
              headerPadding: const EdgeInsets.symmetric(vertical: 8),
            ),
            daysOfWeekStyle: DaysOfWeekStyle(
              weekdayStyle: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: subColor,
              ),
              weekendStyle: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: subColor.withAlpha(150),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: SafeArea(
              child: SizedBox(
                width: double.infinity,
                height: 46,
                child: ElevatedButton(
                  onPressed: _canConfirm ? _confirm : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryColor,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: AppTheme.primaryColor.withAlpha(60),
                    disabledForegroundColor: Colors.white54,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text(
                    'Tanlash',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _confirm() {
    if (widget.isRange && _rangeStart != null && _rangeEnd != null) {
      Navigator.pop(context, DateTimeRange(start: _rangeStart!, end: _rangeEnd!));
    } else if (!widget.isRange && _selectedDay != null) {
      Navigator.pop(context, _selectedDay);
    }
  }
}
