import 'dart:async';

import 'package:flutter/material.dart';

class InactivityGuard extends StatefulWidget {
  const InactivityGuard({
    super.key,
    required this.enabled,
    required this.onTimeout,
    required this.child,
    this.onActivity,
    this.timeout = const Duration(minutes: 10),
  });

  final bool enabled;
  final Future<void> Function() onTimeout;
  final Widget child;
  final VoidCallback? onActivity;
  final Duration timeout;

  @override
  State<InactivityGuard> createState() => _InactivityGuardState();
}

class _InactivityGuardState extends State<InactivityGuard>
    with WidgetsBindingObserver {
  Timer? _timer;
  DateTime? _lastActivityAt;
  bool _timingOut = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _configureTimer();
  }

  @override
  void didUpdateWidget(covariant InactivityGuard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.enabled != widget.enabled ||
        oldWidget.timeout != widget.timeout) {
      _configureTimer();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && widget.enabled) {
      _scheduleFromLastActivity();
    }
  }

  void _configureTimer() {
    _timer?.cancel();
    _timingOut = false;

    if (!widget.enabled) {
      _lastActivityAt = null;
      return;
    }

    _lastActivityAt = DateTime.now();
    widget.onActivity?.call();
    _scheduleFromLastActivity();
  }

  void _recordActivity([PointerEvent? _]) {
    if (!widget.enabled || _timingOut) return;
    _lastActivityAt = DateTime.now();
    widget.onActivity?.call();
    _scheduleFromLastActivity();
  }

  void _scheduleFromLastActivity() {
    _timer?.cancel();
    if (!widget.enabled || _lastActivityAt == null || _timingOut) return;

    final elapsed = DateTime.now().difference(_lastActivityAt!);
    final remaining = widget.timeout - elapsed;
    if (remaining <= Duration.zero) {
      unawaited(_handleTimeout());
      return;
    }

    _timer = Timer(remaining, _handleTimeout);
  }

  Future<void> _handleTimeout() async {
    if (!widget.enabled || _timingOut) return;
    _timingOut = true;
    _timer?.cancel();
    await widget.onTimeout();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: _recordActivity,
      onPointerMove: _recordActivity,
      onPointerSignal: _recordActivity,
      child: widget.child,
    );
  }
}
