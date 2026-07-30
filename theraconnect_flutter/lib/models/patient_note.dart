class PatientNote {
  final String id;
  final String? title;
  final String body;
  final String? clinicianName;
  final String? createdAt;

  const PatientNote({
    required this.id,
    this.title,
    required this.body,
    this.clinicianName,
    this.createdAt,
  });

  factory PatientNote.fromJson(Map<String, dynamic> json) {
    return PatientNote(
      id: json['public_id'] as String,
      title: json['title'] as String?,
      body: json['body'] as String,
      clinicianName: json['clinician_name'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}
