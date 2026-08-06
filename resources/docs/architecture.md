# Arbol Architecture Deep Dive

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Database Schema](#database-schema)
4. [Series](#series)
5. [ArbolBag](#arbolbag)
6. [Filters](#filters)
7. [Slices](#slices)
8. [Aggregators](#aggregators)
9. [Data Pipeline](#data-pipeline)
10. [Caching Layer](#caching-layer)
11. [Frontend Architecture](#frontend-architecture)
12. [Format Rendering](#format-rendering)
13. [Access Control](#access-control)
14. [Multi-tenancy](#multi-tenancy)
15. [Artisan Commands](#artisan-commands)

---

## Overview

Arbol is a Laravel package that provides self-service data visualization for Laravel + Inertia.js + React applications. Users create **Reports** containing **Sections**, where each section is backed by a developer-defined **Series** (a PHP class that returns raw data). Sections can apply **Filters**, **Slices**, and **Aggregators** to transform and visualize the data as tables, line charts, bar charts, or pie charts.

The package is installed via Composer, publishes its own routes, migrations, views, and config, and renders its UI within an isolated Inertia.js context (separate from the host app's Inertia middleware).

---

## File Structure

```
src/
  ArbolServiceProvider.php         # Package registration, config, routes, migrations, default ArbolAccess binding
  Contracts/
    IArbolSeries.php               # Interface every Series class must implement
    ArbolAccess.php                 # Interface for access control (users, teams, report permissions)
  DataObjects/
    ArbolBag.php                   # Value object carrying filters + slice selection into Series::data()
  Models/
    ArbolReport.php                # Eloquent model for reports (has many sections)
    ArbolSection.php               # Eloquent model for sections (belongs to report)
  Services/
    ArbolService.php               # Core service: series discovery, cache read/write, run-state tracking
  Jobs/
    LoadSectionData.php            # Queued job: fetches data from series, slices it, formats for charts, caches results
  Http/
    Controllers/
      ReportsController.php        # CRUD for reports (Inertia pages)
      SectionsController.php       # CRUD for sections within a report (Inertia pages)
      API/
        SeriesController.php       # JSON API: serves section data to the frontend, handles CSV downloads
    Middleware/
      HandleInertiaRequests.php    # Sets Arbol's own Inertia root view
  Commands/
    MakeArbolSeries.php            # `php artisan make:arbol-series {name}` scaffolding
    ClearArbolCache.php            # `php artisan arbol:clear` cache management
    BenchmarkFormatting.php        # `php artisan arbol:benchmark` performance testing
  Database/Factories/
    ArbolReportFactory.php         # Test factory for reports
    ArbolSectionFactory.php        # Test factory for sections

database/migrations/
  create_arbol_reports_table.php.stub
  create_arbol_sections_table.php.stub
  add_xaxis_slice_to_arbol_sections_table.php.stub
  add_aggregator_to_arbol_sections_table.php.stub
  add_client_id_to_arbol_reports_table.php.stub
  add_team_ids_to_arbol_reports_table.php.stub
  add_percentage_mode_to_arbol_sections_table.php.stub

routes/
  web.php                          # Report/section CRUD + CSV download routes
  api.php                          # JSON API for series data

config/
  arbol.php                        # user_model, series_path, max_chart_groups

resources/
  ts/
    Types/
      Report.ts                    # TypeScript type for Report
      Section.ts                   # TypeScript type for Section
      Series.ts                    # TypeScript type for Series metadata
      User.ts                      # TypeScript type for User
    Utils/
      toQueryString.ts             # Converts nested objects to URL query strings
      inferColumnNames.ts          # Scans table data to extract column headers
      stringToColor.ts             # Deterministic string -> hex color hash
      chartTruncation.ts           # Detects backend truncation metadata in chart data
    Components/
      Layout.tsx                   # App shell layout
      DataTableContainer.tsx       # Primary data table: sticky headers, column visibility, sort, pagination, toolbar
      Paginator.tsx                # Table pagination component
      BoxSelect.tsx                # Icon-based format selector (table/line/bar/pie)
      AutoComplete.tsx             # Autocomplete input
      ConfirmableBtn.tsx           # Button with confirmation dialog
    Pages/Reports/
      Index.tsx                    # Report list page
      Show.tsx                     # Single report view (renders all sections)
      Create.tsx                   # Create report form
      Edit.tsx                     # Edit report form
      Sections/
        Create.tsx                 # Create section form (series/filter/slice/format selection)
        Edit.tsx                   # Edit section form
        Components/
          ReportSection.tsx        # Main section renderer: fetches data, shows loading/format
          AddFilters.tsx           # Filter selection popover UI
          Formats/
            TableFormat.tsx        # Table with sorting, pagination, slice selector
            LineFormat.tsx         # Recharts line chart
            BarFormat.tsx          # Recharts bar chart
            PieFormat.tsx          # Recharts pie chart
            TruncationWarning.tsx  # Warning banner for truncated chart data
    Embeddable/              # Public API for parent app reuse
      index.ts               # Exports DataTableFromUrl, DataTableContainer + types
      DataTableFromUrl.tsx   # URL-driven wrapper: pass dataUrl, get table with fetch/polling
      README.md              # How to use from parent app
```

---

## Database Schema

### `arbol_reports`

| Column        | Type             | Description                                                  |
|---------------|------------------|--------------------------------------------------------------|
| `id`          | bigint (PK)      | Primary key                                                  |
| `name`        | string           | Report name                                                  |
| `description` | text (nullable)  | Report description                                           |
| `author_id`   | bigint (FK)      | User who created the report                                  |
| `user_ids`    | json (nullable)  | Array of user IDs with access; `-1` means "all users"        |
| `team_ids`    | json (nullable)  | Array of team IDs with access                                |
| `client_id`   | bigint (nullable)| Multi-tenancy: scopes report to a client                     |
| `timestamps`  |                  | `created_at`, `updated_at`                                   |

### `arbol_sections`

| Column            | Type             | Description                                                  |
|-------------------|------------------|--------------------------------------------------------------|
| `id`              | bigint (PK)      | Primary key                                                  |
| `arbol_report_id` | bigint (FK)      | Parent report                                                |
| `name`            | string           | Section name                                                 |
| `description`     | text (nullable)  | Section description                                          |
| `series`          | string           | Name of the Series (matches `IArbolSeries::name()`)          |
| `slice`           | string (nullable)| Selected slice for sub-dividing data                         |
| `xaxis_slice`     | string (nullable)| Slice used for x-axis on line/bar charts                     |
| `aggregator`      | string (nullable)| Selected aggregator name (e.g., "Default", "Total Listen Time") |
| `percentage_mode` | string (nullable)| `"xaxis_group"` or `"total"` or null                         |
| `filters`         | json (nullable)  | Array of `{field, value}` objects                            |
| `format`          | string           | `"table"`, `"line"`, `"bar"`, or `"pie"`                     |
| `sequence`        | integer          | Display order within the report                              |
| `timestamps`      |                  | `created_at`, `updated_at`                                   |

---

## Series

A **Series** is the central abstraction in Arbol. It is a PHP class that implements `Calvient\Arbol\Contracts\IArbolSeries` and defines:

### Interface: `IArbolSeries`

**Location:** `src/Contracts/IArbolSeries.php`

```php
interface IArbolSeries
{
    public function name(): string;
    public function description(): string;
    public function data(ArbolBag $arbolBag, $user = null): array;
    public function slices(): array;
    public function filters(): array;
    public function aggregators(): array;
}
```

### Method Breakdown

#### `name(): string`
Returns a human-readable name displayed in the UI. This name is stored in `arbol_sections.series` and used as the lookup key throughout the system. Must be unique across all series.

#### `description(): string`
A short description shown in the UI when selecting a dataset.

#### `data(ArbolBag $arbolBag, $user = null): array`
The core data-fetching method. Must return a **flat array of associative arrays** (2D tabular data). Each element represents a row.

- **`$arbolBag`**: Contains the user's selected filters and slice. Use `$arbolBag->applyQueryFilters($query, $this->filters())` to automatically apply matching filters to an Eloquent query.
- **`$user`**: The authenticated user (or null). Useful for user-specific data scoping.

Example return:
```php
[
    ['state' => 'TX', 'source' => 'Spotify', 'listen_length' => 12, 'created_at' => '2024-01-15'],
    ['state' => 'CA', 'source' => 'Apple',   'listen_length' => 25, 'created_at' => '2024-02-10'],
]
```

#### `slices(): array`
Returns a keyed array of callbacks. Each callback takes a single row (associative array) and returns a grouping value.

```php
public function slices(): array
{
    return [
        'State' => fn($row) => $row['state'],
        'Month' => fn($row) => date('Y-m', strtotime($row['created_at'])),
    ];
}
```

Slices serve two purposes depending on the format:
- **Table / Pie**: The selected slice groups the raw data via `Collection::groupBy()`. The table UI shows a dropdown to switch between groups.
- **Line / Bar**: The `xaxis_slice` determines the x-axis grouping, while the `slice` (called "chartSlice" internally) further sub-divides each x-axis group into separate lines/bars.

#### `filters(): array`
Returns a 2D array: filter group name -> filter option name -> callback. Each callback receives an Eloquent query builder.

```php
public function filters(): array
{
    return [
        'Time Period' => [
            'Last 7 Days'  => fn($query) => $query->where('created_at', '>=', now()->subDays(7)),
            'Last 30 Days' => fn($query) => $query->where('created_at', '>=', now()->subDays(30)),
        ],
        'Listen Length' => [
            '< 15 minutes'  => fn($query) => $query->where('listen_length', '<', 15),
            '>= 15 minutes' => fn($query) => $query->where('listen_length', '>=', 15),
        ],
    ];
}
```

See the [Filters](#filters) section for how these are applied.

#### `aggregators(): array`
Returns a keyed array of callbacks. Each callback receives an array of rows (within a slice group) and returns a single numeric value.

```php
public function aggregators(): array
{
    return [
        'Default'             => fn($rows) => count($rows),
        'Total Listen Time'   => fn($rows) => collect($rows)->sum('listen_length'),
        'Average Listen Time' => fn($rows) => collect($rows)->avg('listen_length'),
    ];
}
```

Aggregators are used by chart formats (line, bar, pie) to reduce grouped rows into single data points. The "Default" aggregator is the fallback.

### Series Discovery

**Location:** `ArbolService::getSeriesClasses()` in `src/Services/ArbolService.php`

Series classes are discovered at runtime by scanning the directory configured at `config('arbol.series_path')` (defaults to `app_path('Arbol')`). The discovery process:

1. Recursively iterates all `.php` files in the configured directory
2. `require_once`s each file
3. Checks all declared classes for the `IArbolSeries` interface
4. Returns matching class names

`ArbolService::getSeries()` then instantiates each class and extracts metadata (name, description, slice keys, filter keys, aggregator keys) for the frontend.

### Creating a Series

Use the artisan command:
```bash
php artisan make:arbol-series PodcastStreams
```

This generates `app/Arbol/PodcastStreamsSeries.php` (auto-appends "Series" suffix if not present) with a stub implementing all required methods.

---

## ArbolBag

**Location:** `src/DataObjects/ArbolBag.php`

`ArbolBag` is a simple data transfer object that carries the user's filter selections and slice choice into the `Series::data()` method. It acts as the bridge between what the user selected in the UI and the series' data-fetching logic.

### Construction

The `ArbolBag` is constructed in `LoadSectionData::createArbolBag()`:

```php
protected function createArbolBag(): ArbolBag
{
    $arbolBag = new ArbolBag;

    // Filters come as [{field: "Time Period", value: "Last 7 Days"}, ...]
    collect($this->filters)->each(
        fn ($filter) => $arbolBag->addFilter($filter['field'], $filter['value'])
    );

    if ($this->slice) {
        $arbolBag->addSlice($this->slice);
    }

    return $arbolBag;
}
```

### Internal State

```php
public array $filters = [];   // ['Time Period' => ['Last 7 Days'], 'Listen Length' => ['< 15 minutes']]
public ?string $slice = null;  // 'Month'
```

The `$filters` property is a map of filter group name to an array of selected option names within that group. Multiple options can be selected within the same group.

### Key Methods

#### `addFilter(string $field, string $filter): void`
Appends a filter selection. Multiple values per field are supported (stored as an array).

#### `addSlice(string $value): void`
Sets the selected slice name.

#### `getFilters(): array`
Returns the full filter selections map.

#### `getSlice(): ?string`
Returns the selected slice name.

#### `isFilterSet(string $field, string $filter): bool`
Checks whether a specific filter option is active. Used for manual conditional logic:
```php
if ($arbolBag->isFilterSet('Time Period', 'Last 7 Days')) {
    $query->where('created_at', '>=', now()->subDays(7));
}
```

#### `applyFilters(array $allFilters, callable $callback): void`
Iterates all defined filters and calls `$callback($filterFn)` for each one that is currently selected. Useful for non-query-based filtering.

#### `applyQueryFilters(Builder $query, array $allFilters): Builder`
The primary method for applying filters to Eloquent queries. For each filter group, it wraps the matching filter callbacks in a `where(function($q) { ... })` clause. This means filters within the same group are OR'd together (wrapped in a single `where` closure), while different groups are AND'd (separate `where` calls).

**Important behavior:** Filters within the same group are effectively OR'd because they apply to the same sub-query. Filters across different groups are AND'd because each group gets its own `$query->where(...)` wrapper.

Example: If a user selects "Last 7 Days" AND "< 15 minutes":
```sql
WHERE (created_at >= '7 days ago')          -- Time Period group
  AND (listen_length < 15)                  -- Listen Length group
```

If a user selects "Last 7 Days" AND "Last 30 Days" (same group):
```sql
WHERE (created_at >= '7 days ago' AND created_at >= '30 days ago')  -- both in same closure
```

---

## Filters

Filters narrow the data returned by a Series before any slicing or aggregation occurs. They operate at the query level (database) rather than post-processing.

### Definition (Series Side)

Filters are defined as a nested associative array in `IArbolSeries::filters()`:

```
[
    'Group Name' => [
        'Option Label' => fn($query) => $query->where(...),
        'Option Label' => fn($query) => $query->where(...),
    ],
]
```

- **Group Name**: The category shown in the UI (e.g., "Time Period")
- **Option Label**: Individual choices within the group (e.g., "Last 7 Days")
- **Callback**: Receives an Eloquent query builder and applies a constraint

### Storage (Database Side)

Selected filters are stored on `arbol_sections.filters` as JSON:
```json
[
    {"field": "Time Period", "value": "Last 7 Days"},
    {"field": "Listen Length", "value": "< 15 minutes"}
]
```

### Application Flow

1. **Frontend**: The `AddFilters` component displays available filter groups and options. Users add/remove filters via a popover. Selections are stored as `Array<{field: string, value: string}>`.

2. **API Request**: When the section loads data, filters are passed as query params: `filters[0][field]=Time+Period&filters[0][value]=Last+7+Days`.

3. **Job Creation**: `SeriesController::getSeriesData()` dispatches `LoadSectionData` with the filters array.

4. **ArbolBag Construction**: `LoadSectionData::createArbolBag()` converts the flat filter array into the ArbolBag's internal structure: `{field => [value1, value2]}`.

5. **Query Application**: Inside `Series::data()`, the developer calls `$arbolBag->applyQueryFilters($query, $this->filters())` which:
   - Iterates each filter group
   - For each group, wraps a sub-query
   - For each selected option in the group, calls the filter callback on the sub-query
   - Returns the modified query

### Frontend Filter UI

**Location:** `resources/ts/Pages/Reports/Sections/Components/AddFilters.tsx`

The `AddFilters` component:
- Shows currently selected filters as removable chips/pills
- Provides an "Add Filter" button that opens a popover
- The popover has two dropdowns: group selection, then option selection
- Adding a filter appends `{field, value}` to the section's filter array
- Filter groups and options come from the Series metadata provided by `ArbolService::getSeries()`

### Manual vs Automatic Filter Application

Developers have two choices in their `data()` method:

**Automatic** (recommended):
```php
$arbolBag->applyQueryFilters($query, $this->filters());
```

**Manual** (for custom logic):
```php
if ($arbolBag->isFilterSet('Time Period', 'Last 7 Days')) {
    $query->where('created_at', '>=', now()->subDays(7));
}
```

---

## Slices

Slices define how data is grouped for display. They are the primary mechanism for organizing data into meaningful categories.

### Definition

```php
public function slices(): array
{
    return [
        'State'  => fn($row) => $row['state'],
        'Month'  => fn($row) => date('Y-m', strtotime($row['created_at'])),
        'Source' => fn($row) => $row['source'],
    ];
}
```

Each slice callback receives a single data row (associative array) and returns a string value used as the group key.

### Application

**Location:** `LoadSectionData::applySlice()`

After the Series returns raw data, the job groups it:

```php
protected function applySlice($data, $seriesInstance): Collection
{
    foreach ($seriesInstance->slices() as $name => $callback) {
        if ($name === $this->slice) {
            return $data->groupBy(fn ($item) => $callback($item));
        }
    }
    return $data;
}
```

If no slice is selected, data is grouped under a single "All" key:
```php
$data = $this->slice
    ? $this->applySlice($data, $seriesInstance)
    : $data->groupBy(fn () => 'All');
```

### Result Structure

After slicing, data becomes a keyed collection:
```php
[
    'TX' => [
        ['state' => 'TX', 'source' => 'Spotify', ...],
        ['state' => 'TX', 'source' => 'Apple', ...],
    ],
    'CA' => [
        ['state' => 'CA', 'source' => 'Spotify', ...],
    ],
]
```

### Dual-Slice System for Charts

Line and bar charts use **two** slice fields:
- **`xaxis_slice`**: Determines the x-axis categories (what goes on the horizontal axis)
- **`slice`** (aka `chartSlice`): Further sub-divides each x-axis group into separate lines/bars

In the `LoadSectionData` constructor, the primary `$slice` parameter is set to `xaxis_slice` for line/bar formats, while `chartSlice` holds the sub-division slice:

```php
slice: request('format') === 'line' || request('format') === 'bar'
    ? request('xaxis_slice')   // x-axis grouping
    : request('slice'),         // table/pie grouping

chartSlice: request('slice'),   // sub-division for charts
```

### Chart Formatting with Slices

**Location:** `LoadSectionData::formatForChart()`

For chart data, the flow is:

1. Raw data is already grouped by `xaxis_slice` (the primary slice applied in `applySlice()`)
2. The `chartSlice` further sub-divides within each x-axis group
3. All unique `chartSlice` values are pre-computed in a single pass (O(N)) for consistency
4. For each x-axis group, rows are grouped by the `chartSlice` callback, aggregated, and missing slice values are filled with `0`

The result for a chart might look like:
```php
[
    ['name' => '2024-01', 'Spotify' => 150, 'Apple' => 80],
    ['name' => '2024-02', 'Spotify' => 200, 'Apple' => 120],
]
```

Where `name` is the x-axis label (from xaxis_slice) and each other key is a chartSlice value.

---

## Aggregators

Aggregators reduce a collection of rows into a single numeric value for chart formats.

### Definition

```php
public function aggregators(): array
{
    return [
        'Default'             => fn($rows) => count($rows),
        'Total Listen Time'   => fn($rows) => collect($rows)->sum('listen_length'),
        'Average Listen Time' => fn($rows) => collect($rows)->avg('listen_length'),
    ];
}
```

### Where They're Used

- **Pie charts**: Each slice group's rows are passed to the aggregator. Result: `[{name: "TX", value: 42}, {name: "CA", value: 18}]`
- **Line/Bar charts**: Within each x-axis group, the chartSlice sub-groups are aggregated individually.
- **Tables**: Aggregators are NOT used. Tables display raw rows.

### Fallback

If the requested aggregator is not found, the system falls back to `'Default'`. If neither exists, it falls back to `count($rows)`.

---

## Data Pipeline

The full lifecycle of data from request to rendering:

### 1. User Views Report
`Reports/Show.tsx` renders a `ReportSection` for each section.

### 2. Frontend Requests Data
`ReportSection.tsx` calls `GET /api/arbol/series-data` with section configuration (series name, filters, slice, format, aggregator, percentage_mode).

### 3. Cache Check
`SeriesController::getSeriesData()` checks for cached data:
- For chart formats: checks formatted cache first, then raw cache
- For table format: checks raw cache only

### 4. Job Dispatch
If no cache exists (or force_refresh), dispatches `LoadSectionData` to the queue.

Returns HTTP 202 with an estimated time (based on last run duration or 300s default).

### 5. Job Execution (`LoadSectionData::handle()`)

```
Series::data(ArbolBag)  ->  applySlice()  ->  storeDataInCache()
                                           ->  formatData()  ->  storeFormattedDataInCache()
```

a. Sets "is_running" semaphore
b. Constructs ArbolBag from filters + slice
c. Calls `seriesInstance->data($arbolBag, $user)` to get raw data
d. Groups data by selected slice (or "All" if none)
e. Stores raw sliced data in cache (14-day TTL)
f. If not table format: formats data for charts, stores formatted data in cache
g. Records run duration, clears semaphore

### 6. Frontend Polls
The frontend polls every 10 seconds. On 200, data is rendered. On 202, it continues polling.

### 7. Rendering
The `ReportSection` component passes data to the appropriate format component (TableFormat, LineFormat, BarFormat, PieFormat).

### Job Uniqueness
`LoadSectionData` implements `ShouldBeUnique`. The unique ID is:
```php
"{section_id}_{timestamp_rounded_to_5_minutes}"
```
This prevents duplicate jobs for the same section within a 5-minute window.

---

## Caching Layer

**Location:** `ArbolService` methods

All cache operations use Laravel's Cache facade with string keys:

| Cache Key                                    | TTL     | Content                              |
|----------------------------------------------|---------|--------------------------------------|
| `arbol:section:{id}`                         | 14 days | JSON-encoded raw sliced data         |
| `arbol:section:{id}:formatted`               | 14 days | JSON-encoded chart-formatted data    |
| `arbol:section:{id}:is_running`              | 30 min  | Boolean semaphore                    |
| `arbol:section:{id}:last_run_duration`       | 14 days | Integer seconds                      |

### Key Preservation
When storing raw data, numeric group keys (e.g., location ID `24`) are cast to strings to prevent `json_encode` from converting the object to a JSON array (which would lose keys).

### Cache Invalidation
- `arbol:clear` command clears all sections or a specific one
- Updating a section via `SectionsController::update()` clears that section's cache
- Frontend "Force Refresh" button sends `force_refresh=1` which clears and re-dispatches

---

## Frontend Architecture

### Stack
- React (via Inertia.js)
- Chakra UI (via `@calvient/decal` wrapper)
- Recharts for charts
- TanStack Table for sortable/paginated tables
- TypeScript

### Data Flow

```
Inertia Page Props (report, sections, series metadata)
  -> ReportSection component
    -> fetch(/api/arbol/series-data?...)
      -> polling on 202
      -> render format component on 200
```

### Key TypeScript Types

**Series** (metadata passed from backend):
```typescript
type Series = {
  name: string;
  description: string;
  filters: Record<string, string[]>;  // { "Time Period": ["Last 7 Days", "Last 30 Days"] }
  slices: string[];                    // ["State", "Month", "Source"]
  aggregators: string[];               // ["Default", "Total Listen Time"]
};
```

**Section** (stored configuration):
```typescript
type Section = {
  id?: number;
  name: string;
  series: string;           // Series name
  slice: string;            // Sub-division slice
  xaxis_slice?: string;     // X-axis slice (line/bar only)
  aggregator?: string;      // Aggregator name
  percentage_mode?: string; // "xaxis_group" | "total" | null
  filters: Array<{field: string; value: string}>;
  format: string;           // "table" | "line" | "bar" | "pie"
  sequence: number;
};
```

---

## Format Rendering

### Data Table Container (`DataTableContainer.tsx`)
- **Primary table container** used by all Arbol reporting pages (report sections and standalone section view).
- Lives in its own container region (`data-region="data-table-container"`); table sections render inside a `data-region="data-table"` wrapper.
- **Sticky column headers**; sortable columns; **column show/hide** via a Columns menu; **pagination** (no infinite scroll).
- **Top-right toolbar**: Refresh (re-run query), Export to CSV, Add to Report (optional hook), and Columns visibility menu.
- Exposes **canonical table state** via `onTableStateChange(state)`: `visibleColumns`, `sortBy`, `pageIndex`, `pageSize`, `totalRows`, `currentViewRows`, `allColumns`. Downstream visualizations should consume this state so the table remains the single source of truth.

**Using the table in the parent app:** The table can be reused in different views across the app. Use **`DataTableFromUrl`** from `resources/ts/Embeddable` when you only need to pass the data URL (e.g. the stateless section endpoint); it handles fetching, 202 polling, and loading. Use **`DataTableContainer`** when you already have data and want full control. See `resources/ts/Embeddable/README.md` for setup (Vite alias, props, and examples).

### Table Format (`TableFormat.tsx`)
- Receives `Record<string, Row[]>` (slice-key to rows). Report table sections use `DataTableContainer` instead for sticky headers, column visibility, toolbar actions, and canonical state. `TableFormat` remains for simpler table-only usage where the full container is not needed.
- Column names are inferred from the data via `inferColumnNames()`

### Line Chart (`LineFormat.tsx`)
- Receives `Array<{name: string, [sliceValue]: number}>`
- Uses Recharts `LineChart` with `ResponsiveContainer`
- Each data key (other than "name") becomes a separate line
- Colors are deterministic via `stringToColor()` hash
- Supports percentage mode (Y-axis 0-100)
- Handles truncation warnings

### Bar Chart (`BarFormat.tsx`)
- Same data structure and behavior as LineFormat, but renders bars
- Stacked by default when multiple slice values exist

### Pie Chart (`PieFormat.tsx`)
- Receives `Array<{name: string, value: number}>`
- Uses Recharts `PieChart` with custom labels showing name, value, and percentage
- 7-color palette cycles for slices
- Handles truncation warnings

### Chart Truncation
**Backend:** `LoadSectionData::truncateChartData()` limits chart data to `config('arbol.max_chart_groups')` (default 100). Appends a metadata marker:
```json
{"_meta": "truncated", "_total": 250, "_shown": 100}
```

**Frontend:** `extractTruncationMeta()` detects this marker and separates it from chart data. `TruncationWarning` displays an orange banner suggesting filters or table format.

### Percentage Mode (Line/Bar only)
Two modes:
- **`xaxis_group`**: Each row's values become percentages of that row's total
- **`total`**: Each value becomes a percentage of the grand total across all rows

Applied in both `LoadSectionData::applyPercentageMode()` (for cached chart data) and `SeriesController::applyPercentageMode()` (for CSV downloads).

---

## Access Control

### Interface: `ArbolAccess`

**Location:** `src/Contracts/ArbolAccess.php`

```php
interface ArbolAccess
{
    public function getUsers(): Collection;
    public function getTeams(): Collection;
    public function getUserTeamIds($user): array;
    public function userCanAccessReport($user, ArbolReport $report): bool;
}
```

### Default Implementation
`ArbolServiceProvider::registeringPackage()` binds a default anonymous class that:
- Lists users via the configured user model (with optional `scopeArbol` for filtering)
- Lists teams via the configured team model
- Resolves user team IDs via `$user->teams->pluck('id')` or `$user->team_id`
- Grants report access if user is author, in `user_ids`, `user_ids` contains `-1` (public), or user shares a team with the report's `team_ids`

### Custom Implementation
Publish the ArbolServiceProvider stub:
```bash
php artisan vendor:publish --tag=arbol-provider
```
This creates `app/Providers/ArbolServiceProvider.php` implementing `ArbolAccess`, which you can customize. The binding check `if ($this->app->bound(ArbolAccess::class))` ensures your custom binding takes precedence.

### Report Scoping
`ArbolReport::scopeMine()` filters reports to those the current user can see:
- Reports they authored
- Reports where their ID is in `user_ids`
- Reports where `user_ids` contains `-1`
- Reports where any of their team IDs appear in `team_ids`

---

## Multi-tenancy

Arbol supports optional multi-tenancy via `client_id`:

- If the authenticated user has a `client_id` attribute, reports are automatically scoped
- `ReportsController` filters index queries by client_id
- `SeriesController` validates section access against the report's client_id
- `SectionsController` validates report access against client_id
- The `client_id` field is guarded on `ArbolReport` to prevent mass-assignment

---

## Artisan Commands

### `make:arbol-series {name}`
Generates a new Series class at `app/Arbol/{Name}Series.php` with stubs for all required methods.

### `arbol:clear [--section=ID]`
Clears cached data. Without `--section`, clears all sections. Removes: raw data, formatted data, run flag, run duration, and last-kicked-off timestamp.

### `arbol:benchmark [--section=ID] [--groups=50] [--rows=500] [--slices=20]`
Performance benchmarking command comparing the old O(N*M) `formatForChart` algorithm (computing unique slice values inside the map loop) against the optimized O(N+M) version (computing them once before). Can run against real cached section data or synthetic generated data.

---

## Routes

### Web Routes (prefix: `/arbol`, middleware: `auth`, `web`, `HandleInertiaRequests`)
- `GET /arbol` -> redirect to reports index
- `GET /arbol/reports` -> `ReportsController@index`
- `GET /arbol/reports/create` -> `ReportsController@create`
- `POST /arbol/reports` -> `ReportsController@store`
- `GET /arbol/reports/{report}` -> `ReportsController@show`
- `GET /arbol/reports/{report}/edit` -> `ReportsController@edit`
- `PUT /arbol/reports/{report}` -> `ReportsController@update`
- `DELETE /arbol/reports/{report}` -> `ReportsController@destroy`
- `GET /arbol/reports/{report}/sections/create` -> `SectionsController@create`
- `POST /arbol/reports/{report}/sections` -> `SectionsController@store`
- `GET /arbol/reports/{report}/sections/{section}/edit` -> `SectionsController@edit`
- `PUT /arbol/reports/{report}/sections/{section}` -> `SectionsController@update`
- `DELETE /arbol/reports/{report}/sections/{section}` -> `SectionsController@destroy`
- `GET /arbol/series-data/download` -> `SeriesController@downloadData`

### API Routes (prefix: `/api/arbol`, middleware: `auth`, `api`)
- `GET /api/arbol/series-data` -> `SeriesController@getSeriesData`

---

## CSV Download

The download endpoint (`SeriesController::downloadData()`) re-formats cached data on the fly and streams it as CSV:
- Adds UTF-8 BOM for Excel compatibility
- Dynamically infers column headers from the data
- Numeric values are formatted with commas and 2 decimal places
- Nested grouped data is flattened with a `key` column added
- Supports `slice_key` parameter to download only a specific slice

The frontend provides two download buttons:
- **Download View**: Downloads only the currently visible slice (passes `slice_key`)
- **Download All**: Downloads all sliced data
