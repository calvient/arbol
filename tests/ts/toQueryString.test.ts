import {describe, expect, it} from 'vitest';
import {toQueryString} from '../../resources/ts/Utils/toQueryString';

describe('toQueryString', () => {
  it('omits null and undefined values', () => {
    expect(
      toQueryString({
        section_id: 90,
        slice: null,
        xaxis_slice: undefined,
        format: 'table',
        values: [1, null, undefined, 0, false],
      }),
    ).toBe('section_id=90&format=table&values[]=1&values[]=0&values[]=false');
  });

  it('preserves nested report filters while omitting nullable fields', () => {
    expect(
      toQueryString({
        filters: [
          {field: 'Remaining Balance', value: 'Greater Than 0'},
          {field: 'Balance Age', value: 'Older Than 90 Days'},
        ],
        slice: null,
        force_refresh: 0,
      }),
    ).toBe(
      'filters%5B0%5D%5Bfield%5D=Remaining%20Balance&filters%5B0%5D%5Bvalue%5D=Greater%20Than%200&filters%5B1%5D%5Bfield%5D=Balance%20Age&filters%5B1%5D%5Bvalue%5D=Older%20Than%2090%20Days&force_refresh=0',
    );
  });
});
