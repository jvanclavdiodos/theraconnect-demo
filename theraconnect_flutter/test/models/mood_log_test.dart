import 'package:flutter_test/flutter_test.dart';
import 'package:theraconnect/models/mood_log.dart';

void main() {
  test(
      'uses the server-provided Manila date without deriving it from created_at',
      () {
    final mood = MoodLog.fromJson({
      'id': 1,
      'score': 8,
      'note': 'Calmer today',
      'logged_on': '2026-08-12',
      'created_at': '2026-08-11T16:05:00.000000Z',
    });

    expect(mood.loggedOn, '2026-08-12');
    expect(mood.createdAt, '2026-08-11T16:05:00.000000Z');
  });

  test('feed retains the API completion state and today entry', () {
    const mood = MoodLog(
      score: 7,
      loggedOn: '2026-08-12',
    );
    const feed = MoodLogFeed(
      logs: [mood],
      today: '2026-08-12',
      todayCompleted: true,
      todayLog: mood,
    );

    expect(feed.todayCompleted, isTrue);
    expect(feed.todayLog?.loggedOn, feed.today);
  });
}
