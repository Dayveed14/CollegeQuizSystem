<?php
/**
 * Quiz attempt helpers. Every attempt belongs to one student, so two
 * students taking the same quiz at once can never overwrite each other.
 */

const QUIZ_GRACE_SECONDS = 90;   // allowance for slow connections after the timer hits zero

/** Save answers (question_id => 'A'..'D') into an open attempt. */
function save_answers(int $attemptId, array $answers): void
{
    $stmt = db()->prepare('UPDATE attempt_answers SET chosen_option = ? WHERE attempt_id = ? AND question_id = ?');
    foreach ($answers as $qid => $choice) {
        $choice = strtoupper((string) $choice);
        if (ctype_digit((string) $qid) && in_array($choice, ['A', 'B', 'C', 'D'], true)) {
            $stmt->execute([$choice, $attemptId, (int) $qid]);
        }
    }
}

/**
 * Grade and close an attempt. Returns false if it was already closed.
 * $answers === null means "use whatever was autosaved".
 */
function finalize_attempt(int $attemptId, ?array $answers, bool $late): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $open = q('SELECT id FROM attempts WHERE id = ? AND submitted_at IS NULL FOR UPDATE', [$attemptId])->fetch();
        if (!$open) {
            $pdo->rollBack();
            return false;
        }
        if ($answers !== null) save_answers($attemptId, $answers);

        q('UPDATE attempt_answers aa JOIN questions qq ON qq.id = aa.question_id
           SET aa.is_correct = (aa.chosen_option IS NOT NULL AND aa.chosen_option = qq.correct_option)
           WHERE aa.attempt_id = ?', [$attemptId]);

        $t = q('SELECT COUNT(*) AS total, COALESCE(SUM(is_correct), 0) AS score FROM attempt_answers WHERE attempt_id = ?', [$attemptId])->fetch();
        q('UPDATE attempts SET score = ?, total = ?, submitted_at = ?, late = ? WHERE id = ?',
          [(int) $t['score'], (int) $t['total'], date('Y-m-d H:i:s'), $late ? 1 : 0, $attemptId]);
        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

/** Close any of this student's attempts whose time ran out without a submission. */
function close_expired_attempts(int $userId): void
{
    $cutoff = date('Y-m-d H:i:s', time() - QUIZ_GRACE_SECONDS);
    $rows = q('SELECT id FROM attempts WHERE user_id = ? AND submitted_at IS NULL AND deadline_at < ?', [$userId, $cutoff])->fetchAll();
    foreach ($rows as $r) finalize_attempt((int) $r['id'], null, true);
}
