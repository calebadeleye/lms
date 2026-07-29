export interface CoachingServiceSummary {
  id: number;
  title: string;
  description: string | null;
  session_type: 'one_to_one' | 'group';
  duration_minutes: number;
  is_free: boolean;
  price_cents: number;
  currency: string;
}

export interface CoachSummary {
  id: number;
  name: string;
  title: string | null;
  bio: string | null;
  years_experience: number | null;
  timezone: string;
  services: CoachingServiceSummary[];
}

export interface AvailabilityRule {
  id: number;
  day_of_week: number;
  start_time: string;
  end_time: string;
  timezone: string;
}

export interface CoachDetail extends CoachSummary {
  availability_rules: AvailabilityRule[];
}

export interface CoachingSessionOccurrence {
  id: number;
  scheduled_start: string;
  scheduled_end: string;
  status: string;
  bookings_count: number;
}

export interface BookingRecord {
  id: number;
  status: string;
  booked_at: string;
  cancelled_at: string | null;
  session: {
    id: number;
    scheduled_start: string;
    scheduled_end: string;
    status: string;
    meeting_url: string | null;
    service: CoachingServiceSummary;
    coach: { id: number; user: { id: number; name: string } };
  };
}

export const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
