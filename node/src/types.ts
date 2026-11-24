export type EmailStatus =
  | 'queued'
  | 'sending'
  | 'sent'
  | 'delivered'
  | 'bounced'
  | 'complained'
  | 'failed'
  | (string & {});

export interface VinMailOptions {
  /** Override API base URL (defaults to https://vinmail-api.vinworlddev.com). */
  baseUrl?: string;
  /** Request timeout in milliseconds (defaults to 15000). */
  timeoutMs?: number;
  /** Maximum retries on 429 or 5xx responses (defaults to 2). */
  maxRetries?: number;
  /** Custom fetch implementation (defaults to globalThis.fetch). */
  fetch?: typeof globalThis.fetch;
}

export interface AttachmentContentInput {
  /** Filename including extension (e.g. "invoice-1042.pdf"). */
  filename: string;
  /** Raw bytes (Buffer/Uint8Array) or base64-encoded string. */
  content: string | Uint8Array;
  /** Optional MIME hint (VinMail always enforces the canonical MIME for the extension). */
  contentType?: string;
}

export interface AttachmentPathInput {
  /** Path to a local file on disk (Node.js only). */
  path: string;
  /** Optional override for the filename shown to the recipient (defaults to basename of path). */
  filename?: string;
  /** Optional MIME hint. */
  contentType?: string;
}

export type AttachmentInput = AttachmentContentInput | AttachmentPathInput;

export interface SendEmailParams {
  /** Verified sender address, e.g. "Acme <billing@acme.com>". Optional if project has a defaultFrom. */
  from?: string;
  /** One recipient email or an array of up to 50 email addresses (without display names). */
  to: string | string[];
  /** Optional Reply-To address ("email" or "Name <email>"). */
  replyTo?: string;
  /** Single-line subject (required unless using a template that defines one). */
  subject?: string;
  /** HTML body (up to 500 KB). */
  html?: string;
  /** Plain-text body (up to 500 KB). */
  text?: string;
  /** Template name (project template or built-in global template: "otp", "notification", "notification-action"). */
  template?: string;
  /** Template variables (never stored or logged by VinMail). */
  variables?: Record<string, unknown>;
  /** Up to 5 key/value tags attached to delivery events. */
  tags?: Record<string, string>;
  /**
   * Optional business file attachments (max 5 files, 5 MB each, 7.5 MB combined).
   * Allowed formats: PDF, DOC/DOCX, XLS/XLSX, CSV, PPT/PPTX, TXT, RTF, PNG, JPG, JPEG, WEBP, GIF.
   */
  attachments?: AttachmentInput[];
  /** Optional Idempotency-Key header to prevent duplicate sends on retry. */
  idempotencyKey?: string;
}

export interface SendEmailOptions {
  /** Overrides `params.idempotencyKey` if supplied. */
  idempotencyKey?: string;
}

export interface SendEmailResponse {
  id: string;
  status: EmailStatus;
  /** Included only when one or more recipients were automatically dropped due to suppression. */
  suppressed?: string[];
}

export interface EmailEvent {
  type: string;
  recipient: string | null;
  detail: Record<string, unknown> | null;
  createdAt: string;
}

export interface EmailDetails {
  id: string;
  status: EmailStatus;
  to: string[];
  subject: string;
  templateName: string | null;
  recipientCount: number;
  error: string | null;
  createdAt: string;
  sentAt: string | null;
  events: EmailEvent[];
}
