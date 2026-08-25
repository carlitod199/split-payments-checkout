<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class LessonProgress
{
    /** Map of lesson_id => progress row, for one student in one course. */
    public static function mapForUserCourse(int $userId, int $courseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM lesson_progress WHERE user_id = :uid AND course_id = :cid'
        );
        $stmt->execute([':uid' => $userId, ':cid' => $courseId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['lesson_id']] = $row;
        }
        return $map;
    }

    /**
     * Saves the progress (idempotent through the UNIQUE user+lesson key).
     * When $completed is true it stores completed_at and forces the watched
     * seconds up to the full lesson duration.
     */
    public static function save(int $userId, int $lessonId, int $courseId, int $watchedSeconds, bool $completed): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO lesson_progress
                (user_id, lesson_id, course_id, watched_seconds, completed, completed_at, last_watched_at)
             VALUES
                (:uid, :lid, :cid, :ws, :done, :cat, NOW())
             ON DUPLICATE KEY UPDATE
                watched_seconds = VALUES(watched_seconds),
                completed       = VALUES(completed),
                completed_at    = VALUES(completed_at),
                last_watched_at = NOW(),
                updated_at      = NOW()'
        );
        $stmt->execute([
            ':uid'  => $userId,
            ':lid'  => $lessonId,
            ':cid'  => $courseId,
            ':ws'   => max(0, $watchedSeconds),
            ':done' => $completed ? 1 : 0,
            ':cat'  => $completed ? date('Y-m-d H:i:s') : null,
        ]);
    }
}
