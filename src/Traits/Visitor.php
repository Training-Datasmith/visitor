<?php

declare (strict_types=1);
namespace Shetabit\Visitor\Traits;

use Shetabit\Visitor\Models\Visit;
trait Visitor
{
    /**
     * Get all of the post's comments.
     * @return mixed
     */
    public function visits()
    {
        return $this->morph_many(Visit::class, 'visitor');
    }
    /**
     * Create a visit log.
     * @return mixed
     */
    public function visit(?Model $visitable = null)
    {
        return app('shetabit-visitor')->set_visitor($this)->visit($visitable);
    }
    /**
     * Retrieve online users
     * @param $query
     * @param int $seconds
     */
    public function scope_online($query, $seconds = 180): void
    {
        $time = now()->sub_seconds($seconds);
        $query->where_has('visits', function ($query) use ($time): void {
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
        return $this->visits()->where_has_morph('visitor', [static::class], function ($query) use ($time): void {
            $query->where('visitor_id', $this->id)->where(config('visitor.table_name') . '.created_at', '>=', $time->to_date_time());
        })->count() > 0;
    }
}