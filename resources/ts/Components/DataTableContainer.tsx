import React, {useEffect, useMemo, useState} from 'react';
import {
  AddIcon,
  Box,
  Button,
  Checkbox,
  ChevronDownIcon,
  HStack,
  Link as ChakraLink,
  Menu,
  MenuButton,
  MenuItem,
  MenuList,
  RepeatIcon,
  Select,
  Spacer,
  Table,
  Tbody,
  Td,
  Text,
  Th,
  Thead,
  Tr,
} from '@calvient/decal';
import {
  createColumnHelper,
  flexRender,
  getCoreRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  PaginationState,
  SortingState,
  useReactTable,
  VisibilityState,
} from '@tanstack/react-table';
import {inferColumnNames} from '../Utils/inferColumnNames.ts';
import Paginator from './Paginator.tsx';

export type DataTableRow = Record<string, string | number | null>;

/** Canonical table state exposed for downstream visualizations (charts, exports, etc.) */
export interface DataTableState {
  /** Column ids currently visible */
  visibleColumns: string[];
  /** Current sort: column id and direction */
  sortBy: {id: string; desc: boolean} | null;
  /** Current page (1-based) */
  pageIndex: number;
  /** Page size */
  pageSize: number;
  /** Total row count (all pages) */
  totalRows: number;
  /** Rows for the current page after sort and filter (canonical view) */
  currentViewRows: DataTableRow[];
  /** All column ids (before visibility) */
  allColumns: string[];
}

export interface DataTableContainerProps {
  /** Slice key -> rows. Use a single key (e.g. "All") when no slicing. */
  data: Record<string, DataTableRow[]>;
  /** Current slice key to display */
  currentSlice: string | null;
  /** Called when user changes slice (only used when slices.length > 1) */
  onSliceChange?: (slice: string) => void;
  /** Optional slice selector hidden (e.g. when report filter bar is used) */
  hideSliceSelector?: boolean;
  /** Client-side search filter applied to rows */
  searchQuery?: string;
  /** Re-run query / refresh data */
  onRefresh?: () => void;
  /** URL for "Download current view" (e.g. CSV for current slice) */
  downloadCurrentViewUrl?: string;
  /** URL for "Export to CSV" (can be same as downloadCurrentViewUrl) */
  exportCsvUrl?: string;
  /** Called when user clicks "Add to Report" */
  onAddToReport?: () => void;
  /** Called when table state changes so downstream visualizations stay in sync */
  onTableStateChange?: (state: DataTableState) => void;
  /** Initial page size */
  defaultPageSize?: number;
  /** Optional Select component for slice dropdown (avoids importing in this file if not needed) */
  sliceOptions?: Array<{value: string; label: string}>;
  /** When true, show toolbar but table body shows a loading state (for stateless view and others) */
  isLoading?: boolean;
}

const defaultPageSize = 10;
const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

const DataTableContainer: React.FC<DataTableContainerProps> = ({
  data,
  currentSlice,
  onSliceChange,
  hideSliceSelector = false,
  searchQuery = '',
  onRefresh,
  downloadCurrentViewUrl,
  exportCsvUrl,
  onAddToReport,
  onTableStateChange,
  defaultPageSize: defaultPageSizeProp = defaultPageSize,
  sliceOptions,
  isLoading = false,
}) => {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: defaultPageSizeProp,
  });
  const [sorting, setSorting] = useState<SortingState>([]);
  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({});

  const slices = useMemo(() => Object.keys(data), [data]);
  const activeSlice =
    currentSlice && currentSlice in data ? currentSlice : slices[0] ?? null;

  const columnHelper = createColumnHelper<DataTableRow>();
  const allColumnNames = useMemo(() => inferColumnNames(data), [data]);

  /** Stateless job returns { All: [...] }; support empty data when loading */
  const hasData = !isLoading && Object.keys(data).length > 0 && allColumnNames.length > 0;

  const columns = useMemo(
    () =>
      allColumnNames.map((columnName) =>
        columnHelper.accessor(columnName, {
          header: columnName,
          cell: ({row}) => {
            const val = (row.original as DataTableRow)[columnName];
            return val ?? '';
          },
          enableHiding: true,
        }),
      ),
    [allColumnNames, columnHelper],
  );

  const rows = useMemo(() => {
    const sliceData = activeSlice ? (data[activeSlice] ?? []) : [];
    if (!searchQuery.trim()) return sliceData;
    const q = searchQuery.toLowerCase();
    return sliceData.filter((row) =>
      Object.values(row).some(
        (val) => val != null && String(val).toLowerCase().includes(q),
      ),
    );
  }, [data, activeSlice, searchQuery]);

  const table = useReactTable({
    data: rows,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    onPaginationChange: setPagination,
    onSortingChange: setSorting,
    onColumnVisibilityChange: setColumnVisibility,
    state: {
      pagination,
      sorting,
      columnVisibility,
    },
  });

  const rowModelRows = table.getRowModel().rows;
  const currentViewRows = useMemo(
    () => rowModelRows.map((row) => row.original as DataTableRow),
    [rowModelRows],
  );

  const visibleColumnIds = table.getVisibleLeafColumns().map((c) => c.id);
  const sortBy =
    sorting.length > 0
      ? {id: sorting[0].id, desc: sorting[0].desc}
      : null;

  const visibleColumnsKey = visibleColumnIds.join(',');
  const allColumnsKey = allColumnNames.join(',');
  useEffect(() => {
    onTableStateChange?.({
      visibleColumns: visibleColumnIds,
      sortBy,
      pageIndex: pagination.pageIndex + 1,
      pageSize: pagination.pageSize,
      totalRows: rows.length,
      currentViewRows,
      allColumns: allColumnNames,
    });
    // Intentionally depend only on primitive/stable keys so we don't run on every callback identity change
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    visibleColumnsKey,
    sortBy?.id,
    sortBy?.desc,
    pagination.pageIndex,
    pagination.pageSize,
    rows.length,
    allColumnsKey,
  ]);

  const showSliceSelector =
    !hideSliceSelector && slices.length > 1 && onSliceChange;
  const options = sliceOptions ?? slices.map((s) => ({value: s, label: s}));

  return (
    <Box
      w="full"
      border="solid 1px"
      borderColor="gray.200"
      borderRadius="md"
      overflow="hidden"
      bg="white"
      data-region="data-table-container"
    >
      {/* Top toolbar: optional slice selector (left) + actions (right) */}
      <HStack w="full" p={3} borderBottom="solid 1px" borderColor="gray.200" spacing={3}>
        {showSliceSelector && (
          <Select
            value={activeSlice ?? ''}
            onChange={(e) => onSliceChange?.(e.target.value)}
            size="sm"
            maxW="200px"
          >
            {options.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </Select>
        )}
        <Spacer />
        <HStack spacing={2}>
          {onRefresh && (
            <Button size="sm" leftIcon={<RepeatIcon />} onClick={onRefresh} colorScheme="blue">
              Refresh
            </Button>
          )}
          {downloadCurrentViewUrl && (
            <Button
              size="sm"
              variant="outline"
              as={ChakraLink}
              href={downloadCurrentViewUrl}
              target="_blank"
              rel="noopener noreferrer"
            >
              Download current view
            </Button>
          )}
          {exportCsvUrl && (
            <Button
              size="sm"
              variant="outline"
              as={ChakraLink}
              href={exportCsvUrl}
              target="_blank"
              rel="noopener noreferrer"
            >
              Export to CSV
            </Button>
          )}
          {onAddToReport && (
            <Button size="sm" variant="outline" leftIcon={<AddIcon />} onClick={onAddToReport}>
              Add to Report
            </Button>
          )}
          <Menu closeOnSelect={false}>
            <MenuButton as={Button} size="sm" variant="outline" rightIcon={<ChevronDownIcon />}>
              Columns
            </MenuButton>
            <MenuList minW="200px" maxH="320px" overflowY="auto" zIndex={1400}>
              {table.getAllLeafColumns().map((column) => (
                <MenuItem key={column.id} closeOnSelect={false}>
                  <Checkbox
                    isChecked={column.getIsVisible()}
                    isDisabled={!column.getCanHide()}
                    onChange={column.getToggleVisibilityHandler()}
                    size="sm"
                  >
                    <Text fontSize="sm">
                      {typeof column.columnDef.header === 'string'
                        ? column.columnDef.header
                        : column.id}
                    </Text>
                  </Checkbox>
                </MenuItem>
              ))}
            </MenuList>
          </Menu>
        </HStack>
      </HStack>

      {/* Table with sticky header, or loading/empty state */}
      <Box overflowX="auto" maxH="70vh" overflowY="auto">
        {!hasData ? (
          <Box p={8} textAlign="center" color="gray.500">
            <Text fontSize="sm">
              {isLoading ? 'Loading data…' : 'No data to display.'}
            </Text>
          </Box>
        ) : (
          <Table size="sm" sx={{tableLayout: 'auto'}}>
            <Thead position="sticky" top={0} zIndex={1} bg="gray.50" boxShadow="sm">
              {table.getHeaderGroups().map((headerGroup) => (
                <Tr key={headerGroup.id}>
                  {headerGroup.headers.map((header) => (
                    <Th
                      key={header.id}
                      colSpan={header.colSpan}
                      whiteSpace="nowrap"
                      cursor={header.column.getCanSort() ? 'pointer' : 'default'}
                      userSelect="none"
                      onClick={header.column.getToggleSortingHandler()}
                      title={
                        header.column.getCanSort()
                          ? header.column.getNextSortingOrder() === 'asc'
                            ? 'Sort ascending'
                            : header.column.getNextSortingOrder() === 'desc'
                              ? 'Sort descending'
                              : 'Clear sort'
                          : undefined
                      }
                    >
                      {header.isPlaceholder
                        ? null
                        : flexRender(header.column.columnDef.header, header.getContext())}
                      {{
                        asc: ' ↑',
                        desc: ' ↓',
                      }[header.column.getIsSorted() as string] ?? null}
                    </Th>
                  ))}
                </Tr>
              ))}
            </Thead>
            <Tbody>
              {table.getRowModel().rows.map((row) => (
                <Tr key={row.id}>
                  {row.getVisibleCells().map((cell) => (
                    <Td key={cell.id}>
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </Td>
                  ))}
                </Tr>
              ))}
            </Tbody>
          </Table>
        )}
      </Box>

      {/* Pagination and rows-per-page (only when we have data) */}
      {hasData && (
        <Box borderTop="solid 1px" borderColor="gray.200">
          <HStack w="full" p={4} justifyContent="space-between" alignItems="center" flexWrap="wrap" gap={3}>
            <HStack alignItems="center" spacing={2}>
              <Text fontSize="sm" color="gray.600" whiteSpace="nowrap">
                Rows per page:
              </Text>
              <Select
                size="sm"
                value={pagination.pageSize}
                onChange={(e) => {
                  const size = Number(e.target.value);
                  table.setPageSize(size);
                  table.setPageIndex(0);
                }}
                w="auto"
                minW="70px"
              >
                {PAGE_SIZE_OPTIONS.map((n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
              </Select>
            </HStack>
            <Box flex={1} display="flex" justifyContent="center">
              <Paginator
                currentPage={pagination.pageIndex + 1}
                totalPages={table.getPageCount()}
                onChange={(page) => table.setPageIndex(page - 1)}
              />
            </Box>
          </HStack>
        </Box>
      )}
    </Box>
  );
};

export default DataTableContainer;
