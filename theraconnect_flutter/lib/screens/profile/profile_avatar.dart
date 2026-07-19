import 'dart:io';
import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_cropper/image_cropper.dart';
import '../../providers/profile_provider.dart';
import '../../theme/app_theme.dart';

/// Circular profile picture. By default it shows a tap-to-change camera button
/// (profile screen); set [editable] to false for a read-only display (e.g. the
/// dashboard account header). [radius] sizes the avatar.
class ProfileAvatar extends ConsumerStatefulWidget {
  final bool hasAvatar;
  final double radius;
  final bool editable;

  const ProfileAvatar({
    super.key,
    required this.hasAvatar,
    this.radius = 40,
    this.editable = true,
  });

  @override
  ConsumerState<ProfileAvatar> createState() => _ProfileAvatarState();
}

class _ProfileAvatarState extends ConsumerState<ProfileAvatar> {
  Future<Uint8List>? _bytes;
  bool _uploading = false;

  @override
  void initState() {
    super.initState();
    if (widget.hasAvatar) _load();
  }

  @override
  void didUpdateWidget(ProfileAvatar old) {
    super.didUpdateWidget(old);
    if (widget.hasAvatar && _bytes == null) _load();
  }

  void _load() {
    setState(() => _bytes = ref.read(profileApiProvider).getAvatarBytes());
  }

  Future<void> _pickAndUpload() async {
    final result = await FilePicker.platform.pickFiles(type: FileType.image);
    final path = result?.files.single.path;
    if (path == null || !mounted) return;

    final colorScheme = Theme.of(context).colorScheme;
    CroppedFile? cropped;
    try {
      cropped = await ImageCropper().cropImage(
        sourcePath: path,
        maxWidth: 1024,
        maxHeight: 1024,
        compressFormat: ImageCompressFormat.jpg,
        compressQuality: 90,
        aspectRatio: const CropAspectRatio(ratioX: 1, ratioY: 1),
        uiSettings: [
          AndroidUiSettings(
            toolbarTitle: 'Adjust profile photo',
            toolbarColor: colorScheme.primary,
            toolbarWidgetColor: colorScheme.onPrimary,
            activeControlsWidgetColor: colorScheme.primary,
            lockAspectRatio: true,
            cropStyle: CropStyle.circle,
            aspectRatioPresets: const [CropAspectRatioPreset.square],
          ),
          IOSUiSettings(
            title: 'Adjust profile photo',
            doneButtonTitle: 'Save',
            cancelButtonTitle: 'Cancel',
            aspectRatioLockEnabled: true,
            resetAspectRatioEnabled: false,
            cropStyle: CropStyle.circle,
            aspectRatioPresets: const [CropAspectRatioPreset.square],
          ),
        ],
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text(
              'The photo could not be opened. Choose another image.'),
          backgroundColor: colorScheme.error,
        ),
      );
      return;
    }

    if (cropped == null || !mounted) return;

    if (await File(cropped.path).length() > 2 * 1024 * 1024) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('The adjusted photo must be 2 MB or smaller.'),
          backgroundColor: colorScheme.error,
        ),
      );
      return;
    }

    setState(() => _uploading = true);
    final error =
        await ref.read(profileProvider.notifier).uploadAvatar(cropped.path);
    if (!mounted) return;
    setState(() => _uploading = false);

    if (error != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error), backgroundColor: colorScheme.error),
      );
    } else {
      _load(); // refetch the new image
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Profile picture updated!'),
            backgroundColor: AppTheme.success),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    final radius = widget.radius;

    return Stack(
      children: [
        CircleAvatar(
          radius: radius,
          backgroundColor: scheme.primaryContainer,
          child: widget.hasAvatar
              ? FutureBuilder<Uint8List>(
                  future: _bytes,
                  builder: (context, snap) {
                    if (snap.hasData) {
                      return CircleAvatar(
                          radius: radius,
                          backgroundImage: MemoryImage(snap.data!));
                    }
                    return const SizedBox(
                      width: 24,
                      height: 24,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    );
                  },
                )
              : Icon(Icons.person,
                  size: radius, color: scheme.onPrimaryContainer),
        ),
        if (widget.editable)
          Positioned(
            right: 0,
            bottom: 0,
            child: Material(
              color: scheme.primary,
              shape: const CircleBorder(),
              child: InkWell(
                customBorder: const CircleBorder(),
                onTap: _uploading ? null : _pickAndUpload,
                child: Padding(
                  padding: const EdgeInsets.all(6),
                  child: _uploading
                      ? SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: scheme.onPrimary))
                      : Icon(Icons.camera_alt,
                          size: 16, color: scheme.onPrimary),
                ),
              ),
            ),
          ),
      ],
    );
  }
}
