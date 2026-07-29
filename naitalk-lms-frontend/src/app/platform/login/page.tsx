import { PlatformLoginForm } from '@/components/platform-login-form';

export default function PlatformLoginPage() {
  return (
    <div className="flex min-h-full flex-1 items-center justify-center bg-slate-100 px-4 py-12">
      <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div className="mb-6 text-center">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src="/naitalk-logo.png" alt="NAI TALK" className="mx-auto h-8 w-auto" />
          <p className="mt-2 text-xs font-medium text-slate-500">PLATFORM CONTROL</p>
        </div>
        <PlatformLoginForm />
      </div>
    </div>
  );
}
