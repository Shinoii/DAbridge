<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Streamer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function loginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $ok = hash_equals(config('admin.login'), (string) $request->login)
            && config('admin.password_hash')
            && Hash::check((string) $request->password, config('admin.password_hash'));

        if (! $ok) {
            return back()->withErrors(['login' => 'Неверный логин или пароль']);
        }

        $request->session()->regenerate();
        $request->session()->put('is_admin', true);

        return redirect()->route('admin.index');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();

        return redirect()->route('admin.login');
    }

    public function index(Request $request)
    {
        $streamers = Streamer::query()
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderByDesc('is_connected')->orderByDesc('last_donation_at')
            ->paginate(25)->withQueryString();

        return view('admin.index', [
            'streamers' => $streamers,
            'stats' => [
                'total'  => Streamer::count(),
                'online' => Streamer::where('is_connected', true)->count(),
            ],
        ]);
    }

    public function toggle(Streamer $streamer)
    {
        $streamer->update(['is_active' => ! $streamer->is_active]);

        return back();
    }

    public function regenerate(Streamer $streamer)
    {
        $streamer->update(['widget_token' => Str::random(40)]);

        return back()->with('ok', "Новый токен для {$streamer->name} создан");
    }

    public function destroy(Streamer $streamer)
    {
        $streamer->delete();

        return back();
    }
}
