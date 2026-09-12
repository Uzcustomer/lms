import 'dart:io';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'api_service.dart';

/// Fetches a protected file through the API with the bearer header (never a
/// token in the URL), saves it to the app cache and opens it with the
/// system viewer.
class FileOpenService {
  final ApiService _api;

  FileOpenService([ApiService? api]) : _api = api ?? ApiService();

  Future<void> downloadAndOpen(String endpoint, String fileName) async {
    if (kIsWeb) {
      throw ApiException('Bu funksiya web versiyada mavjud emas', 0);
    }
    final bytes = await _api.getBytes(endpoint);
    final dir = await getTemporaryDirectory();
    final file = File('${dir.path}/$fileName');
    await file.writeAsBytes(bytes, flush: true);
    final result = await OpenFilex.open(file.path);
    if (result.type != ResultType.done) {
      throw ApiException('Faylni ochish uchun mos ilova topilmadi', 0);
    }
  }
}
