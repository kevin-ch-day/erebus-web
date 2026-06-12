import path from 'node:path';
import { build } from 'vite';

const root = process.cwd();

const entries = [
  {
    entry: 'frontend/pages/landing-page.ts',
    outDir: 'public/assets/js/pages',
    fileName: 'landing_page.js',
    name: 'ErebusLandingPage',
  },
  {
    entry: 'frontend/pages/stack-audit-page.ts',
    outDir: 'public/assets/js/pages',
    fileName: 'stack_audit_page.js',
    name: 'ErebusStackAuditPage',
  },
  {
    entry: 'frontend/pages/family-taxonomy-queue-page.ts',
    outDir: 'public/assets/js/pages',
    fileName: 'family_taxonomy_queue_page.js',
    name: 'ErebusFamilyTaxonomyQueuePage',
  },
  {
    entry: 'frontend/pages/family-taxonomy-repair-planning-page.ts',
    outDir: 'public/assets/js/pages',
    fileName: 'family_taxonomy_repair_planning_page.js',
    name: 'ErebusFamilyTaxonomyRepairPlanningPage',
  },
  {
    entry: 'frontend/pages/family-taxonomy-check-page.ts',
    outDir: 'public/assets/js/pages',
    fileName: 'family_taxonomy_check_page.js',
    name: 'ErebusFamilyTaxonomyCheckPage',
  },
  {
    entry: 'frontend/pages/health-page.ts',
    outDir: 'public/assets/js/pages',
    fileName: 'health_page.js',
    name: 'ErebusHealthPage',
  },
  {
    entry: 'frontend/pages/check-hash-page.ts',
    outDir: 'public/assets/js/pages',
    fileName: 'check_hash_page.js',
    name: 'ErebusCheckHashPage',
  },
  {
    entry: 'frontend/pages/samples-page.ts',
    outDir: 'public/assets/js/pages',
    fileName: 'samples_page.js',
    name: 'ErebusSamplesPage',
  },
  {
    entry: 'frontend/pages/submit-artifact-page.ts',
    outDir: 'public/assets/js/pages',
    fileName: 'submit_artifact_page.js',
    name: 'ErebusSubmitArtifactPage',
  },
  {
    entry: 'frontend/pages/sample-detail-page.ts',
    outDir: 'public/assets/js/pages',
    fileName: 'sample_detail_page.js',
    name: 'ErebusSampleDetailPage',
  },
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
