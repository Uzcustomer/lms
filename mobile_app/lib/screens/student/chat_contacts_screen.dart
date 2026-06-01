import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import '../../utils/page_transitions.dart';
import '../../widgets/clinic_header.dart';
import '../../widgets/notification_bell.dart';
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
    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: 'FOYDALI',
            title: 'Xabarlar',
            onBack: () => Navigator.pop(context),
            actions: const [NotificationBell()],
          ),
          Container(
            decoration: BoxDecoration(
              color: ClinicTheme.surfaceOf(context),
              border: Border(
                bottom: BorderSide(color: ClinicTheme.dividerOf(context), width: 1),
              ),
            ),
            child: TabBar(
              controller: _tabController,
              indicatorColor: ClinicTheme.teal,
              indicatorWeight: 2.5,
              indicatorSize: TabBarIndicatorSize.tab,
              labelColor: ClinicTheme.teal,
              unselectedLabelColor: ClinicTheme.mutedOf(context),
              labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
              unselectedLabelStyle:
                  const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
              tabs: const [
                Tab(text: 'Foydalanuvchilar'),
                Tab(text: 'Guruh'),
              ],
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: _tabController,
              children: [
                _buildContactsTab(),
                const ChatGroupScreen(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildContactsTab() {
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
                color: ClinicTheme.teal.withOpacity(0.10),
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.chat_bubble_outline_rounded,
                  size: 36, color: ClinicTheme.teal),
            ),
            const SizedBox(height: 16),
            Text('Guruhda boshqa talaba topilmadi',
                style: TextStyle(
                    color: ClinicTheme.mutedOf(context),
                    fontSize: 14,
                    fontWeight: FontWeight.w600)),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 24),
        itemCount: _contacts.length,
        itemBuilder: (_, i) => _buildContact(_contacts[i]),
      ),
    );
  }

  Widget _buildContact(dynamic c) {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);
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
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
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
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: unread > 0 ? ClinicTheme.teal : ClinicTheme.dividerOf(context),
                width: unread > 0 ? 1.5 : 1,
              ),
              boxShadow: ClinicTheme.cardShadow,
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 25,
                  backgroundColor: ClinicTheme.teal,
                  backgroundImage: image != null && image.isNotEmpty
                      ? NetworkImage('${ApiConfig.baseUrl}/image-proxy?url=$image')
                      : null,
                  child: image == null || image.isEmpty
                      ? Text(initials,
                          style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                              color: Colors.white))
                      : null,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(name,
                                style: TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w800,
                                    color: ink),
                                overflow: TextOverflow.ellipsis),
                          ),
                          if (timeStr != null)
                            Text(timeStr,
                                style: TextStyle(
                                    fontSize: 11,
                                    fontWeight:
                                        unread > 0 ? FontWeight.w700 : FontWeight.w500,
                                    color: unread > 0 ? ClinicTheme.teal : muted)),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          if (lastIsMe)
                            Padding(
                              padding: const EdgeInsets.only(right: 3),
                              child: Icon(Icons.done_all, size: 14, color: muted),
                            ),
                          Expanded(
                            child: Text(
                              lastMsg ?? 'Xabar yo\'q',
                              style: TextStyle(
                                  fontSize: 12,
                                  color: unread > 0 ? ink : muted,
                                  fontWeight:
                                      unread > 0 ? FontWeight.w600 : FontWeight.w400),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          if (unread > 0) ...[
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 7, vertical: 2),
                              decoration: BoxDecoration(
                                color: ClinicTheme.teal,
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Text('$unread',
                                  style: const TextStyle(
                                      fontSize: 11,
                                      color: Colors.white,
                                      fontWeight: FontWeight.w800)),
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
