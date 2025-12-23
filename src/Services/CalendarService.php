<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Calendar Service
 * 
 * Editorial calendar and conflict detection
 */
class CalendarService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get calendar view for month
     */
    public function getMonthView(int $channelId, int $year, int $month): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $scheduled = $this->db->fetchAll(
            "SELECT DATE(schedule_time) as date, COUNT(*) as count
             FROM scheduled
             WHERE channel_id = ? 
             AND schedule_time >= ? 
             AND schedule_time <= ?
             AND status = 'pending'
             GROUP BY DATE(schedule_time)",
            [$channelId, $startDate, $endDate . ' 23:59:59']
        );

        $calendar = [];
        foreach ($scheduled as $row) {
            $calendar[$row['date']] = (int)$row['count'];
        }

        return $calendar;
    }

    /**
     * Get day's  scheduled posts
     */
    public function getDayPosts(int $channelId, string $date): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM scheduled
             WHERE channel_id = ? 
             AND DATE(schedule_time) = ?
             AND status = 'pending'
             ORDER BY schedule_time",
            [$channelId, $date]
        );
    }

    /**
     * Detect scheduling conflicts
     */
    public function detectConflicts(int $channelId, int $scheduleTime, int $thresholdMinutes = 15): array
    {
        $startTime = $scheduleTime - ($thresholdMinutes * 60);
        $endTime = $scheduleTime + ($thresholdMinutes * 60);

        return $this->db->fetchAll(
            "SELECT * FROM scheduled
             WHERE channel_id = ?
             AND status = 'pending'
             AND UNIX_TIMESTAMP(schedule_time) BETWEEN ? AND ?",
            [$channelId, $startTime, $endTime]
        );
    }

    /**
     * Get campaign calendar
     */
    public function getCampaignCalendar(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT c.*, COUNT(s.id) as scheduled_count
             FROM campaigns c
             LEFT JOIN scheduled s ON c.id = s.campaign_id AND s.status = 'pending'
             WHERE c.user_id = ? AND c.status = 'active'
             GROUP BY c.id
             ORDER BY c.start_date",
            [$userId]
        );
    }

    /**
     * Get posting frequency
     */
    public function getPostingFrequency(int $channelId, int $days = 30): array
    {
        return $this->db->fetchAll(
            "SELECT DATE(posted_at) as date, COUNT(*) as count
             FROM posts
             WHERE channel_id = ?
             AND posted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             AND is_deleted = 0
             GROUP BY DATE(posted_at)
             ORDER BY date DESC",
            [$channelId, $days]
        );
    }
}
