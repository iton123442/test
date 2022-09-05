<?php

namespace App\Http\Middleware;

use App\Traits\TGAuthTrait;
use Closure;

class TGAuth
{
    use TGAuthTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $service=null)
    {

        if($service == 'issue_access_token'){
            return $this->issueAccessToken($request);
        }else{
            if(!$this->verifyAccessToken($request->header('Authorization'))){
                return ['error' => "access_denied", "error_description" => "The resource owner or authorization server denied the request."];
            };
        }

        // Pre-Middleware Action
        $response = $next($request);

        // Post-Middleware Action
        return $response;
    }
}
