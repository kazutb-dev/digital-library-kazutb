const BASE = '/api/v1';

export async function api(path, options = {}) {
  const url = `${BASE}${path}`;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
    ...(options.headers || {}),
  };

  const res = await fetch(url, { ...options, headers });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw Object.assign(new Error(body?.message || `HTTP ${res.status}`), {
      status: res.status,
      body,
    });
  }
  return res.json();
}
