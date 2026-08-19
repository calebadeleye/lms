/**
 * HR GEMs event galleries, sourced from the client's shared Google Drive
 * folders. Each event's title is the Drive folder's own name; `images` are
 * two representative photos pulled from that folder (rendered via Drive's
 * thumbnail endpoint), and `driveUrl` is the "See more" link to the full
 * folder. Static, like `home-content.ts` — edit and redeploy when a new
 * year's folder is shared, not database-backed.
 */

export interface EventEntry {
  title: string;
  year: number;
  /** Google Drive file IDs for the two representative photos. */
  imageIds: string[];
  driveUrl: string;
}

function driveThumbnail(fileId: string): string {
  return `https://drive.google.com/thumbnail?id=${fileId}&sz=w1000`;
}

export const EVENTS: EventEntry[] = [
  {
    title: 'HR GEMS 2026 EVENT',
    year: 2026,
    imageIds: ['1jl6ak8aRxj72VHtWiQ9ARqFVxNdgkxnS', '1j4bBUunwXIRtLHyveZpNJcoLEVsJcg_A'],
    driveUrl: 'https://drive.google.com/drive/folders/17bO2yOS4GEgd9Sa9b1-CDctgUimKnPdE?usp=sharing',
  },
  {
    title: 'HR GEMS MAY 2025 EVENT VIDEOS',
    year: 2025,
    imageIds: ['1wbJIxyWAYh_E6Ef5z0XtYA2VczlXMRW0', '1lY9k2Z_UbHgiAhMvKkPdKzX2Bzn2q65M'],
    driveUrl: 'https://drive.google.com/drive/folders/1DH-SIhOeLx_m1vVsQOdC6IwEV01QViXV?usp=sharing',
  },
  {
    title: 'HR GEMS MAY DAY 2024 HANGOUT',
    year: 2024,
    // This folder only contains one file (a video), so only one thumbnail
    // is available — unlike the other two years, which have photo folders.
    imageIds: ['1Yr0i0KSKVGUskNb5F37Zc_FwaZ59_4_s'],
    driveUrl: 'https://drive.google.com/drive/folders/1FoedXi6SkB3-SW1c3mm0eD-dKWvNM6PC?usp=sharing',
  },
].sort((a, b) => b.year - a.year);

export function eventImageUrls(event: EventEntry): string[] {
  return event.imageIds.map(driveThumbnail);
}
