/**
 * Photo gallery albums. Photos live in public/gallery/<album-slug>/ in two
 * sizes: `NN-name.jpg` (1600px on its longest side, for the full-screen
 * viewer) and `NN-name-sm.jpg` (800px, for grids). Static, like `home-content.ts` —
 * add an album here and redeploy.
 *
 * Photos are picked from each event's shared Google Drive folder and resized
 * only, never edited. The May Day 2024 Hangout photos are stills taken from
 * that event's video, since its Drive folder only contains a video.
 */

export interface GalleryPhoto {
  /** 1600px (longest side) image for the full-screen viewer. */
  src: string;
  /** 800px (longest side) image for grids and cards. */
  thumb: string;
  alt: string;
  caption: string;
}

export interface GalleryAlbum {
  slug: string;
  title: string;
  year: number;
  description: string;
  photos: GalleryPhoto[];
}

function photo(album: string, file: string, caption: string, alt: string): GalleryPhoto {
  return {
    src: `/gallery/${album}/${file}.jpg`,
    thumb: `/gallery/${album}/${file}-sm.jpg`,
    caption,
    alt,
  };
}

const EVENT_2026 = 'workplace-transformation-2026';
const WORKERS_DAY_2025 = 'workers-day-2025';
const MAY_DAY_2024 = 'may-day-2024-hangout';

export const GALLERY_ALBUMS: GalleryAlbum[] = [
  {
    slug: EVENT_2026,
    title: 'HR GEMs 2026 Event — Workplace Transformation: A Clarion Call',
    year: 2026,
    description: 'Keynotes, panels, drummers and dancers, and a hall full of HR professionals answering the call to transform the workplace.',
    photos: [
      photo(EVENT_2026, '16-group-photo', 'The whole family', 'A large group of attendees, many in yellow HR GEMs T-shirts, posing together in front of the stage'),
      photo(EVENT_2026, '09-keynote', 'Keynote address', 'A speaker addressing the audience from the podium in front of the HR GEM Coach Network backdrop'),
      photo(EVENT_2026, '12-panel', 'The panel', 'Panellists seated on white chairs on the Workplace Transformation stage'),
      photo(EVENT_2026, '10-full-hall', 'A full hall', 'A wide view of the hall, rows of seated guests facing the stage along a green carpet'),
      photo(EVENT_2026, '03-drummers', 'Talking drums', 'Two drummers in traditional attire playing drums'),
      photo(EVENT_2026, '04-cultural-dancers', 'A cultural welcome', 'Two dancers in traditional attire performing in front of the speakers wall'),
      photo(EVENT_2026, '05-opening-remarks', 'Opening remarks', 'A host in a yellow HR GEMs T-shirt speaking at the podium'),
      photo(EVENT_2026, '07-question-time', 'Question time', 'An attendee in yellow asking a question into a microphone'),
      photo(EVENT_2026, '14-fireside-chat', 'Fireside chat', 'Three speakers in conversation on white sofas on stage'),
      photo(EVENT_2026, '06-all-smiles', 'All smiles', 'Three smiling attendees seated in the audience'),
      photo(EVENT_2026, '08-good-vibes', 'Good vibes', 'Three attendees posing and smiling at the commitment wall'),
      photo(EVENT_2026, '11-friends', 'Friends of the network', 'Three women smiling together, one in a yellow HR GEMs T-shirt'),
      photo(EVENT_2026, '13-appreciation', 'Appreciation', 'A guest receiving an HR GEMs gift bag on stage'),
      photo(EVENT_2026, '15-dancing-on-stage', 'Dancing on stage', 'Performers dancing on the Workplace Transformation stage'),
      photo(EVENT_2026, '17-celebration', "Let's celebrate", 'Attendees in yellow T-shirts cheering around a celebration cake'),
      photo(EVENT_2026, '18-team-hr-gems', 'Team HR GEMs', 'The HR GEMs team in yellow T-shirts posing on the green carpet'),
      photo(EVENT_2026, '02-yellow-team', 'The HR GEMs team', 'Three team members in yellow HR GEMs T-shirts at the speakers wall, one holding an "I am a coach" placard'),
      photo(EVENT_2026, '01-stage', 'The stage is set', 'The Workplace Transformation stage with HR GEM Coach Network screens before guests arrive'),
    ],
  },
  {
    slug: WORKERS_DAY_2025,
    title: 'HR GEMs Workers Day Event 2025 — Mindset Transformation',
    year: 2025,
    description: 'A packed Workers Day celebration of mindset, growth and community, with speakers, a panel and plenty of selfies.',
    photos: [
      photo(WORKERS_DAY_2025, '01-cake', 'Workers Day Event 2025', 'A celebration cake reading "HR GEMs Workers Day Event 2025"'),
      photo(WORKERS_DAY_2025, '03-coach-lara', 'Coach Lara Yeku', 'Coach Lara Yeku speaking into a microphone on stage'),
      photo(WORKERS_DAY_2025, '08-full-house', 'A full house', 'A packed hall of attendees listening to a speaker'),
      photo(WORKERS_DAY_2025, '16-panel', 'The panel', 'Five panellists on white chairs on the Mindset Transformation stage'),
      photo(WORKERS_DAY_2025, '02-welcome', 'Welcome!', 'The host welcoming guests at the podium in front of a "Welcome!" screen'),
      photo(WORKERS_DAY_2025, '15-speaker', 'Speaking with passion', 'A speaker gesturing as she talks at the podium'),
      photo(WORKERS_DAY_2025, '06-question', 'A question from the floor', 'An attendee asking a question into a microphone'),
      photo(WORKERS_DAY_2025, '17-in-discussion', 'In discussion', 'A panellist raising his hand as the panel discusses'),
      photo(WORKERS_DAY_2025, '09-sharing', 'Sharing her story', 'An attendee speaking into a microphone beside the host'),
      photo(WORKERS_DAY_2025, '10-appreciation', 'Appreciation', 'A guest speaker receiving a gift on stage'),
      photo(WORKERS_DAY_2025, '05-among-the-audience', 'Among the audience', 'A speaker walking among the seated audience'),
      photo(WORKERS_DAY_2025, '07-applause', 'Applause', 'Attendees smiling and clapping in their seats'),
      photo(WORKERS_DAY_2025, '11-selfie', 'Selfie time', 'Two smiling attendees taking a selfie'),
      photo(WORKERS_DAY_2025, '12-friends', 'Friends', 'Two women posing together for a selfie'),
      photo(WORKERS_DAY_2025, '13-warm-hugs', 'Warm hugs', 'Two attendees sharing a hug'),
      photo(WORKERS_DAY_2025, '14-sponsor-booth', 'At the sponsor booth', 'A group of attendees posing at a sponsor booth'),
      photo(WORKERS_DAY_2025, '18-our-speakers', 'Our speakers', 'The speakers lined up together on stage'),
      photo(WORKERS_DAY_2025, '04-hall', 'Mindset Transformation', 'The event hall with rows of white chairs along a green carpet'),
    ],
  },
  {
    slug: MAY_DAY_2024,
    title: 'HR GEMs May Day 2024 Hangout',
    year: 2024,
    description: 'Conversations, games, a panel discussion and plenty of connection at our May Day hangout.',
    photos: [
      photo(MAY_DAY_2024, '02-placards-group', 'Impact, together', 'Four members smiling in front of the HR GEM Coach Network backdrop, holding "I am a coach" and "Impact" placards'),
      photo(MAY_DAY_2024, '06-panel', 'The panel discussion', 'Five panellists seated on stools in front of a brick wall, sharing their experiences'),
      photo(MAY_DAY_2024, '07-audience', 'A full house', 'A large audience seated at tables under a canopy, listening to a speaker'),
      photo(MAY_DAY_2024, '14-speaker', 'Sharing the mic', 'A member speaking into a microphone under green and yellow balloons'),
      photo(MAY_DAY_2024, '08-networking', 'Real conversations', 'Two members deep in conversation at a table'),
      photo(MAY_DAY_2024, '09-i-am-a-coach', 'I am a coach', 'Two smiling members holding "Impact" and "I am a coach" placards at the HR GEM backdrop'),
      photo(MAY_DAY_2024, '03-dancing', 'Dancing on the lawn', 'Members dancing on the lawn outside the venue'),
      photo(MAY_DAY_2024, '13-under-the-balloons', 'Under the balloons', 'Three members posing together under green and yellow balloons'),
      photo(MAY_DAY_2024, '11-board-game', 'Game time', 'Members playing a traditional board game at a table'),
      photo(MAY_DAY_2024, '10-jenga', 'A steady hand', 'Members gathered around a game of Jenga'),
      photo(MAY_DAY_2024, '12-handshake', 'Warm welcomes', 'Two members shaking hands under the balloons'),
      photo(MAY_DAY_2024, '05-tables', 'Catching up', 'Members relaxing and chatting around tables'),
      photo(MAY_DAY_2024, '04-the-workplace-book', 'The Work Place', 'A copy of the book "The Work Place: Managing Your Mental Health at Work"'),
      photo(MAY_DAY_2024, '01-backdrop', 'HR GEM Coach Network', 'The HR GEM Coach Network event backdrop framed with balloons'),
    ],
  },
];

/** Looks a photo up by album slug and file name (e.g. '06-panel'). */
export function galleryPhoto(albumSlug: string, file: string): GalleryPhoto {
  const album = GALLERY_ALBUMS.find((a) => a.slug === albumSlug);
  const found = album?.photos.find((p) => p.src.endsWith(`/${file}.jpg`));

  if (!found) {
    throw new Error(`Gallery photo "${albumSlug}/${file}" not found`);
  }

  return found;
}
