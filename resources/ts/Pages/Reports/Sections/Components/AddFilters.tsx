import {
  AddIcon,
  Button,
  CloseIcon,
  FormControl,
  FormLabel,
  IconButton,
  Popover,
  PopoverArrow,
  PopoverBody,
  PopoverCloseButton,
  PopoverContent,
  PopoverHeader,
  PopoverTrigger,
  Select,
  Text,
  Wrap,
  WrapItem,
} from '@calvient/decal';
import {FC, useState} from 'react';

interface AddFiltersProps {
  allFilters: Record<string, string[]>;
  selectedFilters: Array<{field: string; value: string}>;
  onFiltersChange: (filters: Array<{field: string; value: string}>) => void;
}

const AddFilters: FC<AddFiltersProps> = ({allFilters, selectedFilters, onFiltersChange}) => {
  const [popoverOpen, setPopoverOpen] = useState(false);
  const [filterToAdd, setFilterToAdd] = useState<string | undefined>();
  const [filterValueToAdd, setFilterValueToAdd] = useState<string | undefined>();

  const handleAddFilter = (field: string, value: string) => {
    onFiltersChange([...selectedFilters, {field, value}]);
  };

  const handleRemoveFilter = (index: number) => {
    onFiltersChange(selectedFilters.filter((_, i) => i !== index));
  };

  return (
    <FormControl flex={2}>
      <FormLabel>Filters:</FormLabel>

      <Wrap>
        {selectedFilters.map((filter, index) => (
          <WrapItem key={index} px={4} py={2} bgColor={'gray.100'} borderRadius={'full'}>
            <Text fontSize={'sm'}>{filter.value}</Text>
            <IconButton
              aria-label={'Remove'}
              icon={<CloseIcon />}
              size={'xs'}
              borderRadius={'full'}
              ml={2}
              colorScheme={'red'}
              onClick={() => handleRemoveFilter(index)}
            />
          </WrapItem>
        ))}
        <WrapItem>
          <Popover
            placement={'top-start'}
            closeOnBlur={false}
            returnFocusOnClose={false}
            isOpen={popoverOpen}
            onClose={() => setPopoverOpen(false)}
            isLazy
          >
            <PopoverTrigger>
              <Button
                leftIcon={<AddIcon />}
                size={'md'}
                borderRadius={'full'}
                onClick={() => setPopoverOpen(true)}
              >
                Add Filter
              </Button>
            </PopoverTrigger>
            <PopoverContent boxShadow={'lg'}>
              <PopoverArrow />
              <PopoverCloseButton />
              <PopoverHeader>Add a Filter</PopoverHeader>
              <PopoverBody py={4}>
                <Select
                  placeholder={'Select a filter'}
                  onChange={(e) => setFilterToAdd(e.target.value)}
                >
                  {Object.keys(allFilters).map((filter) => (
                    <option key={filter} value={filter}>
                      {filter}
                    </option>
                  ))}
                </Select>
                <Select
                  mt={4}
                  isDisabled={!filterToAdd}
                  placeholder={'Select a value'}
                  value={filterValueToAdd}
                  onChange={(e) => setFilterValueToAdd(e.target.value)}
                >
                  {filterToAdd &&
                    allFilters[filterToAdd].map((value) => (
                      <option key={value} value={value}>
                        {value}
                      </option>
                    ))}
                </Select>
                <Button
                  isDisabled={!filterToAdd || !filterValueToAdd}
                  w={'full'}
                  mt={4}
                  colorScheme={'blue'}
                  onClick={() => {
                    setFilterToAdd(undefined);
                    setFilterValueToAdd(undefined);
                    handleAddFilter(filterToAdd ?? '', filterValueToAdd ?? '');
                    setPopoverOpen(false);
                  }}
                >
                  Add Filter
                </Button>
              </PopoverBody>
            </PopoverContent>
          </Popover>
        </WrapItem>
      </Wrap>
    </FormControl>
  );
};

export default AddFilters;
