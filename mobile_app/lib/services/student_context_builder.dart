import 'student_service.dart';

class StudentContextBuilder {
  final StudentService _service;

  StudentContextBuilder(this._service);

  Future<String> build() async {
    final buf = StringBuffer();

    final results = await Future.wait([
      _safeCall(() => _service.getProfile()),
      _safeCall(() => _service.getDashboard()),
      _safeCall(() => _service.getSubjects()),
      _safeCall(() => _service.getAttendance()),
      _safeCall(() => _service.getExamSchedule()),
      _safeCall(() => _service.getRating()),
    ]);

    final profile = results[0]?['data'] as Map<String, dynamic>?;
    final dashboard = results[1]?['data'] as Map<String, dynamic>?;
    final subjects = results[2]?['data'] as List<dynamic>?;
    final attendance = results[3]?['data'] as Map<String, dynamic>?;
    final examSchedule = results[4]?['data'];
    final rating = results[5]?['data'] as Map<String, dynamic>?;

    if (profile != null) {
      buf.writeln('## SHAXSIY MA\'LUMOTLAR');
      _line(buf, 'F.I.Sh', profile['full_name']);
      _line(buf, 'Talaba ID', profile['hemis_id']);
      _line(buf, 'Guruh', profile['group_name']);
      _line(buf, 'Kurs', profile['level']);
      _line(buf, 'Yo\'nalish', profile['specialty']);
      _line(buf, 'Fakultet', profile['department']);
      _line(buf, 'Semester', profile['semester']);
      _line(buf, 'Telefon', profile['phone']);
      _line(buf, 'Email', profile['email']);
      buf.writeln();
    }

    if (dashboard != null) {
      buf.writeln('## UMUMIY KO\'RSATKICHLAR');
      _line(buf, 'GPA', dashboard['gpa']);
      _line(buf, 'O\'rtacha baho', dashboard['average_grade']);
      _line(buf, 'Davomat foizi', dashboard['attendance_percent']);
      _line(buf, 'Jami fanlar', dashboard['total_subjects']);
      buf.writeln();
    }

    if (subjects != null && subjects.isNotEmpty) {
      buf.writeln('## FANLAR VA BAHOLAR (${subjects.length} fan)');
      for (final s in subjects) {
        final m = s as Map<String, dynamic>;
        final name = m['name'] ?? m['subject_name'] ?? '?';
        final grades = m['grades'] as Map<String, dynamic>? ?? {};
        final yn = _computeYn(grades, m);
        buf.writeln('### $name');
        _line(buf, '  YN (yakuniy)', yn?.toStringAsFixed(1));
        _line(buf, '  JN (joriy nazorat)', grades['jn']);
        _line(buf, '  MT (mustaqil topshiriq)', grades['mt']);
        _line(buf, '  ON (oraliq nazorat)', grades['on']);
        _line(buf, '  OSKI', grades['oski']);
        _line(buf, '  TEST', grades['test']);
        _line(buf, '  Kredit', m['credit']);
        _line(buf, '  O\'qituvchi', m['teacher_name']);
        buf.writeln();
      }
    }

    if (attendance != null) {
      buf.writeln('## DAVOMAT');
      _line(buf, 'Jami darslar', attendance['total_lessons']);
      _line(buf, 'Qatnashgan', attendance['attended']);
      _line(buf, 'Qatnashmagan', attendance['missed']);
      _line(buf, 'Davomat foizi', attendance['percentage']);
      final bySubject = attendance['by_subject'] as List<dynamic>?;
      if (bySubject != null && bySubject.isNotEmpty) {
        buf.writeln('Fanlar bo\'yicha:');
        for (final s in bySubject) {
          final m = s as Map<String, dynamic>;
          buf.writeln('  - ${m['subject_name']}: ${m['attended']}/${m['total']} (${m['percentage']}%)');
        }
      }
      buf.writeln();
    }

    if (examSchedule != null) {
      buf.writeln('## IMTIHON JADVALI');
      if (examSchedule is List) {
        for (final e in examSchedule) {
          if (e is Map<String, dynamic>) {
            buf.writeln('  - ${e['subject_name'] ?? '?'}: ${e['date'] ?? '?'} ${e['time'] ?? ''} (${e['type'] ?? ''})');
          }
        }
      } else if (examSchedule is Map<String, dynamic>) {
        examSchedule.forEach((k, v) {
          if (v is List) {
            buf.writeln('$k:');
            for (final e in v) {
              if (e is Map<String, dynamic>) {
                buf.writeln('  - ${e['subject_name'] ?? e['name'] ?? '?'}: ${e['date'] ?? '?'} ${e['time'] ?? ''}');
              }
            }
          }
        });
      }
      buf.writeln();
    }

    if (rating != null) {
      buf.writeln('## REYTING');
      _line(buf, 'Sizning o\'rningiz', rating['my_rank']);
      _line(buf, 'Jami talabalar', rating['total_students']);
      _line(buf, 'O\'rtacha baho', rating['my_jn_average']);
      buf.writeln();
    }

    return buf.toString();
  }

  void _line(StringBuffer buf, String key, dynamic value) {
    if (value == null || value.toString().isEmpty) return;
    buf.writeln('- $key: $value');
  }

  double? _computeYn(Map<String, dynamic> grades, Map<String, dynamic> subject) {
    const weights = {'jn': 50, 'mt': 20, 'on': 0, 'oski': 15, 'test': 15};
    double sum = 0;
    bool hasAny = false;
    for (final k in weights.keys) {
      final v = grades[k];
      if (v is num) {
        sum += v * (weights[k]! / 100);
        hasAny = true;
      }
    }
    return hasAny ? sum : null;
  }

  Future<Map<String, dynamic>?> _safeCall(
      Future<Map<String, dynamic>> Function() fn) async {
    try {
      final res = await fn();
      if (res['success'] == true) return res;
    } catch (_) {}
    return null;
  }
}
