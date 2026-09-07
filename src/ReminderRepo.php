<?php

namespace DepositFinance;

class ReminderRepo
{
    public static function alreadySent(int $instrumentId, int $thresholdDays): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM reminders_sent WHERE instrument_id = ? AND threshold_days = ?'
        );
        $stmt->execute([$instrumentId, $thresholdDays]);
        return (bool) $stmt->fetchColumn();
    }

    public static function markSent(int $instrumentId, int $thresholdDays): void
    {
        try {
            Database::connection()
                ->prepare('INSERT INTO reminders_sent (instrument_id, threshold_days) VALUES (?, ?)')
                ->execute([$instrumentId, $thresholdDays]);
        } catch (\PDOException $e) {
            // Unique key (instrument_id, threshold_days) already exists — already recorded, nothing to do.
        }
    }
}
