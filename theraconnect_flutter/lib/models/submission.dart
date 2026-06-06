class Submission {
  final int id;
  final int assignmentId;
  final int patientId;
  final String? content;
  final String? filePath;
  final String? fileUrl;
  final String status;
  final String? submittedAt;
  final String? reviewedAt;
  final String? createdAt;
  final String? updatedAt;

  const Submission({
    required this.id,
    required this.assignmentId,
    required this.patientId,
    this.content,
    this.filePath,
    this.fileUrl,
    required this.status,
    this.submittedAt,
    this.reviewedAt,
    this.createdAt,
    this.updatedAt,
  });

  factory Submission.fromJson(Map<String, dynamic> json) {
    return Submission(
      id: json['id'] as int,
      assignmentId: json['assignment_id'] as int,
      patientId: json['patient_id'] as int,
      content: json['content'] as String?,
      filePath: json['file_path'] as String?,
      fileUrl: json['file_url'] as String?,
      status: json['status'] as String? ?? 'submitted',
      submittedAt: json['submitted_at'] as String?,
      reviewedAt: json['reviewed_at'] as String?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'assignment_id': assignmentId,
      'patient_id': patientId,
      'content': content,
      'file_path': filePath,
      'file_url': fileUrl,
      'status': status,
      'submitted_at': submittedAt,
      'reviewed_at': reviewedAt,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }
}
