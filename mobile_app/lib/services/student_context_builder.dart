import 'student_service.dart';

class StudentContextBuilder {
  final StudentService _service;

  StudentContextBuilder(this._service);

  static const _monthsUz = [
    'yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun',
    'iyul', 'avgust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'
  ];

  static const _weekdaysUz = [
    'dushanba', 'seshanba', 'chorshanba', 'payshanba',
    'juma', 'shanba', 'yakshanba'
  ];

  Future<String> build() async {
    final buf = StringBuffer();
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);

    final todayStr =
        '${now.year}-${_pad(now.month)}-${_pad(now.day)} '
        '(${_weekdaysUz[now.weekday - 1]}), '
        '${now.year}-yil ${now.day}-${_monthsUz[now.month - 1]}';

    buf.writeln('## BUGUNGI SANA');
    buf.writeln('- $todayStr');
    buf.writeln('- ISO: ${now.toIso8601String().substring(0, 10)}');
    buf.writeln(
        '- ESLATMA: bu sanadan oldingi sanalar [O\'TGAN], keyingilari [KELGUSI]');
    buf.writeln();

    final results = await Future.wait([
      _safeCall(() => _service.getProfile()),
      _safeCall(() => _service.getDashboard()),
      _safeCall(() => _service.getSubjects()),
      _safeCall(() => _service.getAttendance()),
      _safeCall(() => _service.getExamSchedule()),
      _safeCall(() => _service.getRating()),
      _safeCall(() => _service.getSchedule()),
      _safeCall(() => _service.getPendingLessons()),
      _safeCall(() => _service.getContract()),
      _safeCall(() => _service.getExcuses()),
    ]);

    final profile = results[0]?['data'] as Map<String, dynamic>?;
    final dashboard = results[1]?['data'] as Map<String, dynamic>?;
    final subjects = results[2]?['data'] as List<dynamic>?;
    final attendance = results[3]?['data'] as Map<String, dynamic>?;
    final examSchedule = results[4]?['data'];
    final rating = results[5]?['data'] as Map<String, dynamic>?;
    final schedule = results[6]?['data'] as Map<String, dynamic>?;
    final pendingLessons = results[7]?['data'];
    final contract = results[8]?['data'];
    final excuses = results[9]?['data'];

    if (profile != null) {
      buf.writeln('## SHAXSIY MA\'LUMOTLAR');
      for (final k in profile.keys) {
        final v = profile[k];
        if (v != null && v.toString().isNotEmpty && v is! List && v is! Map) {
          buf.writeln('- $k: $v');
        }
      }
      buf.writeln();
    }

    if (dashboard != null) {
      buf.writeln('## UMUMIY KO\'RSATKICHLAR (DASHBOARD)');
      _writeAllScalar(buf, dashboard);
      buf.writeln();
    }

    if (subjects != null && subjects.isNotEmpty) {
      buf.writeln('## FANLAR VA BAHOLAR (${subjects.length} ta fan)');
      buf.writeln('YN formula: JN×50% + MT×20% + ON×0% + OSKI×15% + TEST×15%');
      buf.writeln();

      final detailFutures = <Future<Map<String, dynamic>?>>[];
      for (final s in subjects) {
        if (s is Map<String, dynamic>) {
          final id = s['id'] as int?;
          if (id != null) {
            detailFutures.add(
                _safeCall(() => _service.getSubjectGrades(id)));
          } else {
            detailFutures.add(Future.value(null));
          }
        }
      }

      final details = await Future.wait(detailFutures);

      for (int i = 0; i < subjects.length; i++) {
        final s = subjects[i];
        if (s is! Map<String, dynamic>) continue;
        final name = s['subject_name'] ?? s['name'] ?? '?';
        final grades = s['grades'] as Map<String, dynamic>? ?? {};
        final yn = _computeYn(grades);

        buf.writeln('### $name');
        _line(buf, 'YN (yakuniy nazorat)', yn?.toStringAsFixed(1));
        _line(buf, 'JN (joriy nazorat)', grades['jn']);
        _line(buf, 'MT (mustaqil ta\'lim)', grades['mt']);
        _line(buf, 'ON (oraliq nazorat)', grades['on']);
        _line(buf, 'OSKI', grades['oski']);
        _line(buf, 'TEST', grades['test']);
        _line(buf, 'Kredit', s['credit'] ?? s['credits']);
        _line(buf, 'O\'qituvchi', s['teacher_name'] ?? s['teacher']);
        _line(buf, 'Dars turi', s['subject_type'] ?? s['type']);

        if (s['mt_submission'] is Map<String, dynamic>) {
          final mt = s['mt_submission'] as Map<String, dynamic>;
          _line(buf, 'MT yuklangan', mt['has_submission'] == true ? 'Ha' : 'Yo\'q');
          _line(buf, 'MT yuklash mumkin', mt['can_submit'] == true ? 'Ha' : 'Yo\'q');
          _line(buf, 'MT yuklangan sana', mt['submitted_at']);
        }

        final detail = details.length > i ? details[i] : null;
        final detailData = detail?['data'];
        if (detailData is Map<String, dynamic>) {
          final gradesList = detailData['grades'] as List<dynamic>?;
          if (gradesList != null && gradesList.isNotEmpty) {
            buf.writeln('  JN batafsil kunlik baholar:');
            for (final g in gradesList) {
              if (g is! Map<String, dynamic>) continue;
              final date = g['lesson_date']?.toString() ?? '';
              final grade = g['grade'] ?? g['ball'];
              final absent = g['absent'] == true || g['is_absent'] == true;
              final type = g['training_type_name'] ?? '';
              final shortDate = date.length >= 10 ? date.substring(0, 10) : date;
              final marker = _pastFutureMarker(shortDate, today);
              if (absent) {
                buf.writeln('    - $shortDate $marker: NB (qatnashmagan) [$type]');
              } else if (grade != null) {
                buf.writeln('    - $shortDate $marker: $grade ball [$type]');
              }
            }
          }
        }

        buf.writeln();
      }
    }

    if (attendance != null) {
      buf.writeln('## DAVOMAT STATISTIKASI');
      _writeAllScalar(buf, attendance);
      final bySubject = attendance['by_subject'] ?? attendance['subjects'];
      if (bySubject is List && bySubject.isNotEmpty) {
        buf.writeln('Fanlar bo\'yicha davomat:');
        for (final s in bySubject) {
          if (s is! Map<String, dynamic>) continue;
          final name = s['subject_name'] ?? s['name'] ?? '?';
          buf.writeln(
              '  - $name: qatnashgan ${s['attended'] ?? '?'}/${s['total'] ?? '?'} (${s['percentage'] ?? '?'}%)');
        }
      }
      buf.writeln();
    }

    if (pendingLessons != null) {
      buf.writeln('## QARZDOR DARSLAR (qatnashilmagan, qaytarish kerak)');
      _writeListOrScalar(buf, pendingLessons, today);
      buf.writeln();
    }

    if (schedule != null) {
      buf.writeln('## DARS JADVALI');
      _writeSchedule(buf, schedule, today);
      buf.writeln();
    }

    if (examSchedule != null) {
      buf.writeln('## IMTIHON JADVALI');
      _writeExamSchedule(buf, examSchedule, today);
      buf.writeln();
    }

    if (rating != null) {
      buf.writeln('## REYTING');
      _writeAllScalar(buf, rating);
      final students = rating['students'] as List<dynamic>?;
      if (students != null && students.isNotEmpty) {
        buf.writeln('Top 5 talaba:');
        for (final s in students.take(5)) {
          if (s is! Map<String, dynamic>) continue;
          buf.writeln(
              '  ${s['rank']}. ${s['full_name']} - ${s['jn_average']} (${s['is_me'] == true ? "SIZ" : s['group_name'] ?? ''})');
        }
      }
      buf.writeln();
    }

    if (excuses != null) {
      buf.writeln('## RUXSATNOMALAR (sababli qoldirilgan darslar)');
      _writeListOrScalar(buf, excuses, today);
      buf.writeln();
    }

    if (contract != null) {
      buf.writeln('## SHARTNOMA / TO\'LOV');
      _writeListOrScalar(buf, contract, today);
      buf.writeln();
    }

    return buf.toString();
  }

  String _pad(int n) => n.toString().padLeft(2, '0');

  String _pastFutureMarker(String dateStr, DateTime today) {
    final d = _parseDate(dateStr);
    if (d == null) return '';
    if (d.isBefore(today)) return '[O\'TGAN]';
    if (d.isAtSameMomentAs(today)) return '[BUGUN]';
    return '[KELGUSI]';
  }

  DateTime? _parseDate(String s) {
    if (s.isEmpty) return null;
    try {
      if (s.length >= 10 && s[4] == '-' && s[7] == '-') {
        final y = int.parse(s.substring(0, 4));
        final m = int.parse(s.substring(5, 7));
        final d = int.parse(s.substring(8, 10));
        return DateTime(y, m, d);
      }
      if (s.length >= 10 && s[2] == '.' && s[5] == '.') {
        final d = int.parse(s.substring(0, 2));
        final m = int.parse(s.substring(3, 5));
        final y = int.parse(s.substring(6, 10));
        return DateTime(y, m, d);
      }
      final iso = DateTime.tryParse(s);
      if (iso != null) return DateTime(iso.year, iso.month, iso.day);
    } catch (_) {}
    return null;
  }

  void _writeAllScalar(StringBuffer buf, Map<String, dynamic> map) {
    for (final e in map.entries) {
      if (e.value == null || e.value is List || e.value is Map) continue;
      if (e.value.toString().isEmpty) continue;
      buf.writeln('- ${e.key}: ${e.value}');
    }
  }

  void _writeListOrScalar(StringBuffer buf, dynamic data, DateTime today) {
    if (data is List) {
      if (data.isEmpty) {
        buf.writeln('- (bo\'sh)');
        return;
      }
      for (final item in data) {
        if (item is Map<String, dynamic>) {
          buf.write('  -');
          for (final e in item.entries) {
            final v = e.value;
            if (v == null || v is List || v is Map) continue;
            if (v.toString().isEmpty) continue;
            final marker = _looksLikeDate(v.toString())
                ? ' ${_pastFutureMarker(v.toString(), today)}'
                : '';
            buf.write(' ${e.key}=$v$marker;');
          }
          buf.writeln();
        } else if (item != null) {
          buf.writeln('  - $item');
        }
      }
    } else if (data is Map<String, dynamic>) {
      _writeAllScalar(buf, data);
    } else if (data != null) {
      buf.writeln('- $data');
    }
  }

  bool _looksLikeDate(String s) {
    if (s.length < 10) return false;
    return (s[4] == '-' && s[7] == '-') || (s[2] == '.' && s[5] == '.');
  }

  void _writeSchedule(
      StringBuffer buf, Map<String, dynamic> schedule, DateTime today) {
    final currentWeek = schedule['current_week'] as Map<String, dynamic>?;
    final days = schedule['days'] as List<dynamic>?;

    if (currentWeek != null) {
      _line(buf, 'Joriy hafta', currentWeek['name'] ?? currentWeek['label']);
      _line(buf, 'Boshlanishi', currentWeek['start_date']);
      _line(buf, 'Tugashi', currentWeek['end_date']);
    }

    if (days != null && days.isNotEmpty) {
      for (final day in days) {
        if (day is! Map<String, dynamic>) continue;
        final dayName = day['day_name'] ?? day['name'] ?? '';
        final date = day['date']?.toString() ?? '';
        final marker = _pastFutureMarker(date, today);
        final lessons = day['lessons'] as List<dynamic>?;
        if (lessons == null || lessons.isEmpty) continue;
        buf.writeln('  $dayName $date $marker:');
        for (final l in lessons) {
          if (l is! Map<String, dynamic>) continue;
          buf.writeln(
              '    - ${l['time'] ?? l['start_time'] ?? ''}: ${l['subject_name'] ?? l['name'] ?? '?'} (${l['teacher_name'] ?? ''}) [${l['room'] ?? l['auditorium'] ?? ''}]');
        }
      }
    }
  }

  void _writeExamSchedule(
      StringBuffer buf, dynamic examSchedule, DateTime today) {
    if (examSchedule is List) {
      for (final e in examSchedule) {
        if (e is Map<String, dynamic>) {
          final date = e['date']?.toString() ?? '?';
          final marker = _pastFutureMarker(date, today);
          buf.writeln(
              '  - ${e['subject_name'] ?? e['name'] ?? '?'}: $date ${e['time'] ?? ''} $marker (${e['type'] ?? e['exam_type'] ?? ''})');
        }
      }
    } else if (examSchedule is Map<String, dynamic>) {
      for (final key in examSchedule.keys) {
        final v = examSchedule[key];
        if (v is List) {
          buf.writeln('$key:');
          for (final e in v) {
            if (e is Map<String, dynamic>) {
              final date = e['date']?.toString() ?? '?';
              final marker = _pastFutureMarker(date, today);
              buf.writeln(
                  '  - ${e['subject_name'] ?? e['name'] ?? '?'}: $date ${e['time'] ?? ''} $marker');
            }
          }
        } else if (v != null && v is! Map) {
          buf.writeln('- $key: $v');
        }
      }
    }
  }

  void _line(StringBuffer buf, String key, dynamic value) {
    if (value == null || value.toString().isEmpty) return;
    buf.writeln('- $key: $value');
  }

  double? _computeYn(Map<String, dynamic> grades) {
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
