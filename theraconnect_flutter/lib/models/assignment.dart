class Assignment {
  final int id;
  final int clinicianId;
  final String? clinicianName;
  final int patientId;
  final String title;
  final String? description;
  final String? attachmentUrl;
  final String? attachmentName;
  final String? dueDate;
  final String? submissionStatus;
  final String? submittedAt;
  final String? createdAt;
  final String? updatedAt;

  const Assignment({
    required this.id,
    required this.clinicianId,
    this.clinicianName,
    required this.patientId,
    required this.title,
    this.description,
    this.attachmentUrl,
    this.attachmentName,
    this.dueDate,
    this.submissionStatus,
    this.submittedAt,
    this.createdAt,
    this.updatedAt,
  });

  factory Assignment.fromJson(Map<String, dynamic> json) {
    return Assignment(
      id: json['id'] as int,
      clinicianId: json['clinician_id'] as int,
      clinicianName: json['clinician_name'] as String?,
      patientId: json['patient_id'] as int,
      title: json['title'] as String,
      description: json['description'] as String?,
      attachmentUrl: json['attachment_url'] as String?,
      attachmentName: json['attachment_name'] as String?,
      dueDate: json['due_date'] as String?,
      submissionStatus: json['submission_status'] as String?,
      submittedAt: json['submitted_at'] as String?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'clinician_id': clinicianId,
      'clinician_name': clinicianName,
      'patient_id': patientId,
      'title': title,
      'description': description,
      'attachment_url': attachmentUrl,
      'attachment_name': attachmentName,
      'due_date': dueDate,
      'submission_status': submissionStatus,
      'submitted_at': submittedAt,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  bool get hasAttachment => attachmentUrl != null;
  bool get isSubmitted =>
      submissionStatus == 'submitted' || submissionStatus == 'reviewed';
  bool get isReviewed => submissionStatus == 'reviewed';
}
