import 'dart:typed_data';
import 'package:flutter/material.dart';
import '../services/student_service.dart';
import '../services/student_data_cache.dart';
import '../services/api_service.dart';

/// Student data provider — reads from the shared [StudentDataCache] and
/// only hits the network on a cache miss (or when the screen explicitly
/// asks to force a refresh, e.g. pull-to-refresh).
///
/// Pattern per load* method:
///   1. Sync cached value into provider fields and notify immediately.
///   2. Ask the cache to fetch the endpoint if missing/stale (or forced).
///   3. Re-sync, surface any error, notify again.
///
/// Screens see instant data on warm starts, and a single quick fetch
/// only when something is genuinely missing.
class StudentProvider extends ChangeNotifier {
  final StudentService _service;
  final StudentDataCache _cache = StudentDataCache();

  bool _isLoading = false;
  String? _error;

  Map<String, dynamic>? _dashboard;
  Map<String, dynamic>? _profile;
  Map<String, dynamic>? _schedule;
  List<dynamic>? _subjects;
  Map<String, dynamic>? _attendance;
  List<dynamic>? _pendingLessons;
  Map<String, dynamic>? _contract;
  List<dynamic>? _contractList;
  List<dynamic>? _excuses;
  List<dynamic>? _excuseReasons;

  /// True while a specific (non-default) week or semester is shown, so a
  /// cache sync doesn't reset the schedule back to the default week.
  bool _customSchedule = false;

  StudentProvider(this._service);

  bool get isLoading => _isLoading;
  String? get error => _error;
  Map<String, dynamic>? get dashboard => _dashboard;
  Map<String, dynamic>? get profile => _profile;
  Map<String, dynamic>? get schedule => _schedule;
  List<dynamic>? get subjects => _subjects;
  Map<String, dynamic>? get attendance => _attendance;
  List<dynamic>? get pendingLessons => _pendingLessons;
  Map<String, dynamic>? get contract => _contract;
  List<dynamic>? get contractList => _contractList;
  List<dynamic>? get excuses => _excuses;
  List<dynamic>? get excuseReasons => _excuseReasons;

  // ── Sync helpers ───────────────────────────────────────

  void _syncDashboard() => _dashboard = _cache.dataOf<Map<String, dynamic>>(StudentDataCache.kDashboard);
  void _syncProfile() => _profile = _cache.dataOf<Map<String, dynamic>>(StudentDataCache.kProfile);
  void _syncSubjects() => _subjects = _cache.dataOf<List<dynamic>>(StudentDataCache.kSubjects);
  void _syncAttendance() => _attendance = _cache.dataOf<Map<String, dynamic>>(StudentDataCache.kAttendance);
  void _syncPendingLessons() => _pendingLessons = _cache.dataOf<List<dynamic>>(StudentDataCache.kPendingLessons);
  void _syncContract() {
    _contract = _cache.dataOf<Map<String, dynamic>>(StudentDataCache.kContract);
    _contractList = _contract?['contracts'] as List<dynamic>?;
  }
  void _syncExcuses() => _excuses = _cache.dataOf<List<dynamic>>(StudentDataCache.kExcuses);
  void _syncSchedule() {
    if (_customSchedule) return;
    _schedule = _cache.dataOf<Map<String, dynamic>>(StudentDataCache.kSchedule);
  }

  /// Generic load: hydrate from cache, optionally trigger a fetch, surface
  /// errors. The [syncer] copies cache → provider fields.
  Future<void> _loadEndpoint({
    required String key,
    required Future<Map<String, dynamic>> Function() fetcher,
    required VoidCallback syncer,
    bool force = false,
  }) async {
    await _cache.loadFromDisk();

    final hadCached = _cache.hasEndpoint(key);
    syncer();
    if (!hadCached) {
      _isLoading = true;
    }
    notifyListeners();

    try {
      await _cache.getOrFetch(key: key, fetcher: fetcher, force: force);
    } catch (_) {/* cache stored the error */}

    syncer();
    final err = _cache.errorFor(key);
    if (err != null && !_cache.hasEndpoint(key)) {
      _error = _humanizeError(err);
    } else {
      _error = null;
    }
    _isLoading = false;
    notifyListeners();
  }

  String _humanizeError(String raw) {
    if (raw.contains('SocketException') ||
        raw.contains('TimeoutException') ||
        raw.contains('Tarmoq')) {
      return 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
    }
    return raw.replaceFirst('Exception: ', '');
  }

  // ── Public load methods ────────────────────────────────

  Future<void> loadDashboard({bool force = false}) => _loadEndpoint(
        key: StudentDataCache.kDashboard,
        fetcher: _service.getDashboard,
        syncer: _syncDashboard,
        force: force,
      );

  Future<void> loadProfile({bool force = false}) => _loadEndpoint(
        key: StudentDataCache.kProfile,
        fetcher: _service.getProfile,
        syncer: _syncProfile,
        force: force,
      );

  Future<void> loadContract({bool force = false}) => _loadEndpoint(
        key: StudentDataCache.kContract,
        fetcher: _service.getContract,
        syncer: _syncContract,
        force: force,
      );

  Future<void> loadPendingLessons({bool force = false}) => _loadEndpoint(
        key: StudentDataCache.kPendingLessons,
        fetcher: _service.getPendingLessons,
        syncer: _syncPendingLessons,
        force: force,
      );

  Future<void> loadSubjects({String? semesterCode, bool force = false}) async {
    if (semesterCode == null) {
      return _loadEndpoint(
        key: StudentDataCache.kSubjects,
        fetcher: _service.getSubjects,
        syncer: _syncSubjects,
        force: force,
      );
    }
    // Specific semester — bypass cache (cache only holds the default).
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final response = await _service.getSubjects(semesterCode: semesterCode);
      _subjects = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadAttendance({String? semesterCode}) async {
    if (semesterCode == null) {
      return _loadEndpoint(
        key: StudentDataCache.kAttendance,
        fetcher: _service.getAttendance,
        syncer: _syncAttendance,
      );
    }
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final response = await _service.getAttendance(semesterCode: semesterCode);
      _attendance = response['data'] as Map<String, dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadSchedule({String? semesterId, String? weekId}) async {
    if (semesterId == null && weekId == null) {
      _customSchedule = false;
      return _loadEndpoint(
        key: StudentDataCache.kSchedule,
        fetcher: _service.getSchedule,
        syncer: _syncSchedule,
      );
    }
    // Week / semester navigation — always a fresh call, never cached.
    _customSchedule = true;
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final response = await _service.getSchedule(
        semesterId: semesterId,
        weekId: weekId,
      );
      _schedule = response['data'] as Map<String, dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadExcuses({bool force = false}) => _loadEndpoint(
        key: StudentDataCache.kExcuses,
        fetcher: _service.getExcuses,
        syncer: _syncExcuses,
        force: force,
      );

  Future<void> loadExcuseReasons() async {
    // Small reference list — fetch once per app launch, no disk cache.
    if (_excuseReasons != null) return;
    try {
      final response = await _service.getExcuseReasons();
      _excuseReasons = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }
    notifyListeners();
  }

  /// Pull-to-refresh — forces a full re-fetch of every bulk endpoint.
  Future<void> refreshAll() async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      await _cache.refresh(force: true);
    } catch (_) {}
    _syncDashboard();
    _syncProfile();
    _syncSubjects();
    _syncAttendance();
    _syncPendingLessons();
    _syncContract();
    _syncExcuses();
    _syncSchedule();
    _isLoading = false;
    notifyListeners();
  }

  // ── Mutations ──────────────────────────────────────────

  Future<Map<String, dynamic>> saveTelegram(String telegramUsername) async {
    final response = await _service.saveTelegram(telegramUsername);
    await _cache.invalidate([StudentDataCache.kProfile]);
    return response;
  }

  Future<Map<String, dynamic>> checkTelegramVerification() async {
    return await _service.checkTelegramVerification();
  }

  Future<Map<String, dynamic>> getExcuseDetail(int id) async {
    return await _service.getExcuseDetail(id);
  }

  Future<Map<String, dynamic>> getMissedAssessments(
      String startDate, String endDate) async {
    return await _service.getMissedAssessments(startDate, endDate);
  }

  Future<Map<String, dynamic>> submitExcuse({
    required String reason,
    required String docNumber,
    required String startDate,
    required String endDate,
    String? description,
    required Uint8List fileBytes,
    required String fileName,
    List<Map<String, dynamic>>? makeupDates,
  }) async {
    final res = await _service.submitExcuse(
      reason: reason,
      docNumber: docNumber,
      startDate: startDate,
      endDate: endDate,
      description: description,
      fileBytes: fileBytes,
      fileName: fileName,
      makeupDates: makeupDates,
    );
    await _cache.invalidate([StudentDataCache.kExcuses]);
    return res;
  }

  void clearData() {
    _dashboard = null;
    _profile = null;
    _schedule = null;
    _subjects = null;
    _attendance = null;
    _pendingLessons = null;
    _contract = null;
    _contractList = null;
    _excuses = null;
    _excuseReasons = null;
    _customSchedule = false;
    notifyListeners();
  }
}
