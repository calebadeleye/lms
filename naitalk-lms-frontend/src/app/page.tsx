import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { HeroSection } from '@/components/home/hero-section';
import { ProgrammesSection } from '@/components/home/programmes-section';
import { WhoWeAreSection } from '@/components/home/who-we-are-section';
import { CommunityGallerySection } from '@/components/home/community-gallery-section';
import { TestimonialCarouselSection } from '@/components/home/testimonial-carousel-section';

export default async function HomePage() {
  const config = BRANDING;
  const user = await getOptionalUser();

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding.logo_url} isAuthenticated={user !== null} />

      <main className="flex-1">
        <HeroSection />
        <ProgrammesSection />
        <WhoWeAreSection />
        <CommunityGallerySection />
        <TestimonialCarouselSection />
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
