import '@testing-library/jest-dom/vitest';
import {cleanup} from '@testing-library/react';
import {afterEach} from 'vitest';

// Recharts sizes itself from a ResizeObserver, which jsdom does not implement.
// Report a fixed box so <ResponsiveContainer> actually renders its chart.
const CHART_WIDTH = 800;
const CHART_HEIGHT = 400;

class FixedSizeResizeObserver implements ResizeObserver {
  constructor(private readonly callback: ResizeObserverCallback) {}

  observe(target: Element) {
    const contentRect = {
      width: CHART_WIDTH,
      height: CHART_HEIGHT,
      top: 0,
      left: 0,
      bottom: CHART_HEIGHT,
      right: CHART_WIDTH,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    };

    this.callback(
      [{target, contentRect, borderBoxSize: [], contentBoxSize: [], devicePixelContentBoxSize: []}],
      this,
    );
  }

  unobserve() {}

  disconnect() {}
}

globalThis.ResizeObserver = FixedSizeResizeObserver;

Object.defineProperty(HTMLElement.prototype, 'clientWidth', {
  configurable: true,
  value: CHART_WIDTH,
});
Object.defineProperty(HTMLElement.prototype, 'clientHeight', {
  configurable: true,
  value: CHART_HEIGHT,
});

afterEach(cleanup);
