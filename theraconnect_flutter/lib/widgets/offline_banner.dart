import 'dart:async';

import 'package:flutter/material.dart';

class OfflineBanner extends StatefulWidget {
  final String message;
  final Future<void> Function()? onRetry;

  const OfflineBanner({
    super.key,
    this.message = 'You are offline. Showing messages saved on this device.',
    this.onRetry,
  });

  @override
  State<OfflineBanner> createState() => _OfflineBannerState();
}

class _OfflineBannerState extends State<OfflineBanner> {
  Timer? _retryTimer;

  @override
  void initState() {
    super.initState();
    if (widget.onRetry != null) {
      _retryTimer = Timer.periodic(
        const Duration(seconds: 10),
        (_) => widget.onRetry!(),
      );
    }
  }

  @override
  void dispose() {
    _retryTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Material(
      color: scheme.secondaryContainer,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(
          children: [
            Icon(Icons.cloud_off_outlined,
                size: 20, color: scheme.onSecondaryContainer),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                widget.message,
                style: TextStyle(color: scheme.onSecondaryContainer),
              ),
            ),
            if (widget.onRetry != null)
              IconButton(
                onPressed: widget.onRetry,
                tooltip: 'Try again',
                icon: const Icon(Icons.refresh),
                color: scheme.onSecondaryContainer,
              ),
          ],
        ),
      ),
    );
  }
}
