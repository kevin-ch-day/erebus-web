import type { JsonRecord } from '../types/app-globals';

export function toRecord(value: unknown): JsonRecord {
  return value && typeof value === 'object' ? (value as JsonRecord) : {};
}

export function asRows<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

export function fmtInt(value: unknown): string {
  const num = Number(value ?? 0);
  return Number.isFinite(num) ? num.toLocaleString() : '--';
}

export function setText(id: string, value: string): void {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

export function setTextBySelector(selector: string, value: string): void {
  const el = document.querySelector(selector);
  if (el) el.textContent = value;
}

export function debounce<T extends (...args: never[]) => void>(fn: T, waitMs: number): (...args: Parameters<T>) => void {
  let timer: ReturnType<typeof setTimeout> | null = null;
  return (...args: Parameters<T>) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => fn(...args), waitMs);
  };
}

export async function copyTextWithFeedback(button: HTMLButtonElement, text: string): Promise<void> {
  const command = text.trim();
  if (!command) return;

  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(command);
    } else {
      throw new Error('clipboard unavailable');
    }
    const original = button.textContent;
    button.textContent = 'Copied';
    window.setTimeout(() => {
      button.textContent = original;
    }, 1500);
  } catch {
    window.prompt('Copy CLI command:', command);
  }
}

export function bindPipelineEngineCopyButtons(root: ParentNode = document): void {
  root.querySelectorAll<HTMLButtonElement>('.pipeline-engine-copy[data-copy-command]').forEach((button) => {
    if (button.dataset.copyBound === '1') return;
    button.dataset.copyBound = '1';
    button.addEventListener('click', () => {
      void copyTextWithFeedback(button, button.getAttribute('data-copy-command') || '');
    });
  });
}
