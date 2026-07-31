import { OG_IMAGE_ALT, OG_IMAGE_SIZE, renderBrandOgImage } from '@/lib/brand-og-image';

export const alt = OG_IMAGE_ALT;
export const size = OG_IMAGE_SIZE;
export const contentType = 'image/png';

export default async function Image() {
  return renderBrandOgImage();
}
