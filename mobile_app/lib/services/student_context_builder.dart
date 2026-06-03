import 'student_data_cache.dart';

/// Builds the long markdown text we feed to Gemini as the AI's view of the
/// student. Pure projection of the cache — no API calls happen here, so the
/// AI stays in sync with whatever the cache last fetched.
///
/// The per-section body is rendered by a generic recursive dumper
/// ([_writeAny]) so it survives any API response shape — whatever the LMS
/// returns under `data`, the whole structure ends up in the context.
class StudentContextBuilder {
  final StudentDataCache _cache;

  StudentContextBuilder(this._cache);

  static const _monthsUz = [
    'yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun',
    'iyul', 'avgust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'
  ];

  static const _weekdaysUz = [
    'dushanba', 'seshanba', 'chorshanba', 'payshanba',
    'juma', 'shanba', 'yakshanba'
  ];

  String build() {
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
    if (_cache.lastFetchedAt != null) {
      final fa = _cache.lastFetchedAt!;
      buf.writeln(
          '- Cache yangilangan: ${fa.year}-${_pad(fa.month)}-${_pad(fa.day)} '
          '${_pad(fa.hour)}:${_pad(fa.minute)}');
    }
    buf.writeln();

    final profile = _unwrap(_cache.profile);
    final dashboard = _unwrap(_cache.dashboard);
    final subjects = _unwrap(_cache.subjects);
    final attendance = _unwrap(_cache.attendance);
    final examSchedule = _unwrap(_cache.examSchedule);
    final rating = _unwrap(_cache.rating);
    final schedule = _unwrap(_cache.schedule);
    final pendingLessons = _unwrap(_cache.pendingLessons);
    final contract = _unwrap(_cache.contract);
    final excuses = _unwrap(_cache.excuses);

    _section(buf, 'SHAXSIY MA\'LUMOTLAR', profile, today);
    _section(buf, 'UMUMIY KO\'RSATKICHLAR (DASHBOARD)', dashboard, today);

    // Subjects keep their richer rendering (YN computation + per-subject
    // daily grade breakdown pulled from a separate cache entry).
    if (subjects is List && subjects.isNotEmpty) {
      buf.writeln('## FANLAR VA BAHOLAR (${subjects.length} ta fan)');
      buf.writeln('YN formula: JN×50% + MT×20% + ON×0% + OSKI×15% + TEST×15%');
      buf.writeln();

      for (final s in subjects) {
        if (s is! Map) continue;
        final id = s['subject_id'] ?? s['id'];
        final name = s['subject_name'] ?? s['name'] ?? '?';
        final grades = s['grades'];
        final gradesMap =
            grades is Map ? Map<String, dynamic>.from(grades) : <String, dynamic>{};
        final yn = _computeYn(gradesMap);

        buf.writeln('### $name');
        _line(buf, 'YN (yakuniy nazorat)', yn?.toStringAsFixed(1));
        // Dump every field the API sent for this subject — nothing is dropped.
        _writeAny(buf, s, 1, today);

        if (id is int) {
          final detail = _unwrap(_cache.subjectGrades(id));
          if (detail is Map) {
            final gradesList = detail['grades'];
            if (gradesList is List && gradesList.isNotEmpty) {
              buf.writeln('  JN batafsil kunlik baholar:');
              for (final g in gradesList) {
                if (g is! Map) continue;
                final date = g['lesson_date']?.toString() ?? '';
                final grade = g['grade'] ?? g['ball'];
                final absent = g['absent'] == true || g['is_absent'] == true;
                final type = g['training_type_name'] ?? '';
                final shortDate =
                    date.length >= 10 ? date.substring(0, 10) : date;
                final marker = _pastFutureMarker(shortDate, today);
                if (absent) {
                  buf.writeln(
                      '    - $shortDate $marker: NB (qatnashmagan) [$type]');
                } else if (grade != null) {
                  buf.writeln('    - $shortDate $marker: $grade ball [$type]');
                }
              }
            } else {
              // Unknown detail shape — still hand it over verbatim.
              buf.writeln('  Batafsil ma\'lumot:');
              _writeAny(buf, detail, 2, today);
            }
          }
        }

        buf.writeln();
      }
    }

    _section(buf, 'DAVOMAT STATISTIKASI', attendance, today);
    _section(buf, 'QARZDOR DARSLAR (qatnashilmagan, qaytarish kerak)',
        pendingLessons, today);
    _section(buf, 'DARS JADVALI', schedule, today);
    _section(buf, 'IMTIHON JADVALI', examSchedule, today);
    _section(buf, 'REYTING', rating, today);
    _section(buf, 'RUXSATNOMALAR (sababli qoldirilgan darslar)', excuses, today);
    _section(buf, 'SHARTNOMA / TO\'LOV', contract, today);

    return buf.toString();
  }

  /// Unwraps the API envelope: returns `response['data']` when present,
  /// otherwise the response itself. Works for any response shape.
  dynamic _unwrap(Map<String, dynamic>? response) {
    if (response == null) return null;
    if (response.containsKey('data')) return response['data'];
    return response;
  }

  /// Writes a `## HEADER` block followed by a generic recursive dump of
  /// whatever [data] is. Skips the section entirely when there is nothing.
  void _section(StringBuffer buf, String header, dynamic data, DateTime today) {
    if (data == null) return;
    if (data is Map && data.isEmpty) return;
    if (data is List && data.isEmpty) {
      buf.writeln('## $header');
      buf.writeln('- (bo\'sh)');
      buf.writeln();
      return;
    }
    if (data is String && data.isEmpty) return;

    buf.writeln('## $header');
    _writeAny(buf, data, 0, today);
    buf.writeln();
  }

  /// Generic recursive renderer. Handles Map / List / scalar at any depth so
  /// the AI receives the complete structure regardless of the API shape.
  void _writeAny(StringBuffer buf, dynamic data, int indent, DateTime today) {
    final pad = '  ' * indent;

    if (data is Map) {
      data.forEach((key, value) {
        if (value == null) return;
        if (value is Map) {
          if (value.isEmpty) return;
          buf.writeln('$pad- $key:');
          _writeAny(buf, value, indent + 1, today);
        } else if (value is List) {
          if (value.isEmpty) return;
          buf.writeln('$pad- $key:');
          _writeAny(buf, value, indent + 1, today);
        } else {
          final s = value.toString();
          if (s.isEmpty) return;
          buf.writeln('$pad- $key: $s${_dateMarker(s, today)}');
        }
      });
    } else if (data is List) {
      for (var i = 0; i < data.length; i++) {
        final item = data[i];
        if (item == null) continue;
        if (item is Map) {
          if (item.isEmpty) continue;
          buf.writeln('$pad- [${i + 1}]:');
          _writeAny(buf, item, indent + 1, today);
        } else if (item is List) {
          if (item.isEmpty) continue;
          buf.writeln('$pad- [${i + 1}]:');
          _writeAny(buf, item, indent + 1, today);
        } else {
          final s = item.toString();
          if (s.isEmpty) continue;
          buf.writeln('$pad- $s${_dateMarker(s, today)}');
        }
      }
    } else if (data != null) {
      final s = data.toString();
      if (s.isNotEmpty) {
        buf.writeln('$pad- $s${_dateMarker(s, today)}');
      }
    }
  }

  String _dateMarker(String s, DateTime today) =>
      _looksLikeDate(s) ? ' ${_pastFutureMarker(s, today)}' : '';

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

  bool _looksLikeDate(String s) {
    if (s.length < 10) return false;
    return (s[4] == '-' && s[7] == '-') || (s[2] == '.' && s[5] == '.');
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
}
