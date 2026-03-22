import {FC, ReactNode} from 'react';
import {Box, ChakraProvider, theme} from '@calvient/decal';
import {QueryClient, QueryClientProvider} from 'react-query';

interface MinimalLayoutProps {
  children: ReactNode;
}

const queryClient = new QueryClient();

const MinimalLayout: FC<MinimalLayoutProps> = ({children}) => {
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
              {children}
            </Box>
          </Box>
        </Box>
      </ChakraProvider>
    </QueryClientProvider>
  );
};

export default MinimalLayout;
