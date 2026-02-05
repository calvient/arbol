export type Section = {
  id?: number;
  name: string;
  description?: string | null;
  created_at?: string;
  updated_at?: string;
  series: string;
  slice: string;
  xaxis_slice?: string;
  aggregator?: string;
  percentage_mode?: string | null;
  filters: Array<{field: string; value: string}>;
  format: string;
  sequence: number;
};
