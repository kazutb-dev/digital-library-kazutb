import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const source = (relativePath) => readFile(path.join(projectRoot, relativePath), 'utf8');

const serverRenderedViews = [
    'resources/views/layouts/public.blade.php',
    'resources/views/layouts/librarian.blade.php',
    'resources/views/layouts/member.blade.php',
    'resources/views/layouts/admin.blade.php',
    'resources/views/book.blade.php',
    'resources/views/account.blade.php',
    'resources/views/auth/password-change.blade.php',
];

test('core server-rendered views use the local Vite stylesheet only', async () => {
    for (const relativePath of serverRenderedViews) {
        const view = await source(relativePath);

        assert.doesNotMatch(view, /cdn\.tailwindcss\.com|tailwind\.config/, relativePath);
        assert.match(view, /@vite\('resources\/css\/app\.css'\)/, relativePath);
    }
});

test('the shared source preserves the forms, palette, typography and radius contracts', async () => {
    const cssSource = await source('resources/css/app.css');

    for (const contract of [
        "@plugin '@tailwindcss/forms'",
        '--color-primary: #000613',
        '--color-secondary: #006a6a',
        '--color-on-surface-variant: #43474e',
        "--font-headline: 'Newsreader', serif",
        "[data-library-tailwind-theme='public']",
        "--font-headline: 'Literata', serif",
        "[data-library-tailwind-radius='compact']",
        'border-radius: 0.75rem',
    ]) {
        assert.ok(cssSource.includes(contract), `missing shared Tailwind contract: ${contract}`);
    }
});

test('the production build contains the entry stylesheet and custom generated utilities', async () => {
    const manifest = JSON.parse(await source('public/build/manifest.json'));
    const entry = manifest['resources/css/app.css'];

    assert.equal(entry?.isEntry, true);
    assert.match(entry?.file ?? '', /^assets\/app-[A-Za-z0-9_-]+\.css$/);

    const compiledCss = await source(path.posix.join('public/build', entry.file));
    for (const generatedContract of [
        '.bg-primary{',
        '.text-on-surface-variant{',
        '.bg-surface-low{',
        '.font-headline{',
        '.font-body{',
        '.font-label{',
        '.group-hover\\:bg-secondary-soft',
        '.bg-secondary-soft\\/40',
        '[type=checkbox]',
        '[data-library-tailwind-theme=public]',
        '[data-library-tailwind-radius=compact]',
    ]) {
        assert.ok(compiledCss.includes(generatedContract), `missing compiled Tailwind contract: ${generatedContract}`);
    }
});
