import { readdir, readFile } from 'node:fs/promises';
import { join, relative } from 'node:path';

const root = process.cwd();
const includedExtensions = new Set(['.css', '.js', '.php']);
const excludedDirs = new Set([
  '.cursor',
  '.git',
  'assets',
  'node_modules',
  'scratch',
]);

const m3FabPattern = /(^|[^A-Za-z0-9_-])(m3-fab)(?=[^A-Za-z0-9_-]|$)/g;
const fontAwesomePattern = /(^|[^A-Za-z0-9_-])((?:fab|fas|far|fal|fad)|fa-[A-Za-z0-9-]+)(?=[^A-Za-z0-9_-]|$)/g;

function getExtension(path) {
  const index = path.lastIndexOf('.');
  return index === -1 ? '' : path.slice(index);
}

function lineForIndex(text, index) {
  return text.slice(0, index).split('\n').length;
}

async function collectFiles(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    if (excludedDirs.has(entry.name)) continue;

    const fullPath = join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...await collectFiles(fullPath));
      continue;
    }

    if (entry.isFile() && includedExtensions.has(getExtension(entry.name))) {
      files.push(fullPath);
    }
  }

  return files;
}

function findMatches(text, pattern, tokenGroupIndex) {
  const matches = [];
  pattern.lastIndex = 0;

  for (let match = pattern.exec(text); match; match = pattern.exec(text)) {
    const prefixLength = match[1]?.length ?? 0;
    const tokenStart = match.index + prefixLength;
    matches.push({
      token: match[tokenGroupIndex],
      line: lineForIndex(text, tokenStart),
    });
  }

  return matches;
}

const files = await collectFiles(root);
const m3FabMatches = [];
const fontAwesomeMatches = [];

for (const file of files) {
  const text = await readFile(file, 'utf8');
  const relativePath = relative(root, file);

  for (const match of findMatches(text, m3FabPattern, 2)) {
    m3FabMatches.push({ file: relativePath, ...match });
  }

  for (const match of findMatches(text, fontAwesomePattern, 2)) {
    fontAwesomeMatches.push({ file: relativePath, ...match });
  }
}

console.log('Icon class audit');
console.log(`- Material 3 FAB tokens (allowed): ${m3FabMatches.length}`);
console.log(`- Font Awesome tokens (blocked): ${fontAwesomeMatches.length}`);

if (fontAwesomeMatches.length > 0) {
  console.log('\nFont Awesome tokens found:');
  for (const match of fontAwesomeMatches) {
    console.log(`${match.file}:${match.line}: ${match.token}`);
  }
  process.exitCode = 1;
}
