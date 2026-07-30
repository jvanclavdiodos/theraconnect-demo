class Appointment {
  final String id;
  final String patientId;
  final String? clinicianId;
  final String? clinicianName;
  final String? clinicianEmail;
  final String? clinicianPhone;
  final String? clinicianSpecialization;
  final String? requestedAt;
  final String? scheduledAt;
  final String mode;
  final String? meetingLink;
  final bool meetingLinkActive;
  final String? meetingLinkExpiresAt;
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
    this.clinicianEmail,
    this.clinicianPhone,
    this.clinicianSpecialization,
    this.requestedAt,
    this.scheduledAt,
    required this.mode,
    this.meetingLink,
    this.meetingLinkActive = false,
    this.meetingLinkExpiresAt,
    required this.status,
    this.reason,
    this.clinicNotes,
    this.createdAt,
    this.updatedAt,
  });

  factory Appointment.fromJson(Map<String, dynamic> json) {
    final clinicianContact = json['clinician_contact'] as Map<String, dynamic>?;
    return Appointment(
      id: json['public_id'] as String,
      patientId: json['patient_public_id'] as String,
      clinicianId: json['clinician_public_id'] as String?,
      clinicianName: json['clinician_name'] as String?,
      clinicianEmail: clinicianContact?['email'] as String?,
      clinicianPhone: clinicianContact?['phone'] as String?,
      clinicianSpecialization: clinicianContact?['specialization'] as String?,
      requestedAt: json['requested_at'] as String?,
      scheduledAt: json['scheduled_at'] as String?,
      mode: json['mode'] as String? ?? 'in_person',
      meetingLink: json['meeting_link'] as String?,
      meetingLinkActive: (json['meeting_link_active'] as bool?) ?? false,
      meetingLinkExpiresAt: json['meeting_link_expires_at'] as String?,
      status: json['status'] as String? ?? 'pending',
      reason: json['reason'] as String?,
      clinicNotes: json['clinic_notes'] as String?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'public_id': id,
      'patient_public_id': patientId,
      'clinician_public_id': clinicianId,
      'clinician_name': clinicianName,
      'clinician_contact': {
        'email': clinicianEmail,
        'phone': clinicianPhone,
        'specialization': clinicianSpecialization,
      },
      'requested_at': requestedAt,
      'scheduled_at': scheduledAt,
      'mode': mode,
      'meeting_link': meetingLink,
      'meeting_link_active': meetingLinkActive,
      'meeting_link_expires_at': meetingLinkExpiresAt,
      'status': status,
      'reason': reason,
      'clinic_notes': clinicNotes,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  /// Online appointment whose meeting window has passed (link no longer offered).
  bool get meetingLinkExpired {
    if (mode != 'online' || meetingLinkExpiresAt == null) return false;
    final expiry = DateTime.tryParse(meetingLinkExpiresAt!);
    return !meetingLinkActive &&
        expiry != null &&
        expiry.isBefore(DateTime.now());
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
      case 'expired':
        return 'Expired';
      default:
        return status;
    }
  }
}
