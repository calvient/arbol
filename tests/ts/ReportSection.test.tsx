import {render, screen, waitFor} from '@testing-library/react';
import {afterEach, describe, expect, it, vi} from 'vitest';
import ReportSection from '../../resources/ts/Pages/Reports/Sections/Components/ReportSection';
import {Report} from '../../resources/ts/Types/Report';
import {Section} from '../../resources/ts/Types/Section';

type Filter = Section['filters'][number];

const report: Report = {
  id: 1,
  name: 'Regression report',
  description: '',
  sections: [],
  created_at: '',
  updated_at: '',
};

const section = (id: number, filters: Filter[]): Section => ({
  id,
  name: `Section ${id}`,
  series: 'Test Series',
  slice: 'All',
  filters,
  format: 'table',
  sequence: id,
});

const filtersFromUrl = (url: string): Filter[] => {
  const filters = new Map<number, Partial<Filter>>();

  for (const [key, value] of new URL(url, 'http://localhost').searchParams) {
    const match = key.match(/^filters\[(\d+)]\[(field|value)]$/);
    if (!match) {
      continue;
    }

    const index = Number(match[1]);
    const filter = filters.get(index) ?? {};
    filter[match[2] as keyof Filter] = value;
    filters.set(index, filter);
  }

  return [...filters.entries()]
    .sort(([left], [right]) => left - right)
    .map(([, filter]) => ({field: filter.field ?? '', value: filter.value ?? ''}));
};

const successfulFetch = () => {
  const fetchMock = vi.fn().mockResolvedValue({
    status: 200,
    json: vi.fn().mockResolvedValue({All: []}),
  });
  vi.stubGlobal('fetch', fetchMock);

  return fetchMock;
};

const requestUrlForSection = (
  fetchMock: ReturnType<typeof successfulFetch>,
  sectionId: number,
): string => {
  const call = fetchMock.mock.calls.find(([url]) =>
    String(url).includes(`section_id=${sectionId}`),
  );

  return String(call?.[0] ?? '');
};

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('ReportSection filters', () => {
  it('sends each table section only its own saved defaults on initial load', async () => {
    const fetchMock = successfulFetch();
    const firstSection = section(1, [{field: 'Date Range', value: 'This Month'}]);
    const secondSection = section(2, [
      {field: 'Date Range', value: 'Last Month'},
      {field: 'Survey Answer', value: 'Yes'},
      {field: 'Survey Answer', value: 'No'},
    ]);

    render(
      <>
        <ReportSection report={report} section={firstSection} hasFilterBar />
        <ReportSection report={report} section={secondSection} hasFilterBar />
      </>,
    );

    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));

    expect(filtersFromUrl(requestUrlForSection(fetchMock, 1))).toEqual(firstSection.filters);
    expect(filtersFromUrl(requestUrlForSection(fetchMock, 2))).toEqual(secondSection.filters);
  });

  it('overrides matching defaults while retaining unrelated defaults and multiple values', async () => {
    const fetchMock = successfulFetch();
    const savedFilters = [
      {field: 'Date Range', value: 'This Month'},
      {field: 'Survey Answer', value: 'Yes'},
      {field: 'Survey Answer', value: 'No'},
    ];
    const reportFilters = [
      {field: 'Date Range', value: 'Last Month'},
      {field: 'Date Range', value: 'Last 90 Days'},
      {field: 'Sibling Only', value: 'Ignored'},
    ];
    const expectedFilters = [
      {field: 'Survey Answer', value: 'Yes'},
      {field: 'Survey Answer', value: 'No'},
      {field: 'Date Range', value: 'Last Month'},
      {field: 'Date Range', value: 'Last 90 Days'},
    ];

    render(
      <ReportSection
        report={report}
        section={section(1, savedFilters)}
        reportFilters={reportFilters}
        hasFilterBar
      />,
    );

    await waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());

    expect(filtersFromUrl(requestUrlForSection(fetchMock, 1))).toEqual(expectedFilters);

    const viewUrl = screen.getByTitle('Download current view').getAttribute('href') ?? '';
    const allUrl = screen.getByTitle('Download all data').getAttribute('href') ?? '';
    expect(filtersFromUrl(viewUrl)).toEqual(expectedFilters);
    expect(filtersFromUrl(allUrl)).toEqual(expectedFilters);
  });
});
