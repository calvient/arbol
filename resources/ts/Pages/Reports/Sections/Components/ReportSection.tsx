import {Section} from '../../../../Types/Section.ts';
import {Box, Button, HStack, Heading, Progress, Spacer, Text} from '@calvient/decal';
import {useQuery} from 'react-query';
import {toQueryString} from '../../../../Utils/toQueryString.ts';
import TableFormat from './Formats/TableFormat.tsx';
import PieFormat from './Formats/PieFormat.tsx';
import {Link} from '@inertiajs/react';
import {Report} from '../../../../Types/Report.ts';

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
    return <Progress size='xs' isIndeterminate />;
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
          size={'sm'}
        >
          Edit
        </Button>
      </HStack>

      {section.format === 'table' && <TableFormat data={data} />}
      {section.format === 'pie' && <PieFormat data={data} />}
    </Box>
  );
};

export default ReportSection;
