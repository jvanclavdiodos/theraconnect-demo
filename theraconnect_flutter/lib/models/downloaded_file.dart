class DownloadedFile {
  /// Original file name as sent by the server (e.g. "worksheet-3.docx").
  final String fileName;

  /// Source assignment title, shown in the in-app Downloads list.
  final String title;

  /// Path to the app-owned persistent copy (app documents dir). Used to
  /// re-open the file reliably via open_filex — opening the public MediaStore
  /// content:// URI is flaky across OEMs, an app-owned file path is not.
  final String localPath;

  /// MediaStore URI of the public Downloads/TheraConnect copy, if it was
  /// written successfully. Kept for reference only.
  final String? publicUri;

  final String? mimeType;
  final int sizeBytes;

  /// ISO-8601 timestamp of when the file was saved.
  final String savedAt;

  const DownloadedFile({
    required this.fileName,
    required this.title,
    required this.localPath,
    this.publicUri,
    this.mimeType,
    this.sizeBytes = 0,
    required this.savedAt,
  });

  factory DownloadedFile.fromJson(Map<String, dynamic> json) {
    return DownloadedFile(
      fileName: json['file_name'] as String,
      title: json['title'] as String? ?? json['file_name'] as String,
      localPath: json['local_path'] as String,
      publicUri: json['public_uri'] as String?,
      mimeType: json['mime_type'] as String?,
      sizeBytes: (json['size_bytes'] as num?)?.toInt() ?? 0,
      savedAt: json['saved_at'] as String,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'file_name': fileName,
      'title': title,
      'local_path': localPath,
      'public_uri': publicUri,
      'mime_type': mimeType,
      'size_bytes': sizeBytes,
      'saved_at': savedAt,
    };
  }

  DateTime? get savedAtDate => DateTime.tryParse(savedAt);
}
