import 'package:flutter/material.dart';

class SlideFadePageRoute<T> extends PageRouteBuilder<T> {
  SlideFadePageRoute({
    required this.builder,
    Duration duration = const Duration(milliseconds: 320),
    Duration reverseDuration = const Duration(milliseconds: 280),
    super.settings,
  }) : super(
          pageBuilder: (context, animation, secondaryAnimation) =>
              builder(context),
          transitionDuration: duration,
          reverseTransitionDuration: reverseDuration,
          transitionsBuilder: (context, animation, secondaryAnimation, child) {
            final incoming = Tween<Offset>(
              begin: const Offset(1.0, 0),
              end: Offset.zero,
            ).animate(CurvedAnimation(
              parent: animation,
              curve: Curves.fastOutSlowIn,
              reverseCurve: Curves.fastOutSlowIn.flipped,
            ));

            final outgoing = Tween<Offset>(
              begin: Offset.zero,
              end: const Offset(-0.25, 0),
            ).animate(CurvedAnimation(
              parent: secondaryAnimation,
              curve: Curves.fastOutSlowIn,
              reverseCurve: Curves.fastOutSlowIn.flipped,
            ));

            return SlideTransition(
              position: outgoing,
              child: SlideTransition(
                position: incoming,
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
