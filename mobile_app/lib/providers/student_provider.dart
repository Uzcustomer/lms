import 'dart:typed_data';
import 'package:flutter/material.dart';
import '../services/student_service.dart';
import '../services/student_data_cache.dart';
import '../services/api_service.dart';

/// Student data provider.
///
/// Reads every screen's data through [StudentDataCache] — a 24h disk cache.
/// During the day the UI is served straight from the cache (instant, no
/// network); a refresh only happens when the cache is older than 24h or
/// when the user explicitly pulls to refresh.
class StudentProvider extends ChangeNotifier {
  final StudentService _service;

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

  // True while a specific (non-default) week is shown, so a cache sync
  // doesn't reset the schedule back to the default week.
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

  // ── Cache plumbing ───────────────────────────────────

  /// Copies the latest cached responses into the provider's fields.
  void _syncFromCache() {
    final c = StudentDataCache();
    _dashboard = c.dashboard?['data'] as Map<String, dynamic>?;
    _profile = c.profile?['data'] as Map<String, dynamic>?;
    _subjects = c.subjects?['data'] as List<dynamic>?;
    _attendance = c.attendance?['data'] as Map<String, dynamic>?;
    _pendingLessons = c.pendingLessons?['data'] as List<dynamic>?;
    _contract = c.contract?['data'] as Map<String, dynamic>?;
    _contractList = _contract?['contracts'] as List<dynamic>?;
    _excuses = c.excuses?['data'] as List<dynamic>?;
    if (!_customSchedule) {
      _schedule = c.schedule?['data'] as Map<String, dynamic>?;
    }
  }

  /// Ensures the cache is warm and mirrors it into the provider.
  /// [force] = true re-runs every API call (pull-to-refresh).
  Future<void> _ensureLoaded({bool force = false}) async {
    final cache = StudentDataCache();
    await cache.loadFromDisk();
    final hasAny = cache.hasData;

    // Show disk data immediately; only show a spinner on a truly cold start.
    if (hasAny) _syncFromCache();
    if (!hasAny || force) {
      _isLoading = true;
    }
    notifyListeners();

    try {
      if (force) {
        await cache.refresh();
      } else {
        await cache.ensureFresh();
      }
    } catch (_) {}

    _syncFromCache();
    _isLoading = false;
    notifyListeners();
  }

  /// Pull-to-refresh — forces a full re-fetch of every endpoint.
  Future<void> refreshAll() => _ensureLoaded(force: true);

  // ── Cache-backed loaders ─────────────────────────────

  Future<void> loadDashboard({bool force = false}) =>
      _ensureLoaded(force: force);

  Future<void> loadProfile({bool force = false}) =>
      _ensureLoaded(force: force);

  Future<void> loadContract({bool force = false}) =>
      _ensureLoaded(force: force);

  Future<void> loadPendingLessons({bool force = false}) =>
      _ensureLoaded(force: force);

  Future<void> loadSubjects({String? semesterCode, bool force = false}) async {
    if (semesterCode == null) {
      _customSchedule = false;
      return _ensureLoaded(force: force);
    }
    // Specific semester — bypass the cache.
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final response = await _service.getSubjects(semesterCode: semesterCode);
      _subjects = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadSchedule({String? semesterId, String? weekId}) async {
    if (semesterId == null && weekId == null) {
      _customSchedule = false;
      return _ensureLoaded();
    }
    // Week / semester navigation — always a fresh call.
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
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadAttendance({String? semesterCode}) async {
    if (semesterCode == null) return _ensureLoaded();
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final response = await _service.getAttendance(semesterCode: semesterCode);
      _attendance = response['data'] as Map<String, dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<Map<String, dynamic>> saveTelegram(String telegramUsername) async {
    final response = await _service.saveTelegram(telegramUsername);
    return response;
  }

  Future<Map<String, dynamic>> checkTelegramVerification() async {
    final response = await _service.checkTelegramVerification();
    return response;
  }

  // Absence excuse methods
  List<dynamic>? _excuses;
  List<dynamic>? _excuseReasons;

  List<dynamic>? get excuses => _excuses;
  List<dynamic>? get excuseReasons => _excuseReasons;

  Future<void> loadExcuseReasons() async {
    try {
      final response = await _service.getExcuseReasons();
      _excuseReasons = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }
    notifyListeners();
  }

  Future<void> loadExcuses({bool force = false}) => _ensureLoaded(force: force);

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
    return await _service.submitExcuse(
      reason: reason,
      docNumber: docNumber,
      startDate: startDate,
      endDate: endDate,
      description: description,
      fileBytes: fileBytes,
      fileName: fileName,
      makeupDates: makeupDates,
    );
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
