import {Section} from '../../../../Types/Section.ts';
import {Box, Button, HStack, Heading, Spacer, Text} from '@calvient/decal';
import {useQuery} from 'react-query';
import {toQueryString} from '../../../../Utils/toQueryString.ts';
import TableFormat from './Formats/TableFormat.tsx';
import PieFormat from './Formats/PieFormat.tsx';
import {Link} from '@inertiajs/react';
import {Report} from '../../../../Types/Report.ts';
import LineFormat from './Formats/LineFormat.tsx';
import BarFormat from './Formats/BarFormat.tsx';

interface ReportSectionProps {
  report: Report;
  section: Section;
}

const ReportSection = ({report, section}: ReportSectionProps) => {
  const {data, isLoading} = useQuery(['series-data', section.id, section.format], async () => {
    const response = await fetch(
      `/api/arbol/series-data?${toQueryString({
        series: section.series,
        slice: section.slice,
        filters: section.filters,
        format: section.format,
      })}`,
      {
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
      }
    );

    return response.json();
  });

  if (isLoading || !data) {
    return (
      <Box w={'full'} p={4} border={'solid 1px'} borderColor={'gray.200'} borderRadius={'md'}>
        <Text>Loading data...</Text>
      </Box>
    );
  }

  return (
    <Box w={'full'} p={4} border={'solid 1px'} borderColor={'gray.200'} borderRadius={'md'}>
      <HStack>
        <Box>
          <Heading size={'sm'}>{section.name}</Heading>
          {(section.description ?? '').split('\n').map((line, index) => {
            return (
              <Text key={index} fontSize={'sm'}>
                {line}
              </Text>
            );
          })}
        </Box>
        <Spacer />
        <Button
          as={Link}
          href={`/arbol/reports/${report.id}/sections/${section.id}/edit`}
          size={'xs'}
        >
          Edit Section
        </Button>
      </HStack>

      {section.format === 'table' && <TableFormat data={data} />}
      {section.format === 'pie' && <PieFormat data={data} />}
      {section.format === 'line' && <LineFormat data={data} />}
      {section.format === 'bar' && <BarFormat data={data} />}
    </Box>
  );
};

export default ReportSection;
