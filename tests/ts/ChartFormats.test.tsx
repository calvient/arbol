import {render, screen, waitFor} from '@testing-library/react';
import {describe, expect, it} from 'vitest';
import BarFormat from '../../resources/ts/Pages/Reports/Sections/Components/Formats/BarFormat';
import LineFormat from '../../resources/ts/Pages/Reports/Sections/Components/Formats/LineFormat';
import PieFormat from '../../resources/ts/Pages/Reports/Sections/Components/Formats/PieFormat';

// Guards the Recharts 3 upgrade: these assert real SVG output, not just "did not throw".
// Series shapes only appear once Recharts' enter animation finishes, hence the waits.
const ANIMATED = {timeout: 5000};

const singleSeries = [
  {name: 'January', value: 1000},
  {name: 'February', value: 2500.5},
];

const multiSeries = [
  {name: 'January', East: 10, West: 20},
  {name: 'February', East: 30, West: 40},
];

const truncated: Array<Record<string, string | number>> = [
  {name: 'January', value: 10},
  {_meta: 'truncated', _total: 50, _shown: 1},
];

const tickTexts = (container: HTMLElement) =>
  [...container.querySelectorAll('.recharts-cartesian-axis-tick-value')].map(
    (tick) => tick.textContent ?? '',
  );

describe('BarFormat', () => {
  it('renders a bar per series and labels the category axis', async () => {
    const {container} = render(<BarFormat data={multiSeries} />);

    await waitFor(
      () => expect(container.querySelectorAll('.recharts-rectangle')).toHaveLength(4),
      ANIMATED,
    );
    expect(container.querySelectorAll('.recharts-bar')).toHaveLength(2);
    expect(container.querySelector('.recharts-legend-wrapper')).toBeInTheDocument();
    expect(tickTexts(container)).toEqual(expect.arrayContaining(['January', 'February']));
  });

  it('omits the legend for a single series and formats value ticks', async () => {
    const {container} = render(<BarFormat data={singleSeries} />);

    await waitFor(
      () => expect(container.querySelectorAll('.recharts-rectangle')).toHaveLength(2),
      ANIMATED,
    );
    expect(container.querySelectorAll('.recharts-bar')).toHaveLength(1);
    expect(container.querySelector('.recharts-legend-wrapper')).not.toBeInTheDocument();
    // Thousands separator and two decimals, without pinning Recharts' tick algorithm.
    expect(tickTexts(container).some((tick) => /^\d{1,3},\d{3}\.\d{2}$/.test(tick))).toBe(true);
  });

  it('suffixes value ticks with a percent sign in percentage mode', async () => {
    const {container} = render(<BarFormat data={[{name: 'January', value: 50}]} isPercentage />);

    // The [0, 100] domain makes these ticks deterministic.
    await waitFor(() =>
      expect(tickTexts(container)).toEqual(
        expect.arrayContaining(['0.00%', '25.00%', '50.00%', '75.00%', '100.00%']),
      ),
    );
  });

  it('renders without crashing when the series is empty', () => {
    const {container} = render(<BarFormat data={[]} />);

    expect(container.querySelector('.recharts-wrapper')).toBeInTheDocument();
    expect(container.querySelectorAll('.recharts-bar')).toHaveLength(0);
  });

  it('surfaces a truncation warning when the backend trimmed groups', () => {
    render(<BarFormat data={truncated} />);

    expect(screen.getByText(/Showing 1 of 50 groups/)).toBeInTheDocument();
  });
});

describe('LineFormat', () => {
  it('renders a line per series', async () => {
    const {container} = render(<LineFormat data={multiSeries} />);

    await waitFor(
      () => expect(container.querySelectorAll('.recharts-line-curve')).toHaveLength(2),
      ANIMATED,
    );
    expect(container.querySelectorAll('.recharts-line')).toHaveLength(2);
    expect(tickTexts(container)).toEqual(expect.arrayContaining(['January', 'February']));
  });

  it('renders without crashing when the series is empty', () => {
    const {container} = render(<LineFormat data={[]} />);

    expect(container.querySelector('.recharts-wrapper')).toBeInTheDocument();
    expect(container.querySelectorAll('.recharts-line')).toHaveLength(0);
  });
});

describe('PieFormat', () => {
  it('renders a slice per datum with a formatted label', async () => {
    const {container} = render(<PieFormat data={singleSeries} />);

    await waitFor(
      () => expect(container.querySelectorAll('.recharts-pie-sector')).toHaveLength(2),
      ANIMATED,
    );
    // Labels land a frame after the sectors.
    expect(await screen.findByText('January - 1,000.00 (29%)', {}, ANIMATED)).toBeInTheDocument();
    expect(screen.getByText('February - 2,500.50 (71%)')).toBeInTheDocument();
  });

  it('renders without crashing when the series is empty', () => {
    const {container} = render(<PieFormat data={[]} />);

    expect(container.querySelector('.recharts-wrapper')).toBeInTheDocument();
    expect(container.querySelectorAll('.recharts-pie-sector')).toHaveLength(0);
  });
});
