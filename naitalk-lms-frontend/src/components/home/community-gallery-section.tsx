import { HOME_CONTENT } from '@/lib/home-content';
import { BrandIcon, type BrandIconName } from '@/components/home/brand-icon';

const descriptions = [
  'Interactive sessions that inspire clarity.',
  'Learn from peers and share real experiences.',
  'Guidance that helps you grow with confidence.',
  'Build relationships that open new doors.',
  'Practical learning for real-world results.',
  'Transform from within and thrive daily.',
];

const shortTitles = ['Group Coaching', 'Peer Learning', 'Mentorship', 'Networking', 'Workshops', 'Personal Growth'];
const icons: BrandIconName[] = ['compass', 'people', 'community', 'people', 'star', 'target'];

export function CommunityGallerySection() {
  const { communityExperience } = HOME_CONTENT;

  return (
    <section id="experience" className="px-0 py-8 sm:px-5">
      <div className="relative mx-auto w-full max-w-[1240px] overflow-hidden rounded-[24px] border border-[#0b7477] bg-[radial-gradient(circle_at_50%_-10%,rgba(11,124,128,.72),transparent_43%),linear-gradient(110deg,#034b50_0%,#00666a_48%,#03484d_100%)] px-5 py-6 text-white shadow-[inset_0_1px_0_rgba(255,255,255,.08)] sm:px-7 lg:px-8">
        <div className="text-center">
          <p className="text-[11px] font-extrabold uppercase tracking-[0.06em] text-white/95 sm:text-xs">The HR GEMs Experience</p>
          <h2 className="mt-1 text-[22px] font-extrabold leading-tight tracking-[-0.03em] sm:text-[28px]">Learn. Connect. Grow. Impact<span className="text-[#f4b728]">.</span></h2>
        </div>

        <div className="mt-4 grid grid-cols-2 gap-x-3 gap-y-8 md:grid-cols-3 lg:grid-cols-6 lg:gap-4">
          {communityExperience.items.map((item, index) => (
            <article key={item.caption} className="text-center">
              <div className="relative pb-[21px]">
                <div className="h-[150px] overflow-hidden rounded-[14px] border border-[#d6c89f]/85 bg-[#073d40] lg:h-[145px]">
                  {item.photo.src && (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={item.photo.src} alt={item.photo.alt} className="h-full w-full object-cover transition duration-500 hover:scale-[1.04]" />
                  )}
                </div>
                <span className="absolute bottom-0 left-1/2 grid h-[43px] w-[43px] -translate-x-1/2 place-items-center rounded-full border-2 border-white bg-[#f7f6f0] text-[#006c70] shadow-[0_3px_8px_rgba(0,0,0,.12)]">
                  <BrandIcon name={icons[index]} className="h-6 w-6" />
                </span>
              </div>
              <h3 className="mt-2 text-sm font-extrabold leading-tight text-white">{shortTitles[index]}</h3>
              <p className="mx-auto mt-2 max-w-[165px] text-[11px] leading-[1.5] text-white/88">{descriptions[index]}</p>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}
