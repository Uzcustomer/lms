import 'dart:async';
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';
import 'student_service.dart';

/// Once-per-day cache for the student's API data.
///
/// Strategy: stale-while-revalidate with per-endpoint fallback.
///   • App start → load all stored responses from disk.
///   • Each endpoint has its own `fetchedAt`. The whole bundle is "fresh"
///     when every essential endpoint was fetched within [_validity].
///   • Reads are instant from memory; UI never waits on the network during
///     the day.
///   • If a screen needs an endpoint that's missing from the cache (e.g.
///     a previous refresh partially failed), `getOrFetch` pulls just that
///     one endpoint and writes it back.
///   • Failures are captured per endpoint in `[errors]` — never silently
///     swallowed; the provider can surface them.
///   • Pull-to-refresh on any screen → `refresh(force: true)` re-runs
///     every fetcher in parallel.
class StudentDataCache {
  // ── Persistence keys ───────────────────────────────────
  static const _prefsKeyPrefix = 'student_cache_';
  static const _tsKeyPrefix = 'student_cache_ts_';
  static const _bundleTimestampKey = 'student_cache_fetched_at';
  static const _validity = Duration(hours: 24);

  // ── Endpoint keys ──────────────────────────────────────
  static const kProfile = 'profile';
  static const kDashboard = 'dashboard';
  static const kSubjects = 'subjects';
  static const kAttendance = 'attendance';
  static const kExamSchedule = 'exam_schedule';
  static const kRating = 'rating';
  static const kSchedule = 'schedule';
  static const kPendingLessons = 'pending_lessons';
  static const kContract = 'contract';
  static const kExcuses = 'excuses';
  static const kClubs = 'clubs';
  static const kAppeals = 'appeals';
  static const kChatContacts = 'chat_contacts';
  static const kSubjectGradesPrefix = 'subject_grades_';

  /// Endpoints that get fetched together on the daily bulk refresh.
  static const _bulkKeys = [
    kProfile,
    kDashboard,
    kSubjects,
    kAttendance,
    kExamSchedule,
    kRating,
    kSchedule,
    kPendingLessons,
    kContract,
    kExcuses,
  ];

  // ── Singleton ──────────────────────────────────────────
  static final StudentDataCache _instance = StudentDataCache._();
  factory StudentDataCache() => _instance;
  StudentDataCache._();

  StudentService? _service;
  ApiService? _api;
  Future<void>? _ongoingRefresh;
  final Map<String, Future<void>> _ongoingPerEndpoint = {};
  bool _loaded = false;

  final Map<String, dynamic> _memory = {};
  final Map<String, int> _endpointTs = {}; // ms since epoch
  final Map<String, String> _errors = {};

  void attachService(StudentService service, ApiService api) {
    _service = service;
    _api = api;
  }

  /// Older API kept for the AI assistant: just the StudentService.
  // ignore: avoid_setters_without_getters
  set service(StudentService s) => _service = s;

  // ── Read API ──────────────────────────────────────────

  Map<String, dynamic>? raw(String key) =>
      _memory[key] as Map<String, dynamic>?;

  /// Returns the unwrapped `data` payload, or null if the endpoint isn't
  /// cached or its response had no `data` field.
  T? dataOf<T>(String key) {
    final res = _memory[key];
    if (res is Map<String, dynamic>) {
      final d = res['data'];
      if (d is T) return d;
    }
    return null;
  }

  String? errorFor(String key) => _errors[key];

  bool hasEndpoint(String key) => _memory.containsKey(key);

  DateTime? endpointFetchedAt(String key) {
    final ts = _endpointTs[key];
    return ts == null ? null : DateTime.fromMillisecondsSinceEpoch(ts);
  }

  bool isEndpointFresh(String key) {
    final at = endpointFetchedAt(key);
    if (at == null) return false;
    return DateTime.now().difference(at) < _validity;
  }

  /// True if every essential endpoint was fetched within [_validity].
  bool get isBundleFresh =>
      _bulkKeys.every((k) => hasEndpoint(k) && isEndpointFresh(k));

  bool get hasData => _memory.isNotEmpty;

  DateTime? get lastFetchedAt {
    final all = _endpointTs.values;
    if (all.isEmpty) return null;
    return DateTime.fromMillisecondsSinceEpoch(all.reduce((a, b) => a > b ? a : b));
  }

  // ── Typed getters (kept for the AI context builder) ──

  Map<String, dynamic>? get profile => raw(kProfile);
  Map<String, dynamic>? get dashboard => raw(kDashboard);
  Map<String, dynamic>? get subjects => raw(kSubjects);
  Map<String, dynamic>? get attendance => raw(kAttendance);
  Map<String, dynamic>? get examSchedule => raw(kExamSchedule);
  Map<String, dynamic>? get rating => raw(kRating);
  Map<String, dynamic>? get schedule => raw(kSchedule);
  Map<String, dynamic>? get pendingLessons => raw(kPendingLessons);
  Map<String, dynamic>? get contract => raw(kContract);
  Map<String, dynamic>? get excuses => raw(kExcuses);
  Map<String, dynamic>? subjectGrades(int subjectId) =>
      raw('$kSubjectGradesPrefix$subjectId');

  // ── Lifecycle ─────────────────────────────────────────

  Future<void> loadFromDisk() async {
    if (_loaded) return;
    final prefs = await SharedPreferences.getInstance();
    for (final key in prefs.getKeys()) {
      if (key.startsWith(_prefsKeyPrefix)) {
        final raw = prefs.getString(key);
        if (raw == null) continue;
        try {
          _memory[key.substring(_prefsKeyPrefix.length)] = jsonDecode(raw);
        } catch (_) {}
      } else if (key.startsWith(_tsKeyPrefix)) {
        final ts = prefs.getInt(key);
        if (ts != null) {
          _endpointTs[key.substring(_tsKeyPrefix.length)] = ts;
        }
      }
    }
    _loaded = true;
  }

  /// Ensure the cache is warm. Called from `main.dart` on auth.
  /// Returns immediately if the bundle is fresh; otherwise triggers the
  /// bulk refresh.
  Future<void> ensureFresh({bool force = false}) async {
    await loadFromDisk();
    if (!force && isBundleFresh) return;
    return refresh(force: force);
  }

  /// Re-run every bulk endpoint. Concurrent callers share one in-flight
  /// future so we never double-fetch.
  Future<void> refresh({bool force = false}) async {
    if (_service == null) return;
    if (await _api?.getToken() == null) return; // no token, skip silently
    if (_ongoingRefresh != null) return _ongoingRefresh;
    final fut = _doRefresh();
    _ongoingRefresh = fut;
    try {
      await fut;
    } finally {
      _ongoingRefresh = null;
    }
  }

  Future<void> _doRefresh() async {
    final svc = _service;
    if (svc == null) return;

    final fetchers = <String, Future<Map<String, dynamic>> Function()>{
      kProfile: svc.getProfile,
      kDashboard: svc.getDashboard,
      kSubjects: svc.getSubjects,
      kAttendance: svc.getAttendance,
      kExamSchedule: svc.getExamSchedule,
      kRating: () => svc.getRating(),
      kSchedule: svc.getSchedule,
      kPendingLessons: svc.getPendingLessons,
      kContract: svc.getContract,
      kExcuses: svc.getExcuses,
    };

    await Future.wait(fetchers.entries.map((e) async {
      await _fetchInto(e.key, e.value);
    }));

    // Also pre-fetch each subject's grades when subjects landed.
    final subjectsData = dataOf<List<dynamic>>(kSubjects);
    if (subjectsData != null) {
      await Future.wait(subjectsData.map((s) async {
        if (s is Map<String, dynamic>) {
          final id = s['subject_id'] ?? s['id'];
          if (id is int) {
            await _fetchInto(
              '$kSubjectGradesPrefix$id',
              () => svc.getSubjectGrades(id),
            );
          }
        }
      }));
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(
      _bundleTimestampKey,
      DateTime.now().millisecondsSinceEpoch,
    );
  }

  /// Pull a single endpoint into the cache (only if missing/stale or
  /// `force == true`). Used by the provider when a screen needs data
  /// that wasn't part of the bulk refresh, or to refill a slot after
  /// a previous partial failure.
  Future<Map<String, dynamic>?> getOrFetch({
    required String key,
    required Future<Map<String, dynamic>> Function() fetcher,
    bool force = false,
  }) async {
    await loadFromDisk();
    if (!force && hasEndpoint(key) && isEndpointFresh(key)) {
      return raw(key);
    }
    // Avoid double-fetching the same endpoint when a bulk refresh is
    // already running.
    if (!force && _ongoingRefresh != null) {
      await _ongoingRefresh;
      return raw(key);
    }
    // De-dup concurrent single-endpoint fetches.
    final existing = _ongoingPerEndpoint[key];
    if (existing != null && !force) {
      await existing;
      return raw(key);
    }
    final fut = _fetchInto(key, fetcher);
    _ongoingPerEndpoint[key] = fut;
    try {
      await fut;
    } finally {
      _ongoingPerEndpoint.remove(key);
    }
    return raw(key);
  }

  /// Force a single endpoint to be re-fetched on its next read. Use after
  /// a mutation (excuse submit, club join, etc.) so the new server state
  /// is picked up.
  Future<void> invalidate(List<String> keys) async {
    final prefs = await SharedPreferences.getInstance();
    for (final k in keys) {
      _memory.remove(k);
      _endpointTs.remove(k);
      _errors.remove(k);
      await prefs.remove('$_prefsKeyPrefix$k');
      await prefs.remove('$_tsKeyPrefix$k');
    }
  }

  Future<void> _fetchInto(
    String key,
    Future<Map<String, dynamic>> Function() fetcher,
  ) async {
    try {
      final res = await fetcher();
      _memory[key] = res;
      _endpointTs[key] = DateTime.now().millisecondsSinceEpoch;
      _errors.remove(key);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('$_prefsKeyPrefix$key', jsonEncode(res));
      await prefs.setInt('$_tsKeyPrefix$key', _endpointTs[key]!);
    } catch (e) {
      _errors[key] = e.toString();
      // Don't overwrite previously-good cached data on failure.
    }
  }

  /// Wipe everything (on logout).
  Future<void> clear() async {
    _memory.clear();
    _endpointTs.clear();
    _errors.clear();
    _loaded = false;
    final prefs = await SharedPreferences.getInstance();
    final toRemove = prefs.getKeys().where(
          (k) =>
              k.startsWith(_prefsKeyPrefix) ||
              k.startsWith(_tsKeyPrefix) ||
              k == _bundleTimestampKey,
        );
    for (final k in toRemove.toList()) {
      await prefs.remove(k);
    }
  }
}
