import json, re, glob
keys = set()
pat_js = re.compile(r"t\('integration_docuseal',\s*'((?:[^'\\]|\\.)+)'")
pat_php = re.compile(r"->t\('((?:[^'\\]|\\.)+)'")
for f in glob.glob('src/**/*.vue', recursive=True) + glob.glob('src/**/*.js', recursive=True):
    s = open(f, encoding='utf-8').read()
    for m in pat_js.finditer(s):
        keys.add(m.group(1).replace("\\'", "'"))
for f in glob.glob('lib/**/*.php', recursive=True):
    s = open(f, encoding='utf-8').read()
    for m in pat_php.finditer(s):
        keys.add(m.group(1).replace("\\'", "'"))
en = json.load(open('l10n/en.json', encoding='utf-8'))['translations']
it = json.load(open('l10n/it.json', encoding='utf-8'))['translations']
miss_en = sorted(k for k in keys if k not in en)
miss_it = sorted(k for k in keys if k not in it)
print('TOTAL SOURCE KEYS:', len(keys))
print('MISSING IN en.json:', len(miss_en))
for k in miss_en: print('  EN:', repr(k))
print('MISSING IN it.json:', len(miss_it))
for k in miss_it: print('  IT:', repr(k))
