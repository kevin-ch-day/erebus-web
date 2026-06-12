import type {
  SampleDetailNamespace,
  SamplePermissionRow,
} from '../../types/sample-detail-globals';

const SampleDetail = window.SampleDetail as SampleDetailNamespace | undefined;

if (window.App && SampleDetail) {
  const fmt = window.App.fmt;

  SampleDetail.createPermissionsCsv = ({ bucketLabel, isUnknown, ruleLabel }) => {
    function toCsv(rows: SamplePermissionRow[]): string {
      const headers = ['permission_string', 'classification', 'bucket', 'known', 'rule_fired', 'observed_at'];
      const escapeCsv = (value: unknown): string => {
        const raw = String(value ?? '');
        if (raw === '') return '';
        if (/["\n,]/.test(raw)) {
          return '"' + raw.replaceAll('"', '""') + '"';
        }
        return raw;
      };

      const lines = [headers.join(',')];
      rows.forEach((row) => {
        const line = [
          fmt(row.permission_string, ''),
          fmt(row.classification, ''),
          bucketLabel(row.bucket),
          isUnknown(row) ? 'Unknown' : 'Known',
          ruleLabel(row.rule_fired),
          fmt(row.observed_at, ''),
        ].map(escapeCsv).join(',');
        lines.push(line);
      });
      return lines.join('\n');
    }

    function downloadCsv(filename: string, contents: string): void {
      const blob = new Blob([contents], { type: 'text/csv;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    }

    return {
      toCsv,
      downloadCsv,
    };
  };
}
