import 'package:flutter_test/flutter_test.dart';
import 'package:theraconnect/models/user_guide.dart';

void main() {
  test('parses detailed guide workflow fields', () {
    final section = UserGuideSection.fromJson({
      'title': 'Book an appointment',
      'description': 'Choose a schedule.',
      'action': 'appointments',
      'before_you_start': 'Check your profile.',
      'steps': ['Open Appointments.', 'Select Book Appointment.'],
      'expected_result': 'The request is pending.',
      'tips': ['Select an available time.'],
    });

    expect(section.beforeYouStart, 'Check your profile.');
    expect(section.steps, hasLength(2));
    expect(section.expectedResult, 'The request is pending.');
    expect(section.tips.single, 'Select an available time.');
  });

  test('remains compatible with the original summary-only response', () {
    final section = UserGuideSection.fromJson({
      'title': 'Appointments',
      'description': 'Manage bookings.',
      'action': 'appointments',
    });

    expect(section.steps, isEmpty);
    expect(section.expectedResult, isEmpty);
    expect(section.tips, isEmpty);
  });
}
