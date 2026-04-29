import 'package:flutter/material.dart';

class SlideFadePageRoute<T> extends PageRouteBuilder<T> {
  SlideFadePageRoute({
    required this.builder,
    Duration duration = const Duration(milliseconds: 420),
    Duration reverseDuration = const Duration(milliseconds: 320),
    super.settings,
  }) : super(
          pageBuilder: (context, animation, secondaryAnimation) =>
              builder(context),
          transitionDuration: duration,
          reverseTransitionDuration: reverseDuration,
          transitionsBuilder: (context, animation, secondaryAnimation, child) {
            final curved = CurvedAnimation(
              parent: animation,
              curve: Curves.easeOutCubic,
              reverseCurve: Curves.easeInCubic,
            );
            return FadeTransition(
              opacity: curved,
              child: SlideTransition(
                position: Tween<Offset>(
                  begin: const Offset(0.12, 0),
                  end: Offset.zero,
                ).animate(curved),
                child: child,
              ),
            );
          },
        );

  final WidgetBuilder builder;
}

extension SlideFadeNavigator on NavigatorState {
  Future<T?> pushSlideFade<T>(WidgetBuilder builder, {RouteSettings? settings}) {
    return push<T>(SlideFadePageRoute<T>(builder: builder, settings: settings));
  }
}
