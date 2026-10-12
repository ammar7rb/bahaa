<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Utils\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ProfileImageController extends Controller
{
    public function update(Request $request) {
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=4096,max_height=4096']);
        $id = $request->is('api/*') ? $request->user('api')->id : auth('customer')->id();
        $name = (string) \Illuminate\Support\Str::uuid().'.webp';
        $image = \Intervention\Image\Facades\Image::make($request->file('image'))->orientate()->resize(1600,1600, function ($constraint) { $constraint->aspectRatio(); $constraint->upsize(); })->encode('webp',85);
        $disk = \Illuminate\Support\Facades\Storage::disk(config('filesystems.disks.default') ?? 'public');
        if (!$disk->put('profile/'.$name,(string)$image)) throw \Illuminate\Validation\ValidationException::withMessages(['image'=>'تعذر حفظ الصورة. أعد المحاولة.']);
        try {
            $old = DB::transaction(function () use ($id, $name) {
                $user = User::lockForUpdate()->findOrFail($id); $old = $user->image;
                $user->image = $name; $user->save(); return $old;
            });
        } catch (\Throwable $e) { ImageManager::delete('profile/'.$name); throw $e; }
        if ($old) ImageManager::delete('profile/'.$old);
        return response()->json(['image_full_url' => User::findOrFail($id)->image_full_url]);
    }
}
