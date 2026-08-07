const apiBaseUrl = import.meta.env.VITE_API_BASE_URL;
const apiOrigin = new URL(apiBaseUrl).origin;

export class ApiError extends Error {
  constructor(message, { status, errors } = {}) {
    super(message);
    this.status = status;
    this.errors = errors ?? {};
  }
}

function readCookie(name) {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

async function ensureCsrfCookie() {
  if (readCookie("XSRF-TOKEN")) {
    return;
  }
  await fetch(`${apiOrigin}/sanctum/csrf-cookie`, { credentials: "include" });
}

export async function apiFetch(path, options = {}) {
  const method = (options.method ?? "GET").toUpperCase();
  const headers = {
    "Content-Type": "application/json",
    ...options.headers,
  };

  if (method !== "GET" && method !== "HEAD") {
    await ensureCsrfCookie();
    const xsrfToken = readCookie("XSRF-TOKEN");
    if (xsrfToken) {
      headers["X-XSRF-TOKEN"] = xsrfToken;
    }
  }

  const response = await fetch(`${apiBaseUrl}${path}`, {
    credentials: "include",
    ...options,
    headers,
  });

  if (response.status === 204) {
    return null;
  }

  const body = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(body?.message ?? "Request failed", {
      status: response.status,
      errors: body?.errors,
    });
  }

  return body;
}
