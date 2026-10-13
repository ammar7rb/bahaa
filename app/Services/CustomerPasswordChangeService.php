<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerPasswordChangeService
{
    /** Call with a locked customer inside the profile update transaction. */
    public function password(User $customer, Request $request): string
    {
        if (!$request->filled('password')) return (string)$customer->password;
        if (!Hash::check((string)$request->input('current_password'),(string)$customer->password))
            throw ValidationException::withMessages(['current_password'=>'كلمة المرور الحالية غير صحيحة. أدخل كلمة المرور الحالية أو التي أعطتها لك الإدارة.']);
        $request->validate(['password'=>'required|string|min:8|max:255','password_confirmation'=>'required|string|same:password']);
        return Hash::make($request->input('password'));
    }
}
