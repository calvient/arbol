import {
  HStack,
  Button,
  Table,
  Text,
  Thead,
  Th,
  Tbody,
  Tr,
  Td,
  Heading,
  Spacer,
} from '@calvient/decal';
import Layout from '../../Components/Layout.tsx';
import React, {FC} from 'react';
import {Head, Link} from '@inertiajs/react';
import {Report} from '../../Types/Report.ts';

interface ReportsProps {
  reports: Report[];
}

const Index: FC<ReportsProps> = ({reports}) => {
  return (
    <>
      <Head title={'My Reports'} />
      <HStack w={'full'}>
        <Heading size={'md'} color={'gray.700'}>
          Your Reports
        </Heading>
        <Spacer />
        <Button as={Link} colorScheme={'blue'} size={'sm'} href={'/arbol/reports/create'}>
          Create Report
        </Button>
      </HStack>
      <Table mt={8}>
        <Thead>
          <Tr>
            <Th>Report Name</Th>
            <Th>Description</Th>
            <Th>Author</Th>
          </Tr>
        </Thead>
        <Tbody>
          {reports.map((report) => (
            <Tr key={report.id}>
              <Td>
                <Button
                  variant={'link'}
                  as={Link}
                  href={`/arbol/reports/${report.id}`}
                  colorScheme={'blue'}
                >
                  {report.name}
                </Button>
              </Td>
              <Td>
                <Text noOfLines={1}>{report.description}</Text>
              </Td>
              <Td>{report.author?.name}</Td>
            </Tr>
          ))}
        </Tbody>
      </Table>
    </>
  );
};

// @ts-expect-error -- Inertia wants this
Index.layout = (page: React.ReactElement) => <Layout children={page} />;

export default Index;
