<?php

declare (strict_types=1);
namespace Shetabit\Visitor\Middlewares;

use Closure;
use Illuminate\Database\Eloquent\Model;
class Log_Visits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $log_has_saved = false;
        // create log for first binded model
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof Model) {
                visitor()->visit($parameter);
                $log_has_saved = true;
                break;
            }
        }
        // create log for normal visits
        if (!$log_has_saved) {
            visitor()->visit();
        }
        return $next($request);
    }
}