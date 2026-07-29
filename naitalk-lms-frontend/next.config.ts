import type { NextConfig } from "next";
import path from "node:path";

const nextConfig: NextConfig = {
  // An unrelated lockfile in the user's home directory was otherwise being
  // detected as a second workspace root.
  turbopack: {
    root: path.join(__dirname),
  },
  // Next.js only trusts `localhost` for dev-server/HMR requests by default
  // and silently blocks cross-origin ones — but this app's tenant routing
  // is entirely hostname-based (hrgems.naitalk-lms.test, other tenant
  // subdomains, api.naitalk-lms.test), so every real dev visit looks
  // "cross-origin" to Next.js. The blocked HMR WebSocket handshake
  // (net::ERR_INVALID_HTTP_RESPONSE, endlessly retried) was part of the
  // client bootstrap sequence, which meant hydration — and therefore every
  // client-side event handler, including the login form's submit — never
  // completed.
  allowedDevOrigins: ['naitalk-lms.test', '*.naitalk-lms.test'],
};

export default nextConfig;
