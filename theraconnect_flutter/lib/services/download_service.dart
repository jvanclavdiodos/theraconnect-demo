import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:media_store_plus/media_store_plus.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/downloaded_file.dart';

/// Downloads files through the authenticated [Dio] client and persists them in
/// two places:
///  1. the app's own documents dir (`.../downloads/`) — the durable copy used
///     to re-open files in-app via open_filex (an app-owned file path is far
///     more reliable to open than a public MediaStore content:// URI), and
///  2. the public `Download/TheraConnect` folder via MediaStore, so the user
///     can find them in the phone's Downloads.
///
/// A lightweight JSON index in shared_preferences backs the in-app Downloads
/// list — scoped storage on Android 10+ makes listing the public folder
/// unreliable, so we never scan it.
class DownloadService {
  final Dio _dio;
  final SharedPreferences _prefs;

  static const _indexKey = 'downloads_index';

  DownloadService(this._dio, this._prefs);

  Future<DownloadedFile> downloadAndStore({
    required String urlPath,
    required String fileName,
    required String title,
  }) async {
    final safeName = fileName.replaceAll(RegExp(r'[\\/]+'), '_').trim();
    final name = safeName.isEmpty ? 'download' : safeName;

    // 1. Durable app-owned copy (never auto-purged, unlike the temp/cache dir).
    final docsDir = await getApplicationDocumentsDirectory();
    final downloadsDir = Directory('${docsDir.path}/downloads');
    if (!await downloadsDir.exists()) {
      await downloadsDir.create(recursive: true);
    }
    final localPath = '${downloadsDir.path}/$name';
    await _dio.download(urlPath, localPath);

    final size = await File(localPath).length();

    // 2. Public Download/TheraConnect copy via MediaStore. Best-effort: if it
    //    fails (older OS, denied permission), the app-owned copy and the in-app
    //    list still work. saveFile() consumes (deletes) the temp file it's
    //    given, so hand it a throwaway copy and keep localPath intact.
    String? publicUri;
    try {
      await _ensureStoragePermission();
      final tempDir = await getTemporaryDirectory();
      final tempCopy = '${tempDir.path}/$name';
      await File(localPath).copy(tempCopy);
      final info = await MediaStore().saveFile(
        tempFilePath: tempCopy,
        dirType: DirType.download,
        dirName: DirName.download,
      );
      publicUri = info?.uri.toString();
    } catch (_) {
      // Public copy is optional; keep going with the app-owned copy.
    }

    final entry = DownloadedFile(
      fileName: name,
      title: title,
      localPath: localPath,
      publicUri: publicUri,
      mimeType: _guessMime(name),
      sizeBytes: size,
      savedAt: DateTime.now().toIso8601String(),
    );

    _saveIndex([entry, ..._readIndex().where((e) => e.fileName != name)]);
    return entry;
  }

  List<DownloadedFile> list() => _readIndex();

  Future<void> remove(DownloadedFile file) async {
    // Per design, only the app-owned copy and the index entry are removed; the
    // public Download/TheraConnect copy is left for the user to manage.
    try {
      final f = File(file.localPath);
      if (await f.exists()) await f.delete();
    } catch (_) {}
    _saveIndex(_readIndex().where((e) => e.fileName != file.fileName).toList());
  }

  /// Whether the durable app-owned copy still exists on disk.
  Future<bool> exists(DownloadedFile file) => File(file.localPath).exists();

  // --- index persistence (same jsonEncode/jsonDecode style as CacheService) ---

  List<DownloadedFile> _readIndex() {
    final raw = _prefs.getString(_indexKey);
    if (raw == null) return [];
    try {
      final list = jsonDecode(raw) as List<dynamic>;
      return list
          .map((e) => DownloadedFile.fromJson(e as Map<String, dynamic>))
          .toList();
    } catch (_) {
      return [];
    }
  }

  void _saveIndex(List<DownloadedFile> files) {
    _prefs.setString(
      _indexKey,
      jsonEncode(files.map((e) => e.toJson()).toList()),
    );
  }

  Future<void> _ensureStoragePermission() async {
    // API 29+ writes to the Downloads collection without any permission; only
    // legacy Android (<= 28) needs WRITE_EXTERNAL_STORAGE.
    int sdkInt;
    try {
      sdkInt = await MediaStore().getPlatformSDKInt();
    } catch (_) {
      return; // can't determine; let the save attempt decide
    }
    if (sdkInt <= 28) {
      final status = await Permission.storage.request();
      if (!status.isGranted) {
        throw Exception('Storage permission denied');
      }
    }
  }

  String _guessMime(String fileName) {
    final ext = fileName.contains('.')
        ? fileName.split('.').last.toLowerCase()
        : '';
    switch (ext) {
      case 'pdf':
        return 'application/pdf';
      case 'doc':
        return 'application/msword';
      case 'docx':
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
      case 'xls':
        return 'application/vnd.ms-excel';
      case 'xlsx':
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
      case 'png':
        return 'image/png';
      case 'jpg':
      case 'jpeg':
        return 'image/jpeg';
      case 'txt':
        return 'text/plain';
      default:
        return 'application/octet-stream';
    }
  }
}
