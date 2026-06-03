import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'student_service.dart';

/// Cache for student data fetched from the LMS API.
///
/// Strategy: fetch every endpoint once, persist responses to SharedPreferences
/// keyed by endpoint, plus a "last fetched at" timestamp. Throughout the day
/// every consumer (UI screens, AI context builder) reads from this cache —
/// no more round-trips on every login/screen-open. A background refresh
/// happens automatically on app start when the cache is older than 24h.
class StudentDataCache {
  static const _prefsKeyPrefix = 'student_cache_';
  static const _timestampKey = 'student_cache_fetched_at';
  static const _validity = Duration(hours: 24);

  static const _kProfile = 'profile';
  static const _kDashboard = 'dashboard';
  static const _kSubjects = 'subjects';
  static const _kAttendance = 'attendance';
  static const _kExamSchedule = 'exam_schedule';
  static const _kRating = 'rating';
  static const _kSchedule = 'schedule';
  static const _kPendingLessons = 'pending_lessons';
  static const _kContract = 'contract';
  static const _kExcuses = 'excuses';
  static const _kSubjectGradesPrefix = 'subject_grades_';

  static final StudentDataCache _instance = StudentDataCache._();
  factory StudentDataCache() => _instance;
  StudentDataCache._();

  StudentService? _service;
  Future<void>? _ongoingRefresh;
  DateTime? _lastFetchedAt;
  final Map<String, dynamic> _memory = {};
  bool _loaded = false;

  void attachService(StudentService service) {
    _service = service;
  }

  DateTime? get lastFetchedAt => _lastFetchedAt;

  bool get hasData => _memory.isNotEmpty;

  bool get isStale {
    if (_lastFetchedAt == null) return true;
    return DateTime.now().difference(_lastFetchedAt!) > _validity;
  }

  /// Loads cached data from disk into memory. Call once on app start.
  Future<void> loadFromDisk() async {
    if (_loaded) return;
    final prefs = await SharedPreferences.getInstance();
    final ts = prefs.getInt(_timestampKey);
    if (ts != null) {
      _lastFetchedAt = DateTime.fromMillisecondsSinceEpoch(ts);
    }

    for (final key in prefs.getKeys()) {
      if (key.startsWith(_prefsKeyPrefix)) {
        final raw = prefs.getString(key);
        if (raw == null) continue;
        try {
          _memory[key.substring(_prefsKeyPrefix.length)] = jsonDecode(raw);
        } catch (_) {}
      }
    }
    _loaded = true;
  }

  /// Ensures a fresh cache exists. If stale or missing, triggers refresh.
  /// Concurrent callers share the same future.
  Future<void> ensureFresh({bool force = false}) async {
    await loadFromDisk();
    if (!force && !isStale && _memory.isNotEmpty) return;
    return refresh();
  }

  /// Force refresh. Re-runs all API calls and overwrites the cache on success.
  Future<void> refresh() async {
    if (_service == null) return;
    if (_ongoingRefresh != null) return _ongoingRefresh;

    final completer = _doRefresh();
    _ongoingRefresh = completer;
    try {
      await completer;
    } finally {
      _ongoingRefresh = null;
    }
  }

  Future<void> _doRefresh() async {
    final svc = _service;
    if (svc == null) return;

    final results = await Future.wait([
      _safe(() => svc.getProfile()),
      _safe(() => svc.getDashboard()),
      _safe(() => svc.getSubjects()),
      _safe(() => svc.getAttendance()),
      _safe(() => svc.getExamSchedule()),
      _safe(() => svc.getRating()),
      _safe(() => svc.getSchedule()),
      _safe(() => svc.getPendingLessons()),
      _safe(() => svc.getContract()),
      _safe(() => svc.getExcuses()),
    ]);

    final next = <String, dynamic>{};
    void put(String key, Map<String, dynamic>? res) {
      if (res != null) next[key] = res;
    }

    put(_kProfile, results[0]);
    put(_kDashboard, results[1]);
    put(_kSubjects, results[2]);
    put(_kAttendance, results[3]);
    put(_kExamSchedule, results[4]);
    put(_kRating, results[5]);
    put(_kSchedule, results[6]);
    put(_kPendingLessons, results[7]);
    put(_kContract, results[8]);
    put(_kExcuses, results[9]);

    final subjectsRes = results[2];
    final subjectsList = subjectsRes?['data'];
    if (subjectsList is List) {
      final detailFutures = <Future<MapEntry<int, Map<String, dynamic>?>>>[];
      for (final s in subjectsList) {
        if (s is Map<String, dynamic>) {
          final id = s['subject_id'] ?? s['id'];
          if (id is int) {
            detailFutures.add(_safe(() => svc.getSubjectGrades(id))
                .then((res) => MapEntry(id, res)));
          }
        }
      }
      final detailResults = await Future.wait(detailFutures);
      for (final entry in detailResults) {
        if (entry.value != null) {
          next['$_kSubjectGradesPrefix${entry.key}'] = entry.value;
        }
      }
    }

    if (next.isEmpty) return;

    _memory
      ..clear()
      ..addAll(next);
    _lastFetchedAt = DateTime.now();

    final prefs = await SharedPreferences.getInstance();
    final existingKeys = prefs.getKeys()
        .where((k) => k.startsWith(_prefsKeyPrefix))
        .toList();
    for (final k in existingKeys) {
      await prefs.remove(k);
    }
    for (final entry in _memory.entries) {
      await prefs.setString(
          '$_prefsKeyPrefix${entry.key}', jsonEncode(entry.value));
    }
    await prefs.setInt(_timestampKey, _lastFetchedAt!.millisecondsSinceEpoch);
  }

  /// Erase everything (use on logout).
  Future<void> clear() async {
    _memory.clear();
    _lastFetchedAt = null;
    _loaded = false;
    final prefs = await SharedPreferences.getInstance();
    final keys = prefs.getKeys().where((k) => k.startsWith(_prefsKeyPrefix));
    for (final k in keys) {
      await prefs.remove(k);
    }
    await prefs.remove(_timestampKey);
  }

  Map<String, dynamic>? get profile =>
      _memory[_kProfile] as Map<String, dynamic>?;
  Map<String, dynamic>? get dashboard =>
      _memory[_kDashboard] as Map<String, dynamic>?;
  Map<String, dynamic>? get subjects =>
      _memory[_kSubjects] as Map<String, dynamic>?;
  Map<String, dynamic>? get attendance =>
      _memory[_kAttendance] as Map<String, dynamic>?;
  Map<String, dynamic>? get examSchedule =>
      _memory[_kExamSchedule] as Map<String, dynamic>?;
  Map<String, dynamic>? get rating =>
      _memory[_kRating] as Map<String, dynamic>?;
  Map<String, dynamic>? get schedule =>
      _memory[_kSchedule] as Map<String, dynamic>?;
  Map<String, dynamic>? get pendingLessons =>
      _memory[_kPendingLessons] as Map<String, dynamic>?;
  Map<String, dynamic>? get contract =>
      _memory[_kContract] as Map<String, dynamic>?;
  Map<String, dynamic>? get excuses =>
      _memory[_kExcuses] as Map<String, dynamic>?;

  Map<String, dynamic>? subjectGrades(int subjectId) =>
      _memory['$_kSubjectGradesPrefix$subjectId'] as Map<String, dynamic>?;

  Future<Map<String, dynamic>?> _safe(
      Future<Map<String, dynamic>> Function() fn) async {
    try {
      final res = await fn();
      if (res.containsKey('data') || res.containsKey('success')) return res;
    } catch (_) {}
    return null;
  }
}
