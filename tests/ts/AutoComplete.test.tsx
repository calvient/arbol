import {ChakraProvider} from '@chakra-ui/react';
import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeAll, beforeEach, describe, expect, it, vi} from 'vitest';
import AutoComplete from '../../resources/ts/Components/AutoComplete';

const scrollIntoView = vi.fn();

const options = Array.from({length: 12}, (_, index) => ({
  label: `User ${index + 1}`,
  value: index + 1,
}));

const renderAutoComplete = (values: Array<string | number>, onChange = vi.fn()) => {
  render(
    <ChakraProvider>
      <AutoComplete options={options} values={values} onChange={onChange} />
    </ChakraProvider>,
  );

  return {onChange};
};

describe('AutoComplete', () => {
  beforeAll(() => {
    Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
      configurable: true,
      value: scrollIntoView,
    });
  });

  beforeEach(() => {
    scrollIntoView.mockClear();
  });

  it('filters options and preserves numeric values when selecting', async () => {
    const user = userEvent.setup();
    const {onChange} = renderAutoComplete([]);
    const input = screen.getByRole('combobox');

    await user.type(input, 'User 10');
    await user.click(screen.getByRole('option', {name: 'User 10'}));

    expect(onChange).toHaveBeenCalledWith([10]);
    expect(input).toHaveValue('');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('renders selected values as removable tags', async () => {
    const user = userEvent.setup();
    const {onChange} = renderAutoComplete([1]);

    expect(screen.getByText('User 1')).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: /close/i}));

    expect(onChange).toHaveBeenCalledWith([]);
  });

  it('announces and scrolls the active keyboard option into view', async () => {
    const user = userEvent.setup();
    const {onChange} = renderAutoComplete([]);
    const input = screen.getByRole('combobox');

    await user.click(input);
    scrollIntoView.mockClear();
    await user.keyboard('{ArrowDown}');

    const activeOptionId = input.getAttribute('aria-activedescendant');
    const activeOption = document.getElementById(activeOptionId ?? '');

    expect(activeOption).toHaveTextContent('User 2');
    expect(scrollIntoView).toHaveBeenCalledWith({block: 'nearest'});

    await user.keyboard('{Enter}');
    expect(onChange).toHaveBeenCalledWith([2]);
  });

  it('highlights the first option when reopening the list after a selection', async () => {
    const user = userEvent.setup();
    const {onChange} = renderAutoComplete([]);
    const input = screen.getByRole('combobox');

    await user.click(input);
    await user.click(screen.getByRole('option', {name: 'User 1'}));
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();

    // Reopening must highlight the first option rather than skipping past it.
    await user.keyboard('{ArrowDown}');
    const activeOption = document.getElementById(input.getAttribute('aria-activedescendant') ?? '');

    expect(activeOption).toHaveTextContent('User 1');
    expect(activeOption).toHaveAttribute('aria-selected', 'true');

    await user.keyboard('{Enter}');
    expect(onChange).toHaveBeenLastCalledWith([1]);
  });

  it('shows an explicit empty state for unmatched searches', async () => {
    const user = userEvent.setup();

    renderAutoComplete([]);
    await user.type(screen.getByRole('combobox'), 'Missing user');

    expect(screen.getByText('No matching options')).toBeInTheDocument();
  });
});
