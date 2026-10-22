#!/usr/bin/env bash
# Rebuilds www/fonts/material-icons.woff2 with only the Material Icons used in the templates.
# Run it after using a new icon. Needs fonttools: brew install fonttools
set -euo pipefail
cd "$(dirname "$0")/.."

# Every <i class="material-icons ...">name</i> in the templates and PHP code
ICONS=$(grep -rhoE "material-icons[^>]*>[a-z_0-9]+<" views src | sed -E 's/.*>([a-z_0-9]+)</\1/' | sort -u | tr '\n' ' ')
echo "Icons: $ICONS"

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
CSS=$(curl -fsS -A "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/150 Safari/537.36" \
    "https://fonts.googleapis.com/icon?family=Material+Icons")
curl -fsS "$(grep -o 'https://fonts.gstatic.com[^)]*' <<< "$CSS" | head -1)" -o "$TMP/full.woff2"

# The Python that comes with Homebrew's fonttools
PYTHON=${PYTHON:-$(head -1 "$(command -v pyftsubset)" | sed 's/^#!//')}
"$PYTHON" - "$TMP/full.woff2" www/fonts/material-icons.woff2 "$ICONS" <<'PY'
import sys
from fontTools.ttLib import TTFont
from fontTools import subset

src, dst, names = sys.argv[1], sys.argv[2], sys.argv[3].split()
font = TTFont(src)
cmap = font.getBestCmap()
letters = sorted(set(''.join(names)))
letter_glyphs = {cmap[ord(c)] for c in letters}
# Find the ligature glyph that each icon name turns into
want = {tuple(cmap[ord(c)] for c in n): n for n in names}
found = {}
for lookup in font['GSUB'].table.LookupList.Lookup:
    for st in lookup.SubTable:
        st = getattr(st, 'ExtSubTable', st)
        for first, ligs in getattr(st, 'ligatures', {}).items():
            for lig in ligs:
                key = (first, *lig.Component)
                if key in want:
                    found[want[key]] = lig.LigGlyph
missing = set(names) - set(found)
if missing:
    sys.exit('missing icons: %s' % ' '.join(sorted(missing)))
options = subset.Options()
options.flavor = 'woff2'
options.layout_features = ['liga']
options.layout_closure = False
options.name_IDs = ['*']
s = subset.Subsetter(options)
s.populate(glyphs=sorted(letter_glyphs | set(found.values())), unicodes=[ord(c) for c in letters])
s.subset(font)
font.flavor = 'woff2'
font.save(dst)
print(len(found), 'icons')
PY
ls -l www/fonts/material-icons.woff2
