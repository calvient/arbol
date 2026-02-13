/**
 * Detects and extracts truncation metadata from chart data.
 *
 * The backend appends a `{ _meta: 'truncated', _total, _shown }` marker
 * to chart data when the number of groups exceeds `arbol.max_chart_groups`.
 */

interface TruncationMeta {
  isTruncated: boolean;
  total: number;
  shown: number;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function extractTruncationMeta(data: Array<Record<string, any>>): {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  chartData: Array<Record<string, any>>;
  meta: TruncationMeta;
} {
  if (!data || data.length === 0) {
    return {chartData: data, meta: {isTruncated: false, total: 0, shown: 0}};
  }

  const lastItem = data[data.length - 1];

  if (lastItem && lastItem._meta === 'truncated') {
    return {
      chartData: data.slice(0, -1),
      meta: {
        isTruncated: true,
        total: lastItem._total ?? data.length - 1,
        shown: lastItem._shown ?? data.length - 1,
      },
    };
  }

  return {chartData: data, meta: {isTruncated: false, total: data.length, shown: data.length}};
}
