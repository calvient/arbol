// Written by ChatGPT 😁
export function toQueryString(obj: Record<string, unknown>): string {
  const buildQuery = (key: string, value: unknown): string => {
    if (value === null || value === undefined) {
      return '';
    }

    if (Array.isArray(value)) {
      return value
        .map((v, i) =>
          typeof v === 'object' && v !== null
            ? Object.keys(v as Record<string, unknown>)
                .map((subKey) =>
                  buildQuery(`${key}[${i}][${subKey}]`, (v as Record<string, unknown>)[subKey]),
                )
                .filter(Boolean)
                .join('&')
            : v === null || v === undefined
              ? ''
              : `${encodeURIComponent(key)}[]=${encodeURIComponent(String(v))}`,
        )
        .filter(Boolean)
        .join('&');
    }
    if (typeof value === 'object' && value !== null) {
      return Object.keys(value as Record<string, unknown>)
        .map((subKey) =>
          buildQuery(`${key}[${subKey}]`, (value as Record<string, unknown>)[subKey]),
        )
        .filter(Boolean)
        .join('&');
    }
    return `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`;
  };

  return Object.keys(obj)
    .map((key) => buildQuery(key, obj[key]))
    .filter(Boolean)
    .join('&');
}
