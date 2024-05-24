import {createInertiaApp} from '@inertiajs/react';
import {createRoot} from 'react-dom/client';

createInertiaApp({
  resolve: (name) => {
    // @ts-expect-error -- this is required by Inertia
    const pages = import.meta.glob('./Pages/**/*.tsx', {eager: true});
    return pages[`./Pages/${name}.tsx`];
  },
  setup({el, App, props}) {
    createRoot(el).render(<App {...props} />);
  },
});
