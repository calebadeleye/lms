type IconProps = { className?: string };

/** Hand-written line icons in the same style as `social-icons.tsx` — no
 * icon library dependency for a small, fixed set of glyphs. */

export function StrengthIcon({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M6 14V9a2 2 0 0 1 4 0v3" />
      <path d="M10 12V7a2 2 0 0 1 4 0v5" />
      <path d="M14 12V8a2 2 0 0 1 4 0v6c0 3.31-2.69 6-6 6h-1a5 5 0 0 1-5-5v-2.5L4.6 11a1.4 1.4 0 0 1 2-2L8 10.5" />
    </svg>
  );
}

export function PersonalityIcon({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M9 3a6 6 0 0 1 6 6c0 1.5.5 2 1.2 2.7.5.5.8 1.2.5 1.9-.3.8-1.1 1.1-1.7 1.4v2a2 2 0 0 1-2 2h-1v2H9v-2.6c-2.4-.8-4-3-4-5.6a1 1 0 0 0-1-1 1 1 0 0 1-.8-1.6C4 8.3 5.5 3 9 3Z" />
      <circle cx="9.5" cy="9.5" r="0.8" fill="currentColor" stroke="none" />
    </svg>
  );
}

export function ValuesIcon({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M6 3h12l4 6-10 12L2 9Z" />
      <path d="M2 9h20M9 3l-1 6 4 12M15 3l1 6-4 12" />
    </svg>
  );
}

export function PathsIcon({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M3 8h11l3-3v6l-3-3" />
      <path d="M3 16h8l3 3v-6l-3 3" />
    </svg>
  );
}

export function DecisionIcon({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" className={className}>
      <circle cx="12" cy="12" r="9" />
      <circle cx="12" cy="12" r="5" />
      <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none" />
    </svg>
  );
}

export function CheckIcon({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M5 13l4 4L19 7" />
    </svg>
  );
}

export function UsersIcon({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <circle cx="9" cy="8" r="3" />
      <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" />
      <circle cx="17" cy="9" r="2.4" />
      <path d="M15.5 14.2c2.6.4 4.5 2.6 4.5 5.3" />
    </svg>
  );
}

export function CalendarIcon({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <rect x="3" y="5" width="18" height="16" rx="2" />
      <path d="M3 10h18M8 3v4M16 3v4" />
    </svg>
  );
}

export function MentorIcon({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <circle cx="12" cy="7" r="3.2" />
      <path d="M5.5 20c0-3.6 2.9-6.5 6.5-6.5s6.5 2.9 6.5 6.5" />
      <path d="M9 17.5l2 2 4-4" />
    </svg>
  );
}

export function TeamIcon({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <circle cx="7" cy="8" r="2.6" />
      <circle cx="17" cy="8" r="2.6" />
      <path d="M2.5 19c0-2.8 2-5 4.5-5s4.5 2.2 4.5 5M12.5 19c0-2.8 2-5 4.5-5s4.5 2.2 4.5 5" />
    </svg>
  );
}

export function TagIcon({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M12.5 3.5H5a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 .44 1.06l9 9a1.5 1.5 0 0 0 2.12 0l6-6a1.5 1.5 0 0 0 0-2.12l-9-9a1.5 1.5 0 0 0-1.06-.44Z" />
      <circle cx="8.5" cy="8.5" r="1.25" fill="currentColor" stroke="none" />
    </svg>
  );
}

export const WHY_IT_MATTERS_ICONS = {
  strength: StrengthIcon,
  personality: PersonalityIcon,
  values: ValuesIcon,
  paths: PathsIcon,
  decision: DecisionIcon,
} as const;

export const MEMBERSHIP_BENEFIT_ICONS = {
  users: UsersIcon,
  calendar: CalendarIcon,
  mentor: MentorIcon,
  team: TeamIcon,
  tag: TagIcon,
} as const;
