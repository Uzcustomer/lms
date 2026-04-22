import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import 'chat_conversation_screen.dart';

class ChatContactsScreen extends StatefulWidget {
  const ChatContactsScreen({super.key});

  @override
  State<ChatContactsScreen> createState() => _ChatContactsScreenState();
}

class _ChatContactsScreenState extends State<ChatContactsScreen> {
  List<dynamic> _contacts = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final api = ApiService();
      final service = StudentService(api);
      final res = await service.getChatContacts();
      if (mounted && res['success'] == true) {
        setState(() {
          _contacts = res['data'] as List<dynamic>? ?? [];
          _loading = false;
        });
      } else {
        if (mounted) setState(() => _loading = false);
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? AppTheme.darkBackground : const Color(0xFFF5F7FB);
    final card = isDark ? AppTheme.darkCard : Colors.white;
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(title: const Text('Xabarlar')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _contacts.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.chat_bubble_outline_rounded,
                          size: 48, color: sub),
                      const SizedBox(height: 12),
                      Text('Guruhda boshqa talaba topilmadi',
                          style: TextStyle(color: sub)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.separated(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    itemCount: _contacts.length,
                    separatorBuilder: (_, __) => Divider(
                        height: 1,
                        indent: 76,
                        color: isDark ? Colors.white10 : Colors.grey.shade200),
                    itemBuilder: (_, i) =>
                        _buildContact(_contacts[i], card, txt, sub, isDark),
                  ),
                ),
    );
  }

  Widget _buildContact(
      dynamic c, Color card, Color txt, Color sub, bool isDark) {
    final name = c['name']?.toString() ?? '';
    final image = c['image']?.toString();
    final lastMsg = c['last_message']?.toString();
    final lastIsMe = c['last_message_is_me'] == true;
    final unread = c['unread_count'] as int? ?? 0;
    final contactId = c['id'] as int? ?? 0;

    String? timeStr;
    if (c['last_message_at'] != null) {
      try {
        final dt = DateTime.parse(c['last_message_at'].toString()).toLocal();
        final now = DateTime.now();
        if (dt.year == now.year && dt.month == now.month && dt.day == now.day) {
          timeStr =
              '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
        } else {
          timeStr = '${dt.day}.${dt.month.toString().padLeft(2, '0')}';
        }
      } catch (_) {}
    }

    final initials = name.split(' ').take(2).map((w) =>
        w.isNotEmpty ? w[0].toUpperCase() : '').join();

    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      leading: CircleAvatar(
        radius: 26,
        backgroundColor: const Color(0xFF4A6CF7).withOpacity(0.12),
        backgroundImage: image != null && image.isNotEmpty
            ? NetworkImage('${ApiConfig.baseUrl}/image-proxy?url=$image')
            : null,
        child: image == null || image.isEmpty
            ? Text(initials,
                style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF4A6CF7)))
            : null,
      ),
      title: Text(name,
          style: TextStyle(
              fontSize: 14,
              fontWeight: unread > 0 ? FontWeight.w700 : FontWeight.w500,
              color: txt)),
      subtitle: lastMsg != null
          ? Text(
              lastIsMe ? 'Siz: $lastMsg' : lastMsg,
              style: TextStyle(
                  fontSize: 12,
                  color: unread > 0 ? txt : sub,
                  fontWeight:
                      unread > 0 ? FontWeight.w500 : FontWeight.normal),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            )
          : Text('Xabar yo\'q', style: TextStyle(fontSize: 12, color: sub)),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (timeStr != null)
            Text(timeStr,
                style: TextStyle(
                    fontSize: 11,
                    color: unread > 0 ? const Color(0xFF4A6CF7) : sub)),
          if (unread > 0) ...[
            const SizedBox(height: 4),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(
                color: const Color(0xFF4A6CF7),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text('$unread',
                  style: const TextStyle(
                      fontSize: 11,
                      color: Colors.white,
                      fontWeight: FontWeight.w600)),
            ),
          ],
        ],
      ),
      onTap: () async {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ChatConversationScreen(
              contactId: contactId,
              contactName: name,
              contactImage: image,
            ),
          ),
        );
        _load();
      },
    );
  }
}
