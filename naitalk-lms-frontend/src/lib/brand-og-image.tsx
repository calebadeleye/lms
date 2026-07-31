import { ImageResponse } from 'next/og';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';

export const OG_IMAGE_SIZE = { width: 1200, height: 630 };
export const OG_IMAGE_ALT = 'HR GEMs Coach Network — Find Your Career Fit';

/** Shared by app/opengraph-image.tsx and app/twitter-image.tsx so the two
 * file conventions don't duplicate the render tree. */
export async function renderBrandOgImage() {
  const logoData = await readFile(join(process.cwd(), 'public/branding/logo.png'), 'base64');
  const logoSrc = `data:image/png;base64,${logoData}`;

  return new ImageResponse(
    (
      <div
        style={{
          width: '100%',
          height: '100%',
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'center',
          padding: '80px',
          background: 'linear-gradient(135deg, #008080 0%, #006666 55%, #F5D908 130%)',
        }}
      >
        <div
          style={{
            display: 'flex',
            padding: '20px 28px',
            borderRadius: 20,
            background: '#ffffff',
          }}
        >
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={logoSrc} width={200} height={72} alt="" />
        </div>
        <div
          style={{
            marginTop: 56,
            fontSize: 68,
            fontWeight: 700,
            color: '#ffffff',
            lineHeight: 1.1,
            maxWidth: 920,
          }}
        >
          Find Your Career Fit
        </div>
        <div style={{ marginTop: 24, fontSize: 30, color: '#F9E86B', maxWidth: 820 }}>
          Discover your strengths, personality, values, and purpose — with HR GEMs Coach Network.
        </div>
      </div>
    ),
    OG_IMAGE_SIZE,
  );
}
