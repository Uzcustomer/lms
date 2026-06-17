import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../services/api_service.dart';
import '../../services/student_service.dart';
import '../../widgets/clinic_header.dart';

class RetakeApplicationsScreen extends StatefulWidget {
  const RetakeApplicationsScreen({super.key});

  @override
  State<RetakeApplicationsScreen> createState() => _RetakeApplicationsScreenState();
}

class _RetakeApplicationsScreenState extends State<RetakeApplicationsScreen> {
  final _service = StudentService(ApiService());
  final _commentCtrl = TextEditingController();
  final _money = NumberFormat.decimalPattern('uz_UZ');

  Map<String, dynamic>? _data;
  final List<Map<String, dynamic>> _selected = [];
  Uint8List? _receiptBytes;
  String? _receiptFileName;
  bool _loading = true;
  bool _submitting = false;
  int? _uploadingPaymentGroupId;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _commentCtrl.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> get _debts => _listOfMaps(_data?['debts']);
  List<Map<String, dynamic>> get _history => _listOfMaps(_data?['history']);
  List<Map<String, dynamic>> get _awaitingPayment =>
      _listOfMaps(_data?['groups_awaiting_payment']);
  List<Map<String, dynamic>> get _paymentVerifying =>
      _listOfMaps(_data?['groups_payment_verifying']);
  Map<String, dynamic>? get _window => _data?['window'] is Map
      ? Map<String, dynamic>.from(_data!['window'] as Map)
      : null;
  Map<String, dynamic> get _settings => _data?['settings'] is Map
      ? Map<String, dynamic>.from(_data!['settings'] as Map)
      : <String, dynamic>{};

  int get _remainingSlots => (_settings['remaining_slots'] as num?)?.toInt() ?? 0;
  double get _creditPrice => (_settings['credit_price'] as num?)?.toDouble() ?? 0;
  double get _totalCredits => _selected.fold(
        0,
        (sum, subject) => sum + ((subject['credit'] as num?)?.toDouble() ?? 0),
      );
  double get _totalAmount => _totalCredits * _creditPrice;
  int get _selectedCurrentCount =>
      _selected.where((subject) => subject['is_current_semester'] == true).length;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await _service.getRetakeApplications();
      if (!mounted) return;
      setState(() {
        _data = Map<String, dynamic>.from(res['data'] as Map? ?? {});
        _selected.removeWhere((s) {
          final matching = _debts.where((d) =>
              d['subject_id']?.toString() == s['subject_id']?.toString() &&
              d['semester_id']?.toString() == s['semester_id']?.toString());
          return matching.isEmpty || matching.first['is_active'] == true;
        });
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Ma\'lumotlarni yuklashda xatolik');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> _listOfMaps(dynamic value) {
    return (value as List? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  bool _isSelected(Map<String, dynamic> debt) {
    return _selected.any((s) =>
        s['subject_id']?.toString() == debt['subject_id']?.toString() &&
        s['semester_id']?.toString() == debt['semester_id']?.toString());
  }

  bool _canSelect(Map<String, dynamic> debt) {
    if (_window?['is_open'] != true) return false;
    if (debt['is_active'] == true) return false;
    if (_isSelected(debt)) return true;
    if (debt['is_current_semester'] == true &&
        _selectedCurrentCount >= _remainingSlots) {
      return false;
    }
    return true;
  }

  void _toggleDebt(Map<String, dynamic> debt) {
    if (!_canSelect(debt)) return;
    setState(() {
      final index = _selected.indexWhere((s) =>
          s['subject_id']?.toString() == debt['subject_id']?.toString() &&
          s['semester_id']?.toString() == debt['semester_id']?.toString());
      if (index >= 0) {
        _selected.removeAt(index);
      } else {
        _selected.add(debt);
      }
    });
  }

  Future<void> _pickReceipt() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
      withData: true,
    );
    if (result?.files.single.bytes == null) return;
    setState(() {
      _receiptBytes = result!.files.single.bytes;
      _receiptFileName = result.files.single.name;
    });
  }

  Future<void> _submitApplication() async {
    if (_selected.isEmpty) return;
    if (_receiptBytes == null || _receiptFileName == null) {
      _showSnack('Dekanat tasdig\'idagi faylni yuklang', error: true);
      return;
    }

    setState(() => _submitting = true);
    try {
      await _service.submitRetakeApplication(
        subjects: _selected,
        receiptBytes: _receiptBytes!,
        receiptFileName: _receiptFileName!,
        comment: _commentCtrl.text,
      );
      if (!mounted) return;
      Navigator.pop(context);
      _showSnack('Ariza muvaffaqiyatli yuborildi');
      setState(() {
        _selected.clear();
        _receiptBytes = null;
        _receiptFileName = null;
        _commentCtrl.clear();
      });
      await _load();
    } on ApiException catch (e) {
      if (mounted) _showSnack(e.message, error: true);
    } catch (_) {
      if (mounted) _showSnack('Ariza yuborishda xatolik', error: true);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _pickAndUploadPayment(Map<String, dynamic> group) async {
    final groupId = (group['id'] as num?)?.toInt();
    if (groupId == null) return;

    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
      withData: true,
    );
    if (result?.files.single.bytes == null) return;

    setState(() => _uploadingPaymentGroupId = groupId);
    try {
      await _service.uploadRetakePayment(
        groupId: groupId,
        paymentBytes: result!.files.single.bytes!,
        paymentFileName: result.files.single.name,
      );
      if (!mounted) return;
      _showSnack('To\'lov cheki yuborildi');
      await _load();
    } on ApiException catch (e) {
      if (mounted) _showSnack(e.message, error: true);
    } catch (_) {
      if (mounted) _showSnack('To\'lov chekini yuborishda xatolik', error: true);
    } finally {
      if (mounted) setState(() => _uploadingPaymentGroupId = null);
    }
  }

  Future<void> _openUrl(String? url) async {
    if (url == null || url.isEmpty) return;
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  void _showSnack(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? const Color(0xFFBE123C) : ClinicTheme.green,
      ),
    );
  }

  String _moneyText(num? value) => '${_money.format((value ?? 0).round())} UZS';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: 'XIZMATLAR',
            title: 'Qayta o\'qish arizasi',
            onBack: () => Navigator.pop(context),
            actions: [
              ClinicIconButton(icon: Icons.refresh_rounded, onTap: _load),
            ],
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? _ErrorState(message: _error!, onRetry: _load)
                    : RefreshIndicator(
                        onRefresh: _load,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(14, 14, 14, 120),
                          children: [
                            _buildHero(),
                            const SizedBox(height: 12),
                            _buildWindowCard(),
                            const SizedBox(height: 12),
                            _buildPaymentAlerts(),
                            _buildDebtList(),
                            const SizedBox(height: 14),
                            _buildHistory(),
                          ],
                        ),
                      ),
          ),
        ],
      ),
      bottomNavigationBar: _selected.isEmpty ? null : _buildBottomBar(),
    );
  }

  Widget _buildHero() {
    return ShinySweep(
      radius: 20,
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF0F766E), Color(0xFF1E3A8A)],
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: Colors.white.withAlpha(35),
                borderRadius: BorderRadius.circular(16),
              ),
              child: const Icon(Icons.school_outlined, color: Colors.white, size: 28),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Qayta o\'qish',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Qarzdor fanlar uchun ariza yuboring va holatini kuzating',
                    style: TextStyle(color: Colors.white.withAlpha(225), fontSize: 12),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildWindowCard() {
    final window = _window;
    if (window == null) {
      return _InfoCard(
        icon: Icons.event_busy_outlined,
        title: 'Qabul oynasi ochilmagan',
        message: 'Sizning yo\'nalishingiz va kursingiz uchun ariza oynasi hali ochilmagan.',
        color: const Color(0xFFB45309),
      );
    }

    final isOpen = window['is_open'] == true;
    return _InfoCard(
      icon: isOpen ? Icons.event_available_outlined : Icons.event_busy_outlined,
      title: isOpen ? 'Ariza qabul ochiq' : 'Ariza qabul yopiq',
      message:
          '${window['semester_name'] ?? ''}\n${window['start_date'] ?? '-'} -> ${window['end_date'] ?? '-'}',
      color: isOpen ? ClinicTheme.green : const Color(0xFFB45309),
      trailing: isOpen
          ? '${_remainingSlots.toString()} slot'
          : (window['status']?.toString() ?? ''),
    );
  }

  Widget _buildPaymentAlerts() {
    final cards = <Widget>[];
    for (final group in _awaitingPayment) {
      final rejected = group['payment_verification_status'] == 'rejected';
      cards.add(_ActionCard(
        icon: Icons.receipt_long_outlined,
        title: rejected ? 'To\'lov cheki rad etildi' : 'To\'lov chekini yuklang',
        message: rejected
            ? (group['payment_rejection_reason']?.toString() ?? 'Qayta yuklash kerak')
            : 'Dekan va registrator tasdiqlagan. Jarayon davom etishi uchun to\'lov chekini yuboring.',
        color: const Color(0xFFB45309),
        buttonText: _uploadingPaymentGroupId == group['id'] ? 'Yuklanmoqda...' : 'Chek yuklash',
        onTap: _uploadingPaymentGroupId == null ? () => _pickAndUploadPayment(group) : null,
      ));
      cards.add(const SizedBox(height: 10));
    }

    for (final group in _paymentVerifying) {
      cards.add(_InfoCard(
        icon: Icons.hourglass_top_rounded,
        title: 'To\'lov cheki tekshirilmoqda',
        message:
            'Registrator ofisi chekingizni tekshirmoqda.\nYuklangan: ${group['payment_uploaded_at'] ?? '-'}',
        color: ClinicTheme.blue,
      ));
      cards.add(const SizedBox(height: 10));
    }

    return Column(children: cards);
  }

  Widget _buildDebtList() {
    final debts = _debts;
    final textColor = ClinicTheme.inkOf(context);
    final subColor = ClinicTheme.mutedOf(context);

    return _SectionCard(
      title: 'Qarzdor fanlar',
      subtitle: '${debts.length} ta fan',
      child: debts.isEmpty
          ? Padding(
              padding: const EdgeInsets.symmetric(vertical: 22),
              child: Column(
                children: [
                  const Icon(Icons.verified_rounded, color: ClinicTheme.green, size: 42),
                  const SizedBox(height: 8),
                  Text(
                    'Akademik qarzdorlik mavjud emas',
                    style: TextStyle(color: textColor, fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            )
          : Column(
              children: debts.map((debt) {
                final selected = _isSelected(debt);
                final canSelect = _canSelect(debt);
                final active = debt['is_active'] == true;
                return Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: canSelect ? () => _toggleDebt(debt) : null,
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: selected
                            ? ClinicTheme.teal.withAlpha(18)
                            : ClinicTheme.surfaceOf(context),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(
                          color: selected ? ClinicTheme.teal : ClinicTheme.dividerOf(context),
                          width: selected ? 1.4 : 1,
                        ),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 22,
                            height: 22,
                            margin: const EdgeInsets.only(top: 2),
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: selected ? ClinicTheme.teal : Colors.transparent,
                              border: Border.all(
                                color: selected
                                    ? ClinicTheme.teal
                                    : canSelect
                                        ? ClinicTheme.faint
                                        : ClinicTheme.dividerOf(context),
                                width: 1.5,
                              ),
                            ),
                            child: selected
                                ? const Icon(Icons.check, size: 15, color: Colors.white)
                                : null,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  debt['subject_name']?.toString() ?? '',
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w800,
                                    color: active ? subColor : textColor,
                                  ),
                                ),
                                const SizedBox(height: 5),
                                Wrap(
                                  spacing: 6,
                                  runSpacing: 5,
                                  children: [
                                    _MiniPill(text: debt['semester_name']?.toString() ?? '-'),
                                    _MiniPill(
                                      text:
                                          '${((debt['credit'] as num?)?.toDouble() ?? 0).toStringAsFixed(1)} kr',
                                    ),
                                    if (debt['grade'] != null)
                                      _MiniPill(text: 'Baho: ${debt['grade']}'),
                                  ],
                                ),
                                if (active) ...[
                                  const SizedBox(height: 6),
                                  Text(
                                    debt['active_status']?.toString() ?? 'Ariza mavjud',
                                    style: const TextStyle(
                                      fontSize: 11,
                                      color: Color(0xFFB45309),
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              }).toList(),
            ),
    );
  }

  Widget _buildHistory() {
    final history = _history;
    if (history.isEmpty) {
      return _SectionCard(
        title: 'Mening arizalarim',
        subtitle: 'Hali ariza yo\'q',
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 18),
          child: Text(
            'Ariza yuborganingizdan keyin uning holati shu yerda ko\'rinadi.',
            textAlign: TextAlign.center,
            style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 12),
          ),
        ),
      );
    }

    return _SectionCard(
      title: 'Mening arizalarim',
      subtitle: '${history.length} ta ariza',
      child: Column(
        children: history.map((group) => _HistoryCard(
              group: group,
              moneyText: _moneyText((group['receipt_amount'] as num?) ?? 0),
              onOpen: _openUrl,
            )).toList(),
      ),
    );
  }

  Widget _buildBottomBar() {
    return SafeArea(
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
        decoration: BoxDecoration(
          color: ClinicTheme.surfaceOf(context),
          border: Border(top: BorderSide(color: ClinicTheme.dividerOf(context))),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withAlpha(18),
              blurRadius: 16,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${_selected.length} fan, ${_totalCredits.toStringAsFixed(1)} kredit',
                    style: TextStyle(
                      fontSize: 13,
                      color: ClinicTheme.inkOf(context),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    _moneyText(_totalAmount),
                    style: const TextStyle(
                      fontSize: 12,
                      color: ClinicTheme.blue,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            ElevatedButton.icon(
              onPressed: _showSubmitSheet,
              icon: const Icon(Icons.send_rounded, size: 18),
              label: const Text('Yuborish'),
              style: ElevatedButton.styleFrom(
                backgroundColor: ClinicTheme.teal,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showSubmitSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, sheetSetState) {
            return Padding(
              padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
              child: Container(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
                decoration: BoxDecoration(
                  color: ClinicTheme.surfaceOf(context),
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        width: 42,
                        height: 4,
                        decoration: BoxDecoration(
                          color: ClinicTheme.dividerOf(context),
                          borderRadius: BorderRadius.circular(999),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Arizani yuborish',
                      style: TextStyle(
                        color: ClinicTheme.inkOf(context),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 10),
                    ..._selected.map((s) => Padding(
                          padding: const EdgeInsets.only(bottom: 6),
                          child: Row(
                            children: [
                              const Icon(Icons.check_circle, color: ClinicTheme.teal, size: 16),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  '${s['semester_name']} - ${s['subject_name']}',
                                  style: TextStyle(
                                    color: ClinicTheme.inkOf(context),
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                              Text(
                                '${((s['credit'] as num?)?.toDouble() ?? 0).toStringAsFixed(1)} kr',
                                style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11),
                              ),
                            ],
                          ),
                        )),
                    const SizedBox(height: 10),
                    _FilePickerTile(
                      fileName: _receiptFileName,
                      title: 'Dekanat tasdig\'idagi tushuntirish xati',
                      subtitle: 'PDF, DOC, DOCX, JPG, PNG',
                      onTap: () async {
                        await _pickReceipt();
                        sheetSetState(() {});
                      },
                      onClear: _receiptFileName == null
                          ? null
                          : () {
                              setState(() {
                                _receiptBytes = null;
                                _receiptFileName = null;
                              });
                              sheetSetState(() {});
                            },
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _commentCtrl,
                      maxLines: 3,
                      maxLength: 500,
                      decoration: InputDecoration(
                        hintText: 'Izoh (ixtiyoriy)',
                        filled: true,
                        fillColor: ClinicTheme.dividerOf(context).withAlpha(35),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: BorderSide.none,
                        ),
                        counterStyle: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 10),
                      ),
                    ),
                    const SizedBox(height: 10),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _submitting ? null : _submitApplication,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: ClinicTheme.teal,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        child: _submitting
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.4,
                                  valueColor: AlwaysStoppedAnimation(Colors.white),
                                ),
                              )
                            : const Text(
                                'Ariza yuborish',
                                style: TextStyle(fontWeight: FontWeight.w800),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _HistoryCard extends StatelessWidget {
  final Map<String, dynamic> group;
  final String moneyText;
  final Future<void> Function(String? url) onOpen;

  const _HistoryCard({
    required this.group,
    required this.moneyText,
    required this.onOpen,
  });

  @override
  Widget build(BuildContext context) {
    final apps = (group['applications'] as List? ?? const [])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
    final docxUrl = group['docx_url']?.toString();
    final certificateUrl = group['certificate_uz_url']?.toString();

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: ClinicTheme.dividerOf(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  '${apps.length} fan - $moneyText',
                  style: TextStyle(
                    color: ClinicTheme.inkOf(context),
                    fontWeight: FontWeight.w900,
                    fontSize: 13,
                  ),
                ),
              ),
              Text(
                group['created_at']?.toString() ?? '',
                style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 10),
              ),
            ],
          ),
          if (group['session_name'] != null) ...[
            const SizedBox(height: 3),
            Text(
              'Sessiya: ${group['session_name']}',
              style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11),
            ),
          ],
          const SizedBox(height: 10),
          ...apps.map((app) => _ApplicationRow(app: app)),
          if (docxUrl != null || certificateUrl != null) ...[
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              children: [
                if (docxUrl != null)
                  _LinkChip(text: 'DOCX', onTap: () => onOpen(docxUrl)),
                if (certificateUrl != null)
                  _LinkChip(text: 'Ruxsatnoma PDF', onTap: () => onOpen(certificateUrl)),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _ApplicationRow extends StatelessWidget {
  final Map<String, dynamic> app;

  const _ApplicationRow({required this.app});

  @override
  Widget build(BuildContext context) {
    final status = app['final_status']?.toString() ?? 'pending';
    final color = switch (status) {
      'approved' => ClinicTheme.green,
      'rejected' => const Color(0xFFBE123C),
      _ => const Color(0xFFB45309),
    };
    final retakeGroup = app['retake_group'] is Map
        ? Map<String, dynamic>.from(app['retake_group'] as Map)
        : null;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  '${app['semester_name'] ?? ''} - ${app['subject_name'] ?? ''}',
                  style: TextStyle(
                    color: ClinicTheme.inkOf(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              _StatusPill(text: app['display_status']?.toString() ?? status, color: color),
            ],
          ),
          if (retakeGroup != null) ...[
            const SizedBox(height: 4),
            Text(
              '${retakeGroup['name'] ?? 'Guruh'} - ${retakeGroup['teacher_name'] ?? 'O\'qituvchi'}',
              style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11),
            ),
            Text(
              '${retakeGroup['start_date'] ?? '-'} -> ${retakeGroup['end_date'] ?? '-'}',
              style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11),
            ),
          ],
          if (app['rejection_reason'] != null) ...[
            const SizedBox(height: 4),
            Text(
              'Sabab: ${app['rejection_reason']}',
              style: const TextStyle(color: Color(0xFFBE123C), fontSize: 11),
            ),
          ],
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final Widget child;

  const _SectionCard({
    required this.title,
    required this.subtitle,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: ClinicTheme.dividerOf(context)),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: TextStyle(
                    color: ClinicTheme.inkOf(context),
                    fontWeight: FontWeight.w900,
                    fontSize: 15,
                  ),
                ),
              ),
              Text(
                subtitle,
                style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11),
              ),
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final Color color;
  final String? trailing;

  const _InfoCard({
    required this.icon,
    required this.title,
    required this.message,
    required this.color,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withAlpha(14),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withAlpha(55)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 24),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: ClinicTheme.inkOf(context),
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  message,
                  style: TextStyle(
                    color: ClinicTheme.mutedOf(context),
                    fontSize: 11.5,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          if (trailing != null)
            _StatusPill(text: trailing!, color: color),
        ],
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final Color color;
  final String buttonText;
  final VoidCallback? onTap;

  const _ActionCard({
    required this.icon,
    required this.title,
    required this.message,
    required this.color,
    required this.buttonText,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withAlpha(14),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withAlpha(55)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: color, size: 24),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: TextStyle(
                    color: ClinicTheme.inkOf(context),
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            message,
            style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11.5, height: 1.35),
          ),
          const SizedBox(height: 10),
          Align(
            alignment: Alignment.centerRight,
            child: ElevatedButton(
              onPressed: onTap,
              style: ElevatedButton.styleFrom(
                backgroundColor: color,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: Text(buttonText),
            ),
          ),
        ],
      ),
    );
  }
}

class _FilePickerTile extends StatelessWidget {
  final String? fileName;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final VoidCallback? onClear;

  const _FilePickerTile({
    required this.fileName,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.onClear,
  });

  @override
  Widget build(BuildContext context) {
    final picked = fileName != null;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          color: ClinicTheme.dividerOf(context).withAlpha(35),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: picked ? ClinicTheme.teal : ClinicTheme.dividerOf(context),
          ),
        ),
        child: Row(
          children: [
            Icon(
              picked ? Icons.attach_file_rounded : Icons.upload_file_outlined,
              color: picked ? ClinicTheme.teal : ClinicTheme.mutedOf(context),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    picked ? fileName! : title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: ClinicTheme.inkOf(context),
                      fontWeight: FontWeight.w800,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 10.5),
                  ),
                ],
              ),
            ),
            if (onClear != null)
              IconButton(
                onPressed: onClear,
                icon: Icon(Icons.close_rounded, color: ClinicTheme.mutedOf(context), size: 18),
              ),
          ],
        ),
      ),
    );
  }
}

class _MiniPill extends StatelessWidget {
  final String text;

  const _MiniPill({required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: ClinicTheme.dividerOf(context).withAlpha(45),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: ClinicTheme.mutedOf(context),
          fontSize: 10,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  final String text;
  final Color color;

  const _StatusPill({required this.text, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 130),
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
      decoration: BoxDecoration(
        color: color.withAlpha(18),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        textAlign: TextAlign.center,
        style: TextStyle(color: color, fontSize: 9.5, fontWeight: FontWeight.w800),
      ),
    );
  }
}

class _LinkChip extends StatelessWidget {
  final String text;
  final VoidCallback onTap;

  const _LinkChip({required this.text, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return ActionChip(
      onPressed: onTap,
      avatar: const Icon(Icons.open_in_new_rounded, size: 15),
      label: Text(text),
      labelStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
      backgroundColor: ClinicTheme.blue.withAlpha(18),
      side: BorderSide(color: ClinicTheme.blue.withAlpha(35)),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off_rounded, color: Color(0xFFBE123C), size: 42),
            const SizedBox(height: 10),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 13),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Qayta yuklash'),
            ),
          ],
        ),
      ),
    );
  }
}
