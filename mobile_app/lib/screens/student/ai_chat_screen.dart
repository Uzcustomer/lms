import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../l10n/app_localizations.dart';
import '../../services/gemini_service.dart';
import '../../services/student_context_builder.dart';
import '../../services/student_data_cache.dart';
import '../../widgets/clinic_header.dart';
import '../../widgets/notification_bell.dart';

class AiChatScreen extends StatefulWidget {
  const AiChatScreen({super.key});

  @override
  State<AiChatScreen> createState() => _AiChatScreenState();
}

class _AiChatScreenState extends State<AiChatScreen>
    with TickerProviderStateMixin {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  final _focusNode = FocusNode();
  final _gemini = GeminiService();
  final List<_ChatMessage> _messages = [];
  final List<GeminiAttachment> _pendingAttachments = [];
  bool _isStreaming = false;
  bool _contextLoading = true;
  bool _contextLoaded = false;
  bool _picking = false;

  // AI brand gradient.
  static const _aiA = Color(0xFF4338CA);
  static const _aiB = Color(0xFF7C3AED);

  static const _maxFileSize = 18 * 1024 * 1024;
  static const _imageExt = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
  static const _audioExt = ['mp3', 'wav', 'aiff', 'aac', 'ogg', 'flac', 'm4a'];
  static const _videoExt = ['mp4', 'mpeg', 'mov', 'avi', 'webm', '3gp'];

  @override
  void initState() {
    super.initState();
    _loadStudentContext();
  }

  Future<void> _loadStudentContext({bool force = false}) async {
    if (mounted) {
      setState(() {
        _contextLoading = true;
        _contextLoaded = false;
      });
    }
    try {
      final cache = StudentDataCache();
      await cache.ensureFresh(force: force);
      final builder = StudentContextBuilder(cache);
      final ctx = builder.build();
      _gemini.setStudentContext(ctx);
      if (mounted) {
        setState(() {
          _contextLoading = false;
          _contextLoaded = cache.hasData;
        });
        if (!cache.hasData) {
          await cache.refresh();
          if (mounted && cache.hasData) {
            final ctx2 = StudentContextBuilder(cache).build();
            _gemini.setStudentContext(ctx2);
            setState(() => _contextLoaded = true);
          }
        }
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _contextLoading = false;
          _contextLoaded = false;
        });
      }
    }
  }

  Future<void> _refreshData() async {
    if (_contextLoading) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Ma\'lumotlar yangilanmoqda...'),
        duration: Duration(seconds: 2),
      ),
    );
    await _loadStudentContext(force: true);
    if (!mounted) return;
    final cache = StudentDataCache();
    final ts = cache.lastFetchedAt;
    final tsStr = ts == null
        ? ''
        : ' (${ts.hour.toString().padLeft(2, '0')}:${ts.minute.toString().padLeft(2, '0')})';
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(_contextLoaded
            ? 'Ma\'lumotlar yangilandi$tsStr'
            : 'Yangilashda xatolik'),
        duration: const Duration(seconds: 2),
      ),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 150),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    final hasAttachments = _pendingAttachments.isNotEmpty;
    if ((text.isEmpty && !hasAttachments) || _isStreaming || _contextLoading) {
      return;
    }

    final attachments = List<GeminiAttachment>.from(_pendingAttachments);
    final messageText = text.isEmpty ? 'Yuborilgan faylni tahlil qiling' : text;

    setState(() {
      _messages.add(_ChatMessage(
        text: text,
        isUser: true,
        attachments: attachments,
      ));
      _messages.add(_ChatMessage(text: '', isUser: false));
      _isStreaming = true;
      _pendingAttachments.clear();
    });
    _controller.clear();
    _scrollToBottom();

    try {
      final aiIndex = _messages.length - 1;
      await for (final chunk in _gemini.sendMessageStream(
        messageText,
        attachments: attachments,
      )) {
        if (!mounted) return;
        setState(() {
          _messages[aiIndex] = _ChatMessage(
            text: _messages[aiIndex].text + chunk,
            isUser: false,
          );
        });
        _scrollToBottom();
      }
    } catch (e) {
      if (mounted) {
        final aiIndex = _messages.length - 1;
        final errMsg = e.toString().replaceFirst('Exception: ', '');
        setState(() {
          _messages[aiIndex] = _ChatMessage(
            text: errMsg.length > 200 ? '${errMsg.substring(0, 200)}...' : errMsg,
            isUser: false,
            isError: true,
          );
        });
        _gemini.resetChat();
      }
    } finally {
      if (mounted) setState(() => _isStreaming = false);
    }
  }

  Future<void> _pickFile(FileType type, {List<String>? extensions}) async {
    if (_picking) return;
    setState(() => _picking = true);
    try {
      final result = await FilePicker.platform.pickFiles(
        type: type,
        allowedExtensions: extensions,
        withData: true,
        allowMultiple: false,
      );
      if (result == null || result.files.isEmpty) return;

      final file = result.files.first;
      Uint8List? bytes = file.bytes;
      if (bytes == null) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Faylni o\'qib bo\'lmadi')),
          );
        }
        return;
      }

      if (bytes.length > _maxFileSize) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
                content: Text('Fayl hajmi 18MB dan katta. Kichikroq fayl tanlang')),
          );
        }
        return;
      }

      final ext = (file.extension ?? '').toLowerCase();
      final mimeType = _mimeFromExtension(ext);

      if (mounted) {
        setState(() {
          _pendingAttachments.add(GeminiAttachment(
            name: file.name,
            mimeType: mimeType,
            bytes: bytes!,
          ));
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Xatolik: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  String _mimeFromExtension(String ext) {
    switch (ext) {
      case 'jpg':
      case 'jpeg':
        return 'image/jpeg';
      case 'png':
        return 'image/png';
      case 'webp':
        return 'image/webp';
      case 'heic':
        return 'image/heic';
      case 'heif':
        return 'image/heif';
      case 'pdf':
        return 'application/pdf';
      case 'mp3':
        return 'audio/mp3';
      case 'wav':
        return 'audio/wav';
      case 'aiff':
        return 'audio/aiff';
      case 'aac':
        return 'audio/aac';
      case 'ogg':
        return 'audio/ogg';
      case 'flac':
        return 'audio/flac';
      case 'm4a':
        return 'audio/mp4';
      case 'mp4':
        return 'video/mp4';
      case 'mpeg':
        return 'video/mpeg';
      case 'mov':
        return 'video/mov';
      case 'avi':
        return 'video/avi';
      case 'webm':
        return 'video/webm';
      case '3gp':
        return 'video/3gpp';
      case 'txt':
        return 'text/plain';
      case 'md':
        return 'text/markdown';
      case 'csv':
        return 'text/csv';
      default:
        return 'application/octet-stream';
    }
  }

  void _showAttachMenu() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          padding: EdgeInsets.only(
            top: 16,
            bottom: MediaQuery.of(ctx).padding.bottom + 16,
          ),
          decoration: BoxDecoration(
            color: ClinicTheme.surfaceOf(context),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 38,
                height: 4,
                decoration: BoxDecoration(
                  color: ClinicTheme.dividerOf(context),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 18),
              _attachOption(Icons.image_outlined, 'Rasm', 'JPG, PNG, WEBP',
                  const Color(0xFF1D4ED8), () {
                Navigator.pop(ctx);
                _pickFile(FileType.custom, extensions: _imageExt);
              }),
              _attachOption(Icons.picture_as_pdf_outlined, 'PDF',
                  'Hujjatlar va kitoblar', const Color(0xFFBE123C), () {
                Navigator.pop(ctx);
                _pickFile(FileType.custom, extensions: ['pdf']);
              }),
              _attachOption(Icons.audiotrack_outlined, 'Audio',
                  'MP3, WAV, M4A', const Color(0xFFB45309), () {
                Navigator.pop(ctx);
                _pickFile(FileType.custom, extensions: _audioExt);
              }),
              _attachOption(Icons.videocam_outlined, 'Video',
                  'MP4, MOV, WEBM', const Color(0xFF7C3AED), () {
                Navigator.pop(ctx);
                _pickFile(FileType.custom, extensions: _videoExt);
              }),
              _attachOption(Icons.insert_drive_file_outlined, 'Boshqa fayl',
                  'TXT, CSV, MD', const Color(0xFF0F766E), () {
                Navigator.pop(ctx);
                _pickFile(FileType.any);
              }),
            ],
          ),
        );
      },
    );
  }

  Widget _attachOption(
      IconData icon, String title, String subtitle, Color color, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 11),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: color,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: Colors.white, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      style: TextStyle(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w800,
                          color: ClinicTheme.inkOf(context))),
                  const SizedBox(height: 2),
                  Text(subtitle,
                      style: TextStyle(
                          fontSize: 11.5, color: ClinicTheme.mutedOf(context))),
                ],
              ),
            ),
            Icon(Icons.arrow_forward_ios_rounded,
                size: 14, color: ClinicTheme.faint),
          ],
        ),
      ),
    );
  }

  void _removeAttachment(int index) {
    setState(() => _pendingAttachments.removeAt(index));
  }

  void _clearChat() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Chatni tozalash'),
        content: const Text('Barcha xabarlar o\'chiriladi. Davom etasizmi?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Bekor qilish'),
          ),
          TextButton(
            onPressed: () {
              Navigator.pop(ctx);
              setState(() {
                _messages.clear();
                _gemini.resetChat();
              });
            },
            child: const Text('Tozalash', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          _buildHeader(),
          Expanded(
            child: _messages.isEmpty
                ? _buildWelcome()
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
                    itemCount: _messages.length,
                    itemBuilder: (_, i) => _buildBubble(_messages[i]),
                  ),
          ),
          _buildInput(),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    final l = AppLocalizations.of(context);
    final statusBarH = MediaQuery.of(context).padding.top;
    final ink = ClinicTheme.inkOf(context);
    return Container(
      padding: EdgeInsets.fromLTRB(14, statusBarH + 10, 14, 12),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        border: Border(
          bottom: BorderSide(color: ClinicTheme.dividerOf(context), width: 1),
        ),
      ),
      child: Row(
        children: [
          ClinicIconButton(
            icon: Icons.arrow_back_rounded,
            onTap: () => Navigator.pop(context),
          ),
          const SizedBox(width: 11),
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [_aiA, _aiB]),
              borderRadius: BorderRadius.circular(11),
            ),
            child: const Icon(Icons.auto_awesome, color: Colors.white, size: 19),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  l.pick(uz: 'AI Yordamchi', ru: 'AI помощник', en: 'AI Assistant'),
                  style: TextStyle(
                      fontSize: 15, fontWeight: FontWeight.w700, color: ink),
                ),
                Row(
                  children: [
                    if (_contextLoading) ...[
                      SizedBox(
                        width: 8,
                        height: 8,
                        child: CircularProgressIndicator(
                            strokeWidth: 1.5, color: ClinicTheme.mutedOf(context)),
                      ),
                      const SizedBox(width: 6),
                      Text(l.pick(
                          uz: 'Ma\'lumot yuklanmoqda...',
                          ru: 'Данные загружаются...',
                          en: 'Loading data...'),
                          style: TextStyle(
                              fontSize: 10.5, color: ClinicTheme.mutedOf(context))),
                    ] else if (_contextLoaded) ...[
                      const Icon(Icons.check_circle_rounded,
                          size: 11, color: ClinicTheme.green),
                      const SizedBox(width: 4),
                      Text(l.pick(
                          uz: 'Ma\'lumotlaringiz bilan tayyor',
                          ru: 'Готов с вашими данными',
                          en: 'Ready with your data'),
                          style: TextStyle(
                              fontSize: 10.5, color: ClinicTheme.mutedOf(context))),
                    ] else
                      Text('Gemini · TDTU',
                          style: TextStyle(
                              fontSize: 11, color: ClinicTheme.mutedOf(context))),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          const NotificationBell(),
          const SizedBox(width: 8),
          ClinicIconButton(
            icon: Icons.refresh_rounded,
            onTap: () {
              if (!_contextLoading) _refreshData();
            },
          ),
          const SizedBox(width: 8),
          ClinicIconButton(
            icon: Icons.delete_outline_rounded,
            onTap: () {
              if (_messages.isNotEmpty) _clearChat();
            },
          ),
        ],
      ),
    );
  }

  Widget _buildWelcome() {
    final l = AppLocalizations.of(context);
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);

    final suggestions = _contextLoaded
        ? [
            _Suggestion(
                Icons.bar_chart_rounded,
                l.pick(uz: 'Mening baholarim', ru: 'Мои оценки', en: 'My grades'),
                l.pick(
                    uz: 'Barcha fanlardan baholarimni umumlashtirib bering',
                    ru: 'Обобщите мои оценки по всем предметам',
                    en: 'Summarize my grades across all subjects')),
            _Suggestion(
                Icons.trending_up_rounded,
                l.pick(
                    uz: 'Eng yaxshi/yomon fanim',
                    ru: 'Лучший/сложный предмет',
                    en: 'Best/worst subject'),
                l.pick(
                    uz: 'Qaysi fanda eng yaxshi va qaysida yomon natija bor?',
                    ru: 'По какому предмету у меня лучший и худший результат?',
                    en: 'Which subject is my best and which is my weakest?')),
            _Suggestion(
                Icons.warning_amber_rounded,
                l.pick(
                    uz: 'Diqqat qilishim kerak',
                    ru: 'На что обратить внимание',
                    en: 'Needs attention'),
                l.pick(
                    uz: 'Qaysi fanlarga ko\'proq e\'tibor berishim kerak?',
                    ru: 'Каким предметам мне нужно уделить больше внимания?',
                    en: 'Which subjects should I focus on more?')),
            _Suggestion(
                Icons.calendar_month_rounded,
                l.pick(uz: 'Imtihon jadvalim', ru: 'Мое расписание экзаменов', en: 'My exam schedule'),
                l.pick(
                    uz: 'Yaqinlashayotgan imtihonlarim qachon?',
                    ru: 'Когда мои ближайшие экзамены?',
                    en: 'When are my upcoming exams?')),
            _Suggestion(
                Icons.event_available_rounded,
                l.pick(uz: 'Davomatim', ru: 'Моя посещаемость', en: 'My attendance'),
                l.pick(
                    uz: 'Davomat statistikasini tahlil qiling',
                    ru: 'Проанализируйте мою посещаемость',
                    en: 'Analyze my attendance statistics')),
            _Suggestion(
                Icons.lightbulb_outline_rounded,
                l.pick(uz: 'Maslahat bering', ru: 'Дайте совет', en: 'Give advice'),
                l.pick(
                    uz: 'Reytingimni yaxshilash uchun nima qilishim kerak?',
                    ru: 'Что мне сделать, чтобы улучшить рейтинг?',
                    en: 'What should I do to improve my ranking?')),
          ]
        : [
            _Suggestion(Icons.science_outlined, 'Anatomiya',
                l.pick(uz: 'Yurak tuzilishi haqida tushuntiring', ru: 'Объясните строение сердца', en: 'Explain the structure of the heart')),
            _Suggestion(Icons.medication_outlined, 'Farmakologiya',
                l.pick(uz: 'Antibiotiklar klassifikatsiyasi', ru: 'Классификация антибиотиков', en: 'Classification of antibiotics')),
            _Suggestion(Icons.biotech_outlined, 'Fiziologiya',
                l.pick(uz: 'Qon aylanish doiralari', ru: 'Круги кровообращения', en: 'Circles of blood circulation')),
            _Suggestion(Icons.school_outlined, l.pick(uz: 'Imtihon', ru: 'Экзамен', en: 'Exam'),
                l.pick(uz: 'Patologik anatomiyadan savollar', ru: 'Вопросы по патологической анатомии', en: 'Pathological anatomy questions')),
          ];

    return SingleChildScrollView(
      padding: const EdgeInsets.all(18),
      child: Column(
        children: [
          const SizedBox(height: 26),
          Container(
            width: 80,
            height: 80,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [_aiA, _aiB],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(24),
              boxShadow: [
                BoxShadow(
                  color: _aiA.withOpacity(0.35),
                  blurRadius: 20,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: const Icon(Icons.auto_awesome, color: Colors.white, size: 40),
          ),
          const SizedBox(height: 18),
          Text(
            l.pick(uz: 'TDTU AI Yordamchi', ru: 'AI помощник TDTU', en: 'TDTU AI Assistant'),
            style: TextStyle(
                fontSize: 21, fontWeight: FontWeight.w900, color: ink),
          ),
          const SizedBox(height: 8),
          Text(
            l.pick(
              uz: 'Tibbiyot fanlari va o\'quv jarayonida\nsizga yordam berishga tayyorman!',
              ru: 'Готов помочь вам с медицинскими дисциплинами\nи учебным процессом!',
              en: 'Ready to help with medical subjects\nand your learning process!',
            ),
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: muted, height: 1.5),
          ),
          const SizedBox(height: 24),
          ...suggestions.map((s) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _buildSuggestionCard(s),
              )),
        ],
      ),
    );
  }

  Widget _buildSuggestionCard(_Suggestion s) {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);

    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () {
          _controller.text = s.prompt;
          _send();
        },
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
          decoration: BoxDecoration(
            color: ClinicTheme.surfaceOf(context),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0F172A).withAlpha(16),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: ClinicTheme.teal.withAlpha(18),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Icon(s.icon, size: 19, color: ClinicTheme.teal),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(s.label,
                        style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: ink)),
                    const SizedBox(height: 2),
                    Text(s.prompt,
                        style: TextStyle(fontSize: 11.5, color: muted),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis),
                  ],
                ),
              ),
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: ClinicTheme.blue.withAlpha(12),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.chevron_right_rounded,
                  size: 18,
                  color: ClinicTheme.blue,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBubble(_ChatMessage msg) {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);

    if (!msg.isUser) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12, right: 36),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 30,
              height: 30,
              margin: const EdgeInsets.only(top: 2),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [_aiA, _aiB]),
                borderRadius: BorderRadius.circular(9),
              ),
              child: const Icon(Icons.auto_awesome, color: Colors.white, size: 16),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: ClinicTheme.surfaceOf(context),
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(4),
                    topRight: Radius.circular(16),
                    bottomLeft: Radius.circular(16),
                    bottomRight: Radius.circular(16),
                  ),
                  border: Border.all(
                    color: msg.isError
                        ? const Color(0xFFBE123C)
                        : ClinicTheme.dividerOf(context),
                  ),
                  boxShadow: ClinicTheme.cardShadow,
                ),
                child: msg.text.isEmpty
                    ? _buildTypingIndicator()
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          SelectableText(
                            msg.text,
                            style: TextStyle(
                              fontSize: 14,
                              height: 1.5,
                              color: msg.isError
                                  ? const Color(0xFFBE123C)
                                  : ink,
                            ),
                          ),
                          const SizedBox(height: 6),
                          InkWell(
                            borderRadius: BorderRadius.circular(6),
                            onTap: () {
                              Clipboard.setData(ClipboardData(text: msg.text));
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                  content: Text('Nusxa olindi'),
                                  duration: Duration(seconds: 1),
                                ),
                              );
                            },
                            child: Padding(
                              padding: const EdgeInsets.all(4),
                              child: Icon(Icons.copy_rounded,
                                  size: 14, color: muted),
                            ),
                          ),
                        ],
                      ),
              ),
            ),
          ],
        ),
      );
    }

    return Align(
      alignment: Alignment.centerRight,
      child: Container(
        constraints:
            BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
        margin: const EdgeInsets.only(bottom: 12, left: 36),
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF0F766E), Color(0xFF1E3A8A)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(16),
            topRight: Radius.circular(4),
            bottomLeft: Radius.circular(16),
            bottomRight: Radius.circular(16),
          ),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0F766E).withOpacity(0.3),
              blurRadius: 8,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            if (msg.attachments.isNotEmpty)
              Padding(
                padding: EdgeInsets.only(bottom: msg.text.isEmpty ? 0 : 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: msg.attachments
                      .map((a) => Padding(
                            padding: const EdgeInsets.only(bottom: 6),
                            child: _buildBubbleAttachment(a),
                          ))
                      .toList(),
                ),
              ),
            if (msg.text.isNotEmpty)
              Text(
                msg.text,
                style: const TextStyle(
                  fontSize: 14,
                  color: Colors.white,
                  height: 1.45,
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildBubbleAttachment(GeminiAttachment a) {
    if (a.isImage) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: Image.memory(a.bytes, width: 220, fit: BoxFit.cover),
      );
    }
    IconData icon;
    if (a.isPdf) {
      icon = Icons.picture_as_pdf_rounded;
    } else if (a.isAudio) {
      icon = Icons.audiotrack_rounded;
    } else if (a.isVideo) {
      icon = Icons.videocam_rounded;
    } else {
      icon = Icons.insert_drive_file_rounded;
    }
    final sizeKb = (a.bytes.length / 1024).round();
    final sizeStr =
        sizeKb > 1024 ? '${(sizeKb / 1024).toStringAsFixed(1)} MB' : '$sizeKb KB';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.18),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.white.withOpacity(0.25)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: Colors.white, size: 22),
          const SizedBox(width: 8),
          Flexible(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  a.name,
                  style: const TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w600,
                      color: Colors.white),
                  overflow: TextOverflow.ellipsis,
                  maxLines: 1,
                ),
                Text(sizeStr,
                    style:
                        const TextStyle(fontSize: 10.5, color: Colors.white70)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTypingIndicator() {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(3, (i) {
        return TweenAnimationBuilder<double>(
          tween: Tween(begin: 0, end: 1),
          duration: Duration(milliseconds: 600 + i * 200),
          builder: (_, v, child) {
            return Container(
              margin: const EdgeInsets.symmetric(horizontal: 3),
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                color: _aiA.withOpacity(0.3 + 0.4 * ((v + i * 0.3) % 1.0)),
                shape: BoxShape.circle,
              ),
            );
          },
        );
      }),
    );
  }

  Widget _buildInput() {
    final disabled = _isStreaming || _contextLoading;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      padding: EdgeInsets.only(
        left: 8,
        right: 8,
        top: 8,
        bottom: MediaQuery.of(context).padding.bottom + 10,
      ),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        border: Border(
          top: BorderSide(color: ClinicTheme.dividerOf(context), width: 1),
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (_pendingAttachments.isNotEmpty) _buildAttachmentPreview(),
          Row(
            children: [
              Container(
                decoration: BoxDecoration(
                  color: disabled ? ClinicTheme.dividerOf(context) : ClinicTheme.teal,
                  shape: BoxShape.circle,
                ),
                child: Material(
                  color: Colors.transparent,
                  shape: const CircleBorder(),
                  child: InkWell(
                    customBorder: const CircleBorder(),
                    onTap: disabled ? null : _showAttachMenu,
                    child: Padding(
                      padding: const EdgeInsets.all(10),
                      child: _picking
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Colors.white),
                            )
                          : Icon(Icons.add_rounded,
                              color: disabled ? ClinicTheme.faint : Colors.white,
                              size: 22),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: TextField(
                  controller: _controller,
                  focusNode: _focusNode,
                  textCapitalization: TextCapitalization.sentences,
                  maxLines: 4,
                  minLines: 1,
                  style: TextStyle(fontSize: 14, color: ClinicTheme.inkOf(context)),
                  decoration: InputDecoration(
                    hintText: _pendingAttachments.isNotEmpty
                        ? AppLocalizations.of(context).pick(
                            uz: 'Fayl haqida savol yozing...',
                            ru: 'Задайте вопрос по файлу...',
                            en: 'Ask a question about the file...',
                          )
                        : AppLocalizations.of(context).pick(
                            uz: 'Savol yozing...',
                            ru: 'Напишите вопрос...',
                            en: 'Write a question...',
                          ),
                    hintStyle: TextStyle(
                        color: ClinicTheme.mutedOf(context), fontSize: 14),
                    filled: true,
                    fillColor: isDark
                        ? Colors.white.withOpacity(0.05)
                        : const Color(0xFFF1F5F9),
                    contentPadding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 10),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(22),
                      borderSide:
                          BorderSide(color: ClinicTheme.dividerOf(context)),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(22),
                      borderSide:
                          const BorderSide(color: ClinicTheme.teal, width: 1.5),
                    ),
                  ),
                  onSubmitted: (_) => _send(),
                ),
              ),
              const SizedBox(width: 6),
              Container(
                decoration: BoxDecoration(
                  color: disabled ? ClinicTheme.dividerOf(context) : ClinicTheme.teal,
                  shape: BoxShape.circle,
                  boxShadow: disabled
                      ? null
                      : [
                          BoxShadow(
                            color: ClinicTheme.teal.withOpacity(0.3),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                ),
                child: Material(
                  color: Colors.transparent,
                  shape: const CircleBorder(),
                  child: InkWell(
                    customBorder: const CircleBorder(),
                    onTap: disabled ? null : _send,
                    child: Padding(
                      padding: const EdgeInsets.all(11),
                      child: _isStreaming
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Colors.white))
                          : Icon(Icons.send_rounded,
                              color: disabled ? ClinicTheme.faint : Colors.white,
                              size: 20),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildAttachmentPreview() {
    return Container(
      height: 80,
      margin: const EdgeInsets.only(bottom: 8, left: 4, right: 4),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _pendingAttachments.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (_, i) => _buildPreviewChip(_pendingAttachments[i], i),
      ),
    );
  }

  Widget _buildPreviewChip(GeminiAttachment a, int index) {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);
    final sizeKb = (a.bytes.length / 1024).round();
    final sizeStr = sizeKb > 1024
        ? '${(sizeKb / 1024).toStringAsFixed(1)}MB'
        : '${sizeKb}KB';

    Widget content;
    if (a.isImage) {
      content = ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: Image.memory(a.bytes, width: 80, height: 80, fit: BoxFit.cover),
      );
    } else {
      IconData icon;
      Color iconColor;
      if (a.isPdf) {
        icon = Icons.picture_as_pdf_rounded;
        iconColor = const Color(0xFFBE123C);
      } else if (a.isAudio) {
        icon = Icons.audiotrack_rounded;
        iconColor = const Color(0xFFB45309);
      } else if (a.isVideo) {
        icon = Icons.videocam_rounded;
        iconColor = const Color(0xFF7C3AED);
      } else {
        icon = Icons.insert_drive_file_rounded;
        iconColor = const Color(0xFF0F766E);
      }
      content = Container(
        width: 140,
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        decoration: BoxDecoration(
          color: ClinicTheme.surfaceOf(context),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
        ),
        child: Row(
          children: [
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                color: iconColor,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, color: Colors.white, size: 18),
            ),
            const SizedBox(width: 6),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(a.name,
                      style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: ink),
                      overflow: TextOverflow.ellipsis,
                      maxLines: 1),
                  const SizedBox(height: 2),
                  Text(sizeStr, style: TextStyle(fontSize: 10, color: muted)),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return Stack(
      clipBehavior: Clip.none,
      children: [
        content,
        Positioned(
          top: -4,
          right: -4,
          child: Material(
            color: Colors.black87,
            shape: const CircleBorder(),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: () => _removeAttachment(index),
              child: const Padding(
                padding: EdgeInsets.all(2),
                child: Icon(Icons.close_rounded, size: 14, color: Colors.white),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _ChatMessage {
  final String text;
  final bool isUser;
  final bool isError;
  final List<GeminiAttachment> attachments;

  const _ChatMessage({
    required this.text,
    required this.isUser,
    this.isError = false,
    this.attachments = const [],
  });
}

class _Suggestion {
  final IconData icon;
  final String label;
  final String prompt;
  const _Suggestion(this.icon, this.label, this.prompt);
}
