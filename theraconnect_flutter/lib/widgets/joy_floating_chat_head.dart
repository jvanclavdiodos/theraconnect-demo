import 'dart:async';

import 'package:flutter/material.dart';

import 'joy_avatar.dart';

class JoyFloatingChatHead extends StatefulWidget {
  final bool visible;
  final VoidCallback onOpen;
  final Duration invitationDuration;

  const JoyFloatingChatHead({
    super.key,
    required this.visible,
    required this.onOpen,
    this.invitationDuration = const Duration(seconds: 5),
  });

  @override
  State<JoyFloatingChatHead> createState() => _JoyFloatingChatHeadState();
}

class _JoyFloatingChatHeadState extends State<JoyFloatingChatHead> {
  static const _diameter = 56.0;
  static const _edgeInset = 12.0;
  static const _invitationWidth = 190.0;

  Timer? _invitationTimer;
  Offset? _position;
  bool _showInvitation = true;
  bool _dragging = false;
  bool _dockedLeft = false;

  @override
  void initState() {
    super.initState();
    _invitationTimer = Timer(widget.invitationDuration, () {
      if (mounted) setState(() => _showInvitation = false);
    });
  }

  @override
  void dispose() {
    _invitationTimer?.cancel();
    super.dispose();
  }

  double _bounded(double value, double minimum, double maximum) {
    if (maximum <= minimum) return minimum;
    return value.clamp(minimum, maximum).toDouble();
  }

  @override
  Widget build(BuildContext context) {
    return Offstage(
      offstage: !widget.visible,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final padding = MediaQuery.paddingOf(context);
          final minimumX = _edgeInset;
          final maximumX = constraints.maxWidth - _diameter - _edgeInset;
          final minimumY = padding.top + _edgeInset;
          final maximumY =
              constraints.maxHeight - _diameter - padding.bottom - _edgeInset;
          final stored = _position ?? Offset(maximumX, maximumY);
          final headPosition = Offset(
            _dragging
                ? _bounded(stored.dx, minimumX, maximumX)
                : (_dockedLeft ? minimumX : maximumX),
            _bounded(stored.dy, minimumY, maximumY),
          );
          final invitationOnRight =
              headPosition.dx + (_diameter / 2) <= constraints.maxWidth / 2;
          final invitationLeft = _bounded(
            invitationOnRight
                ? headPosition.dx + _diameter + 8
                : headPosition.dx - _invitationWidth - 8,
            8,
            constraints.maxWidth - _invitationWidth - 8,
          );

          return Stack(
            clipBehavior: Clip.none,
            children: [
              if (_showInvitation)
                AnimatedPositioned(
                  duration: _dragging
                      ? Duration.zero
                      : const Duration(milliseconds: 180),
                  curve: Curves.easeOut,
                  left: invitationLeft,
                  top: headPosition.dy + 6,
                  width: _invitationWidth,
                  child: Material(
                    elevation: 4,
                    color: Theme.of(context).colorScheme.surface,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                      side: BorderSide(
                        color: Theme.of(context).colorScheme.primary,
                      ),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: InkWell(
                      onTap: widget.onOpen,
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 10,
                        ),
                        child: Text.rich(
                          TextSpan(
                            text: 'Have questions? ',
                            children: [
                              TextSpan(
                                text: 'Talk to Joy.',
                                style: TextStyle(
                                  color: Theme.of(context).colorScheme.primary,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              AnimatedPositioned(
                duration: _dragging
                    ? Duration.zero
                    : const Duration(milliseconds: 180),
                curve: Curves.easeOut,
                left: headPosition.dx,
                top: headPosition.dy,
                width: _diameter,
                height: _diameter,
                child: GestureDetector(
                  key: const Key('joy-chat-head'),
                  behavior: HitTestBehavior.opaque,
                  onTap: widget.onOpen,
                  onPanStart: (_) {
                    setState(() {
                      _position = headPosition;
                      _dragging = true;
                    });
                  },
                  onPanUpdate: (details) {
                    final current = _position ?? headPosition;
                    setState(() {
                      _position = Offset(
                        _bounded(
                          current.dx + details.delta.dx,
                          minimumX,
                          maximumX,
                        ),
                        _bounded(
                          current.dy + details.delta.dy,
                          minimumY,
                          maximumY,
                        ),
                      );
                    });
                  },
                  onPanEnd: (_) {
                    final current = _position ?? headPosition;
                    setState(() {
                      _dockedLeft = current.dx + (_diameter / 2) <=
                          constraints.maxWidth / 2;
                      _position = Offset(
                        _dockedLeft ? minimumX : maximumX,
                        _bounded(current.dy, minimumY, maximumY),
                      );
                      _dragging = false;
                    });
                  },
                  child: Tooltip(
                    message: 'Open Joy assistant',
                    child: Material(
                      key: const Key('joy-chat-head-material'),
                      elevation: 6,
                      color: Theme.of(context).colorScheme.primaryContainer,
                      shape: const CircleBorder(),
                      clipBehavior: Clip.antiAlias,
                      child: const Center(child: JoyAvatar(size: 30)),
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
