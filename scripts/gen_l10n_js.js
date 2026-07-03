const fs = require('fs');
const path = require('path');

const langs = {
  en: 'nplurals=2; plural=(n != 1);',
  it: 'nplurals=3; plural=n == 1 ? 0 : n != 0 && n % 1000000 == 0 ? 1 : 2;',
  de: 'nplurals=2; plural=(n != 1);',
  fr: 'nplurals=2; plural=(n > 1);',
  es: 'nplurals=2; plural=(n != 1);',
};

for (const [lang, plural] of Object.entries(langs)) {
  const jsonPath = path.join('l10n', lang + '.json');
  if (!fs.existsSync(jsonPath)) { console.log('skip', lang); continue; }

  const raw = fs.readFileSync(jsonPath, 'utf8');
  // Parse line-by-line to handle duplicate keys (last one wins)
  // Format: "key": "value",  (inside "translations" block)
  const obj = {};
  let inTranslations = false;
  for (const line of raw.split('\n')) {
    if (line.includes('"translations"')) { inTranslations = true; continue; }
    if (!inTranslations) continue;
    if (line.trim() === '},' || line.trim() === '}') { inTranslations = false; continue; }
    // Match: "key": "value" or "key": "value",
    const m = line.match(/^\s+"((?:[^"\\]|\\.)*)"\s*:\s*"((?:[^"\\]|\\.)*)"/);
    if (m) obj[m[1]] = m[2];
  }

  const inner = Object.entries(obj)
    .map(([k, v]) => '    ' + JSON.stringify(k) + ' : ' + JSON.stringify(v))
    .join(',\n');

  const js = `OC.L10N.register(\n    "integration_docuseal",\n    {\n${inner}\n},\n"${plural}");`;
  fs.writeFileSync(path.join('l10n', lang + '.js'), js, 'utf8');
  console.log(`Generated l10n/${lang}.js (${Object.keys(obj).length} keys)`);
}
