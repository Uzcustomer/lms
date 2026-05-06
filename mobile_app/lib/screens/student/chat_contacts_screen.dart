import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../config/aurora_themes.dart';
import '../../providers/settings_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import '../../utils/page_transitions.dart';
import 'chat_conversation_screen.dart';
import 'chat_group_screen.dart';

class ChatContactsScreen extends StatefulWidget {
  const ChatContactsScreen({super.key});

  @override
  State<ChatContactsScreen> createState() => _ChatContactsScreenState();
}

class _ChatContactsScreenState extends State<ChatContactsScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  List<dynamic> _contacts = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
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
    final aurora = context.watch<SettingsProvider>().auroraTheme;
    final statusBarH = MediaQuery.of(context).padding.top;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: auroraBase(aurora, isDark),
      body: Column(
        children: [
          Container(
            padding: EdgeInsets.only(top: statusBarH, left: 4, right: 4),
            decoration: const BoxDecoration(
              color: Color(0xFF1E3A8A),
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
                    const Expanded(
                      child: Text(
                        'Xabarlar',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Colors.white),
                        textAlign: TextAlign.center,
                      ),
                    ),
                    const SizedBox(width: 48),
                  ],
                ),
                TabBar(
                  controller: _tabController,
                  indicatorColor: Colors.white,
                  indicatorWeight: 3,
                  labelColor: Colors.white,
                  unselectedLabelColor: Colors.white60,
                  labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                  unselectedLabelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w400),
                  tabs: const [
                    Tab(text: 'Foydalanuvchilar'),
                    Tab(text: 'Guruh'),
                  ],
                ),
              ],
            ),
          ),
          Expanded(child: TabBarView(
            controller: _tabController,
            children: [
              _buildContactsTab(isDark, sub),
              const ChatGroupScreen(),
            ],
          )),
        ],
      ),
    );
  }

  Widget _buildContactsTab(bool isDark, Color sub) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_contacts.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                color: const Color(0xFF4A6CF7).withOpacity(0.08),
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.chat_bubble_outline_rounded,
                  size: 36, color: sub),
            ),
            const SizedBox(height: 16),
            Text('Guruhda boshqa talaba topilmadi',
                style: TextStyle(
                    color: sub,
                    fontSize: 15,
                    fontWeight: FontWeight.w500)),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 20),
        itemCount: _contacts.length,
        itemBuilder: (_, i) => _buildContact(_contacts[i], isDark),
      ),
    );
  }

  Widget _buildContact(dynamic c, bool isDark) {
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final cardBg = isDark ? AppTheme.darkCard : Colors.white;
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

    final initials = name
        .split(' ')
        .take(2)
        .map((w) => w.isNotEmpty ? w[0].toUpperCase() : '')
        .join();

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: cardBg,
        borderRadius: BorderRadius.circular(16),
        elevation: isDark ? 0 : 1,
        shadowColor: Colors.black12,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () async {
            await Navigator.push(
              context,
              SlideFadePageRoute(
                builder: (_) => ChatConversationScreen(
                  contactId: contactId,
                  contactName: name,
                  contactImage: image,
                ),
              ),
            );
            _load();
          },
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: unread > 0
                    ? const Color(0xFF4A6CF7).withOpacity(0.4)
                    : isDark
                        ? Colors.white10
                        : Colors.grey.shade200,
                width: unread > 0 ? 1.5 : 1,
              ),
            ),
            child: Row(
              children: [
                Container(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: const Color(0xFF4A6CF7).withOpacity(0.2),
                      width: 2,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF4A6CF7).withOpacity(0.1),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: CircleAvatar(
                    radius: 26,
                    backgroundColor:
                        const Color(0xFF4A6CF7).withOpacity(0.08),
                    backgroundImage: image != null && image.isNotEmpty
                        ? NetworkImage(
                            '${ApiConfig.baseUrl}/image-proxy?url=$image')
                        : null,
                    child: image == null || image.isEmpty
                        ? Text(initials,
                            style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF4A6CF7)))
                        : null,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(name,
                                style: TextStyle(
                                    fontSize: 14.5,
                                    fontWeight: unread > 0
                                        ? FontWeight.w700
                                        : FontWeight.w600,
                                    color: txt),
                                overflow: TextOverflow.ellipsis),
                          ),
                          if (timeStr != null)
                            Text(timeStr,
                                style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: unread > 0
                                        ? FontWeight.w600
                                        : FontWeight.normal,
                                    color: unread > 0
                                        ? const Color(0xFF4A6CF7)
                                        : sub)),
                        ],
                      ),
                      const SizedBox(height: 5),
                      Row(
                        children: [
                          if (lastIsMe)
                            Padding(
                              padding: const EdgeInsets.only(right: 3),
                              child: Icon(Icons.done_all,
                                  size: 14,
                                  color: const Color(0xFF4A6CF7)
                                      .withOpacity(0.6)),
                            ),
                          Expanded(
                            child: Text(
                              lastMsg ?? 'Xabar yo\'q',
                              style: TextStyle(
                                  fontSize: 12.5,
                                  color: unread > 0 ? txt : sub,
                                  fontWeight: unread > 0
                                      ? FontWeight.w500
                                      : FontWeight.normal),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          if (unread > 0) ...[
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                gradient: const LinearGradient(
                                  colors: [
                                    Color(0xFF4A6CF7),
                                    Color(0xFF6C63FF),
                                  ],
                                ),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Text('$unread',
                                  style: const TextStyle(
                                      fontSize: 11,
                                      color: Colors.white,
                                      fontWeight: FontWeight.w700)),
                            ),
                          ],
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
