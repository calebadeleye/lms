/**
 * All homepage/marketing copy for HR GEMs Coach Network, sourced from the
 * client's own mockup and the WhatsApp value-proposition message shared
 * earlier in this project. Static, like `branding.ts` — this app serves one
 * organization, so content is a source constant you edit and redeploy, not
 * a database-backed CMS.
 */

export interface PhotoSlot {
  /** null until a real asset is supplied — every consumer falls back to the
   * site's teal-to-gold gradient placeholder. Currently populated from
   * Pexels via scripts/fetch-marketing-images.mjs (see public/marketing/CREDITS.md);
   * swap for the client's own photography whenever it's supplied. */
  src: string | null;
  alt: string;
}

export const HOME_CONTENT = {
  hero: {
    heading: 'Find Your Career Fit',
    subheading: 'A practical guide to discovering your strengths, personality, values, and purpose.',
    description:
      'Too many people choose careers based on trends, pressure, family expectations, or what appears ' +
      'financially attractive. The Find Your Career Fit course helps participants understand ' +
      'themselves, identify suitable career paths, and make informed, purpose-driven career decisions.',
    ctaPrimary: { label: 'Explore the Course', href: '/courses' },
    ctaSecondary: { label: 'Join HR GEMs', href: '/register' },
    audiences: ['Recent Graduates', 'Career Explorers', 'Professionals Seeking a New Direction'],
    quote: 'Understand yourself. Discover your purpose. Build a career that fits.',
    photo: { src: '/marketing/hero.jpg', alt: 'HR GEMs coaching session' } as PhotoSlot,
  },

  whoWeAre: {
    eyebrow: 'Who We Are',
    heading: 'Transforming Professionals into Change Agents',
    meaning: 'HR GEMs means Great.Excellent.Minds.',
    paragraphs: [
      'HR GEMs Coach Network is a community of professionals, primarily within Human Resources, who ' +
        'are learning transformational skills that prepare them to become change agents in the Work of Now.',
      'Members begin by transforming themselves and then use tools such as coaching, Neuro-Linguistic ' +
        'Programming, Cognitive Behavioural Therapy, and other transformational techniques to positively ' +
        'influence individuals, organisations, and society.',
    ],
    photo: { src: '/marketing/who-we-are.jpg', alt: 'A group of hands joined together' } as PhotoSlot,
  },

  whyItMatters: {
    eyebrow: 'Why the Course Matters',
    heading: 'Discover the Career Where You Can Thrive',
    cards: [
      { icon: 'strength', title: 'Discover your natural strengths', description: 'Identify what comes naturally to you and where you excel.' },
      { icon: 'personality', title: 'Understand your personality', description: 'Gain clarity about your personality and how you work best.' },
      { icon: 'values', title: 'Clarify your values', description: 'Understand what matters most to you in life and work.' },
      { icon: 'paths', title: 'Identify suitable career paths', description: 'Explore career options that align with who you are.' },
      { icon: 'decision', title: 'Make confident career decisions', description: 'Make informed choices that lead to long-term fulfilment.' },
    ] as { icon: 'strength' | 'personality' | 'values' | 'paths' | 'decision'; title: string; description: string }[],
  },

  whoShouldJoin: {
    eyebrow: 'Who Should Join HR GEMs?',
    heading: 'We Welcome Those Who',
    // Mirrors register-form.tsx's REQUIREMENTS wording exactly, restated in
    // the third person for marketing copy — keep both in sync if either changes.
    items: [
      'Are passionate about creating an impact beyond simply earning a living',
      'Are open-minded, growth-oriented, flexible, and willing to transform',
      'Have a genuine interest in learning coaching and related transformational skills',
      'Are committed to making a positive difference in the world',
    ],
  },

  membershipBenefits: {
    eyebrow: 'Membership Benefits',
    heading: 'What You Gain as a Member',
    items: [
      { icon: 'users', text: 'Free access to a learning community of like-minded coaches and aspiring coaches' },
      { icon: 'calendar', text: 'Opportunity to receive three months of pro bono coaching, subject to coach availability' },
      { icon: 'mentor', text: 'Free three-month coaching mentorship for coaches in training who need to acquire coaching hours' },
      { icon: 'team', text: 'Opportunity to participate in team coaching sessions and collaborative learning' },
      { icon: 'tag', text: 'Special discounted rates from selected coach training institute partners' },
    ] as { icon: 'users' | 'calendar' | 'mentor' | 'team' | 'tag'; text: string }[],
  },

  memberVoices: {
    eyebrow: 'What Our Members Say',
    heading: 'Voices of Transformation',
    testimonials: [
      { quote: 'HR GEMs helped me discover my true calling and build the confidence to pursue a career I love.', author: 'Aisha O., HR Professional' },
      { quote: "The coaching skills I've gained are transforming not only my career but also the lives of others I support.", author: 'Tunde B., Coach in Training' },
      { quote: 'A supportive community that challenges me to grow, learn, and make a real difference in the world.', author: 'Funmi A., Career Explorer' },
    ],
    moreLink: { label: 'Read more stories', href: '/testimonials' },
  },

  communityExperience: {
    eyebrow: 'Our Community Experience',
    heading: 'Learn. Connect. Grow. Create Impact.',
    items: [
      { caption: 'Group Coaching Sessions', photo: { src: '/marketing/community-1.jpg', alt: 'Group coaching session' } as PhotoSlot },
      { caption: 'Peer Learning', photo: { src: '/marketing/community-2.jpg', alt: 'Peer learning session' } as PhotoSlot },
      { caption: 'Mentorship', photo: { src: '/marketing/community-3.jpg', alt: 'Mentorship conversation' } as PhotoSlot },
      { caption: 'Professional Networking', photo: { src: '/marketing/community-4.jpg', alt: 'Professional networking event' } as PhotoSlot },
      { caption: 'Transformational Workshops', photo: { src: '/marketing/community-5.jpg', alt: 'Transformational workshop' } as PhotoSlot },
      { caption: 'Personal & Career Development', photo: { src: '/marketing/community-6.jpg', alt: 'Personal and career development session' } as PhotoSlot },
    ],
  },

  moreStories: {
    heading: 'More Stories. More Impact.',
    testimonials: [
      { quote: 'HR GEMs has been a game-changer. The tools and community support have helped me become a better leader and coach.', author: 'Chinedu K., HR Manager' },
      { quote: 'I came for the course, but stayed for the people. This community feels like family.', author: 'Esther I., Aspiring Coach' },
      { quote: 'The mentorship and practical learning here are simply outstanding.', author: 'David M., Career Changer' },
    ],
    photo: { src: '/marketing/more-stories.jpg', alt: 'A member reflecting on their journey' } as PhotoSlot,
    moreLink: { label: 'View More Testimonials', href: '/testimonials' },
  },

  footer: {
    quickLinks: [
      { label: 'Home', href: '/' },
      { label: 'Courses', href: '/courses' },
      { label: 'Membership', href: '/membership' },
      { label: 'Coaching', href: '/coaching' },
      { label: 'About', href: '/about' },
    ],
    // Placeholder hrefs — real profile URLs weren't supplied yet, and a
    // guessed URL is worse than an inert link. Fill these in once known.
    social: [
      { platform: 'facebook', href: '#' },
      { platform: 'linkedin', href: '#' },
      { platform: 'instagram', href: '#' },
      { platform: 'youtube', href: '#' },
    ] as { platform: 'facebook' | 'linkedin' | 'instagram' | 'youtube'; href: string }[],
    contactEmail: 'info@hrgemscoachnetwork.com',
    legalLinks: [
      { label: 'Privacy Policy', href: '/coming-soon?feature=Privacy%20Policy' },
      { label: 'Terms of Service', href: '/coming-soon?feature=Terms%20of%20Service' },
    ],
  },
};
