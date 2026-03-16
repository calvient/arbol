import MinimalLayout from '../../Components/MinimalLayout.tsx';
import {Head} from '@inertiajs/react';
import React, {useCallback, useEffect, useState} from 'react';
import {Box, Button, Heading, Text} from '@calvient/decal';
import {toQueryString} from '../../Utils/toQueryString.ts';
import DataTableContainer from '../../Components/DataTableContainer.tsx';
import ReportFilterBar from '../../Components/ReportFilterBar.tsx';

interface ShowProps {
  series: string;
  allFilters: Record<string, string[]>;
  defaultFilters: Array<{field: string; value: string}>;
}

const Show = ({series, allFilters, defaultFilters = []}: ShowProps) => {
  const [reportFilters, setReportFilters] = useState<Array<{field: string; value: string}>>(defaultFilters);
  const [searchQuery, setSearchQuery] = useState('');
  const [refreshKey, setRefreshKey] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const [data, setData] = useState<any>(null);
  const [estimatedTime, setEstimatedTime] = useState(0);
  const [timeElapsed, setTimeElapsed] = useState(0);
  const [currentSlice, setCurrentSlice] = useState<string | null>(null);

  const hasFilters = Object.keys(allFilters).length > 0;

  // Keep the URL in sync with the current filter state so the page is shareable/bookmarkable
  useEffect(() => {
    const filterEntries: string[] = [];

    for (const group of Object.keys(allFilters)) {
      const selected = reportFilters.filter((f) => f.field === group);
      if (selected.length > 0) {
        selected.forEach((f) => filterEntries.push(`${f.field}:${f.value}`));
      } else {
        filterEntries.push(group);
      }
    }

    const params = new URLSearchParams();
    params.set('series', series);
    if (filterEntries.length > 0) {
      params.set('filters', filterEntries.join(','));
    }

    window.history.replaceState({}, '', `?${params.toString()}`);
  }, [reportFilters, series, allFilters]);

  const loadData = useCallback(
    async (forceRefresh: boolean = false) => {
      setIsLoading(true);
      if (forceRefresh) setTimeElapsed(0);

      const response = await fetch(
        `/api/arbol/section-data?${toQueryString({
          series,
          filters: reportFilters,
          format: 'table',
          force_refresh: forceRefresh ? 1 : 0,
        })}`,
        {
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
        },
      );

      if (response.status === 200) {
        const result = await response.json();
        setData(result);
        if (result && typeof result === 'object' && !Array.isArray(result)) {
          const keys = Object.keys(result);
          if (keys.length > 0) {
            setCurrentSlice((prev) => prev && keys.includes(prev) ? prev : keys[0]);
          }
        }
        setIsLoading(false);
      } else if (response.status === 202) {
        const result = await response.json();
        setEstimatedTime(result.estimated_time);
        setTimeout(() => loadData(), 10000);
      }
    },
    [series, reportFilters],
  );

  useEffect(() => {
    setCurrentSlice(null);
    loadData();

    const interval = setInterval(() => {
      setTimeElapsed((prev) => prev + 1);
    }, 1000);

    return () => clearInterval(interval);
  }, [refreshKey, loadData]);

  return (
    <>
      <Head title={series} />
      <Heading size={'md'}>{series}</Heading>

      {hasFilters && (
        <Box mt={4}>
          <ReportFilterBar
            allFilters={allFilters}
            selectedFilters={reportFilters}
            onFiltersChange={setReportFilters}
            searchQuery={searchQuery}
            onSearchChange={setSearchQuery}
            onRefresh={() => setRefreshKey((k) => k + 1)}
            isLoading={isLoading}
          />
        </Box>
      )}

      {/* Data table container: always visible in stateless view; shows loading state until data is ready */}
      <Box mt={4} w={'full'} data-region="data-table">
        <DataTableContainer
          data={data ?? { All: [] }}
          currentSlice={currentSlice}
          onSliceChange={setCurrentSlice}
          searchQuery={searchQuery}
          hideSliceSelector={true}
          isLoading={isLoading}
          onRefresh={() => setRefreshKey((k) => k + 1)}
          downloadCurrentViewUrl={
            data
              ? `/arbol/section-data/download?${toQueryString({
                  series,
                  filters: reportFilters,
                  format: 'table',
                  slice_key: currentSlice,
                })}`
              : undefined
          }
          exportCsvUrl={
            data
              ? `/arbol/section-data/download?${toQueryString({
                  series,
                  filters: reportFilters,
                  format: 'table',
                  slice_key: currentSlice,
                })}`
              : undefined
          }
        />
        {isLoading && estimatedTime - timeElapsed > 0 && (
          <Box mt={2} px={2}>
            <Text fontSize="sm" color="gray.500">
              Estimated time remaining: {estimatedTime - timeElapsed} second(s)
            </Text>
          </Box>
        )}
        {isLoading && estimatedTime - timeElapsed < 0 && (
          <Box mt={2} px={2}>
            <Text fontSize="sm" color="gray.600">This is taking longer than expected.</Text>
            <Button mt={2} size="xs" colorScheme="red" onClick={() => loadData(true)}>
              Force Refresh
            </Button>
          </Box>
        )}
      </Box>
    </>
  );
};

Show.layout = (page: React.ReactElement) => <MinimalLayout children={page} />;

export default Show;
