// Erzeugt entwurf-diagramme.html (Klickdummy-Seite) aus Beispieldaten.
// Die Funktionen unten sind die Vorlage für ChartRenderer.php (Geometrie, Helfer, Markup).
// Aufruf: node bauen.cjs [ausgabe.html] [--einzeln]   (--einzeln = Stile und Skript eingebettet)
const fs = require('fs'), path = require('path');
const dir = __dirname;
const out = process.argv[2] || path.join(dir, '..', 'entwurf-diagramme.html');
const einzeln = process.argv.includes('--einzeln');

/* ---------------- Helfer (→ ChartRenderer) ---------------- */
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const n1 = x => Math.round(x * 10) / 10;
const NB = ' '; // schmales Leerzeichen: Tausender und Einheit
function fmt(v, d = 0) {
  let s = Math.abs(v).toFixed(d);
  let [i, f] = s.split('.');
  if (i.length > 4) i = i.replace(/\B(?=(\d{3})+(?!\d))/g, NB);
  return (v < 0 ? '−' : '') + i + (f ? ',' + f : '');
}
const withUnit = (v, d, u) => fmt(v, d) + (u ? (u === '%' ? NB + '%' : NB + u) : '');
function niceScale(max) { // schöne Schritte 1/2/5 × 10^n, höchstens 6 Intervalle
  for (let p = Math.pow(10, Math.floor(Math.log10(max / 6))); ; p *= 10) for (const f of [1, 2, 5]) { const step = f * p; if (max / step <= 6 + 1e-9) return { step, max: Math.ceil(max / step - 1e-9) * step, dec: step >= 1 ? 0 : -Math.floor(Math.log10(step)) }; }
}
function largestRemainder(vals, total = 100) { // Anteile, die genau 100 ergeben
  const sum = vals.reduce((a, b) => a + b, 0), raw = vals.map(v => v / sum * total);
  const fl = raw.map(Math.floor); let rest = total - fl.reduce((a, b) => a + b, 0);
  raw.map((r, i) => [r - fl[i], i]).sort((a, b) => b[0] - a[0]).slice(0, rest).forEach(([, i]) => fl[i]++);
  return fl;
}
const wrap = (s, n) => { const w = s.split(' '), L = []; let c = ''; for (const x of w) { if ((c + ' ' + x).trim().length > n && c) { L.push(c); c = x; } else c = (c + ' ' + x).trim(); } L.push(c); return L; };
const textW = (s, fs = 12.5) => s.length * fs * 0.56; // grobe Breite für „passt die Beschriftung?“
const title = t => `<title>${esc(t)}</title>`;
function text(x, y, s, cls = '', anchor = '') { return `<text x="${n1(x)}" y="${n1(y)}"${cls ? ` class="${cls}"` : ''}${anchor ? ` text-anchor="${anchor}"` : ''}>${esc(s)}</text>`; }
// Balken/Säule mit 4 px abgerundetem Datenende, Basis eckig. dir: 'r' | 'l' | 'o' (oben) | 'u' (unten)
function mark(x, y, w, h, dir, cls, tit) {
  const r = Math.min(4, w / 2, h / 2); let d;
  if (dir === 'o') d = `M${n1(x)} ${n1(y + h)}V${n1(y + r)}Q${n1(x)} ${n1(y)} ${n1(x + r)} ${n1(y)}H${n1(x + w - r)}Q${n1(x + w)} ${n1(y)} ${n1(x + w)} ${n1(y + r)}V${n1(y + h)}Z`;
  else if (dir === 'u') d = `M${n1(x)} ${n1(y)}V${n1(y + h - r)}Q${n1(x)} ${n1(y + h)} ${n1(x + r)} ${n1(y + h)}H${n1(x + w - r)}Q${n1(x + w)} ${n1(y + h)} ${n1(x + w)} ${n1(y + h - r)}V${n1(y)}Z`;
  else if (dir === 'r') d = `M${n1(x)} ${n1(y)}H${n1(x + w - r)}Q${n1(x + w)} ${n1(y)} ${n1(x + w)} ${n1(y + r)}V${n1(y + h - r)}Q${n1(x + w)} ${n1(y + h)} ${n1(x + w - r)} ${n1(y + h)}H${n1(x)}Z`;
  else d = `M${n1(x + w)} ${n1(y)}H${n1(x + r)}Q${n1(x)} ${n1(y)} ${n1(x)} ${n1(y + r)}V${n1(y + h - r)}Q${n1(x)} ${n1(y + h)} ${n1(x + r)} ${n1(y + h)}H${n1(x + w)}Z`;
  return `<path class="m ${cls || ''}" d="${d}">${tit ? title(tit) : ''}</path>`;
}
const rect = (x, y, w, h, cls, tit, extra = '') => `<rect class="m ${cls || ''}" x="${n1(x)}" y="${n1(y)}" width="${n1(w)}" height="${n1(h)}"${extra}>${tit ? title(tit) : ''}</rect>`;
const legende = (items, skala) => `<ul class="diagramm-legende${skala ? ' skala' : ''}">${items.map(i => `<li class="${i.cls}"><svg class="marke" viewBox="0 0 13 13" aria-hidden="true"><rect class="m" width="13" height="13" rx="${skala ? 0 : 3}"/></svg>${esc(i.name)}</li>`).join('')}</ul>`;
function tabelle(head, rows, sums = []) {
  return `<table><thead><tr>${head.map(h => `<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map((r, i) => `<tr${sums.includes(i) ? ' data-sum="true"' : ''}>${r.map((c, j) => j ? `<td>${esc(c)}</td>` : `<th scope="row">${esc(c)}</th>`).join('')}</tr>`).join('')}</tbody></table>`;
}
let ID = 0;
function figure({ typ, titel, beschr, w = 440, h, svg, defs = '', leg = '', tab, cap, breit, extra = '', syntax }) {
  const id = ++ID;
  return `<figure class="diagramm" data-typ="${typ}"${breit ? ' data-breit' : ''}${extra}>
  <div class="diagramm-flaeche"><svg role="img" aria-labelledby="d${id}-titel d${id}-beschreibung" viewBox="0 0 ${w} ${h}"><title id="d${id}-titel">${esc(titel)}</title><desc id="d${id}-beschreibung">${esc(beschr)}</desc>${defs}${svg}</svg></div>
  ${leg}
  <details class="diagramm-tabelle"><summary>Zahlen als Tabelle</summary>${tab}</details>
  <figcaption>${cap}</figcaption>
</figure>`;
}
function achseY(x0, x1, y0, ymax, sc, fmtv, unitTitel, d = 0) { // eine Zahlenachse, Gitter als Haarlinien
  let s = `<g class="achse">`;
  for (let v = 0; v <= sc.max + 1e-9; v += sc.step) {
    const y = y0 - v / sc.max * (y0 - ymax);
    s += `<line class="${v === 0 ? 'basis' : 'gitter'}" x1="${x0}" x2="${x1}" y1="${n1(y)}" y2="${n1(y)}"/>` + text(x0 - 6, y + 4, fmt(v, sc.dec), '', 'end');
  }
  return s + (unitTitel ? text(0, 10, unitTitel, 'achsentitel') : '') + '</g>';
}

/* ---------------- Typen ---------------- */
// Säulen / gruppiert
function saeulen(d) {
  const { cats, series, unit, dec = 0 } = d, W = 440, H = 250, x0 = 36, x1 = 432, yb = 216, yt = 28;
  const mx = Math.max(...series.flatMap(s => s.v)), sc = niceScale(mx * 1.02);
  const band = (x1 - x0) / cats.length, s = series.length;
  const bw = Math.min(24, (band * .72 - (s - 1) * 2) / s), gw = s * bw + (s - 1) * 2;
  let g = achseY(x0, x1, yb, yt, sc, fmt, unit ? 'in ' + unit : '', 0);
  const lbl = cats.length <= 8 ? 'alle' : 'max';
  cats.forEach((c, i) => {
    const gx = x0 + band * i + (band - gw) / 2;
    series.forEach((se, j) => {
      const v = se.v[i], h = v / sc.max * (yb - yt), x = gx + j * (bw + 2);
      g += `<g class="r${j + 1}">${mark(x, yb - h, bw, h, 'o', '', `${se.name}, ${c}: ${withUnit(v, dec, unit)}`)}</g>`;
      if (s === 1 ? lbl === 'alle' || v === mx : v === Math.max(...se.v)) { const t = withUnit(v, dec, unit), hw = textW(t) / 2, cx = Math.min(x + bw / 2, W - 4 - hw); g += text(cx, yb - h - 5, t, 'wert', 'middle'); }
    });
    const lab = cats.length > 12 ? (i % 4 === 0 ? c : '') : c;
    if (lab) g += `<g class="achse">${text(x0 + band * i + band / 2, yb + 16, lab, '', 'middle')}</g>`;
  });
  return { W, H, g };
}
function balken(d) {
  const { cats, v, unit, dec = 0 } = d, W = 440, rowH = 30, x0 = 124, top = 22, mx = Math.max(...v), sc = niceScale(mx * 1.05), x1 = 432 - Math.max(...v.map(z => textW(withUnit(z, dec, unit), 12.5))) - 12;
  const H = top + cats.length * rowH + 24; let g = '<g class="achse">';
  for (let t = 0; t <= sc.max + 1e-9; t += sc.step) {
    const x = x0 + t / sc.max * (x1 - x0);
    g += `<line class="${t === 0 ? 'basis' : 'gitter'}" x1="${n1(x)}" x2="${n1(x)}" y1="${top - 4}" y2="${H - 22}"/>` + text(x, H - 6, fmt(t, sc.dec), '', 'middle');
  }
  g += text(0, 10, unit ? 'in ' + unit : '', 'achsentitel') + '</g><g class="r1">';
  cats.forEach((c, i) => {
    const y = top + i * rowH, w = v[i] / sc.max * (x1 - x0), L = wrap(c, 19);
    g += mark(x0, y + 4, w, 20, 'r', '', `${c}: ${withUnit(v[i], dec, unit)}`);
    L.forEach((l, k) => g += text(x0 - 8, y + 18 - (L.length - 1) * 7 + k * 14, l, '', 'end'));
    g += text(x0 + w + 6, y + 18, withUnit(v[i], dec, unit), 'wert');
  });
  return { W, H, g: g + '</g>' };
}
function stack(d, horiz) {
  const { cats, series, unit } = d, W = 440;
  const tot = cats.map((_, i) => series.reduce((a, s) => a + s.v[i], 0)), sc = niceScale(Math.max(...tot) * 1.02);
  let g = '', H;
  if (horiz) {
    const x0 = 78, x1 = 424, rowH = 32, top = 22; H = top + cats.length * rowH + 24; g += '<g class="achse">';
    for (let t = 0; t <= sc.max + 1e-9; t += sc.step) { const x = x0 + t / sc.max * (x1 - x0); g += `<line class="${t === 0 ? 'basis' : 'gitter'}" x1="${n1(x)}" x2="${n1(x)}" y1="${top - 4}" y2="${H - 22}"/>` + text(x, H - 6, fmt(t, sc.dec), '', 'middle'); }
    g += text(0, 10, 'in ' + unit, 'achsentitel') + '</g>';
    cats.forEach((c, i) => {
      const y = top + i * rowH; let acc = 0; g += `<g class="achse">${text(x0 - 8, y + 19, c, '', 'end')}</g>`;
      series.forEach((s, j) => {
        const a = x0 + acc / sc.max * (x1 - x0), b = x0 + (acc + s.v[i]) / sc.max * (x1 - x0), last = j === series.length - 1, ax = a + (j ? 1 : 0), bx = b - (last ? 0 : 1);
        const t = `${s.name}, ${c}: ${withUnit(s.v[i], 0, unit)}`;
        g += `<g class="r${j + 1}">${last ? mark(ax, y + 4, bx - ax, 22, 'r', '', t) : rect(ax, y + 4, bx - ax, 22, '', t)}`;
        const lab = fmt(s.v[i]); if (textW(lab) + 10 < bx - ax) g += text((ax + bx) / 2, y + 19, lab, 'in', 'middle');
        g += '</g>'; acc += s.v[i];
      });
      g += text(x0 + acc / sc.max * (x1 - x0) + 6, y + 19, fmt(acc), 'wert');
    });
  } else {
    const x0 = 36, x1 = 432, yb = 216, yt = 28; H = 250; g += achseY(x0, x1, yb, yt, sc, fmt, 'in ' + unit);
    const band = (x1 - x0) / cats.length, bw = Math.min(40, band * .7);
    cats.forEach((c, i) => {
      const x = x0 + band * i + (band - bw) / 2; let acc = 0;
      series.forEach((s, j) => {
        const h = s.v[i] / sc.max * (yb - yt), ya = yb - acc / sc.max * (yb - yt) - h, last = j === series.length - 1, hh = h - (last ? 0 : 1), yy = ya + (last ? 0 : 1);
        const t = `${s.name}, ${c}: ${withUnit(s.v[i], 0, unit)}`;
        g += `<g class="r${j + 1}">${last ? mark(x, yy, bw, hh, 'o', '', t) : rect(x, yy, bw, hh, '', t)}`;
        const lab = fmt(s.v[i]); if (h > 18) g += text(x + bw / 2, ya + h / 2 + 4, lab, 'in', 'middle'); g += '</g>'; acc += s.v[i];
      });
      g += text(x + bw / 2, yb - acc / sc.max * (yb - yt) - 5, fmt(acc), 'wert', 'middle') + `<g class="achse">${text(x + bw / 2, yb + 16, c, '', 'middle')}</g>`;
    });
  }
  return { W, H, g };
}
function linie(d, flaeche) {
  const { cats, series, unit, dec = 0, labelEvery = 1 } = d, W = 440, H = 250, x0 = 36, x1 = 380, yb = 216, yt = 28;
  const mx = Math.max(...series.flatMap(s => s.v)), sc = niceScale(mx * 1.02);
  let g = achseY(x0, x1 + 4, yb, yt, sc, fmt, 'in ' + unit);
  const X = i => x0 + 10 + i * ((x1 - x0 - 20) / (cats.length - 1)), Y = v => yb - v / sc.max * (yb - yt);
  cats.forEach((c, i) => { if (i % labelEvery === 0) g += `<g class="achse">${text(X(i), yb + 16, c, '', 'middle')}</g>`; });
  series.forEach((s, j) => {
    const pts = s.v.map((v, i) => [X(i), Y(v)]), dline = pts.map((p, i) => (i ? 'L' : 'M') + n1(p[0]) + ' ' + n1(p[1])).join('');
    g += `<g class="r${j + 1}">`;
    if (flaeche) g += `<path class="flaeche-fuell" d="${dline}L${n1(pts.at(-1)[0])} ${yb}L${n1(pts[0][0])} ${yb}Z"/>`;
    g += `<path class="l" d="${dline}">${title(s.name)}</path>`;
    const lp = pts.at(-1); g += `<circle class="punkt" cx="${n1(lp[0])}" cy="${n1(lp[1])}" r="4">${title(`${s.name}, ${cats.at(-1)}: ${withUnit(s.v.at(-1), dec, unit)}`)}</circle>`;
    g += text(lp[0] + 9, lp[1] + 4, s.name, 'wert halo') + '</g>';
  });
  return { W, H, g };
}
function torte(d, ring) {
  const { parts, unit, total } = d, W = 440, H = 280, cx = 220, cy = 140, R = 92, r = ring ? 56 : 0;
  const sum = parts.reduce((a, p) => a + p.v, 0), pct = largestRemainder(parts.map(p => p.v));
  let a = -Math.PI / 2, g = '', labels = [];
  const P = (rad, ang) => [cx + rad * Math.cos(ang), cy + rad * Math.sin(ang)];
  parts.forEach((p, i) => {
    const da = p.v / sum * Math.PI * 2, a1 = a + da, big = da > Math.PI ? 1 : 0, [x0, y0] = P(R, a), [x1, y1] = P(R, a1);
    let dd = `M${n1(x0)} ${n1(y0)}A${R} ${R} 0 ${big} 1 ${n1(x1)} ${n1(y1)}`;
    if (ring) { const [x2, y2] = P(r, a1), [x3, y3] = P(r, a); dd += `L${n1(x2)} ${n1(y2)}A${r} ${r} 0 ${big} 0 ${n1(x3)} ${n1(y3)}Z`; } else dd += `L${cx} ${cy}Z`;
    g += `<g class="r${i + 1}"><path class="m ring" d="${dd}">${title(`${p.name}: ${withUnit(p.v, 0, unit)} (${pct[i]} %)`)}</path></g>`;
    const mid = a + da / 2; labels.push({ i, mid, name: p.name, pct: pct[i] }); a = a1;
  });
  ['r', 'l'].forEach(side => { // Beschriftungen je Seite, Mindestabstand 15
    const L = labels.filter(l => (Math.cos(l.mid) >= 0) === (side === 'r')).sort((p, q) => Math.sin(p.mid) - Math.sin(q.mid));
    L.forEach(l => { l.y = cy + (R + 12) * Math.sin(l.mid); }); for (let k = 1; k < L.length; k++) if (L[k].y - L[k - 1].y < 15) L[k].y = L[k - 1].y + 15;
    L.forEach(l => { const x = cx + (R + 12) * Math.cos(l.mid); g += `<text class="wert" x="${n1(x + (side === 'r' ? 4 : -4))}" y="${n1(l.y + 4)}" text-anchor="${side === 'r' ? 'start' : 'end'}">${esc(l.name)} ${l.pct} %</text>`; });
  });
  if (ring) g += text(cx, cy - 2, total, 'wert', 'middle').replace('class="wert"', 'class="wert" font-size="20"') + text(cx, cy + 16, 'gesamt', 'skala-text', 'middle');
  return { W, H, g, pct };
}
function netz(d) {
  const { axes, series, max } = d, W = 440, H = 310, cx = 220, cy = 160, R = 104, n = axes.length;
  const ang = i => -Math.PI / 2 + i * 2 * Math.PI / n, P = (rad, i) => [cx + rad * Math.cos(ang(i)), cy + rad * Math.sin(ang(i))];
  let g = '<g class="achse">';
  for (let l = 1; l <= 5; l++) { const rr = R * l / 5; g += `<polygon class="gitter" points="${axes.map((_, i) => P(rr, i).map(n1).join(',')).join(' ')}"/>`; if (l % 1 === 0) g += text(cx + 5, cy - rr + 12, fmt(max * l / 5), 'skala-text'); }
  axes.forEach((a, i) => { const [x, y] = P(R, i), [lx, ly] = P(R + 14, i); g += `<line class="gitter" x1="${cx}" y1="${cy}" x2="${n1(x)}" y2="${n1(y)}"/>` + text(lx, ly + 4, a, '', Math.abs(lx - cx) < 8 ? 'middle' : lx > cx ? 'start' : 'end'); });
  g += '</g>';
  series.forEach((s, j) => {
    const pts = s.v.map((v, i) => P(R * v / max, i));
    g += `<g class="r${j + 1}"><polygon class="flaeche-fuell l" points="${pts.map(p => p.map(n1).join(',')).join(' ')}">${title(s.name)}</polygon>`;
    pts.forEach((p, i) => g += `<circle class="punkt" cx="${n1(p[0])}" cy="${n1(p[1])}" r="4">${title(`${s.name}, ${axes[i]}: ${fmt(s.v[i], 1)}`)}</circle>`);
    g += '</g>';
  });
  return { W, H, g };
}
function waffel(d) {
  const { parts } = d, W = 440, H = 214, cell = 15, gap = 3.2, x0 = 20, y0 = 14; const pct = largestRemainder(parts.map(p => p.v));
  const seq = []; pct.forEach((p, i) => { for (let k = 0; k < p; k++) seq.push(i); });
  let g = ''; // Reihenweise von unten links nach oben rechts, Reihe 1 = unten
  seq.forEach((i, k) => { const col = k % 10, row = Math.floor(k / 10), x = x0 + col * (cell + gap), y = y0 + (9 - row) * (cell + gap); g += `<g class="r${i + 1}">${rect(x, y, cell, cell, '', `${parts[i].name}: ${pct[i]} %`, ' rx="3"')}</g>`; });
  parts.forEach((p, i) => { const y = 40 + i * 44; g += `<g class="r${i + 1}"><rect class="m" x="230" y="${y - 14}" width="14" height="14" rx="3"/></g>` + text(254, y, pct[i] + ' %', 'wert').replace('class="wert"', 'class="wert" font-size="19"') + text(254, y + 18, p.name, ''); });
  g += text(230, 196, '1 Kästchen = 1 % der Gesamtmenge', 'skala-text');
  return { W, H, g, pct };
}
function wasserfall(d) {
  const { items, unit } = d, W = 440, H = 250, x0 = 36, x1 = 432, yb = 216, yt = 30; let run = 0; const bars = [];
  items.forEach(it => { if (it.sum) { const t = it.v ?? run; bars.push({ a: 0, b: t, sum: true, name: it.name, v: t }); run = t; } else { bars.push({ a: run, b: run + it.v, name: it.name, v: it.v }); run += it.v; } });
  const mx = Math.max(...bars.flatMap(b => [b.a, b.b])), sc = niceScale(mx * 1.04), band = (x1 - x0) / bars.length, bw = Math.min(36, band * .7);
  let g = achseY(x0, x1, yb, yt, sc, fmt, 'in ' + unit); const Y = v => yb - v / sc.max * (yb - yt);
  bars.forEach((b, i) => {
    const x = x0 + band * i + (band - bw) / 2, hi = Math.max(b.a, b.b), lo = Math.min(b.a, b.b), cls = b.sum ? 'summe' : b.v >= 0 ? 'plus' : 'minus';
    const lab = b.sum ? fmt(b.v) : (b.v >= 0 ? '+' : '−') + fmt(Math.abs(b.v));
    g += `<g class="${cls}">${mark(x, Y(hi), bw, Y(lo) - Y(hi), b.sum || b.v >= 0 ? 'o' : 'u', '', `${b.name}: ${b.sum ? 'Summe ' : b.v >= 0 ? 'Anstieg ' : 'Rückgang '}${lab} ${unit}`)}</g>`;
    g += text(x + bw / 2, Y(hi) - 5, lab, 'wert', 'middle') + `<g class="achse">${text(x0 + band * i + band / 2, yb + 16, b.name, '', 'middle')}</g>`;
    if (i < bars.length - 1) g += `<line class="verbinder" x1="${n1(x + bw)}" x2="${n1(x + band)}" y1="${n1(Y(b.b))}" y2="${n1(Y(b.b))}"/>`;
  });
  return { W, H, g };
}
function heatmap(d) {
  const { rows, cols, v, unit, dec = 0 } = d, W = 440, x0 = 92, top = 22, cw = (432 - x0) / cols.length, ch = 28, H = top + rows.length * ch + 6;
  const flat = v.flat(), mn = Math.min(...flat), mx = Math.max(...flat), step = (mx - mn) / 5;
  const cls = x => 's' + Math.min(5, 1 + Math.floor((x - mn) / step)); let g = '<g class="achse">';
  cols.forEach((c, j) => g += text(x0 + cw * j + cw / 2, 14, c, '', 'middle')); rows.forEach((r, i) => g += text(x0 - 8, top + i * ch + 18, r, '', 'end')); g += '</g>';
  rows.forEach((r, i) => cols.forEach((c, j) => { const x = v[i][j]; g += `<g class="${cls(x)}">${rect(x0 + cw * j + 1, top + i * ch + 1, cw - 2, ch - 2, '', `${r}, ${c}: ${withUnit(x, dec, unit)}`, ' rx="3"')}` + text(x0 + cw * j + cw / 2, top + i * ch + 19, fmt(x, dec), 'in', 'middle') + '</g>'; }));
  const bounds = [0, 1, 2, 3, 4, 5].map(k => mn + step * k);
  return { W, H, g, leg: [1, 2, 3, 4, 5].map(k => ({ cls: 's' + k, name: `${fmt(bounds[k - 1], dec)}–${fmt(bounds[k], dec)}` })) };
}
function kalender(d) {
  const { year, vals, unit } = d, W = 640, cell = 10, gap = 1.4, x0 = 28, y0 = 30, H = 30 + 7 * (cell + gap) + 12;
  const mx = Math.max(...Object.values(vals)); const cls = v => v === 0 ? 's0' : 's' + Math.min(5, 1 + Math.floor((v - 1) / (mx / 5)));
  const jan1 = new Date(Date.UTC(year, 0, 1)), off = (jan1.getUTCDay() + 6) % 7; // Montag = 0
  const mon = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'], mn = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
  let g = '<g class="achse">' + ['Mo', 'Mi', 'Fr'].map((t, k) => text(x0 - 5, y0 + k * 2 * (cell + gap) + 9, t, '', 'end')).join('');
  let lastM = -1, cells = '';
  for (let k = 0; k < 365; k++) {
    const dt = new Date(Date.UTC(year, 0, 1 + k)), iso = dt.toISOString().slice(0, 10), pos = k + off, wk = Math.floor(pos / 7), wd = pos % 7, v = vals[iso] ?? 0;
    const x = x0 + wk * (cell + gap), y = y0 + wd * (cell + gap);
    if (dt.getUTCMonth() !== lastM && dt.getUTCDate() <= 7 && wd < 7) { lastM = dt.getUTCMonth(); g += text(x, y0 - 8, mon[lastM], ''); }
    cells += `<g class="${cls(v)}">${rect(x, y, cell, cell, '', `${dt.getUTCDate()}. ${mn[dt.getUTCMonth()]}: ${v ? withUnit(v, 0, unit) : 'keine Daten'}`, ' rx="2"')}</g>`;
  }
  const edges = []; for (let k = 0; k <= 5; k++) edges.push(k === 0 ? 0 : Math.round(k * mx / 5));
  return { W, H, g: g + '</g>' + cells, leg: [{ cls: 's0', name: '0' }, ...[1, 2, 3, 4, 5].map(k => ({ cls: 's' + k, name: `${edges[k - 1] + (k > 1 ? 1 : 1)}–${edges[k]}` }))] };
}
function sankey(d) {
  const { cols, links, unit } = d, W = 440, H = 250, nodeW = 10, xs = [74, 215, 356], gapN = 8, top = 6;
  const names = cols.flat(), out = {}, inn = {}; names.forEach(n => { out[n] = 0; inn[n] = 0; }); links.forEach(([a, b, v]) => { out[a] += v; inn[b] += v; });
  const val = n => Math.max(out[n], inn[n]);
  const maxCol = Math.max(...cols.map(c => c.reduce((s, n) => s + val(n), 0) + (c.length - 1) * gapN)), scale = (H - top - 8 - (Math.max(...cols.map(c => c.length)) - 1) * gapN) / Math.max(...cols.map(c => c.reduce((s, n) => s + val(n), 0)));
  const pos = {}; cols.forEach((c, ci) => { let y = top; c.forEach(n => { pos[n] = { x: xs[ci], y, h: val(n) * scale, oy: 0, iy: 0, col: ci }; y += val(n) * scale + gapN; }); });
  let g = '', bands = '', nodes = '';
  const order = names; const sorted = [...links].sort((p, q) => order.indexOf(p[1]) - order.indexOf(q[1]));
  const byOut = [...links].sort((p, q) => order.indexOf(p[1]) - order.indexOf(q[1]));
  const outOff = {}, inOff = {}; names.forEach(n => { outOff[n] = 0; inOff[n] = 0; });
  const inSorted = [...links].sort((p, q) => order.indexOf(p[0]) - order.indexOf(q[0]));
  const yIn = new Map(); inSorted.forEach(l => { yIn.set(l, pos[l[1]].y + inOff[l[1]]); inOff[l[1]] += l[2] * scale; });
  byOut.forEach(l => { const [a, b, v] = l, w = v * scale, y0 = pos[a].y + outOff[a] + w / 2, y1 = yIn.get(l) + w / 2; outOff[a] += w; const xa = pos[a].x + nodeW, xb = pos[b].x, xm = (xa + xb) / 2;
    const src = pos[a].col === 0 ? 'r' + (cols[0].indexOf(a) + 1) : 'neutral';
    bands += `<g class="${src}"><path class="band" data-von="${esc(a)}" data-nach="${esc(b)}" d="M${xa} ${n1(y0)}C${n1(xm)} ${n1(y0)} ${n1(xm)} ${n1(y1)} ${xb} ${n1(y1)}" stroke-width="${n1(w)}">${title(`${a} → ${b}: ${fmt(v)} ${unit}`)}</path></g>`; });
  cols.forEach((c, ci) => c.forEach((n, k) => { const p = pos[n], cls = ci === 0 ? 'r' + (k + 1) : 'neutral';
    nodes += `<g class="knoten ${cls}" data-knoten="${esc(n)}">${rect(p.x, p.y, nodeW, p.h, '', `${n}: ${fmt(val(n))} ${unit}`)}`;
    nodes += ci === 0 ? text(p.x - 6, p.y + p.h / 2 + 4, n, 'wert', 'end') : ci === 1 ? text(p.x + nodeW + 5, p.y + p.h / 2 + 4, n, 'wert halo') : text(p.x + nodeW + 6, p.y + p.h / 2 + 4, n, 'wert'); nodes += '</g>'; }));
  return { W, H, g: bands + nodes };
}
function squarify(items, x, y, w, h) { // items: [{v,...}] absteigend → Rechtecke
  const total = items.reduce((s, i) => s + i.v, 0), k = w * h / total, res = []; let row = [], rest = items.map(i => ({ ...i, a: i.v * k }));
  const worst = (r, side) => { const s = r.reduce((a, b) => a + b.a, 0), mx = Math.max(...r.map(i => i.a)), mn = Math.min(...r.map(i => i.a)); return Math.max(side * side * mx / (s * s), s * s / (side * side * mn)); };
  while (rest.length) {
    const side = Math.min(w, h), it = rest[0];
    if (!row.length || worst([...row, it], side) <= worst(row, side)) { row.push(it); rest.shift(); }
    else { const s = row.reduce((a, b) => a + b.a, 0); if (w >= h) { const rw = s / h; let yy = y; row.forEach(r => { res.push({ ...r, x, y: yy, w: rw, h: r.a / rw }); yy += r.a / rw; }); x += rw; w -= rw; } else { const rh = s / w; let xx = x; row.forEach(r => { res.push({ ...r, x: xx, y, w: r.a / rh, h: rh }); xx += r.a / rh; }); y += rh; h -= rh; } row = []; }
  }
  if (row.length) { const s = row.reduce((a, b) => a + b.a, 0); if (w >= h) { const rw = s / h; let yy = y; row.forEach(r => { res.push({ ...r, x, y: yy, w: rw, h: r.a / rw }); yy += r.a / rw; }); } else { const rh = s / w; let xx = x; row.forEach(r => { res.push({ ...r, x: xx, y, w: r.a / rh, h: rh }); xx += r.a / rh; }); } }
  return res;
}
function baumkarte(d) {
  const { groups, unit } = d, W = 440, H = 280, gp = 2;
  const G = groups.map(g => ({ ...g, v: g.kids.reduce((s, k) => s + k.v, 0) })).sort((a, b) => b.v - a.v);
  const tiles = squarify(G, 0, 0, W, H); let g1 = '', g2 = '';
  const label = (x, y, w, h, name, val, cls) => { let s = ''; if (w > textW(name) + 14 && h > 34) { s += `<text class="in" x="${n1(x + 7)}" y="${n1(y + 18)}">${esc(name)}</text>`; if (h > 50) s += `<text class="in" x="${n1(x + 7)}" y="${n1(y + 34)}" font-weight="400">${esc(val)}</text>`; } else if (w > 26 && h > 18) s += `<text class="in" x="${n1(x + 5)}" y="${n1(y + 14)}" font-size="11">${esc(name.slice(0, Math.max(2, Math.floor((w - 8) / 6.2))))}${name.length > (w - 8) / 6.2 ? '…' : ''}</text>`; return s; };
  tiles.forEach((t, i) => {
    const cls = 'r' + (i + 1), id = 'g' + (i + 1);
    g1 += `<g class="gruppe ${cls}" data-gruppe="${id}" tabindex="0" role="button" aria-label="${esc(t.name)}: ${fmt(t.v)} ${unit}, Untergruppen zeigen">${rect(t.x + gp / 2, t.y + gp / 2, t.w - gp, t.h - gp, '', `${t.name}: ${fmt(t.v)} ${unit} (${Math.round(t.v / G.reduce((s, q) => s + q.v, 0) * 100)} %)`, ' rx="3"')}${label(t.x + gp / 2, t.y + gp / 2, t.w - gp, t.h - gp, t.name, fmt(t.v) + NB + unit)}</g>`;
    const kids = squarify([...t.kids].sort((a, b) => b.v - a.v), 0, 0, W, H);
    g2 += `<g class="ebene ${cls}" data-gruppe="${id}" hidden><g class="achse-leer">${kids.map(k => `<g>${rect(k.x + gp / 2, k.y + gp / 2, k.w - gp, k.h - gp, '', `${t.name} › ${k.name}: ${fmt(k.v)} ${unit}`, ' rx="3"')}${label(k.x + gp / 2, k.y + gp / 2, k.w - gp, k.h - gp, k.name, fmt(k.v) + NB + unit)}</g>`).join('')}</g></g>`;
  });
  return { W, H, g: `<g class="ebene-1">${g1}</g>${g2}`, G };
}
function blasen(d) {
  const { pts, cats, xU, yU } = d, W = 440, H = 290, x0 = 40, x1 = 424, yb = 252, yt = 22, xmax = 60, ymin = 60, ymax = 85;
  const X = v => x0 + v / xmax * (x1 - x0), Y = v => yb - (v - ymin) / (ymax - ymin) * (yb - yt), maxS = Math.max(...pts.map(p => p.s)), R = s => 4 + 20 * Math.sqrt(s / maxS);
  let g = '<g class="achse">'; for (let v = 0; v <= xmax; v += 10) g += `<line class="gitter" x1="${n1(X(v))}" x2="${n1(X(v))}" y1="${yt}" y2="${yb}"/>` + text(X(v), yb + 16, fmt(v), '', 'middle');
  for (let v = ymin; v <= ymax; v += 5) g += `<line class="${v === ymin ? 'basis' : 'gitter'}" x1="${x0}" x2="${x1}" y1="${n1(Y(v))}" y2="${n1(Y(v))}"/>` + text(x0 - 6, Y(v) + 4, fmt(v), '', 'end');
  g += text(0, 10, yU, 'achsentitel') + text(x1, H - 4, xU, 'achsentitel', 'end') + '</g>';
  const top5 = [...pts].sort((a, b) => b.s - a.s).slice(0, 5).map(p => p.name);
  [...pts].sort((a, b) => b.s - a.s).forEach(p => { const c = cats.indexOf(p.c) + 1; g += `<g class="r${c}"><circle class="m ring" cx="${n1(X(p.x))}" cy="${n1(Y(p.y))}" r="${n1(R(p.s))}" fill-opacity=".86">${title(`${p.name}: ${fmt(p.x)} ${xU}, ${fmt(p.y)} ${yU}, ${fmt(p.s)} Mio. Einwohner`)}</circle></g>`; });
  [...pts].filter(p => top5.includes(p.name)).forEach(p => g += text(X(p.x), Y(p.y) + 4, p.name, 'wert halo', 'middle'));
  return { W, H, g };
}
function karte(d) {
  const { tiles, vals, unit } = d, W = 440, tw = 70, th = 52, gap = 3, ox = 4, oy = 4, H = oy + 5 * (th + gap) + 4;
  const v = Object.values(vals).sort((a, b) => a - b), q = k => v[Math.min(v.length - 1, Math.floor(v.length * k / 5))], b = [v[0], q(1), q(2), q(3), q(4), v.at(-1)];
  const cls = x => 's' + (x <= b[1] ? 1 : x <= b[2] ? 2 : x <= b[3] ? 3 : x <= b[4] ? 4 : 5);
  let g = ''; Object.entries(tiles).forEach(([code, [c, r, name]]) => {
    const x = ox + c * (tw + gap), y = oy + r * (th + gap), val = vals[code];
    if (val === undefined) g += `<g><rect class="region-leer" data-region="DE-${code}" x="${x}" y="${y}" width="${tw}" height="${th}" rx="4">${title(`${name}: keine Daten`)}</rect>${text(x + 6, y + 18, code, 'skala-text')}${text(x + 6, y + 36, 'keine Daten', 'skala-text').replace('<text', '<text font-size="11"')}</g>`;
    else g += `<g class="${cls(val)}">${rect(x, y, tw, th, '', `${name}: ${withUnit(val, 1, unit)}`, ` rx="4" data-region="DE-${code}"`).replace('class="m ', 'class="m region ')}${text(x + 6, y + 18, code, 'in')}${text(x + 6, y + 38, fmt(val, 1), 'in').replace('class="in"', 'class="in" font-weight="400"')}</g>`;
  });
  return { W, H, g, leg: [1, 2, 3, 4, 5].map(k => ({ cls: 's' + k, name: `${fmt(b[k - 1], 1)}–${fmt(b[k], 1)}` })), b };
}

/* ---------------- Beispieldaten (erfunden) ---------------- */
const monate = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
const F = [];
const fig = {};
function add(key, f) { fig[key] = f; }
const syn = s => `<pre class="d-syntax">${esc(s)}</pre>`;

// 1 Balken
{ const d = { cats: ['Gletscher in den Ostalpen', 'Hohe Tauern', 'Ötztaler Alpen', 'Silvretta', 'Bernina', 'Mont-Blanc-Gruppe'], v: [32, 28, 24, 19, 14, 9], unit: '%' }; const r = balken(d);
  add('balken', { f: figure({ typ: 'balken', titel: 'Anteil an der Eisfläche nach Region', beschr: 'Die Ostalpen tragen mit 32 % den größten Anteil, die Mont-Blanc-Gruppe mit 9 % den kleinsten.', h: r.H, svg: r.g, tab: tabelle(['Region', 'Anteil in %'], d.cats.map((c, i) => [c, fmt(d.v[i])])), cap: '<b>Abbildung 1:</b> Anteil der Regionen an der Eisfläche (Beispielzahlen). Quelle: erfunden, Stand 2026.' }),
    s: `<figure class="chart" data-type="bar" data-unit="%">\n<table><thead><tr><th></th><th>Anteil</th></tr></thead>\n<tbody><tr><th>Gletscher in den Ostalpen</th><td>32</td></tr> …</tbody></table>\n<figcaption>Beschreibung, Quelle, Stand</figcaption></figure>` }); }
// 2 Säulen
{ const d = { cats: ['2021', '2022', '2023', '2024', '2025'], series: [{ name: 'Nordseite', v: [-1.1, -1.4, -1.9, -2.3, -2.8].map(Math.abs) }, { name: 'Südseite', v: [0.9, 1.2, 1.6, 2.1, 2.4] }], unit: 'm', dec: 1 }; const r = saeulen(d);
  add('saeulen', { f: figure({ typ: 'saeulen', titel: 'Eisverlust je Jahr nach Seite', beschr: 'Der Eisverlust steigt auf beiden Seiten, 2025 mit 2,8 m auf der Nordseite am höchsten.', h: r.H, svg: r.g, leg: legende(d.series.map((s, i) => ({ cls: 'r' + (i + 1), name: s.name }))), tab: tabelle(['Jahr', 'Nordseite in m', 'Südseite in m'], d.cats.map((c, i) => [c, fmt(d.series[0].v[i], 1), fmt(d.series[1].v[i], 1)])), cap: '<b>Abbildung 2:</b> Mittlerer Eisverlust je Jahr in Metern Dicke (Beispielzahlen).' }),
    s: `<figure class="chart" data-type="column" data-unit="m">\n<table><thead><tr><th></th><th>Nordseite</th><th>Südseite</th></tr></thead>\n<tbody><tr><th>2021</th><td>1,1</td><td>0,9</td></tr> …</tbody></table> …` }); }
// 3 Linie
{ const d = { cats: monate, series: [{ name: 'Nord', v: [3, 3, 5, 9, 18, 31, 38, 33, 19, 10, 6, 4] }, { name: 'Mitte', v: [5, 5, 8, 14, 26, 42, 50, 44, 27, 15, 9, 6] }, { name: 'Süd', v: [8, 8, 12, 21, 36, 55, 66, 58, 36, 20, 12, 9] }], unit: 'm³/s', labelEvery: 2 }; const r = linie(d);
  add('linie', { f: figure({ typ: 'linie', titel: 'Abfluss je Monat an drei Pegeln', beschr: 'Alle drei Pegel erreichen im Juli ihr Maximum; am Südpegel fließen im Juli 66 Kubikmeter pro Sekunde.', h: r.H, svg: r.g, leg: legende(d.series.map((s, i) => ({ cls: 'r' + (i + 1), name: s.name }))), tab: tabelle(['Monat', ...d.series.map(s => s.name + ' in m³/s')], monate.map((m, i) => [m, ...d.series.map(s => fmt(s.v[i]))])), cap: '<b>Abbildung 3:</b> Mittlerer Abfluss je Monat in Kubikmetern pro Sekunde (Beispielzahlen).', extra: '' }),
    s: `<figure class="chart" data-type="line" data-unit="m³/s" [data-interactive="true"]> … (Interaktion nur auf Wunsch, Phase 8)` }); }
// 4 Fläche
{ const d = { cats: ['2016', '2017', '2018', '2019', '2020', '2021', '2022', '2023', '2024', '2025'], series: [{ name: 'Schnee', v: [62, 60, 58, 55, 57, 50, 46, 44, 41, 38] }, { name: 'Eis', v: [40, 39, 38, 36, 35, 33, 31, 29, 27, 26] }], unit: 'km²', labelEvery: 2 }; const r = linie(d, true);
  add('flaeche', { f: figure({ typ: 'flaeche', titel: 'Schnee- und Eisfläche seit 2016', beschr: 'Beide Flächen schrumpfen seit 2016; die Schneefläche von 62 auf 38 Quadratkilometer.', h: r.H, svg: r.g, leg: legende(d.series.map((s, i) => ({ cls: 'r' + (i + 1), name: s.name }))), tab: tabelle(['Jahr', 'Schnee in km²', 'Eis in km²'], d.cats.map((c, i) => [c, fmt(d.series[0].v[i]), fmt(d.series[1].v[i])])), cap: '<b>Abbildung 4:</b> Flächen im Beobachtungsgebiet (Beispielzahlen).' }), s: `<figure class="chart" data-type="area" data-unit="km²"> … ` }); }
// 5 Balken gestapelt
{ const d = { cats: ['Nord', 'Mitte', 'Süd', 'West', 'Ost'], series: [{ name: 'Schnee', v: [30, 24, 18, 22, 12] }, { name: 'Firn', v: [22, 20, 16, 14, 10] }, { name: 'Eis', v: [18, 26, 30, 12, 20] }], unit: 'km²' }; const r = stack(d, true);
  add('balkenGestapelt', { f: figure({ typ: 'balken-gestapelt', titel: 'Fläche nach Oberfläche und Gebiet', beschr: 'Im Süden überwiegt Eis, im Norden Schnee; die größte Gesamtfläche hat der Norden mit 70 Quadratkilometern.', h: r.H, svg: r.g, leg: legende(d.series.map((s, i) => ({ cls: 'r' + (i + 1), name: s.name }))), tab: tabelle(['Gebiet', ...d.series.map(s => s.name + ' in km²')], d.cats.map((c, i) => [c, ...d.series.map(s => fmt(s.v[i]))])), cap: '<b>Abbildung 5:</b> Flächen nach Oberfläche (Beispielzahlen).' }), s: `<figure class="chart" data-type="stacked-bar" data-unit="km²"> … ` }); }
// 6 Säulen gestapelt
{ const d = { cats: ['2020', '2021', '2022', '2023', '2024', '2025'], series: [{ name: 'Wasserkraft', v: [14, 15, 12, 13, 15, 16] }, { name: 'Wind', v: [8, 10, 13, 16, 19, 22] }, { name: 'Sonne', v: [4, 6, 9, 12, 16, 21] }], unit: 'TWh' }; const r = stack(d, false);
  add('saeulenGestapelt', { f: figure({ typ: 'saeulen-gestapelt', titel: 'Erzeugung erneuerbarer Energie nach Quelle', beschr: 'Die Erzeugung wächst von 26 auf 59 Terawattstunden; Wind und Sonne tragen den Zuwachs.', h: r.H, svg: r.g, leg: legende(d.series.map((s, i) => ({ cls: 'r' + (i + 1), name: s.name }))), tab: tabelle(['Jahr', ...d.series.map(s => s.name + ' in TWh')], d.cats.map((c, i) => [c, ...d.series.map(s => fmt(s.v[i]))])), cap: '<b>Abbildung 6:</b> Erzeugung je Jahr (Beispielzahlen).' }), s: `<figure class="chart" data-type="stacked-column" data-unit="TWh"> … ` }); }
// 7 Kreis, 8 Ring
const kreisDaten = { parts: [{ name: 'Wald', v: 46 }, { name: 'Wiese', v: 27 }, { name: 'Fels', v: 15 }, { name: 'Eis', v: 9 }, { name: 'See', v: 3 }], unit: '%' };
{ const r = torte(kreisDaten);
  add('kreis', { f: figure({ typ: 'kreis', titel: 'Flächennutzung im Gebiet', beschr: 'Wald bedeckt 46 % der Fläche, Wiese 27 %; See und Eis zusammen 12 %.', h: r.H, svg: r.g, leg: legende(kreisDaten.parts.map((s, i) => ({ cls: 'r' + (i + 1), name: s.name }))), tab: tabelle(['Nutzung', 'Anteil in %'], kreisDaten.parts.map(p => [p.name, fmt(p.v)])), cap: '<b>Abbildung 7:</b> Flächennutzung (Beispielzahlen). Höchstens sechs Teile.' }), s: `<figure class="chart" data-type="pie" data-unit="%"> … ` }); }
{ const r = torte({ ...kreisDaten, total: '100 %' }, true);
  add('ring', { f: figure({ typ: 'ring', titel: 'Flächennutzung im Gebiet als Ring', beschr: 'Wald bedeckt 46 % der Fläche, Wiese 27 %; See und Eis zusammen 12 %.', h: r.H, svg: r.g, leg: legende(kreisDaten.parts.map((s, i) => ({ cls: 'r' + (i + 1), name: s.name }))), tab: tabelle(['Nutzung', 'Anteil in %'], kreisDaten.parts.map(p => [p.name, fmt(p.v)])), cap: '<b>Abbildung 8:</b> Dieselben Zahlen als Ring; die Mitte nennt die Gesamtsumme.' }), s: `<figure class="chart" data-type="donut" data-unit="%"> … ` }); }
// 9 Netz
{ const d = { axes: ['Datenqualität', 'Abdeckung', 'Aktualität', 'Genauigkeit', 'Zugang', 'Kosten'], max: 10, series: [{ name: 'Station A', v: [9, 6, 8, 7, 5, 4] }, { name: 'Station B', v: [5, 9, 6, 8, 8, 7] }, { name: 'Station C', v: [7, 5, 9, 4, 6, 9] }] }; const r = netz(d);
  add('netz', { f: figure({ typ: 'netz', titel: 'Bewertung dreier Messstationen in sechs Kriterien', beschr: 'Station A glänzt bei Datenqualität, B bei Abdeckung, C bei Aktualität und Kosten.', h: r.H, svg: r.g, leg: legende(d.series.map((s, i) => ({ cls: 'r' + (i + 1), name: s.name }))), tab: tabelle(['Kriterium', ...d.series.map(s => s.name)], d.axes.map((a, i) => [a, ...d.series.map(s => fmt(s.v[i]))])), cap: '<b>Abbildung 9:</b> Punkte von 0 bis 10 (Beispielzahlen). Höchstens drei Reihen.' }), s: `<figure class="chart" data-type="radar"> … ` }); }
// 10 Waffel
{ const d = { parts: [{ name: 'Wald', v: 46 }, { name: 'Wiese', v: 27 }, { name: 'Sonstiges', v: 27 }] }; const r = waffel(d);
  add('waffel', { f: figure({ typ: 'waffel', titel: 'Flächenanteile als Waffel', beschr: 'Von 100 Kästchen sind 46 Wald, 27 Wiese und 27 Sonstiges.', h: r.H, svg: r.g, tab: tabelle(['Nutzung', 'Anteil in %'], d.parts.map((p, i) => [p.name, String(r.pct[i])])), cap: '<b>Abbildung 10:</b> Anteile, gerundet auf ganze Prozent, Summe 100 (Beispielzahlen).' }), s: `<figure class="chart" data-type="waffle" data-unit="%"> … ` }); }
// 11 Wasserfall
{ const d = { items: [{ name: 'Start 2020', sum: true, v: 100 }, { name: 'Zubau', v: 12 }, { name: 'Abgang', v: -5 }, { name: 'Zubau', v: 8 }, { name: 'Abgang', v: -14 }, { name: 'Ende 2025', sum: true }], unit: 'km²' }; const r = wasserfall(d);
  add('wasserfall', { f: figure({ typ: 'wasserfall', titel: 'Veränderung der Fläche von 2020 bis 2025', beschr: 'Von 100 Quadratkilometern bleiben nach Zubau von 20 und Abgängen von 19 genau 101 übrig.', h: r.H, svg: r.g, tab: tabelle(['Posten', 'Wert in km²'], [['Start 2020', '100'], ['Zubau', '+12'], ['Abgang', '−5'], ['Zubau', '+8'], ['Abgang', '−14'], ['Ende 2025', '101']], [0, 5]), cap: '<b>Abbildung 11:</b> Summen grau, Anstieg blau mit „+“, Rückgang rot mit „−“ (Beispielzahlen).' }), s: `<figure class="chart" data-type="waterfall" data-unit="km²">\n<table>… <tr data-sum="true"><th>Start 2020</th><td>100</td></tr><tr><th>Zubau</th><td>+12</td></tr> …` }); }
// 12 Heatmap
{ const rows = ['Station Nord', 'Station Mitte', 'Station Süd', 'Station West', 'Station Ost'], cols = ['Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt']; const v = [[12, 22, 41, 58, 70, 64, 38, 20], [10, 18, 36, 50, 62, 57, 33, 15], [18, 30, 52, 71, 86, 80, 49, 27], [14, 25, 40, 55, 66, 60, 36, 22], [8, 15, 30, 46, 57, 52, 29, 13]]; const d = { rows, cols, v, unit: 'mm' }; const r = heatmap(d);
  add('heatmap', { f: figure({ typ: 'heatmap', titel: 'Niederschlag je Station und Monat', beschr: 'Der meiste Niederschlag fällt im Juli, am stärksten an der Südstation mit 86 Millimetern.', h: r.H, svg: r.g, leg: legende(r.leg, true), tab: tabelle(['Station', ...cols.map(c => c + ' in mm')], rows.map((x, i) => [x, ...v[i].map(z => fmt(z))])), cap: '<b>Abbildung 12:</b> Monatssummen in Millimetern, fünf gleich breite Klassen (Beispielzahlen).' }), s: `<figure class="chart" data-type="heatmap" data-unit="mm"> … ` }); }
// 13 Kalender
{ let seed = 7; const rnd = () => (seed = (seed * 16807) % 2147483647) / 2147483647; const vals = {}; for (let k = 0; k < 365; k++) { const dt = new Date(Date.UTC(2026, 0, 1 + k)), iso = dt.toISOString().slice(0, 10), m = dt.getUTCMonth(), wd = (dt.getUTCDay() + 6) % 7; const base = 3 + 5 * Math.sin((m - 2) / 12 * Math.PI * 2 * .9) + (wd > 4 ? -3 : 1); vals[iso] = rnd() < .12 ? 0 : Math.max(0, Math.round(base + rnd() * 6)); }
  const r = kalender({ year: 2026, vals, unit: 'Messungen' });
  add('kalender', { f: figure({ typ: 'kalender', titel: 'Messungen pro Tag im Jahr 2026', beschr: 'Im Sommer wird an Werktagen am häufigsten gemessen, am Wochenende und im Winter seltener.', w: r.W, h: r.H, svg: r.g, breit: true, leg: legende(r.leg, true), tab: tabelle(['Datum', 'Messungen'], Object.entries(vals).slice(0, 6).concat([['…', '…']]).map(([a, b]) => [a, String(b)])), cap: '<b>Abbildung 13:</b> Je Kästchen ein Tag, Wochen als Spalten, Montag oben (Beispielzahlen). Am Handy seitlich wischen.' }), s: `<figure class="chart" data-type="calendar" data-unit="Messungen">\n<table><tbody><tr><th>2026-01-01</th><td>4</td></tr> …</tbody></table> …` }); }
// 14 Sankey
{ const d = { cols: [['Kohle', 'Gas', 'Wind', 'Sonne'], ['Strom', 'Wärme', 'Treibstoff'], ['Haushalte', 'Industrie', 'Gewerbe', 'Mobilität']], links: [['Kohle', 'Strom', 18], ['Kohle', 'Wärme', 8], ['Gas', 'Strom', 14], ['Gas', 'Wärme', 16], ['Gas', 'Treibstoff', 5], ['Wind', 'Strom', 20], ['Sonne', 'Strom', 10], ['Sonne', 'Wärme', 4], ['Strom', 'Haushalte', 25], ['Strom', 'Industrie', 30], ['Strom', 'Gewerbe', 7], ['Wärme', 'Haushalte', 18], ['Wärme', 'Industrie', 10], ['Treibstoff', 'Mobilität', 5]], unit: 'TWh' }; const r = sankey(d);
  add('sankey', { f: figure({ typ: 'sankey', titel: 'Energiefluss von der Quelle zum Verbraucher', beschr: 'Strom nimmt mit 62 von 95 Terawattstunden den größten Teil auf; Industrie und Haushalte verbrauchen am meisten.', h: r.H, svg: r.g, tab: tabelle(['Von', 'Nach', 'Wert in TWh'], d.links.map(l => [l[0], l[1], String(l[2])])), cap: '<b>Abbildung 14:</b> Energiefluss in Terawattstunden (Beispielzahlen). Bandbreite = Menge.' }), s: `<figure class="chart" data-type="sankey" data-unit="TWh"> <table><thead><tr><th>Von</th><th>Nach</th><th>Wert</th></tr></thead> … ` }); }
// 15 Baumkarte
{ const d = { unit: '€', groups: [{ name: 'Wohnen', kids: [{ name: 'Miete', v: 520 }, { name: 'Nebenkosten', v: 140 }, { name: 'Energie', v: 90 }] }, { name: 'Mobilität', kids: [{ name: 'Auto', v: 210 }, { name: 'ÖPNV', v: 60 }, { name: 'Bahn', v: 40 }] }, { name: 'Essen', kids: [{ name: 'Lebensmittel', v: 320 }, { name: 'Restaurant', v: 90 }] }, { name: 'Freizeit', kids: [{ name: 'Reisen', v: 150 }, { name: 'Kultur', v: 60 }, { name: 'Sport', v: 40 }] }, { name: 'Sonstiges', kids: [{ name: 'Versicherung', v: 50 }, { name: 'Rest', v: 30 }] }] }; const r = baumkarte(d);
  add('baumkarte', { f: figure({ typ: 'baumkarte', titel: 'Haushaltsausgaben nach Bereich und Posten', beschr: 'Wohnen ist mit 750 Euro der größte Bereich, gefolgt von Essen mit 410 und Mobilität mit 310 Euro.', h: r.H, svg: r.g, tab: tabelle(['Bereich', 'Posten', 'Wert in €'], d.groups.flatMap(g => g.kids.map(k => [g.name, k.name, String(k.v)]))), cap: '<b>Abbildung 15:</b> Monatliche Ausgaben in Euro (Beispielzahlen). Fläche = Betrag. <b>Bedienen:</b> Bereich anklicken zeigt die Posten (Interaktion nur mit data-interactive).', extra: ' data-interaktiv' }), s: `<figure class="chart" data-type="treemap" data-unit="€"> <table><thead><tr><th>Gruppe</th><th>Untergruppe</th><th>Wert</th></tr></thead> … ` }); }
// 16 Blasen
{ const cats = ['Europa', 'Asien', 'Amerika']; const pts = [{ name: 'Schweiz', x: 58, y: 84, s: 9, c: 'Europa' }, { name: 'Deutschland', x: 49, y: 81, s: 84, c: 'Europa' }, { name: 'Italien', x: 36, y: 83, s: 59, c: 'Europa' }, { name: 'Polen', x: 20, y: 77, s: 38, c: 'Europa' }, { name: 'Japan', x: 38, y: 84, s: 124, c: 'Asien' }, { name: 'Indien', x: 7, y: 70, s: 1420, c: 'Asien' }, { name: 'China', x: 16, y: 78, s: 1410, c: 'Asien' }, { name: 'Vietnam', x: 5, y: 74, s: 99, c: 'Asien' }, { name: 'USA', x: 56, y: 78, s: 335, c: 'Amerika' }, { name: 'Brasilien', x: 12, y: 74, s: 216, c: 'Amerika' }, { name: 'Kolumbien', x: 8, y: 77, s: 52, c: 'Amerika' }, { name: 'Chile', x: 16, y: 80, s: 19, c: 'Amerika' }]; const r = blasen({ pts, cats, xU: 'Einkommen je Kopf in 1 000 €', yU: 'Lebenserwartung in Jahren' });
  add('blasen', { f: figure({ typ: 'blasen', titel: 'Einkommen und Lebenserwartung nach Land', beschr: 'Höhere Einkommen gehen mit längerer Lebenserwartung einher; Indien und China sind die größten Länder.', h: r.H, svg: r.g, leg: legende(cats.map((c, i) => ({ cls: 'r' + (i + 1), name: c }))), tab: tabelle(['Land', 'Einkommen in 1 000 €', 'Lebenserwartung', 'Einwohner in Mio.', 'Region'], pts.map(p => [p.name, fmt(p.x), fmt(p.y), fmt(p.s), p.c])), cap: '<b>Abbildung 16:</b> Fläche der Blase = Einwohnerzahl; nur die fünf größten sind beschriftet (Beispielzahlen). Die y-Achse beginnt bei 60 Jahren.' }), s: `<figure class="chart" data-type="bubble"> <table><thead><tr><th>Name</th><th>x</th><th>y</th><th>Größe</th><th>Kategorie</th></tr></thead> … ` }); }
// 17 Karte
{ const tiles = { SH: [2, 0, 'Schleswig-Holstein'], MV: [4, 0, 'Mecklenburg-Vorpommern'], HB: [1, 1, 'Bremen'], NI: [2, 1, 'Niedersachsen'], HH: [3, 1, 'Hamburg'], BB: [4, 1, 'Brandenburg'], BE: [5, 1, 'Berlin'], NW: [1, 2, 'Nordrhein-Westfalen'], HE: [2, 2, 'Hessen'], TH: [3, 2, 'Thüringen'], ST: [4, 2, 'Sachsen-Anhalt'], SN: [5, 2, 'Sachsen'], SL: [0, 3, 'Saarland'], RP: [1, 3, 'Rheinland-Pfalz'], BW: [2, 4, 'Baden-Württemberg'], BY: [3, 4, 'Bayern'] };
  const vals = { SH: 1.9, MV: 2.4, NI: 1.7, HH: .4, BB: 3.1, BE: .5, NW: 1.0, HE: 1.1, TH: 1.6, ST: 2.0, SN: 1.3, SL: 1.2, RP: 1.8, BW: 1.9, BY: 3.4 }; const r = karte({ tiles, vals, unit: 'kW' });
  add('karte', { f: figure({ typ: 'karte', titel: 'Photovoltaik-Leistung je Einwohner nach Bundesland', beschr: 'Bayern und Brandenburg liegen vorn, die Stadtstaaten hinten; für Bremen liegen keine Daten vor.', h: r.H, svg: r.g, leg: legende(r.leg, true), tab: tabelle(['Region', 'Wert in kW', 'Name'], Object.entries(vals).map(([c, v]) => ['DE-' + c, fmt(v, 1), tiles[c][2]])), cap: '<b>Abbildung 17:</b> Leistung in Kilowatt je Einwohner (Beispielzahlen). <b>Im Entwurf als Kachelraster;</b> die echte Karte zeichnet Phase 7 mit Umrissen aus Natural Earth. Grau = keine Daten.', extra: ' data-karte="de"' }), s: `<figure class="chart" data-type="map" data-map="de" data-unit="kW"> <table><thead><tr><th>Region</th><th>Wert</th><th>Name</th></tr></thead> … ` }); }
// Grenzfälle
{ const d = { cats: ['Mehr als sehr lange Beschriftung einer einzelnen Zeile', 'Kurz', 'Mittellange Beschriftung hier', 'Noch ein ganz normaler Eintrag', 'Letzter'], v: [1284, 960, 700, 310, 12], unit: 'Messungen' }; const r = balken(d);
  add('langeNamen', { f: figure({ typ: 'balken', titel: 'Lange Beschriftungen', beschr: 'Prüffall: lange Namen brechen um, kleine Werte bleiben lesbar.', h: r.H, svg: r.g, tab: tabelle(['Name', 'Wert'], d.cats.map((c, i) => [c, fmt(d.v[i])])), cap: '<b>Prüffall:</b> sehr lange Namen brechen auf zwei Zeilen; Wert 12 neben 1284 bleibt als schmaler Balken sichtbar; Zahlen ab fünf Stellen mit schmalem Leerzeichen als Tausendertrenner.' }) }); }
{ const cats = Array.from({ length: 40 }, (_, i) => 'W' + (i + 1)); const v = cats.map((_, i) => Math.round(20 + 14 * Math.sin(i / 5) + (i * 7 % 11))); const r = saeulen({ cats, series: [{ name: 'Wert', v }], unit: 'Stück' });
  add('viele', { f: figure({ typ: 'saeulen', titel: 'Viele Spalten', beschr: 'Prüffall: 40 Spalten, nur jede vierte Beschriftung, nur das Maximum mit Zahl.', h: r.H, svg: r.g, tab: tabelle(['Woche', 'Wert in Stück'], cats.map((c, i) => [c, fmt(v[i])])), cap: '<b>Prüffall:</b> 40 Zeilen (Obergrenze) — Achsenbeschriftung ausgedünnt, nur das Maximum trägt eine Zahl; alle Werte stehen im Tooltip und in der Tabelle.' }) }); }
{ const parts = [{ name: 'A', v: 52 }, { name: 'B', v: 24 }, { name: 'C', v: 12 }, { name: 'D', v: 6 }, { name: 'E', v: 4 }, { name: 'F', v: 2 }]; const r = torte({ parts, unit: '%' });
  add('kreisKlein', { f: figure({ typ: 'kreis', titel: 'Kleine Teile', beschr: 'Prüffall: sechs Teile mit 2 % und 4 %, Beschriftungen weichen aus.', h: r.H, svg: r.g, leg: legende(parts.map((s, i) => ({ cls: 'r' + (i + 1), name: s.name }))), tab: tabelle(['Teil', 'Anteil in %'], parts.map(p => [p.name, fmt(p.v)])), cap: '<b>Prüffall:</b> sechs Teile (Obergrenze), kleine Teile liegen eng beieinander — Beschriftungen rücken mit 15 Einheiten Abstand auseinander.' }) }); }

/* ---------------- Seite ---------------- */
const muster = `<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>
<pattern id="mu1" width="6" height="6" patternUnits="userSpaceOnUse"><rect width="6" height="6" fill="#fff"/><rect width="6" height="6" fill="#444"/></pattern>
<pattern id="mu2" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="6" height="6" fill="#fff"/><rect width="1.6" height="6" fill="#000"/></pattern>
<pattern id="mu3" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(135)"><rect width="6" height="6" fill="#fff"/><rect width="1.6" height="6" fill="#000"/></pattern>
<pattern id="mu4" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="6" height="6" fill="#fff"/><rect width="3.2" height="6" fill="#000"/></pattern>
<pattern id="mu5" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(135)"><rect width="6" height="6" fill="#fff"/><rect width="3.2" height="6" fill="#000"/></pattern>
<pattern id="mu6" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="6" height="6" fill="#fff"/><rect width="1.4" height="6" fill="#000"/><rect width="6" height="1.4" fill="#000"/></pattern>
<pattern id="mu7" width="4" height="4" patternUnits="userSpaceOnUse" patternTransform="rotate(135)"><rect width="4" height="4" fill="#fff"/><rect width="1.2" height="4" fill="#000"/></pattern>
<pattern id="mu8" width="6" height="6" patternUnits="userSpaceOnUse"><rect width="6" height="6" fill="#bbb"/></pattern>
</defs></svg>`;
const sect = (id, h, wofuer, keys) => `<h3 id="${id}">${h}</h3><p class="d-wofuer"><b>Wofür:</b> ${wofuer}</p>${keys.map(k => fig[k].f + (fig[k].s ? syn(fig[k].s) : '')).join('\n')}`;
const swatches = (pre, n, label) => `<div class="d-palette diagramm">${Array.from({ length: n }, (_, i) => `<div class="${pre}${i + 1}">${label} ${i + 1}</div>`).join('')}</div>`;

const body = `
<div class="dummy" role="region" aria-label="Steuerung des Entwurfs">
  <b>Entwurf</b>
  <label>Thema <select id="d-thema"><option value="">System</option><option value="hell">Hell</option><option value="dunkel">Dunkel</option></select></label>
  <label><input type="checkbox" id="d-muster"> Druckansicht (Muster statt Farbe)</label>
  <label><input type="checkbox" id="d-syntax"> Syntax für Agenten zeigen</label>
  <span class="stufe" id="d-stufe"></span>
</div>
<p class="dummy-wofuer"><b>Wofür:</b> Leser erfassen Zahlen eines Artikels auf einen Blick und finden jede Zahl auch in der Tabelle. Jede Figur unten ist so gebaut, wie der Server sie liefern soll (Server-SVG, ohne JavaScript vollständig). <span>· Alle Zahlen erfunden; Karte im Entwurf als Kachelraster</span></p>
<main id="inhalt"><article class="artikel wrap layout-standard"><div class="koerper"><aside class="seitenspalte" id="seitenspalte"><nav class="iv" aria-label="Inhaltsverzeichnis"><span class="klein">Inhalt</span><ol><li><a href="#palette">Palette und Regeln</a></li><li><a href="#g1">Balken bis Fläche</a></li><li><a href="#g2">Gestapelt bis Waffel</a></li><li><a href="#g3">Wasserfall bis Kalender</a></li><li><a href="#g4">Sankey bis Karte</a></li><li><a href="#g5">Grenzfälle und Fehler</a></li></ol></nav></aside><div class="text">
<h1>Diagramme für Artikel: Entwurf der Typen</h1>
<p class="lead">Siebzehn Diagramm-Typen in einer Gestalt: eine Zahlenachse, Haarlinien, wenige Farben, jede Farbe zusätzlich mit Wort oder Muster. Agenten liefern nur eine Tabelle, der Server zeichnet.</p>

<h2 id="palette">Palette und Regeln</h2>
<p class="d-wofuer"><b>Wofür:</b> Alle Diagramme teilen eine Palette. Hell und dunkel sind getrennt gewählt und mit dem Validator gegen die Seitenfarben (hell #f6f1e7, dunkel #17150f) geprüft: Helligkeit, Farbigkeit, Unterscheidbarkeit bei Farbfehlsichtigkeit — alles bestanden. Hell sind Orange, Aqua, Gelb und Magenta unter 3:1 zur Fläche; deshalb tragen sie immer Beschriftung, Legende und Tabelle (Prüfwarnung des Validators, so vorgesehen).</p>
<h3>Reihenfarben (feste Reihenfolge, nie zyklisch)</h3>${swatches('r', 8, 'Reihe')}
<h3>Einfarbskala (Heatmap, Kalender, Karte) und Anstieg/Rückgang</h3>${swatches('s', 5, 'Stufe')}
<div class="d-palette diagramm"><div class="plus">Anstieg „+“</div><div class="minus">Rückgang „−“</div><div class="summe">Summe</div></div>
<ul>
<li><b>Eine Zahlenachse</b>, nie zwei; Null-Basislinie bei Balken, Säulen, Flächen; Achsenschritte 1/2/5 × 10ⁿ; Einheit nur einmal im Achsentitel.</li>
<li><b>Marken:</b> höchstens 24 Einheiten breit, Datenende 4 Einheiten rund, 2 Einheiten Abstand zwischen gestapelten Teilen, Linien 2 Einheiten stark, Punkte mit Ring in Flächenfarbe.</li>
<li><b>Beschriftung:</b> Legende ab zwei Reihen; nur Maximum, Endwert oder die größten Marken tragen eine Zahl; alles andere im Hinweis (<code>&lt;title&gt;</code>) und in „Zahlen als Tabelle“.</li>
<li><b>Schrift</b> 12,5 Einheiten bei 440 Einheiten Breite — auf dem Handy etwa 10 px, am Rechner 15 px. Die Zeichenfläche wird höchstens 34 rem breit.</li>
<li><b>Druck:</b> Muster (45° und 135°) ersetzen die Farben, Werte stehen mit weißem Rand. Schalter „Druckansicht“ oben zeigt es am Bildschirm.</li>
</ul>

<h2 id="g1">Balken, Säulen, Linie, Fläche</h2>
${sect('balken', 'Balken', 'Rangfolge von Kategorien mit längeren Namen lesen — größter Wert oben.', ['balken'])}
${sect('saeulen', 'Säulen (gruppiert)', 'Werte über Zeit oder wenige Kategorien vergleichen; bei zwei Reihen nur das Maximum je Reihe mit Zahl.', ['saeulen'])}
${sect('linie', 'Linie', 'Verläufe über viele Zeitpunkte und ihre Abstände zwischen Reihen sehen; Endpunkte sind direkt beschriftet. Interaktion (Reihen aus/ein, Fadenkreuz) folgt in Phase 8 und nur auf Wunsch des Agenten.', ['linie'])}
${sect('flaeche', 'Fläche', 'Umfang über Zeit zeigen — Fläche zehnprozentig, die Linie trägt die Farbe.', ['flaeche'])}

<h2 id="g2">Gestapelt, Kreis, Ring, Netz, Waffel</h2>
${sect('bg', 'Balken gestapelt', 'Gesamtgröße und Zusammensetzung je Kategorie; Zahl im Teil nur, wenn sie passt, Summe am Ende.', ['balkenGestapelt'])}
${sect('sg', 'Säulen gestapelt', 'Gesamtgröße und Zusammensetzung im Zeitverlauf.', ['saeulenGestapelt'])}
${sect('kreis', 'Kreis', 'Anteile an einem Ganzen, höchstens sechs Teile; Name und Prozent stehen direkt am Teil.', ['kreis'])}
${sect('ring', 'Ring', 'Wie Kreis; die Mitte nennt die Gesamtsumme.', ['ring'])}
${sect('netz', 'Netz', 'Profile weniger Reihen (höchstens drei) über sechs bis zwölf Kriterien vergleichen.', ['netz'])}
${sect('waffel', 'Waffel', 'Anteile als greifbare Menge: 100 Kästchen, Summe immer genau 100.', ['waffel'])}

<h2 id="g3">Wasserfall, Heatmap, Kalender</h2>
${sect('wf', 'Wasserfall', 'Wie sich ein Anfangswert über Anstiege und Rückgänge zum Endwert verändert; Vorzeichen stehen im Text, nicht nur in der Farbe.', ['wasserfall'])}
${sect('hm', 'Heatmap', 'Muster in einer Tabelle aus Zeilen und Spalten erkennen; jede Zelle trägt ihre Zahl, die Legende nennt die Klassengrenzen.', ['heatmap'])}
${sect('kal', 'Kalender-Heatmap', 'Rhythmus über ein ganzes Jahr: Wochen als Spalten, Montag oben. Auf dem Handy seitlich wischen statt winzig zu werden.', ['kalender'])}

<h2 id="g4">Sankey, Baumkarte, Blasen, Karte (Phase 7)</h2>
${sect('sk', 'Sankey', 'Wohin Mengen fließen; Bandbreite = Menge. Ohne Skript lesbar, Hervorheben eines Flusses folgt in Phase 8.', ['sankey'])}
${sect('bk', 'Baumkarte', 'Anteile mit zwei Ebenen; Bereich anklicken zeigt die Posten (Klick geht im Entwurf bereits).', ['baumkarte'])}
${sect('bl', 'Blasen', 'Zusammenhang zweier Größen und eine dritte als Fläche; nur die fünf größten sind beschriftet.', ['blasen'])}
${sect('ka', 'Karte', 'Räumliche Verteilung eines Werts; fünf Klassen nach Quantilen, Regionen ohne Wert grau.', ['karte'])}

<h2 id="g5">Grenzfälle und Fehler</h2>
<p class="d-wofuer"><b>Wofür:</b> Zeigt, was bei unbequemen Daten passiert — lange Namen, viele Zeilen, kleine Teile, fehlerhafte Tabelle.</p>
${fig.langeNamen.f}
${fig.viele.f}
${fig.kreisKlein.f}
<h3>Fehlerhafte Tabelle</h3>
<p class="d-fehler"><b>Figur entfällt.</b> Enthält die Tabelle eine Zelle wie „viel“ statt einer Zahl, zeichnet der Server kein Diagramm und die Figur erscheint nicht im Artikel (Eintrag im Fehlerprotokoll). Beim Speichern warnt die Formprüfung den Agenten mit <code>chart_invalid</code> und nennt Zeile und Zelle; der Agent korrigiert die Tabelle.</p>
<h3>Leer</h3>
<p class="d-fehler"><b>Figur entfällt auch ohne Zeilen.</b> Eine Tabelle ohne Datenzeilen ergibt kein Diagramm; die Formprüfung meldet, welche Zeilen fehlen.</p>
<h3>Ohne JavaScript, Dunkelmodus, Druck</h3>
<p class="d-wofuer">Alle Figuren sind statisches SVG: ohne JavaScript vollständig, die Zahlen stehen in Hinweisen und in der Tabelle. Dunkelmodus: eigene Farbstufen (Schalter oben). Druck: Muster, Tabelle ausgeblendet.</p>
<p class="d-hinweis">Offen für später: Karten für „welt“, Hervorhebungen und Hinweisfenster (Phase 8).</p>
</div></div></article></main>`;

const head = `<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Entwurf: Diagramme für Artikel (DISPATCH)</title>`;
let css = fs.readFileSync(path.join(dir, 'diagramme.css'), 'utf8'), js = fs.readFileSync(path.join(dir, 'entwurf.js'), 'utf8');
let style = '';
if (einzeln) {
  const theme = fs.readFileSync(path.join(dir, '..', '..', '..', '..', '..', 'app', 'theme', 'style.css'), 'utf8');
  style = `<style>${theme}</style><style>${css}</style>`;
} else style = `<link rel="stylesheet" href="../../../../app/theme/style.css"><link rel="stylesheet" href="_entwurf-diagramme/diagramme.css">`;
const script = einzeln ? `<script>${js}</script>` : `<script src="_entwurf-diagramme/entwurf.js" defer></script>`;
fs.writeFileSync(out, head + style + script + `</head><body data-seite="artikel">` + muster + body + `</body></html>`);
console.log('geschrieben', out, 'Figuren', ID);
