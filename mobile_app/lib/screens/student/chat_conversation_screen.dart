import 'dart:async';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';

class ChatConversationScreen extends StatefulWidget {
  final int contactId;
  final String contactName;
  final String? contactImage;

  const ChatConversationScreen({
    super.key,
    required this.contactId,
    required this.contactName,
    this.contactImage,
  });

  @override
  State<ChatConversationScreen> createState() => _ChatConversationScreenState();
}

class _ChatConversationScreenState extends State<ChatConversationScreen> {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  final _focusNode = FocusNode();
  List<dynamic> _messages = [];
  bool _loading = true;
  bool _sending = false;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    _loadMessages();
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (mounted) _loadMessages(silent: true);
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _controller.dispose();
    _scrollController.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  Future<void> _loadMessages({bool silent = false}) async {
    try {
      final api = ApiService();
      final service = StudentService(api);
      final res = await service.getChatMessages(widget.contactId);
      if (mounted && res['success'] == true) {
        final newList = res['data'] as List<dynamic>? ?? [];
        final hadMessages = _messages.length;
        setState(() {
          _messages = newList;
          _loading = false;
        });
        if (hadMessages < newList.length) {
          _scrollToBottom();
        }
      } else {
        if (mounted && !silent) setState(() => _loading = false);
      }
    } catch (_) {
      if (mounted && !silent) setState(() => _loading = false);
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    if (text.isEmpty || _sending) return;

    setState(() => _sending = true);
    _controller.clear();

    try {
      final api = ApiService();
      final service = StudentService(api);
      final res = await service.sendChatMessage(widget.contactId, text);
      if (mounted && res['success'] == true) {
        final msg = res['data'];
        setState(() {
          _messages.add(msg);
          _sending = false;
        });
        _scrollToBottom();
      } else {
        if (mounted) {
          setState(() => _sending = false);
          _controller.text = text;
        }
      }
    } catch (_) {
      if (mounted) {
        setState(() => _sending = false);
        _controller.text = text;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? AppTheme.darkBackground : const Color(0xFFE8EAF0);
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    final initials = widget.contactName
        .split(' ')
        .take(2)
        .map((w) => w.isNotEmpty ? w[0].toUpperCase() : '')
        .join();

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        titleSpacing: 0,
        title: Row(
          children: [
            CircleAvatar(
              radius: 18,
              backgroundColor: const Color(0xFF4A6CF7).withOpacity(0.15),
              backgroundImage:
                  widget.contactImage != null && widget.contactImage!.isNotEmpty
                      ? NetworkImage(
                          '${ApiConfig.baseUrl}/image-proxy?url=${widget.contactImage}')
                      : null,
              child: widget.contactImage == null || widget.contactImage!.isEmpty
                  ? Text(initials,
                      style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF4A6CF7)))
                  : null,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(widget.contactName,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 16)),
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _messages.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.chat_bubble_outline_rounded,
                                size: 48, color: sub),
                            const SizedBox(height: 12),
                            Text('Hali xabar yo\'q',
                                style: TextStyle(color: sub, fontSize: 14)),
                            const SizedBox(height: 4),
                            Text('Birinchi xabarni yuboring!',
                                style: TextStyle(color: sub, fontSize: 12)),
                          ],
                        ),
                      )
                    : ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 8),
                        itemCount: _messages.length,
                        itemBuilder: (_, i) {
                          final msg = _messages[i];
                          final isMe = msg['is_me'] == true;
                          final showDate = i == 0 ||
                              _dateDiffers(_messages[i - 1], msg);
                          return Column(
                            children: [
                              if (showDate) _buildDateChip(msg, isDark),
                              _buildBubble(msg, isMe, isDark),
                            ],
                          );
                        },
                      ),
          ),
          _buildInput(isDark),
        ],
      ),
    );
  }

  bool _dateDiffers(dynamic prev, dynamic curr) {
    try {
      final a = DateTime.parse(prev['created_at'].toString()).toLocal();
      final b = DateTime.parse(curr['created_at'].toString()).toLocal();
      return a.year != b.year || a.month != b.month || a.day != b.day;
    } catch (_) {
      return false;
    }
  }

  Widget _buildDateChip(dynamic msg, bool isDark) {
    String label = '';
    try {
      final dt = DateTime.parse(msg['created_at'].toString()).toLocal();
      final now = DateTime.now();
      if (dt.year == now.year && dt.month == now.month && dt.day == now.day) {
        label = 'Bugun';
      } else if (dt.year == now.year &&
          dt.month == now.month &&
          dt.day == now.day - 1) {
        label = 'Kecha';
      } else {
        label =
            '${dt.day}.${dt.month.toString().padLeft(2, '0')}.${dt.year}';
      }
    } catch (_) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Center(
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          decoration: BoxDecoration(
            color: isDark ? Colors.white10 : Colors.black12,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(label,
              style: TextStyle(
                  fontSize: 11,
                  color: isDark
                      ? AppTheme.darkTextSecondary
                      : AppTheme.textSecondary)),
        ),
      ),
    );
  }

  Widget _buildBubble(dynamic msg, bool isMe, bool isDark) {
    final text = msg['message']?.toString() ?? '';
    final read = msg['read'] == true;
    String time = '';
    try {
      final dt = DateTime.parse(msg['created_at'].toString()).toLocal();
      time =
          '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {}

    final bubbleColor = isMe
        ? const Color(0xFF4A6CF7)
        : isDark
            ? AppTheme.darkCard
            : Colors.white;
    final textColor = isMe
        ? Colors.white
        : isDark
            ? AppTheme.darkTextPrimary
            : AppTheme.textPrimary;
    final timeColor = isMe
        ? Colors.white70
        : isDark
            ? AppTheme.darkTextSecondary
            : AppTheme.textSecondary;

    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints:
            BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
        margin: const EdgeInsets.symmetric(vertical: 2),
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
        decoration: BoxDecoration(
          color: bubbleColor,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(isMe ? 16 : 4),
            bottomRight: Radius.circular(isMe ? 4 : 16),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 4,
              offset: const Offset(0, 1),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(text, style: TextStyle(fontSize: 14, color: textColor)),
            const SizedBox(height: 2),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(time, style: TextStyle(fontSize: 10, color: timeColor)),
                if (isMe) ...[
                  const SizedBox(width: 3),
                  Icon(
                    read ? Icons.done_all : Icons.done,
                    size: 14,
                    color: read ? const Color(0xFF64FFDA) : Colors.white54,
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInput(bool isDark) {
    final inputBg = isDark ? AppTheme.darkCard : Colors.white;

    return Container(
      padding: EdgeInsets.only(
        left: 8,
        right: 8,
        top: 8,
        bottom: MediaQuery.of(context).padding.bottom + 8,
      ),
      decoration: BoxDecoration(
        color: inputBg,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 8,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: _controller,
              focusNode: _focusNode,
              textCapitalization: TextCapitalization.sentences,
              maxLines: 4,
              minLines: 1,
              decoration: InputDecoration(
                hintText: 'Xabar yozing...',
                hintStyle: TextStyle(
                    color: isDark
                        ? AppTheme.darkTextSecondary
                        : AppTheme.textSecondary,
                    fontSize: 14),
                filled: true,
                fillColor: isDark
                    ? AppTheme.darkBackground
                    : const Color(0xFFF0F2F5),
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: BorderSide.none,
                ),
              ),
              onSubmitted: (_) => _send(),
            ),
          ),
          const SizedBox(width: 6),
          Material(
            color: const Color(0xFF4A6CF7),
            shape: const CircleBorder(),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: _sending ? null : _send,
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: _sending
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
        ],
      ),
    );
  }
}
