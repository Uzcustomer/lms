import 'dart:async';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/student_service.dart';
import '../utils/page_transitions.dart';
import '../screens/student/notifications_screen.dart';

/// Global unread-notification counter for the bell icon in every header.
/// Any screen can update it; all NotificationBell widgets rebuild instantly.
class NotificationBadge {
  static final ValueNotifier<int> unread = ValueNotifier<int>(0);
  static Timer? _poll;

  /// Fetch the latest unread count from the API and update [unread].
  static Future<void> refresh() async {
    try {
      final res = await StudentService(ApiService()).getNotificationsUnreadCount();
      final c = res['count'];
      if (c is int) {
        unread.value = c;
      } else if (c is String) {
        unread.value = int.tryParse(c) ?? unread.value;
      }
    } catch (_) {/* swallow — bell stays as-is */}
  }

  /// Start polling the unread count every [period]. Idempotent — safe to
  /// call from multiple places.
  static void startPolling({Duration period = const Duration(minutes: 1)}) {
    _poll?.cancel();
    refresh();
    _poll = Timer.periodic(period, (_) => refresh());
  }

  static void stopPolling() {
    _poll?.cancel();
    _poll = null;
  }
}

/// Bell IconButton that shows a red unread-count badge and opens the
/// notifications screen on tap. Drop-in replacement for the hardcoded
/// IconButton(Icons.notifications_outlined) across all student headers.
class NotificationBell extends StatelessWidget {
  final Color iconColor;
  final double iconSize;

  const NotificationBell({
    super.key,
    this.iconColor = Colors.white,
    this.iconSize = 22,
  });

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<int>(
      valueListenable: NotificationBadge.unread,
      builder: (_, count, __) {
        return Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.center,
          children: [
            IconButton(
              icon: Icon(Icons.notifications_outlined, color: iconColor, size: iconSize),
              onPressed: () async {
                await Navigator.of(context).push(
                  SlideFadePageRoute(builder: (_) => const NotificationsScreen()),
                );
                // After returning, the count likely changed.
                NotificationBadge.refresh();
              },
            ),
            if (count > 0)
              Positioned(
                right: 6,
                top: 6,
                child: Container(
                  padding: EdgeInsets.symmetric(
                    horizontal: count > 9 ? 5 : 4,
                    vertical: 2,
                  ),
                  constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE53935),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: Colors.white, width: 1.2),
                  ),
                  child: Text(
                    count > 99 ? '99+' : count.toString(),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      height: 1.1,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}
