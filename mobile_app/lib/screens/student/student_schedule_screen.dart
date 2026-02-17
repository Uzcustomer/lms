import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/student_provider.dart';
import '../../widgets/loading_widget.dart';

class StudentScheduleScreen extends StatefulWidget {
  const StudentScheduleScreen({super.key});

  @override
  State<StudentScheduleScreen> createState() => _StudentScheduleScreenState();
}

class _StudentScheduleScreenState extends State<StudentScheduleScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadSchedule();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        title: const Text('Dars jadvali'),
      ),
      body: Consumer<StudentProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading && provider.schedule == null) {
            return const LoadingWidget();
          }

          final schedule = provider.schedule;
          if (schedule == null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.calendar_today_outlined,
                      size: 64, color: AppTheme.textSecondary),
                  const SizedBox(height: 16),
                  Text(provider.error ?? 'Jadval topilmadi'),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () => provider.loadSchedule(),
                    child: const Text('Qayta yuklash'),
                  ),
                ],
              ),
            );
          }

          final days = schedule['days'] as Map<String, dynamic>? ?? {};

          if (days.isEmpty) {
            return const Center(
              child: Text('Bu hafta uchun jadval topilmadi'),
            );
          }

          return RefreshIndicator(
            onRefresh: () => provider.loadSchedule(),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (schedule['week_label'] != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: Text(
                      schedule['week_label'].toString(),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ...days.entries.map((entry) {
                  final dayName = entry.key;
                  final lessons = entry.value as List<dynamic>? ?? [];
                  return _DayScheduleCard(
                    dayName: dayName,
                    lessons: lessons,
                  );
                }),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _DayScheduleCard extends StatelessWidget {
  final String dayName;
  final List<dynamic> lessons;

  const _DayScheduleCard({
    required this.dayName,
    required this.lessons,
  });

  @override
  Widget build(BuildContext context) {
    final isToday = _isToday(dayName);

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: isToday
            ? const BorderSide(color: AppTheme.primaryColor, width: 2)
            : BorderSide.none,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Day header
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: isToday
                  ? AppTheme.primaryColor.withAlpha(25)
                  : AppTheme.backgroundColor,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.calendar_today,
                  size: 18,
                  color: isToday ? AppTheme.primaryColor : AppTheme.textSecondary,
                ),
                const SizedBox(width: 8),
                Text(
                  dayName,
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 15,
                    color: isToday ? AppTheme.primaryColor : AppTheme.textPrimary,
                  ),
                ),
                if (isToday) ...[
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppTheme.primaryColor,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text(
                      'Bugun',
                      style: TextStyle(color: Colors.white, fontSize: 11),
                    ),
                  ),
                ],
              ],
            ),
          ),
          // Lessons
          if (lessons.isEmpty)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Dars yo\'q',
                style: TextStyle(color: AppTheme.textSecondary),
              ),
            )
          else
            ...lessons.map((lesson) {
              final l = lesson as Map<String, dynamic>;
              return ListTile(
                contentPadding: const EdgeInsets.symmetric(horizontal: 16),
                leading: Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: AppTheme.primaryColor.withAlpha(25),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        l['lesson_pair_code']?.toString() ?? '',
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          color: AppTheme.primaryColor,
                          fontSize: 16,
                        ),
                      ),
                      Text(
                        'para',
                        style: TextStyle(
                          fontSize: 9,
                          color: AppTheme.primaryColor.withAlpha(178),
                        ),
                      ),
                    ],
                  ),
                ),
                title: Text(
                  l['subject_name']?.toString() ?? '',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                ),
                subtitle: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (l['employee_name'] != null)
                      Text(
                        l['employee_name'].toString(),
                        style: const TextStyle(fontSize: 11),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    Row(
                      children: [
                        if (l['auditorium_name'] != null) ...[
                          const Icon(Icons.room_outlined, size: 12, color: AppTheme.textSecondary),
                          const SizedBox(width: 2),
                          Text(
                            l['auditorium_name'].toString(),
                            style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary),
                          ),
                        ],
                        if (l['lesson_pair_start_time'] != null) ...[
                          const SizedBox(width: 8),
                          const Icon(Icons.access_time, size: 12, color: AppTheme.textSecondary),
                          const SizedBox(width: 2),
                          Text(
                            '${l['lesson_pair_start_time']} - ${l['lesson_pair_end_time'] ?? ''}',
                            style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
                trailing: l['training_type_name'] != null
                    ? Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppTheme.accentColor.withAlpha(25),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          l['training_type_name'].toString(),
                          style: const TextStyle(
                            fontSize: 10,
                            color: AppTheme.accentColor,
                          ),
                        ),
                      )
                    : null,
              );
            }),
        ],
      ),
    );
  }

  bool _isToday(String dayName) {
    final weekDays = {
      'Dushanba': DateTime.monday,
      'Seshanba': DateTime.tuesday,
      'Chorshanba': DateTime.wednesday,
      'Payshanba': DateTime.thursday,
      'Juma': DateTime.friday,
      'Shanba': DateTime.saturday,
      'Yakshanba': DateTime.sunday,
    };
    return weekDays[dayName] == DateTime.now().weekday;
  }
}
