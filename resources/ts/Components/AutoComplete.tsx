import {Box, Input, Tag, TagCloseButton, TagLabel, Text, Wrap} from '@chakra-ui/react';
import {KeyboardEvent, useEffect, useId, useMemo, useRef, useState} from 'react';

type Option = {label: string; value: string | number};

interface AutoCompleteProps {
  options: Option[];
  values: Array<string | number>;
  onChange: (values: Array<string | number>) => void;
}

const AutoComplete = ({options, values, onChange}: AutoCompleteProps) => {
  const [query, setQuery] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const [focusedIndex, setFocusedIndex] = useState(0);
  const listboxId = useId();
  const optionRefs = useRef<Array<HTMLElement | null>>([]);
  const selectedOptions = useMemo(
    () => options.filter((option) => values.includes(option.value)),
    [options, values],
  );
  const filteredOptions = useMemo(
    () =>
      options.filter(
        (option) =>
          !values.includes(option.value) &&
          option.label.toLocaleLowerCase().includes(query.toLocaleLowerCase()),
      ),
    [options, query, values],
  );
  const activeOption = filteredOptions[focusedIndex];
  const activeOptionId = activeOption ? `${listboxId}-option-${focusedIndex}` : undefined;

  useEffect(() => {
    setFocusedIndex((index) => Math.min(index, Math.max(filteredOptions.length - 1, 0)));
  }, [filteredOptions.length]);

  useEffect(() => {
    if (!isOpen || !activeOption) return;

    optionRefs.current[focusedIndex]?.scrollIntoView({block: 'nearest'});
  }, [activeOption, focusedIndex, isOpen]);

  const handleAddOption = (optionId: number | string) => {
    if (values.includes(optionId)) return;

    onChange([...values, optionId]);
    setQuery('');
    setIsOpen(false);
    setFocusedIndex(0);
  };

  const handleRemoveOption = (optionId: number | string) => {
    onChange(values.filter((value) => value !== optionId));
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Escape') {
      setIsOpen(false);

      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();

      // Reopening a closed list highlights the first option rather than skipping past it.
      if (!isOpen) {
        setIsOpen(true);
        setFocusedIndex(0);

        return;
      }

      if (filteredOptions.length > 0) {
        setFocusedIndex((index) => Math.min(index + 1, filteredOptions.length - 1));
      }

      return;
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();

      if (!isOpen) {
        setIsOpen(true);
        setFocusedIndex(0);

        return;
      }

      setFocusedIndex((index) => Math.max(index - 1, 0));

      return;
    }

    if (event.key === 'Enter' && isOpen && filteredOptions[focusedIndex]) {
      event.preventDefault();
      handleAddOption(filteredOptions[focusedIndex].value);
    }
  };

  return (
    <>
      <Box position='relative'>
        <Input
          aria-autocomplete='list'
          aria-activedescendant={isOpen ? activeOptionId : undefined}
          aria-controls={isOpen ? listboxId : undefined}
          aria-expanded={isOpen}
          aria-haspopup='listbox'
          autoFocus
          onChange={(event) => {
            setQuery(event.target.value);
            setFocusedIndex(0);
            setIsOpen(true);
          }}
          onFocus={() => setIsOpen(true)}
          onBlur={() => setIsOpen(false)}
          onKeyDown={handleKeyDown}
          placeholder='Search...'
          role='combobox'
          value={query}
          variant='filled'
        />
        {isOpen && (
          <Box
            id={listboxId}
            role='listbox'
            aria-multiselectable='true'
            position='absolute'
            zIndex='dropdown'
            w='full'
            mt={1}
            maxH='240px'
            overflowY='auto'
            borderWidth='1px'
            borderRadius='md'
            bg='white'
            boxShadow='md'
          >
            {filteredOptions.length === 0 ? (
              <Text px={3} py={2} color='gray.500'>
                No matching options
              </Text>
            ) : (
              filteredOptions.map((option, index) => (
                <Box
                  as='button'
                  type='button'
                  key={option.value}
                  id={`${listboxId}-option-${index}`}
                  role='option'
                  aria-selected={index === focusedIndex}
                  ref={(element) => {
                    optionRefs.current[index] = element;
                  }}
                  w='full'
                  px={3}
                  py={2}
                  textAlign='left'
                  textTransform='capitalize'
                  bg={index === focusedIndex ? 'gray.100' : undefined}
                  _hover={{bg: 'gray.100'}}
                  onMouseEnter={() => setFocusedIndex(index)}
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={() => handleAddOption(option.value)}
                >
                  {option.label}
                </Box>
              ))
            )}
          </Box>
        )}
      </Box>

      <Wrap mt={2}>
        {selectedOptions.map((selectedOption) => (
          <Tag
            key={selectedOption.value}
            size='sm'
            borderRadius='full'
            variant='solid'
            colorScheme='pink'
          >
            <TagLabel>{selectedOption.label}</TagLabel>
            <TagCloseButton onClick={() => handleRemoveOption(selectedOption.value)} />
          </Tag>
        ))}
      </Wrap>
    </>
  );
};

export default AutoComplete;
