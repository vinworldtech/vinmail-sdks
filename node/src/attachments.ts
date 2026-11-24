import { VinMailError } from './errors.js';
import type { AttachmentInput } from './types.js';

export const MAX_ATTACHMENTS_PER_EMAIL = 5;
export const MAX_SINGLE_ATTACHMENT_BYTES = 5 * 1024 * 1024;
export const MAX_TOTAL_ATTACHMENTS_BYTES = Math.floor(7.5 * 1024 * 1024);

export const ALLOWED_BUSINESS_EXTENSIONS: Readonly<Record<string, string>> = {
  '.pdf': 'application/pdf',
  '.doc': 'application/msword',
  '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  '.xls': 'application/vnd.ms-excel',
  '.xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  '.csv': 'text/csv',
  '.ppt': 'application/vnd.ms-powerpoint',
  '.pptx': 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  '.txt': 'text/plain',
  '.rtf': 'application/rtf',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
};

export interface EncodedAttachmentPayload {
  filename: string;
  content: string;
  contentType: string;
}

function getExtension(filename: string): string {
  const idx = filename.lastIndexOf('.');
  if (idx <= 0 || idx === filename.length - 1) return '';
  return filename.slice(idx).toLowerCase();
}

function basenameOf(filePath: string): string {
  const cleaned = filePath.replace(/\\/g, '/');
  const parts = cleaned.split('/');
  return parts[parts.length - 1] ?? 'attachment';
}

function uint8ArrayToBase64(bytes: Uint8Array): string {
  if (typeof Buffer !== 'undefined') {
    return Buffer.from(bytes.buffer, bytes.byteOffset, bytes.byteLength).toString('base64');
  }
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i += 1) {
    binary += String.fromCharCode(bytes[i]!);
  }
  return btoa(binary);
}

function decodedByteLengthFromBase64(rawBase64: string): { clean: string; byteLength: number } {
  const trimmed = rawBase64.trim();
  const dataUrlMatch = /^data:[^;]+;base64,(.+)$/is.exec(trimmed);
  const clean = (dataUrlMatch ? dataUrlMatch[1]! : trimmed).replace(/\s+/g, '');

  if (!/^[A-Za-z0-9+/]*={0,2}$/.test(clean) || clean.length % 4 !== 0) {
    throw new VinMailError('Attachment contains invalid base64 content.', 422, 'validation_error');
  }
  const padding = clean.endsWith('==') ? 2 : clean.endsWith('=') ? 1 : 0;
  return { clean, byteLength: (clean.length / 4) * 3 - padding };
}

/**
 * Resolves `path` / `Uint8Array` / base64 attachments into API payloads and
 * enforces VinMail's strict business-only allowlist and size caps client-side.
 */
export async function prepareAttachments(
  inputs: AttachmentInput[],
): Promise<EncodedAttachmentPayload[]> {
  if (inputs.length === 0) return [];
  if (inputs.length > MAX_ATTACHMENTS_PER_EMAIL) {
    throw new VinMailError(
      `At most ${MAX_ATTACHMENTS_PER_EMAIL} file attachments are allowed per email.`,
      422,
      'validation_error',
    );
  }

  let totalBytes = 0;
  const out: EncodedAttachmentPayload[] = [];

  for (const item of inputs) {
    let filename: string;
    let base64Content: string;
    let sizeBytes: number;

    if ('path' in item) {
      filename = (item.filename ?? basenameOf(item.path)).trim();
      const fs = await import('node:fs/promises');
      const fileBytes = await fs.readFile(item.path);
      sizeBytes = fileBytes.byteLength;
      base64Content = fileBytes.toString('base64');
    } else {
      filename = item.filename.trim();
      if (typeof item.content === 'string') {
        const parsed = decodedByteLengthFromBase64(item.content);
        base64Content = parsed.clean;
        sizeBytes = parsed.byteLength;
      } else {
        sizeBytes = item.content.byteLength;
        base64Content = uint8ArrayToBase64(item.content);
      }
    }

    if (!filename) {
      throw new VinMailError('Each attachment must include a valid filename.', 422, 'validation_error');
    }

    const ext = getExtension(filename);
    const canonicalMime = ALLOWED_BUSINESS_EXTENSIONS[ext];
    if (!canonicalMime) {
      throw new VinMailError(
        `File "${filename}" is not permitted. Only business documents and images are allowed (PDF, DOC/DOCX, XLS/XLSX, CSV, PPT/PPTX, TXT, RTF, PNG, JPG, WEBP, GIF).`,
        422,
        'validation_error',
      );
    }

    if (sizeBytes <= 0) {
      throw new VinMailError(`Attachment "${filename}" cannot be empty.`, 422, 'validation_error');
    }
    if (sizeBytes > MAX_SINGLE_ATTACHMENT_BYTES) {
      throw new VinMailError(
        `Attachment "${filename}" exceeds the 5 MB per-file size limit.`,
        422,
        'validation_error',
      );
    }

    totalBytes += sizeBytes;
    if (totalBytes > MAX_TOTAL_ATTACHMENTS_BYTES) {
      throw new VinMailError(
        'Combined attachment size exceeds the 7.5 MB limit per email.',
        422,
        'validation_error',
      );
    }

    out.push({
      filename,
      content: base64Content,
      contentType: canonicalMime,
    });
  }

  return out;
}
