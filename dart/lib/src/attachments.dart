import 'dart:convert';
import 'dart:typed_data';

import 'errors.dart';

/// Maximum number of file attachments allowed per email.
const int maxAttachmentsPerEmail = 5;

/// Maximum decoded size of a single attachment (5 MB).
const int maxSingleAttachmentBytes = 5 * 1024 * 1024;

/// Maximum combined decoded size of all attachments in one email (7.5 MB).
const int maxTotalAttachmentsBytes = 7864320;

/// Strict business-only file extension allowlist mapped to canonical MIME types.
const Map<String, String> allowedBusinessExtensions = {
  '.pdf': 'application/pdf',
  '.doc': 'application/msword',
  '.docx':
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  '.xls': 'application/vnd.ms-excel',
  '.xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  '.csv': 'text/csv',
  '.ppt': 'application/vnd.ms-powerpoint',
  '.pptx':
      'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  '.txt': 'text/plain',
  '.rtf': 'application/rtf',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
};

final _dataUrlRe = RegExp(r'^data:[^;]+;base64,(.+)$', caseSensitive: false, dotAll: true);

/// A validated business file attachment ready to send via VinMail.
class VinMailAttachment {
  /// Filename including extension (e.g. `invoice-1042.pdf`).
  final String filename;

  /// Base64-encoded file contents.
  final String base64Content;

  /// Canonical MIME type inferred from the file extension.
  final String contentType;

  /// Decoded size in bytes.
  final int sizeBytes;

  const VinMailAttachment._({
    required this.filename,
    required this.base64Content,
    required this.contentType,
    required this.sizeBytes,
  });

  /// Creates a validated attachment from raw bytes.
  factory VinMailAttachment.fromBytes({
    required String filename,
    required Uint8List bytes,
  }) {
    final cleanName = filename.trim();
    final mime = _validateFilenameAndGetMime(cleanName);
    _validateSingleSize(cleanName, bytes.length);

    return VinMailAttachment._(
      filename: cleanName,
      base64Content: base64Encode(bytes),
      contentType: mime,
      sizeBytes: bytes.length,
    );
  }

  /// Creates a validated attachment from a base64 string (or data URL).
  factory VinMailAttachment.fromBase64({
    required String filename,
    required String contentBase64,
  }) {
    final cleanName = filename.trim();
    final mime = _validateFilenameAndGetMime(cleanName);

    final trimmed = contentBase64.trim();
    final match = _dataUrlRe.firstMatch(trimmed);
    final cleanB64 =
        (match != null ? match.group(1)! : trimmed).replaceAll(RegExp(r'\s+'), '');

    Uint8List decoded;
    try {
      decoded = base64Decode(cleanB64);
    } catch (_) {
      throw VinMailException(
        'Attachment "$cleanName" contains invalid base64 content.',
        status: 422,
        code: 'validation_error',
      );
    }

    _validateSingleSize(cleanName, decoded.length);

    return VinMailAttachment._(
      filename: cleanName,
      base64Content: cleanB64,
      contentType: mime,
      sizeBytes: decoded.length,
    );
  }

  /// Serializes this attachment to the JSON payload expected by `POST /v1/emails`.
  Map<String, String> toJson() => {
        'filename': filename,
        'content': base64Content,
        'contentType': contentType,
      };
}

/// Validates a batch of attachments against VinMail's count and combined size limits.
List<Map<String, String>> prepareAttachments(List<VinMailAttachment> attachments) {
  if (attachments.isEmpty) return const [];
  if (attachments.length > maxAttachmentsPerEmail) {
    throw const VinMailException(
      'At most $maxAttachmentsPerEmail file attachments are allowed per email.',
      status: 422,
      code: 'validation_error',
    );
  }

  var totalBytes = 0;
  final out = <Map<String, String>>[];
  for (final att in attachments) {
    totalBytes += att.sizeBytes;
    if (totalBytes > maxTotalAttachmentsBytes) {
      throw const VinMailException(
        'Combined attachment size exceeds the 7.5 MB limit per email.',
        status: 422,
        code: 'validation_error',
      );
    }
    out.add(att.toJson());
  }
  return out;
}

String _validateFilenameAndGetMime(String filename) {
  if (filename.isEmpty) {
    throw const VinMailException(
      'Each attachment must include a valid filename.',
      status: 422,
      code: 'validation_error',
    );
  }

  final dot = filename.lastIndexOf('.');
  final ext = (dot > 0 && dot < filename.length - 1)
      ? filename.substring(dot).toLowerCase()
      : '';
  final mime = allowedBusinessExtensions[ext];
  if (mime == null) {
    throw VinMailException(
      'File "$filename" is not permitted. Only business documents and images are allowed '
      '(PDF, DOC/DOCX, XLS/XLSX, CSV, PPT/PPTX, TXT, RTF, PNG, JPG, WEBP, GIF).',
      status: 422,
      code: 'validation_error',
    );
  }
  return mime;
}

void _validateSingleSize(String filename, int sizeBytes) {
  if (sizeBytes <= 0) {
    throw VinMailException(
      'Attachment "$filename" cannot be empty.',
      status: 422,
      code: 'validation_error',
    );
  }
  if (sizeBytes > maxSingleAttachmentBytes) {
    throw VinMailException(
      'Attachment "$filename" exceeds the 5 MB per-file size limit.',
      status: 422,
      code: 'validation_error',
    );
  }
}
