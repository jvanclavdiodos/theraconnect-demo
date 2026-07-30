/// One daily self-reported mood check-in (1-10) on the server's Manila date.
class MoodLog {
  final int id;
  final int score;
  final String? note;
  final String loggedOn;
  final String? createdAt;

  const MoodLog({
    required this.id,
    required this.score,
    required this.loggedOn,
    this.note,
    this.createdAt,
  });

  factory MoodLog.fromJson(Map<String, dynamic> json) {
    return MoodLog(
      id: json['id'] as int,
      score: (json['score'] as num).toInt(),
      loggedOn: json['logged_on'] as String,
      note: json['note'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}

class MoodLogFeed {
  final List<MoodLog> logs;
  final String today;
  final bool todayCompleted;
  final MoodLog? todayLog;

  const MoodLogFeed({
    required this.logs,
    required this.today,
    required this.todayCompleted,
    this.todayLog,
  });
}
