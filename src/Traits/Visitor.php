<?php

declare(strict_types=1);

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
        return $this->morphMany(Visit::class, 'visitor');
    }

    /**
     * Create a visit log.
     * @return mixed
     */
    public function visit(?Model $visitable = null)
    {
        return app('shetabit-visitor')->setVisitor($this)->visit($visitable);
    }

    /**
     * Retrieve online users
     * @param $query
     * @param int $seconds
     */
    public function scopeOnline($query, $seconds = 180): void
    {
        $time = now()->subSeconds($seconds);

        $query->whereHas('visits', function ($query) use ($time): void {
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

        return $this->visits()->whereHasMorph('visitor', [static::class], function ($query) use ($time): void {
            $query
                ->where('visitor_id', $this->id)
                ->where(config('visitor.table_name') . '.created_at', '>=', $time->toDateTime());
        })->count() > 0;
    }
}
