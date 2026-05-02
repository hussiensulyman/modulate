import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import * as vscode from 'vscode';
import { formatViolationMessage, parseCheckOutput, resolveViolationFilePath, Violation } from './diagnostics';

const execFileAsync = promisify(execFile);
const modulateCommand = 'modulate.runCheck';
const diagnosticSource = 'modulate';

interface ExecError extends Error {
  stdout?: string;
  stderr?: string;
}

export function activate(context: vscode.ExtensionContext): void {
  const diagnostics = vscode.languages.createDiagnosticCollection(diagnosticSource);
  const output = vscode.window.createOutputChannel('Modulate');
  context.subscriptions.push(diagnostics, output);

  let runInProgress = false;
  let rerunRequested = false;

  const scheduleCheck = async (trigger: 'save' | 'command' | 'startup'): Promise<void> => {
    if (runInProgress) {
      rerunRequested = true;
      return;
    }

    runInProgress = true;

    try {
      do {
        rerunRequested = false;
        await runCheck(trigger, diagnostics, output);
      } while (rerunRequested);
    } finally {
      runInProgress = false;
    }
  };

  context.subscriptions.push(vscode.commands.registerCommand(modulateCommand, async () => {
    await scheduleCheck('command');
  }));

  context.subscriptions.push(vscode.workspace.onDidSaveTextDocument(async (document) => {
    if (document.languageId !== 'php') {
      return;
    }

    await scheduleCheck('save');
  }));

  void scheduleCheck('startup');
}

export function deactivate(): void {
  // No explicit cleanup required; subscriptions are disposed by VS Code.
}

async function runCheck(
  trigger: 'save' | 'command' | 'startup',
  diagnostics: vscode.DiagnosticCollection,
  output: vscode.OutputChannel,
): Promise<void> {
  const workspaceFolder = vscode.workspace.workspaceFolders?.[0];

  if (!workspaceFolder) {
    diagnostics.clear();

    if (trigger === 'command') {
      void vscode.window.showWarningMessage('Modulate check requires an opened workspace folder.');
    }

    return;
  }

  let commandOutput = '';

  try {
    commandOutput = await runModulateCheck(workspaceFolder.uri.fsPath);
  } catch (error) {
    diagnostics.clear();
    const message = error instanceof Error ? error.message : String(error);
    output.appendLine(`modulate:check failed: ${message}`);
    void vscode.window.showErrorMessage(`Modulate check failed: ${message}`);
    return;
  }

  const violations = parseCheckOutput(commandOutput);
  applyDiagnostics(diagnostics, workspaceFolder.uri.fsPath, violations);

  if (trigger === 'command') {
    if (violations.length > 0) {
      void vscode.window.showInformationMessage(`Modulate reported ${violations.length} violation(s).`);
      return;
    }

    if (looksLikeNoViolationOutput(commandOutput)) {
      void vscode.window.showInformationMessage('Modulate found no violations.');
      return;
    }

    output.appendLine('No JSON violation payload found in modulate:check output.');
    output.appendLine(commandOutput);
    void vscode.window.showWarningMessage(
      'Modulate check finished, but no JSON violations payload was detected. Ensure modulate:check supports --json output.',
    );
  }
}

async function runModulateCheck(workspaceRoot: string): Promise<string> {
  try {
    const { stdout, stderr } = await execFileAsync(
      'php',
      ['artisan', 'modulate:check', '--json'],
      {
        cwd: workspaceRoot,
        maxBuffer: 10 * 1024 * 1024,
      },
    );

    return [stdout, stderr].filter(Boolean).join('\n').trim();
  } catch (error) {
    if (isExecError(error)) {
      const output = [error.stdout, error.stderr].filter(Boolean).join('\n').trim();

      if (output.length > 0) {
        return output;
      }
    }

    throw error;
  }
}

function isExecError(error: unknown): error is ExecError {
  return typeof error === 'object' && error !== null && ('stdout' in error || 'stderr' in error);
}

function looksLikeNoViolationOutput(output: string): boolean {
  const normalized = output.toLowerCase();

  return normalized.includes('no violations detected')
    || normalized.includes('found 0 violation')
    || normalized.includes('"violations":[]')
    || normalized.includes('"violations": []');
}

function applyDiagnostics(
  collection: vscode.DiagnosticCollection,
  workspaceRoot: string,
  violations: Violation[],
): void {
  collection.clear();

  const grouped = new Map<string, { uri: vscode.Uri; diagnostics: vscode.Diagnostic[] }>();

  for (const violation of violations) {
    const absoluteFile = resolveViolationFilePath(violation.file, workspaceRoot);
    const uri = vscode.Uri.file(absoluteFile);

    const line = Math.max(0, violation.line - 1);
    const startColumn = Math.max(0, violation.column - 1);
    const endColumn = startColumn + 1;

    const diagnostic = new vscode.Diagnostic(
      new vscode.Range(new vscode.Position(line, startColumn), new vscode.Position(line, endColumn)),
      formatViolationMessage(violation),
      vscode.DiagnosticSeverity.Error,
    );

    diagnostic.source = diagnosticSource;
    diagnostic.code = violation.type;

    const bucket = grouped.get(uri.fsPath);

    if (bucket) {
      bucket.diagnostics.push(diagnostic);
      continue;
    }

    grouped.set(uri.fsPath, {
      uri,
      diagnostics: [diagnostic],
    });
  }

  for (const value of grouped.values()) {
    collection.set(value.uri, value.diagnostics);
  }
}
