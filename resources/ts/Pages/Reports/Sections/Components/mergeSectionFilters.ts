import {SectionFilter} from '../../../../Types/Section.ts';

export const mergeSectionFilters = (
  sectionFilters: SectionFilter[] | null | undefined,
  reportFilters: SectionFilter[],
): SectionFilter[] => {
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
};
