'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { TenantMemberDetail, TenantRole, PendingInvitation } from '@/lib/admin-users-types';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { SearchInput } from '@/components/search-input';

const PER_PAGE = 20;

export function AdminUsersManager({
  initialMembers,
  initialMeta,
  initialInvitations,
  roles,
  currentUserId,
}: {
  initialMembers: TenantMemberDetail[];
  initialMeta: PaginationMeta;
  initialInvitations: PendingInvitation[];
  roles: TenantRole[];
  currentUserId: number;
}) {
  const router = useRouter();
  const [members, setMembers] = useState(initialMembers);
  const [meta, setMeta] = useState(initialMeta);
  const [invitations, setInvitations] = useState(initialInvitations);
  const [showInactive, setShowInactive] = useState(false);
  const [search, setSearch] = useState('');
  const [email, setEmail] = useState('');
  const [roleId, setRoleId] = useState(roles[0]?.id.toString() ?? '');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  async function loadMembers({
    page = 1,
    includeInactive = showInactive,
    query = search,
  }: { page?: number; includeInactive?: boolean; query?: string } = {}) {
    const params = new URLSearchParams({ page: String(page), per_page: String(PER_PAGE) });
    if (includeInactive) params.set('include_inactive', '1');
    if (query) params.set('search', query);

    const res = await fetch(`/api/v1/admin/tenant-users?${params.toString()}`);
    const body = await res.json();
    setMembers(body.data ?? []);
    setMeta(body.meta?.pagination ?? { page: 1, per_page: PER_PAGE, total: (body.data ?? []).length });
  }

  async function toggleShowInactive() {
    const next = !showInactive;
    setShowInactive(next);
    await loadMembers({ includeInactive: next });
  }

  async function handleSearch(query: string) {
    setSearch(query);
    await loadMembers({ query, page: 1 });
  }

  async function invite(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);
    setNotice(null);

    try {
      const res = await fetch('/api/v1/admin/invitations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, role_id: Number(roleId) }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.email?.[0] ?? body?.errors?.[0]?.message ?? 'Could not send invitation.');
        return;
      }

      if (body.data.status === 'added_existing_user') {
        setNotice(`${email} already has an account — added directly.`);
        await loadMembers();
      } else {
        setNotice(`Invitation sent to ${email}.`);
        setInvitations((prev) => [body.data, ...prev]);
      }
      setEmail('');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  async function changeRole(userId: number, newRoleId: string) {
    const res = await fetch(`/api/v1/admin/tenant-users/${userId}/role`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ role_id: Number(newRoleId) }),
    });
    if (res.ok) {
      const roleName = roles.find((r) => r.id === Number(newRoleId))?.name ?? '';
      setMembers((prev) =>
        prev.map((m) => (m.id === userId ? { ...m, role_id: Number(newRoleId), role: roleName } : m))
      );
      router.refresh();
    }
  }

  async function deactivate(userId: number) {
    if (!window.confirm('Remove this person\'s access to the admin panel?')) return;
    await fetch(`/api/v1/admin/tenant-users/${userId}`, { method: 'DELETE' });
    await loadMembers();
    router.refresh();
  }

  async function reactivate(userId: number) {
    await fetch(`/api/v1/admin/tenant-users/${userId}/reactivate`, { method: 'POST' });
    await loadMembers();
    router.refresh();
  }

  async function revokeInvitation(invitationId: number) {
    await fetch(`/api/v1/admin/invitations/${invitationId}`, { method: 'DELETE' });
    setInvitations((prev) => prev.filter((i) => i.id !== invitationId));
    router.refresh();
  }

  return (
    <div className="space-y-6">
      <form onSubmit={invite} className="grid gap-3 rounded-xl border border-neutral-200 bg-white p-4 sm:grid-cols-4">
        <div className="sm:col-span-2">
          <label className="block text-xs font-medium text-neutral-700">Invite by email</label>
          <input
            required
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="colleague@example.com"
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-neutral-700">Role</label>
          <select
            value={roleId}
            onChange={(e) => setRoleId(e.target.value)}
            className="mt-1 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
          >
            {roles.map((r) => (
              <option key={r.id} value={r.id}>
                {r.name}
              </option>
            ))}
          </select>
        </div>
        <div className="flex items-end">
          <button
            type="submit"
            disabled={pending || !email}
            className="w-full rounded-md bg-[var(--tenant-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
          >
            {pending ? 'Sending…' : 'Invite'}
          </button>
        </div>
        {error && <p className="sm:col-span-4 text-sm text-red-600">{error}</p>}
        {notice && <p className="sm:col-span-4 text-sm text-green-600">{notice}</p>}
      </form>

      {invitations.length > 0 && (
        <div className="rounded-xl border border-neutral-200 bg-white p-4">
          <h2 className="text-sm font-semibold text-neutral-900">Pending invitations</h2>
          <ul className="mt-3 divide-y divide-neutral-100">
            {invitations.map((invite) => (
              <li key={invite.id} className="flex items-center justify-between py-2 text-sm">
                <span className="text-neutral-700">
                  {invite.email} <span className="text-xs text-neutral-400">as {invite.role.name}</span>
                </span>
                <button onClick={() => revokeInvitation(invite.id)} className="text-xs font-medium text-red-600 hover:underline">
                  Revoke
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 px-4 py-3">
          <h2 className="text-sm font-semibold text-neutral-900">Members</h2>
          <div className="flex items-center gap-3">
            <SearchInput placeholder="Search by name or email…" onSearch={handleSearch} />
            <label className="flex items-center gap-1.5 whitespace-nowrap text-xs text-neutral-600">
              <input type="checkbox" checked={showInactive} onChange={toggleShowInactive} />
              Show deactivated
            </label>
          </div>
        </div>
        <table className="w-full text-left text-sm">
          <thead className="border-b border-neutral-200 bg-neutral-50 text-xs uppercase text-neutral-500">
            <tr>
              <th className="px-4 py-3">Name</th>
              <th className="px-4 py-3">Email</th>
              <th className="px-4 py-3">Role</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Joined</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {members.map((member) => (
              <tr key={member.id}>
                <td className="px-4 py-3 font-medium text-neutral-900">{member.name}</td>
                <td className="px-4 py-3 text-neutral-600">{member.email}</td>
                <td className="px-4 py-3">
                  {member.id === currentUserId ? (
                    <span className="text-neutral-600">{member.role} (you)</span>
                  ) : (
                    <select
                      value={member.role_id}
                      onChange={(e) => changeRole(member.id, e.target.value)}
                      className="rounded-md border border-neutral-300 bg-white px-2 py-1 text-xs focus:border-[var(--tenant-primary)] focus:outline-none"
                    >
                      {roles.map((r) => (
                        <option key={r.id} value={r.id}>
                          {r.name}
                        </option>
                      ))}
                    </select>
                  )}
                </td>
                <td className="px-4 py-3">
                  <span
                    className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                      member.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-700'
                    }`}
                  >
                    {member.status}
                  </span>
                </td>
                <td className="px-4 py-3 text-neutral-600">{new Date(member.joined_at).toLocaleDateString()}</td>
                <td className="px-4 py-3 text-right text-xs font-medium">
                  {member.id === currentUserId ? null : member.status === 'active' ? (
                    <button onClick={() => deactivate(member.id)} className="text-red-600 hover:underline">
                      Deactivate
                    </button>
                  ) : (
                    <button onClick={() => reactivate(member.id)} className="text-neutral-700 hover:underline">
                      Reactivate
                    </button>
                  )}
                </td>
              </tr>
            ))}
            {members.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-6 text-center text-neutral-500">
                  No members found.
                </td>
              </tr>
            )}
          </tbody>
        </table>
        <Pagination meta={meta} onPageChange={(page) => loadMembers({ page })} />
      </div>
    </div>
  );
}
