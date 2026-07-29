export interface TenantMemberDetail {
  id: number;
  name: string;
  email: string;
  role: string;
  role_id: number;
  status: string;
  joined_at: string;
}

export interface TenantRole {
  id: number;
  name: string;
  slug: string;
}

export interface PendingInvitation {
  id: number;
  email: string;
  status: string;
  expires_at: string;
  created_at: string;
  role: { id: number; name: string };
}

export interface AdminStudent {
  id: number;
  name: string;
  email: string;
  status: string;
  joined_at: string;
  enrolments_count: number;
  completed_count: number;
  certificates_count: number;
}
