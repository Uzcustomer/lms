import 'package:flutter/material.dart';
import '../services/student_service.dart';
import '../services/api_service.dart';

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

  StudentProvider(this._service);

  bool get isLoading => _isLoading;
  String? get error => _error;
  Map<String, dynamic>? get dashboard => _dashboard;
  Map<String, dynamic>? get profile => _profile;
  Map<String, dynamic>? get schedule => _schedule;
  List<dynamic>? get subjects => _subjects;
  Map<String, dynamic>? get attendance => _attendance;
  List<dynamic>? get pendingLessons => _pendingLessons;

  Future<void> loadDashboard() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getDashboard();
      _dashboard = response['data'] as Map<String, dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadProfile() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getProfile();
      _profile = response['data'] as Map<String, dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadSchedule({String? semesterCode, String? week}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getSchedule(
        semesterCode: semesterCode,
        week: week,
      );
      _schedule = response['data'] as Map<String, dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadSubjects({String? semesterCode}) async {
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

  Future<void> loadAttendance({String? semesterCode}) async {
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

  Future<void> loadPendingLessons() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getPendingLessons();
      _pendingLessons = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  void clearData() {
    _dashboard = null;
    _profile = null;
    _schedule = null;
    _subjects = null;
    _attendance = null;
    _pendingLessons = null;
    notifyListeners();
  }
}
