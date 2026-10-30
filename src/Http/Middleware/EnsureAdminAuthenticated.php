<?php

namespace NepalPay\Gateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('nepal_payment_admin_authenticated')) {
            return redirect()->route('nepal-payment.admin.login');
        }

        return $next($request);
    }
}