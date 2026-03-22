# Stateless Section View

A standalone route that renders a single Arbol table section entirely from URL parameters — no saved report or section needed.

---

## Table of Contents

1. [Overview](#overview)
2. [URL Structure](#url-structure)
3. [Parameters](#parameters)
4. [How It Works](#how-it-works)
5. [Example Usage](#example-usage)
6. [Backend Architecture](#backend-architecture)
7. [Frontend Architecture](#frontend-architecture)
8. [Caching](#caching)
9. [File Manifest](#file-manifest)
10. [Differences from Report Sections](#differences-from-report-sections)

---

## Overview

The stateless section view provides a way to render an Arbol series as a table at a URL without creating a report or section in the database. Everything is driven by query parameters:

- **Which series** to display
- **Which filters** to expose in the filter bar
- **Which default values** to pre-select

This is useful for:
- Embedding data views in navigation menus or dashboards via `<iframe>` or direct links
- Linking to a specific data view from external tools (Slack, email, etc.)
- Ad-hoc data exploration without configuring a full report

---

## URL Structure

```
/arbol/section?series={name}&filters={Group}:{Default},{Group}
```

The route is: `GET /arbol/section`

Named route: `arbol.section.show`

---

## Parameters

| Parameter | Required | Description |
|---|---|---|
| `series` | Yes | The name of the Series class (matches `IArbolSeries::name()`) |
| `filters` | No | Comma-separated list of filter entries (see syntax below) |

### Filter Syntax

The `filters` parameter is a comma-separated string where each entry is either:

- **`Group:Value`** — Show the filter group in the bar with `Value` pre-selected as default
- **`Group`** — Show the filter group in the bar with no default selected
- **`Group:Value1,Group:Value2`** — Repeat a group to pre-select multiple values (multi-select)

The split happens on the **first colon only**, so values containing colons are safe.

Only filter groups that actually exist on the Series are shown (invalid group names are silently ignored). The filter bar allows users to toggle values interactively, then click Refresh.

### URL Sync

The URL is kept in sync with the current filter state via `window.history.replaceState()`. When the user changes filters in the filter bar, the `filters` query parameter is rebuilt to reflect the current selections. This means:

- The URL is always shareable and bookmarkable
- Refreshing the page restores the current filter state
- Browser history is not polluted (no new entries per filter change)

---

## How It Works

1. User navigates to `/arbol/section?series=My+Series&filters=Status:Active`
2. The backend resolves the "My Series" series class and its metadata
3. The backend parses the `filters` string into filter bar configuration
4. An Inertia page renders with a filter bar and table
5. The frontend fetches data from `GET /api/arbol/section-data` (a stateless API endpoint)
6. Data is cached using a config hash (no database section needed)
7. The table renders with sorting, pagination, and search
8. When the user changes filters, the URL is updated via `replaceState` to stay in sync
9. A Download button exports the current view as CSV via `GET /arbol/section-data/download`

---

## Example Usage

### Basic — Display a series with no filters

```
/arbol/section?series=Podcast+Streams
```

Renders the full "Podcast Streams" dataset as a table. No filter bar is shown.

### With filter bar — Expose filter groups with no defaults

```
/arbol/section?series=Podcast+Streams&filters=Time+Period,Source
```

Renders the table with a filter bar showing "Time Period" and "Source" dropdowns. No filters are pre-selected — the user picks values and clicks Refresh.

### With defaults — Pre-select filter values

```
/arbol/section?series=Podcast+Streams&filters=Time+Period:Last+30+Days,Source
```

Renders the table with "Time Period" defaulting to "Last 30 Days" and "Source" with no default. Data loads immediately with the "Last 30 Days" filter applied.

### Multiple defaults

```
/arbol/section?series=Podcast+Streams&filters=Time+Period:Last+30+Days,Source:Spotify
```

### Multi-select — Pre-select multiple values for one group

```
/arbol/section?series=Podcast+Streams&filters=Source:Spotify,Source:Apple,Time+Period
```

Renders the table with "Source" defaulting to both "Spotify" and "Apple" selected, and "Time Period" with no default. Repeat the group name to select multiple values.

### Linking from PHP (named route)

```php
route('arbol.section.show', [
    'series' => 'Podcast Streams',
    'filters' => 'Time Period:Last 30 Days,Source',
]);
```

### Linking from Blade

```blade
<a href="{{ route('arbol.section.show', [
    'series' => 'Podcast Streams',
    'filters' => 'Status:Active',
]) }}">
    View Active Streams
</a>
```

### Building the URL manually in JavaScript

```js
const url = `/arbol/section?series=Podcast+Streams&filters=Time+Period:Last+30+Days,Source`;
window.location.href = url;
```

---

## Backend Architecture

### Web Routes

**File:** `routes/web.php`

```
GET /arbol/section                → SectionViewController@show
GET /arbol/section-data/download  → StatelessSectionController@download
```

Both sit inside the same middleware group as all Arbol routes (`auth`, `web`, `HandleInertiaRequests`).

### API Route

**File:** `routes/api.php`

```
GET /api/arbol/section-data → StatelessSectionController@getData
```

A dedicated endpoint that works without a `section_id`. It accepts `series`, `filters`, and `format` (currently only `table`), and uses a config hash for caching instead of a database section ID.

### Controller: `SectionViewController`

**File:** `src/Http/Controllers/SectionViewController.php`

Web controller. Reads URL params, resolves the series by name, builds `allFilters` and `defaultFilters` from the requested filter groups, and renders the `Section/Show` Inertia page.

### Controller: `StatelessSectionController`

**File:** `src/Http/Controllers/API/StatelessSectionController.php`

API controller with two public methods:

- **`getData()`** — Handles data fetching:
  1. Validates `series` and `format` (required), `filters` (optional)
  2. Computes a `configHash` from series + format for cache keying
  3. Checks the hash-based cache
  4. If miss, dispatches `LoadStatelessSectionData` with the `configHash`
  5. Returns 200 with data or 202 with estimated time

- **`download()`** — Handles CSV export:
  1. Validates `series`, `format`, optional `filters` and `slice_key`
  2. Reads cached data by config hash
  3. Filters to `slice_key` if provided
  4. Streams a CSV download with UTF-8 BOM for Excel compatibility

### Job: `LoadStatelessSectionData`

**File:** `src/Jobs/LoadStatelessSectionData.php`

A self-contained job for loading stateless section data. Uses hash-based cache methods on `ArbolService`. Does not touch the existing `LoadSectionData` job.

### Service: `ArbolService` (extended)

**File:** `src/Services/ArbolService.php`

New hash-based cache methods mirror the section-based ones:
- `storeDataInCacheByHash()` / `getDataFromCacheByHash()`
- `setIsRunningByHash()` / `getIsRunningByHash()`
- `setLastRunDurationByHash()` / `getLastRunDurationByHash()`
- `clearCacheByHash()`
- `computeConfigHash()` — static method to create a stable hash from series + format

Cache keys use the pattern: `arbol:stateless:{configHash}` (with optional `:fh:{filterHash}` suffix).

---

## Frontend Architecture

### Page: `Section/Show.tsx`

**File:** `resources/ts/Pages/Section/Show.tsx`

A self-contained Inertia page that:

1. Receives `series`, `allFilters`, `defaultFilters` as props
2. Manages local state: `reportFilters`, `searchQuery`, `refreshKey`, `data`, loading state
3. Syncs filter state to the URL via `replaceState` on every change (shareable/bookmarkable)
4. Renders `ReportFilterBar` (if filters are configured)
5. Fetches data from `GET /api/arbol/section-data`
6. Polls on 202 responses (same 10-second interval as report sections)
7. Renders `TableFormat` with the fetched data
8. Provides a Download button that opens `GET /arbol/section-data/download` in a new tab

### Layout: `MinimalLayout`

**File:** `resources/ts/Components/MinimalLayout.tsx`

A lightweight layout that provides ChakraProvider and QueryClientProvider without the breadcrumb navigation bar or version footer. Used exclusively by the stateless section view.

### Reused Components

- **`ReportFilterBar`** (`resources/ts/Components/ReportFilterBar.tsx`) — filter pills, search input, refresh button
- **`TableFormat`** (`resources/ts/Pages/Reports/Sections/Components/Formats/TableFormat.tsx`) — TanStack Table with sorting, pagination, client-side search

---

## Caching

Stateless sections use the same 14-day cache TTL as regular sections. Cache keys:

```
arbol:stateless:{configHash}                    # Raw data, no filters
arbol:stateless:{configHash}:fh:{filterHash}   # Raw data, with specific filters
arbol:stateless:{configHash}:is_running         # Job semaphore
arbol:stateless:{configHash}:last_run_duration  # Last run time
```

- `configHash` = `md5(json_encode(['series' => name, 'format' => 'table']))`
- `filterHash` = `md5` of the sorted filter array (same as regular sections)

The `LoadStatelessSectionData` job uses `ShouldBeUnique` with `uniqueId()` returning `stateless_{configHash}_{timestamp}_{filterHash}` to prevent duplicate jobs.

---

## File Manifest

All files created or modified for the stateless section feature.

### New files

| File | Description |
|---|---|
| `src/Http/Controllers/SectionViewController.php` | Web controller — parses URL params, renders Inertia page |
| `src/Http/Controllers/API/StatelessSectionController.php` | API controller — data fetching + CSV download |
| `src/Jobs/LoadStatelessSectionData.php` | Queued job — loads series data using hash-based cache |
| `resources/ts/Pages/Section/Show.tsx` | Inertia page — filter bar, table, download button |
| `resources/ts/Components/MinimalLayout.tsx` | Layout without breadcrumb nav or version footer |
| `resources/docs/stateless-section.md` | This documentation file |

### Modified files

| File | Change |
|---|---|
| `src/Services/ArbolService.php` | Added hash-based cache methods (`*ByHash`) and `computeConfigHash()` — pure additions, no existing methods changed |
| `routes/web.php` | Added `GET /arbol/section` and `GET /arbol/section-data/download` routes, plus imports |
| `routes/api.php` | Added `GET /api/arbol/section-data` route, plus import |
| `resources/ts/Components/ReportFilterBar.tsx` | Popover now uses render-prop pattern so the Clear button closes the popover |

---

## Differences from Report Sections

| Aspect | Report Section | Stateless Section |
|---|---|---|
| Database record | Required (`arbol_sections` row) | None |
| Part of a report | Yes | No |
| Edit/delete controls | Yes | No |
| Download CSV | Yes (Download View + Download All) | Yes (Download only) |
| Format support | Table, line, bar, pie | Table only (for now) |
| Filter bar | Configured via section editor | Configured via URL params |
| Layout | Full layout with breadcrumb + version | Minimal layout (content only) |
| Cache key | `arbol:section:{id}` | `arbol:stateless:{configHash}` |
| API endpoint | `GET /api/arbol/series-data` | `GET /api/arbol/section-data` |
| Download endpoint | `GET /arbol/series-data/download` | `GET /arbol/section-data/download` |
