import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../services/attendance_service.dart';
import '../../utils/page_transitions.dart';
import '../../widgets/clinic_header.dart';
import 'teacher_attendance_session_screen.dart';

/// "Davomat" tab: the teacher's lessons for a day with a "start attendance"
/// action per slot, or the live result if a session already exists.
class TeacherAttendanceScreen extends StatefulWidget {
  const TeacherAttendanceScreen({super.key});

  @override
  State<TeacherAttendanceScreen> createState() => _TeacherAttendanceScreenState();
}

class _TeacherAttendanceScreenState extends State<TeacherAttendanceScreen> {
  final _service = AttendanceService();
  DateTime _date = DateTime.now();
  List<dynamic> _lessons = const [];
  bool _loading = true;
  String? _error;
  String? _starting; // lesson key while the start call is in flight

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _service.teacherLessons(date: _date);
      _lessons = data['lessons'] as List<dynamic>? ?? const [];
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
    }
    if (mounted) setState(() => _loading = false);
  }

  void _shiftDate(int days) {
    setState(() => _date = _date.add(Duration(days: days)));
    _load();
  }

  Future<void> _start(Map<String, dynamic> lesson) async {
    final hasBeacon = lesson['has_beacon'] == true;
    final minutes = await showDialog<int>(
      context: context,
      builder: (ctx) => _StartDialog(lesson: lesson, hasBeacon: hasBeacon),
    );
    if (minutes == null || !mounted) return;

    final key = '${lesson['subject_id']}|${lesson['lesson_pair_code']}';
    setState(() => _starting = key);
    try {
      final data = await _service.startSession(
        subjectId: int.parse(lesson['subject_id'].toString()),
        lessonPairCode: lesson['lesson_pair_code'].toString(),
        date: _date,
        windowMinutes: minutes,
      );
      final session = data['session'] as Map<String, dynamic>?;
      if (!mounted || session == null) return;
      await _openSession(int.parse(session['id'].toString()));
    } on ApiException catch (e) {
      if (mounted) _snack(e.message);
    } catch (_) {
      if (mounted) _snack('Tarmoq xatoligi. Qayta urinib ko\'ring.');
    } finally {
      if (mounted) setState(() => _starting = null);
    }
  }

  Future<void> _openSession(int id) async {
    await Navigator.of(context).push(
      SlideFadePageRoute(builder: (_) => TeacherAttendanceSessionScreen(sessionId: id)),
    );
    _load();
  }

  void _snack(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  @override
  Widget build(BuildContext context) {
    final isToday = _isSameDay(_date, DateTime.now());
    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          const ClinicHeader(title: 'Davomat'),
          _dateBar(isToday),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(14, 6, 14, 30),
                      children: [
                        if (_error != null)
                          _empty(Icons.wifi_off_rounded, _error!)
                        else if (_lessons.isEmpty)
                          _empty(Icons.event_busy_outlined, 'Bu kunda jadvalda darsingiz yo\'q.')
                        else
                          ..._lessons.whereType<Map>().map((l) => _lessonCard(Map<String, dynamic>.from(l))),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _dateBar(bool isToday) {
    const weekdays = ['Dush', 'Sesh', 'Chor', 'Pay', 'Jum', 'Shan', 'Yak'];
    final label =
        '${weekdays[_date.weekday - 1]}, ${_date.day.toString().padLeft(2, '0')}.${_date.month.toString().padLeft(2, '0')}.${_date.year}';
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 4),
      child: Row(
        children: [
          IconButton(onPressed: () => _shiftDate(-1), icon: const Icon(Icons.chevron_left)),
          Expanded(
            child: Column(
              children: [
                Text(label,
                    style: TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 15, color: ClinicTheme.inkOf(context))),
                if (!isToday)
                  TextButton(
                    onPressed: () {
                      setState(() => _date = DateTime.now());
                      _load();
                    },
                    child: const Text('Bugunga qaytish'),
                  ),
              ],
            ),
          ),
          IconButton(onPressed: () => _shiftDate(1), icon: const Icon(Icons.chevron_right)),
        ],
      ),
    );
  }

  Widget _empty(IconData icon, String text) {
    return Padding(
      padding: const EdgeInsets.only(top: 60),
      child: Column(children: [
        Icon(icon, size: 52, color: ClinicTheme.faint),
        const SizedBox(height: 14),
        Text(text,
            textAlign: TextAlign.center,
            style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 13.5)),
      ]),
    );
  }

  Widget _lessonCard(Map<String, dynamic> l) {
    final session = l['session'] as Map<String, dynamic>?;
    final hasBeacon = l['has_beacon'] == true;
    final key = '${l['subject_id']}|${l['lesson_pair_code']}';
    final starting = _starting == key;
    final groups = (l['group_names'] as List<dynamic>? ?? const []).join(', ');
    final isOpen = session?['status'] == 'open';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
            color: isOpen ? ClinicTheme.teal : ClinicTheme.dividerOf(context),
            width: isOpen ? 1.5 : 1),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                decoration: BoxDecoration(
                  color: ClinicTheme.teal.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text('${l['start_time'] ?? ''}–${l['end_time'] ?? ''}',
                    style: const TextStyle(
                        color: ClinicTheme.teal, fontWeight: FontWeight.w800, fontSize: 12)),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(l['training_type_name']?.toString() ?? '',
                    style: TextStyle(fontSize: 11.5, color: ClinicTheme.mutedOf(context))),
              ),
              if (!hasBeacon)
                Tooltip(
                  message: 'Bu xona uchun beacon sozlanmagan',
                  child: Icon(Icons.bluetooth_disabled, size: 18, color: const Color(0xFFB45309)),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Text(l['subject_name']?.toString() ?? '',
              style: TextStyle(
                  fontSize: 15, fontWeight: FontWeight.w800, color: ClinicTheme.inkOf(context))),
          const SizedBox(height: 4),
          Text(
            '${l['auditorium_name'] ?? '—'} · $groups · ${l['students_count'] ?? 0} talaba',
            style: TextStyle(fontSize: 12.5, color: ClinicTheme.mutedOf(context)),
          ),
          const SizedBox(height: 12),
          if (session == null)
            SizedBox(
              width: double.infinity,
              height: 44,
              child: ElevatedButton.icon(
                onPressed: starting ? null : () => _start(l),
                style: ElevatedButton.styleFrom(
                  backgroundColor: ClinicTheme.blue,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: starting
                    ? const SizedBox(
                        width: 18, height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.play_arrow_rounded),
                label: const Text('Davomatni boshlash',
                    style: TextStyle(fontWeight: FontWeight.w800)),
              ),
            )
          else
            InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: () => _openSession(int.parse(session['id'].toString())),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                decoration: BoxDecoration(
                  color: (isOpen ? ClinicTheme.teal : ClinicTheme.green).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    Icon(isOpen ? Icons.timer_outlined : Icons.check_circle_outline,
                        color: isOpen ? ClinicTheme.teal : ClinicTheme.green, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        '${isOpen ? 'Ochiq' : 'Yopilgan'} · keldi ${session['present']} / ${session['total']}'
                        '${isOpen ? '' : ' · kelmadi ${session['absent']}'}',
                        style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                            color: ClinicTheme.inkOf(context)),
                      ),
                    ),
                    Icon(Icons.chevron_right, color: ClinicTheme.mutedOf(context)),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  static bool _isSameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;
}

class _StartDialog extends StatefulWidget {
  final Map<String, dynamic> lesson;
  final bool hasBeacon;
  const _StartDialog({required this.lesson, required this.hasBeacon});

  @override
  State<_StartDialog> createState() => _StartDialogState();
}

class _StartDialogState extends State<_StartDialog> {
  int _minutes = 10;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Davomatni boshlash'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.lesson['subject_name']?.toString() ?? '',
              style: const TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text('${widget.lesson['auditorium_name'] ?? '—'} · ${widget.lesson['students_count'] ?? 0} talaba',
              style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 12.5)),
          if (!widget.hasBeacon) ...[
            const SizedBox(height: 12),
            const Text(
              'Diqqat: bu xonada beacon sozlanmagan — talabalar tasdiqlay olmaydi, faqat qo\'lda belgilash mumkin.',
              style: TextStyle(color: Color(0xFFB45309), fontSize: 12.5),
            ),
          ],
          const SizedBox(height: 16),
          const Text('Tasdiqlash oynasi', style: TextStyle(fontSize: 12.5)),
          Row(
            children: [5, 10, 15, 20]
                .map((m) => Padding(
                      padding: const EdgeInsets.only(right: 6),
                      child: ChoiceChip(
                        label: Text('$m daq'),
                        selected: _minutes == m,
                        onSelected: (_) => setState(() => _minutes = m),
                      ),
                    ))
                .toList(),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Bekor qilish')),
        ElevatedButton(
          onPressed: () => Navigator.pop(context, _minutes),
          child: const Text('Boshlash'),
        ),
      ],
    );
  }
}
