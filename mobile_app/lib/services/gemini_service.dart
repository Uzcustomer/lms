import 'package:google_generative_ai/google_generative_ai.dart';

class GeminiService {
  static const _apiKey = 'AIzaSyAIWqLr1y_ViAtzVGjev0fRRg822oAAFzc';

  static final GeminiService _instance = GeminiService._();
  factory GeminiService() => _instance;
  GeminiService._();

  GenerativeModel? _model;
  ChatSession? _chat;

  GenerativeModel get model {
    _model ??= GenerativeModel(
      model: 'gemini-2.5-flash',
      apiKey: _apiKey,
      systemInstruction: Content.text(
        'Sen TDTU (Toshkent Davlat Tibbiyot Universiteti) talabalariga '
        'yordam beruvchi AI assistantisan. Ismingiz "TDTU AI Yordamchi". '
        'Tibbiyot fanlari, anatomiya, fiziologiya, farmakologiya va boshqa '
        'tibbiy mavzularda savolarga javob bering. '
        'O\'zbek tilida javob bering, lekin foydalanuvchi boshqa tilda yozsa '
        'o\'sha tilda javob bering. Javoblaringiz aniq, qisqa va foydali bo\'lsin. '
        'Matematika, fizika, kimyo va boshqa fanlar bo\'yicha ham yordam bera olasiz.',
      ),
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
    _model = null;
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
      return 'API limit tugadi. Google AI Studio sahifasida billing yoqilganligini tekshiring yoki biroz kutib qayta urinib ko\'ring.';
    }
    if (msg.contains('API key') || msg.contains('401') || msg.contains('403')) {
      return 'API kalit noto\'g\'ri yoki faol emas. Google AI Studio sahifasida kalitni tekshiring.';
    }
    return msg;
  }
}
