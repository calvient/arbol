import {FC} from 'react';
import {
  Box,
  Button,
  Checkbox,
  HStack,
  Input,
  InputGroup,
  InputLeftElement,
  Popover,
  PopoverBody,
  PopoverContent,
  PopoverTrigger,
  RepeatIcon,
  SearchIcon,
  Text,
  VStack,
  Wrap,
  WrapItem,
  ChevronDownIcon,
  Tag,
  TagLabel,
  TagCloseButton,
} from '@calvient/decal';

interface ReportFilterBarProps {
  allFilters: Record<string, string[]>;
  selectedFilters: Array<{field: string; value: string}>;
  onFiltersChange: (filters: Array<{field: string; value: string}>) => void;
  searchQuery: string;
  onSearchChange: (query: string) => void;
  onRefresh: () => void;
  isLoading?: boolean;
}

const ReportFilterBar: FC<ReportFilterBarProps> = ({
  allFilters,
  selectedFilters,
  onFiltersChange,
  searchQuery,
  onSearchChange,
  onRefresh,
  isLoading = false,
}) => {
  const filterGroups = Object.keys(allFilters);

  const getSelectedValuesForGroup = (group: string): string[] => {
    return selectedFilters.filter((f) => f.field === group).map((f) => f.value);
  };

  const toggleFilterValue = (group: string, value: string) => {
    const exists = selectedFilters.some((f) => f.field === group && f.value === value);
    if (exists) {
      onFiltersChange(selectedFilters.filter((f) => !(f.field === group && f.value === value)));
    } else {
      onFiltersChange([...selectedFilters, {field: group, value}]);
    }
  };

  const clearGroupFilters = (group: string) => {
    onFiltersChange(selectedFilters.filter((f) => f.field !== group));
  };

  const clearAllFilters = () => {
    onFiltersChange([]);
    onSearchChange('');
  };

  const hasActiveFilters = selectedFilters.length > 0 || searchQuery.length > 0;

  return (
    <Box
      w='full'
      p={4}
      bg='white'
      border='solid 1px'
      borderColor='gray.200'
      borderRadius='md'
    >
      {/* Filters + search/refresh on same row, filters wrap as needed */}
      <HStack spacing={3} w='full' alignItems='flex-start'>
        <Wrap spacing={2} align='center' flex={1}>
          <WrapItem>
            <Text fontSize='sm' fontWeight='medium' color='gray.600' lineHeight='32px'>
              Filters:
            </Text>
          </WrapItem>
        {filterGroups.map((group) => {
          const selectedValues = getSelectedValuesForGroup(group);
          const isActive = selectedValues.length > 0;

          return (
            <WrapItem key={group}>
              <Popover placement='bottom-start' isLazy>
                {({onClose}) => (
                  <>
                    <PopoverTrigger>
                      <Button
                        size='sm'
                        variant='outline'
                        borderRadius='full'
                        borderColor={isActive ? 'blue.300' : 'gray.200'}
                        bg={isActive ? 'blue.50' : 'white'}
                        color={isActive ? 'blue.700' : 'gray.700'}
                        fontWeight='normal'
                        rightIcon={<ChevronDownIcon />}
                        _hover={{bg: isActive ? 'blue.100' : 'gray.50'}}
                      >
                        {group}
                        {isActive && (
                          <Tag size='sm' ml={1} borderRadius='full' colorScheme='blue' variant='solid'>
                            <TagLabel>{selectedValues.length}</TagLabel>
                          </Tag>
                        )}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent minW='220px' maxH='300px' overflowY='auto' boxShadow='lg'>
                      <PopoverBody py={2}>
                        <VStack align='stretch' spacing={1}>
                          {isActive && (
                            <Button
                              size='xs'
                              variant='ghost'
                              colorScheme='red'
                              alignSelf='flex-end'
                              mb={1}
                              onClick={() => {
                                clearGroupFilters(group);
                                onClose();
                              }}
                            >
                              Clear
                            </Button>
                          )}
                          {allFilters[group].map((value) => (
                            <Checkbox
                              key={value}
                              isChecked={selectedValues.includes(value)}
                              onChange={() => toggleFilterValue(group, value)}
                              px={2}
                              py={1}
                              borderRadius='md'
                              _hover={{bg: 'gray.50'}}
                            >
                              <Text fontSize='sm'>{value}</Text>
                            </Checkbox>
                          ))}
                        </VStack>
                      </PopoverBody>
                    </PopoverContent>
                  </>
                )}
              </Popover>
            </WrapItem>
          );
        })}

        {hasActiveFilters && (
          <WrapItem>
            <Button
              size='sm'
              variant='ghost'
              colorScheme='red'
              onClick={clearAllFilters}
            >
              Clear all
            </Button>
          </WrapItem>
        )}
        </Wrap>
        <HStack spacing={3} flexShrink={0}>
          <InputGroup size='sm' maxW='250px'>
            <InputLeftElement pointerEvents='none'>
              <SearchIcon color='gray.400' />
            </InputLeftElement>
            <Input
              placeholder='Search...'
              borderRadius='full'
              value={searchQuery}
              onChange={(e) => onSearchChange(e.target.value)}
            />
          </InputGroup>
          <Button
            size='sm'
            colorScheme='blue'
            leftIcon={<RepeatIcon />}
            onClick={onRefresh}
            flexShrink={0}
            isDisabled={isLoading}
            isLoading={isLoading}
            loadingText='Loading'
          >
            Refresh
          </Button>
        </HStack>
      </HStack>

      {selectedFilters.length > 0 && (
        <Wrap mt={3} spacing={2}>
          {selectedFilters.map((filter, index) => (
            <WrapItem key={`${filter.field}-${filter.value}-${index}`}>
              <Tag size='sm' borderRadius='full' variant='subtle' colorScheme='blue'>
                <TagLabel>
                  {filter.field}: {filter.value}
                </TagLabel>
                <TagCloseButton
                  onClick={() =>
                    onFiltersChange(selectedFilters.filter((_, i) => i !== index))
                  }
                />
              </Tag>
            </WrapItem>
          ))}
        </Wrap>
      )}
    </Box>
  );
};

export default ReportFilterBar;
