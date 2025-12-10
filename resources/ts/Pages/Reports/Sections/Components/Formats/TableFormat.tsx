import {Box, Select, Table, Tbody, Td, Th, Thead, Tr} from '@calvient/decal';
import React from 'react';
import {
  createColumnHelper,
  flexRender,
  getCoreRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  PaginationState,
  useReactTable,
} from '@tanstack/react-table';
import {inferColumnNames} from '../../../../../Utils/inferColumnNames.ts';
import Paginator from '../../../../../Components/Paginator.tsx';

type Row = Record<string, string | number | null>;

interface TableFormatProps {
  data: Record<string, Row[]>;
  currentSlice: string | null;
  onSliceChange: (slice: string) => void;
}

const TableFormat = ({data, currentSlice, onSliceChange}: TableFormatProps) => {
  const [pagination, setPagination] = React.useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });
  const slices = Object.keys(data);
  // Use the first slice as fallback if currentSlice is null or not in data
  const activeSlice = currentSlice && currentSlice in data ? currentSlice : slices[0];

  const columnHelper = createColumnHelper();

  const columns = inferColumnNames(data).map((columnName) =>
    columnHelper.accessor(columnName, {
      header: columnName,
      cell: ({row}) =>
        columnName in (row.original as Row) ? (row.original as Row)[columnName] : null,
    }),
  );

  const rows = React.useMemo(() => {
    return data[activeSlice];
  }, [data, activeSlice]);

  const table = useReactTable({
    // @ts-expect-error -- Because the columns are dynamically generated
    columns,
    data: rows,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    onPaginationChange: setPagination,
    state: {
      pagination,
    },
  });

  return (
    <Box w={'full'} mt={4}>
      <Select
        value={activeSlice}
        onChange={(e) => onSliceChange(e.target.value)}
        boxShadow={'0 -1px 0 rgba(0, 0, 0, 0.2)'}
      >
        {slices.map((slice) => (
          <option key={slice} value={slice}>
            {slice}
          </option>
        ))}
      </Select>

      <Box overflowX={'auto'}>
        <Table mt={4} size={'sm'}>
          <Thead>
            {table.getHeaderGroups().map((headerGroup) => (
              <Tr key={headerGroup.id}>
                {headerGroup.headers.map((header) => {
                  return (
                    <Th key={header.id} colSpan={header.colSpan}>
                      {header.isPlaceholder ? null : (
                        <div
                          className={header.column.getCanSort() ? 'cursor-pointer select-none' : ''}
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
                          {flexRender(header.column.columnDef.header, header.getContext())}
                          {{
                            asc: ' 🔼',
                            desc: ' 🔽',
                          }[header.column.getIsSorted() as string] ?? null}
                        </div>
                      )}
                    </Th>
                  );
                })}
              </Tr>
            ))}
          </Thead>
          <Tbody>
            {table.getRowModel().rows.map((row) => {
              return (
                <Tr key={row.id}>
                  {row.getVisibleCells().map((cell) => {
                    return (
                      <Td key={cell.id}>
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </Td>
                    );
                  })}
                </Tr>
              );
            })}
          </Tbody>
        </Table>
        <Paginator
          currentPage={pagination.pageIndex + 1}
          totalPages={table.getPageCount()}
          onChange={(page) => table.setPageIndex(page - 1)}
        />
      </Box>
    </Box>
  );
};

export default TableFormat;
