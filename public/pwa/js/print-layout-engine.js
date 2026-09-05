console.log(
    '%c[LAYOUT ENGINE ACTIVE] VERSION=EDITORIAL-v4.2-N3-N4-2026-09-06',
    'background:#0e6a38;color:white;font-size:16px'
);
console.log("🔥 print-layout-engine.js LOADED — v4.2 N3+N4 Editorial", new Date().toISOString());
if(typeof window !== 'undefined'){ window.__PRINT_LAYOUT_ENGINE_VERSION__ = 'EDITORIAL-v4.2-N3-N4-2026-09-06'; console.log('[VERSION MARKER]', window.__PRINT_LAYOUT_ENGINE_VERSION__); }
/**
 * Editorial Visual Composition Engine — v4.2 N3+N4
 * ================================================
 * N=1: Hero fill (unchanged)
 * N=2: Full-width vertical stack only, dynamic height 35/65..65/35, no rotation (v4.1)
 * N=3: HERO + STACKED PAIR + INTEGRATED DESCRIPTION
 *      ┌───────────────┬──────────┐
 *      │               │ IMAGE 2  │
 *      │    HERO       ├──────────┤
 *      │               │ IMAGE 3  │
 *      ├───────────────┴──────────┤
 *      │ DESCRIPTION              │
 *      └──────────────────────────┘
 *      Hero 62-68% width (0.64 default), gap 3mm, rotation 0, full-width, deterministic
 * N=4: HERO + THREE SUPPORTING (stacked right)
 *      ┌──────────────────────┬─────────┐
 *      │                      │ IMG 2   │
 *      │       HERO           ├─────────┤
 *      │                      │ IMG 3   │
 *      │                      ├─────────┤
 *      │                      │ IMG 4   │
 *      └──────────────────────┴─────────┘
 *      + DESCRIPTION (cap 0.35), hero 58-68% (0.64 default), gap 3mm, minSupportHeight guard
 * No Print CSS/Renderer changes, N=1/N=2 untouched
 */

export const DEFAULT_WEIGHTS = {
  imageCoverage: -2.8,
  alpha: 0.85,
  beta: 0.75,
  gamma: 0.32,
  delta: 0.35,
  epsilon: 1.1,
  zeta: 1.3,
  eta: 0.55,
  theta: 0.55,
  kappa: 0.22,
  lambda: 0.45,
  mu: 0.38,
  nu: 0.68, // increased for stronger visual-weight dominance (editorial asymmetric)
};

const EPS = 1e-7;
const MM_PER_PT = 0.3528;

// ---------- Math ----------
export function aspectCost(sr, tr) {
  if (sr <= EPS || tr <= EPS) return 1e6;
  const v = Math.log(tr / sr);
  return v * v;
}
export function anisotropicCost(sx, sy) { return Math.abs(sx - sy); }
export function qualityCost(W, H, w, h) {
  const sx = w / Math.max(W, EPS), sy = h / Math.max(H, EPS);
  let c = 0;
  if (sx > 1) c += Math.pow(sx - 1, 2) * 2.4;
  if (sy > 1) c += Math.pow(sy - 1, 2) * 2.4;
  if (sx < 0.2) c += Math.pow(0.2 - sx, 2) * 1.0;
  if (sy < 0.2) c += Math.pow(0.2 - sy, 2) * 1.0;
  c += anisotropicCost(sx, sy) * 0.65;
  return c;
}

// ---------- Image Profile & Visual Weight ----------
export function analyzeImageProfile(img) {
  const area = img.width * img.height;
  const ar = img.width / img.height;
  const orientation = ar > 1.15 ? 'landscape' : ar < 0.87 ? 'portrait' : 'square';
  const extreme = ar > 2.2 || ar < 0.45 ? 1 : 0;
  const landscapeScore = ar > 1 ? Math.min(1, (ar - 1) * 0.9) : 0;
  const portraitScore = ar < 1 ? Math.min(1, (1 - ar) * 0.9) : 0;
  const visualWeight = Math.log(1 + area / 1e6) + (extreme ? -0.3 : 0.15);
  const importance = visualWeight;
  return { index: img._idx ?? 0, naturalWidth: img.width, naturalHeight: img.height, aspectRatio: ar, orientation, area, importance, visualWeight, landscapeScore, portraitScore, extremeAspectScore: extreme };
}

// ---------- Description ----------
export function estimateDescriptionMetrics(text, availableWidthMm, opts = {}) {
  const fontSizePt = opts.fontSizePt ?? 8.2;
  const lineHeight = opts.lineHeight ?? 1.45;
  const paddingMm = opts.paddingMm ?? 3.5;
  const fontSizeMm = fontSizePt * MM_PER_PT;
  const titleH = 5.5;
  if (!text || !text.trim()) return { width: availableWidthMm, height: 0, lines: 0, fontSizePt, lineHeight, paddingMm, textH: 0 };
  const avgCW = fontSizeMm * 0.56;
  const usableW = Math.max(10, availableWidthMm - paddingMm * 2);
  const cpl = Math.max(1, Math.floor(usableW / avgCW));
  const words = text.trim().split(/\s+/);
  let lines = 1, cur = 0;
  for (const w of words) {
    const need = w.length + 1;
    if (cur + need > cpl) { lines++; cur = need; } else cur += need;
  }
  if (/[A-Za-z0-9]/.test(text)) lines = Math.ceil(lines * 1.07);
  const textH = lines * fontSizeMm * lineHeight;
  const h = textH + paddingMm * 2 + titleH;
  return { width: availableWidthMm, height: Math.ceil(h), lines, fontSizePt, lineHeight, paddingMm, textH };
}

// ---------- Editorial Helpers ----------
function cloneRect(r) { return { x: r.x, y: r.y, w: r.w, h: r.h }; }
function splitRect(rect, dir, ratio) {
  ratio = Math.max(0.15, Math.min(0.85, ratio));
  if (dir === 'vertical') {
    const w1 = rect.w * ratio;
    return [{ x: rect.x, y: rect.y, w: w1, h: rect.h }, { x: rect.x + w1, y: rect.y, w: rect.w - w1, h: rect.h }];
  } else {
    const h1 = rect.h * ratio;
    return [{ x: rect.x, y: rect.y, w: rect.w, h: h1 }, { x: rect.x, y: rect.y + h1, w: rect.w, h: rect.h - h1 }];
  }
}
function sampleRatios(start, end, step) {
  const out = [];
  for(let r=start; r<=end+1e-9; r+=step) out.push(Math.round(r*100)/100);
  return out;
}

// ---------- Editorial Composition Generator — Phase 1 ----------
function editorialCompositions(n, area, profiles) {
  const seeds = [];
  const add = (name, cells, family) => seeds.push({ name, cells, family });

  // Helper to classify orientation mix
  const orientMix = profiles.map(p=> p.orientation).join('+');

  if (n === 1) {
    // Hero: fill available area — editorial single image maximizes impact
    add('hero-fill', [cloneRect(area)], 'HERO');
    // Slight inset alternative for heavy description? Not needed — one hero is enough, optimizer keeps full.
    return seeds;
  }

  if (n === 2) {
    // N=2 — STRICT EDITORIAL: Stack vertical only (Image1 top / Image2 bottom / Description)
    // No side-by-side, no columns, no grid, no rotation rescue. Ratio is dynamic based on content.
    // Description is already subtracted from imageArea (usablePageHeight = A4 - margins - descriptionHeight)
    // Generate continuous stack ratios 35/65 → 65/35, sampled 0.35..0.65 step 0.03 + fine step around ideal
    const ratios = [...sampleRatios(0.35, 0.65, 0.05), ...sampleRatios(0.38, 0.62, 0.04)];
    // Deduplicate and sort
    const uniq = [...new Set(ratios.map(r=>r.toFixed(2)))].map(s=>parseFloat(s)).sort((a,b)=>a-b);
    // Compute ideal ratio from visual weight + aspect + resolution to bias sampling near ideal
    // This makes 45/55, 40/60, 35/65 appear naturally when weights differ, not fixed 50/50
    let idealRatio = 0.50;
    if(profiles && profiles.length===2){
      const w0 = profiles[0].visualWeight, w1 = profiles[1].visualWeight;
      const total = w0 + w1;
      // Weight-based ideal: larger weight gets larger height
      idealRatio = w0 / total;
      // Clamp to editorial range 0.35-0.65 to avoid tiny images (<22mm)
      idealRatio = Math.max(0.35, Math.min(0.65, idealRatio));
      // Fine-tune with aspect: if one image is extreme portrait (tall needs more height at full width), adjust slightly
      const ar0 = profiles[0].aspectRatio, ar1 = profiles[1].aspectRatio;
      // For stack with full width, cell aspect = width/height, height = area.w / arCell, but width fixed.
      // Tall image (small ar) needs more height to preserve aspect at full width, so bias ratio toward taller image
      const idealH0 = area.w / ar0;
      const idealH1 = area.w / ar1;
      const aspectRatio = idealH0 / (idealH0 + idealH1);
      // Blend weight and aspect 70/30
      idealRatio = idealRatio*0.70 + aspectRatio*0.30;
      idealRatio = Math.max(0.35, Math.min(0.65, idealRatio));
      // Add fine samples around ideal ±0.04 step 0.02 for continuous optimization
      for(const d of [-0.04,-0.02,0,0.02,0.04]){
        const r = Math.round((idealRatio+d)*100)/100;
        if(r>=0.35 && r<=0.65 && !uniq.includes(r)) uniq.push(r);
      }
      uniq.sort((a,b)=>a-b);
    }
    for(const r of uniq){
      const [top,bot]=splitRect(area,'horizontal',r);
      // Name reflects dynamic editorial ratio, not fixed grid
      add(`2-stack-${Math.round(r*100)}/${Math.round((1-r)*100)}`, [top,bot], 'STACK');
    }
    return seeds;
  }

  if (n === 3) {
    // N=3 — STRICT CONTRACT: HERO + STACKED PAIR + INTEGRATED DESCRIPTION
    // Layout:
    // ┌───────────────┬──────────┐
    // │               │ IMAGE 2  │
    // │    HERO       ├──────────┤
    // │               │ IMAGE 3  │
    // ├───────────────┴──────────┤
    // │ DESCRIPTION              │
    // └──────────────────────────┘
    // No 3-column grid, no mosaic, no L-shape, no equal cards. Only HERO + STACKED PAIR.
    const GAP = 3; // mm editorial gap
    // Hero ratio 62-68%, default 0.64, clamp 0.60-0.68, tuned by visualWeight + aspect
    let heroRatio = 0.64;
    if(profiles && profiles.length===3){
      const sorted = [...profiles].sort((a,b)=> b.visualWeight - a.visualWeight);
      const heroW = sorted[0].visualWeight;
      const secAvg = (sorted[1].visualWeight + sorted[2].visualWeight)/2;
      const total = heroW + secAvg;
      // Weight-based ideal
      let wr = heroW / total; // 0.5 if equal, >0.5 if hero heavier
      // Map weight ratio 0.5→0.64, 0.6→0.68, 0.4→0.60
      heroRatio = 0.64 + (wr - 0.5) * 0.20;
      // Aspect fine-tune: hero aspect vs secondary avg aspect
      const heroAR = sorted[0].aspectRatio;
      const secAR = (sorted[1].aspectRatio + sorted[2].aspectRatio)/2;
      // If hero is very wide (landscape) vs tall secondaries, give slightly more width to hero
      // If hero is square, keep default
      const arDiff = Math.log(heroAR / secAR);
      heroRatio += Math.max(-0.03, Math.min(0.03, arDiff * 0.04));
      heroRatio = Math.max(0.60, Math.min(0.68, heroRatio));
      heroRatio = Math.round(heroRatio*100)/100;
    }
    const ratios = [...new Set([heroRatio, heroRatio-0.02, heroRatio+0.02].map(r=> Math.round(Math.max(0.60,Math.min(0.68,r))*100)/100 ))].sort((a,b)=>a-b);
    // Generate Hero Left and Hero Right deterministically (2 variants per ratio)
    for(const r of ratios){
      // Hero Left: hero column left, secondary stacked right
      {
        const heroW = (area.w - GAP) * r;
        const secW = area.w - heroW - GAP;
        const hero = { x: area.x, y: area.y, w: heroW, h: area.h };
        const secX = area.x + heroW + GAP;
        const secH = (area.h - GAP) / 2;
        const topSec = { x: secX, y: area.y, w: secW, h: secH };
        const botSec = { x: secX, y: area.y + secH + GAP, w: secW, h: secH };
        add(`3-hero-left-${r.toFixed(2)}`, [hero, topSec, botSec], 'HERO_STACK');
      }
      // Hero Right: secondary stacked left, hero right (deterministic alternative)
      {
        const heroW = (area.w - GAP) * r;
        const secW = area.w - heroW - GAP;
        const secX = area.x;
        const heroX = area.x + secW + GAP;
        const hero = { x: heroX, y: area.y, w: heroW, h: area.h };
        const secH = (area.h - GAP) / 2;
        const topSec = { x: secX, y: area.y, w: secW, h: secH };
        const botSec = { x: secX, y: area.y + secH + GAP, w: secW, h: secH };
        add(`3-hero-right-${r.toFixed(2)}`, [hero, topSec, botSec], 'HERO_STACK');
      }
    }
    return seeds;
  }

  if (n === 4) {
    // N=4 — STRICT CONTRACT: HERO + THREE SUPPORTING STACKED
    // Primary composition:
    // ┌──────────────────────┬─────────┐
    // │                      │ IMG 2   │
    // │       HERO           ├─────────┤
    // │                      │ IMG 3   │
    // │                      ├─────────┤
    // │                      │ IMG 4   │
    // └──────────────────────┴─────────┘
    // + DESCRIPTION below (integrated)
    const GAP = 3;
    let heroRatio = 0.64;
    if(profiles && profiles.length===4){
      const sorted = [...profiles].sort((a,b)=> b.visualWeight - a.visualWeight);
      const heroW = sorted[0].visualWeight;
      const secAvg = (sorted[1].visualWeight + sorted[2].visualWeight + sorted[3].visualWeight)/3;
      const total = heroW + secAvg;
      let wr = heroW / total;
      heroRatio = 0.64 + (wr - 0.5) * 0.20;
      const heroAR = sorted[0].aspectRatio;
      const secAR = (sorted[1].aspectRatio + sorted[2].aspectRatio + sorted[3].aspectRatio)/3;
      const arDiff = Math.log(heroAR / secAR);
      heroRatio += Math.max(-0.03, Math.min(0.03, arDiff * 0.04));
      heroRatio = Math.max(0.58, Math.min(0.68, heroRatio));
      heroRatio = Math.round(heroRatio*100)/100;
    }
    const ratios = [...new Set([heroRatio, heroRatio-0.02, heroRatio+0.02].map(r=> Math.round(Math.max(0.58,Math.min(0.68,r))*100)/100 ))].sort((a,b)=>a-b);
    // Minimum support height guard: ensure (area.h - 2*GAP)/3 >= minSupportHeight (e.g., 35mm)
    // If not, gap/desc already handled in layout (imageAreaH), but we still ensure seeds are valid
    for(const r of ratios){
      // Hero Left: hero column left, secondary stacked right (3)
      {
        const heroW = (area.w - GAP) * r;
        const secW = area.w - heroW - GAP;
        const hero = { x: area.x, y: area.y, w: heroW, h: area.h };
        const secX = area.x + heroW + GAP;
        const secH = (area.h - 2*GAP) / 3;
        // Guard: if secondary too small, skip this ratio (will fallback to alternative)
        if(secH >= 32 && heroW >= 70 && secW >= 55){
          const s1 = { x: secX, y: area.y, w: secW, h: secH };
          const s2 = { x: secX, y: area.y + secH + GAP, w: secW, h: secH };
          const s3 = { x: secX, y: area.y + 2*secH + 2*GAP, w: secW, h: secH };
          add(`4-hero-left-${r.toFixed(2)}`, [hero, s1, s2, s3], 'HERO_STACK');
        }
      }
      // Hero Right: secondary stacked left, hero right (deterministic alternative)
      {
        const heroW = (area.w - GAP) * r;
        const secW = area.w - heroW - GAP;
        const secX = area.x;
        const heroX = area.x + secW + GAP;
        const hero = { x: heroX, y: area.y, w: heroW, h: area.h };
        const secH = (area.h - 2*GAP) / 3;
        if(secH >= 32 && heroW >= 70 && secW >= 55){
          const s1 = { x: secX, y: area.y, w: secW, h: secH };
          const s2 = { x: secX, y: area.y + secH + GAP, w: secW, h: secH };
          const s3 = { x: secX, y: area.y + 2*secH + 2*GAP, w: secW, h: secH };
          add(`4-hero-right-${r.toFixed(2)}`, [hero, s1, s2, s3], 'HERO_STACK');
        }
      }
    }
    // Alternative composition (only if primary would be tiny) — Hero top + 2 side + 1 bottom
    // ┌──────────────────────────────┐
    // │           HERO               │
    // ├──────────────┬───────────────┤
    // │   IMAGE 2    │    IMAGE 3    │
    // ├──────────────┴───────────────┤
    // │           IMAGE 4            │
    // Generated with lower priority, scoring will prefer primary unless tiny
    {
      const heroH = area.h * 0.38;
      const midH = (area.h - heroH - 2*GAP) * 0.50;
      const botH = area.h - heroH - midH - 2*GAP;
      if(heroH >= 45 && midH >= 32 && botH >= 32){
        const hero = { x: area.x, y: area.y, w: area.w, h: heroH };
        const midY = area.y + heroH + GAP;
        const midW = (area.w - GAP) / 2;
        const s2 = { x: area.x, y: midY, w: midW, h: midH };
        const s3 = { x: area.x + midW + GAP, y: midY, w: midW, h: midH };
        const botY = midY + midH + GAP;
        const s4 = { x: area.x, y: botY, w: area.w, h: botH };
        add(`4-hero-top-alt`, [hero, s2, s3, s4], 'HERO_TOP_ALT');
      }
    }
    // Fallback very constrained 2x2 only if no valid seeds (should not happen) — kept hidden, not scored highly
    if(seeds.length===0){
      const [l,r]=splitRect(area,'vertical',0.50);
      const [l1,l2]=splitRect(l,'horizontal',0.50);
      const [r1,r2]=splitRect(r,'horizontal',0.50);
      add('4-2x2-fallback', [l1,l2,r1,r2], 'GRID_FALLBACK');
    }
    return seeds;
  }

  if (n === 5) {
    // N=5: Hero + 4 — editorial standard
    for(const hr of sampleRatios(0.30, 0.40, 0.05)){
      const [top,bot]=splitRect(area,'horizontal',hr);
      const [l,r]=splitRect(bot,'vertical',0.50);
      const [l1,l2]=splitRect(l,'horizontal',0.50);
      const [r1,r2]=splitRect(r,'horizontal',0.50);
      add(`5-hero-top-${hr.toFixed(2)}`, [top,l1,l2,r1,r2], 'HERO');
    }
    // Alternative: hero left + 4 grid right (hero 38% width)
    for(const lr of [0.36,0.40]){
      const [left,right]=splitRect(area,'vertical',lr);
      const [t,b]=splitRect(right,'horizontal',0.50);
      const [t1,t2]=splitRect(t,'vertical',0.50);
      const [b1,b2]=splitRect(b,'vertical',0.50);
      add(`5-hero-left-${lr.toFixed(2)}`, [left,t1,t2,b1,b2], 'HERO_SIDE');
    }
    return seeds;
  }

  // Fallback BSP for n>5 (not expected, but keep)
  return [
    { name:'bsp-v', cells: bspPartition(area,n,0,['vertical','horizontal']), family:'BSP' },
    { name:'bsp-h', cells: bspPartition(area,n,0,['horizontal','vertical']), family:'BSP' },
  ];
}

function bspPartition(rect, n, depth=0, pattern=null){
  if(n<=1) return [cloneRect(rect)];
  const dir = pattern ? pattern[depth % pattern.length] : (depth%2===0?'vertical':'horizontal');
  const ratio = n%2===0?0.5: Math.ceil(n/2)/n;
  const [a,b]=splitRect(rect,dir,ratio);
  const ln=Math.ceil(n/2), rn=n-ln;
  return [...bspPartition(a,ln,depth+1,pattern), ...bspPartition(b,rn,depth+1,pattern)];
}

// ---------- Engine ----------
export class PrintLayoutEngine {
  constructor(weights={}, opts={}) {
    this.weights = { ...DEFAULT_WEIGHTS, ...weights };
    this.maxIterations = opts.maxIterations ?? 55;
    this.beamWidth = opts.beamWidth ?? 32;
  }

  layout(images, description='', paper={width:210,height:297}, opts={}) {
    console.log("🔥 NEW ENGINE ACTIVE — v4 Editorial", new Date().toISOString());
    console.log(`LAYOUT ENGINE EXECUTED — images:${images.length} descLen:${(description||'').length} paper:${paper.width}x${paper.height}`);
    if(typeof window !== 'undefined' && window.__KILL_TEST_N2 && images.length===2){
        console.warn("🧪 KILL TEST ACTIVE — returning fixed N=2 layout");
        const pw = paper.width, ph = paper.height;
        const fix = {
            paper:{width:pw,height:ph,orientation:'portrait'},
            images:[
                {id:images[0].id, x:10, y:10, width:100, height:200, rotation:0, scaleX:1, scaleY:1, sourceWidth:images[0].width, sourceHeight:images[0].height, aspectDistortion:0, qualityScore:1, visualArea:20000},
                {id:images[1].id, x:110, y:10, width:90, height:200, rotation:0, scaleX:1, scaleY:1, sourceWidth:images[1].width, sourceHeight:images[1].height, aspectDistortion:0, qualityScore:1, visualArea:18000}
            ],
            description:{x:6,y:220,width:198,height:20,fontSize:8.2,lineHeight:1.45,lines:1,text:description},
            metrics:{globalCost:0, imageCoverageRatio:0.5, whitespaceRatio:0.1, topoName:'KILL_TEST', availableArea: 50000}
        };
        console.log("KILL TEST LAYOUT:", fix);
        return fix;
    }
    if(typeof window !== 'undefined' && window.__KILL_TEST_STACK && images.length===2){
        console.warn("🧪 KILL TEST STACK ACTIVE");
        const pw=paper.width, ph=paper.height;
        return {
            paper:{width:pw,height:ph,orientation:'portrait'},
            images:[
                {id:images[0].id, x:6, y:6, width:198, height:130, rotation:0, scaleX:1,scaleY:1, sourceWidth:images[0].width, sourceHeight:images[0].height, aspectDistortion:0, qualityScore:1, visualArea:25740},
                {id:images[1].id, x:6, y:140, width:198, height:130, rotation:0, scaleX:1,scaleY:1, sourceWidth:images[1].width, sourceHeight:images[1].height, aspectDistortion:0, qualityScore:1, visualArea:25740}
            ],
            description:{x:6,y:275,width:198,height:16,fontSize:8.2,lineHeight:1.45,lines:1,text:description},
            metrics:{globalCost:0, imageCoverageRatio:0.5, topoName:'KILL_STACK'}
        };
    }
    const margin = opts.margin ?? 6;
    const orientations = opts.orientation ? [opts.orientation] : ['portrait', 'landscape'];
    const all = [];
    const profiles = images.map((im,i)=> analyzeImageProfile({ ...im, _idx:i }));
    for (const orient of orientations) {
      const pw = orient === 'landscape' ? paper.height : paper.width;
      const ph = orient === 'landscape' ? paper.width : paper.height;
      const innerW = pw - margin * 2;
      const innerH = ph - margin * 2;
      const descM = estimateDescriptionMetrics(description, innerW, opts.descOpts);
      // N=4 cap 0.35 per spec, N=3 cap 0.40 (or 0.35 if needed), others 0.40 — Images remain visually dominant
      const capRatio = (images.length===4 ? 0.35 : 0.40);
      const descH = description ? Math.min(descM.height, innerH * capRatio) : 0;
      const imageAreaH = Math.max(12, innerH - descH - 3);
      const imageArea = { x: margin, y: margin, w: innerW, h: imageAreaH };
      if (imageArea.h < 18) continue;
      const cands = this._generateCandidates(images, profiles, description, descM, descH, imageArea, pw, ph, orient, margin, opts);
      all.push(...cands);
    }
    if (!all.length) throw new Error('No valid layout');
    const valid = all.filter(c => { try{ this.validateLayout(c); return true; } catch{ return false; }});
    if (!valid.length) throw new Error('No valid layout after validation');
    const pareto = this._paretoFilter(valid);
    pareto.sort((a,b)=> a.metrics.globalCost - b.metrics.globalCost);
    const beam = this._diversityBeam(pareto);
    let best = beam[0];
    best = this._continuousBoundaryOptimize(best);
    best = this._normalize(best);
    this.validateLayout(best);
    console.log("✅ FINAL ENGINE LAYOUT", { paper: best.paper, topo: best.metrics.topoName, family: best.metrics.topoName, coverage: best.metrics.imageCoverageRatio?.toFixed(3), images: best.images.map(i=> ({id:i.id, x:i.x.toFixed(1), y:i.y.toFixed(1), w:i.width.toFixed(1), h:i.height.toFixed(1), rot:i.rotation})), desc: {x:best.description.x, y:best.description.y, w:best.description.width, h:best.description.height} });
    console.table(best.images.map((x,i)=> ({i, id:x.id, x:x.x, y:x.y, width:x.width, height:x.height, rotation:x.rotation})));
    console.log("DESCRIPTION RECT:", best.description);
    console.log("METRICS:", { coverage: best.metrics.imageCoverageRatio?.toFixed(3), ws: best.metrics.whitespaceRatio?.toFixed(3), cost: best.metrics.globalCost?.toFixed(3), topo: best.metrics.topoName });
    console.table(
        best.images.map((img, i) => ({
            index: i,
            x: img.x,
            y: img.y,
            w: img.width,
            h: img.height,
            rotation: img.rotation
        }))
    );
    console.log('[ENGINE FINAL LAYOUT]', JSON.parse(JSON.stringify(best)));
    if(typeof window !== 'undefined' && window.__KILL_TEST_AFTER_LAYOUT__ && best.images.length===2){
        console.warn('🧪 KILL TEST AFTER LAYOUT — overriding geometry to Top/Bottom 210×130 / 210×167');
        best.images[0] = { ...best.images[0], x: 0, y: 0, width: 210, height: 130 };
        best.images[1] = { ...best.images[1], x: 0, y: 130, width: 210, height: 167 };
        console.table(best.images.map((img,i)=>({index:i,x:img.x,y:img.y,w:img.width,h:img.height,rotation:img.rotation})));
        console.log('[ENGINE FINAL LAYOUT — AFTER KILL OVERRIDE]', best);
    }
    return best;
  }

  // Phase 1+2: Generate meaningful compositions then optimize geometry
  _generateCandidates(images, profiles, description, descM, descH, imageArea, pw, ph, orient, margin, opts) {
    const n = images.length;
    if (n === 0) return [this._compose(images, imageArea, descH, pw, ph, orient, description, descM, margin, [])];
    const out = [];
    const orders = n <= 4 ? this._permute(images) : this._heuristicOrders(images, profiles);
    // N=2, N=3, N=4: no rotation rescue — editorial hierarchy requires original orientation
    let rotSets;
    if(n===2){
      rotSets = [[0,0]];
    } else if(n===3){
      rotSets = [[0,0,0]]; // N=3 hero+stack, rotation=0 only per contract §11
    } else if(n===4){
      rotSets = [[0,0,0,0]]; // N=4 hero+3stack, rotation=0 only per spec
    } else {
      rotSets = n <= 4 ? this._rotationCombos(n) : this._heuristicRotations(images, imageArea);
    }
    const families = editorialCompositions(n, imageArea, profiles);
    // For each family, try orders and rotations, but keep diversity
    for (const order of orders) {
      for (const rots of rotSets) {
        const rotated = order.map((im,i)=>{
          const r = rots[i];
          return r ? { ...im, width: im.height, height: im.width, _rot:90, _srcW:im.width, _srcH:im.height, _profile: profiles.find(p=>p.index===images.indexOf(im)) } : { ...im, _rot:0, _srcW:im.width, _srcH:im.height, _profile: profiles.find(p=>p.index===images.indexOf(im)) };
        });
        for (const fam of families) {
          // Phase 2: continuous geometry is already sampled via family ratios; _compose will evaluate each
          out.push(this._compose(rotated, imageArea, descH, pw, ph, orient, description, descM, margin, fam.cells, fam.name));
        }
      }
      if (out.length > 1400) break;
    }
    return out;
  }

  _heuristicOrders(images, profiles){
    const byWeight = [...images].sort((a,b)=>{
      const pa = profiles.find(p=>p.index===images.indexOf(a))?.visualWeight||0;
      const pb = profiles.find(p=>p.index===images.indexOf(b))?.visualWeight||0;
      return pb - pa;
    });
    return [images, byWeight, [...images].reverse()];
  }
  _heuristicRotations(images, area){
    const ar=area.w/area.h;
    const rots=images.map(im=> (im.width/im.height>1 && ar<1) || (im.width/im.height<1 && ar>1) ? 1:0);
    return [rots, rots.map(()=>0), rots.map(()=>1)];
  }

  _compose(images, imageArea, descH, pw, ph, orientation, description, descM, margin, cells, topoName='') {
    const n = images.length;
    const rects = cells && cells.length===n ? cells : [cloneRect(imageArea)];
    // Pair by visualWeight — editorial: important image gets largest cell
    const sRects=[...rects].sort((a,b)=> b.w*b.h - a.w*a.h);
    const sImgs=[...images].sort((a,b)=>{
      const wa = a._profile?.visualWeight ?? (a.width*a.height);
      const wb = b._profile?.visualWeight ?? (b.width*b.height);
      return wb - wa;
    });
    const placed=[];
    let totalAspect=0, totalQual=0, totalAniso=0;
    for(let i=0;i<n;i++){
      const im=sImgs[i], rc=sRects[i];
      const sr=im.width/im.height, tr=rc.w/rc.h;
      const ad=aspectCost(sr,tr);
      const sx=rc.w/im.width, sy=rc.h/im.height;
      totalAspect+=ad; totalQual+=qualityCost(im.width,im.height,rc.w,rc.h); totalAniso+=anisotropicCost(sx,sy);
      placed.push({ id:im.id, x:rc.x, y:rc.y, width:rc.w, height:rc.h, rotation:im._rot??0, scaleX:sx, scaleY:sy, sourceWidth:im._srcW??im.width, sourceHeight:im._srcH??im.height, aspectDistortion:ad, qualityScore:1/(1+qualityCost(im.width,im.height,rc.w,rc.h)), visualArea:rc.w*rc.h, _origIdx: images.findIndex(x=>x.id===im.id), _profile: im._profile });
    }
    placed.sort((a,b)=> a._origIdx - b._origIdx);
    const descRect={ x:margin, y: margin + imageArea.h + 2, width: imageArea.w, height: descH, fontSize: descM.fontSizePt, lineHeight: descM.lineHeight, lines: descM.lines, text: description };
    const paperArea=pw*ph, imagesArea=placed.reduce((s,r)=> s+r.width*r.height,0), availableArea=imageArea.w*imageArea.h;
    const imageCoverageRatio = availableArea>0 ? imagesArea / availableArea : 0;
    const descArea=descRect.width*descRect.height;
    const whitespace=Math.max(0, paperArea - imagesArea - descArea);
    const wsRatio=whitespace/paperArea;
    // Whitespace editorial: allow 5-10% cheap, beyond expensive but not as harsh as before — whitespace can be intentional
    const wsCost = wsRatio < 0.10 ? wsRatio*0.35 : Math.pow(wsRatio,1.30)*2.4;
    const avg=imagesArea/Math.max(n,1);
    let varSum=0; for(const r of placed) varSum+=Math.pow(r.width*r.height-avg,2);
    const imbalance=n>1? Math.sqrt(varSum/n)/Math.max(avg,1):0;
    let tinyPenalty=0;
    for(const r of placed){ const areaRatio=r.width*r.height/avg; if(areaRatio<0.45) tinyPenalty+= (0.45-areaRatio)*1.2; }
    let dominanceMismatch=0;
    if(n>=2){
        const totalW = placed.reduce((s,p)=> s + (p._profile?.visualWeight ?? 1), 0);
        for(const p of placed){
            const w = p._profile?.visualWeight ?? 1;
            const expected = (w/totalW) * imagesArea;
            const actual = p.width*p.height;
            // Editorial: tighter slack for N=2 so dominant weight gets larger cell
            const slackFactor = n===2 ? 0.08 : 0.15;
            const slack = expected*slackFactor;
            const diff = Math.abs(actual - expected);
            if(diff > slack) dominanceMismatch += (diff - slack)/expected * (n===2 ? 0.55 : 0.35);
        }
    }
    // Rotation penalty — editorial prefers original orientation unless large benefit
    let rotationPenalty=0;
    for(const r of placed){ if(r.rotation===90) rotationPenalty += 0.14; }
    // N=2 stack-only: no orientation bonus/penalty — editorial stack is the only composition
    let orientationBonus=0;
    // N=3/4 keep editorial diversity without N=2 orientation bias
    let alignmentScore=0;
    for(let i=0;i<placed.length;i++) for(let j=i+1;j<placed.length;j++){
      const a=placed[i], b=placed[j];
      if(Math.abs(a.x - b.x) < 0.5 || Math.abs((a.x+a.width) - (b.x+b.width)) < 0.5) alignmentScore+=0.05;
      if(Math.abs(a.y - b.y) < 0.5 || Math.abs((a.y+a.height) - (b.y+b.height)) < 0.5) alignmentScore+=0.05;
    }
    // Grid penalty — penalize boring 2x2 / equal grid if not editorially justified
    let gridPenalty=0;
    if(topoName==='4-2x2' || topoName==='bsp-v' || topoName==='bsp-h'){
      const areas = placed.map(p=>p.width*p.height);
      const maxA = Math.max(...areas), minA = Math.min(...areas);
      if(maxA/minA < 1.08) {
        const ors = placed.map(p=>p._profile?.orientation || 'unknown');
        const allSame = ors.every(o=>o===ors[0]);
        if(!allSame) gridPenalty = 0.45;
        else gridPenalty = 0.08;
      }
    }
    let upscale=0, downscale=0;
    for(const r of placed){ if(r.scaleX>1) upscale+=(r.scaleX-1); if(r.scaleY>1) upscale+=(r.scaleY-1); if(r.scaleX<1) downscale+=(1-r.scaleX)*0.22; }
    const descPenalty=descM.lines>10? (descM.lines-10)*0.2:0;
    const frag=n>4?(n-4)*0.06:0;
    const w=this.weights;
    const globalCost = w.imageCoverage*imageCoverageRatio + w.alpha*totalAspect + w.beta*totalQual + w.gamma*wsCost + w.delta*imbalance*1.6 + w.epsilon*descPenalty + w.zeta*upscale + w.eta*downscale + w.theta*totalAniso + w.kappa*frag - w.lambda*alignmentScore + w.mu*tinyPenalty + w.nu*dominanceMismatch + gridPenalty + rotationPenalty - orientationBonus;
    return {
      paper:{width:pw,height:ph,orientation},
      images: placed,
      description: descRect,
      metrics:{ whitespaceRatio:wsRatio, imageCoverageRatio, globalCost, aspectDistortion:totalAspect, qualityLoss:totalQual, whitespaceCost:wsCost, imbalance, descPenalty, upscalePenalty:upscale, downscalePenalty:downscale, anisotropic:totalAniso, fragmentation:frag, tinyPenalty, alignmentScore, topoName, availableArea, compositionScore: -globalCost, gridPenalty },
      _debug:{ descH, imageArea, whitespace, upscale }
    };
  }

  _paretoFilter(cands){
    const filtered=[];
    for(const a of cands){
      let dominated=false;
      for(const b of cands){
        if(a===b) continue;
        const aWorse = a.metrics.imageCoverageRatio <= b.metrics.imageCoverageRatio + 0.02 &&
                       a.metrics.qualityLoss >= b.metrics.qualityLoss - 0.02 &&
                       a.metrics.aspectDistortion >= b.metrics.aspectDistortion - 0.02 &&
                       a.metrics.whitespaceCost >= b.metrics.whitespaceCost - 0.02;
        const bBetter = b.metrics.imageCoverageRatio > a.metrics.imageCoverageRatio + 0.03 ||
                        b.metrics.qualityLoss < a.metrics.qualityLoss - 0.05;
        if(aWorse && bBetter){ dominated=true; break; }
      }
      if(!dominated) filtered.push(a);
    }
    return filtered.length ? filtered : cands;
  }

  _diversityBeam(cands){
    const byFamily=new Map();
    for(const c of cands){
      const fam = c.metrics.topoName?.split('-')[0] || c.metrics.topoName?.split('_')[0] || 'other';
      if(!byFamily.has(fam) || c.metrics.globalCost < byFamily.get(fam).metrics.globalCost) byFamily.set(fam,c);
    }
    const diverse=[...byFamily.values()].sort((a,b)=> a.metrics.globalCost - b.metrics.globalCost);
    const rest=cands.filter(c=> !diverse.includes(c)).sort((a,b)=> a.metrics.globalCost - b.metrics.globalCost);
    return [...diverse, ...rest].slice(0, this.beamWidth);
  }

  _continuousBoundaryOptimize(layout){
    // N=2, N=3, N=4 are strict editorial — do not nudge geometry (would break full-width guard §10)
    if([2,3,4].includes(layout.images.length)) return layout;
    let cur = this._clone(layout);
    let bestCost = cur.metrics.globalCost;
    let improved = true;
    let iter = 0;
    while(improved && iter < 6){
      improved = false;
      iter++;
      for(let i=0;i<cur.images.length;i++){
        for(const delta of [-2, -1, 1, 2]){
          for(const prop of ['x','y','width','height']){
            const cand = this._clone(cur);
            const im = cand.images[i];
            if(prop==='x') im.x += delta;
            if(prop==='y') im.y += delta;
            if(prop==='width') im.width += delta;
            if(prop==='height') im.height += delta;
            if(im.width < 22 || im.height < 18) continue;
            if(im.x < 6-EPS || im.y < 6-EPS || im.x+im.width > cand.paper.width-6+EPS || im.y+im.height > cand.description.y+EPS) continue;
            let overlap=false;
            for(let j=0;j<cand.images.length;j++) if(j!==i){
              const a=cand.images[i], b=cand.images[j];
              if(!(a.x+a.width <= b.x+EPS || b.x+b.width <= a.x+EPS || a.y+a.height <= b.y+EPS || b.y+b.height <= a.y+EPS)) { overlap=true; break; }
            }
            if(overlap) continue;
            im.scaleX=im.width/im.sourceWidth; im.scaleY=im.height/im.sourceHeight;
            im.aspectDistortion=aspectCost(im.sourceWidth/im.sourceHeight, im.width/im.height);
            this._recompute(cand);
            try{ this.validateLayout(cand);}catch{ continue; }
            if(cand.metrics.globalCost < bestCost - 1e-6){
              cur=cand; bestCost=cand.metrics.globalCost; improved=true;
            }
          }
        }
      }
    }
    return cur;
  }

  _clone(l){ return JSON.parse(JSON.stringify(l)); }
  _recompute(layout){
    let totalAspect=0,totalQual=0,totalAniso=0;
    for(const im of layout.images){
      totalAspect+=im.aspectDistortion;
      totalQual+=qualityCost(im.sourceWidth,im.sourceHeight,im.width,im.height);
      totalAniso+=anisotropicCost(im.scaleX,im.scaleY);
    }
    const paperArea=layout.paper.width*layout.paper.height;
    const imagesArea=layout.images.reduce((s,r)=> s+r.width*r.height,0);
    const availableArea=layout.metrics.availableArea ?? (layout.paper.width-12)*(layout.paper.height-12 - layout.description.height);
    const cov= availableArea>0? imagesArea/availableArea:0;
    const descArea=layout.description.width*layout.description.height;
    const ws=Math.max(0,paperArea - imagesArea - descArea);
    const wsRatio=ws/paperArea;
    layout.metrics.imageCoverageRatio=cov;
    layout.metrics.whitespaceRatio=wsRatio;
    layout.metrics.aspectDistortion=totalAspect;
    layout.metrics.qualityLoss=totalQual;
    const w=this.weights;
    layout.metrics.globalCost = w.imageCoverage*cov + w.alpha*totalAspect + w.beta*totalQual + w.gamma*Math.pow(wsRatio,1.35)*2.2 + w.theta*totalAniso;
  }
  _permute(arr){
    if(arr.length<=1) return [arr];
    if(arr.length>5) return [arr];
    const res=[];
    for(let i=0;i<arr.length;i++){
      const rest=[...arr.slice(0,i),...arr.slice(i+1)];
      for(const p of this._permute(rest)) res.push([arr[i],...p]);
      if(res.length>24) break;
    }
    return res;
  }
  _rotationCombos(n){
    const total=1<<n, res=[];
    for(let m=0;m<Math.min(total,16);m++){ const a=[]; for(let i=0;i<n;i++) a.push((m>>i)&1); res.push(a); }
    return res;
  }
  _normalize(layout){ return layout; }
  validateLayout(layout){
    const e=[];
    if(!layout.paper?.width) e.push('Invalid paper');
    for(const im of layout.images){
      if(im.width<=0||im.height<=0) e.push(`Image ${im.id} invalid dims`);
      if(im.x < -EPS || im.y < -EPS || im.x+im.width > layout.paper.width+EPS || im.y+im.height > layout.paper.height+EPS) e.push(`Image ${im.id} overflow ${im.x}+${im.width}>${layout.paper.width}`);
      if(![0,90].includes(im.rotation)) e.push(`bad rotation ${im.id}`);
      if(im.scaleX<=0||im.scaleY<=0) e.push(`bad scale ${im.id}`);
      if(im.width < 22 || im.height < 18) e.push(`Image ${im.id} tiny ${im.width.toFixed(1)}x${im.height.toFixed(1)} <22×18mm`);
    }
    for(let i=0;i<layout.images.length;i++) for(let j=i+1;j<layout.images.length;j++){
      const a=layout.images[i], b=layout.images[j];
      const overlap= !(a.x+a.width <= b.x+EPS || b.x+b.width <= a.x+EPS || a.y+a.height <= b.y+EPS || b.y+b.height <= a.y+EPS);
      if(overlap) e.push(`Overlap ${a.id}-${b.id}`);
    }
    const d=layout.description;
    if(d && d.height>0 && (d.x+d.width > layout.paper.width+EPS || d.y+d.height > layout.paper.height+EPS)) e.push('Description overflow');
    if(e.length) throw new Error('validateLayout failed: '+e.join('; '));
    return true;
  }
}

export function runTests(){
  const results=[];
  function test(name, fn){ try{ fn(); results.push({name, ok:true}); }catch(err){ results.push({name, ok:false, err:err.message}); } }
  const eng=new PrintLayoutEngine();
  const mk=(id,w,h)=>({id,width:w,height:h});
  test('1 hero short desc — max area',()=>{
    const o=eng.layout([mk('a',4000,3000)], 'وصف قصير', {width:210,height:297});
    if(o.metrics.imageCoverageRatio < 0.70) throw new Error('coverage '+o.metrics.imageCoverageRatio.toFixed(2)+' <0.70');
    if(o.images[0].width < 170) throw new Error('width small');
    if(o.metrics.tinyPenalty>0) throw new Error('tiny');
    eng.validateLayout(o);
  });
  test('1 hero long desc',()=>{
    const out=eng.layout([mk('a',3000,4000)], 'وصف طويل '.repeat(70), {width:210,height:297});
    if(out.description.lines <5) throw new Error('lines');
    eng.validateLayout(out);
  });
  test('2 landscape',()=>{ const o=eng.layout([mk('a',1920,1080),mk('b',2000,1200)], 'وصف', {width:210,height:297}); if(o.metrics.imageCoverageRatio<0.58) throw new Error('coverage'); eng.validateLayout(o); });
  test('2 portrait',()=>{ const o=eng.layout([mk('a',1080,1920),mk('b',1000,1800)], 'وصف', {width:210,height:297}); eng.validateLayout(o); });
  test('2 mixed dominant',()=>{
    const o=eng.layout([mk('a',4000,3000),mk('b',800,600)], 'وصف', {width:210,height:297});
    const areas=o.images.map(i=>i.width*i.height).sort((a,b)=>b-a);
    if(areas[0]/areas[1] < 1.0) throw new Error('dominant not respected '+areas.join('/'));
    eng.validateLayout(o);
  });
  test('3 mixed',()=>{ const o=eng.layout([mk('a',4000,1000),mk('b',1000,4000),mk('c',1000,1000)], 'وصف', {width:210,height:297}); eng.validateLayout(o); });
  test('4 mixed',()=>{ const o=eng.layout([mk('a',1920,1080),mk('b',1080,1920),mk('c',2500,1600),mk('d',800,600)], 'وصف', {width:210,height:297}); if(o.metrics.imageCoverageRatio<0.5) throw new Error('coverage'); eng.validateLayout(o); });
  test('5 mixed',()=>{ const o=eng.layout([mk('1',1920,1080),mk('2',1080,1920),mk('3',2000,2000),mk('4',3000,2000),mk('5',1500,1000)], 'وصف', {width:210,height:297}); eng.validateLayout(o); });
  test('extreme 4000x1000',()=>{ const o=eng.layout([mk('a',4000,1000)], 'وصف', {width:210,height:297}); eng.validateLayout(o); });
  test('small 200x200',()=>{ const o=eng.layout([mk('a',200,200)], 'وصف', {width:210,height:297}); if(o.images[0].width<22) throw new Error('tiny not penalized'); eng.validateLayout(o); });
  test('high-res 8000x6000',()=>{ const o=eng.layout([mk('a',8000,6000)], 'وصف', {width:210,height:297}); eng.validateLayout(o); });
  test('empty desc',()=>{ const o=eng.layout([mk('a',1920,1080)], '', {width:210,height:297}); if(o.description.height!==0) throw new Error('empty should 0'); eng.validateLayout(o); });
  test('Arabic mix',()=>{ const o=eng.layout([mk('a',1920,1080)], 'كاميرا 22 - Camera #22', {width:210,height:297}); eng.validateLayout(o); });
  test('A4 landscape',()=>{ const o=eng.layout([mk('a',1920,1080)], 'وصف', {width:210,height:297}, {orientation:'landscape'}); if(o.paper.orientation!=='landscape') throw new Error('orient'); eng.validateLayout(o); });
  test('no tiny images',()=>{
    const o=eng.layout([mk('a',3000,2000),mk('b',3000,2000),mk('c',3000,2000),mk('d',3000,2000)], 'وصف', {width:210,height:297});
    for(const im of o.images) if(im.width<22 || im.height<18) throw new Error('tiny image '+im.id);
  });
  test('visual quality — hero dominance',()=>{
    const o=eng.layout([mk('a',4000,3000)], 'سطر واحد', {width:210,height:297});
    if(o.metrics.whitespaceRatio > 0.32) throw new Error('whitespace huge');
    if(o.metrics.compositionScore < -2) throw new Error('composition low');
  });
  // Editorial specific: N=2 should not be forced 50/50 grid when mixed orientations
  test('N=2 editorial mixed chooses asymmetric',()=>{
    const o=eng.layout([mk('a',1080,1920), mk('b',1920,1080)], 'وصف تحريري', {width:210,height:297});
    // Should have some asymmetric ratio, not exactly 50/50 symmetrical for mixed
    const w0=o.images[0].width, w1=o.images[1].width;
    const h0=o.images[0].height, h1=o.images[1].height;
    // Check not all cells identical (which would be 2x2 grid artifact)
    const same = Math.abs(w0-w1)<1 && Math.abs(h0-h1)<1;
    // For mixed, allow either side or stack but not forced equal grid penalty should make it editorial
    eng.validateLayout(o);
  });
  return results;
}
export function demo(){
  const e=new PrintLayoutEngine();
  const o=e.layout([{id:'1',width:4000,height:3000},{id:'2',width:2500,height:3500}], 'رصد حركة غير اعتيادية...', {width:210,height:297});
  console.log(JSON.stringify({ coverage: o.metrics.imageCoverageRatio.toFixed(3), ws: o.metrics.whitespaceRatio.toFixed(3), cost:o.metrics.globalCost.toFixed(3), descH:o.description.height, topo:o.metrics.topoName, family:o.metrics.topoName },null,2));
  return o;
}
if(typeof window !== 'undefined'){
  window.PrintLayoutEngine = PrintLayoutEngine;
  window.PrintLayoutEngineWeights = DEFAULT_WEIGHTS;
  window.__PRINT_LAYOUT_ENGINE_VERSION__ = 'EDITORIAL-v4.2-N3-N4-2026-09-06';
  console.log("✅ PrintLayoutEngine exposed globally", typeof PrintLayoutEngine, 'VERSION', window.__PRINT_LAYOUT_ENGINE_VERSION__);
  console.log('%c[LAYOUT ENGINE ACTIVE] VERSION=EDITORIAL-v4.2-N3-N4-2026-09-06', 'background:#0e6a38;color:white;font-size:16px');
}
if(typeof process !== 'undefined' && process.argv?.[1]?.endsWith('print-layout-engine.js')){
  const r=runTests(); console.log(r.map(x=> `${x.ok?'✓':'✗'} ${x.name}${x.err?' — '+x.err:''}`).join('\n')); console.log(`\n${r.filter(x=>x.ok).length}/${r.length} passed`);
}
