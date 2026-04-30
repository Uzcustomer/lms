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
      model: 'gemini-2.0-flash',
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
  }

  Stream<String> sendMessageStream(String message) async* {
    final response = chat.sendMessageStream(Content.text(message));
    await for (final chunk in response) {
      final text = chunk.text;
      if (text != null && text.isNotEmpty) {
        yield text;
      }
    }
  }

  Future<String> sendMessage(String message) async {
    final response = await chat.sendMessage(Content.text(message));
    return response.text ?? '';
  }
}
