import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:file_picker/file_picker.dart';
import 'package:intl/intl.dart';
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

  Future<void> _pickDate(bool isStart) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: isStart ? (_startDate ?? now) : (_endDate ?? _startDate ?? now),
      firstDate: now.subtract(const Duration(days: 40)),
      lastDate: now.add(const Duration(days: 30)),
      selectableDayPredicate: (date) => date.weekday != DateTime.sunday,
    );
    if (picked != null && mounted) {
      setState(() {
        if (isStart) {
          _startDate = picked;
          if (_endDate != null && _endDate!.isBefore(picked)) {
            _endDate = null;
            _missedAssessments = [];
            _assessmentsLoaded = false;
          }
        } else {
          _endDate = picked;
        }
      });

      if (_startDate != null && _endDate != null) {
        _fetchMissedAssessments();
      }
    }
  }

  Future<void> _fetchMissedAssessments() async {
    setState(() {
      _isLoadingAssessments = true;
      _missedAssessments = [];
      _assessmentsLoaded = false;
    });

    try {
      final provider = context.read<StudentProvider>();
      final dateFormat = DateFormat('yyyy-MM-dd');
      final assessments = await provider.getMissedAssessments(
        dateFormat.format(_startDate!),
        dateFormat.format(_endDate!),
      );
      if (mounted) {
        setState(() {
          _missedAssessments = assessments;
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

    setState(() {
      _isSubmitting = true;
      _submitError = null;
    });

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
            Container(
              decoration: BoxDecoration(color: cardColor, borderRadius: BorderRadius.circular(14)),
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Expanded(
                    child: _DateButton(
                      label: l.startDate,
                      value: _startDate != null ? dateFormat.format(_startDate!) : null,
                      onTap: () => _pickDate(true),
                      isDark: isDark,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _DateButton(
                      label: l.endDate,
                      value: _endDate != null ? dateFormat.format(_endDate!) : null,
                      onTap: () => _pickDate(false),
                      isDark: isDark,
                    ),
                  ),
                ],
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
    final grouped = <String, List<Map<String, dynamic>>>{};
    for (final item in _missedAssessments) {
      final assessment = item as Map<String, dynamic>;
      final subject = assessment['subject_name'] as String? ?? '';
      grouped.putIfAbsent(subject, () => []);
      grouped[subject]!.add(assessment);
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
                  '${l.missedAssessments} (${_missedAssessments.length})',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: textColor),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            'Ariza yuborilganda avtomatik qayd etiladi',
            style: TextStyle(fontSize: 11, color: subColor, fontStyle: FontStyle.italic),
          ),
          const SizedBox(height: 12),
          ...grouped.entries.map((entry) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Text(
                    entry.key,
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: textColor),
                  ),
                ),
                ...entry.value.map((assessment) => _buildAssessmentCard(assessment, textColor, subColor)),
                const SizedBox(height: 8),
              ],
            );
          }),
        ],
      ),
    );
  }

  Widget _buildAssessmentCard(Map<String, dynamic> assessment, Color textColor, Color subColor) {
    final type = assessment['assessment_type'] as String? ?? '';
    final typeLabel = type.toUpperCase();
    final color = _assessmentColor(type);
    final originalDate = assessment['original_date'] as String? ?? '';

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: color.withAlpha(10),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withAlpha(40)),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(
              color: color.withAlpha(25),
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              typeLabel,
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: color),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Row(
              children: [
                Icon(Icons.calendar_today, size: 13, color: subColor),
                const SizedBox(width: 4),
                Text(
                  originalDate,
                  style: TextStyle(fontSize: 12, color: subColor),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DateButton extends StatelessWidget {
  final String label;
  final String? value;
  final VoidCallback onTap;
  final bool isDark;

  const _DateButton({required this.label, this.value, required this.onTap, required this.isDark});

  @override
  Widget build(BuildContext context) {
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 12),
        decoration: BoxDecoration(
          color: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: TextStyle(fontSize: 11, color: subColor)),
            const SizedBox(height: 4),
            Row(
              children: [
                Icon(Icons.calendar_today, size: 16, color: value != null ? AppTheme.primaryColor : subColor),
                const SizedBox(width: 6),
                Text(
                  value ?? '—',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: value != null ? textColor : subColor,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
