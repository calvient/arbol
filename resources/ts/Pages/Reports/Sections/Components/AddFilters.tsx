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
  tableMode?: boolean;
}

const AddFilters: FC<AddFiltersProps> = ({allFilters, selectedFilters, onFiltersChange, tableMode = false}) => {
  const [popoverOpen, setPopoverOpen] = useState(false);
  const [filterToAdd, setFilterToAdd] = useState<string | undefined>();
  const [filterValueToAdd, setFilterValueToAdd] = useState<string | undefined>();

  const handleAddFilter = (field: string, value: string) => {
    onFiltersChange([...selectedFilters, {field, value}]);
  };

  const handleRemoveFilter = (index: number) => {
    onFiltersChange(selectedFilters.filter((_, i) => i !== index));
  };

  // In table mode, only show filter groups not already added
  const availableFilterGroups = tableMode
    ? Object.keys(allFilters).filter(
        (group) => !selectedFilters.some((f) => f.field === group),
      )
    : Object.keys(allFilters);

  // In table mode, the add button only requires a group (value is optional)
  const canAdd = tableMode ? !!filterToAdd : !!filterToAdd && !!filterValueToAdd;

  return (
    <FormControl flex={2}>
      <FormLabel>{tableMode ? 'Report Filters:' : 'Filters:'}</FormLabel>
      {tableMode && (
        <Text fontSize='xs' color='gray.500' mb={2}>
          Choose which filters appear on the report. Optionally set a default value.
        </Text>
      )}

      <Wrap>
        {selectedFilters.map((filter, index) => (
          <WrapItem key={index} px={4} py={2} bgColor={'gray.100'} borderRadius={'full'}>
            <Text fontSize={'sm'}>
              {tableMode
                ? filter.value
                  ? `${filter.field}: ${filter.value}`
                  : filter.field
                : filter.value}
            </Text>
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
                isDisabled={tableMode && availableFilterGroups.length === 0}
              >
                Add Filter
              </Button>
            </PopoverTrigger>
            <PopoverContent boxShadow={'lg'}>
              <PopoverArrow />
              <PopoverCloseButton />
              <PopoverHeader>{tableMode ? 'Add a Report Filter' : 'Add a Filter'}</PopoverHeader>
              <PopoverBody py={4}>
                <Select
                  placeholder={'Select a filter'}
                  onChange={(e) => {
                    setFilterToAdd(e.target.value);
                    setFilterValueToAdd(undefined);
                  }}
                >
                  {availableFilterGroups.map((filter) => (
                    <option key={filter} value={filter}>
                      {filter}
                    </option>
                  ))}
                </Select>
                <Select
                  mt={4}
                  isDisabled={!filterToAdd}
                  placeholder={tableMode ? 'No default (optional)' : 'Select a value'}
                  value={filterValueToAdd ?? ''}
                  onChange={(e) => setFilterValueToAdd(e.target.value || undefined)}
                >
                  {filterToAdd &&
                    allFilters[filterToAdd].map((value) => (
                      <option key={value} value={value}>
                        {value}
                      </option>
                    ))}
                </Select>
                <Button
                  isDisabled={!canAdd}
                  w={'full'}
                  mt={4}
                  colorScheme={'blue'}
                  onClick={() => {
                    handleAddFilter(filterToAdd ?? '', filterValueToAdd ?? '');
                    setFilterToAdd(undefined);
                    setFilterValueToAdd(undefined);
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
