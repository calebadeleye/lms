export interface CourseCategory {
  id: number;
  name: string;
  slug: string;
}

export interface CourseSummary {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  thumbnail_url: string | null;
  pricing_type: 'free' | 'paid' | 'membership_only';
  price_cents: number;
  currency: string;
  difficulty_level: string;
  category: { id: number; name: string; slug: string } | null;
  instructors: { id: number; name: string }[];
  average_rating: number;
  enrolments_count: number;
  status: string;
}

export interface LessonSummary {
  id: number;
  title: string;
  type: string;
  duration_seconds: number | null;
  is_preview: boolean;
  is_mandatory: boolean;
  locked: boolean;
}

export interface CourseModuleSummary {
  id: number;
  title: string;
  lessons: LessonSummary[];
}

export interface CourseDetail extends CourseSummary {
  description: string | null;
  reviews_count: number;
  is_enrolled: boolean;
  modules: CourseModuleSummary[];
}

export interface LessonNavItem {
  id: number;
  title: string;
  type: string;
  is_current: boolean;
}

export interface LessonModuleNav {
  id: number;
  title: string;
  lessons: LessonNavItem[];
}

export interface LessonContent {
  id: number;
  title: string;
  type: 'video' | 'rich_text' | 'audio' | 'file' | 'external_link' | 'quiz' | 'assignment' | 'live';
  content: Record<string, unknown> | null;
  video_path: string | null;
  duration_seconds: number | null;
  is_mandatory: boolean;
  course_id: number;
  course_title: string;
  course_slug: string;
  modules: LessonModuleNav[];
  progress?: { status: string; video_position_seconds: number };
}

export type VideoEmbed =
  | { kind: 'youtube' | 'vimeo' | 'google_drive'; embedUrl: string }
  | { kind: 'direct' };

/**
 * The admin "Video URL" field deliberately accepts a YouTube/Vimeo/Drive
 * page link, not just a direct file URL (see admin/lesson-row.tsx) — a
 * plain HTML5 <video> element can't play those at all (it just fails
 * silently, no error, nothing happens), so the player needs to detect them
 * and render an <iframe> embed instead.
 */
export function resolveVideoEmbed(url: string | null | undefined): VideoEmbed {
  if (!url) return { kind: 'direct' };

  const youtube = url.match(
    /(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{6,})/
  );
  if (youtube) {
    // rel=0 keeps the end-of-video suggestions restricted to this same
    // channel instead of other creators' videos; modestbranding trims the
    // YouTube logo out of the player controls. Neither removes the small
    // logo watermark in the corner — YouTube's embed terms require it to
    // always stay clickable, so it can't be fully hidden.
    return {
      kind: 'youtube',
      embedUrl: `https://www.youtube.com/embed/${youtube[1]}?rel=0&modestbranding=1`,
    };
  }

  const vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
  if (vimeo) {
    // byline=0/title=0 hide Vimeo's author/title overlay; portrait already
    // defaults off. dnt=1 also skips Vimeo's own analytics cookie.
    return {
      kind: 'vimeo',
      embedUrl: `https://player.vimeo.com/video/${vimeo[1]}?byline=0&title=0&dnt=1`,
    };
  }

  const drive = url.match(/drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?id=)([a-zA-Z0-9_-]+)/);
  if (drive) {
    // Only works if the file is shared as "Anyone with the link" — Drive
    // has no equivalent of a public/unlisted upload, sharing is all it has.
    return { kind: 'google_drive', embedUrl: `https://drive.google.com/file/d/${drive[1]}/preview` };
  }

  return { kind: 'direct' };
}

export function formatPrice(cents: number | null | undefined, currency: string | null | undefined): string {
  if (!cents) return 'Free';
  // Intl.NumberFormat throws outright on a missing/empty currency rather
  // than degrading gracefully — a real crash has happened here before from
  // a record whose `currency` came back null, so this falls back instead
  // of taking down the whole page over what's just a formatting detail.
  if (!currency) return `${cents / 100}`;
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency, maximumFractionDigits: 0 }).format(cents / 100);
}
