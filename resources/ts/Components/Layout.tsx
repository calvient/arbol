import {FC, ReactNode} from 'react';
import {Box, Button, Center, ChakraProvider, HStack, theme, Text} from '@calvient/decal';
import {Link} from '@inertiajs/react';
import {QueryClient, QueryClientProvider} from 'react-query';

interface LayoutProps {
  children: ReactNode;
}

const queryClient = new QueryClient();

const Layout: FC<LayoutProps> = ({children}) => {
  const path = window.location.pathname;

  const pathParts = path.split('/').filter((part) => part !== '' && part !== 'arbol');

  const getPathTitle = (part: string) => {
    // convert snake and kebab case to title case
    let title = part.replace(/[-_]/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

    //capitalize first letter of each word
    title = title.replace(/\b\w/g, (char) => char.toUpperCase());

    return title;
  };

  return (
    <QueryClientProvider client={queryClient}>
      <ChakraProvider theme={theme}>
        <Box
          position={'fixed'}
          left={0}
          top={0}
          bottom={0}
          right={0}
          bg='gray.50'
          overflow={'auto'}
        >
          <Box w={'auto'}>
            <Box
              m={4}
              p={4}
              bgColor={'white'}
              border={'solid 1px'}
              borderColor={'gray.200'}
              borderRadius={'md'}
            >
              <HStack divider={<>/</>} spacing={4}>
                {pathParts.map((part, index) => {
                  const href = `/arbol/${pathParts.slice(0, index + 1).join('/')}`;

                  return (
                    <Button
                      key={part}
                      size={'sm'}
                      variant={'ghost'}
                      as={Link}
                      href={href}
                      colorScheme={pathParts.length === index + 1 ? 'blue' : 'gray'}
                    >
                      {getPathTitle(part)}
                    </Button>
                  );
                })}
              </HStack>
            </Box>
            <Box
              m={4}
              p={4}
              bgColor={'white'}
              border={'solid 1px'}
              borderColor={'gray.200'}
              borderRadius={'md'}
            >
              {children}
            </Box>
          </Box>
          <Box
            m={4}
            p={4}
            bgColor={'white'}
            border={'solid 1px'}
            borderColor={'gray.200'}
            borderRadius={'md'}
          >
            <Center>
              <Text fontSize={'sm'}>Version 0.0.4</Text>
            </Center>
          </Box>
        </Box>
      </ChakraProvider>
    </QueryClientProvider>
  );
};

export default Layout;
