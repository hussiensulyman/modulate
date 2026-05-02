import test from 'node:test';
import assert from 'node:assert/strict';
import * as path from 'node:path';
import { formatViolationMessage, parseCheckOutput, resolveViolationFilePath } from '../diagnostics';

test('parseCheckOutput maps violations from JSON object payload', () => {
  const payload = JSON.stringify({
    violations: [
      {
        type: 'cross_module_import',
        file: 'app/Modules/Billing/Services/BillingService.php',
        line: 27,
        column: 14,
        message: 'Billing service imports Course model directly.',
        fix: 'Use a contract from Shared module instead.',
      },
    ],
  });

  const violations = parseCheckOutput(payload);

  assert.equal(violations.length, 1);
  assert.equal(violations[0]?.type, 'cross_module_import');
  assert.equal(violations[0]?.line, 27);
  assert.equal(violations[0]?.column, 14);
});

test('parseCheckOutput extracts JSON payload from mixed command output', () => {
  const raw = [
    'Running checks...',
    '{"violations":[{"type":"missing_binding","file":"app/Modules/Auth/Providers/AuthServiceProvider.php","line":"9","message":"Missing contract binding.","suggestion":"Bind interface to implementation."}]}',
    'Finished.',
  ].join('\n');

  const violations = parseCheckOutput(raw);

  assert.equal(violations.length, 1);
  assert.equal(violations[0]?.column, 1);
  assert.equal(
    formatViolationMessage(violations[0]!),
    '[missing_binding] Missing contract binding.\nFix: Bind interface to implementation.',
  );
});

test('resolveViolationFilePath resolves relative paths against workspace root', () => {
  const workspaceRoot = path.join('tmp', 'workspace');
  const resolved = resolveViolationFilePath('app/Modules/Course/Models/Course.php', workspaceRoot);

  assert.equal(resolved, path.normalize(path.join(workspaceRoot, 'app/Modules/Course/Models/Course.php')));
});
