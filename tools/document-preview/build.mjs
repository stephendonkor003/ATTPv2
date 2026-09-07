import { build } from 'esbuild';
import { cp, mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const sourceDirectory = path.dirname(fileURLToPath(import.meta.url));
const projectDirectory = path.resolve(sourceDirectory, '../..');
const outputDirectory = path.join(projectDirectory, 'public/assets/vendor/document-preview');
const require = createRequire(import.meta.url);
const packageDirectory = (name) => path.dirname(require.resolve(`${name}/package.json`));

await mkdir(outputDirectory, { recursive: true });
const result = await build({
    absWorkingDir: sourceDirectory,
    entryPoints: ['runtime-entry.js'],
    outfile: path.join(outputDirectory, 'runtime.js'),
    bundle: true,
    format: 'esm',
    platform: 'browser',
    target: ['es2022'],
    minify: true,
    legalComments: 'linked',
    metafile: true,
    inject: [path.join(sourceDirectory, 'browser-polyfills.js')],
    alias: { stream: 'stream-browserify' },
    define: { 'process.env.NODE_ENV': '"production"' },
    logLevel: 'info',
});

const pdfDirectory = packageDirectory('pdfjs-dist');
await cp(path.join(pdfDirectory, 'build/pdf.worker.min.mjs'), path.join(outputDirectory, 'pdf.worker.min.js'));
for (const directory of ['standard_fonts', 'cmaps', 'wasm', 'iccs']) {
    await cp(path.join(pdfDirectory, directory), path.join(outputDirectory, directory), { recursive: true });
}

// Include the license of every npm package actually bundled, including nested
// versions. Filesystem-only Word/ZIP entry points are not part of this runtime.
const packages = new Map();
for (const filename of Object.keys(result.metafile.inputs)) {
    const absolute = path.resolve(sourceDirectory, filename);
    const segments = absolute.split(path.sep);
    const index = segments.lastIndexOf('node_modules');
    if (index < 0) continue;
    const end = index + (segments[index + 1].startsWith('@') ? 3 : 2);
    const directory = segments.slice(0, end).join(path.sep);
    if (packages.has(directory)) continue;
    const manifest = JSON.parse(await readFile(path.join(directory, 'package.json'), 'utf8'));
    packages.set(directory, manifest);
}

const bundled = [...packages].sort(([, left], [, right]) => `${left.name}@${left.version}`.localeCompare(`${right.name}@${right.version}`));
const licenseDirectory = path.join(outputDirectory, 'licenses');
await mkdir(licenseDirectory, { recursive: true });
for (const [directory, manifest] of bundled) {
    const prefix = `${manifest.name.replaceAll('/', '_').replaceAll('@', '')}-${manifest.version}`;
    const entries = await readdir(directory, { withFileTypes: true });
    const licenses = entries.filter((entry) => entry.isFile() && /^(?:licen[cs]e|copying|notice)(?:[._-]|$)/i.test(entry.name));
    if (!licenses.length) throw new Error(`Missing license file: ${manifest.name}@${manifest.version}`);
    for (const license of licenses) await cp(path.join(directory, license.name), path.join(licenseDirectory, `${prefix}-${license.name}`));
}

const manifest = {
    runtime: 'runtime.js',
    worker: 'pdf.worker.min.js',
    exports: ['pdfjs', 'docx', 'JSZip', 'extractLegacyDoc'],
    bundledPackages: bundled.map(([, item]) => ({ name: item.name, version: item.version, license: item.license })),
};
await writeFile(path.join(outputDirectory, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
await cp(path.join(sourceDirectory, 'README.md'), path.join(outputDirectory, 'README.md'));
console.log(`Built ${bundled.length} licensed browser packages in ${path.relative(projectDirectory, outputDirectory)}.`);
