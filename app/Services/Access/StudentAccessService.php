<?php
declare(strict_types=1);

namespace App\Services\Access;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PasswordResetToken;
use App\Models\Payment;
use App\Models\ProductCourse;
use App\Models\Student;
use App\Models\User;
use App\Services\Mail\MailService;
use App\Support\Logger;

/**
 * Grants course access once a payment is confirmed.
 *
 * Idempotency is enforced in three layers:
 *  1. payment_webhooks already blocks reprocessing of the SAME event;
 *  2. payments.access_granted_at short-circuits redeliveries (a "paid" event
 *     arriving under a different event id);
 *  3. enrollments has a UNIQUE (user_id, course_id) key, so an enrollment can
 *     never be duplicated.
 */
final class StudentAccessService
{
    private MailService $mail;

    public function __construct(private array $config)
    {
        $this->mail = new MailService($config);
    }

    /**
     * Grants access from a PAID payment.
     * @param array $payment a row of the payments table
     * @return bool true when access was granted by this call; false when it had
     *              already been granted or no course is linked to the product
     */
    public function grant(array $payment): bool
    {
        $paymentId = (int) $payment['id'];

        // Layer 2: already granted? do nothing (and do not resend the e-mail).
        if (!empty($payment['access_granted_at'])) {
            return false;
        }

        $courses = ProductCourse::coursesForProduct((int) $payment['product_id']);
        if ($courses === []) {
            // Product with no course linked: there is nothing to unlock (e.g. a
            // payment-only product). access_granted_at is deliberately left
            // untouched so an admin can still grant access manually later.
            Logger::log('access', 'Product has no course linked; nothing to unlock', [
                'internal_id' => $payment['internal_id'] ?? null,
                'product_id'  => $payment['product_id'] ?? null,
            ]);
            return false;
        }

        $email = strtolower(trim((string) $payment['customer_email']));
        $name  = (string) $payment['customer_name'];

        // User: look it up by e-mail, or create one (a student with no password yet).
        $user = User::findByEmail($email);
        if ($user === null) {
            $userId = User::create($name, $email, 'student');
            $user = User::findById($userId);
        }
        $userId = (int) $user['id'];
        $needsPassword = empty($user['password_hash']);

        Student::upsert($userId, $payment['customer_phone'] ?? null, $payment['customer_doc'] ?? null);

        // Enroll the user in every course attached to the product (idempotent).
        foreach ($courses as $course) {
            Enrollment::grant($userId, (int) $course['id'], $paymentId);
        }

        // Layer 2: flag access as granted BEFORE sending the e-mail, so a mail failure does not undo the access.
        Payment::markAccessGranted($paymentId);

        // Access e-mail (carrying the create-password link on first access).
        $token = '';
        if ($needsPassword) {
            $token = PasswordResetToken::issue($userId, 24);
        }
        $courseTitle = count($courses) === 1 ? (string) $courses[0]['title'] : 'seus cursos';
        $this->mail->sendAccessGranted($email, $name, $courseTitle, $needsPassword, $token);

        Logger::log('access', 'Access granted', [
            'internal_id'   => $payment['internal_id'] ?? null,
            'user_id'       => $userId,
            'courses'       => count($courses),
            'needsPassword' => $needsPassword,
        ]);

        return true;
    }
}
