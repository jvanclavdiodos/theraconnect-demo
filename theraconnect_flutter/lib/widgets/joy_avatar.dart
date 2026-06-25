import 'package:flutter/widgets.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Joy's brand mark — the same custom teal badge used on the web portal
/// (public/img/joy-avatar.svg). Used wherever the assistant appears so the
/// identity is consistent across the app.
class JoyAvatar extends StatelessWidget {
  final double size;

  const JoyAvatar({super.key, this.size = 24});

  @override
  Widget build(BuildContext context) {
    return SvgPicture.asset(
      'assets/joy-avatar.svg',
      width: size,
      height: size,
      semanticsLabel: 'Joy',
    );
  }
}
