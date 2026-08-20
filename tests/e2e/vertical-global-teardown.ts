import { rmSync } from 'node:fs';
import path from 'node:path';

/** Remove only the per-run filesystem sandbox; official DB rows stay immutable. */
export default function verticalGlobalTeardown(): void {
  if (process.env.PLAYWRIGHT_E2E_MUTATIONS !== '1') return;

  const database = process.env.PLAYWRIGHT_E2E_DATABASE ?? '';
  const runId = process.env.PLAYWRIGHT_VERTICAL_RUN_ID ?? '';
  const port = Number(process.env.PLAYWRIGHT_VERTICAL_PORT ?? 8017);
  if (!/^[A-Za-z_][A-Za-z0-9_]*_test$/i.test(database)
    || !/^[A-Za-z0-9_-]+$/.test(runId)
    || !Number.isInteger(port)
    || port < 1024
    || port > 65535) {
    throw new Error('Refusing vertical E2E filesystem teardown with an unsafe runtime identity.');
  }

  const base = '/tmp/kazutb-library-playwright';
  const cacheStem = database.replace(/[^A-Za-z0-9_-]/g, '_');
  const target = path.join(base, `${cacheStem}-${port}-${runId}`);
  const relative = path.relative(base, target);
  if (relative === '' || relative.startsWith('..') || path.isAbsolute(relative)) {
    throw new Error('Refusing vertical E2E filesystem teardown outside its exact sandbox.');
  }

  rmSync(target, { recursive: true, force: true });
}
