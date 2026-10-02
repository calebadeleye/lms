'use client';

import { useEffect, useRef, useState } from 'react';

function parseValue(value: string) {
  const numeric = Number(value.replace(/[^0-9.]/g, ''));
  const suffix = value.replace(/[0-9.,]/g, '');
  const hasComma = value.includes(',');
  return { numeric, suffix, hasComma };
}

function formatNumber(n: number, hasComma: boolean) {
  const rounded = Math.round(n);
  return hasComma ? rounded.toLocaleString('en-US') : String(rounded);
}

/** Counts up from 0 to the target once the number scrolls into view. */
export function AnimatedCounter({ value, durationMs = 1500 }: { value: string; durationMs?: number }) {
  const { numeric, suffix, hasComma } = parseValue(value);
  const [display, setDisplay] = useState(0);
  const ref = useRef<HTMLSpanElement>(null);
  const started = useRef(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    const observer = new IntersectionObserver(
      (entries) => {
        if (!entries[0].isIntersecting || started.current) return;
        started.current = true;

        const start = performance.now();
        const tick = (now: number) => {
          const progress = Math.min((now - start) / durationMs, 1);
          const eased = 1 - (1 - progress) ** 3;
          setDisplay(numeric * eased);
          if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
      },
      { threshold: 0.4 },
    );

    observer.observe(el);
    return () => observer.disconnect();
  }, [numeric, durationMs]);

  return (
    <span ref={ref}>
      {formatNumber(display, hasComma)}
      {suffix}
    </span>
  );
}
