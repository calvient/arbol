import React, {useCallback, useEffect, useState} from 'react';
import {Box, Text} from '@calvient/decal';
import DataTableContainer, {
  type DataTableRow,
  type DataTableState,
} from '../Components/DataTableContainer.tsx';

export interface DataTableFromUrlProps {
  /** URL that returns table data (e.g. GET /api/arbol/section-data?series=...&format=table). Must return JSON: Record<string, DataTableRow[]> (e.g. { All: [...] }). */
  dataUrl: string;
  /** Optional URL for "Download current view" (e.g. GET /arbol/section-data/download?...) */
  downloadUrl?: string;
  /** Optional URL for "Export to CSV" (can be same as downloadUrl) */
  exportCsvUrl?: string;
  /** Optional: callback when table state changes (for downstream visualizations) */
  onTableStateChange?: (state: DataTableState) => void;
  /** Optional: callback when user clicks "Add to Report" */
  onAddToReport?: () => void;
  /** Optional: initial page size (default 10) */
  defaultPageSize?: number;
  /** Optional: client-side search is applied to rows when provided */
  searchQuery?: string;
  /** Optional: poll again after this many ms when API returns 202 (default 10000) */
  pollIntervalMs?: number;
  /** Optional: when this value changes, a force-refresh is triggered (e.g. from a parent Refresh button) */
  refreshKey?: number;
  /** Optional: called when loading state changes (e.g. to disable a parent Refresh button) */
  onLoadingChange?: (isLoading: boolean) => void;
  /** Optional: called when API returns 202 with estimated_time (for showing countdown in parent) */
  onReceiving202?: (estimatedTimeSeconds: number) => void;
}

/**
 * URL-driven data table for use in the parent app. Fetches data from the given URL,
 * handles loading and 202 polling, and renders DataTableContainer. Use this when you
 * want to drop a table into any view with just the stateless section data URL (or any
 * compatible JSON endpoint).
 *
 * @example
 * // Stateless Arbol section URL
 * <DataTableFromUrl
 *   dataUrl={`/api/arbol/section-data?${new URLSearchParams({ series: 'My Series', format: 'table' })}`}
 *   downloadUrl={`/arbol/section-data/download?${new URLSearchParams({ series: 'My Series', format: 'table' })}`}
 *   exportCsvUrl={`/arbol/section-data/download?${new URLSearchParams({ series: 'My Series', format: 'table' })}`}
 * />
 */
const DataTableFromUrl: React.FC<DataTableFromUrlProps> = ({
  dataUrl,
  downloadUrl,
  exportCsvUrl,
  onTableStateChange,
  onAddToReport,
  defaultPageSize,
  searchQuery = '',
  pollIntervalMs = 10000,
  refreshKey,
  onLoadingChange,
  onReceiving202,
}) => {
  const [data, setData] = useState<Record<string, DataTableRow[]> | null>(null);
  const [currentSlice, setCurrentSlice] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    onLoadingChange?.(isLoading);
  }, [isLoading, onLoadingChange]);

  const appendForceRefresh = useCallback((url: string) => {
    const u = new URL(url, window.location.origin);
    u.searchParams.set('force_refresh', '1');
    return u.pathname + u.search;
  }, []);

  const loadData = useCallback(
    async (forceRefresh = false) => {
      setError(null);
      setIsLoading(true);
      const url = forceRefresh ? appendForceRefresh(dataUrl) : dataUrl;
      try {
        const response = await fetch(url, {
          headers: { Accept: 'application/json' },
        });
        if (response.status === 200) {
          const result = (await response.json()) as Record<string, DataTableRow[]>;
          setData(result);
          if (result && typeof result === 'object' && !Array.isArray(result)) {
            const keys = Object.keys(result);
            if (keys.length > 0) {
              setCurrentSlice((prev) => (prev && keys.includes(prev) ? prev : keys[0]));
            }
          }
          setIsLoading(false);
        } else if (response.status === 202) {
          const body = (await response.json()) as { estimated_time?: number };
          onReceiving202?.(body.estimated_time ?? pollIntervalMs / 1000);
          setTimeout(() => loadData(false), pollIntervalMs);
        } else {
          setError(response.statusText || `HTTP ${response.status}`);
          setIsLoading(false);
        }
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Failed to load data');
        setIsLoading(false);
      }
    },
    [dataUrl, appendForceRefresh, pollIntervalMs, onReceiving202],
  );

  useEffect(() => {
    setCurrentSlice(null);
    loadData();
  }, [loadData]);

  useEffect(() => {
    if (refreshKey != null && refreshKey > 0) {
      loadData(true);
    }
  }, [refreshKey, loadData]);

  if (error) {
    return (
      <Box p={4} borderWidth="1px" borderColor="red.200" borderRadius="md" bg="red.50">
        <Text color="red.700" fontSize="sm">
          {error}
        </Text>
      </Box>
    );
  }

  return (
    <DataTableContainer
      data={data ?? { All: [] }}
      currentSlice={currentSlice}
      onSliceChange={setCurrentSlice}
      searchQuery={searchQuery}
      hideSliceSelector={true}
      isLoading={isLoading}
      onRefresh={() => loadData(true)}
      downloadCurrentViewUrl={data ? downloadUrl : undefined}
      exportCsvUrl={data ? exportCsvUrl : undefined}
      onAddToReport={onAddToReport}
      onTableStateChange={onTableStateChange}
      defaultPageSize={defaultPageSize}
    />
  );
};

export default DataTableFromUrl;
