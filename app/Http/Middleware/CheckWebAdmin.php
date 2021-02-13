<?php

namespace App\Http\Middleware;

use Closure;

class CheckWebAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if($request->session()->get('is_login') == 1){
            return $next($request);
        } else {
            return redirect('/a@dmin');
        }
    }
}
