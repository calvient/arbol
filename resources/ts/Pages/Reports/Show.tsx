import Layout from '../../Components/Layout.tsx';
import {Head, Link} from '@inertiajs/react';
import React, {useCallback, useState} from 'react';
import {User} from '../../Types/User.ts';
import {Report} from '../../Types/Report.ts';
import {AddIcon, Box, Center, HStack, Heading, Spacer, Text, VStack, Button} from '@calvient/decal';
import ReportSection from './Sections/Components/ReportSection.tsx';
import ReportFilterBar from '../../Components/ReportFilterBar.tsx';

interface ShowProps {
  report: Report;
  users: User[];
  allFilters: Record<string, string[]>;
  defaultFilters?: Array<{field: string; value: string}>;
}

const Show = ({report, allFilters, defaultFilters = []}: ShowProps) => {
  const [reportFilters, setReportFilters] = useState<Array<{field: string; value: string}>>(defaultFilters);
  const [searchQuery, setSearchQuery] = useState('');
  const [refreshKey, setRefreshKey] = useState(0);
  const [loadingSections, setLoadingSections] = useState<Record<number, boolean>>({});

  const hasFilters = Object.keys(allFilters).length > 0;
  const isAnyLoading = Object.values(loadingSections).some(Boolean);

  const handleSectionLoading = useCallback((sectionId: number, loading: boolean) => {
    setLoadingSections((prev) => ({...prev, [sectionId]: loading}));
  }, []);

  return (
    <>
      <Head title={report.name} />
      <HStack w={'full'} spacing={4}>
        <Heading size={'md'}>{report.name}</Heading>
        <Spacer />
        <Button as={Link} href={`/arbol/reports/${report.id}/edit`} size={'xs'}>
          Edit Report
        </Button>
      </HStack>
      <Box mt={4}>
        <Text fontSize={'sm'}>{report.description}</Text>
      </Box>

      {hasFilters && (
        <Box mt={4}>
          <ReportFilterBar
            allFilters={allFilters}
            selectedFilters={reportFilters}
            onFiltersChange={setReportFilters}
            searchQuery={searchQuery}
            onSearchChange={setSearchQuery}
            onRefresh={() => setRefreshKey((k) => k + 1)}
            isLoading={isAnyLoading}
          />
        </Box>
      )}

      <Box mt={hasFilters ? 4 : 8}>
        <VStack spacing={4}>
          {report.sections.map((section) => (
            <ReportSection
              key={section.id}
              section={section}
              report={report}
              reportFilters={reportFilters}
              searchQuery={searchQuery}
              refreshKey={refreshKey}
              onLoadingChange={handleSectionLoading}
              hasFilterBar={hasFilters}
            />
          ))}

          <Center
            as={Link}
            href={`/arbol/reports/${report.id}/sections/create`}
            w={'full'}
            p={8}
            border={'dashed 1px'}
            borderColor={'gray.200'}
            borderRadius={'md'}
            color={'gray.400'}
            bg={'gray.50'}
            _hover={{bg: 'gray.100', color: 'gray.500'}}
          >
            <Box>
              <AddIcon w={24} h={24} color={'gray.500'} />
            </Box>
            <Heading size={'md'} ml={4}>
              Add New Section
            </Heading>
          </Center>
        </VStack>
      </Box>
    </>
  );
};

Show.layout = (page: React.ReactElement) => <Layout children={page} />;

export default Show;
