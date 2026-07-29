/// The patient's own submission for an assignment (for in-app preview).
class AssignmentSubmission {
  final int id;
  final String? content;
  final String? originalName;
  final String kind; // image | pdf | text | other
  final String? fileUrl;

  const AssignmentSubmission({
    required this.id,
    this.content,
    this.originalName,
    required this.kind,
    this.fileUrl,
  });

  factory AssignmentSubmission.fromJson(Map<String, dynamic> json) {
    return AssignmentSubmission(
      id: json['id'] as int,
      content: json['content'] as String?,
      originalName: json['original_name'] as String?,
      kind: (json['kind'] as String?) ?? 'other',
      fileUrl: json['file_url'] as String?,
    );
  }

  bool get hasFile => fileUrl != null;
}

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
  final AssignmentSubmission? submission;
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
    this.submission,
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
      submission: json['submission'] is Map<String, dynamic>
          ? AssignmentSubmission.fromJson(json['submission'] as Map<String, dynamic>)
          : null,
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
