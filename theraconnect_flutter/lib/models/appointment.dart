class Appointment {
  final int id;
  final int patientId;
  final int? clinicianId;
  final String? clinicianName;
  final String? requestedAt;
  final String? scheduledAt;
  final String mode;
  final String? meetingLink;
  final String status;
  final String? reason;
  final String? clinicNotes;
  final String? createdAt;
  final String? updatedAt;

  const Appointment({
    required this.id,
    required this.patientId,
    this.clinicianId,
    this.clinicianName,
    this.requestedAt,
    this.scheduledAt,
    required this.mode,
    this.meetingLink,
    required this.status,
    this.reason,
    this.clinicNotes,
    this.createdAt,
    this.updatedAt,
  });

  factory Appointment.fromJson(Map<String, dynamic> json) {
    return Appointment(
      id: json['id'] as int,
      patientId: json['patient_id'] as int,
      clinicianId: json['clinician_id'] as int?,
      clinicianName: json['clinician_name'] as String?,
      requestedAt: json['requested_at'] as String?,
      scheduledAt: json['scheduled_at'] as String?,
      mode: json['mode'] as String? ?? 'in_person',
      meetingLink: json['meeting_link'] as String?,
      status: json['status'] as String? ?? 'pending',
      reason: json['reason'] as String?,
      clinicNotes: json['clinic_notes'] as String?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'patient_id': patientId,
      'clinician_id': clinicianId,
      'clinician_name': clinicianName,
      'requested_at': requestedAt,
      'scheduled_at': scheduledAt,
      'mode': mode,
      'meeting_link': meetingLink,
      'status': status,
      'reason': reason,
      'clinic_notes': clinicNotes,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  String get statusLabel {
    switch (status) {
      case 'pending':
        return 'Pending';
      case 'approved':
        return 'Approved';
      case 'rejected':
        return 'Rejected';
      case 'rescheduled':
        return 'Rescheduled';
      case 'completed':
        return 'Completed';
      case 'cancelled':
        return 'Cancelled';
      default:
        return status;
    }
  }
}
