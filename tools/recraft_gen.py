#!/usr/bin/env python3
"""
Taskly — Recraft-Asset-Pipeline (Items, Szenen, Tanuki-Skins).

Generisch über Manifeste: ein Manifest ist eine JSON-Liste von Jobs
  { "out": "items-raw/japan/onigiri.png",   # Zielpfad (Roh-PNG, 1024er)
    "prompt": "…",                          # voller Prompt
    "size": "1024x1024",                    # Recraft-Größe
    "style": "digital_illustration",        # ODER "style_id": "<uuid>" (Custom Style)
    "remove_bg": true }                     # Hintergrund automatisch freistellen

Befehle:
  items-manifest   journey_assets.md → manifests/items.json (alle Item-Icons)
  scenes-manifest  journey_assets.md → manifests/scenes.json (Karten 9:16 + Splash)
  generate         --manifest <f> [--only <substr>] [--limit N] [--force]
  style-create     --name <n> --images a.png b.png …  (max 5) → style_id in tools/recraft_styles.json
  process          --src items-raw --dst public/assets/img/items --size 256  (PIL-Resize)

API-Key: Umgebungsvariable RECRAFT_API_KEY oder Datei .recraft_key im Repo-Root (gitignored).
Neue Reise/Theme: Tabelle in journey_assets.md ergänzen → items-manifest → generate → process → rsync.
Neuer Tanuki-Skin: style-create (einmalig, aus bestehenden Posen) → Manifest wie
tools/manifest_tanuki_beispiel.json → generate → wie gehabt via tanuki-raw-Pipeline verarbeiten.
"""
import argparse
import io
import json
import mimetypes
import os
import re
import sys
import time
import urllib.error
import urllib.request
import uuid

API = 'https://external.api.recraft.ai/v1'
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


# ---------------------------------------------------------------- API-Basics
def api_key() -> str:
    key = os.environ.get('RECRAFT_API_KEY', '').strip()
    if not key:
        p = os.path.join(ROOT, '.recraft_key')
        if os.path.exists(p):
            key = open(p, encoding='utf-8').read().strip()
    if not key:
        sys.exit('Kein API-Key: RECRAFT_API_KEY setzen oder .recraft_key im Repo-Root anlegen.')
    return key


def _request(req: urllib.request.Request, tries: int = 4):
    for attempt in range(tries):
        try:
            with urllib.request.urlopen(req, timeout=120) as r:
                return json.loads(r.read().decode('utf-8'))
        except urllib.error.HTTPError as e:
            body = e.read().decode('utf-8', 'replace')[:300]
            if e.code in (429, 500, 502, 503) and attempt < tries - 1:
                wait = 5 * (attempt + 1)
                print(f'    … HTTP {e.code}, warte {wait}s ({body})')
                time.sleep(wait)
                continue
            raise SystemExit(f'API-Fehler HTTP {e.code}: {body}')
        except (urllib.error.URLError, TimeoutError) as e:
            if attempt < tries - 1:
                time.sleep(5 * (attempt + 1))
                continue
            raise SystemExit(f'Netzwerk-Fehler: {e}')


def post_json(path: str, payload: dict):
    req = urllib.request.Request(
        API + path,
        data=json.dumps(payload).encode('utf-8'),
        headers={'Authorization': f'Bearer {api_key()}', 'Content-Type': 'application/json'},
        method='POST',
    )
    return _request(req)


def post_multipart(path: str, fields: dict, files: list):
    """files: Liste (feldname, dateipfad)."""
    boundary = uuid.uuid4().hex
    body = io.BytesIO()
    for k, v in fields.items():
        body.write(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
    for field, fpath in files:
        fname = os.path.basename(fpath)
        ctype = mimetypes.guess_type(fname)[0] or 'application/octet-stream'
        body.write(f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{fname}"\r\n'
                   f'Content-Type: {ctype}\r\n\r\n'.encode())
        body.write(open(fpath, 'rb').read())
        body.write(b'\r\n')
    body.write(f'--{boundary}--\r\n'.encode())
    req = urllib.request.Request(
        API + path, data=body.getvalue(),
        headers={'Authorization': f'Bearer {api_key()}',
                 'Content-Type': f'multipart/form-data; boundary={boundary}'},
        method='POST',
    )
    return _request(req)


def download(url: str, dest: str):
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    # CDN blockt den Python-Default-UA → browser-üblichen Header senden.
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (Macintosh) TasklyAssetBot/1.0'})
    with urllib.request.urlopen(req, timeout=120) as r:
        open(dest, 'wb').write(r.read())


# ---------------------------------------------------------------- Befehle
def cmd_generate(args):
    jobs = json.load(open(args.manifest, encoding='utf-8'))
    if args.only:
        jobs = [j for j in jobs if args.only in j['out']]
    if args.limit:
        jobs = jobs[: args.limit]
    done = skipped = failed = 0
    for i, job in enumerate(jobs, 1):
        out = os.path.join(ROOT, job['out'])
        if os.path.exists(out) and not args.force:
            skipped += 1
            continue
        print(f'[{i}/{len(jobs)}] {job["out"]}')
        payload = {
            'prompt': job['prompt'][:1000],
            'model': job.get('model', 'recraftv3'),
            'size': job.get('size', '1024x1024'),
            'n': 1,
            'response_format': 'url',
        }
        if job.get('style_id'):
            payload['style_id'] = job['style_id']
        else:
            payload['style'] = job.get('style', 'digital_illustration')
        try:
            res = post_json('/images/generations', payload)
            url = res['data'][0]['url']
            download(url, out)
            if job.get('remove_bg'):
                res2 = post_multipart('/images/removeBackground', {'response_format': 'url'}, [('file', out)])
                download(res2['image']['url'], out)
            done += 1
        except SystemExit as e:
            print(f'    FEHLER: {e}')
            failed += 1
        time.sleep(args.delay)
    print(f'\nFertig: {done} generiert, {skipped} übersprungen (existieren), {failed} Fehler.')
    sys.exit(1 if failed else 0)


def _parse_assets_md():
    md = open(os.path.join(ROOT, 'journey_assets.md'), encoding='utf-8').read()
    base = re.search(r'^>\s*`([^`]+)`', md, re.M)
    base_style = (base.group(1).strip() + ', ') if base else ''
    # Themes über Überschriften trennen, Tabellenzeilen `items/...` einsammeln
    items, scenes = [], []
    theme = None
    for line in md.splitlines():
        h = re.match(r'^##\s+\S+\s+(\w+)', line)  # "## 🌸 Japan / Kyoto" → "Japan"
        if h:
            theme = h.group(1).lower()
        m = re.match(r'\|\s*`(items/[^`]+\.png)`\s*\|\s*(.+?)\s*\|$', line)
        if m:
            items.append({
                'out': 'items-raw/' + m.group(1)[len('items/'):],
                'prompt': base_style + m.group(2),
                'size': '1024x1024',
                'style': 'digital_illustration',
                'remove_bg': True,
            })
    # Szenen: Codeblöcke nach "Streckenkarte"/"Splash"-Überschriften
    for kind, suffix, size in (('Streckenkarte', 'map', '1024x1820'), ('Splash', 'splash', '1024x1820')):
        for m in re.finditer(r'##\s+\S+\s+(\w+)[^\n]*\n(?:.|\n)*?###\s+' + kind + r'[^\n]*\n`([^`]+)`', md):
            scenes.append({
                'out': f'items-raw/_scenes/{m.group(1).lower()}-{suffix}.png',
                'prompt': m.group(2),
                'size': size,
                'style': 'digital_illustration',
                'remove_bg': False,
            })
    return items, scenes


def cmd_items_manifest(_):
    items, _s = _parse_assets_md()
    os.makedirs(os.path.join(ROOT, 'manifests'), exist_ok=True)
    out = os.path.join(ROOT, 'manifests', 'items.json')
    json.dump(items, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    themes = {}
    for it in items:
        themes.setdefault(it['out'].split('/')[1], []).append(it)
    print(f'{out}: {len(items)} Items — ' + ', '.join(f'{t}={len(v)}' for t, v in sorted(themes.items())))


def cmd_scenes_manifest(_):
    _i, scenes = _parse_assets_md()
    os.makedirs(os.path.join(ROOT, 'manifests'), exist_ok=True)
    out = os.path.join(ROOT, 'manifests', 'scenes.json')
    json.dump(scenes, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print(f'{out}: {len(scenes)} Szenen')


def cmd_style_create(args):
    if len(args.images) > 5:
        sys.exit('Max. 5 Referenzbilder.')
    res = post_multipart('/styles', {'style': args.base}, [('file', p) for p in args.images])
    sid = res.get('id') or res.get('style_id')
    store = os.path.join(ROOT, 'tools', 'recraft_styles.json')
    styles = json.load(open(store)) if os.path.exists(store) else {}
    styles[args.name] = {'style_id': sid, 'base': args.base, 'refs': [os.path.basename(p) for p in args.images]}
    json.dump(styles, open(store, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print(f'Style "{args.name}" angelegt: {sid} (gespeichert in tools/recraft_styles.json)')


def cmd_process(args):
    from PIL import Image
    src_root = os.path.join(ROOT, args.src)
    dst_root = os.path.join(ROOT, args.dst)
    n = 0
    for dirpath, _d, files in os.walk(src_root):
        # _-Ordner (z.B. _scenes, _tanuki-test) sind Arbeitsmaterial, keine Item-Icons.
        if any(part.startswith('_') for part in os.path.relpath(dirpath, src_root).split(os.sep)):
            continue
        for f in sorted(files):
            if not f.endswith('.png'):
                continue
            rel = os.path.relpath(os.path.join(dirpath, f), src_root)
            dst = os.path.join(dst_root, rel)
            os.makedirs(os.path.dirname(dst), exist_ok=True)
            img = Image.open(os.path.join(dirpath, f)).convert('RGBA')
            img.thumbnail((args.size, args.size), Image.LANCZOS)
            img.save(dst, 'PNG', optimize=True)
            n += 1
    print(f'{n} Icons → {args.dst} (max {args.size}px)')


def cmd_process_scenes(args):
    """Szenen (Karten/Splash) → JPEG fürs Web (keine Transparenz nötig, viel kleiner)."""
    from PIL import Image
    src_root = os.path.join(ROOT, args.src)
    dst_root = os.path.join(ROOT, args.dst)
    os.makedirs(dst_root, exist_ok=True)
    n = 0
    for f in sorted(os.listdir(src_root)):
        if not f.endswith('.png'):
            continue
        img = Image.open(os.path.join(src_root, f)).convert('RGB')
        w, h = img.size
        if w > args.width:
            img = img.resize((args.width, round(h * args.width / w)), Image.LANCZOS)
        out = os.path.join(dst_root, f[:-4] + '.jpg')
        img.save(out, 'JPEG', quality=args.quality, optimize=True, progressive=True)
        n += 1
    print(f'{n} Szenen → {args.dst} (Breite {args.width}px, JPEG q{args.quality})')


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = p.add_subparsers(dest='cmd', required=True)
    sub.add_parser('items-manifest').set_defaults(fn=cmd_items_manifest)
    sub.add_parser('scenes-manifest').set_defaults(fn=cmd_scenes_manifest)
    g = sub.add_parser('generate')
    g.add_argument('--manifest', required=True)
    g.add_argument('--only')
    g.add_argument('--limit', type=int)
    g.add_argument('--force', action='store_true')
    g.add_argument('--delay', type=float, default=1.0)
    g.set_defaults(fn=cmd_generate)
    s = sub.add_parser('style-create')
    s.add_argument('--name', required=True)
    s.add_argument('--base', default='digital_illustration')
    s.add_argument('--images', nargs='+', required=True)
    s.set_defaults(fn=cmd_style_create)
    pr = sub.add_parser('process')
    pr.add_argument('--src', default='items-raw')
    pr.add_argument('--dst', default='public/assets/img/items')
    pr.add_argument('--size', type=int, default=256)
    pr.set_defaults(fn=cmd_process)
    ps = sub.add_parser('process-scenes')
    ps.add_argument('--src', default='items-raw/_scenes')
    ps.add_argument('--dst', default='public/assets/img/journey')
    ps.add_argument('--width', type=int, default=768)
    ps.add_argument('--quality', type=int, default=82)
    ps.set_defaults(fn=cmd_process_scenes)
    args = p.parse_args()
    args.fn(args)


if __name__ == '__main__':
    main()
