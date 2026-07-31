import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:theraconnect/services/inactivity_service.dart';

void main() {
  test('expires persisted authentication after ten minutes', () async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();
    final service = InactivityService(preferences);
    final activityAt = DateTime(2026, 7, 31, 9);

    await service.recordActivity(force: true, now: activityAt);

    expect(
      service.hasTimedOut(now: activityAt.add(const Duration(minutes: 9))),
      isFalse,
    );
    expect(
      service.hasTimedOut(now: activityAt.add(const Duration(minutes: 10))),
      isTrue,
    );
  });

  test('missing activity timestamp preserves existing signed-in users',
      () async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();

    expect(InactivityService(preferences).hasTimedOut(), isFalse);
  });
}
