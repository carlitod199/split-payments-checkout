<?php
declare(strict_types=1);

namespace App\Services\Student;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Producer;
use App\Models\Student;
use App\Models\User;

/**
 * Assembles the real data for the student area (student / course / modules /
 * lessons / continue), in the shape the views consume.
 *
 * Returns null when the user has no active enrollment, in which case the area
 * renders its "no courses" state.
 */
final class StudentDashboardService
{
    /**
     * @return array{student:array,course:array,modules:array,lessons:array,continue:array}|null
     */
    public static function forUser(int $userId, ?string $courseSlug = null): ?array
    {
        $enrollments = Enrollment::activeForUser($userId);
        if ($enrollments === []) {
            return null;
        }

        // The requested course (?slug=) provided the user is enrolled in it; otherwise the first active one.
        $courseId = (int) $enrollments[0]['course_id'];
        if ($courseSlug !== null && $courseSlug !== '') {
            foreach ($enrollments as $e) {
                if (($e['slug'] ?? '') === $courseSlug) {
                    $courseId = (int) $e['course_id'];
                    break;
                }
            }
        }

        $courseRow = Course::find($courseId);
        if ($courseRow === null) {
            return null;
        }

        $producer = Producer::find((int) $courseRow['producer_id']);
        $modulesRows = CourseModule::forCourse($courseId);
        $lessonsRows = Lesson::forCourse($courseId);
        $progress = LessonProgress::mapForUserCourse($userId, $courseId);

        // module_id => n (display ordinal)
        $modules = [];
        $moduleN = [];
        foreach ($modulesRows as $i => $m) {
            $n = $i + 1;
            $moduleN[(int) $m['id']] = ['n' => $n, 'title' => $m['title']];
            $modules[] = [
                'id'          => (int) $m['id'],
                'n'           => $n,
                'title'       => (string) $m['title'],
                'description' => (string) ($m['description'] ?? ''),
            ];
        }

        // lessons in the shape the views expect: a global sequential n, watched seconds from the progress rows.
        $lessons = [];
        foreach ($lessonsRows as $i => $l) {
            $lid = (int) $l['id'];
            $duration = (int) $l['duration_seconds'];
            $prow = $progress[$lid] ?? null;
            $completed = $prow !== null && (int) $prow['completed'] === 1;
            $watched = $completed ? $duration : (int) ($prow['watched_seconds'] ?? 0);

            $lessons[] = [
                'id'               => $lid,
                'module_id'        => (int) $l['module_id'],
                'n'                => $i + 1,
                'title'            => (string) $l['title'],
                'description'      => (string) ($l['description'] ?? ''),
                'duration_seconds' => $duration,
                'watched_seconds'  => $watched,
                'video_url'        => (string) ($l['video_url'] ?? ''),
                'video_provider'   => (string) ($l['video_provider'] ?? 'other'),
                'module'           => (string) ($l['module_title'] ?? ''),
            ];
        }

        // student (identity)
        $userRow = User::findById($userId) ?? [];
        $studentRow = Student::findByUser($userId) ?? [];
        $student = [
            'name'  => (string) ($userRow['name'] ?? ''),
            'email' => (string) ($userRow['email'] ?? ''),
            'phone' => (string) ($studentRow['phone'] ?? ''),
        ];

        // course plus derived metrics
        $total = count($lessons);
        $done = count(array_filter($lessons, fn ($l) => $l['watched_seconds'] >= $l['duration_seconds'] && $l['duration_seconds'] > 0));
        $course = [
            'id'            => $courseId,
            'title'         => (string) $courseRow['title'],
            'slug'          => (string) $courseRow['slug'],
            'description'   => (string) ($courseRow['description'] ?? ''),
            'producer'      => (string) ($producer['name'] ?? ''),
            'cover_image'   => (string) ($courseRow['cover_image'] ?? ''),
            'banner_image'  => (string) ($courseRow['banner_image'] ?? ''),
            'lessons_total' => $total,
            'lessons_done'  => $done,
            'progress'      => $total ? (int) round($done / $total * 100) : 0,
        ];

        // "pick up where you left off": the first unfinished lesson, otherwise the first one.
        $continue = null;
        foreach ($lessons as $l) {
            if ($l['watched_seconds'] < $l['duration_seconds']) {
                $continue = $l;
                break;
            }
        }
        $continue ??= ($lessons[0] ?? [
            'id' => 0, 'module_id' => 0, 'n' => 0, 'title' => '—', 'description' => '',
            'duration_seconds' => 0, 'watched_seconds' => 0, 'video_url' => '', 'video_provider' => 'other', 'module' => '',
        ]);

        return compact('student', 'course', 'modules', 'lessons', 'continue');
    }
}
