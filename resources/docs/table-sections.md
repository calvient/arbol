# Table Sections vs Chart Sections

Table sections behave fundamentally differently from chart sections (line, bar, pie) throughout the entire Arbol stack. This document explains every difference, why it exists, and where to find the relevant code.

---

## Table of Contents

1. [Overview](#overview)
2. [Section Editor Differences](#section-editor-differences)
3. [The `filters` Field: Dual Semantics](#the-filters-field-dual-semantics)
4. [Report Filter Bar](#report-filter-bar)
5. [Slicing Behavior](#slicing-behavior)
6. [Data Pipeline Differences](#data-pipeline-differences)
7. [Cache Key Scoping](#cache-key-scoping)
8. [Frontend Data Flow](#frontend-data-flow)
9. [Summary Table](#summary-table)

---

## Overview

Arbol supports four section formats: `table`, `line`, `bar`, and `pie`. While the chart formats (line, bar, pie) share a common data pipeline—aggregators reduce rows into single values, slices sub-divide data into chart lines/bars/wedges—table sections display **raw row-level data** and interact with the report in a different way.

The key architectural distinction: **table sections drive the report-level filter bar**, while chart sections use filters as hard data restrictions.

---

## Section Editor Differences

**Files:**
- `resources/ts/Pages/Reports/Sections/Create.tsx`
- `resources/ts/Pages/Reports/Sections/Edit.tsx`

When the user selects "Table" as the section type in the create/edit form, the following UI elements are **hidden**:

| UI Element | Table | Line/Bar | Pie |
|---|---|---|---|
| **Sub-divide the data by** (slice selector) | Hidden | Shown | Shown |
| **Aggregator** | Hidden | Shown | Shown |
| **Show on x-axis** (xaxis_slice) | Hidden | Shown (line/bar only) | Hidden |
| **Percentage Mode** | Hidden | Shown (line/bar only) | Hidden |
| **Filters label** | "Report Filters:" | "Filters:" | "Filters:" |
| **Filter value required** | No (optional default) | Yes | Yes |

The slice selector and all chart-specific controls are wrapped in `data.format !== 'table'` conditions. The `AddFilters` component receives `tableMode={data.format === 'table'}` to switch its behavior.

---

## The `filters` Field: Dual Semantics

The `arbol_sections.filters` JSON column stores `Array<{field: string, value: string}>`, but its **meaning changes based on the section format**.

### Chart Sections (line, bar, pie)

For chart sections, `filters` are **hard data restrictions**. They are sent directly to the API and applied at the database query level via `ArbolBag::applyQueryFilters()`. Both `field` and `value` are required.

Example stored data:
```json
[
  {"field": "Time Period", "value": "Last 30 Days"},
  {"field": "Status", "value": "Active"}
]
```

These filters are always applied—the chart only ever shows data matching these constraints.

### Table Sections

For table sections, `filters` are **report filter bar configuration**. They define:
1. **Which filter groups** appear in the report-level filter bar
2. **Optional default values** for those filter groups

Example stored data:
```json
[
  {"field": "Assignee", "value": ""},
  {"field": "Status", "value": "Open"},
  {"field": "Tasklist", "value": ""}
]
```

- `Assignee` and `Tasklist` have empty values: they appear in the filter bar but do not add a filter until the user selects a value.
- `Status` has `"Open"` as its value: that table section initially loads with `Status=Open` as its own saved default.

Configured fields and executable filters are kept separate. Empty configuration entries are never sent to the API. Non-empty defaults are sent only for their owning table section, and an explicit report-level selection replaces the saved default for sections that configure the same field.

### How This Works in Code

**`AddFilters.tsx`** — The `tableMode` prop controls this behavior:

- **Table mode (`tableMode=true`):**
  - Label reads "Report Filters:"
  - Helper text: "Choose which filters appear on the report. Optionally set a default value."
  - Value dropdown placeholder says "No default (optional)" and is not required
  - Each filter group can only be added once (duplicates prevented)
  - Chips display as "Group" or "Group: Default" depending on whether a value was set
  - The "Add Filter" button is disabled once all available groups have been added

- **Chart mode (`tableMode=false`):**
  - Label reads "Filters:"
  - Value dropdown is required
  - Same group can be added multiple times with different values
  - Chips display only the value

**`mergeSectionFilters.ts`** — Filter merging logic used by `ReportSection.tsx`:

```typescript
const configuredFilters = sectionFilters ?? [];
const sectionFields = new Set(configuredFilters.map((filter) => filter.field));
const applicableReportFilters = reportFilters.filter((filter) => sectionFields.has(filter.field));
const overriddenFields = new Set(applicableReportFilters.map((filter) => filter.field));

return [
  ...configuredFilters.filter(
    (filter) => filter.value !== '' && !overriddenFields.has(filter.field),
  ),
  ...applicableReportFilters,
];
```

The full configured list determines which report-level fields apply to the section. Only non-empty saved defaults enter the request, and report-level selections replace defaults field-by-field.

---

## Report Filter Bar

**Files:**
- `resources/ts/Components/ReportFilterBar.tsx`
- `resources/ts/Pages/Reports/Show.tsx`
- `src/Http/Controllers/ReportsController.php`

The report filter bar is a top-level UI element on the report show page. It only appears when table sections have configured report filters.

### Backend: Building `allFilters`

In `ReportsController::show()`, the controller iterates **only table sections** to build the filter bar configuration:

```php
foreach ($report->sections as $section) {
    if ($section->format !== 'table') {
        continue;  // Chart sections don't contribute to the report filter bar
    }

    $seriesData = $this->arbolService->getSeriesByName($section->series);

    foreach ($section->filters ?? [] as $filter) {
        $group = $filter['field'];
        // Populate allFilters[group] with available values from the series
    }
}
```

The controller passes **`allFilters`** (`Record<string, string[]>`) to the frontend. Saved defaults stay attached to each serialized section instead of being combined into a report-wide list.

### Frontend: Filter State Management

In `Show.tsx`, report-level selections start empty:

```typescript
const [reportFilters, setReportFilters] = useState<Array<{field: string; value: string}>>([]);
```

The filter bar is only rendered when `allFilters` has entries:

```typescript
const hasFilters = Object.keys(allFilters).length > 0;
```

### User Interaction Flow

1. Page loads with no report-wide selection; each table section applies only its own non-empty saved defaults
2. User toggles filter values via dropdown popovers (multi-select checkboxes)
3. User clicks **Refresh** to re-fetch data; selected values replace defaults only for sections that configure the matching field
4. Clearing report-level selections and refreshing restores each section's saved defaults
5. Data is not auto-fetched on filter change—the refresh is manual
6. The Refresh button is disabled while any section is loading

### Filter Bar Components

The `ReportFilterBar` component renders:
- **Filter pill buttons** — one per configured filter group, with a count badge when active
- **Search input** — client-side text search within table rows
- **Refresh button** — triggers data re-fetch for all sections
- **Active filter tags** — removable chips showing "Group: Value" for each selected filter
- **Clear all** — resets all filters and search

---

## Slicing Behavior

Slicing (the "Sub-divide the data by" concept) works completely differently for tables vs charts.

### Chart Sections

Charts use slicing to organize data:
- **Pie**: The slice groups data into wedges
- **Line/Bar**: `xaxis_slice` determines x-axis categories, `slice` sub-divides into separate lines/bars

The `section.slice` value is always sent to the API.

### Table Sections

When a report filter bar exists (`hasFilterBar=true`), table sections **ignore slicing entirely**:

```typescript
const effectiveSlice = hasFilterBar && section.format === 'table' ? null : section.slice;
```

This means:
- The API receives `slice: null`, so data is grouped under a single "All" key
- The full unsliced dataset is returned
- The slice dropdown in `TableFormat` is hidden (`hideSliceSelector={hasFilterBar}`)
- Filtering and narrowing is handled entirely by the report filter bar

When a report filter bar does NOT exist (no table sections have configured filters), table sections fall back to the original slicing behavior with the slice dropdown.

---

## Data Pipeline Differences

### Job Processing (`LoadSectionData`)

| Step | Table | Chart (line/bar/pie) |
|---|---|---|
| Fetch raw data | Same | Same |
| Apply slice | Groups by slice or "All" | Groups by xaxis_slice |
| Store raw cache | Yes | Yes |
| Format data | No (raw data returned as-is) | Yes (`formatForChart` / `formatForPie`) |
| Store formatted cache | No | Yes |
| Apply percentage mode | No | Yes (line/bar only) |
| Truncate chart groups | No | Yes (respects `max_chart_groups` config) |

### API Response (`SeriesController`)

| Behavior | Table | Chart |
|---|---|---|
| Cache lookup | Raw cache only | Formatted cache first, then raw cache |
| Response format | Raw grouped data `Record<string, Row[]>` | Array of `{name, value}` or `{name, ...sliceValues}` |
| Inline formatting | `formatForTable()` (pass-through) | Not done inline—dispatches job if formatted cache missing |

### Frontend Rendering

| Behavior | Table (`TableFormat`) | Charts |
|---|---|---|
| Library | TanStack Table | Recharts |
| Sorting | Client-side column sorting | N/A |
| Pagination | Client-side, 10 rows/page | N/A |
| Search | Client-side text filtering across all columns | N/A |
| Slice selector | Dropdown (hidden when filter bar active) | N/A (slices are chart dimensions) |

---

## Cache Key Scoping

Different filter combinations produce different cached datasets. Cache keys include a **filter hash** to prevent collisions.

**`ArbolService::computeFilterHash()`** generates an MD5 hash from the sorted filter array. This hash is appended to cache keys:

```
arbol:section:{id}              # No filters
arbol:section:{id}:fh:{hash}   # With specific filter combination
```

This means:
- A table section with "Assignee=Trina" cached data won't collide with "Assignee=John"
- The `LoadSectionData` job uses the filter hash in its `uniqueId()` to prevent duplicate jobs for different filter combos

---

## Frontend Data Flow

### Table Section with Filter Bar

```
Show.tsx (manages reportFilters state, initially empty)
  -> ReportFilterBar (user selects filters, clicks Refresh)
    -> Show.tsx bumps refreshKey
      -> ReportSection (useEffect on refreshKey)
        -> configured fields determine which reportFilters apply
        -> non-empty section defaults remain unless their field is overridden
        -> effectiveSlice = null                (no slicing)
        -> fetch(/api/arbol/series-data?filters=mergedFilters&slice=null)
          -> TableFormat (renders raw rows, search filtering, no slice dropdown)
```

### Chart Section

```
Show.tsx
  -> ReportSection (useEffect on refreshKey)
    -> mergedFilters = [...section.filters, ...reportFilters]
    -> effectiveSlice = section.slice (or xaxis_slice for line/bar)
    -> fetch(/api/arbol/series-data?filters=merged&slice=effectiveSlice)
      -> LineFormat / BarFormat / PieFormat
```

---

## Summary Table

| Aspect | Table Sections | Chart Sections (line/bar/pie) |
|---|---|---|
| `section.filters` meaning | Report filter bar config plus optional per-section defaults | Hard data restrictions |
| Filter value required | No (optional default) | Yes |
| Contributes to report filter bar | Yes | No |
| Slice selector in editor | Hidden | Shown |
| Slice at display time | Null (when filter bar active) | `section.slice` or `xaxis_slice` |
| Aggregator | Not used | Required |
| Percentage mode | Not available | Available (line/bar) |
| Raw data cached | Yes | Yes |
| Formatted data cached | No | Yes |
| Client-side search | Yes | No |
| Client-side sorting | Yes | No |
| Pagination | Yes (10 rows/page) | No |
