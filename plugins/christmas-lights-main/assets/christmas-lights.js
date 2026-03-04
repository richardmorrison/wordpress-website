(function(){
  /**
   * `<christmas-lights>` web component
   *
   * Attributes (optional):
   * - count: Number of bulbs. If `fit` is not false, acts as a minimum and auto-fits to viewport. Default: 24.
   * - colors: Preset name (`classic`, `warm`) or comma-separated list of CSS colors. Default: `classic`.
   * - twinkle: Toggle twinkle animation. Values: `true`/`false` (string). Default: `true`.
   * - speed: Animation speed multiplier. Lower = slower. Default: 0.6.
   * - size: Scale factor for bulbs and wire. Default: 1.
   * - offset: Vertical offset in pixels from the top of the viewport. Default: 0.
   * - spacing: Fixed pixel spacing between bulbs. Default: 64.
   * - fit: Auto-fit bulbs to fill viewport width. Values: `true`/`false` (string). Default: `true`.
   *
   * Notes:
   * - Bulbs are centered around the midpoint, keeping the center stable on resize.
   * - Wire alignment uses the SVG path; attachment point (cap gap) is correct by default.
   */
  class ChristmasLights extends HTMLElement {
    static get observedAttributes() {
      return ['count','colors','twinkle','speed','size','offset','spacing','fit'];
    }
    constructor(){
      super();
      this.attachShadow({mode:'open'});
      // Default configuration mapped from attributes
      this._config = {
        count: 24,
        colors: 'classic',
        twinkle: 'true',
        speed: 0.6,
        size: 1,
        offset: 0,
        spacing: 64, // px between bulbs, fixed (half as many lights)
        fit: 'true' // stretch across viewport by auto-adjusting count
      };
      this._scrollState = { lastY: 0, vy: 0, y: 0, raf: null };
    }
    connectedCallback(){
      this._upgradeProps();
      this._render();
      this._onResize = this._reflow.bind(this);
      window.addEventListener('resize', this._onResize, {passive:true});
      this._onScroll = this._handleScroll.bind(this);
      window.addEventListener('scroll', this._onScroll, {passive:true});
      // initialize
      this._scrollState.lastY = window.scrollY || 0;
    }
    disconnectedCallback(){
      window.removeEventListener('resize', this._onResize);
      window.removeEventListener('scroll', this._onScroll);
      if(this._scrollState.raf){ cancelAnimationFrame(this._scrollState.raf); this._scrollState.raf = null; }
    }
    /** Reflect attribute changes into internal config */
    attributeChangedCallback(name, oldVal, newVal){
      if(oldVal === newVal) return;
      switch(name){
        case 'count': this._config.count = Math.max(6, parseInt(newVal||'24',10)); break;
        case 'colors': this._config.colors = String(newVal||this._config.colors); break;
        case 'twinkle': this._config.twinkle = String(newVal||'true'); break;
        case 'speed': this._config.speed = Math.max(0.25, parseFloat(newVal||'1')); break;
        case 'size': this._config.size = Math.max(0.5, parseFloat(newVal||'1')); break;
        case 'offset': this._config.offset = parseInt(newVal||'0',10); break;
        case 'spacing': this._config.spacing = Math.max(16, parseInt(newVal||this._config.spacing,10)); break;
        case 'fit': this._config.fit = String(newVal||'true'); break;
      }
      this._render();
    }
    _upgradeProps(){
      // Make properties reactive if set before definition
      for(const p of ChristmasLights.observedAttributes){
        if(this.hasOwnProperty(p)){
          const v = this[p];
          delete this[p];
          this.setAttribute(p, v);
        }
      }
    }
    _reflow(){
      this._setWireLength();
    }
    /** Sync wire width CSS var with host width */
    _setWireLength(){
      const wire = this.shadowRoot.querySelector('.wire');
      if(!wire) return;
      // Avoid forced reflow: use viewport width since wire is 100% width
      const width = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
      wire.style.setProperty('--wire-width', `${width}px`);
    }
    /** Build styles, wire, bulbs and align to path */
    _render(){
      const cfg = this._config;
      const presetMap = {
        // classic: red, green, blue, orange, white
        classic: ['#e74c3c','#27ae60','#3498db','#e67e22','#ffffff'],
        warm: ['#ff3b30','#ffd166','#06d6a0','#118ab2','#ef476f'],
      };
      let colors = Array.isArray(cfg.colors)
        ? cfg.colors
        : String(cfg.colors).toLowerCase() in presetMap
          ? presetMap[String(cfg.colors).toLowerCase()]
          : String(cfg.colors).split(',').map(c => c.trim()).filter(Boolean);
      let count = cfg.count;
      const twinkle = cfg.twinkle !== 'false';
      const speed = cfg.speed;
      const size = cfg.size; // scale factor
      const offset = cfg.offset; // top offset in px
      const spacing = cfg.spacing; // fixed pixel spacing

      // If fit is true, auto-calc count to fill viewport width
      if(cfg.fit !== 'false'){
        const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        count = Math.max(6, Math.ceil(vw / spacing) + 1);
      }
      const bulbs = new Array(count).fill(0).map((_, i) => {
        const color = colors[i % colors.length];
        return `<span class="bulb" style="--i:${i};--color:${color}"></span>`;
      }).join('');

      const style = `
        :host{position:fixed;left:0;top:${offset}px;width:100%;z-index:9999;pointer-events:none;}
        .container{width:100%;height:calc(36px * ${size});}
        .wire{position:relative;display:block;width:var(--wire-width,100%);height:100%;
          --sway: 12px; --spacing: ${spacing}px;
        }
        svg{position:absolute;left:0;top:0;width:100%;height:100%;}
        .bulbs{position:absolute;left:0;top:0;width:100%;height:100%; display:block;}
        .bulb{position:absolute; width:calc(14px * ${size}); height:calc(22px * ${size});
          left: calc( 50% + var(--spacing) * ( var(--i) - var(--mid, 0) ) ); top: calc(8px * ${size}); transform: translateX(-50%);
          filter: drop-shadow(0 2px 2px rgba(0,0,0,.25));
        }
        .bulb::before{content:""; display:block; width:100%; height:100%;
          border-radius: 50% 50% 45% 45%; background: var(--color);
          opacity: 0.95;
        }
        /* Halo/glow around the bulb */
        .bulb::after{content:""; position:absolute; left:50%; top:50%; transform:translate(-50%,-50%);
          width:calc(52px * ${size}); height:calc(52px * ${size}); pointer-events:none;
          background: radial-gradient(closest-side, var(--halo-color, rgba(255,255,255,0.35)) 0%, rgba(255,255,255,0.0) 60%);
          opacity: 0; border-radius:50%; filter: blur(calc(2px * ${size}));
        }
        .cap{position:absolute; width:calc(10px * ${size}); height:calc(8px * ${size});
          background:#2c3e50; border-radius:2px; left:50%; transform:translateX(-50%);
          top: calc(-6px * ${size});
        }
        .string{position:absolute; width:calc(12px * ${size}); height:calc(4px * ${size});
          background:#2c3e50; border-radius:2px; left:50%; transform:translateX(-50%);
          top: calc(-12px * ${size});
        }
        ${twinkle ? `
        @keyframes twinkle{ 0%, 100% {opacity:.9} 50% {opacity:.25} }
        @keyframes haloPulse{ 0%, 100% {opacity:.55} 50% {opacity:.0} }
        .bulb::before{ animation: twinkle ${1.5/speed}s ease-in-out infinite; }
        .bulb::after{ --halo-color: color-mix(in oklab, var(--color) 60%, white 40%);
          animation: haloPulse ${1.5/speed}s ease-in-out infinite; }
        .bulb:nth-child(odd)::before, .bulb:nth-child(odd)::after{ animation-delay: .4s; }
        .bulb:nth-child(3n)::before, .bulb:nth-child(3n)::after{ animation-delay: .8s; }
        `: ''}
        /* bounce is driven by scroll, no idle animation */
      `;

      const svgPath = `<svg viewBox="0 0 100 36" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,4 C20,10 40,2 60,8 C80,14 90,4 100,10" fill="none" stroke="#2c3e50" stroke-width="2"/>
      </svg>`;

      const mid = (count - 1) / 2;
      this.shadowRoot.innerHTML = `
        <style>${style}</style>
        <div class="container">
          <div class="wire" style="--mid:${mid}">
            ${svgPath}
            <span class="bulbs">${bulbs}</span>
          </div>
        </div>
      `;
      // add caps and strings after
      const bulbEls = this.shadowRoot.querySelectorAll('.bulb');
      bulbEls.forEach(b => {
        const cap = document.createElement('span'); cap.className = 'cap';
        const string = document.createElement('span'); string.className = 'string';
        b.appendChild(cap); b.appendChild(string);
      });
      this._setWireLength();
      // reset transform
      const wire = this.shadowRoot.querySelector('.wire');
      if(wire) wire.style.transform = 'translateY(0px)';

      // Align bulbs vertically to the SVG wire curve
      // Avoid layout reads: wire fills viewport width, so use viewport width
      const wireWidthPx = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
      const containerHeightPx = 36 * size; // matches SVG viewBox height scaled by size

      // Cubic Bezier helper
      const pointOnCubic = (p0, p1, p2, p3, t) => {
        const mt = 1 - t;
        const x = mt*mt*mt*p0.x + 3*mt*mt*t*p1.x + 3*mt*t*t*p2.x + t*t*t*p3.x;
        const y = mt*mt*mt*p0.y + 3*mt*mt*t*p1.y + 3*mt*t*t*p2.y + t*t*t*p3.y;
        return {x,y};
      };
      // Two segments of the path in SVG coordinate space
      const seg1 = {
        p0: {x:0, y:4}, p1: {x:20, y:10}, p2: {x:40, y:2}, p3: {x:60, y:8}
      };
      const seg2 = {
        p0: {x:60, y:8}, p1: {x:80, y:14}, p2: {x:90, y:4}, p3: {x:100, y:10}
      };
      const findYForX = (xTarget) => {
        const segment = xTarget <= 60 ? seg1 : seg2;
        // Normalize x range for the segment
        const xStart = segment.p0.x;
        const xEnd = segment.p3.x;
        const xNorm = (xTarget - xStart) / (xEnd - xStart);
        // Search t ~ xNorm first, refine with small iterations
        let t = Math.min(1, Math.max(0, xNorm));
        let best = pointOnCubic(segment.p0, segment.p1, segment.p2, segment.p3, t);
        // refine by local search
        const step = 1/50;
        let bestDiff = Math.abs(best.x - xTarget);
        for(let i=-25;i<=25;i++){
          const ti = Math.min(1, Math.max(0, t + i*step));
          const p = pointOnCubic(segment.p0, segment.p1, segment.p2, segment.p3, ti);
          const d = Math.abs(p.x - xTarget);
          if(d < bestDiff){ bestDiff = d; best = p; }
        }
        return best.y;
      };

      // Position each bulb so its cap aligns near the wire
      bulbEls.forEach((b, i) => {
        const iVal = i; // index
        const midIdx = mid; // center index
        const leftPx = (wireWidthPx * 0.5) + spacing * (iVal - midIdx);
        const xPercent = (leftPx / wireWidthPx) * 100; // to SVG viewBox 0..100
        const ySvg = findYForX(Math.max(0, Math.min(100, xPercent)));
        const yPx = ySvg * (containerHeightPx / 36); // scale to container
        // Position so the wire intersects the gap at the cap
        const attachOffset = 6 * size; // default alignment to cap gap
        b.style.top = `${(yPx - attachOffset + 13).toFixed(2)}px`;
      });
    }
    /** Scroll handler adds bounce impulse */
    _handleScroll(){
      const state = this._scrollState;
      const y = window.scrollY || 0;
      const dy = y - state.lastY;
      state.lastY = y;
      // velocity-based bounce impulse
      state.vy += (-dy) * 0.15; // invert to match natural scroll direction
      // start RAF loop
      if(!state.raf){ state.raf = requestAnimationFrame(this._animateBounce.bind(this)); }
    }
    /** Spring-damper bounce on wire element */
    _animateBounce(){
      const wire = this.shadowRoot && this.shadowRoot.querySelector('.wire');
      const state = this._scrollState;
      if(!wire){ state.raf = null; return; }
      // simple spring-damper toward 0
      const k = 0.08; // spring
      const c = 0.13; // damping
      state.vy += -k * state.y;
      state.vy *= (1 - c);
      state.y += state.vy;
      // clamp and render
      const maxSway = 12 * this._config.size; // px
      if(state.y > maxSway) state.y = maxSway;
      if(state.y < -maxSway) state.y = -maxSway;
      wire.style.transform = `translateY(${state.y.toFixed(2)}px)`;
      // stop when near rest
      if(Math.abs(state.y) < 0.05 && Math.abs(state.vy) < 0.05){
        wire.style.transform = 'translateY(0px)';
        state.y = 0; state.vy = 0; state.raf = null; return;
      }
      state.raf = requestAnimationFrame(this._animateBounce.bind(this));
    }
  }
  if(!customElements.get('christmas-lights')){
    customElements.define('christmas-lights', ChristmasLights);
  }
})();