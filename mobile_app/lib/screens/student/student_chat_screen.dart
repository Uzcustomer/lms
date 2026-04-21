import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/student_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';

class StudentChatScreen extends StatefulWidget {
  const StudentChatScreen({super.key});

  @override
  State<StudentChatScreen> createState() => _StudentChatScreenState();
}

class _StudentChatScreenState extends State<StudentChatScreen> {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  final List<_ChatMessage> _messages = [];
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _messages.add(_ChatMessage(
      text: 'Assalomu alaykum! Men TDTU LMS yordamchisiman. '
          'Baholar, dars jadvali, davomatga oid savollarga javob bera olaman. '
          'Qanday yordam bera olaman?',
      isUser: false,
    ));
  }

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  List<Map<String, String>> _buildHistory() {
    final history = <Map<String, String>>[];
    // Skip the initial greeting, send last 20 messages
    final msgs = _messages.skip(1).toList();
    final start = msgs.length > 20 ? msgs.length - 20 : 0;
    for (int i = start; i < msgs.length; i++) {
      history.add({
        'role': msgs[i].isUser ? 'user' : 'assistant',
        'content': msgs[i].text,
      });
    }
    return history;
  }

  Future<void> _sendMessage() async {
    final text = _controller.text.trim();
    if (text.isEmpty || _isLoading) return;

    _controller.clear();
    setState(() {
      _messages.add(_ChatMessage(text: text, isUser: true));
      _isLoading = true;
    });
    _scrollToBottom();

    try {
      final api = ApiService();
      final service = StudentService(api);
      final history = _buildHistory();
      // Remove last user message from history since it goes as 'message' param
      if (history.isNotEmpty) history.removeLast();

      final response = await service.sendChatMessage(text, history);

      if (response['success'] == true) {
        final reply = response['data']?['reply'] ?? 'Javob olib bo\'lmadi';
        setState(() {
          _messages.add(_ChatMessage(text: reply, isUser: false));
        });
      } else {
        setState(() {
          _messages.add(_ChatMessage(
            text: response['message'] ?? 'Xatolik yuz berdi',
            isUser: false,
            isError: true,
          ));
        });
      }
    } on ApiException catch (e) {
      setState(() {
        _messages.add(_ChatMessage(
          text: e.message,
          isUser: false,
          isError: true,
        ));
      });
    } catch (e) {
      setState(() {
        _messages.add(_ChatMessage(
          text: 'Tarmoq xatoligi. Qayta urinib ko\'ring.',
          isUser: false,
          isError: true,
        ));
      });
    }

    setState(() => _isLoading = false);
    _scrollToBottom();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkBackground : const Color(0xFFF0F2F8);

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        title: Row(
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF4A6CF7), Color(0xFF6C63FF)],
                ),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.smart_toy_rounded, color: Colors.white, size: 20),
            ),
            const SizedBox(width: 10),
            const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('AI Yordamchi', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
                Text('TDTU LMS Bot', style: TextStyle(fontSize: 11, fontWeight: FontWeight.normal)),
              ],
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.delete_outline_rounded),
            onPressed: () {
              setState(() {
                _messages.clear();
                _messages.add(_ChatMessage(
                  text: 'Assalomu alaykum! Men TDTU LMS yordamchisiman. '
                      'Baholar, dars jadvali, davomatga oid savollarga javob bera olaman. '
                      'Qanday yordam bera olaman?',
                  isUser: false,
                ));
              });
            },
          ),
        ],
      ),
      body: Column(
        children: [
          // Messages
          Expanded(
            child: ListView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              itemCount: _messages.length + (_isLoading ? 1 : 0),
              itemBuilder: (context, index) {
                if (index == _messages.length && _isLoading) {
                  return _buildTypingIndicator(isDark);
                }
                return _buildMessageBubble(_messages[index], isDark);
              },
            ),
          ),

          // Quick action chips
          if (_messages.length <= 1)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _buildChip('Baholarim qanday?', isDark),
                  _buildChip('Bugungi dars jadvali', isDark),
                  _buildChip('GPA ni qanday oshiraman?', isDark),
                  _buildChip('Davomatim qanday?', isDark),
                ],
              ),
            ),
          if (_messages.length <= 1) const SizedBox(height: 12),

          // Input area
          Container(
            padding: EdgeInsets.only(
              left: 16,
              right: 8,
              top: 10,
              bottom: MediaQuery.of(context).padding.bottom + 10,
            ),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.darkCard : Colors.white,
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.05),
                  blurRadius: 10,
                  offset: const Offset(0, -2),
                ),
              ],
            ),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _controller,
                    textInputAction: TextInputAction.send,
                    onSubmitted: (_) => _sendMessage(),
                    maxLines: 4,
                    minLines: 1,
                    style: TextStyle(
                      fontSize: 15,
                      color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                    ),
                    decoration: InputDecoration(
                      hintText: 'Savol yozing...',
                      hintStyle: TextStyle(
                        color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                        fontSize: 14,
                      ),
                      filled: true,
                      fillColor: isDark
                          ? AppTheme.darkBackground
                          : const Color(0xFFF0F2F8),
                      contentPadding: const EdgeInsets.symmetric(
                          horizontal: 16, vertical: 10),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(24),
                        borderSide: BorderSide.none,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                Container(
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF4A6CF7), Color(0xFF6C63FF)],
                    ),
                    borderRadius: BorderRadius.circular(22),
                  ),
                  child: IconButton(
                    onPressed: _isLoading ? null : _sendMessage,
                    icon: Icon(
                      _isLoading ? Icons.hourglass_top_rounded : Icons.send_rounded,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildChip(String text, bool isDark) {
    return GestureDetector(
      onTap: () {
        _controller.text = text;
        _sendMessage();
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isDark ? AppTheme.darkCard : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: const Color(0xFF4A6CF7).withOpacity(0.3),
          ),
        ),
        child: Text(
          text,
          style: TextStyle(
            fontSize: 13,
            color: isDark ? const Color(0xFF8FA8FF) : const Color(0xFF4A6CF7),
          ),
        ),
      ),
    );
  }

  Widget _buildMessageBubble(_ChatMessage message, bool isDark) {
    final isUser = message.isUser;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        mainAxisAlignment:
            isUser ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isUser) ...[
            Container(
              width: 30,
              height: 30,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF4A6CF7), Color(0xFF6C63FF)],
                ),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.smart_toy_rounded,
                  color: Colors.white, size: 16),
            ),
            const SizedBox(width: 8),
          ],
          Flexible(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: isUser
                    ? const Color(0xFF4A6CF7)
                    : message.isError
                        ? (isDark ? const Color(0xFF3D1F1F) : const Color(0xFFFEECEC))
                        : (isDark ? AppTheme.darkCard : Colors.white),
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(16),
                  topRight: const Radius.circular(16),
                  bottomLeft: Radius.circular(isUser ? 16 : 4),
                  bottomRight: Radius.circular(isUser ? 4 : 16),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.04),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: SelectableText(
                message.text,
                style: TextStyle(
                  fontSize: 14,
                  height: 1.4,
                  color: isUser
                      ? Colors.white
                      : message.isError
                          ? const Color(0xFFE53935)
                          : (isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary),
                ),
              ),
            ),
          ),
          if (isUser) const SizedBox(width: 8),
        ],
      ),
    );
  }

  Widget _buildTypingIndicator(bool isDark) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF4A6CF7), Color(0xFF6C63FF)],
              ),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.smart_toy_rounded,
                color: Colors.white, size: 16),
          ),
          const SizedBox(width: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.darkCard : Colors.white,
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(16),
                topRight: Radius.circular(16),
                bottomLeft: Radius.circular(4),
                bottomRight: Radius.circular(16),
              ),
            ),
            child: const _TypingDots(),
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

  _ChatMessage({
    required this.text,
    required this.isUser,
    this.isError = false,
  });
}

class _TypingDots extends StatefulWidget {
  const _TypingDots();

  @override
  State<_TypingDots> createState() => _TypingDotsState();
}

class _TypingDotsState extends State<_TypingDots>
    with TickerProviderStateMixin {
  late final List<AnimationController> _controllers;

  @override
  void initState() {
    super.initState();
    _controllers = List.generate(3, (i) {
      final c = AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 600),
      );
      Future.delayed(Duration(milliseconds: i * 200), () {
        if (mounted) c.repeat(reverse: true);
      });
      return c;
    });
  }

  @override
  void dispose() {
    for (final c in _controllers) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(3, (i) {
        return AnimatedBuilder(
          animation: _controllers[i],
          builder: (context, child) {
            return Container(
              margin: const EdgeInsets.symmetric(horizontal: 2),
              width: 7,
              height: 7,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFF4A6CF7)
                    .withOpacity(0.3 + _controllers[i].value * 0.7),
              ),
            );
          },
        );
      }),
    );
  }
}
