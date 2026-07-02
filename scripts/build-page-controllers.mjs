import fs from 'node:fs';
import path from 'node:path';
import { build } from 'vite';

const root = process.cwd();
const pagesDir = path.join(root, 'frontend/pages');

function pageControllerName(fileName) {
  const stem = fileName.replace(/\.ts$/, '').replace(/-page$/, '');
  return `Erebus${stem.split('-').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join('')}Page`;
}

function pageOutputFileName(fileName) {
  const stem = fileName.replace(/\.ts$/, '').replace(/-page$/, '');
  return `${stem.replace(/-/g, '_')}_page.js`;
}

const pageEntries = fs.readdirSync(pagesDir)
  .filter((fileName) => fileName.endsWith('-page.ts'))
  .sort()
  .map((fileName) => ({
    entry: path.join('frontend/pages', fileName),
    outDir: 'public/assets/js/pages',
    fileName: pageOutputFileName(fileName),
    name: pageControllerName(fileName),
  }));

const moduleEntries = [
  {
    entry: 'frontend/modules/samples/samples-query-builder.ts',
    outDir: 'public/assets/js/modules/samples',
    fileName: 'samples_query_builder.js',
    name: 'ErebusSamplesQueryBuilder',
  },
  {
    entry: 'frontend/modules/samples/samples-table-renderer.ts',
    outDir: 'public/assets/js/modules/samples',
    fileName: 'samples_table_renderer.js',
    name: 'ErebusSamplesTableRenderer',
  },
  {
    entry: 'frontend/modules/sample-detail/sample-detail-formatters.ts',
    outDir: 'public/assets/js/modules/sample_detail',
    fileName: 'sample_detail_formatters.js',
    name: 'ErebusSampleDetailFormatters',
  },
  {
    entry: 'frontend/modules/sample-detail/sample-summary-renderer.ts',
    outDir: 'public/assets/js/modules/sample_detail',
    fileName: 'sample_summary_renderer.js',
    name: 'ErebusSampleSummaryRenderer',
  },
  {
    entry: 'frontend/modules/sample-detail/sample-detail-surface.ts',
    outDir: 'public/assets/js/modules/sample_detail',
    fileName: 'sample_detail_surface.js',
    name: 'ErebusSampleDetailSurface',
  },
  {
    entry: 'frontend/modules/sample-detail/sample-permissions-csv.ts',
    outDir: 'public/assets/js/modules/sample_detail',
    fileName: 'sample_permissions_csv.js',
    name: 'ErebusSamplePermissionsCsv',
  },
  {
    entry: 'frontend/modules/sample-detail/sample-permissions-renderers.ts',
    outDir: 'public/assets/js/modules/sample_detail',
    fileName: 'sample_permissions_renderers.js',
    name: 'ErebusSamplePermissionsRenderers',
  },
  {
    entry: 'frontend/modules/sample-detail/sample-permissions-controller.ts',
    outDir: 'public/assets/js/modules/sample_detail',
    fileName: 'sample_permissions_controller.js',
    name: 'ErebusSamplePermissionsController',
  },
];

const entries = [...pageEntries, ...moduleEntries];

for (const item of entries) {
  await build({
    configFile: false,
    publicDir: false,
    logLevel: 'error',
    build: {
      emptyOutDir: false,
      outDir: path.resolve(root, item.outDir),
      sourcemap: false,
      minify: false,
      lib: {
        entry: path.resolve(root, item.entry),
        formats: ['iife'],
        name: item.name,
        fileName: () => item.fileName,
      },
      rollupOptions: {
        output: {
          extend: true,
        },
      },
    },
  });
}

console.log(`Built ${entries.length} frontend controllers (${pageEntries.length} pages, ${moduleEntries.length} modules).`);
