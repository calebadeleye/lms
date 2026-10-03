<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Google Drive embeds were too slow to start, so the "Find Your Career Fit"
 * videos are now hosted by the app itself: re-encoded to 720p H.264 (the
 * originals were 1080p HEVC, which many browsers can't play) and served from
 * the frontend's public/videos/ folder under unguessable filenames. Those
 * files are NOT in git (see the frontend .gitignore) — they were uploaded to
 * the server directly.
 *
 * duration_seconds is set together with the path on purpose: for a directly
 * hosted video, ProgressService only completes a lesson once the learner has
 * watched 90% of duration_seconds, and refuses manual completion — so a
 * lesson with no duration could never be completed (and the course never
 * certified). Drive embeds don't use it, which is why it was null before.
 *
 * Lessons are matched by their Drive file id, so a lesson already switched
 * (or any other course) is left alone. down() puts the Drive links back.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: int}> Drive file id => [new path, duration in seconds] */
    private const VIDEOS = [
        '11Zn7fjF62e7z43blzm4XmS2ZFtNPBeC9' => ['/videos/bbcb62c54d5f7920014c8a02.mp4', 272], // Welcome-Address
        '1cgEmApkEDgYlotCzYRvQYMuTJ48s7BcG' => ['/videos/0c5308ae311ce1a789216c43.mp4', 1579], // Module-1
        '1ehe9NehYrUZRorXKaJy8xg0hd_7qwR62' => ['/videos/2a4cbf81589d2d744916e09e.mp4', 520], // Module-2
        '1B0XHj-NMrTZCRMoJoWg84Py5Iu4oIJt_' => ['/videos/9ec1bc0ca3d25e242fef6616.mp4', 48], // Module-3
        '14DKTo6u9O9XiBIiEl_ECwEjOy-YAyxa5' => ['/videos/cfc9a8b4bb0ec68fa2982ae5.mp4', 760], // Module-4
        '1VjI2k21tw0yvQonxYexKgbzvuQIEYF17' => ['/videos/ae597aaeedb3d3cd3dc10fb4.mp4', 99], // ENFJ
        '13XSIn2EbaxNTmPmlCPZ8dsGcIdPeH9SG' => ['/videos/5f065353d7eeea8d67dad3e0.mp4', 105], // ENFP
        '1RQXx3Tlyng9lEhhEIA4HDtfMKUKcXu_u' => ['/videos/9cb1df517e4bfd1fcf392ec6.mp4', 73], // ENTJ
        '1n8H450BoKRjwhwotDrw9--haWsXpzhka' => ['/videos/b45ce50ec506f90277e0ca79.mp4', 92], // ENTP
        '1kSDXTZMqqyqYCOBi-y3I0cM6NNkzpe89' => ['/videos/90050638241abef5eb7f9edc.mp4', 112], // ESFJ
        '15E0bxTl4l8d_G6TUd9Mpxrdj9h2JlD6H' => ['/videos/f68f737d33c175189814176c.mp4', 115], // ESFP
        '1vEjz-XIHqXtvhREWhZuMJyZui6kBqiaf' => ['/videos/255aba1e62d890e58c01e9c4.mp4', 112], // ESTJ
        '1All0DyaLJyU1zUnlhS6ef8mlfi-1pJOF' => ['/videos/cb4da51216c88c33594b54e1.mp4', 100], // ESTP
        '1xS9G0XWjbTYbDkKJ7Me4Y9ZsWnumktLd' => ['/videos/830fa6905924d7578dd8cded.mp4', 96], // INFJ
        '1bpSXk2PJ88zqJbENUYyutxl8rAJUdzbP' => ['/videos/36760e2dd7c1abc9c8b12c23.mp4', 132], // INFP
        '1S94ruE9PxZnZAM6AKTwRb4Drc2ZYOInv' => ['/videos/902124e9d721ebf9c35b0a0a.mp4', 127], // INTJ
        '1Jz9sX5UTYJjxegBw1F3aQCIafltbAtUx' => ['/videos/5ce394f95d098d9efd7c777f.mp4', 99], // INTP
        '1LVNEAIKzYkPMC3LKMDKHMJga_eJ6gSne' => ['/videos/9bb00ddc0914622efa639435.mp4', 101], // ISFJ
        '1kqpC4hHsiuGigCxmWfSM9pBvNs6wPM1J' => ['/videos/991c3e2b7e1b0a84b5583598.mp4', 80], // ISFP
        '1P8xFyoICmlz3R4QeTBO7RwhjlxJbt6ut' => ['/videos/c63b9185c7c31bd7c21d2035.mp4', 122], // ISTJ
        '1A4MB42CluROYTnFaoKnT3LSHg0_tPiWu' => ['/videos/5efa2b780412970aade0c012.mp4', 103], // ISTP
    ];

    public function up(): void
    {
        $moduleIds = $this->moduleIds();

        DB::transaction(function () use ($moduleIds) {
            foreach (self::VIDEOS as $driveId => [$path, $duration]) {
                DB::table('lessons')
                    ->whereIn('course_module_id', $moduleIds)
                    ->where('video_path', 'like', '%drive.google.com/file/d/'.$driveId.'%')
                    ->update(['video_path' => $path, 'duration_seconds' => $duration, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        $moduleIds = $this->moduleIds();

        DB::transaction(function () use ($moduleIds) {
            foreach (self::VIDEOS as $driveId => [$path]) {
                DB::table('lessons')
                    ->whereIn('course_module_id', $moduleIds)
                    ->where('video_path', $path)
                    ->update([
                        'video_path' => 'https://drive.google.com/file/d/'.$driveId.'/view',
                        'duration_seconds' => null,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    /** @return array<int, int> */
    private function moduleIds(): array
    {
        $courseId = DB::table('courses')->where('slug', 'find-your-career-fit')->value('id');

        return $courseId ? DB::table('course_modules')->where('course_id', $courseId)->pluck('id')->all() : [];
    }
};
