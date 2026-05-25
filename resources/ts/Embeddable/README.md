# Using Arbol table components in your app

You can reuse the data table in different views across your app. Two options:

## 1. **DataTableFromUrl** — just pass the URL

Use when you want a table that fetches its own data from a URL (e.g. the stateless section endpoint). You supply the data URL and optionally the Export to CSV URL.

**Requirements:** Your app must use React and the same UI stack as Arbol (`@calvient/decal` or Chakra UI). The API must return JSON in the shape `Record<string, Row[]>` (e.g. `{ "All": [ { "col1": "a", "col2": 1 }, ... ] }`). The stateless endpoint `GET /api/arbol/section-data` returns this shape.

### Setup (e.g. Vite)

Alias the embeddable module so you can import cleanly:

```ts
// vite.config.ts
resolve: {
  alias: {
    'calvient-arbol/embed': path.resolve(__dirname, 'vendor/calvient/arbol/resources/ts/Embeddable'),
  },
},
```

### Example

```tsx
import { DataTableFromUrl } from 'calvient-arbol/embed';

// Stateless section: series + format (and optional filters)
const dataUrl = `/api/arbol/section-data?${new URLSearchParams({
  series: 'My Series',
  format: 'table',
  // filters: build from your state, e.g. filters[0][field]=X&filters[0][value]=Y
})}`;
const exportCsvUrl = `/arbol/section-data/download?${new URLSearchParams({
  series: 'My Series',
  format: 'table',
  slice_key: 'All',
})}`;

function MyPage() {
  return (
    <DataTableFromUrl
      dataUrl={dataUrl}
      exportCsvUrl={exportCsvUrl}
    />
  );
}
```

With filters from your own state, build the query string (e.g. with `URLSearchParams` or a small helper for array params):

```tsx
const filters = [{ field: 'Status', value: 'Active' }];
const dataUrl = `/api/arbol/section-data?series=My+Series&format=table&${filters
  .map((f, i) => `filters[${i}][field]=${encodeURIComponent(f.field)}&filters[${i}][value]=${encodeURIComponent(f.value)}`)
  .join('&')}`;
```

**Props:**

| Prop | Required | Description |
|------|----------|-------------|
| `dataUrl` | Yes | GET URL that returns `Record<string, DataTableRow[]>` |
| `exportCsvUrl` | No | URL for "Export to CSV" button |
| `onTableStateChange` | No | Callback with current view state (for downstream visualizations) |
| `onAddToReport` | No | Callback when user clicks "Add to Report" |
| `defaultPageSize` | No | Initial rows per page (default 10) |
| `searchQuery` | No | Client-side search string applied to rows |
| `pollIntervalMs` | No | When API returns 202, poll again after this many ms (default 10000) |

---

## 2. **DataTableContainer** — controlled (you pass data)

Use when you already fetch data in your own code and want to render the table with full control (sticky headers, column visibility, sort, pagination, toolbar).

```tsx
import { DataTableContainer } from 'calvient-arbol/embed';

function MyPage() {
  const [data, setData] = useState({ All: [] });
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    fetch('/api/arbol/section-data?series=MySeries&format=table')
      .then((r) => r.json())
      .then(setData)
      .finally(() => setLoading(false));
  }, []);

  return (
    <DataTableContainer
      data={data}
      currentSlice="All"
      onSliceChange={() => {}}
      hideSliceSelector
      isLoading={loading}
      onRefresh={() => { /* refetch */ }}
      exportCsvUrl="..."
    />
  );
}
```

---

## Authentication

Arbol API routes use Laravel `auth` middleware. Ensure the page that embeds the table is behind your auth so the request to `/api/arbol/section-data` (and download URLs) sends credentials (cookies) and the user is authenticated.
