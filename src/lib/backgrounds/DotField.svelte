<script module lang="ts">
  const TWO_PI = Math.PI * 2;

  type Dot = {
    ax: number;
    ay: number;
    sx: number;
    sy: number;
    vx: number;
    vy: number;
    x: number;
    y: number;
  };
</script>

<script lang="ts">
  type Props = {
    dotRadius?: number;
    dotSpacing?: number;
    cursorRadius?: number;
    cursorForce?: number;
    bulgeOnly?: boolean;
    bulgeStrength?: number;
    glowRadius?: number;
    sparkle?: boolean;
    waveAmplitude?: number;
    gradientFrom?: string;
    gradientTo?: string;
    glowColor?: string;
    disableAnimation?: boolean;
    class?: string;
  };

  let {
    dotRadius = 1.5,
    dotSpacing = 14,
    cursorRadius = 500,
    cursorForce = 0.1,
    bulgeOnly = true,
    bulgeStrength = 67,
    glowRadius = 160,
    sparkle = false,
    waveAmplitude = 0,
    gradientFrom = 'rgba(255, 62, 0, 0.35)',
    gradientTo = 'rgba(255, 176, 137, 0.25)',
    glowColor = 'var(--canvas-glow)',
    disableAnimation = false,
    class: className = '',
  }: Props = $props();

  let root: HTMLDivElement;
  let canvas: HTMLCanvasElement;
  let glowEl: SVGCircleElement;
  const glowId = `dot-field-glow-${Math.random().toString(36).slice(2, 9)}`;

  let dots: Dot[] = [];
  const mouse = { x: -9999, y: -9999, prevX: -9999, prevY: -9999, speed: 0 };
  let size = { w: 0, h: 0, offsetX: 0, offsetY: 0 };
  let glowOpacity = 0;
  let engagement = 0;
  let rebuild: (() => void) | null = null;

  $effect(() => {
    const currentCanvas = canvas;
    const currentRoot = root;
    const currentGlow = glowEl;
    if (!currentCanvas || !currentRoot) return;

    const ctx = currentCanvas.getContext('2d', { alpha: true });
    if (!ctx) return;
    const context = ctx;

    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    let resizeTimer: ReturnType<typeof setTimeout>;
    let raf = 0;
    let frameCount = 0;

    function buildDots(w: number, h: number): void {
      const step = dotRadius + dotSpacing;
      const cols = Math.floor(w / step);
      const rows = Math.floor(h / step);
      const padX = (w % step) / 2;
      const padY = (h % step) / 2;
      const nextDots: Dot[] = [];

      for (let row = 0; row < rows; row += 1) {
        for (let column = 0; column < cols; column += 1) {
          const ax = padX + column * step + step / 2;
          const ay = padY + row * step + step / 2;
          nextDots.push({ ax, ay, sx: ax, sy: ay, vx: 0, vy: 0, x: ax, y: ay });
        }
      }

      dots = nextDots;
    }

    function doResize(): void {
      const rect = currentRoot.getBoundingClientRect();
      const w = rect.width;
      const h = rect.height;
      currentCanvas.width = w * dpr;
      currentCanvas.height = h * dpr;
      currentCanvas.style.width = `${w}px`;
      currentCanvas.style.height = `${h}px`;
      context.setTransform(dpr, 0, 0, dpr, 0, 0);
      size = { w, h, offsetX: rect.left + window.scrollX, offsetY: rect.top + window.scrollY };
      buildDots(w, h);
    }

    function resize(): void {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(doResize, 100);
    }

    function onMouseMove(event: MouseEvent): void {
      mouse.x = event.pageX - size.offsetX;
      mouse.y = event.pageY - size.offsetY;
    }

    function updateMouseSpeed(): void {
      const dx = mouse.prevX - mouse.x;
      const dy = mouse.prevY - mouse.y;
      const distance = Math.sqrt(dx * dx + dy * dy);
      mouse.speed += (distance - mouse.speed) * 0.5;
      if (mouse.speed < 0.001) mouse.speed = 0;
      mouse.prevX = mouse.x;
      mouse.prevY = mouse.y;
    }

    const speedInterval = setInterval(updateMouseSpeed, 20);

    function draw(): void {
      frameCount += disableAnimation ? 0 : 1;
      const { w, h } = size;
      const time = frameCount * 0.02;
      const targetEngagement = Math.min(mouse.speed / 5, 1);
      engagement += (targetEngagement - engagement) * 0.06;
      if (engagement < 0.001) engagement = 0;

      glowOpacity += (engagement - glowOpacity) * 0.08;
      if (currentGlow) {
        currentGlow.setAttribute('cx', String(mouse.x));
        currentGlow.setAttribute('cy', String(mouse.y));
        currentGlow.style.opacity = String(glowOpacity);
      }

      context.clearRect(0, 0, w, h);
      const gradient = context.createLinearGradient(0, 0, w, h);
      gradient.addColorStop(0, gradientFrom);
      gradient.addColorStop(1, gradientTo);
      context.fillStyle = gradient;
      context.beginPath();

      const cursorRadiusSquared = cursorRadius * cursorRadius;
      const radius = dotRadius / 2;

      for (let index = 0; index < dots.length; index += 1) {
        const dot = dots[index];
        const dx = mouse.x - dot.ax;
        const dy = mouse.y - dot.ay;
        const distanceSquared = dx * dx + dy * dy;

        if (distanceSquared < cursorRadiusSquared && engagement > 0.01) {
          const distance = Math.sqrt(distanceSquared);
          const angle = Math.atan2(dy, dx);
          if (bulgeOnly) {
            const falloff = 1 - distance / cursorRadius;
            const push = falloff * falloff * bulgeStrength * engagement;
            dot.sx += (dot.ax - Math.cos(angle) * push - dot.sx) * 0.15;
            dot.sy += (dot.ay - Math.sin(angle) * push - dot.sy) * 0.15;
          } else {
            const safeDistance = Math.max(distance, 0.001);
            const move = (500 / safeDistance) * (mouse.speed * cursorForce);
            dot.vx += Math.cos(angle) * -move;
            dot.vy += Math.sin(angle) * -move;
          }
        } else if (bulgeOnly) {
          dot.sx += (dot.ax - dot.sx) * 0.1;
          dot.sy += (dot.ay - dot.sy) * 0.1;
        }

        if (!bulgeOnly) {
          dot.vx *= 0.9;
          dot.vy *= 0.9;
          dot.x = dot.ax + dot.vx;
          dot.y = dot.ay + dot.vy;
          dot.sx += (dot.x - dot.sx) * 0.1;
          dot.sy += (dot.y - dot.sy) * 0.1;
        }

        let drawX = dot.sx;
        let drawY = dot.sy;
        if (waveAmplitude > 0) {
          drawY += Math.sin(dot.ax * 0.03 + time) * waveAmplitude;
          drawX += Math.cos(dot.ay * 0.03 + time * 0.7) * waveAmplitude * 0.5;
        }

        const sparkleRadius = sparkle && ((index * 2654435761) ^ (frameCount >> 3)) % 100 < 3 ? radius * 1.8 : radius;
        context.moveTo(drawX + sparkleRadius, drawY);
        context.arc(drawX, drawY, sparkleRadius, 0, TWO_PI);
      }

      context.fill();
      raf = requestAnimationFrame(draw);
    }

    doResize();
    window.addEventListener('resize', resize);
    window.addEventListener('mousemove', onMouseMove, { passive: true });
    raf = requestAnimationFrame(draw);
    rebuild = () => size.w > 0 && size.h > 0 && buildDots(size.w, size.h);

    return () => {
      cancelAnimationFrame(raf);
      clearInterval(speedInterval);
      clearTimeout(resizeTimer);
      window.removeEventListener('resize', resize);
      window.removeEventListener('mousemove', onMouseMove);
    };
  });

  $effect(() => {
    void dotRadius;
    void dotSpacing;
    rebuild?.();
  });
</script>

<div bind:this={root} class="relative h-full w-full {className}">
  <canvas bind:this={canvas} class="absolute inset-0 h-full w-full"></canvas>
  <svg class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true">
    <defs>
      <radialGradient id={glowId}>
        <stop offset="0%" stop-color={glowColor} />
        <stop offset="100%" stop-color="transparent" />
      </radialGradient>
    </defs>
    <circle bind:this={glowEl} cx="-9999" cy="-9999" r={glowRadius} fill="url(#{glowId})" style:opacity="0" style:will-change="opacity" />
  </svg>
</div>
