import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:theraconnect/widgets/joy_floating_chat_head.dart';

void main() {
  Widget subject({bool visible = true}) {
    return MaterialApp(
      home: Scaffold(
        body: JoyFloatingChatHead(
          visible: visible,
          onOpen: () {},
        ),
      ),
    );
  }

  testWidgets('invitation disappears after five seconds but Joy remains',
      (tester) async {
    await tester.pumpWidget(subject());

    expect(find.textContaining('Have questions?'), findsOneWidget);
    expect(find.byKey(const Key('joy-chat-head')), findsOneWidget);

    await tester.pump(const Duration(seconds: 5));

    expect(find.textContaining('Have questions?'), findsNothing);
    expect(find.byKey(const Key('joy-chat-head')), findsOneWidget);
  });

  testWidgets('chat head is circular and snaps to the nearest edge',
      (tester) async {
    await tester.pumpWidget(subject());
    await tester.pump(const Duration(seconds: 5));

    final material = tester.widget<Material>(
      find.byKey(const Key('joy-chat-head-material')),
    );
    expect(material.shape, isA<CircleBorder>());

    final head = find.byKey(const Key('joy-chat-head'));
    expect(tester.getCenter(head).dx, greaterThan(400));

    await tester.drag(head, const Offset(-700, -120));
    await tester.pumpAndSettle();
    expect(tester.getCenter(head).dx, closeTo(40, 0.1));

    await tester.drag(head, const Offset(700, 80));
    await tester.pumpAndSettle();
    expect(tester.getCenter(head).dx, closeTo(760, 0.1));
  });

  testWidgets('hidden state keeps the overlay out of messaging screens',
      (tester) async {
    await tester.pumpWidget(subject(visible: false));

    expect(find.byKey(const Key('joy-chat-head')), findsNothing);
    expect(find.textContaining('Have questions?'), findsNothing);
  });
}
