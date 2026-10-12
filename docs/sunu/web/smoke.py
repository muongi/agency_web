#!/usr/bin/env python3
"""Load the dossier in headless Chrome and confirm both languages actually render."""
import io, os, re, subprocess, sys, tempfile

SRC = 'sunu_dossier.html'
SKELETON = ('<!doctype html><html><head><meta charset="utf-8">'
            '<meta name="viewport" content="width=device-width,initial-scale=1">'
            '</head><body>%s</body></html>')


def run(lang):
    html = io.open(SRC, encoding='utf-8').read()
    # force the boot language, then open a document tab so renderDoc also executes
    html = html.replace('var lang="en";', 'var lang="%s";' % lang)
    html = html.replace('setLang(lang);',
                        'setLang(lang);document.getElementById("tab-doc1").click();'
                        'window.__ok=1;', 1)
    tmp = tempfile.NamedTemporaryFile('w', suffix='.html', delete=False, encoding='utf-8')
    tmp.write(SKELETON % html); tmp.close()
    out = subprocess.run(
        ['google-chrome', '--headless=new', '--disable-gpu', '--no-sandbox',
         '--virtual-time-budget=9000', '--dump-dom', '--window-size=430,900',
         'file://' + tmp.name],
        capture_output=True, text=True, timeout=180)
    os.unlink(tmp.name)
    return out.stdout, out.stderr


EXPECT = {
 'en': ['Milestones', 'Waiting on SUNU', 'What threatens the date',
        'Deployment topology', 'System context', 'required for field collection',
        'Pilot live', 'hard date', 'Back to top'],
 'fr': ['Jalons', 'En attente de SUNU', 'Ce qui menace l’échéance',
        'Topologie de déploiement', 'Contexte du système', 'indispensable à l’encaissement terrain',
        'Pilote en service', 'date ferme', 'Haut de page'],
}
# the other language's status strings must NOT be on screen at the same time
FORBID = {'en': ['Ce qui menace l’échéance', 'Jalons'],
          'fr': ['What threatens the date', 'Waiting on SUNU']}

bad = 0
for lang in ('en', 'fr'):
    dom, err = run(lang)
    print('=== %s ===  DOM %d KB' % (lang.upper(), len(dom) / 1024))
    for probe in EXPECT[lang]:
        ok = probe in dom
        bad += 0 if ok else 1
        print('   %s %s' % ('OK ' if ok else 'MISS', probe))
    visible = re.sub(r'<script\b[^>]*>.*?</script>', '', dom, flags=re.S)
    for probe in FORBID[lang]:
        ok = probe not in visible
        bad += 0 if ok else 1
        print('   %s not showing: %s' % ('OK ' if ok else 'LEAK', probe))
    # a rendered document means marked ran and the panel was filled
    rendered = len(re.findall(r'class="prose"', dom))
    print('   %s rendered document panels: %d' % ('OK ' if rendered else 'MISS', rendered))
    bad += 0 if rendered else 1
    for line in err.splitlines():
        if ('Uncaught' in line or 'SyntaxError' in line or 'TypeError' in line
                or 'ReferenceError' in line):
            print('   console: %s' % line.strip()[:160])

print('\n%s' % ('Smoke test passed in both languages.' if not bad else '%d PROBLEMS' % bad))
sys.exit(0 if not bad else 1)
