#!/usr/bin/env python3
"""Generates public/assets/theme.css (dark mode) from style.css + inline <style> blocks.
Every color is remapped by inverting lightness (hue kept), scoped under html[data-theme="dark"]."""
import re, sys, glob, colorsys, os

ROOT = sys.argv[1]
PFX = 'html[data-theme="dark"]'

COLOR_PROPS = {'color','background','background-color','background-image','border','border-color','border-top','border-bottom',
               'border-left','border-right','border-top-color','border-bottom-color','border-left-color','border-right-color',
               'border-inline-start','border-inline-end','border-block-start','border-block-end','outline','outline-color',
               'box-shadow','fill','stroke','text-shadow','caret-color','-webkit-text-fill-color','text-decoration-color',
               'column-rule','scrollbar-color'}

NAMED = {'white': (255,255,255,1.0), 'black': (0,0,0,1.0)}
COLOR_RE = re.compile(r'#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|\b(?:white|black)\b')

def parse_color(tok):
    t = tok.lower()
    if t in NAMED: return NAMED[t]
    if t.startswith('#'):
        h = t[1:]
        if len(h) in (3, 4): h = ''.join(c*2 for c in h)
        if len(h) not in (6, 8): return None
        r, g, b = int(h[0:2],16), int(h[2:4],16), int(h[4:6],16)
        a = int(h[6:8],16)/255 if len(h) == 8 else 1.0
        return (r, g, b, a)
    m = re.match(r'rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:\s*[,/]\s*([\d.]+%?))?\s*\)', t)
    if not m: return None
    a = m.group(4)
    if a is None: a = 1.0
    elif a.endswith('%'): a = float(a[:-1])/100
    else: a = float(a)
    return (float(m.group(1)), float(m.group(2)), float(m.group(3)), a)

def fmt(r, g, b, a):
    r, g, b = (max(0, min(255, round(x))) for x in (r, g, b))
    if a >= 0.999: return f'#{r:02x}{g:02x}{b:02x}'
    return f'rgba({r},{g},{b},{round(a,3)})'

def remap(rgba, prop):
    r, g, b, a = rgba
    h, l, s = colorsys.rgb_to_hls(r/255, g/255, b/255)
    if prop in ('box-shadow', 'text-shadow'):
        return fmt(0, 0, 0, min(0.6, a * 1.6))            # سایه‌ها در حالت تاریک مشکی
    if a < 1 and l < 0.35:
        return fmt(r, g, b, a)                              # لایه‌های تیره نیمه‌شفاف (پس‌زمینه پاپ‌آپ) دست نخورند
    # وارونه‌کردن روشنایی: سفید ← سطح تیره، متن تیره ← متن روشن
    nl = 0.085 + (1 - l) * 0.83
    if l > 0.9:  s = s * 0.55                               # سطوح روشن رنگی ← تیره کم‌اشباع
    if 0.25 < l < 0.75 and s > 0.35:                        # رنگ‌های اشباع (قرمز/سبز/زرد) خوانا بمانند
        nl = max(nl, 0.55) if l < 0.5 else min(nl, 0.45)
    rr, gg, bb = colorsys.hls_to_rgb(h, max(0, min(1, nl)), max(0, min(1, s)))
    return fmt(rr*255, gg*255, bb*255, a)

def map_value(val, prop):
    # رنگ داخل url(...) (مثل آیکن‌های data:svg) دست نمی‌خورد
    parts = re.split(r'(url\([^)]*\))', val)
    out = []
    changed = False
    for p in parts:
        if p.startswith('url('):
            out.append(p); continue
        def rep(m):
            nonlocal changed
            c = parse_color(m.group(0))
            if c is None: return m.group(0)
            changed = True
            return remap(c, prop)
        out.append(COLOR_RE.sub(rep, p))
    return ''.join(out), changed

def strip_comments(css):
    return re.sub(r'/\*.*?\*/', '', css, flags=re.S)

def split_blocks(css):
    """yields (prelude, body) for top-level blocks, handling nesting."""
    i, n = 0, len(css)
    while i < n:
        j = css.find('{', i)
        if j < 0: break
        prelude = css[i:j].strip()
        depth, k = 1, j + 1
        while k < n and depth:
            if css[k] == '{': depth += 1
            elif css[k] == '}': depth -= 1
            k += 1
        body = css[j+1:k-1]
        # prelude may contain stray ';' statements (e.g. @import) — keep the part after the last ';'
        if ';' in prelude and not prelude.startswith('@'):
            prelude = prelude.rsplit(';', 1)[1].strip()
        yield prelude, body
        i = k

def scope_selector(sel):
    out = []
    for s in sel.split(','):
        s = s.strip()
        if not s: continue
        if s in (':root', 'html'):
            out.append(PFX)
        elif s.startswith('html') and not s.startswith('html['):
            out.append(PFX + s[4:])
        elif s.startswith('html['):
            out.append(PFX + s[4:])
        elif s == 'body' or s.startswith('body'):
            out.append(PFX + ' ' + s)
        else:
            out.append(PFX + ' ' + s)
    return ',\n'.join(out)

def convert_rules(css):
    res = []
    for prelude, body in split_blocks(css):
        low = prelude.lower()
        if low.startswith('@media'):
            if 'print' in low and 'screen' not in low: continue
            inner = convert_rules(body)
            if inner: res.append(f'{prelude}{{\n{inner}\n}}')
            continue
        if low.startswith('@'):
            continue   # keyframes, font-face, page, supports...
        decls = []
        for d in body.split(';'):
            if ':' not in d: continue
            prop, val = d.split(':', 1)
            prop = prop.strip().lower()
            val = val.strip()
            is_var = prop.startswith('--')
            if not is_var and prop not in COLOR_PROPS: continue
            nv, changed = map_value(val, 'box-shadow' if prop == 'box-shadow' else prop)
            if changed:
                decls.append(f'{prop}:{nv}')
        if decls:
            res.append(f'{scope_selector(prelude)}{{{";".join(decls)}}}')
    return '\n'.join(res)

sources = [open(os.path.join(ROOT, 'public/assets/style.css'), encoding='utf-8').read()]
for p in sorted(glob.glob(os.path.join(ROOT, 'public/*.php'))):
    s = open(p, encoding='utf-8').read()
    for m in re.finditer(r'<style[^>]*>(.*?)</style>', s, re.S):
        block = m.group(1)
        # عبارت‌های PHP داخل CSS (مثل grid-template-columns:<?= $gridCols ?>) حذف می‌شوند
        block = re.sub(r'<\?.*?\?>', '', block, flags=re.S)
        sources.append(block)

generated = '\n'.join(convert_rules(strip_comments(src)) for src in sources)

base = open(os.path.join(os.path.dirname(__file__), 'dark_base.css'), encoding='utf-8').read()
out = ('/* حالت تاریک سامانه — این فایل خودکار از style.css ساخته شده است (رنگ‌ها با وارونه‌کردن روشنایی).\n'
       '   تنظیمات دستی در انتهای فایل آمده است. */\n'
       + generated + '\n\n' + base)
open(os.path.join(ROOT, 'public/assets/theme.css'), 'w', encoding='utf-8').write(out)
print('theme.css', len(out)//1024, 'KB', generated.count('{'), 'rules')
