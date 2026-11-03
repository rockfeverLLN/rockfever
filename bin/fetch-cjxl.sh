#!/usr/bin/env bash
# Downloads libjxl's static Linux cjxl at the version in bin/cjxl.version to bin/cjxl, checked against its SHA-256,
# and fails unless it makes JPEG XL copies as src/Framework/Upload.php asks the server for them: a JPEG repacked
# without loss and a lossless PNG, without worker threads (OVH refuses them). The server's PHP can't write JPEG XL
# by itself (its Imagick is built without it), so bin/deploy.sh uploads this one.
# Used by .github/workflows/deploy.yml and update-cjxl.yml. Needs lzip.
set -euo pipefail
cd "$(dirname "$0")/.."

read -r TAG SHA256 < <(grep -v '^#' bin/cjxl.version)
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

curl -fsSL --retry 3 -o "$WORK/jxl.tar.lz" \
    "https://github.com/libjxl/libjxl/releases/download/$TAG/jxl-linux-x86_64-static.tar.lz"
echo "$SHA256  $WORK/jxl.tar.lz" | sha256sum -c --quiet -
tar -xf "$WORK/jxl.tar.lz" --lzip -C "$WORK" tools/cjxl
mv "$WORK/tools/cjxl" bin/cjxl
bin/cjxl --version

# The same options as Upload::saveJxl
bin/cjxl .github/assets/banner.jpg "$WORK/jpeg.jxl" --lossless_jpeg=1 --num_threads=0 --quiet
bin/cjxl www/images/favicon.png "$WORK/png.jxl" -d 0 --num_threads=0 --quiet
for file in "$WORK/jpeg.jxl" "$WORK/png.jxl"; do
    # A JPEG XL file starts with FF 0A, or with its container's box
    case $(head -c 12 "$file" | od -An -tx1 | tr -d ' \n') in
        ff0a*|0000000c4a584c200d0a870a) ;;
        *) echo "cjxl $TAG didn't write JPEG XL to $(basename "$file")" >&2; exit 1 ;;
    esac
done
echo "cjxl $TAG makes JPEG XL: $(stat -c %s .github/assets/banner.jpg) => $(stat -c %s "$WORK/jpeg.jxl") bytes for a JPEG"
