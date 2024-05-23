export function inferColumnNames(
  data: Record<string, Record<string, string | number | null>[]>
): string[] {
  const columns = new Set<string>();
  for (const slice in data) {
    if (data[slice].length === 0) continue;

    for (const key in data[slice][0]) {
      columns.add(key);
    }
  }
  return Array.from(columns);
}
