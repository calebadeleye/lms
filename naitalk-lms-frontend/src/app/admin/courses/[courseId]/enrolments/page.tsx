import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { IssueCertificateButton } from '@/components/admin/issue-certificate-button';

interface EnrolmentRosterItem {
  id: number;
  status: string;
  enrolled_at: string;
  completed_at: string | null;
  completion_percent: number;
  has_certificate: boolean;
  user: { id: number; name: string; email: string };
}

export default async function CourseEnrolmentsPage({ params }: { params: Promise<{ courseId: string }> }) {
  const [config, me] = await Promise.all([getTenantConfig(), requireUser()]);
  if (!config) notFound();
  if (!me.permissions.includes('students.manage')) redirect('/dashboard');

  const { courseId } = await params;
  const enrolments = await apiFetch<{ data: EnrolmentRosterItem[] }>(`/api/v1/admin/courses/${courseId}/enrolments`);

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={tenantAdminNav} userName={me.user.name} activeHref="/admin/courses">
      <Link href={`/admin/courses/${courseId}`} className="text-xs font-medium text-neutral-500 hover:underline">
        ← Back to course
      </Link>
      <h1 className="mt-1 text-xl font-bold text-neutral-900">Learners</h1>

      <div className="mt-6 overflow-x-auto rounded-xl border border-neutral-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-neutral-200 bg-neutral-50 text-xs uppercase text-neutral-500">
            <tr>
              <th className="px-4 py-3">Name</th>
              <th className="px-4 py-3">Email</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Progress</th>
              <th className="px-4 py-3">Enrolled</th>
              <th className="px-4 py-3">Certificate</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {enrolments.data.map((enrolment) => (
              <tr key={enrolment.id}>
                <td className="px-4 py-3 font-medium text-neutral-900">{enrolment.user.name}</td>
                <td className="px-4 py-3 text-neutral-600">{enrolment.user.email}</td>
                <td className="px-4 py-3 capitalize text-neutral-600">{enrolment.status}</td>
                <td className="px-4 py-3 text-neutral-600">{enrolment.completion_percent}%</td>
                <td className="px-4 py-3 text-neutral-600">{new Date(enrolment.enrolled_at).toLocaleDateString()}</td>
                <td className="px-4 py-3">
                  {enrolment.has_certificate ? (
                    <span className="text-xs font-medium text-green-600">Issued</span>
                  ) : enrolment.status === 'completed' && me.permissions.includes('certificates.issue') ? (
                    <IssueCertificateButton enrolmentId={enrolment.id} />
                  ) : (
                    <span className="text-xs text-neutral-400">—</span>
                  )}
                </td>
              </tr>
            ))}
            {enrolments.data.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-6 text-center text-neutral-500">
                  No learners enrolled yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </DashboardShell>
  );
}
