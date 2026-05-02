import * as path from 'node:path';

export interface Violation {
  type: string;
  file: string;
  line: number;
  column: number;
  message: string;
  fix?: string;
}

type UnknownRecord = Record<string, unknown>;

export function parseCheckOutput(rawOutput: string): Violation[] {
  const trimmed = rawOutput.trim();

  if (trimmed.length === 0) {
    return [];
  }

  const candidates = buildJsonCandidates(trimmed);

  for (const candidate of candidates) {
    try {
      const parsed = JSON.parse(candidate) as unknown;
      const violations = extractViolations(parsed);

      if (violations.length > 0 || payloadContainsViolationKey(parsed)) {
        return violations;
      }
    } catch {
      // Ignore parse failure and continue trying fallback candidates.
    }
  }

  return [];
}

export function formatViolationMessage(violation: Violation): string {
  const base = `[${violation.type}] ${violation.message}`;

  if (!violation.fix || violation.fix.trim() === '') {
    return base;
  }

  return `${base}\nFix: ${violation.fix.trim()}`;
}

export function resolveViolationFilePath(filePath: string, workspaceRoot: string): string {
  if (path.isAbsolute(filePath)) {
    return path.normalize(filePath);
  }

  return path.normalize(path.join(workspaceRoot, filePath));
}

function extractViolations(payload: unknown): Violation[] {
  const lists: unknown[] = [];

  if (Array.isArray(payload)) {
    lists.push(payload);
  }

  if (isRecord(payload)) {
    lists.push(payload.violations);

    if (isRecord(payload.data)) {
      lists.push(payload.data.violations);
    }

    if (isRecord(payload.results)) {
      lists.push(payload.results.violations);
    }
  }

  for (const candidate of lists) {
    if (!Array.isArray(candidate)) {
      continue;
    }

    return candidate
      .map((value) => normalizeViolation(value))
      .filter((value): value is Violation => value !== null);
  }

  return [];
}

function normalizeViolation(value: unknown): Violation | null {
  if (!isRecord(value)) {
    return null;
  }

  const file = asString(value.file);

  if (file === null) {
    return null;
  }

  const type = asString(value.type) ?? 'violation';
  const message = asString(value.message) ?? 'Architecture violation detected.';
  const fix = asString(value.fix) ?? asString(value.suggestion) ?? asString(value.recommendation) ?? undefined;

  return {
    type,
    file,
    line: toPositiveInt(value.line, 1),
    column: toPositiveInt(value.column, 1),
    message,
    fix,
  };
}

function payloadContainsViolationKey(payload: unknown): boolean {
  if (!isRecord(payload)) {
    return false;
  }

  if ('violations' in payload) {
    return true;
  }

  return (isRecord(payload.data) && 'violations' in payload.data)
    || (isRecord(payload.results) && 'violations' in payload.results);
}

function buildJsonCandidates(trimmedOutput: string): string[] {
  const candidates = new Set<string>();

  candidates.add(trimmedOutput);

  const firstObject = trimmedOutput.indexOf('{');
  const lastObject = trimmedOutput.lastIndexOf('}');

  if (firstObject >= 0 && lastObject > firstObject) {
    candidates.add(trimmedOutput.slice(firstObject, lastObject + 1));
  }

  const firstArray = trimmedOutput.indexOf('[');
  const lastArray = trimmedOutput.lastIndexOf(']');

  if (firstArray >= 0 && lastArray > firstArray) {
    candidates.add(trimmedOutput.slice(firstArray, lastArray + 1));
  }

  for (const line of trimmedOutput.split(/\r?\n/)) {
    const value = line.trim();

    if ((value.startsWith('{') && value.endsWith('}')) || (value.startsWith('[') && value.endsWith(']'))) {
      candidates.add(value);
    }
  }

  return Array.from(candidates);
}

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === 'object' && value !== null;
}

function asString(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function toPositiveInt(value: unknown, fallback: number): number {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return Math.max(1, Math.floor(value));
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number.parseInt(value, 10);

    if (!Number.isNaN(parsed)) {
      return Math.max(1, parsed);
    }
  }

  return fallback;
}
