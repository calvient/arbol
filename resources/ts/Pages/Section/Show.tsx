import MinimalLayout from '../../Components/MinimalLayout.tsx';
import {Head} from '@inertiajs/react';
import React, {useEffect, useState} from 'react';
import {Box, Button, Heading, Text} from '@calvient/decal';
import {toQueryString} from '../../Utils/toQueryString.ts';
import DataTableFromUrl from '../../Embeddable/DataTableFromUrl.tsx';
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
  const [estimatedTime, setEstimatedTime] = useState(0);
  const [timeElapsed, setTimeElapsed] = useState(0);

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

  const dataUrl = `/api/arbol/section-data?${toQueryString({
    series,
    filters: reportFilters,
    format: 'table',
    force_refresh: 0,
  })}`;
  const exportCsvUrl = `/arbol/section-data/download?${toQueryString({
    series,
    filters: reportFilters,
    format: 'table',
    slice_key: 'All',
  })}`;

  useEffect(() => {
    const interval = setInterval(() => setTimeElapsed((prev) => prev + 1), 1000);
    return () => clearInterval(interval);
  }, []);

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

      <Box mt={4} w={'full'} data-region="data-table">
        <DataTableFromUrl
          dataUrl={dataUrl}
          exportCsvUrl={exportCsvUrl}
          searchQuery={searchQuery}
          refreshKey={refreshKey}
          onLoadingChange={(loading) => {
            setIsLoading(loading);
            if (loading) setTimeElapsed(0);
          }}
          onReceiving202={setEstimatedTime}
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
            <Button mt={2} size="xs" colorScheme="red" onClick={() => setRefreshKey((k) => k + 1)}>
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
