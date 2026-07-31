import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:theraconnect/widgets/inactivity_guard.dart';

void main() {
  testWidgets('logs out after the configured inactivity period',
      (tester) async {
    var timeoutCount = 0;

    await tester.pumpWidget(
      MaterialApp(
        home: InactivityGuard(
          enabled: true,
          timeout: const Duration(minutes: 10),
          onTimeout: () async => timeoutCount++,
          child: const Scaffold(body: Text('Dashboard')),
        ),
      ),
    );

    await tester.pump(const Duration(minutes: 10));

    expect(timeoutCount, 1);
  });

  testWidgets('user interaction resets the inactivity period', (tester) async {
    var timeoutCount = 0;

    await tester.pumpWidget(
      MaterialApp(
        home: InactivityGuard(
          enabled: true,
          timeout: const Duration(minutes: 10),
          onTimeout: () async => timeoutCount++,
          child: const Scaffold(body: Text('Dashboard')),
        ),
      ),
    );

    await tester.pump(const Duration(minutes: 9));
    await tester.tap(find.text('Dashboard'));
    await tester.pump();
    await tester.pump(const Duration(minutes: 9));

    expect(timeoutCount, 0);

    await tester.pump(const Duration(minutes: 1));
    expect(timeoutCount, 1);
  });

  testWidgets('does not run while unauthenticated', (tester) async {
    var timeoutCount = 0;

    await tester.pumpWidget(
      MaterialApp(
        home: InactivityGuard(
          enabled: false,
          timeout: const Duration(minutes: 10),
          onTimeout: () async => timeoutCount++,
          child: const Scaffold(body: Text('Login')),
        ),
      ),
    );

    await tester.pump(const Duration(minutes: 11));

    expect(timeoutCount, 0);
  });
}
