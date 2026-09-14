import 'package:flutter/material.dart';
import '../screens/student/attendance_confirm_screen.dart';
import '../services/attendance_service.dart';
import '../utils/page_transitions.dart';
import 'clinic_header.dart';

/// Dashboard strip shown while a teacher has an attendance window open for
/// the student's group. Hidden otherwise.
class AttendanceBanner extends StatelessWidget {
  const AttendanceBanner({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<List<PendingAttendance>>(
      valueListenable: AttendanceWatcher.pending,
      builder: (context, pending, _) {
        if (pending.isEmpty) return const SizedBox.shrink();
        final open = pending.where((p) => !p.isPresent).toList();
        final first = open.isNotEmpty ? open.first : pending.first;
        final done = open.isEmpty;

        return Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: () => Navigator.of(context, rootNavigator: true).push(
              SlideFadePageRoute(builder: (_) => const AttendanceConfirmScreen()),
            ),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: done
                      ? const [Color(0xFF047857), Color(0xFF0D9488)]
                      : const [Color(0xFFB45309), Color(0xFFD97706)],
                ),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Row(
                children: [
                  Icon(done ? Icons.how_to_reg : Icons.notifications_active,
                      color: Colors.white, size: 22),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          done ? 'Davomat tasdiqlandi' : 'Davomat ochiq — tasdiqlang',
                          style: const TextStyle(
                              color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13.5),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          [first.subjectName, first.auditoriumName]
                              .where((s) => s != null && s.isNotEmpty)
                              .join(' — '),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(color: Colors.white.withOpacity(0.9), fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                  if (!done) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(9),
                      ),
                      child: const Text('Tasdiqlash',
                          style: TextStyle(
                              color: ClinicTheme.ink, fontWeight: FontWeight.w800, fontSize: 12)),
                    ),
                  ],
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
