export interface AdminQuizOption {
  id?: number;
  option_text: string;
  is_correct: boolean;
}

export interface AdminQuizQuestion {
  id?: number;
  type: 'multiple_choice' | 'multiple_answer' | 'true_false' | 'free_text';
  question_text: string;
  points: number;
  options: AdminQuizOption[];
}

export interface AdminQuiz {
  id: number;
  passing_score_percent: number;
  max_attempts: number | null;
  time_limit_minutes: number | null;
  questions: AdminQuizQuestion[];
}

export interface AdminAssignment {
  id: number;
  title: string;
  instructions: string | null;
  max_points: number;
  due_date: string | null;
}

export interface AdminLesson {
  id: number;
  title: string;
  type: 'video' | 'rich_text' | 'audio' | 'file' | 'external_link' | 'quiz' | 'assignment' | 'live';
  content: { body?: string; url?: string; meeting_url?: string; material_path?: string; material_filename?: string } | null;
  video_path: string | null;
  duration_seconds: number | null;
  is_preview: boolean;
  is_mandatory: boolean;
  sort_order: number;
  quiz: AdminQuiz | null;
  assignment: AdminAssignment | null;
}

export interface AdminModule {
  id: number;
  title: string;
  sort_order: number;
  lessons: AdminLesson[];
}

export interface AdminCourseInstructor {
  id: number;
  name: string;
  pivot?: { role: 'primary' | 'co_instructor' };
}

export interface AdminCourseDetail {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  description: string | null;
  thumbnail_url: string | null;
  status: string;
  pricing_type: string;
  price_cents: number;
  currency: string;
  difficulty_level: string;
  category: { id: number; name: string } | null;
  instructors: AdminCourseInstructor[];
  modules: AdminModule[];
  enrolments_count: number;
}

export interface CourseCategory {
  id: number;
  name: string;
  slug: string;
}
