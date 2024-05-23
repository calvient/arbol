import Layout from '../../Components/Layout.tsx';
import {Head, Link} from '@inertiajs/react';
import React from 'react';
import {User} from '../../Types/User.ts';
import {Report} from '../../Types/Report.ts';
import {AddIcon, Box, Center, HStack, Heading, Spacer, Text, VStack, Button} from '@calvient/decal';
import ReportSection from './Sections/Components/ReportSection.tsx';

interface ShowProps {
  report: Report;
  users: User[];
}

const Show = ({report}: ShowProps) => {
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

      <Box mt={8}>
        <VStack spacing={4}>
          {report.sections.map((section) => (
            <ReportSection key={section.id} section={section} report={report} />
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
