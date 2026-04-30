import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../config/aurora_themes.dart';
import '../../providers/settings_provider.dart';
import '../../services/api_service.dart';
import '../../services/gemini_service.dart';
import '../../services/student_context_builder.dart';
import '../../services/student_service.dart';

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
  bool _isStreaming = false;
  bool _contextLoading = true;
  bool _contextLoaded = false;

  @override
  void initState() {
    super.initState();
    _loadStudentContext();
  }

  Future<void> _loadStudentContext() async {
    try {
      final api = ApiService();
      final service = StudentService(api);
      final builder = StudentContextBuilder(service);
      final context = await builder.build();
      _gemini.setStudentContext(context);
      if (mounted) {
        setState(() {
          _contextLoading = false;
          _contextLoaded = true;
        });
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
    if (text.isEmpty || _isStreaming || _contextLoading) return;

    setState(() {
      _messages.add(_ChatMessage(text: text, isUser: true));
      _messages.add(_ChatMessage(text: '', isUser: false));
      _isStreaming = true;
    });
    _controller.clear();
    _scrollToBottom();

    try {
      final aiIndex = _messages.length - 1;
      await for (final chunk in _gemini.sendMessageStream(text)) {
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
    return Container(
      padding: EdgeInsets.only(top: statusBarH, left: 4, right: 4),
      decoration: const BoxDecoration(
        color: Color(0xFF0A1A3A),
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
                icon: const Icon(Icons.delete_outline_rounded, color: Colors.white70, size: 22),
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
    final border = isDark ? Colors.white12 : Colors.grey.shade200;
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
        child: Text(
          msg.text,
          style: const TextStyle(
            fontSize: 14,
            color: Colors.white,
            height: 1.45,
          ),
        ),
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

    return Container(
      padding: EdgeInsets.only(
        left: 12,
        right: 12,
        top: 10,
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
      child: Row(
        children: [
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
                  hintText: 'Savol yozing...',
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
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(24),
                    borderSide: BorderSide.none,
                  ),
                ),
                onSubmitted: (_) => _send(),
              ),
            ),
          ),
          const SizedBox(width: 8),
          Container(
            decoration: BoxDecoration(
              gradient: (_isStreaming || _contextLoading)
                  ? null
                  : const LinearGradient(
                      colors: [Color(0xFF4A6CF7), Color(0xFF6C63FF)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
              color: (_isStreaming || _contextLoading)
                  ? (isDark ? Colors.white12 : Colors.grey.shade300)
                  : null,
              shape: BoxShape.circle,
              boxShadow: (_isStreaming || _contextLoading)
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
                onTap: (_isStreaming || _contextLoading) ? null : _send,
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
    );
  }
}

class _ChatMessage {
  final String text;
  final bool isUser;
  final bool isError;

  const _ChatMessage({
    required this.text,
    required this.isUser,
    this.isError = false,
  });
}

class _Suggestion {
  final IconData icon;
  final String label;
  final String prompt;
  const _Suggestion(this.icon, this.label, this.prompt);
}
