<?php
declare(strict_types=1);

function artifact_source_options(): array
{
    return [
        ['value' => 'csv', 'label' => 'CSV / dataset import'],
        ['value' => 'ioc_repo', 'label' => 'IOC repo'],
        ['value' => 'sample_repo', 'label' => 'Sample repo'],
        ['value' => 'vendor_report', 'label' => 'Vendor report'],
        ['value' => 'research_blog', 'label' => 'Research blog'],
        ['value' => 'sandbox_report', 'label' => 'Sandbox report'],
        ['value' => 'cert_advisory', 'label' => 'CERT advisory'],
        ['value' => 'conference_paper', 'label' => 'Conference paper'],
        ['value' => 'virustotal_alert', 'label' => 'VirusTotal alert'],
        ['value' => 'osint', 'label' => 'OSINT'],
        ['value' => 'internal_hunt', 'label' => 'Internal hunt'],
        ['value' => 'manual', 'label' => 'Manual'],
        ['value' => 'other', 'label' => 'Other'],
    ];
}


function artifact_source_allowed_values(): array
{
    return array_map(
        static fn(array $option): string => (string)$option['value'],
        artifact_source_options()
    );
}


function artifact_source_is_valid(?string $value): bool
{
    $source = trim((string)($value ?? ''));
    if ($source === '') {
        return false;
    }
    return in_array($source, artifact_source_allowed_values(), true);
}


function render_artifact_source_options(?string $selected = null): string
{
    $selected = (string)($selected ?? '');
    $html = '<option value="">Select source</option>';
    foreach (artifact_source_options() as $option) {
        $value = (string)$option['value'];
        $label = (string)$option['label'];
        $isSelected = $value === $selected ? ' selected' : '';
        $html .= '<option value="' . h($value) . '"' . $isSelected . '>' . h($label) . '</option>';
    }
    return $html;
}
