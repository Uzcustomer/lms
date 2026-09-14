import 'dart:async';
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../services/attendance_service.dart';
import '../../widgets/clinic_header.dart';

/// Live view of one attendance session: counters, countdown, per-student
/// status with manual override, remind and close actions. Polls every 5 s
/// while the window is open.
class TeacherAttendanceSessionScreen extends StatefulWidget {
  final int sessionId;
  const TeacherAttendanceSessionScreen({super.key, required this.sessionId});

  @override
  State<TeacherAttendanceSessionScreen> createState() => _TeacherAttendanceSessionScreenState();
}

class _TeacherAttendanceSessionScreenState extends State<TeacherAttendanceSessionScreen> {
  final _service = AttendanceService();
  Map<String, dynamic>? _session;
  List<dynamic> _students = const [];
  bool _loading = true;
  bool _busy = false;
  String? _error;
  String _filter = 'all'; // all | present | absent | pending
  Timer? _poll;
  Timer? _tick;

  bool get _isOpen => _session?['status'] == 'open';

  @override
  void initState() {
    super.initState();
    _load();
    _poll = Timer.periodic(const Duration(seconds: 5), (_) {
      if (_isOpen) _load(silent: true);
    });
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (_isOpen && mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    _tick?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent) setState(() => _loading = true);
    try {
      _apply(await _service.session(widget.sessionId));
      _error = null;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error ??= 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
    }
    if (mounted) setState(() => _loading = false);
  }

  void _apply(Map<String, dynamic> data) {
    _session = data['session'] as Map<String, dynamic>?;
    _students = data['students'] as List<dynamic>? ?? const [];
  }

  Future<void> _run(Future<Map<String, dynamic>> Function() action, {String? success}) async {
    setState(() => _busy = true);
    try {
      _apply(await action());
      if (success != null && mounted) _snack(success);
    } on ApiException catch (e) {
      if (mounted) _snack(e.message, error: true);
    } catch (_) {
      if (mounted) _snack('Tarmoq xatoligi. Qayta urinib ko\'ring.', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _toggle(Map<String, dynamic> s) async {
    final next = s['status'] == 'present' ? 'absent' : 'present';
    await _run(() => _service.mark(widget.sessionId, int.parse(s['student_id'].toString()), next));
  }

  Future<void> _remind() => _run(
        () => _service.remind(widget.sessionId),
        success: 'Xonadagi talabalarga eslatma yuborildi.',
      );

  Future<void> _close() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Davomatni yopish'),
        content: const Text(
            'Tasdiqlamagan talabalar "kelmadi" deb belgilanadi. Keyin ham qo\'lda o\'zgartira olasiz. Davom etasizmi?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Bekor qilish')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Yopish')),
        ],
      ),
    );
    if (ok == true) await _run(() => _service.close(widget.sessionId), success: 'Davomat yopildi.');
  }

  void _snack(String msg, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: error ? const Color(0xFFBE123C) : null,
    ));
  }

  // ── UI ──────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final s = _session;
    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            title: s?['subject_name']?.toString() ?? 'Davomat',
            onBack: () => Navigator.of(context).pop(),
          ),
          Expanded(
            child: _loading && s == null
                ? const Center(child: CircularProgressIndicator())
                : s == null
                    ? Center(child: Text(_error ?? 'Sessiya topilmadi'))
                    : RefreshIndicator(
                        onRefresh: () => _load(silent: true),
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(14, 12, 14, 30),
                          children: [
                            _summaryCard(s),
                            const SizedBox(height: 10),
                            _filters(s),
                            const SizedBox(height: 6),
                            ..._filtered().map((st) => _studentRow(Map<String, dynamic>.from(st))),
                          ],
                        ),
                      ),
          ),
          if (s != null) _actionBar(),
        ],
      ),
    );
  }

  Widget _summaryCard(Map<String, dynamic> s) {
    final closesAt = DateTime.tryParse(s['closes_at']?.toString() ?? '')?.toLocal();
    final left = _isOpen && closesAt != null
        ? closesAt.difference(DateTime.now())
        : Duration.zero;
    final leftStr = left.isNegative
        ? '00:00'
        : '${left.inMinutes.toString().padLeft(2, '0')}:${(left.inSeconds % 60).toString().padLeft(2, '0')}';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: _isOpen
              ? const [Color(0xFF0D9488), Color(0xFF1E3A8A)]
              : const [Color(0xFF334155), Color(0xFF0F172A)],
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${s['auditorium_name'] ?? '—'} · ${s['lesson_pair_name'] ?? ''} · ${(s['group_names'] as List<dynamic>? ?? const []).join(', ')}',
            style: TextStyle(color: Colors.white.withOpacity(0.85), fontSize: 12.5),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _stat('Keldi', s['present'], Colors.white),
              _stat('Kutilmoqda', s['pending'], Colors.white70),
              _stat('Kelmadi', s['absent'], const Color(0xFFFCA5A5)),
              const Spacer(),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(_isOpen ? 'Qoldi' : 'Yopilgan',
                      style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 11)),
                  Text(_isOpen ? leftStr : '${s['present']}/${s['total']}',
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          fontFeatures: [FontFeature.tabularFigures()])),
                ],
              ),
            ],
          ),
          if (s['beacon'] == null) ...[
            const SizedBox(height: 10),
            Text('Bu xonada beacon yo\'q — talabalar tasdiqlay olmaydi, qo\'lda belgilang.',
                style: TextStyle(color: const Color(0xFFFDE68A), fontSize: 12)),
          ],
        ],
      ),
    );
  }

  Widget _stat(String label, dynamic value, Color color) {
    return Padding(
      padding: const EdgeInsets.only(right: 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('${value ?? 0}',
              style: TextStyle(color: color, fontSize: 22, fontWeight: FontWeight.w800)),
          Text(label, style: TextStyle(color: color.withOpacity(0.85), fontSize: 11)),
        ],
      ),
    );
  }

  Widget _filters(Map<String, dynamic> s) {
    final items = [
      ('all', 'Hammasi', s['total']),
      ('present', 'Keldi', s['present']),
      ('pending', 'Kutilmoqda', s['pending']),
      ('absent', 'Kelmadi', s['absent']),
    ];
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: items
            .map((it) => Padding(
                  padding: const EdgeInsets.only(right: 6),
                  child: ChoiceChip(
                    label: Text('${it.$2} (${it.$3 ?? 0})'),
                    selected: _filter == it.$1,
                    onSelected: (_) => setState(() => _filter = it.$1),
                  ),
                ))
            .toList(),
      ),
    );
  }

  Iterable<dynamic> _filtered() =>
      _filter == 'all' ? _students : _students.where((s) => s['status'] == _filter);

  Widget _studentRow(Map<String, dynamic> st) {
    final status = st['status']?.toString() ?? 'pending';
    final byTeacher = st['decided_by'] == 'teacher';
    final (color, icon, label) = switch (status) {
      'present' => (ClinicTheme.green, Icons.check_circle, 'Keldi'),
      'absent' => (const Color(0xFFBE123C), Icons.cancel, 'Kelmadi'),
      _ => (const Color(0xFFB45309), Icons.schedule, st['beacon_seen'] == true ? 'Xonada, tasdiqlamadi' : 'Kutilmoqda'),
    };

    return Container(
      margin: const EdgeInsets.only(top: 6),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: ClinicTheme.dividerOf(context)),
      ),
      child: ListTile(
        dense: true,
        leading: Icon(icon, color: color),
        title: Text(st['full_name']?.toString() ?? '',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, color: ClinicTheme.inkOf(context))),
        subtitle: Text(
          '${st['group_name'] ?? ''} · $label${byTeacher ? ' (qo\'lda)' : ''}',
          style: TextStyle(fontSize: 11.5, color: ClinicTheme.mutedOf(context)),
        ),
        trailing: _busy
            ? null
            : TextButton(
                onPressed: () => _toggle(st),
                child: Text(status == 'present' ? 'Kelmadi' : 'Keldi',
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
              ),
      ),
    );
  }

  Widget _actionBar() {
    if (!_isOpen) return const SizedBox.shrink();
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 8, 14, 12),
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: _busy ? null : _remind,
                icon: const Icon(Icons.notifications_active_outlined, size: 18),
                label: const Text('Eslatma'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: ElevatedButton.icon(
                onPressed: _busy ? null : _close,
                style: ElevatedButton.styleFrom(
                    backgroundColor: ClinicTheme.blue, foregroundColor: Colors.white),
                icon: const Icon(Icons.lock_outline, size: 18),
                label: const Text('Yopish'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
