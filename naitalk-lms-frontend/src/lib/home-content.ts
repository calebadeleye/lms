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
    eyebrow: 'Career Clarity. Coaching. Transformation.',
    heading: 'Discover Who You Are. Build What Comes Next.',
    subheading: 'Discover who you are and build what comes next.',
    description:
      'HR GEMs helps professionals, graduates and aspiring coaches gain clarity, build transformational skills and create meaningful impact in their careers and communities.',
    ctaPrimary: { label: 'Explore Courses', href: '/courses' },
    ctaSecondary: { label: 'Join Our Community', href: '/register' },
    audiences: [
      { icon: 'graduate', label: 'Recent Graduates' },
      { icon: 'compass', label: 'Career Explorers' },
      { icon: 'briefcase', label: 'Professionals Seeking a New Direction' },
    ] as { icon: 'graduate' | 'compass' | 'briefcase'; label: string }[],
    quote: 'Join 2,000+ purpose-driven minds transforming their lives',
    photo: { src: '/marketing/community-2.jpg', alt: 'A confident professional woman working at her laptop in an office' } as PhotoSlot,
    featureCards: [
      { icon: 'compass', title: 'Career Clarity', description: 'Discover your strengths, values and direction.' },
      { icon: 'people', title: 'Coaching Skills', description: 'Learn practical coaching and people skills.' },
      { icon: 'leaf', title: 'Personal Growth', description: 'Build confidence, purpose and self-awareness.' },
      { icon: 'heart', title: 'Community', description: 'Connect, collaborate and create real impact.' },
    ],
  },

  impact: [
    { icon: 'people', value: '5+', label: 'Years of Impact' },
    { icon: 'community', value: '2,000+', label: 'Members Supported' },
    { icon: 'calendar', value: '1,200+', label: 'Coaching Sessions' },
    { icon: 'award', value: '80+', label: 'Courses Delivered' },
  ],

  programmes: {
    eyebrow: 'Our Courses',
    heading: 'Pathways to Your Next Level',
    description: 'Choose the path that meets you where you are and helps you become who you are meant to be.',
    items: [
      {
        title: 'Find Your Career Fit',
        description: 'Understand your personality, strengths and values to discover career paths that align with who you are.',
        action: 'Explore Course',
        href: '/courses',
        image: '/marketing/community-2.jpg',
        imageAlt: 'A professional exploring her career path',
        icon: 'compass',
      },
      {
        title: 'Transformational Coach Development',
        description: 'Build coaching skills, gain mentorship and learn to help others unlock their potential.',
        action: 'Explore Coaching',
        href: '/coaching',
        image: '/marketing/community-4.jpg',
        imageAlt: 'A mentoring session in a library',
        icon: 'people',
      },
      {
        title: 'HR GEMs Membership',
        description: 'Join a thriving community of professionals who learn, grow and support each other to create greater impact.',
        action: 'Become a Member',
        href: '/membership',
        image: '/marketing/community-5.jpg',
        imageAlt: 'Professionals connecting at a learning event',
        icon: 'community',
      },
    ],
  },

  whoWeAre: {
    eyebrow: 'About HR GEMs',
    heading: 'Great. Excellent. Minds.',
    meaning: 'HR GEMs means Great.Excellent.Minds.',
    paragraphs: [
      'HR GEMs is a transformational learning and coaching community committed to developing purpose-driven professionals and coaches who create impact in their workplaces and communities.',
      'We believe in the power of people, the beauty of collaboration and the impact of intentional growth.',
    ],
    quote: 'When great minds connect with purpose, transformation becomes inevitable.',
    photo: { src: '/marketing/hero.jpg', alt: 'Two women learning and growing together' } as PhotoSlot,
    values: [
      { title: 'Collaboration', description: 'We grow together and achieve more.', icon: 'people', color: 'teal' },
      { title: 'Love', description: 'We care deeply about people.', icon: 'heart', color: 'red' },
      { title: 'Impact', description: 'We create change that matters.', icon: 'star', color: 'gold' },
      { title: 'Purpose', description: 'We live and lead with meaning.', icon: 'target', color: 'green' },
    ],
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
    eyebrow: 'What Our Community Says',
    heading: 'Real Stories. Real Impact.',
    testimonials: [
      { quote: 'HR GEMs helped me understand my strengths and transition into a career I truly love.', author: 'Favour O.', role: 'Project Manager', photo: '/marketing/community-6.jpg' },
      { quote: 'The coaching skills I learned have transformed the way I lead and support my team.', author: 'Samuel A.', role: 'Team Lead', photo: '/marketing/community-1.jpg' },
      { quote: "The community is so supportive. I've built friendships and grown beyond my expectations.", author: 'Blessing N.', role: 'HR Professional', photo: '/marketing/community-5.jpg' },
    ],
  },

  communityExperience: {
    eyebrow: 'Our Community Experience',
    heading: 'Learn. Connect. Grow. Create Impact.',
    items: [
      { caption: 'Group Coaching Sessions', photo: { src: '/marketing/hero-career-fit.png', alt: 'Professionals taking part in group coaching' } as PhotoSlot },
      { caption: 'Peer Learning', photo: { src: '/marketing/hero.jpg', alt: 'Two peers learning together' } as PhotoSlot },
      { caption: 'Mentorship', photo: { src: '/marketing/community-3.jpg', alt: 'Mentorship conversation' } as PhotoSlot },
      { caption: 'Professional Networking', photo: { src: '/marketing/community-4.jpg', alt: 'Professional networking event' } as PhotoSlot },
      { caption: 'Transformational Workshops', photo: { src: '/marketing/community-1.jpg', alt: 'Collaborative learning workshop' } as PhotoSlot },
      { caption: 'Personal & Career Development', photo: { src: '/marketing/community-2.jpg', alt: 'A professional focused on personal growth' } as PhotoSlot },
    ],
  },

  moreStories: {
    heading: 'More Stories. More Impact.',
    // Real, anonymous participant feedback from the personality assessment
    // session — quotes only, no attributed names were collected for these.
    testimonials: [
      'It was a great overview of who I am',
      'It made me know there’s so much more i can archive and be with God.',
      'My overall experience with the personality assessment session was insightful and engaging. It helped me better understand my personality traits, strengths, and areas for improvement. The session also provided practical insights into how I communicate, work with others, and approach challenges. Overall, it was a valuable learning experience that increased my self-awareness and will help me in both my personal and professional development.',
      'Very insightful and interactive',
      'It was enlightening.',
      'My overall experience personality assessment session is ways to find purpose. I found it interesting and loved the purpose behind sharing it',
      'It was impactful and eye opening',
      'Awesome and eye opening. It was a positive and insightful experience. The assessment was easy to follow and provided valuable insights into my personality and work style.',
      'My overall experience was insightful and engaging. It helped me better understand my personality, strengths, and areas for growth, making the session both enjoyable and valuable.',
      "Wonderful and it's resonates exactly with my personality",
      'It was really an eye opener to who i really am.',
      'It was great, it help me know my self better',
      'I get to know more about myself and the areas to improve for growth.',
    ],
    photo: { src: '/marketing/more-stories.jpg', alt: 'A member reflecting on their journey' } as PhotoSlot,
  },

  footer: {
    quickLinks: [
      { label: 'Home', href: '/' },
      { label: 'Courses', href: '/courses' },
      { label: 'Membership', href: '/membership' },
      { label: 'Coaching', href: '/coaching' },
      { label: 'About', href: '/about' },
    ],
    social: [
      { platform: 'facebook', href: 'https://www.facebook.com/HR-GEMs-Coaches-Network-102910981613316/' },
      { platform: 'twitter', href: 'https://twitter.com/hrgemscoaches' },
      { platform: 'linkedin', href: 'https://www.linkedin.com/company/hr-g-e-ms-coach-network' },
      { platform: 'instagram', href: 'https://instagram.com/hrgemscoaches' },
      { platform: 'youtube', href: 'https://www.youtube.com/channel/UCngn418q588vUVd9G4DSbzw' },
    ] as { platform: 'facebook' | 'twitter' | 'linkedin' | 'instagram' | 'youtube'; href: string }[],
    contactEmail: 'info@hrgemscoachnetwork.com',
    legalLinks: [
      { label: 'Privacy Policy', href: '/coming-soon?feature=Privacy%20Policy' },
      { label: 'Terms of Service', href: '/coming-soon?feature=Terms%20of%20Service' },
    ],
  },
};
