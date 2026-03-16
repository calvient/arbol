/**
 * Embeddable Arbol components for use in the parent app.
 *
 * Import from the package (with optional alias in vite.config / tsconfig):
 *
 *   import { DataTableFromUrl, DataTableContainer } from 'calvient-arbol/embed';
 *
 * Or by path:
 *
 *   import { DataTableFromUrl } from 'vendor/calvient/arbol/resources/ts/Embeddable';
 */

export { default as DataTableFromUrl } from './DataTableFromUrl.tsx';
export type { DataTableFromUrlProps } from './DataTableFromUrl.tsx';

export { default as DataTableContainer } from '../Components/DataTableContainer.tsx';
export type {
  DataTableContainerProps,
  DataTableState,
  DataTableRow,
} from '../Components/DataTableContainer.tsx';
