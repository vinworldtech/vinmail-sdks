export {
  ALLOWED_BUSINESS_EXTENSIONS,
  MAX_ATTACHMENTS_PER_EMAIL,
  MAX_SINGLE_ATTACHMENT_BYTES,
  MAX_TOTAL_ATTACHMENTS_BYTES,
  prepareAttachments,
} from './attachments.js';
export type { EncodedAttachmentPayload } from './attachments.js';
export { DEFAULT_BASE_URL, EmailsResource, SDK_VERSION, VinMail } from './client.js';
export { VinMailError } from './errors.js';
export type {
  AttachmentContentInput,
  AttachmentInput,
  AttachmentPathInput,
  EmailDetails,
  EmailEvent,
  EmailStatus,
  SendEmailOptions,
  SendEmailParams,
  SendEmailResponse,
  VinMailOptions,
} from './types.js';
