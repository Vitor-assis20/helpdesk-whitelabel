import { defineConfig } from 'vite';

/**
 * Bundle do widget embutível: um único arquivo IIFE, sem React, servido por
 * WidgetScriptController junto com a configuração da empresa.
 */
export default defineConfig({
    publicDir: false,
    build: {
        outDir: 'public/widget',
        emptyOutDir: true,
        lib: {
            entry: 'resources/widget/widget.ts',
            formats: ['iife'],
            name: 'HelpdeskWidget',
            fileName: () => 'widget.js',
        },
    },
});
