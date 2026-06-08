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
    entry: 'frontend/pages/submit-artifact-page.ts',
    outDir: 'public/assets/js/pages',
    fileName: 'submit_artifact_page.js',
    name: 'ErebusSubmitArtifactPage',
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
