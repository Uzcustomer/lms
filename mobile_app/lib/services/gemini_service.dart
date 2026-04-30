import 'package:google_generative_ai/google_generative_ai.dart';

class GeminiService {
  static const _apiKey = 'AIzaSyAIWqLr1y_ViAtzVGjev0fRRg822oAAFzc';

  static final GeminiService _instance = GeminiService._();
  factory GeminiService() => _instance;
  GeminiService._();

  GenerativeModel? _model;
  ChatSession? _chat;
  String? _studentContext;

  String _buildSystemPrompt() {
    final base = 'Sen TDTU (Toshkent Davlat Tibbiyot Universiteti) talabasining '
        'shaxsiy AI yordamchisisan. Ismingiz "TDTU AI Yordamchi". '
        'Talaba sening egang — uning ma\'lumotlari sen uchun ochiq va shaxsiy '
        'emas. Sen uning baholari, davomati, jadvali va boshqa o\'quv '
        'ma\'lumotlarini tahlil qilib, savollariga javob berishing kerak.\n\n'
        'Qoidalar:\n'
        '- O\'zbek tilida javob ber, foydalanuvchi boshqa tilda yozsa o\'sha tilda javob ber\n'
        '- Aniq, qisqa, foydali javoblar ber\n'
        '- Ma\'lumotni tahlil qilganda raqamlar va statistika bilan ko\'rsat\n'
        '- Tibbiyot, anatomiya, fiziologiya, farmakologiya bo\'yicha ham yordam ber\n'
        '- Agar ma\'lumot yetarli bo\'lmasa, qaysi sahifaga borish kerakligini tushuntir\n'
        '- "Men shaxsiy ma\'lumotlarga ega emasman" deb javob berma — ma\'lumotlar quyida\n';

    if (_studentContext == null || _studentContext!.isEmpty) {
      return '$base\n\nTalaba ma\'lumotlari hali yuklanmagan.';
    }

    return '$base\n\n=== TALABA MA\'LUMOTLARI ===\n$_studentContext\n=== MA\'LUMOTLAR TUGADI ===';
  }

  void setStudentContext(String context) {
    _studentContext = context;
    _model = null;
    _chat = null;
  }

  GenerativeModel get model {
    _model ??= GenerativeModel(
      model: 'gemini-2.5-flash',
      apiKey: _apiKey,
      systemInstruction: Content.text(_buildSystemPrompt()),
      generationConfig: GenerationConfig(
        temperature: 0.7,
        topP: 0.95,
        topK: 40,
        maxOutputTokens: 2048,
      ),
    );
    return _model!;
  }

  ChatSession get chat {
    _chat ??= model.startChat();
    return _chat!;
  }

  void resetChat() {
    _chat = null;
  }

  Stream<String> sendMessageStream(String message) async* {
    try {
      final response = chat.sendMessageStream(Content.text(message));
      await for (final chunk in response) {
        final text = chunk.text;
        if (text != null && text.isNotEmpty) {
          yield text;
        }
      }
    } on GenerativeAIException catch (e) {
      throw _friendlyError(e.message);
    }
  }

  Future<String> sendMessage(String message) async {
    try {
      final response = await chat.sendMessage(Content.text(message));
      return response.text ?? '';
    } on GenerativeAIException catch (e) {
      throw _friendlyError(e.message);
    }
  }

  String _friendlyError(String msg) {
    if (msg.contains('quota') || msg.contains('429')) {
      return 'API limit tugadi. Biroz kutib qayta urinib ko\'ring.';
    }
    if (msg.contains('API key') || msg.contains('401') || msg.contains('403')) {
      return 'API kalit noto\'g\'ri yoki faol emas.';
    }
    return msg;
  }
}
