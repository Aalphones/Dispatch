/* Nur Entwurf: Leiste (Thema, Druckansicht, Syntax), Stufenanzeige, Baumkarte-Ebene wechseln. */
(function () {
  const q = new URLSearchParams(location.search), root = document.documentElement;
  const set = () => {
    const t = document.getElementById('d-thema').value;
    root.classList.remove('hell', 'dunkel'); if (t) root.classList.add(t);
    document.body.classList.toggle('d-muster', document.getElementById('d-muster').checked);
    document.body.classList.toggle('d-syntax-an', document.getElementById('d-syntax').checked);
  };
  const stufe = () => {
    const w = innerWidth, s = w < 576 ? 'xs' : w < 768 ? 'sm' : w < 992 ? 'md' : w < 1200 ? 'lg' : w < 1408 ? 'xl' : 'xxl';
    const el = document.getElementById('d-stufe'); if (el) el.textContent = `Stufe ${s} · ${w} px`;
  };
  addEventListener('DOMContentLoaded', () => {
    if (q.get('thema')) document.getElementById('d-thema').value = q.get('thema');
    if (q.has('muster')) document.getElementById('d-muster').checked = true;
    if (q.has('syntax')) document.getElementById('d-syntax').checked = true;
    document.querySelector('.dummy').addEventListener('change', set); set(); stufe();
    document.querySelectorAll('.diagramm[data-typ="baumkarte"]').forEach(fig => {
      const eb1 = fig.querySelector('.ebene-1'), zeige = id => {
        eb1.toggleAttribute('hidden', !!id);
        fig.querySelectorAll('g.ebene').forEach(e => e.toggleAttribute('hidden', e.dataset.gruppe !== id));
      };
      fig.querySelectorAll('.gruppe').forEach(g => {
        const go = () => zeige(g.dataset.gruppe);
        g.addEventListener('click', go); g.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
      });
      fig.querySelectorAll('g.ebene').forEach(e => e.addEventListener('click', () => zeige(null)));
      if (q.get('ebene')) zeige(q.get('ebene'));
    });
  });
  addEventListener('resize', stufe);
})();
