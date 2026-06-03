import 'dart:async';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/student_service.dart';
import '../utils/page_transitions.dart';
import '../screens/student/notifications_screen.dart';
import 'clinic_header.dart';

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

/// Bell icon for headers. Shows a red unread-count badge in the top-right
/// corner of its 38×38 container. Two variants:
///   • default → light-themed (used inside clinic white headers)
///   • iconColor: Colors.white → on dark/gradient headers
class NotificationBell extends StatelessWidget {
  final Color? iconColor;
  final double iconSize;

  const NotificationBell({
    super.key,
    this.iconColor,
    this.iconSize = 18,
  });

  @override
  Widget build(BuildContext context) {
    final useClinic = iconColor == null;
    return ValueListenableBuilder<int>(
      valueListenable: NotificationBadge.unread,
      builder: (_, count, __) {
        final bell = useClinic
            ? ClinicIconButton(
                icon: Icons.notifications_outlined,
                onTap: () => _openNotifications(context),
              )
            : SizedBox(
                width: 38,
                height: 38,
                child: IconButton(
                  padding: EdgeInsets.zero,
                  icon: Icon(Icons.notifications_outlined,
                      color: iconColor, size: iconSize + 4),
                  onPressed: () => _openNotifications(context),
                ),
              );

        return SizedBox(
          width: 38,
          height: 38,
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              bell,
              if (count > 0)
                Positioned(
                  right: -2,
                  top: -2,
                  child: Container(
                    padding: EdgeInsets.symmetric(
                      horizontal: count > 9 ? 5 : 4,
                      vertical: 1,
                    ),
                    constraints:
                        const BoxConstraints(minWidth: 18, minHeight: 18),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE53935),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: useClinic
                            ? ClinicTheme.surfaceOf(context)
                            : Colors.white,
                        width: 1.5,
                      ),
                    ),
                    child: Text(
                      count > 99 ? '99+' : count.toString(),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        height: 1.15,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _openNotifications(BuildContext context) async {
    await Navigator.of(context).push(
      SlideFadePageRoute(builder: (_) => const NotificationsScreen()),
    );
    NotificationBadge.refresh();
  }
}
