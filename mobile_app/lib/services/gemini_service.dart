import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import 'api_service.dart';
import '../l10n/app_localizations.dart';

class GeminiAttachment {
  final String name;
  final String mimeType;
  final Uint8List bytes;

  const GeminiAttachment({
    required this.name,
    required this.mimeType,
    required this.bytes,
  });

  bool get isImage => mimeType.startsWith('image/');
  bool get isAudio => mimeType.startsWith('audio/');
  bool get isPdf => mimeType == 'application/pdf';
  bool get isVideo => mimeType.startsWith('video/');
}

/// Client for the "TDTU AI Yordamchi". Talks to our own backend
/// (`POST /student/ai/chat`), which holds the Gemini key and relays the
/// model's SSE stream — the app never sees the key.
class GeminiService {
  static final GeminiService _instance = GeminiService._();
  factory GeminiService() => _instance;
  GeminiService._();

  final ApiService _api = ApiService();
  String? _studentContext;

  /// Conversation so far as `{role: user|model, text}` — sent with every
  /// request so the server stays stateless.
  final List<Map<String, String>> _history = [];

  static const _connectTimeout = Duration(seconds: 30);

  void setStudentContext(String context) {
    _studentContext = context;
  }

  void resetChat() {
    _history.clear();
  }

  Stream<String> sendMessageStream(
    String message, {
    List<GeminiAttachment> attachments = const [],
  }) async* {
    final token = await _api.getToken();
    final uri = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.studentAiChat}');
    final request = http.MultipartRequest('POST', uri)
      ..headers['Accept'] = 'text/event-stream'
      ..fields['message'] = message
      ..fields['history'] = jsonEncode(_history)
      ..fields['context'] = _studentContext ?? '';
    if (token != null) {
      request.headers['Authorization'] = 'Bearer $token';
    }
    for (final att in attachments) {
      request.files.add(http.MultipartFile.fromBytes(
        'attachments[]',
        att.bytes,
        filename: att.name,
      ));
    }

    final client = http.Client();
    try {
      final http.StreamedResponse response;
      try {
        response = await client.send(request).timeout(_connectTimeout);
      } on TimeoutException {
        throw Exception(AppLocalizations.current.pick(uz: 'AI javob bermadi. Internet aloqasini tekshiring.', ru: 'ИИ не ответил. Проверьте подключение к интернету.', en: 'The AI did not respond. Check your internet connection.'));
      }

      if (response.statusCode == 401) {
        await _api.clearToken();
        throw Exception(AppLocalizations.current.sessionExpired);
      }
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw Exception(await _errorMessage(response));
      }

      final reply = StringBuffer();
      var pending = '';
      await for (final chunk in response.stream.transform(utf8.decoder)) {
        pending += chunk;
        int nl;
        while ((nl = pending.indexOf('\n')) != -1) {
          final line = pending.substring(0, nl).trimRight();
          pending = pending.substring(nl + 1);
          if (!line.startsWith('data:')) continue;
          final text = _extractText(line.substring(5).trim());
          if (text.isNotEmpty) {
            reply.write(text);
            yield text;
          }
        }
      }

      _history
        ..add({'role': 'user', 'text': message})
        ..add({'role': 'model', 'text': reply.toString()});
    } finally {
      client.close();
    }
  }

  Future<String> _errorMessage(http.StreamedResponse response) async {
    try {
      final body = jsonDecode(await response.stream.bytesToString());
      final msg = (body as Map<String, dynamic>)['message']?.toString();
      if (msg != null && msg.isNotEmpty) return msg;
    } catch (_) {}
    return switch (response.statusCode) {
      429 => AppLocalizations.current.pick(uz: 'Juda ko\'p so\'rov. Biroz kutib qayta urinib ko\'ring.', ru: 'Слишком много запросов. Подождите немного и попробуйте снова.', en: 'Too many requests. Wait a moment and try again.'),
      413 => AppLocalizations.current.pick(uz: 'Fayl hajmi juda katta. 20MB dan kichikroq fayl yuklang.', ru: 'Файл слишком большой. Загрузите файл меньше 20 МБ.', en: 'The file is too large. Upload a file under 20 MB.'),
      503 => AppLocalizations.current.pick(uz: 'AI yordamchi hozircha o\'chirilgan.', ru: 'ИИ-помощник пока отключён.', en: 'The AI assistant is currently disabled.'),
      _ => AppLocalizations.current.pick(uz: 'AI xizmatida xatolik. Keyinroq urinib ko\'ring.', ru: 'Ошибка сервиса ИИ. Попробуйте позже.', en: 'AI service error. Try again later.'),
    };
  }

  /// Pulls the text parts out of one Gemini `data:` JSON chunk.
  String _extractText(String json) {
    if (json.isEmpty || json == '[DONE]') return '';
    try {
      final obj = jsonDecode(json) as Map<String, dynamic>;
      final candidates = obj['candidates'] as List?;
      if (candidates == null || candidates.isEmpty) return '';
      final content = (candidates.first as Map)['content'] as Map?;
      final parts = content?['parts'] as List?;
      if (parts == null) return '';
      return parts.map((p) => (p as Map)['text']?.toString() ?? '').join();
    } catch (_) {
      return '';
    }
  }
}
