import {Box, Text} from '@calvient/decal';

interface TruncationWarningProps {
  total: number;
  shown: number;
}

const TruncationWarning = ({total, shown}: TruncationWarningProps) => {
  return (
    <Box
      mt={2}
      p={3}
      bg={'orange.50'}
      border={'1px solid'}
      borderColor={'orange.200'}
      borderRadius={'md'}
    >
      <Text fontSize={'sm'} color={'orange.800'}>
        Showing {shown.toLocaleString()} of {total.toLocaleString()} groups. Consider using a table
        format or adding filters to narrow the data.
      </Text>
    </Box>
  );
};

export default TruncationWarning;
