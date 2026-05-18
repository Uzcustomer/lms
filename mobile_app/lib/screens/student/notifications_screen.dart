import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/theme.dart';
import '../../config/aurora_themes.dart';
import '../../providers/settings_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import '../../widgets/notification_bell.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final _service = StudentService(ApiService());
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) setState(() => _loading = true);
    try {
      final res = await _service.getNotifications(perPage: 50);
      final data = res['data'];
      if (mounted) {
        setState(() {
          _items = (data is List)
              ? data
                  .whereType<Map<String, dynamic>>()
                  .toList()
              : [];
          _loading = false;
          _error = null;
        });
      }
      // Refresh global badge with server-truth count.
      final unread = res['unread_count'];
      if (unread is int) NotificationBadge.unread.value = unread;
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = e.toString();
        });
      }
    }
  }

  Future<void> _markRead(int id) async {
    // Optimistic UI update
    setState(() {
      _items = _items.map((n) {
        if (n['id'] == id && n['read_at'] == null) {
          return {...n, 'read_at': DateTime.now().toIso8601String()};
        }
        return n;
      }).toList();
    });
    if (NotificationBadge.unread.value > 0) {
      NotificationBadge.unread.value = NotificationBadge.unread.value - 1;
    }
    try {
      await _service.markNotificationRead(id);
    } catch (_) {/* server-side will resync on next poll */}
  }

  Future<void> _markAll() async {
    final hadUnread = _items.any((n) => n['read_at'] == null);
    if (!hadUnread) return;
    setState(() {
      final nowIso = DateTime.now().toIso8601String();
      _items = _items
          .map((n) => n['read_at'] == null ? {...n, 'read_at': nowIso} : n)
          .toList();
    });
    NotificationBadge.unread.value = 0;
    try {
      await _service.markAllNotificationsRead();
    } catch (_) {}
  }

  Future<void> _openLink(String link) async {
    final uri = Uri.tryParse(link);
    if (uri == null) return;
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final aurora = context.watch<SettingsProvider>().auroraTheme;
    final statusBarH = MediaQuery.of(context).padding.top;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;

    return Scaffold(
      backgroundColor: auroraBase(aurora, isDark),
      body: Column(
        children: [
          Container(
            padding: EdgeInsets.only(top: statusBarH, left: 4, right: 4),
            height: statusBarH + 64,
            decoration: BoxDecoration(
              color: isDark ? AppTheme.darkHeaderColor : const Color(0xFF1E3A8A),
              borderRadius: const BorderRadius.only(
                bottomLeft: Radius.circular(18),
                bottomRight: Radius.circular(18),
              ),
            ),
            child: Row(
              children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back, color: Colors.white, size: 22),
                  onPressed: () => Navigator.pop(context),
                ),
                const Expanded(
                  child: Text(
                    'Bildirishnomalar',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Colors.white),
                    textAlign: TextAlign.center,
                  ),
                ),
                TextButton(
                  onPressed: _markAll,
                  child: const Text(
                    'Hammasi',
                    style: TextStyle(fontSize: 12, color: Colors.white, fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? _buildError(subColor)
                    : RefreshIndicator(
                        onRefresh: _load,
                        child: _items.isEmpty
                            ? _buildEmpty(subColor)
                            : ListView.separated(
                                padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                                itemCount: _items.length,
                                separatorBuilder: (_, __) => const SizedBox(height: 10),
                                itemBuilder: (_, i) => _buildTile(_items[i], cardColor, textColor, subColor, isDark),
                              ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmpty(Color subColor) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        SizedBox(
          height: 300,
          child: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.notifications_none_rounded, size: 56, color: subColor),
                const SizedBox(height: 12),
                Text('Bildirishnoma yo\'q', style: TextStyle(fontSize: 14, color: subColor)),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildError(Color subColor) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, color: subColor, size: 48),
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Text(_error ?? '', style: TextStyle(color: subColor), textAlign: TextAlign.center),
          ),
          const SizedBox(height: 16),
          ElevatedButton(onPressed: _load, child: const Text('Qayta urinish')),
        ],
      ),
    );
  }

  Widget _buildTile(
    Map<String, dynamic> n,
    Color cardColor,
    Color textColor,
    Color subColor,
    bool isDark,
  ) {
    final id = n['id'] is int ? n['id'] as int : int.tryParse('${n['id']}') ?? 0;
    final unread = n['read_at'] == null;
    final title = n['title']?.toString() ?? '';
    final message = n['message']?.toString() ?? '';
    final link = n['link']?.toString();
    final created = n['created_at']?.toString() ?? '';
    final dotColor = _typeColor(n['type']?.toString() ?? 'sms');

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () async {
          if (unread) await _markRead(id);
          if (link != null && link.isNotEmpty) await _openLink(link);
        },
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: unread
                ? (isDark ? dotColor.withOpacity(0.10) : dotColor.withOpacity(0.06))
                : cardColor,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: dotColor.withOpacity(0.18),
                  shape: BoxShape.circle,
                ),
                child: Icon(_typeIcon(n['type']?.toString() ?? 'sms'),
                    size: 20, color: dotColor),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            title.isEmpty ? '—' : title,
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: unread ? FontWeight.w800 : FontWeight.w600,
                              color: textColor,
                            ),
                          ),
                        ),
                        if (unread)
                          Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: Color(0xFFE53935),
                              shape: BoxShape.circle,
                            ),
                          ),
                      ],
                    ),
                    if (message.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(message, style: TextStyle(fontSize: 12.5, color: subColor)),
                    ],
                    if (created.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(_humanTime(created),
                          style: TextStyle(fontSize: 11, color: subColor.withOpacity(0.8))),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Color _typeColor(String type) {
    switch (type) {
      case 'grade':
        return const Color(0xFF43A047);
      case 'excuse':
      case 'appeal':
        return const Color(0xFF7C4DFF);
      case 'warning':
        return const Color(0xFFE53935);
      case 'sms':
        return const Color(0xFF1565C0);
      default:
        return const Color(0xFF6366F1);
    }
  }

  IconData _typeIcon(String type) {
    switch (type) {
      case 'grade':
        return Icons.grade_outlined;
      case 'excuse':
      case 'appeal':
        return Icons.assignment_outlined;
      case 'warning':
        return Icons.warning_amber_rounded;
      case 'sms':
        return Icons.sms_outlined;
      default:
        return Icons.notifications_outlined;
    }
  }

  String _humanTime(String iso) {
    final dt = DateTime.tryParse(iso);
    if (dt == null) return iso;
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 1) return 'hozir';
    if (diff.inHours < 1) return '${diff.inMinutes} daqiqa oldin';
    if (diff.inDays < 1) return '${diff.inHours} soat oldin';
    if (diff.inDays < 7) return '${diff.inDays} kun oldin';
    return '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')}.${dt.year}';
  }
}
