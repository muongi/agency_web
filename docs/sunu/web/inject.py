#!/usr/bin/env python3
"""Assemble the dossier artifact: inline the figures, then embed the four documents."""
import io, os, re, sys

DOCS_DIR = '/var/www/html/agency/docs/sunu'
DIAGRAMS = os.path.join(DOCS_DIR, 'diagrams')
HERE = os.path.dirname(os.path.abspath(__file__))

FILES = {'__DOC1__': 'SUNU_01_TECHNICAL_ARCHITECTURE.md',
         '__DOC2__': 'SUNU_02_SECURITY.md',
         '__DOC3__': 'SUNU_03_USER_GUIDE.md',
         '__DOC4__': 'SUNU_04_DEPLOYMENT_PLAN.md',
         '__DOC1FR__': 'SUNU_01_ARCHITECTURE_TECHNIQUE_FR.md',
         '__DOC2FR__': 'SUNU_02_SECURITE_FR.md',
         '__DOC3FR__': 'SUNU_03_GUIDE_UTILISATEUR_FR.md',
         '__DOC4FR__': 'SUNU_04_PLAN_DEPLOIEMENT_FR.md'}

FIG = re.compile(r'^<!--\s*figure:\s*(.+?)\s*-->\s*$', re.M)


def inline_figures(md, suffix=''):
    """Swap each figure marker for the inline SVG, dropping the ASCII it supersedes."""
    out, i = [], 0
    lines = md.split('\n')
    while i < len(lines):
        m = FIG.match(lines[i])
        if not m:
            out.append(lines[i]); i += 1; continue
        bits = [b.strip() for b in m.group(1).split('|')]
        name, caption = bits[0], (bits[1] if len(bits) > 1 else '')
        replace = len(bits) > 2 and bits[2] == 'replace'
        i += 1
        if replace:
            while i < len(lines) and not lines[i].strip():
                i += 1
            if i < len(lines) and lines[i].startswith('```'):
                i += 1
                while i < len(lines) and not lines[i].startswith('```'):
                    i += 1
                i += 1
        svg_path = os.path.join(DIAGRAMS, name + suffix + '.svg')
        if not os.path.exists(svg_path):
            svg_path = os.path.join(DIAGRAMS, name + '.svg')
        svg = io.open(svg_path, encoding='utf-8').read()
        out.append('')
        out.append('<figure><div class="fig-frame">' + svg +
                   '</div><figcaption>' + caption + '</figcaption></figure>')
        out.append('')
    return '\n'.join(out)


def main():
    tpl = io.open(os.path.join(HERE, 'template.html'), encoding='utf-8').read()
    for key, fname in FILES.items():
        md = io.open(os.path.join(DOCS_DIR, fname), encoding='utf-8').read()
        md = inline_figures(md)
        assert '</script' not in md.lower(), fname
        md = md.replace('<script', '&lt;script')
        tpl = tpl.replace(key, md)
    out = os.path.join(HERE, 'sunu_dossier.html')
    io.open(out, 'w', encoding='utf-8').write(tpl)
    print('bytes: %d  figures: %d' % (len(tpl.encode('utf-8')), tpl.count('<figure>')))


if __name__ == '__main__':
    main()
