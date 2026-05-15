import 'dart:typed_data';
import 'dart:ui';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../config/aurora_themes.dart';
import '../../providers/settings_provider.dart';
import '../../services/gemini_service.dart';
import '../../services/student_context_builder.dart';
import '../../services/student_data_cache.dart';

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
        final isDark = Theme.of(context).brightness == Brightness.dark;
        return Container(
          padding: EdgeInsets.only(
            top: 16,
            bottom: MediaQuery.of(ctx).padding.bottom + 16,
          ),
          decoration: BoxDecoration(
            color: isDark ? AppTheme.darkCard : Colors.white,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 38,
                height: 4,
                decoration: BoxDecoration(
                  color: isDark ? Colors.white24 : Colors.grey.shade300,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 18),
              _attachOption(Icons.image_outlined, 'Rasm', 'JPG, PNG, WEBP',
                  const Color(0xFF4A6CF7), () {
                Navigator.pop(ctx);
                _pickFile(FileType.custom, extensions: _imageExt);
              }),
              _attachOption(Icons.picture_as_pdf_outlined, 'PDF',
                  'Hujjatlar va kitoblar', const Color(0xFFE53935), () {
                Navigator.pop(ctx);
                _pickFile(FileType.custom, extensions: ['pdf']);
              }),
              _attachOption(Icons.audiotrack_outlined, 'Audio',
                  'MP3, WAV, M4A', const Color(0xFFF97316), () {
                Navigator.pop(ctx);
                _pickFile(FileType.custom, extensions: _audioExt);
              }),
              _attachOption(Icons.videocam_outlined, 'Video',
                  'MP4, MOV, WEBM', const Color(0xFF8B5CF6), () {
                Navigator.pop(ctx);
                _pickFile(FileType.custom, extensions: _videoExt);
              }),
              _attachOption(Icons.insert_drive_file_outlined, 'Boshqa fayl',
                  'TXT, CSV, MD', const Color(0xFF14B8A6), () {
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
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: color.withOpacity(isDark ? 0.2 : 0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: color, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      style: TextStyle(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w600,
                          color: txt)),
                  const SizedBox(height: 2),
                  Text(subtitle,
                      style: TextStyle(fontSize: 11.5, color: sub)),
                ],
              ),
            ),
            Icon(Icons.arrow_forward_ios_rounded, size: 14, color: sub),
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
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final aurora = context.watch<SettingsProvider>().auroraTheme;
    final statusBarH = MediaQuery.of(context).padding.top;

    return Scaffold(
      backgroundColor: auroraBase(aurora, isDark),
      body: Column(
        children: [
          _buildHeader(statusBarH),
          Expanded(
            child: _messages.isEmpty
                ? _buildWelcome(isDark)
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
                    itemCount: _messages.length + (_isStreaming ? 0 : 0),
                    itemBuilder: (_, i) =>
                        _buildBubble(_messages[i], isDark),
                  ),
          ),
          _buildInput(isDark),
        ],
      ),
    );
  }

  Widget _buildHeader(double statusBarH) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      padding: EdgeInsets.only(top: statusBarH, left: 4, right: 4),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.darkHeaderColor : const Color(0xFF1E3A8A),
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(18),
          bottomRight: Radius.circular(18),
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(height: statusBarH > 0 ? 0 : 8),
          Row(
            children: [
              IconButton(
                icon: const Icon(Icons.arrow_back, color: Colors.white, size: 22),
                onPressed: () => Navigator.pop(context),
              ),
              const SizedBox(width: 4),
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF4A6CF7), Color(0xFF9C27B0)],
                  ),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.auto_awesome, color: Colors.white, size: 18),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'AI Yordamchi',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                    Row(
                      children: [
                        if (_contextLoading) ...[
                          const SizedBox(
                            width: 8,
                            height: 8,
                            child: CircularProgressIndicator(
                                strokeWidth: 1.5, color: Colors.white60),
                          ),
                          const SizedBox(width: 6),
                          const Text(
                            'Ma\'lumot yuklanmoqda...',
                            style: TextStyle(fontSize: 10.5, color: Colors.white60),
                          ),
                        ] else if (_contextLoaded) ...[
                          const Icon(Icons.check_circle_rounded,
                              size: 11, color: Color(0xFF64FFDA)),
                          const SizedBox(width: 4),
                          const Text(
                            'Ma\'lumotlaringiz bilan tayyor',
                            style: TextStyle(fontSize: 10.5, color: Colors.white70),
                          ),
                        ] else
                          const Text(
                            'Gemini · TDTU',
                            style: TextStyle(fontSize: 11, color: Colors.white60),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              IconButton(
                icon: _contextLoading
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white70,
                        ),
                      )
                    : const Icon(Icons.refresh_rounded,
                        color: Colors.white70, size: 22),
                tooltip: 'Ma\'lumotlarni yangilash',
                onPressed: _contextLoading ? null : _refreshData,
              ),
              IconButton(
                icon: const Icon(Icons.delete_outline_rounded,
                    color: Colors.white70, size: 22),
                tooltip: 'Chatni tozalash',
                onPressed: _messages.isNotEmpty ? _clearChat : null,
              ),
            ],
          ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }

  Widget _buildWelcome(bool isDark) {
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;

    final suggestions = _contextLoaded
        ? [
            _Suggestion(Icons.bar_chart_rounded, 'Mening baholarim',
                'Barcha fanlardan baholarimni umumlashtirib bering'),
            _Suggestion(Icons.trending_up_rounded, 'Eng yaxshi/yomon fanim',
                'Qaysi fanda eng yaxshi va qaysida yomon natija bor?'),
            _Suggestion(Icons.warning_amber_rounded, 'Diqqat qilishim kerak',
                'Qaysi fanlarga ko\'proq e\'tibor berishim kerak?'),
            _Suggestion(Icons.calendar_month_rounded, 'Imtihon jadvalim',
                'Yaqinlashayotgan imtihonlarim qachon?'),
            _Suggestion(Icons.event_available_rounded, 'Davomatim',
                'Davomat statistikasini tahlil qiling'),
            _Suggestion(Icons.lightbulb_outline_rounded, 'Maslahat bering',
                'Reytingimni yaxshilash uchun nima qilishim kerak?'),
          ]
        : [
            _Suggestion(Icons.science_outlined, 'Anatomiya',
                'Yurak tuzilishi haqida tushuntiring'),
            _Suggestion(Icons.medication_outlined, 'Farmakologiya',
                'Antibiotiklar klassifikatsiyasi'),
            _Suggestion(Icons.biotech_outlined, 'Fiziologiya',
                'Qon aylanish doiralari'),
            _Suggestion(Icons.school_outlined, 'Imtihon',
                'Patologik anatomiyadan savollar'),
          ];

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          const SizedBox(height: 30),
          Container(
            width: 80,
            height: 80,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF4A6CF7), Color(0xFF9C27B0)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(24),
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFF4A6CF7).withOpacity(0.3),
                  blurRadius: 20,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: const Icon(Icons.auto_awesome, color: Colors.white, size: 40),
          ),
          const SizedBox(height: 20),
          Text(
            'TDTU AI Yordamchi',
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: txt,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Tibbiyot fanlari va o\'quv jarayonida\nsizga yordam berishga tayyorman!',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13.5, color: sub, height: 1.5),
          ),
          const SizedBox(height: 28),
          ...suggestions.map((s) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _buildSuggestionCard(s, isDark),
              )),
        ],
      ),
    );
  }

  Widget _buildSuggestionCard(_Suggestion s, bool isDark) {
    final surface = isDark
        ? Colors.white.withOpacity(0.08)
        : Colors.white.withOpacity(0.85);
    final border = AppTheme.cardBorderColor;
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Material(
      color: surface,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () {
          _controller.text = s.prompt;
          _send();
        },
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: border),
          ),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: const Color(0xFF4A6CF7).withOpacity(isDark ? 0.2 : 0.08),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(s.icon, size: 20, color: const Color(0xFF4A6CF7)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(s.label,
                        style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: txt)),
                    const SizedBox(height: 2),
                    Text(s.prompt,
                        style: TextStyle(fontSize: 11.5, color: sub),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis),
                  ],
                ),
              ),
              Icon(Icons.arrow_forward_ios_rounded, size: 14, color: sub),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBubble(_ChatMessage msg, bool isDark) {
    final isUser = msg.isUser;
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    if (!isUser) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12, right: 40),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 30,
              height: 30,
              margin: const EdgeInsets.only(top: 2),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF4A6CF7), Color(0xFF9C27B0)],
                ),
                borderRadius: BorderRadius.circular(9),
              ),
              child: const Icon(Icons.auto_awesome, color: Colors.white, size: 16),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: isDark ? AppTheme.darkCard : Colors.white,
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(4),
                    topRight: Radius.circular(16),
                    bottomLeft: Radius.circular(16),
                    bottomRight: Radius.circular(16),
                  ),
                  border: Border.all(
                    color: msg.isError
                        ? Colors.red.withOpacity(0.3)
                        : isDark
                            ? Colors.white10
                            : Colors.grey.shade200,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.04),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
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
                              color: msg.isError ? Colors.red : txt,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              InkWell(
                                borderRadius: BorderRadius.circular(6),
                                onTap: () {
                                  Clipboard.setData(
                                      ClipboardData(text: msg.text));
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
                                      size: 14, color: sub),
                                ),
                              ),
                            ],
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
        margin: const EdgeInsets.only(bottom: 12, left: 40),
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF4A6CF7), Color(0xFF5B7BF8)],
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
              color: const Color(0xFF4A6CF7).withOpacity(0.25),
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
        child: Image.memory(
          a.bytes,
          width: 220,
          fit: BoxFit.cover,
        ),
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
    final sizeStr = sizeKb > 1024
        ? '${(sizeKb / 1024).toStringAsFixed(1)} MB'
        : '$sizeKb KB';
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
                    style: const TextStyle(
                        fontSize: 10.5, color: Colors.white70)),
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
                color: const Color(0xFF4A6CF7)
                    .withOpacity(0.3 + 0.4 * ((v + i * 0.3) % 1.0)),
                shape: BoxShape.circle,
              ),
            );
          },
        );
      }),
    );
  }

  Widget _buildInput(bool isDark) {
    final inputBg = isDark ? AppTheme.darkCard : Colors.white;
    final disabled = _isStreaming || _contextLoading;

    return Container(
      padding: EdgeInsets.only(
        left: 8,
        right: 8,
        top: 8,
        bottom: MediaQuery.of(context).padding.bottom + 10,
      ),
      decoration: BoxDecoration(
        color: inputBg,
        border: Border(
          top: BorderSide(
            color: isDark ? Colors.white10 : Colors.grey.shade200,
          ),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, -3),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (_pendingAttachments.isNotEmpty)
            _buildAttachmentPreview(isDark),
          Row(
            children: [
              Container(
                decoration: BoxDecoration(
                  color: disabled
                      ? (isDark ? Colors.white10 : Colors.grey.shade200)
                      : const Color(0xFF4A6CF7).withOpacity(isDark ? 0.2 : 0.1),
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
                                strokeWidth: 2,
                                color: Color(0xFF4A6CF7),
                              ),
                            )
                          : Icon(
                              Icons.add_rounded,
                              color: disabled
                                  ? (isDark
                                      ? Colors.white38
                                      : Colors.grey.shade400)
                                  : const Color(0xFF4A6CF7),
                              size: 22,
                            ),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(
                      color: isDark ? Colors.white12 : Colors.grey.shade300,
                    ),
                  ),
                  child: TextField(
                    controller: _controller,
                    focusNode: _focusNode,
                    textCapitalization: TextCapitalization.sentences,
                    maxLines: 4,
                    minLines: 1,
                    decoration: InputDecoration(
                      hintText: _pendingAttachments.isNotEmpty
                          ? 'Fayl haqida savol yozing...'
                          : 'Savol yozing...',
                      hintStyle: TextStyle(
                        color: isDark
                            ? AppTheme.darkTextSecondary
                            : AppTheme.textSecondary,
                        fontSize: 14,
                      ),
                      filled: true,
                      fillColor: isDark
                          ? AppTheme.darkBackground
                          : const Color(0xFFF5F7FA),
                      contentPadding: const EdgeInsets.symmetric(
                          horizontal: 18, vertical: 10),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(24),
                        borderSide: BorderSide.none,
                      ),
                    ),
                    onSubmitted: (_) => _send(),
                  ),
                ),
              ),
              const SizedBox(width: 6),
              Container(
                decoration: BoxDecoration(
                  gradient: disabled
                      ? null
                      : const LinearGradient(
                          colors: [Color(0xFF4A6CF7), Color(0xFF6C63FF)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                  color: disabled
                      ? (isDark ? Colors.white12 : Colors.grey.shade300)
                      : null,
                  shape: BoxShape.circle,
                  boxShadow: disabled
                      ? null
                      : [
                          BoxShadow(
                            color: const Color(0xFF4A6CF7).withOpacity(0.3),
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
                          : const Icon(Icons.send_rounded,
                              color: Colors.white, size: 20),
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

  Widget _buildAttachmentPreview(bool isDark) {
    return Container(
      height: 80,
      margin: const EdgeInsets.only(bottom: 8, left: 4, right: 4),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _pendingAttachments.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (_, i) {
          final a = _pendingAttachments[i];
          return _buildPreviewChip(a, i, isDark);
        },
      ),
    );
  }

  Widget _buildPreviewChip(GeminiAttachment a, int index, bool isDark) {
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final sizeKb = (a.bytes.length / 1024).round();
    final sizeStr = sizeKb > 1024
        ? '${(sizeKb / 1024).toStringAsFixed(1)}MB'
        : '${sizeKb}KB';

    Widget content;
    if (a.isImage) {
      content = ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: Image.memory(
          a.bytes,
          width: 80,
          height: 80,
          fit: BoxFit.cover,
        ),
      );
    } else {
      IconData icon;
      Color iconColor;
      if (a.isPdf) {
        icon = Icons.picture_as_pdf_rounded;
        iconColor = const Color(0xFFE53935);
      } else if (a.isAudio) {
        icon = Icons.audiotrack_rounded;
        iconColor = const Color(0xFFF97316);
      } else if (a.isVideo) {
        icon = Icons.videocam_rounded;
        iconColor = const Color(0xFF8B5CF6);
      } else {
        icon = Icons.insert_drive_file_rounded;
        iconColor = const Color(0xFF14B8A6);
      }
      content = Container(
        width: 140,
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        decoration: BoxDecoration(
          color: isDark ? Colors.white10 : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppTheme.cardBorderColor),
        ),
        child: Row(
          children: [
            Icon(icon, color: iconColor, size: 26),
            const SizedBox(width: 6),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(a.name,
                      style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w600,
                          color: txt),
                      overflow: TextOverflow.ellipsis,
                      maxLines: 1),
                  const SizedBox(height: 2),
                  Text(sizeStr,
                      style: TextStyle(fontSize: 10, color: sub)),
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
                child:
                    Icon(Icons.close_rounded, size: 14, color: Colors.white),
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
