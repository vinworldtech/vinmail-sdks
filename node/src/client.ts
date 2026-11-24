import { prepareAttachments } from './attachments.js';
import { VinMailError } from './errors.js';
import type {
  EmailDetails,
  SendEmailOptions,
  SendEmailParams,
  SendEmailResponse,
  VinMailOptions,
} from './types.js';

export const DEFAULT_BASE_URL = 'https://vinmail-api.vinworlddev.com';
export const SDK_VERSION = '0.1.0';

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function parseRetryAfter(header: string | null): number | undefined {
  if (!header) return undefined;
  const seconds = Number(header);
  if (Number.isFinite(seconds) && seconds >= 0) return seconds;
  const dateMs = Date.parse(header);
  if (!Number.isNaN(dateMs)) {
    return Math.max(0, Math.ceil((dateMs - Date.now()) / 1000));
  }
  return undefined;
}

class HttpTransport {
  private readonly apiKey: string;
  private readonly baseUrl: string;
  private readonly timeoutMs: number;
  private readonly maxRetries: number;
  private readonly fetchImpl: typeof globalThis.fetch;

  constructor(apiKey: string, options: VinMailOptions = {}) {
    const trimmed = apiKey.trim();
    if (!trimmed) {
      throw new VinMailError('A valid VinMail API key (vm_live_...) is required.', 401, 'missing_api_key');
    }
    this.apiKey = trimmed;
    this.baseUrl = (options.baseUrl ?? DEFAULT_BASE_URL).replace(/\/+$/, '');
    this.timeoutMs = options.timeoutMs ?? 15_000;
    this.maxRetries = Math.max(0, options.maxRetries ?? 2);
    this.fetchImpl = options.fetch ?? globalThis.fetch;

    if (typeof this.fetchImpl !== 'function') {
      throw new VinMailError(
        'No fetch implementation found. Use Node.js 18+ or pass options.fetch.',
        0,
        'missing_fetch',
      );
    }
  }

  async request<T>(
    method: 'GET' | 'POST',
    path: string,
    body?: unknown,
    extraHeaders?: Record<string, string>,
  ): Promise<T> {
    const url = `${this.baseUrl}${path}`;
    const headers: Record<string, string> = {
      Authorization: `Bearer ${this.apiKey}`,
      Accept: 'application/json',
      'User-Agent': `vinmail-node/${SDK_VERSION}`,
      ...extraHeaders,
    };

    let serializedBody: string | undefined;
    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      serializedBody = JSON.stringify(body);
    }

    let attempt = 0;
    while (true) {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), this.timeoutMs);

      try {
        const response = await this.fetchImpl(url, {
          method,
          headers,
          body: serializedBody,
          signal: controller.signal,
        });
        clearTimeout(timer);

        const text = await response.text();
        let parsed: unknown = undefined;
        if (text) {
          try {
            parsed = JSON.parse(text);
          } catch {
            parsed = undefined;
          }
        }

        if (response.ok) {
          return parsed as T;
        }

        const retryAfter = parseRetryAfter(response.headers.get('retry-after'));
        const shouldRetry =
          attempt < this.maxRetries && (response.status === 429 || response.status >= 500);

        if (shouldRetry) {
          const delayMs =
            retryAfter !== undefined
              ? Math.min(retryAfter * 1000, 10_000)
              : Math.min(300 * 2 ** attempt, 5_000);
          attempt += 1;
          await sleep(delayMs);
          continue;
        }

        const errEnvelope = parsed as { error?: { code?: string; message?: string } } | undefined;
        const code = errEnvelope?.error?.code ?? `http_${response.status}`;
        const message =
          errEnvelope?.error?.message ??
          (text ? text.slice(0, 300) : `VinMail request failed with HTTP ${response.status}.`);
        throw new VinMailError(message, response.status, code, retryAfter);
      } catch (err) {
        clearTimeout(timer);
        if (err instanceof VinMailError) {
          throw err;
        }

        if (attempt < this.maxRetries) {
          attempt += 1;
          await sleep(Math.min(300 * 2 ** attempt, 5_000));
          continue;
        }

        const isTimeout = err instanceof Error && err.name === 'AbortError';
        throw new VinMailError(
          isTimeout
            ? `VinMail request timed out after ${this.timeoutMs} ms.`
            : `Network error contacting VinMail: ${err instanceof Error ? err.message : String(err)}`,
          0,
          isTimeout ? 'timeout' : 'network_error',
        );
      }
    }
  }
}

export class EmailsResource {
  constructor(private readonly transport: HttpTransport) {}

  /**
   * Queues a transactional email for immediate delivery (`POST /v1/emails`).
   */
  async send(params: SendEmailParams, options?: SendEmailOptions): Promise<SendEmailResponse> {
    const idempotencyKey = (options?.idempotencyKey ?? params.idempotencyKey)?.trim();
    const attachments =
      params.attachments && params.attachments.length > 0
        ? await prepareAttachments(params.attachments)
        : undefined;

    const payload: Record<string, unknown> = {
      to: params.to,
    };
    if (params.from !== undefined) payload.from = params.from;
    if (params.replyTo !== undefined) payload.replyTo = params.replyTo;
    if (params.subject !== undefined) payload.subject = params.subject;
    if (params.html !== undefined) payload.html = params.html;
    if (params.text !== undefined) payload.text = params.text;
    if (params.template !== undefined) payload.template = params.template;
    if (params.variables !== undefined) payload.variables = params.variables;
    if (params.tags !== undefined) payload.tags = params.tags;
    if (attachments !== undefined) payload.attachments = attachments;

    const headers: Record<string, string> = {};
    if (idempotencyKey) {
      headers['Idempotency-Key'] = idempotencyKey;
    }

    return this.transport.request<SendEmailResponse>('POST', '/v1/emails', payload, headers);
  }

  /**
   * Fetches delivery status and event timeline for an email (`GET /v1/emails/:id`).
   */
  async get(id: string): Promise<EmailDetails> {
    const cleanId = id.trim();
    if (!cleanId) {
      throw new VinMailError('Email id is required.', 422, 'validation_error');
    }
    return this.transport.request<EmailDetails>(
      'GET',
      `/v1/emails/${encodeURIComponent(cleanId)}`,
    );
  }
}

export class VinMail {
  public readonly emails: EmailsResource;

  constructor(apiKey: string, options: VinMailOptions = {}) {
    const transport = new HttpTransport(apiKey, options);
    this.emails = new EmailsResource(transport);
  }
}
