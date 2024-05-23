import {As, Box, Center, HStack, Icon} from '@calvient/decal';
import React from 'react';

interface BoxSelectProps {
  value?: string;
  options: {label: string; icon: As; value: string}[];
  onSelect: (value: string) => void;
}

const BoxSelect: React.FC<BoxSelectProps> = ({value, options, onSelect}) => {
  return (
    <HStack>
      {options.map((option) => (
        <Box
          key={option.value}
          role={'button'}
          flex={1}
          p={8}
          bg={value === option.value ? 'blue.50' : 'white'}
          border={'solid 1px'}
          borderColor={value === option.value ? 'blue.200' : 'gray.200'}
          borderRadius={'md'}
          color={value === option.value ? 'blue.500' : 'gray.300'}
          _hover={{
            color: 'gray.500',
            borderColor: 'gray.300',
            bg: 'gray.50',
          }}
          onClick={() => onSelect(option.value)}
        >
          <Center>
            <Icon as={option.icon} boxSize={48} />
          </Center>
          <Center color={value === option.value ? 'blue.500' : 'gray.500'}>{option.label}</Center>
        </Box>
      ))}
    </HStack>
  );
};

export default BoxSelect;
