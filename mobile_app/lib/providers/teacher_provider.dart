import 'package:flutter/material.dart';
import '../services/teacher_service.dart';
import '../services/api_service.dart';

class TeacherProvider extends ChangeNotifier {
  final TeacherService _service;

  bool _isLoading = false;
  String? _error;

  Map<String, dynamic>? _dashboard;
  Map<String, dynamic>? _profile;
  List<dynamic>? _students;
  List<dynamic>? _groups;
  List<dynamic>? _semesters;
  List<dynamic>? _subjects;
  Map<String, dynamic>? _pagination;

  TeacherProvider(this._service);

  bool get isLoading => _isLoading;
  String? get error => _error;
  Map<String, dynamic>? get dashboard => _dashboard;
  Map<String, dynamic>? get profile => _profile;
  List<dynamic>? get students => _students;
  List<dynamic>? get groups => _groups;
  List<dynamic>? get semesters => _semesters;
  List<dynamic>? get subjects => _subjects;
  Map<String, dynamic>? get pagination => _pagination;

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

  Future<void> loadStudents({String? search, int page = 1}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getStudents(search: search, page: page);
      _students = response['data'] as List<dynamic>?;
      _pagination = response['meta'] as Map<String, dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadGroups() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getGroups();
      _groups = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadSemesters(int groupId) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getSemesters(groupId: groupId);
      _semesters = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadSubjects({required int groupId, required int semesterId}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getSubjects(
        groupId: groupId,
        semesterId: semesterId,
      );
      _subjects = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  void clearData() {
    _dashboard = null;
    _profile = null;
    _students = null;
    _groups = null;
    _semesters = null;
    _subjects = null;
    notifyListeners();
  }
}
