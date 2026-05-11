#!/usr/bin/env python3
"""
public/assets/olgunluk/template.pdf'den tablo grid çizgilerini tespit eder.
Çıktı, src/pdf.php'deki tablo koordinatlarını güncellemek için kullanılır.

Bağımlılıklar: pdftoppm (poppler), numpy, Pillow.
Çalıştırma: python3 tools/detect_grid.py
"""
import subprocess, sys, os, tempfile
from PIL import Image
import numpy as np

TEMPLATE = os.path.join(os.path.dirname(__file__), '..', 'public', 'assets', 'olgunluk', 'template.pdf')
DPI = 300

def render(page: int) -> Image.Image:
    with tempfile.TemporaryDirectory() as d:
        out = os.path.join(d, 'p')
        subprocess.check_call(['pdftoppm', '-r', str(DPI), '-f', str(page), '-l', str(page), TEMPLATE, out, '-png'])
        files = sorted(os.listdir(d))
        return Image.open(os.path.join(d, files[0])).convert('L')

def group_consec(arr, gap=4):
    if not len(arr): return []
    groups = [[arr[0]]]
    for v in arr[1:]:
        if v - groups[-1][-1] <= gap:
            groups[-1].append(v)
        else:
            groups.append([v])
    return [int(np.mean(g)) for g in groups]

def detect(page: int):
    img = render(page)
    arr = np.array(img)
    H, W = arr.shape
    mm = W / 210.0
    dark = arr < 100
    # Horizontal lines
    left, right = int(0.15 * W), int(0.85 * W)
    row_density = dark[:, left:right].sum(axis=1)
    hline_thr = int(0.6 * (right - left))
    hlines = group_consec(np.where(row_density > hline_thr)[0])
    print(f"\n=== Sayfa {page} ===")
    print("Yatay çizgiler (mm):", [round(y/mm, 2) for y in hlines])

    def vlines(y0_mm, y1_mm):
        y0, y1 = int(y0_mm * mm), int(y1_mm * mm)
        band = dark[y0:y1, :]
        col_density = band.sum(axis=0)
        thr = int(0.7 * (y1 - y0))
        return [round(x/mm, 2) for x in group_consec(np.where(col_density > thr)[0])]

    # Sayfa 3 için bilinen 3 tablo aralığı
    if page == 3:
        print("Tablo 1 dikey:", vlines(42, 120))
        print("Tablo 2 dikey:", vlines(180, 230))
        print("Tablo 3 dikey:", vlines(250, 280))

if __name__ == '__main__':
    detect(3)
