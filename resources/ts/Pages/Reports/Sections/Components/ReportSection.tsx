import {Section} from '../../../../Types/Section.ts';
import {Box, Button, HStack, Heading, Spacer, Text} from '@calvient/decal';
import {toQueryString} from '../../../../Utils/toQueryString.ts';
import TableFormat from './Formats/TableFormat.tsx';
import PieFormat from './Formats/PieFormat.tsx';
import {Link} from '@inertiajs/react';
import {Report} from '../../../../Types/Report.ts';
import LineFormat from './Formats/LineFormat.tsx';
import BarFormat from './Formats/BarFormat.tsx';
import {useEffect, useState} from 'react';

interface ReportSectionProps {
  report: Report;
  section: Section;
}

const ReportSection = ({report, section}: ReportSectionProps) => {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [estimatedTime, setEstimatedTime] = useState(0);
  const [timeElapsed, setTimeElapsed] = useState(0);

  const loadData = async (forceRefresh: boolean = false) => {
    setIsLoading(true);
    if (forceRefresh) setTimeElapsed(0);

    const response = await fetch(
      `/api/arbol/series-data?${toQueryString({
        section_id: section.id,
        series: section.series,
        slice: section.slice,
        xaxis_slice: section.xaxis_slice,
        filters: section.filters,
        format: section.format,
        force_refresh: forceRefresh ? 1 : 0,
      })}`,
      {
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
      }
    );

    if (response.status === 200) {
      const data = await response.json();
      setData(data);
      setIsLoading(false);
    } else if (response.status === 202) {
      const data = await response.json();
      setEstimatedTime(data.estimated_time);
      setTimeout(loadData, 5000);
    }
  };

  useEffect(() => {
    loadData();

    const interval = setInterval(() => {
      setTimeElapsed((prev) => prev + 1);
    }, 1000);

    return () => clearInterval(interval);
  }, []);

  if (isLoading || !data) {
    return (
      <Box w={'full'} p={4} border={'solid 1px'} borderColor={'gray.200'} borderRadius={'md'}>
        <Text>Loading data...</Text>
        {estimatedTime - timeElapsed > 0 && (
          <Text>Estimated time remaining: {estimatedTime - timeElapsed} second(s)</Text>
        )}
        {estimatedTime - timeElapsed < 0 && <Text>This is taking longer than expected...</Text>}
      </Box>
    );
  }

  return (
    <Box w={'full'} p={4} border={'solid 1px'} borderColor={'gray.200'} borderRadius={'md'}>
      <HStack spacing={2}>
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
        <Button size={'xs'} colorScheme={'blue'} onClick={() => loadData(true)}>
          Refresh
        </Button>
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
