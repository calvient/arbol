import {createInertiaApp, type ResolvedComponent} from '@inertiajs/react';
import {createRoot} from 'react-dom/client';

const pages = import.meta.glob<{default: ResolvedComponent}>('./Pages/**/*.tsx', {eager: true});

createInertiaApp({
  resolve: (name) => {
    const page = pages[`./Pages/${name}.tsx`];

    if (!page) {
      throw new Error(`Page not found: ${name}`);
    }

    return page;
  },
  setup({el, App, props}) {
    createRoot(el).render(<App {...props} />);
  },
});
