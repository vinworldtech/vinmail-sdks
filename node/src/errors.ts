export class VinMailError extends Error {
  /** HTTP status code (0 for local validation or network errors). */
  public readonly status: number;
  /** Machine-readable VinMail error code (e.g. "invalid_api_key", "domain_not_verified"). */
  public readonly code: string;
  /** Optional Retry-After hint in seconds (present on 429 responses). */
  public readonly retryAfter?: number;

  constructor(message: string, status: number, code: string, retryAfter?: number) {
    super(message);
    this.name = 'VinMailError';
    this.status = status;
    this.code = code;
    this.retryAfter = retryAfter;
    Object.setPrototypeOf(this, new.target.prototype);
  }
}
