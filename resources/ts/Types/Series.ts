export type Series = {
  name: string;
  description: string;
  filters: Record<string, string[]>;
  slices: string[];
};
