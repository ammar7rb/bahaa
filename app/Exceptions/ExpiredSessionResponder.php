<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Admin, vendor and customer share one session cookie on this domain, so a
 * login in one tab regenerates the CSRF token of every other open tab. The
 * next submit there used to end on a bare "419 / page expired" screen (which
 * users reported as "404 on login"). This sends the user back to the form
 * they were using with a clear message and a fresh token instead.
 */
class ExpiredSessionResponder
{
    private const FORM_MESSAGE = 'انتهت صلاحية الصفحة لأن الجلسة تغيّرت (غالبًا بسبب تسجيل دخول في تبويب آخر). أعد المحاولة الآن.';
    private const AJAX_MESSAGE = 'انتهت صلاحية الصفحة لأن الجلسة تغيّرت (غالبًا بسبب تسجيل دخول في تبويب آخر). حدّث الصفحة ثم أعد المحاولة.';

    public function __invoke(HttpExceptionInterface $e, Request $request): ?Response
    {
        if ($e->getStatusCode() !== 419) {
            return null;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'session_expired',
                'message' => self::AJAX_MESSAGE,
                'error' => self::AJAX_MESSAGE,
                'errors' => [['code' => 'session_expired', 'message' => self::AJAX_MESSAGE]],
            ], 419);
        }

        return redirect()->to($this->targetFor($request))
            ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password', 'confirm_password']))
            ->withErrors(['session_expired' => self::FORM_MESSAGE]);
    }

    private function targetFor(Request $request): string
    {
        $path = '/' . ltrim($request->path(), '/');

        return match (true) {
            $path === '/login' => url('/login/' . ($request->input('role') === 'employee' ? 'employee' : 'admin')),
            str_starts_with($path, '/vendor/auth/login') => url('/vendor/auth/login'),
            str_starts_with($path, '/customer/auth/login') => url('/customer/auth/login'),
            default => url()->previous(url('/')),
        };
    }
}
