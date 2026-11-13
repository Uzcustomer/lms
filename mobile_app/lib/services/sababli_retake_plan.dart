/// Sababli ariza qayta topshirish jadvali — LMS dagi App\Services\SababliRetakePlan
/// va veb formadagi (create.blade.php) JS bilan bir xil qoidalar.
///
/// Avtomatik jadvalni server tuzadi (har bir nazoratning `makeup_date` i). Bu yerda
/// talaba kunni ko'chirganda shu qoidalar tekshiriladi; saqlashda server qayta tekshiradi.
/// Sanalar 'yyyy-MM-dd' satr ko'rinishida — ular satr sifatida solishtiriladi.
class SababliRetakePlan {
  SababliRetakePlan({
    required this.items,
    required this.retakeStart,
    required this.retakeUntil,
    this.maxPerDay = 3,
  });

  /// Server qaytargan nazoratlar (missed-assessments `data`)
  final List<Map<String, dynamic>> items;

  /// Birinchi retake kuni
  final String retakeStart;

  /// Retake kunlarining oxirgi chegarasi (SababliRetakePlan::lastAllowedDay)
  final String retakeUntil;

  /// Bir kunda ko'pi bilan nechta fan
  final int maxPerDay;

  /// Topshirilgan / o'z vaqtida / MT yuklangan — kun ajratilmaydi
  static bool isPending(Map<String, dynamic> a) =>
      !_truthy(a['already_submitted']) && !_truthy(a['jn_submitted']);

  static bool _truthy(dynamic v) => v == true || v == 1 || v == '1' || v == 'true';

  static String _subj(Map<String, dynamic> a) =>
      (a['subject_name'] ?? '').toString().trim().toLowerCase();

  String _type(int i) => (items[i]['assessment_type'] ?? 'jn').toString();

  static DateTime parse(String ds) {
    final p = ds.substring(0, 10).split('-').map(int.parse).toList();
    return DateTime(p[0], p[1], p[2]);
  }

  static String format(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  /// Yakshanba bo'lsa — dushanba
  static String firstDay(String ds) {
    final d = parse(ds);
    return format(d.weekday == DateTime.sunday ? d.add(const Duration(days: 1)) : d);
  }

  /// Keyingi ish kuni (yakshanbasiz)
  static String nextDay(String ds) {
    var d = parse(ds).add(const Duration(days: 1));
    if (d.weekday == DateTime.sunday) d = d.add(const Duration(days: 1));
    return format(d);
  }

  static String _dmy(String ds) => '${ds.substring(8, 10)}.${ds.substring(5, 7)}.${ds.substring(0, 4)}';

  /// Server tuzgan avtomatik jadval: indeks => kun (topshirilganlar kirmaydi)
  Map<int, String> get autoPlan {
    final out = <int, String>{};
    for (var i = 0; i < items.length; i++) {
      final d = items[i]['makeup_date'];
      if (isPending(items[i]) && d is String && d.isNotEmpty) out[i] = d;
    }
    return out;
  }

  Map<int, String> withoutMt(Map<int, String> p) =>
      {for (final e in p.entries) if (_type(e.key) != 'mt') e.key: e.value};

  /// MT muddati — fanning oxirgi retake kuni (SababliRetakePlan::manual bilan bir xil)
  Map<int, String> withMt(Map<int, String> p) {
    final out = Map<int, String>.from(p);
    final lastJn = <String, String>{};
    final lastAny = <String, String>{};
    p.forEach((i, d) {
      final s = _subj(items[i]);
      if (lastAny[s] == null || d.compareTo(lastAny[s]!) > 0) lastAny[s] = d;
      if (_type(i) == 'jn' && (lastJn[s] == null || d.compareTo(lastJn[s]!) > 0)) lastJn[s] = d;
    });
    final first = firstDay(retakeStart);
    for (var i = 0; i < items.length; i++) {
      if (_type(i) == 'mt' && isPending(items[i])) {
        final s = _subj(items[i]);
        out[i] = lastJn[s] ?? lastAny[s] ?? first;
      }
    }
    return out;
  }

  /// Amaldagi jadval: talaba ko'chirgan bo'lsa — uniki (MT qayta hisoblanadi), aks holda avtomatik
  Map<int, String> plan(Map<int, String>? manual) => manual != null ? withMt(manual) : autoPlan;

  /// Jadval qoidalari (SababliRetakePlan::validate bilan bir xil): xato matni yoki null
  String? planError(Map<int, String> p) {
    final first = firstDay(retakeStart);
    final days = <String, Map<String, Map<String, int>>>{};
    final subs = <String, Map<String, dynamic>>{};
    for (final e in p.entries) {
      final a = items[e.key];
      if (!isPending(a) || _type(e.key) == 'mt') continue;
      final t = (_type(e.key) == 'oski' || _type(e.key) == 'test') ? _type(e.key) : 'jn';
      final s = _subj(a);
      final d = e.value;
      final name = a['subject_name'] ?? '';
      if (d.isEmpty) return '$name: kun tanlanmagan';
      if (parse(d).weekday == DateTime.sunday || d.compareTo(first) < 0 || d.compareTo(retakeUntil) > 0) {
        return '$name: ${_dmy(d)} — mumkin emas';
      }
      final c = days.putIfAbsent(d, () => {}).putIfAbsent(s, () => {'jn': 0, 'oski': 0, 'test': 0});
      c[t] = c[t]! + 1;
      final sub = subs.putIfAbsent(s, () => {'name': name, 'jn': <String>[], 'oski': <String>[], 'test': <String>[]});
      (sub[t] as List<String>).add(d);
    }
    for (final d in days.keys) {
      if (days[d]!.length > maxPerDay) return '${_dmy(d)} kuni $maxPerDay tadan ortiq fan';
      for (final s in days[d]!.keys) {
        final c = days[d]![s]!;
        if (c['jn']! + c['test']! > 1 || c['oski']! > 1) {
          return '${subs[s]!['name']}: ${_dmy(d)} kuni bittadan ortiq retake';
        }
      }
    }
    String maxOf(List<String> list) => list.fold('', (m, d) => d.compareTo(m) > 0 ? d : m);
    for (final x in subs.values) {
      final jn = x['jn'] as List<String>, oski = x['oski'] as List<String>, test = x['test'] as List<String>;
      final lastJn = maxOf(jn);
      if (oski.any((d) => lastJn.isNotEmpty && d.compareTo(lastJn) < 0)) {
        return '${x['name']}: OSKI oxirgi retake kunidan oldin';
      }
      final before = maxOf([...jn, ...oski]);
      if (before.isNotEmpty) {
        final min = nextDay(nextDay(before));
        if (test.any((d) => d.compareTo(min) < 0)) {
          return '${x['name']}: test retake/OSKI dan bir kun oraliq bilan';
        }
      }
    }
    return null;
  }

  /// Nazoratni ko'chirish mumkin bo'lgan kunlar (retake_start..retake_until, qoidalar buzilmaydigan).
  /// `load` — ko'chirilgandan keyin shu kuni retake qiladigan fanlar soni.
  List<({String date, int load})> candidateDays(int idx, Map<int, String>? manual) {
    final base = manual ?? withoutMt(autoPlan);
    final subj = _subj(items[idx]);
    final out = <({String date, int load})>[];
    for (var d = firstDay(retakeStart); d.compareTo(retakeUntil) <= 0; d = nextDay(d)) {
      if (d == base[idx] || planError({...base, idx: d}) != null) continue;
      final others = base.entries
          .where((e) => e.value == d && _subj(items[e.key]) != subj)
          .map((e) => _subj(items[e.key]))
          .toSet();
      out.add((date: d, load: others.length + 1));
    }
    return out;
  }

  /// Ko'chirilgan jadval yoki null (qoidaga to'g'ri kelmasa)
  Map<int, String>? moveTo(int idx, String date, Map<int, String>? manual) {
    final next = {...(manual ?? withoutMt(autoPlan)), idx: date};
    return planError(next) == null ? next : null;
  }

  /// Yakshanbasiz kunlar soni
  static int countNonSundays(DateTime start, DateTime end) {
    var count = 0;
    for (var d = DateTime(start.year, start.month, start.day);
        !d.isAfter(end);
        d = DateTime(d.year, d.month, d.day + 1)) {
      if (d.weekday != DateTime.sunday) count++;
    }
    return count;
  }

  /// Hujjat topshirish muddati (veb checkDeadline bilan bir xil): tugash sanasidan keyingi
  /// ish kunidan necha kun o'tgan; tugash kuni hali o'tmagan bo'lsa null
  static int? daysSinceEnd(DateTime end, DateTime today) {
    var next = DateTime(end.year, end.month, end.day + 1);
    if (next.weekday == DateTime.sunday) next = DateTime(next.year, next.month, next.day + 1);
    final t = DateTime(today.year, today.month, today.day);
    if (t.isBefore(next)) return null;
    return DateTime.utc(t.year, t.month, t.day).difference(DateTime.utc(next.year, next.month, next.day)).inDays;
  }
}
