export type BrandIconName =
  | 'award'
  | 'calendar'
  | 'community'
  | 'compass'
  | 'heart'
  | 'leaf'
  | 'people'
  | 'star'
  | 'target';

export function BrandIcon({ name, className = 'h-6 w-6' }: { name: BrandIconName | string; className?: string }) {
  const shared = {
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.7,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    className,
    'aria-hidden': true,
  };

  if (name === 'calendar') {
    return <svg {...shared}><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M7 3v4M17 3v4M3 10h18M7 14h3M14 14h3M7 18h3" /></svg>;
  }
  if (name === 'compass') {
    return <svg {...shared}><circle cx="12" cy="12" r="9" /><path d="m15.5 8.5-2.2 4.8-4.8 2.2 2.2-4.8 4.8-2.2Z" /></svg>;
  }
  if (name === 'heart') {
    return <svg {...shared}><path d="M20.8 5.8a5 5 0 0 0-7.1 0L12 7.5l-1.7-1.7a5 5 0 0 0-7.1 7.1L12 21l8.8-8.1a5 5 0 0 0 0-7.1Z" /></svg>;
  }
  if (name === 'leaf') {
    return <svg {...shared}><path d="M20.5 3.5C13 4 7.2 7 5.6 11.3c-1.3 3.5.8 6.4 4 6.7 5 .4 8.8-4.7 10.9-14.5Z" /><path d="M4 21c2.7-5.9 6.5-9.6 11.5-12" /></svg>;
  }
  if (name === 'star') {
    return <svg {...shared}><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3Z" /></svg>;
  }
  if (name === 'target') {
    return <svg {...shared}><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="5" /><circle cx="12" cy="12" r="1" /><path d="m15.5 8.5 4-4M17 4h3v3" /></svg>;
  }
  if (name === 'award') {
    return <svg {...shared}><circle cx="12" cy="9" r="5" /><path d="m8.5 13-1 8 4.5-2 4.5 2-1-8M10 9l1.2 1.2L14 7.5" /></svg>;
  }
  if (name === 'community') {
    return <svg {...shared}><circle cx="12" cy="7" r="3" /><circle cx="5" cy="10" r="2" /><circle cx="19" cy="10" r="2" /><path d="M6 21c0-3.3 2.7-6 6-6s6 2.7 6 6M1.5 19c0-2.4 1.6-4.4 3.8-5M22.5 19c0-2.4-1.6-4.4-3.8-5" /></svg>;
  }
  return <svg {...shared}><circle cx="9" cy="8" r="3" /><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" /><circle cx="17" cy="9" r="2.5" /><path d="M15.5 14.5c2.6.3 4.5 2.6 4.5 5.3" /></svg>;
}
