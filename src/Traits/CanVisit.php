<?php

declare(strict_types=1);

namespace Shetabit\Visitor\Traits;

use Shetabit\Visitor\Models\Visit;

trait CanVisit
{
    /**
     * Get all of the post's comments.
     * @return mixed
     */
    public function visitLogs()
    {
        return $this->morphMany(Visit::class, 'visitor');
    }

    /**
     * Retrieve online users
     * @param $query
     * @param int $seconds
     * @return mixed
     */
    public function scopeOnline($query, $seconds = 180)
    {
        $time = now()->subSeconds($seconds);

        return $query->whereHas('visitLogs', function ($query) use ($time): void {
            $query->where(config('visitor.table_name') . '.created_at', '>=', $time->toDateTime());
        });
    }

    /**
     * check if user is online
     * @param int $seconds
     */
    public function isOnline($seconds = 180): bool
    {
        $time = now()->subSeconds($seconds);

        return $this->visitLogs()->whereHasMorph('user', [static::class], function ($query) use ($time): void {
            $query
                ->where('user_id', $this->id)
                ->where(config('visitor.table_name') . '.created_at', '>=', $time->toDateTime());
        })->count() > 0;
    }
}
