import 'package:flutter/material.dart';
import '../services/teacher_service.dart';
import '../services/api_service.dart';

class TeacherProvider extends ChangeNotifier {
  final TeacherService _service;

  int _inFlight = 0;
  String? _error;

  Map<String, dynamic>? _dashboard;
  Map<String, dynamic>? _profile;
  List<dynamic>? _students;
  List<dynamic>? _groups;
  List<dynamic>? _semesters;
  List<dynamic>? _subjects;
  List<dynamic>? _activeSubjects;
  Map<String, dynamic>? _pagination;

  TeacherProvider(this._service);

  bool get isLoading => _inFlight > 0;
  String? get error => _error;
  Map<String, dynamic>? get dashboard => _dashboard;
  Map<String, dynamic>? get profile => _profile;
  List<dynamic>? get students => _students;
  List<dynamic>? get groups => _groups;
  List<dynamic>? get semesters => _semesters;
  List<dynamic>? get subjects => _subjects;
  List<dynamic>? get activeSubjects => _activeSubjects;
  Map<String, dynamic>? get pagination => _pagination;

  /// Runs one loader under the shared loading/error flags. Any failure —
  /// API error, dropped connection, unexpected payload shape — ends up in
  /// [error], so the spinner can never get stuck. Several loaders may be in
  /// flight at once (dashboard + active subjects); [isLoading] stays true
  /// until the last one finishes.
  Future<void> _run(Future<void> Function() body) async {
    if (_inFlight == 0) _error = null;
    _inFlight++;
    notifyListeners();

    try {
      await body();
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
    }

    _inFlight--;
    notifyListeners();
  }

  Future<void> loadDashboard() => _run(() async {
        final response = await _service.getDashboard();
        _dashboard = response['data'] as Map<String, dynamic>?;
      });

  Future<void> loadProfile() => _run(() async {
        final response = await _service.getProfile();
        _profile = response['data'] as Map<String, dynamic>?;
      });

  Future<void> loadStudents({String? search, int page = 1}) => _run(() async {
        final response = await _service.getStudents(search: search, page: page);
        _students = response['data'] as List<dynamic>?;
        _pagination = response['meta'] as Map<String, dynamic>?;
      });

  Future<void> loadGroups() => _run(() async {
        final response = await _service.getGroups();
        _groups = response['data'] as List<dynamic>?;
      });

  Future<void> loadSemesters(int groupId) => _run(() async {
        final response = await _service.getSemesters(groupId: groupId);
        _semesters = response['data'] as List<dynamic>?;
      });

  Future<void> loadActiveSubjects() => _run(() async {
        final response = await _service.getActiveSubjects();
        _activeSubjects = response['data'] as List<dynamic>?;
      });

  Future<void> loadSubjects({required int groupId, required int semesterId}) =>
      _run(() async {
        final response = await _service.getSubjects(
          groupId: groupId,
          semesterId: semesterId,
        );
        _subjects = response['data'] as List<dynamic>?;
      });

  void clearData() {
    _dashboard = null;
    _profile = null;
    _students = null;
    _groups = null;
    _semesters = null;
    _subjects = null;
    _activeSubjects = null;
    notifyListeners();
  }
}
