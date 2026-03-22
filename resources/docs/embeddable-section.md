# Embeddable Arbol Section Component

A plan for creating a standalone React component that allows consumers of the Arbol package to embed a single report section anywhere in their application.

---

## Table of Contents

1. [Overview](#overview)
2. [Consumer Experience](#consumer-experience)
3. [Prerequisites for Consumers](#prerequisites-for-consumers)
4. [Architecture](#architecture)
5. [Backend Changes](#backend-changes)
6. [Frontend Changes](#frontend-changes)
7. [Component API](#component-api)
8. [Data Flow](#data-flow)
9. [Implementation Steps](#implementation-steps)
10. [Future Enhancements](#future-enhancements)

---

## Overview

Currently, Arbol sections can only be viewed within the full Arbol report UI (`/arbol/reports/{id}`), which runs inside its own Inertia.js context. Consumers have no way to show a single section—a table, chart, or pie graph—on their own pages.

The goal is to create an `<ArbolEmbed />` React component that consumers can import and drop into any page in their application. The component handles data fetching, polling, loading states, and format rendering (table, line, bar, pie) using the same backend pipeline that powers the full Arbol UI.

Two modes are supported:

1. **Section ID mode** — Reference a saved section by its database ID. The section must already exist in Arbol (created via the report builder UI or programmatically). The component fetches the section config and data automatically.

2. **Inline config mode** — Pass the section configuration directly as props (series name, format, filters, slice, etc.) without needing a saved section in the database. Useful for ad-hoc visualizations.

---

## Consumer Experience

### Section ID Mode

```tsx
import {ArbolEmbed} from 'calvient-arbol/embed';

function Dashboard() {
  return (
    <div>
      <h1>Dashboard</h1>
      <ArbolEmbed sectionId={5} />
    </div>
  );
}
```

### Inline Config Mode

```tsx
import {ArbolEmbed} from 'calvient-arbol/embed';

function Dashboard() {
  return (
    <div>
      <h1>Active Users by Department</h1>
      <ArbolEmbed
        series="User Activity"
        format="bar"
        slice="Department"
        xaxisSlice="Month"
        aggregator="Total Hours"
        filters={[{field: 'Status', value: 'Active'}]}
      />
    </div>
  );
}
```

### With Customization

```tsx
<ArbolEmbed
  sectionId={5}
  showTitle={false}
  showDownload={false}
  showRefresh={true}
  height={400}
  onDataLoaded={(data) => console.log('Section data:', data)}
/>
```

---

## Prerequisites for Consumers

### 1. Backend: Arbol Package Installed

The consumer must have the Arbol Composer package installed and configured:

```bash
composer require calvient/arbol
php artisan migrate
php artisan vendor:publish --tag=arbol-config
```

At least one Series class must exist (e.g., `app/Arbol/MyDataSeries.php`).

### 2. Frontend: npm Peer Dependencies

The embeddable component uses the same UI libraries as the full Arbol UI. Consumers must install these in their own `package.json`:

```bash
npm install @calvient/decal recharts @tanstack/react-table
```

`@calvient/decal` wraps Chakra UI, so the consumer's app must have a `ChakraProvider` (or `DecalProvider`) at the root. If the consumer already uses Chakra UI or `@calvient/decal`, no extra setup is needed.

> **Note:** If the consumer's app does not use Chakra UI, they need to wrap the embedded section (or their app root) in a `ChakraProvider`. See [Component API > ChakraProvider Requirement](#chakaprovider-requirement).

### 3. Authentication

The API endpoints require an authenticated user (Laravel's `auth` middleware). The consumer's page must be behind authentication. For section ID mode, the authenticated user must also have access to the section's parent report (same access control as the full Arbol UI).

### 4. Import Path

The component is importable from within the vendor directory. Consumers should alias the import path in their build tool config for cleanliness:

**vite.config.ts:**
```ts
export default defineConfig({
  resolve: {
    alias: {
      'calvient-arbol/embed': path.resolve(
        __dirname,
        'vendor/calvient/arbol/resources/ts/Embeddable'
      ),
    },
  },
});
```

Then import as:
```tsx
import {ArbolEmbed} from 'calvient-arbol/embed';
```

Without the alias, the direct import path works too:
```tsx
import {ArbolEmbed} from '../../vendor/calvient/arbol/resources/ts/Embeddable';
```

---

## Architecture

### Current Flow (Full Arbol UI)

```
Inertia Route (/arbol/reports/{id})
  -> ReportsController::show() [passes report + sections as Inertia props]
    -> Show.tsx [renders ReportSection for each section]
      -> ReportSection.tsx [fetches data from GET /api/arbol/series-data]
        -> TableFormat / LineFormat / BarFormat / PieFormat
```

Key coupling points in the existing `ReportSection`:
- Requires a `report` prop (for edit/link URLs)
- Uses `@inertiajs/react` `Link` component (for navigation to edit pages)
- Depends on `section.id` existing in the database (for the API `section_id` param)
- Manages `reportFilters` from parent (for filter bar integration)

### New Flow (Embeddable Component)

```
Consumer's React Page (any route)
  -> <ArbolEmbed sectionId={5} />
    -> Fetches section config from GET /api/arbol/embed/sections/{id}
    -> Fetches data from GET /api/arbol/series-data (existing endpoint)
      OR from GET /api/arbol/embed/data (new endpoint for inline config)
    -> Renders TableFormat / LineFormat / BarFormat / PieFormat
```

The embeddable component is a **decoupled wrapper** that:
- Does NOT depend on Inertia.js
- Does NOT depend on report context
- Does NOT render edit/navigation links
- Does NOT participate in the report filter bar
- Reuses the same format components (TableFormat, LineFormat, etc.)
- Reuses the same backend data pipeline (LoadSectionData job, caching, etc.)

---

## Backend Changes

### 1. New Controller: `EmbedController`

**File:** `src/Http/Controllers/API/EmbedController.php`

A lightweight controller dedicated to the embed use case.

#### `GET /api/arbol/embed/sections/{section}` — Get Section Config

Returns the section's configuration (series, format, filters, slice, etc.) as JSON. Used by the component in section ID mode to know what data to fetch.

```php
public function getSection(ArbolSection $section): JsonResponse
{
    // Access control: ensure user can access the section's report
    $report = $section->report;
    if ($this->getUserClientId() && $report->client_id !== $this->getUserClientId()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $access = app(ArbolAccess::class);
    if (!$access->userCanAccessReport(auth()->user(), $report)) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    return response()->json([
        'id' => $section->id,
        'name' => $section->name,
        'description' => $section->description,
        'series' => $section->series,
        'format' => $section->format,
        'slice' => $section->slice,
        'xaxis_slice' => $section->xaxis_slice,
        'aggregator' => $section->aggregator,
        'percentage_mode' => $section->percentage_mode,
        'filters' => $section->filters ?? [],
    ]);
}
```

#### `GET /api/arbol/embed/data` — Get Data for Inline Config

A variant of the existing `SeriesController::getSeriesData()` that works without a `section_id`. Instead of looking up a section from the database, it accepts the full config inline and uses a deterministic hash of the config as the cache key.

```php
public function getData(): JsonResponse
{
    // Validate - same as SeriesController but section_id is optional
    // When section_id is present, delegates to existing behavior
    // When absent, uses config hash for cache key

    $validator = Validator::make(request()->all(), [
        'section_id' => 'nullable|integer',
        'series' => 'required|string',
        'slice' => 'nullable|string',
        'xaxis_slice' => 'nullable|string',
        'aggregator' => 'nullable|string',
        'filters' => 'nullable|array',
        'filters.*.field' => 'required|string',
        'filters.*.value' => 'required|string',
        'format' => 'required|string',
        'percentage_mode' => 'nullable|string|in:xaxis_group,total',
        'force_refresh' => 'nullable|boolean',
    ]);

    // ... cache lookup using config hash when no section_id ...
    // ... dispatch LoadSectionData job ...
}
```

**Cache key for inline config (no section_id):**

When no `section_id` is provided, the cache key is derived from a hash of the full config:

```php
$configHash = md5(json_encode([
    'series' => request('series'),
    'slice' => request('slice'),
    'xaxis_slice' => request('xaxis_slice'),
    'aggregator' => request('aggregator'),
    'format' => request('format'),
    'percentage_mode' => request('percentage_mode'),
    'filters' => request('filters', []),
]));

// Cache key: arbol:embed:{configHash}
// Cache key with filters: arbol:embed:{configHash}:fh:{filterHash}
```

### 2. New Routes

**File:** `routes/api.php` (append to existing)

```php
Route::middleware(['auth', 'api'])->prefix('/api/arbol/embed')->group(function () {
    Route::get('/sections/{section}', [EmbedController::class, 'getSection']);
    Route::get('/data', [EmbedController::class, 'getData']);
});
```

### 3. Changes to LoadSectionData Job

The `LoadSectionData` job currently requires an `ArbolSection` model instance. For inline config mode, we need it to also accept a nullable section with a config hash fallback:

```php
public function __construct(
    public ?ArbolSection $arbolSection,   // nullable for inline config
    public string $series,
    public array $filters,
    public ?string $slice,
    public $user,
    public string $format,
    public string $aggregator = 'Default',
    public ?string $chartSlice = null,
    public ?string $percentageMode = null,
    public ?string $filterHash = null,
    public ?string $configHash = null,    // new: used when arbolSection is null
) {}
```

The `uniqueId()` and cache key methods would use `$configHash` when `$arbolSection` is null:

```php
public function uniqueId(): string
{
    $id = $this->arbolSection
        ? $this->arbolSection->id
        : 'embed_' . $this->configHash;

    return $id . '_' . floor(now()->timestamp / 300) . '_' . ($this->filterHash ?? 'no_filter');
}
```

### 4. Changes to ArbolService

Add methods that work with config hashes for cache operations:

```php
public function getDataFromCacheByHash(string $configHash, ?string $filterHash = null): ?array
{
    $key = "arbol:embed:{$configHash}";
    if ($filterHash) {
        $key .= ":fh:{$filterHash}";
    }
    $data = Cache::get($key);
    return $data ? json_decode($data, true) : null;
}

public function storeDataInCacheByHash(string $configHash, array $data, ?string $filterHash = null): void
{
    $key = "arbol:embed:{$configHash}";
    if ($filterHash) {
        $key .= ":fh:{$filterHash}";
    }
    Cache::put($key, json_encode($data), now()->addDays(14));
}

// Similar methods for formatted cache, is_running, last_run_duration
```

---

## Frontend Changes

### 1. New Component: `ArbolEmbed`

**File:** `resources/ts/Embeddable/ArbolEmbed.tsx`

The main embeddable component. It orchestrates config resolution, data fetching, polling, and rendering.

```tsx
import React, {useEffect, useState, useCallback} from 'react';
import {Box, Heading, Text, Button, HStack, Spacer, Spinner} from '@calvient/decal';
import {toQueryString} from '../Utils/toQueryString';
import TableFormat from '../Pages/Reports/Sections/Components/Formats/TableFormat';
import LineFormat from '../Pages/Reports/Sections/Components/Formats/LineFormat';
import BarFormat from '../Pages/Reports/Sections/Components/Formats/BarFormat';
import PieFormat from '../Pages/Reports/Sections/Components/Formats/PieFormat';

interface ArbolEmbedProps {
  // Section ID mode
  sectionId?: number;

  // Inline config mode
  series?: string;
  format?: 'table' | 'line' | 'bar' | 'pie';
  filters?: Array<{field: string; value: string}>;
  slice?: string;
  xaxisSlice?: string;
  aggregator?: string;
  percentageMode?: 'xaxis_group' | 'total' | null;

  // Display options
  showTitle?: boolean;       // default: true (only in sectionId mode)
  showDescription?: boolean; // default: true
  showRefresh?: boolean;     // default: true
  showDownload?: boolean;    // default: true
  height?: number | string;  // optional fixed height

  // Callbacks
  onDataLoaded?: (data: any) => void;
  onError?: (error: string) => void;
}
```

### 2. New Index File: `Embeddable/index.ts`

**File:** `resources/ts/Embeddable/index.ts`

Barrel export for clean imports:

```ts
export {default as ArbolEmbed} from './ArbolEmbed';
export type {ArbolEmbedProps} from './ArbolEmbed';
```

### 3. Internal Component Structure

The `ArbolEmbed` component internally manages two phases:

**Phase 1: Config Resolution**
- If `sectionId` is provided, fetch config from `GET /api/arbol/embed/sections/{id}`
- If inline props are provided, construct the config object from props
- Validate that required fields are present (series + format at minimum)

**Phase 2: Data Fetching + Rendering**
- Same pattern as `ReportSection.tsx`: fetch data, poll on 202, render on 200
- Uses `GET /api/arbol/embed/data` instead of `/api/arbol/series-data`
- No dependency on `report` prop or Inertia.js

### 4. Key Differences from `ReportSection`

| Aspect | ReportSection | ArbolEmbed |
|---|---|---|
| Requires `report` prop | Yes | No |
| Edit section link | Yes | No |
| Inertia.js `Link` usage | Yes | No |
| Report filter bar integration | Yes | No |
| `onLoadingChange` callback | Yes | No (uses `onDataLoaded` instead) |
| Supports inline config | No | Yes |
| Download buttons | Always shown | Configurable via `showDownload` |

### 5. Format Component Reuse

The format components (`TableFormat`, `LineFormat`, `BarFormat`, `PieFormat`) are already well-isolated and accept data via props. They can be imported directly by `ArbolEmbed` without modification.

One small consideration: `TableFormat` accepts `currentSlice` and `onSliceChange` props for slice navigation. In the embedded context, `ArbolEmbed` manages this state internally (same as `ReportSection` does today).

---

## Component API

### Props

| Prop | Type | Default | Description |
|---|---|---|---|
| `sectionId` | `number` | — | ID of a saved Arbol section. Mutually exclusive with inline config props. |
| `series` | `string` | — | Series name (inline config mode). Required if `sectionId` is not provided. |
| `format` | `'table' \| 'line' \| 'bar' \| 'pie'` | — | Display format (inline config mode). Required if `sectionId` is not provided. |
| `filters` | `Array<{field: string; value: string}>` | `[]` | Data filters. |
| `slice` | `string` | — | Slice for grouping data. |
| `xaxisSlice` | `string` | — | X-axis slice (line/bar charts). |
| `aggregator` | `string` | `'Default'` | Aggregator function name (charts only). |
| `percentageMode` | `'xaxis_group' \| 'total' \| null` | `null` | Percentage mode (line/bar only). |
| `showTitle` | `boolean` | `true` | Show the section name as a heading. Only applies in section ID mode. |
| `showDescription` | `boolean` | `true` | Show the section description. Only applies in section ID mode. |
| `showRefresh` | `boolean` | `true` | Show the refresh button. |
| `showDownload` | `boolean` | `true` | Show download CSV buttons. |
| `height` | `number \| string` | — | Optional fixed height for the container. |
| `onDataLoaded` | `(data: any) => void` | — | Callback fired when data is successfully loaded. |
| `onError` | `(error: string) => void` | — | Callback fired on fetch errors. |

### ChakraProvider Requirement

If the consumer's app root does not have a `ChakraProvider`, they must wrap the embed:

```tsx
import {ChakraProvider} from '@calvient/decal';
import {ArbolEmbed} from 'calvient-arbol/embed';

function MyPage() {
  return (
    <ChakraProvider>
      <ArbolEmbed sectionId={5} />
    </ChakraProvider>
  );
}
```

If the consumer already uses Chakra UI / `@calvient/decal`, no extra wrapping is needed.

---

## Data Flow

### Section ID Mode

```
1. ArbolEmbed mounts with sectionId=5
2. GET /api/arbol/embed/sections/5
   -> Returns: {id, name, series, format, filters, slice, ...}
3. GET /api/arbol/embed/data?section_id=5&series=...&format=...&filters=...
   -> 200: Data ready -> render format component
   -> 202: Job queued -> poll every 10s until 200
4. Format component renders (TableFormat, LineFormat, etc.)
```

### Inline Config Mode

```
1. ArbolEmbed mounts with series="User Activity" format="table" ...
2. No config fetch needed (props are the config)
3. GET /api/arbol/embed/data?series=User+Activity&format=table&filters=...
   -> 200: Data ready -> render format component
   -> 202: Job queued -> poll every 10s until 200
4. Format component renders
```

### Polling + Loading States

Same behavior as the existing `ReportSection`:
- Show "Loading data..." with estimated time remaining
- Poll every 10 seconds on 202 responses
- Show "Force Refresh" button if elapsed time exceeds estimate
- Show "Refresh" button in the header once data is loaded

---

## Implementation Steps

### Step 1: Backend — New EmbedController + Routes

1. Create `src/Http/Controllers/API/EmbedController.php` with:
   - `getSection(ArbolSection $section)` — returns section config JSON
   - `getData()` — returns section data (supports both section_id and inline config)
2. Add routes to `routes/api.php`
3. Add access control (reuse `ArbolAccess` contract for section ID mode; auth-only for inline mode)

### Step 2: Backend — Extend ArbolService for Config Hash Caching

1. Add `getDataFromCacheByHash()`, `storeDataInCacheByHash()`, and related methods
2. Add `getFormattedDataFromCacheByHash()`, `storeFormattedDataInCacheByHash()`
3. Add `getIsRunningByHash()`, `setIsRunningByHash()`

### Step 3: Backend — Extend LoadSectionData Job

1. Make `arbolSection` nullable in the constructor
2. Add `configHash` parameter
3. Update `uniqueId()` to use `configHash` when `arbolSection` is null
4. Update cache store/read calls to use hash-based methods when `arbolSection` is null

### Step 4: Frontend — Create ArbolEmbed Component

1. Create `resources/ts/Embeddable/ArbolEmbed.tsx`
2. Create `resources/ts/Embeddable/index.ts` (barrel export)
3. Implement Phase 1: config resolution (fetch from API or use props)
4. Implement Phase 2: data fetching with polling (adapt from `ReportSection.tsx`)
5. Implement format rendering (import existing format components)
6. Add display option props (showTitle, showDownload, height, etc.)

### Step 5: Build Configuration

1. Add a separate Vite entry point for the embeddable component (library mode)
2. Or: document that consumers import the TSX directly from vendor and rely on their own build pipeline to compile it (simpler, fewer moving parts)

**Recommended approach:** Direct TSX import from vendor. The consumer's Vite/webpack config already handles TSX compilation. No separate build step needed from Arbol's side. This is the same pattern used by Laravel Jetstream and Breeze for their React/Vue components.

### Step 6: Documentation

1. Update this doc with final implementation details
2. Add a "Getting Started" section to the package README
3. Document peer dependency installation
4. Provide copy-paste examples for both modes

---

## Future Enhancements

### Blade Component (Non-React Apps)

For consumers who don't use React (or want to embed in a Blade-only page), a Blade component could render a self-contained widget:

```blade
<x-arbol::embed :section-id="5" />
```

This would:
1. Render a target `<div>` with a data attribute
2. Include a standalone JS bundle (separate Vite entry) that mounts React into the div
3. Bundle all dependencies (React, Chakra, Recharts) into the standalone script

This is significantly more complex (requires bundling React as a standalone script) and should be a follow-up.

### Publishable React Component

Instead of importing from the vendor directory, publish the component files to the consumer's app:

```bash
php artisan vendor:publish --tag=arbol-embed
```

This copies the component files to `resources/js/vendor/arbol/`, making them part of the consumer's codebase. The consumer can then customize them if needed.

### Headless Mode

A data-only hook that returns the fetched data without any UI rendering, for consumers who want full control over presentation:

```tsx
import {useArbolSection} from 'calvient-arbol/embed';

function MyCustomChart() {
  const {data, isLoading, error, refresh} = useArbolSection({
    sectionId: 5,
  });

  if (isLoading) return <MySpinner />;
  return <MyCustomVisualization data={data} />;
}
```

### WebSocket/Broadcast Polling

Replace HTTP polling with Laravel Echo + WebSockets for real-time updates when the background job completes. This would eliminate the 10-second polling delay.
