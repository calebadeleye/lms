export interface TenantMember {
  id: number;
  name: string;
  email: string;
  role: string;
}

export interface AdminCoach {
  id: number;
  user_id: number;
  title: string | null;
  bio: string | null;
  years_experience: number | null;
  timezone: string;
  is_active: boolean;
  user: { id: number; name: string; email: string };
}

export interface AdminAvailabilityRule {
  id: number;
  coach_id: number;
  day_of_week: number;
  start_time: string;
  end_time: string;
  timezone: string;
}

export interface AdminCoachingService {
  id: number;
  coach_id: number;
  title: string;
  description: string | null;
  session_type: 'one_to_one' | 'group';
  duration_minutes: number;
  is_free: boolean;
  price_cents: number;
  currency: string;
  max_participants: number;
  is_active: boolean;
}

export interface AdminCoachingSession {
  id: number;
  coaching_service_id: number;
  scheduled_start: string;
  scheduled_end: string;
  status: string;
  meeting_url: string | null;
  bookings_count: number;
}

export const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
