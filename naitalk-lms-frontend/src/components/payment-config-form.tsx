'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { PaymentConfig } from '@/lib/commerce-types';

type Tab = 'client_owned' | 'managed';

interface ClientOwnedFormState {
  provider: string;
  public_key: string;
  secret_key: string;
  webhook_secret: string;
  fee_bearer: string;
  environment: string;
}

interface ManagedFormState {
  provider: string;
  business_name: string;
  settlement_bank_code: string;
  account_number: string;
  fee_bearer: string;
}

export function PaymentConfigForm({ initial }: { initial: PaymentConfig | null }) {
  const router = useRouter();
  const [config, setConfig] = useState(initial);
  const [tab, setTab] = useState<Tab>(initial?.mode ?? 'client_owned');
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<{ ok: boolean; message: string } | null>(null);
  const [copied, setCopied] = useState(false);

  async function testConnection() {
    setTesting(true);
    setTestResult(null);
    try {
      const res = await fetch('/api/v1/admin/payment-config/test-connection', { method: 'POST' });
      const body = await res.json().catch(() => null);
      if (!res.ok) {
        setTestResult({ ok: false, message: body?.errors?.provider?.[0] ?? 'Could not test the connection.' });
        return;
      }
      setTestResult(
        body.data.connected
          ? { ok: true, message: 'Connection verified.' }
          : { ok: false, message: 'Could not verify the connection with the provider.' }
      );
      router.refresh();
    } finally {
      setTesting(false);
    }
  }

  async function copyWebhookUrl() {
    if (!config) return;
    await navigator.clipboard.writeText(config.webhook_url);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  return (
    <div className="space-y-6">
      {config && (
        <div className="rounded-xl border border-neutral-200 bg-white p-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-sm font-semibold text-neutral-900">
                {config.mode === 'managed' ? 'NAI TALK Managed Payments' : 'Client-Owned Gateway'} &middot;{' '}
                <span className="capitalize">{config.provider}</span>
              </p>
              <p className="mt-1 text-xs text-neutral-500">
                {config.public_key_masked ?? (config.mode === 'managed' ? 'Platform credentials' : 'No public key set')}
                {' · '}
                Secret {config.has_secret_configured ? 'configured' : 'missing'}
                {' · '}
                Commission {config.commission_percent}%
                {' · '}
                {config.environment}
              </p>
              {config.last_verified_at && (
                <p className="mt-1 text-xs text-neutral-400">Last verified {new Date(config.last_verified_at).toLocaleString()}</p>
              )}
            </div>
            <button
              onClick={testConnection}
              disabled={testing}
              className="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-semibold hover:bg-neutral-50 disabled:opacity-60"
            >
              {testing ? 'Testing…' : 'Test connection'}
            </button>
          </div>
          {testResult && (
            <p
              className={`mt-2 inline-block rounded-md px-2.5 py-1 text-xs font-semibold ${
                testResult.ok ? 'bg-green-600 text-white' : 'bg-red-50 text-red-700'
              }`}
            >
              {testResult.message}
            </p>
          )}

          <div className="mt-4 rounded-md bg-amber-50 px-3 py-2.5">
            <p className="text-xs font-semibold text-amber-900">Webhook URL — required for payments to confirm</p>
            <p className="mt-0.5 text-xs text-amber-800">
              Paste this into your {config.provider === 'paystack' ? 'Paystack' : 'Flutterwave'} dashboard&apos;s webhook
              settings. Without it, a successful payment on their side never reaches this app — the order stays stuck
              &quot;confirming&quot; forever.
            </p>
            <div className="mt-2 flex items-center gap-2">
              <code className="flex-1 truncate rounded border border-amber-200 bg-white px-2 py-1.5 text-xs text-neutral-700">
                {config.webhook_url}
              </code>
              <button
                onClick={copyWebhookUrl}
                className="shrink-0 rounded-md border border-amber-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-100"
              >
                {copied ? 'Copied!' : 'Copy'}
              </button>
            </div>
          </div>
        </div>
      )}

      <div className="rounded-xl border border-neutral-200 bg-white p-5">
        <div className="flex gap-1 border-b border-neutral-200">
          <TabButton active={tab === 'client_owned'} onClick={() => setTab('client_owned')}>
            Use my own gateway
          </TabButton>
          <TabButton active={tab === 'managed'} onClick={() => setTab('managed')}>
            Use NAI TALK managed payments
          </TabButton>
        </div>

        {/* Both forms stay mounted and are only hidden via CSS — switching
            tabs must not discard credentials the user has typed but not
            yet saved. */}
        <div className={`pt-5 ${tab === 'client_owned' ? '' : 'hidden'}`}>
          <ClientOwnedForm current={config?.mode === 'client_owned' ? config : null} onSaved={(c) => setConfig(c)} />
        </div>
        <div className={`pt-5 ${tab === 'managed' ? '' : 'hidden'}`}>
          <ManagedForm current={config?.mode === 'managed' ? config : null} onSaved={(c) => setConfig(c)} />
        </div>
      </div>
    </div>
  );
}

function TabButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      onClick={onClick}
      className={`px-4 py-2 text-sm font-medium ${
        active
          ? 'border-b-2 border-[var(--tenant-primary)] text-[var(--tenant-primary)]'
          : 'text-neutral-500 hover:text-neutral-700'
      }`}
    >
      {children}
    </button>
  );
}

function ClientOwnedForm({ current, onSaved }: { current: PaymentConfig | null; onSaved: (config: PaymentConfig) => void }) {
  const router = useRouter();
  const [form, setForm] = useState<ClientOwnedFormState>({
    provider: current?.provider ?? 'paystack',
    public_key: current?.public_key ?? '',
    secret_key: '',
    webhook_secret: '',
    fee_bearer: current?.fee_bearer ?? 'tenant',
    environment: current?.environment ?? 'test',
  });
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);
    setSaved(false);

    try {
      const res = await fetch('/api/v1/admin/payment-config/client-owned', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(firstError(body) ?? 'Could not save your gateway credentials.');
        return;
      }

      onSaved(body.data);
      setForm((f) => ({ ...f, secret_key: '', webhook_secret: '' }));
      setSaved(true);
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <p className="text-xs text-neutral-500">
        Connect your own Paystack or Flutterwave account. Payments settle directly to you — NAI TALK never touches this
        money and charges no commission.
      </p>

      {current && (
        <p className="rounded-md bg-neutral-50 px-3 py-2 text-xs text-neutral-600">
          Public key loaded below from your saved config. Secret{' '}
          {current.has_secret_configured ? 'is configured' : 'is missing'} but can&apos;t be shown here — saving
          requires re-entering the secret key and webhook secret even if you&apos;re only changing something else.
        </p>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Provider">
          <select
            value={form.provider}
            onChange={(e) => setForm((f) => ({ ...f, provider: e.target.value }))}
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
          >
            <option value="paystack">Paystack</option>
            <option value="flutterwave">Flutterwave</option>
          </select>
        </Field>
        <Field label="Environment">
          <select
            value={form.environment}
            onChange={(e) => setForm((f) => ({ ...f, environment: e.target.value }))}
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
          >
            <option value="test">Test</option>
            <option value="live">Live</option>
          </select>
        </Field>
      </div>

      <Field label="Public key">
        <input
          value={form.public_key}
          onChange={(e) => setForm((f) => ({ ...f, public_key: e.target.value }))}
          required
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        />
      </Field>

      <Field label="Secret key">
        <input
          type="password"
          value={form.secret_key}
          onChange={(e) => setForm((f) => ({ ...f, secret_key: e.target.value }))}
          required
          autoComplete="off"
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        />
      </Field>

      <Field label="Webhook secret">
        <input
          type="password"
          value={form.webhook_secret}
          onChange={(e) => setForm((f) => ({ ...f, webhook_secret: e.target.value }))}
          required
          autoComplete="off"
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        />
        <p className="mt-1 text-xs text-neutral-400">
          Flutterwave: the dashboard-configured secret hash. Paystack: re-enter your secret key — it signs webhooks too.
        </p>
      </Field>

      <Field label="Who bears the provider fee?">
        <select
          value={form.fee_bearer}
          onChange={(e) => setForm((f) => ({ ...f, fee_bearer: e.target.value }))}
          className="mt-1 w-full max-w-xs rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        >
          <option value="tenant">You (deducted from your payout)</option>
          <option value="learner">The learner (added at checkout)</option>
        </select>
      </Field>

      {error && <p className="text-sm text-red-600">{error}</p>}
      {saved && <p className="text-sm text-green-600">Saved.</p>}

      <button
        type="submit"
        disabled={pending}
        className="rounded-md bg-[var(--tenant-accent)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
      >
        {pending ? 'Saving…' : 'Save credentials'}
      </button>
    </form>
  );
}

function ManagedForm({ current, onSaved }: { current: PaymentConfig | null; onSaved: (config: PaymentConfig) => void }) {
  const router = useRouter();
  const [form, setForm] = useState<ManagedFormState>({
    provider: current?.provider ?? 'paystack',
    business_name: '',
    settlement_bank_code: '',
    account_number: '',
    fee_bearer: current?.fee_bearer ?? 'tenant',
  });
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);
    setSaved(false);

    try {
      const res = await fetch('/api/v1/admin/payment-config/managed', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(firstError(body) ?? 'Could not activate managed payments.');
        return;
      }

      onSaved(body.data);
      setSaved(true);
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <p className="text-xs text-neutral-500">
        Let NAI TALK handle payments end-to-end. We create a split-settlement subaccount so your share lands with you
        automatically — NAI TALK deducts a small commission at the provider level, never manually.
      </p>

      {current?.subaccount_code && (
        <p className="rounded-md bg-neutral-50 px-3 py-2 text-xs text-neutral-600">
          Already active with subaccount <span className="font-mono">{current.subaccount_code}</span>. Saving below
          creates a new subaccount and replaces it.
        </p>
      )}

      <Field label="Provider">
        <select
          value={form.provider}
          onChange={(e) => setForm((f) => ({ ...f, provider: e.target.value }))}
          className="mt-1 w-full max-w-xs rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        >
          <option value="paystack">Paystack</option>
          <option value="flutterwave">Flutterwave</option>
        </select>
      </Field>

      <Field label="Business name">
        <input
          value={form.business_name}
          onChange={(e) => setForm((f) => ({ ...f, business_name: e.target.value }))}
          required
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        />
      </Field>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Settlement bank code">
          <input
            value={form.settlement_bank_code}
            onChange={(e) => setForm((f) => ({ ...f, settlement_bank_code: e.target.value }))}
            required
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
          />
        </Field>
        <Field label="Account number">
          <input
            value={form.account_number}
            onChange={(e) => setForm((f) => ({ ...f, account_number: e.target.value }))}
            required
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
          />
        </Field>
      </div>

      <Field label="Who bears the provider fee?">
        <select
          value={form.fee_bearer}
          onChange={(e) => setForm((f) => ({ ...f, fee_bearer: e.target.value }))}
          className="mt-1 w-full max-w-xs rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        >
          <option value="tenant">You (deducted from your payout)</option>
          <option value="learner">The learner (added at checkout)</option>
        </select>
      </Field>

      {error && <p className="text-sm text-red-600">{error}</p>}
      {saved && <p className="text-sm text-green-600">Managed payments activated.</p>}

      <button
        type="submit"
        disabled={pending}
        className="rounded-md bg-[var(--tenant-accent)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
      >
        {pending ? 'Activating…' : 'Activate managed payments'}
      </button>
    </form>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-sm font-medium text-neutral-700">{label}</label>
      {children}
    </div>
  );
}

function firstError(body: unknown): string | null {
  if (!body || typeof body !== 'object') return null;
  const errors = (body as { errors?: unknown }).errors;
  if (!errors) return null;
  if (Array.isArray(errors)) return (errors[0] as { message?: string })?.message ?? null;
  const first = Object.values(errors as Record<string, unknown>)[0];
  return Array.isArray(first) ? String(first[0]) : null;
}
