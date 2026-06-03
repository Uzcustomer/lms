import 'dart:typed_data';
import 'package:google_generative_ai/google_generative_ai.dart';
import '../config/api_keys.dart';

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

class GeminiService {
  static const _apiKey = ApiKeys.geminiApiKey;

  static final GeminiService _instance = GeminiService._();
  factory GeminiService() => _instance;
  GeminiService._();

  GenerativeModel? _model;
  ChatSession? _chat;
  String? _studentContext;

  String _buildSystemPrompt() {
    final now = DateTime.now();
    const months = [
      'yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun',
      'iyul', 'avgust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'
    ];
    const weekdays = [
      'dushanba', 'seshanba', 'chorshanba', 'payshanba',
      'juma', 'shanba', 'yakshanba'
    ];
    final today = '${now.year}-yil ${now.day}-${months[now.month - 1]} '
        '(${weekdays[now.weekday - 1]}), ${now.hour.toString().padLeft(2, '0')}:'
        '${now.minute.toString().padLeft(2, '0')}';
    final isoToday =
        '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';

    final base = 'Sen TDTU (Toshkent Davlat Tibbiyot Universiteti) talabasining '
        'shaxsiy AI yordamchisisan. Ismingiz "TDTU AI Yordamchi". '
        'Talaba sening egang — uning ma\'lumotlari sen uchun ochiq va shaxsiy '
        'emas. Sen uning baholari, davomati, jadvali va boshqa o\'quv '
        'ma\'lumotlarini tahlil qilib, savollariga javob berishing kerak.\n\n'
        '⚠️ BUGUNGI SANA: $today\n'
        'ISO format: $isoToday\n'
        'Bu sanani DOIMO eslab qol. O\'tib ketgan sanalar haqida "yaqin keladigan" '
        'yoki "hozirda muhim" deb gapirma. Faqat $isoToday dan KEYINGI sanalar '
        'kelajakda hisoblanadi. Ma\'lumotlardagi har bir sanani bugungi sana '
        'bilan solishtir va to\'g\'ri xulosa qil.\n\n'
        'Qoidalar:\n'
        '- O\'zbek tilida javob ber, foydalanuvchi boshqa tilda yozsa o\'sha tilda javob ber\n'
        '- Aniq, qisqa, foydali javoblar ber\n'
        '- Ma\'lumotni tahlil qilganda raqamlar va statistika bilan ko\'rsat\n'
        '- Tibbiyot, anatomiya, fiziologiya, farmakologiya bo\'yicha ham yordam ber\n'
        '- Foydalanuvchi rasm, PDF, audio yoki video yuborsa, uni diqqat bilan tahlil qil\n'
        '- Agar ma\'lumot yetarli bo\'lmasa, qaysi sahifaga borish kerakligini tushuntir\n'
        '- HECH QACHON "Men shaxsiy ma\'lumotlarga ega emasman" deb javob berma — '
        'barcha ma\'lumotlar QUYIDA berilgan. Har bir fan nomi, bahosi, davomati bor\n'
        '- Talaba baholarini so\'rasa, quyidagi "FANLAR VA BAHOLAR" bo\'limidagi har bir '
        'fanni JN, MT, ON, OSKI, TEST, YN ballari bilan batafsil ko\'rsat\n'
        '- Imtihon/dars/muddat haqida gapirsang [O\'TGAN] yoki [KELGUSI] yorlig\'iga '
        'qarab tahlil qil. O\'tgan voqealarni tavsiya qilma\n'
        '- Baholarni tahlil qilganda eng past va eng yuqori baholarni aniqlash, '
        'diqqat qilish kerak bo\'lgan fanlarni tavsiya qilish, GPA ni hisoblash '
        'va umumiy tahlil ber\n';

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

  Content _buildContent(String message, List<GeminiAttachment> attachments) {
    if (attachments.isEmpty) return Content.text(message);

    final parts = <Part>[];
    for (final att in attachments) {
      parts.add(DataPart(att.mimeType, att.bytes));
    }
    if (message.isNotEmpty) {
      parts.add(TextPart(message));
    }
    return Content.multi(parts);
  }

  Stream<String> sendMessageStream(
    String message, {
    List<GeminiAttachment> attachments = const [],
  }) async* {
    try {
      final content = _buildContent(message, attachments);
      final response = chat.sendMessageStream(content);
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

  Future<String> sendMessage(
    String message, {
    List<GeminiAttachment> attachments = const [],
  }) async {
    try {
      final content = _buildContent(message, attachments);
      final response = await chat.sendMessage(content);
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
    if (msg.contains('size') || msg.contains('too large')) {
      return 'Fayl hajmi juda katta. 20MB dan kichikroq fayl yuklang.';
    }
    return msg;
  }
}
