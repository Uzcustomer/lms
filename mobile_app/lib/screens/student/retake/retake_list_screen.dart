import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:intl/intl.dart';
import '../../../config/theme.dart';
import '../../../models/retake_models.dart';
import '../../../services/api_service.dart';
import '../../../services/retake_service.dart';

/// Talaba uchun qayta o'qish ariza ekrani.
///
/// Uchta blok:
///  1. Qabul oynasi paneli (faol/bo'ladi/yopilgan)
///  2. Akademik qarzdorliklar (checkbox bilan, max 3)
///  3. Mavjud arizalar (final_status va stage_description)
class RetakeListScreen extends StatefulWidget {
  const RetakeListScreen({super.key});

  @override
  State<RetakeListScreen> createState() => _RetakeListScreenState();
}

class _RetakeListScreenState extends State<RetakeListScreen> {
  static const int maxSubjects = 3;

  late final RetakeService _service;
  bool _loading = true;
  String? _error;

  RetakePeriod? _activePeriod;
  String _periodState = 'no_period';
  String? _periodMessage;
  List<DebtSubject> _debts = const [];
  List<RetakeApplication> _applications = const [];

  // Tanlangan: subject_id|semester_id => DebtSubject
  final Map<String, DebtSubject> _selected = {};

  @override
  void initState() {
    super.initState();
    _service = RetakeService(ApiService());
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final periodFuture = _service.getActivePeriod();
      final debtsFuture = _service.getDebts();
      final appsFuture = _service.listApplications();

      final period = await periodFuture;
      final debts = await debtsFuture;
      final apps = await appsFuture;

      if (!mounted) return;
      setState(() {
        _activePeriod = period.period;
        _periodState = period.state;
        _periodMessage = period.message;
        _debts = debts;
        _applications = apps;
        _selected.clear();
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  String _key(DebtSubject d) => '${d.subjectId}|${d.semesterId}';

  void _toggleDebt(DebtSubject d) {
    final k = _key(d);
    setState(() {
      if (_selected.containsKey(k)) {
        _selected.remove(k);
      } else {
        if (_selected.length >= maxSubjects) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Maksimal 3 ta fan tanlash mumkin')),
          );
          return;
        }
        _selected[k] = d;
      }
    });
  }

  double get _totalCredits => _selected.values.fold(0.0, (sum, d) => sum + d.credit);

  Future<void> _openSubmitSheet() async {
    if (_activePeriod == null || _selected.isEmpty) return;

    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _SubmitSheet(
        selected: _selected.values.toList(),
        totalCredits: _totalCredits,
        service: _service,
      ),
    );

    if (result == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ariza muvaffaqiyatli yuborildi'), backgroundColor: AppTheme.successColor),
      );
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        title: const Text("Qayta o'qish ariza", style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _ErrorView(message: _error!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(12, 12, 12, 100),
                    children: [
                      _PeriodCard(state: _periodState, period: _activePeriod, message: _periodMessage),
                      const SizedBox(height: 12),
                      if (_debts.isEmpty)
                        _EmptyDebtsCard()
                      else ...[
                        _DebtsHeader(maxSubjects: maxSubjects),
                        ..._buildDebtsList(),
                      ],
                      const SizedBox(height: 16),
                      if (_applications.isNotEmpty) _ApplicationsList(applications: _applications),
                    ],
                  ),
                ),
      bottomNavigationBar: _debts.isEmpty
          ? null
          : SafeArea(
              child: Container(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
                decoration: BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: Colors.grey.shade200)),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Tanlangan: ${_selected.length}/$maxSubjects fan',
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                          ),
                          Text(
                            'jami ${_totalCredits.toStringAsFixed(1)} kredit',
                            style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                          ),
                        ],
                      ),
                    ),
                    ElevatedButton(
                      onPressed: _selected.isEmpty || _activePeriod == null ? null : _openSubmitSheet,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primaryColor,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
                      ),
                      child: const Text('Ariza yuborish', style: TextStyle(fontWeight: FontWeight.w600)),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  List<Widget> _buildDebtsList() {
    final bySemester = <String, List<DebtSubject>>{};
    for (final d in _debts) {
      bySemester.putIfAbsent(d.semesterName ?? '—', () => []).add(d);
    }
    final keys = bySemester.keys.toList()..sort();

    final widgets = <Widget>[];
    for (final semester in keys) {
      widgets.add(Padding(
        padding: const EdgeInsets.fromLTRB(4, 12, 4, 6),
        child: Text(
          semester,
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.grey.shade700, letterSpacing: 0.5),
        ),
      ));
      for (final d in bySemester[semester]!) {
        widgets.add(_DebtTile(
          debt: d,
          isSelected: _selected.containsKey(_key(d)),
          canSelect: d.isEligibleForNew && (_selected.length < maxSubjects || _selected.containsKey(_key(d))),
          onTap: () => _toggleDebt(d),
        ));
      }
    }
    return widgets;
  }
}

class _PeriodCard extends StatelessWidget {
  final String state;
  final RetakePeriod? period;
  final String? message;

  const _PeriodCard({required this.state, required this.period, required this.message});

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat('dd.MM.yyyy');
    Color bg;
    Color fg;
    IconData icon;
    String text;

    switch (state) {
      case 'active':
        bg = const Color(0xFFE8F5E9);
        fg = const Color(0xFF2E7D32);
        icon = Icons.check_circle_outline;
        text = '${fmt.format(period!.startDate)} → ${fmt.format(period!.endDate)} (${period!.daysLeft} kun qoldi)';
        break;
      case 'upcoming':
        bg = const Color(0xFFE3F2FD);
        fg = const Color(0xFF1565C0);
        icon = Icons.schedule;
        text = 'Qabul oynasi ${fmt.format(period!.startDate)} kuni ochiladi.';
        break;
      case 'closed':
        bg = const Color(0xFFEEEEEE);
        fg = const Color(0xFF424242);
        icon = Icons.lock_outline;
        text = 'Qabul muddati tugagan (${fmt.format(period!.endDate)}).';
        break;
      default:
        bg = const Color(0xFFEEEEEE);
        fg = const Color(0xFF424242);
        icon = Icons.info_outline;
        text = message ?? "Sizning yo'nalishingiz va kursingiz uchun qabul oynasi hali ochilmagan.";
    }

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Icon(icon, color: fg, size: 22),
          const SizedBox(width: 10),
          Expanded(
            child: Text(text, style: TextStyle(fontSize: 13, color: fg, fontWeight: FontWeight.w500)),
          ),
        ],
      ),
    );
  }
}

class _DebtsHeader extends StatelessWidget {
  final int maxSubjects;
  const _DebtsHeader({required this.maxSubjects});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Akademik qarzdorliklar', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
          Text("Eng ko'pi $maxSubjects ta fan tanlay olasiz",
              style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
        ],
      ),
    );
  }
}

class _DebtTile extends StatelessWidget {
  final DebtSubject debt;
  final bool isSelected;
  final bool canSelect;
  final VoidCallback onTap;

  const _DebtTile({required this.debt, required this.isSelected, required this.canSelect, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat('dd.MM.yyyy');
    final disabled = !debt.isEligibleForNew || !canSelect;
    final isApproved = debt.applicationStatus == 'approved';
    final isRejected = debt.applicationStatus == 'rejected';

    final (badgeBg, badgeFg) = switch (debt.applicationStatus) {
      'eligible' => (const Color(0xFFE3F2FD), const Color(0xFF1565C0)),
      'approved' => (const Color(0xFFE8F5E9), const Color(0xFF2E7D32)),
      'rejected' => (const Color(0xFFFFEBEE), const Color(0xFFC62828)),
      'pending_academic_dept' => (const Color(0xFFFFF8E1), const Color(0xFF8D6E00)),
      _ => (const Color(0xFFFFFDE7), const Color(0xFF8D6E00)),
    };

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: isSelected ? AppTheme.primaryColor : Colors.transparent, width: isSelected ? 1.5 : 0),
      ),
      child: InkWell(
        onTap: disabled && !isSelected ? null : onTap,
        borderRadius: BorderRadius.circular(12),
        child: Opacity(
          opacity: disabled && !isSelected ? 0.55 : 1.0,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(8, 10, 12, 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Checkbox(
                  value: isSelected,
                  onChanged: disabled && !isSelected ? null : (_) => onTap(),
                  visualDensity: VisualDensity.compact,
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              debt.subjectName,
                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                            ),
                          ),
                          Text('${debt.credit.toStringAsFixed(1)} kr',
                              style: TextStyle(fontSize: 11, color: Colors.grey.shade600, fontWeight: FontWeight.w500)),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: badgeBg,
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          debt.statusLabel,
                          style: TextStyle(fontSize: 10, color: badgeFg, fontWeight: FontWeight.w600),
                        ),
                      ),
                      if (isApproved && debt.activeApplication?.retakeGroup != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          '${fmt.format(debt.activeApplication!.retakeGroup!.startDate)} → '
                          '${fmt.format(debt.activeApplication!.retakeGroup!.endDate)} — '
                          '${debt.activeApplication!.retakeGroup!.teacherName ?? "O'qituvchi"}',
                          style: TextStyle(fontSize: 10, color: Colors.green.shade700),
                        ),
                      ],
                      if (isRejected && debt.activeApplication?.rejectionReason != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          'Sabab: ${debt.activeApplication!.rejectionReason!}',
                          style: TextStyle(fontSize: 10, color: Colors.red.shade700),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _EmptyDebtsCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(vertical: 10),
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Column(
        children: [
          Icon(Icons.check_circle, size: 48, color: Colors.green.shade400),
          const SizedBox(height: 8),
          const Text('Sizda akademik qarzdorlik mavjud emas',
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600), textAlign: TextAlign.center),
          const SizedBox(height: 4),
          Text('Hamma fanlardan akkreditatsiya bahosi mavjud.',
              style: TextStyle(fontSize: 12, color: Colors.grey.shade600), textAlign: TextAlign.center),
        ],
      ),
    );
  }
}

class _ApplicationsList extends StatelessWidget {
  final List<RetakeApplication> applications;
  const _ApplicationsList({required this.applications});

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat('dd.MM.yyyy HH:mm');
    return Container(
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 6),
            child: const Text('Mening arizalarim', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
          ),
          ...applications.map((app) {
            Color dotColor;
            IconData icon;
            switch (app.finalStatus) {
              case 'approved':
                dotColor = Colors.green;
                icon = Icons.check_circle;
                break;
              case 'rejected':
                dotColor = Colors.red;
                icon = Icons.cancel;
                break;
              default:
                dotColor = Colors.amber.shade700;
                icon = Icons.schedule;
            }
            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(icon, size: 18, color: dotColor),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(app.subjectName, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                        Text(
                          '${app.semesterName ?? "—"} — ${app.credit.toStringAsFixed(1)} kr — ${app.submittedAt != null ? fmt.format(app.submittedAt!) : ""}',
                          style: TextStyle(fontSize: 10, color: Colors.grey.shade600),
                        ),
                        const SizedBox(height: 2),
                        Text(app.stageDescription, style: const TextStyle(fontSize: 11)),
                      ],
                    ),
                  ),
                ],
              ),
            );
          }),
          const SizedBox(height: 10),
        ],
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.error_outline, size: 48, color: Colors.red.shade400),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            ElevatedButton(onPressed: onRetry, child: const Text('Qayta urinish')),
          ],
        ),
      ),
    );
  }
}

// ── Submit bottom sheet ─────────────────────────────────────────

class _SubmitSheet extends StatefulWidget {
  final List<DebtSubject> selected;
  final double totalCredits;
  final RetakeService service;

  const _SubmitSheet({required this.selected, required this.totalCredits, required this.service});

  @override
  State<_SubmitSheet> createState() => _SubmitSheetState();
}

class _SubmitSheetState extends State<_SubmitSheet> {
  Uint8List? _receiptBytes;
  String? _receiptFileName;
  final _noteController = TextEditingController();
  bool _submitting = false;
  String? _error;

  Future<void> _pickReceipt() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png'],
      withData: true,
    );
    if (result == null || result.files.isEmpty) return;
    final file = result.files.first;
    if (file.size > 5 * 1024 * 1024) {
      setState(() => _error = 'Kvitansiya hajmi 5 MB dan oshmasligi kerak.');
      return;
    }
    setState(() {
      _receiptBytes = file.bytes;
      _receiptFileName = file.name;
      _error = null;
    });
  }

  Future<void> _submit() async {
    if (_receiptBytes == null || _receiptFileName == null) {
      setState(() => _error = 'Kvitansiya yuklash majburiy.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await widget.service.submit(
        subjects: widget.selected
            .map((d) => {'subject_id': d.subjectId, 'semester_id': d.semesterId})
            .toList(),
        receiptBytes: _receiptBytes!,
        receiptFileName: _receiptFileName!,
        studentNote: _noteController.text.trim().isEmpty ? null : _noteController.text.trim(),
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _submitting = false;
      });
    }
  }

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    return Container(
      padding: EdgeInsets.fromLTRB(16, 12, 16, bottom + 16),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)),
            ),
          ),
          const Text('Ariza yuborish', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),

          Text('TANLANGAN FANLAR', style: TextStyle(fontSize: 11, color: Colors.grey.shade600, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          ...widget.selected.map((d) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Text(
                  '• ${d.semesterName ?? "—"} — ${d.subjectName} (${d.credit.toStringAsFixed(1)} kr)',
                  style: const TextStyle(fontSize: 13),
                ),
              )),
          const SizedBox(height: 6),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 6),
            decoration: BoxDecoration(border: Border(top: BorderSide(color: Colors.grey.shade200))),
            child: Text('Jami: ${widget.totalCredits.toStringAsFixed(1)} kredit',
                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
          ),

          const SizedBox(height: 16),

          OutlinedButton.icon(
            onPressed: _submitting ? null : _pickReceipt,
            icon: const Icon(Icons.upload_file),
            label: Text(_receiptFileName ?? 'Kvitansiya tanlash (PDF/JPG/PNG)'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(44),
              alignment: Alignment.centerLeft,
              padding: const EdgeInsets.symmetric(horizontal: 12),
            ),
          ),
          if (_receiptBytes != null)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                '${(_receiptBytes!.length / 1024).toStringAsFixed(1)} KB',
                style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
              ),
            ),

          const SizedBox(height: 12),

          TextField(
            controller: _noteController,
            maxLength: 500,
            maxLines: 3,
            enabled: !_submitting,
            decoration: const InputDecoration(
              labelText: "Izoh (ixtiyoriy)",
              border: OutlineInputBorder(),
              hintText: "Qo'shimcha ma'lumot...",
            ),
          ),

          if (_error != null) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.red.shade200),
              ),
              child: Text(_error!, style: TextStyle(color: Colors.red.shade800, fontSize: 12)),
            ),
          ],

          const SizedBox(height: 12),

          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: _submitting ? null : () => Navigator.of(context).pop(false),
                  style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(46)),
                  child: const Text('Bekor qilish'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton(
                  onPressed: _submitting ? null : _submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryColor,
                    foregroundColor: Colors.white,
                    minimumSize: const Size.fromHeight(46),
                  ),
                  child: _submitting
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Yuborish', style: TextStyle(fontWeight: FontWeight.w600)),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
