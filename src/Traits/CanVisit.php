<?php

declare (strict_types=1);
namespace Shetabit\Visitor\Traits;

use Shetabit\Visitor\Models\Visit;
trait Can_Visit
{
    /**
     * Get all of the post's comments.
     * @return mixed
     */
    public function visit_logs()
    {
        return $this->morph_many(Visit::class, 'visitor');
    }
    /**
     * Retrieve online users
     * @param $query
     * @param int $seconds
     * @return mixed
     */
    public function scope_online($query, $seconds = 180)
    {
        $time = now()->sub_seconds($seconds);
        return $query->where_has('visitLogs', function ($query) use ($time): void {
            $query->where(config('visitor.table_name') . '.created_at', '>=', $time->to_date_time());
        });
    }
    /**
     * check if user is online
     * @param int $seconds
     */
    public function is_online($seconds = 180): bool
    {
        $time = now()->sub_seconds($seconds);
        return $this->visit_logs()->where_has_morph('user', [static::class], function ($query) use ($time): void {
            $query->where('user_id', $this->id)->where(config('visitor.table_name') . '.created_at', '>=', $time->to_date_time());
        })->count() > 0;
    }
}