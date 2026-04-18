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
          }
        } else {
          _endDate = picked;
        }
      });
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
