<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                $user = auth()->user();
                if (!$user || !$user->isAdmin()) {
                    abort(403, 'Akses ditolak. Modul ini hanya untuk Administrator.');
                }
                return $next($request);
            }, except: ['stopImpersonating']),
        ];
    }

    /**
     * Tampilkan daftar seluruh pengguna dengan filter cerdas & metrik.
     */
    public function index(Request $request): View
    {
        $query = User::query();

        // 1. Filter Search
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('telegram_username', 'like', "%{$search}%");

                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search)
                        ->orWhere('telegram_id', (int) $search);
                }
            });
        }

        // 2. Filter Role
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // 3. Filter Status
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_suspended', false);
            } elseif ($status === 'suspended') {
                $query->where('is_suspended', true);
            }
        }

        // Metrik Ringkasan Pengguna
        $totalUsers = User::count();
        $activeUsers = User::where('is_suspended', false)->count();
        $suspendedUsers = User::where('is_suspended', true)->count();
        $totalCirculatingBalance = (float) User::sum('wallet_balance');

        $stats = [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'suspended_users' => $suspendedUsers,
            'total_wallet_balance' => $totalCirculatingBalance,
        ];

        $users = $query->latest('id')->paginate(15)->withQueryString();

        return view('admin.users.index', compact(
            'users',
            'stats',
            'totalUsers',
            'activeUsers',
            'suspendedUsers',
            'totalCirculatingBalance'
        ));
    }

    /**
     * Daftarkan user baru secara langsung oleh superadmin.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:60', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'in:superadmin,admin,user'],
            'wallet_balance' => ['nullable', 'numeric', 'min:0'],
            'referral_balance' => ['nullable', 'numeric', 'min:0'],
            'is_suspended' => ['nullable', 'boolean'],
        ]);

        User::create([
            'name' => $validated['name'],
            'username' => $validated['username'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'wallet_balance' => $validated['wallet_balance'] ?? 0.00,
            'referral_balance' => $validated['referral_balance'] ?? 0.00,
            'is_suspended' => (bool) ($request->input('is_suspended', 0)),
            'last_active_at' => now(),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', __('users.user_created_success'));
    }

    /**
     * Detail user untuk modal popup interaktif (dukungan AJAX JSON).
     */
    public function show(User $user, Request $request): JsonResponse|View
    {
        $withdrawals = $user->withdrawals()->take(10)->get();
        $campaigns = $user->campaigns()->latest()->take(10)->get();

        // Data Grafik Broadcast: Total pesan Terkirim vs Gagal per kampanye terbaru
        $chartData = [
            'labels' => [],
            'sent' => [],
            'failed' => [],
        ];

        $chartCampaigns = $user->campaigns()->latest()->take(7)->get()->reverse();
        foreach ($chartCampaigns as $c) {
            $label = mb_strimwidth($c->title ?? 'Campaign #'.$c->id, 0, 14, '...');
            $chartData['labels'][] = $label;
            $chartData['sent'][] = (int) ($c->sent_count ?? 0);
            $chartData['failed'][] = (int) ($c->failed_count ?? 0);
        }

        // Jika kampanye masih kosong, sediakan placeholder 0 agar chart tetap render rapi
        if (empty($chartData['labels'])) {
            $chartData['labels'] = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
            $chartData['sent'] = [0, 0, 0, 0, 0, 0, 0];
            $chartData['failed'] = [0, 0, 0, 0, 0, 0, 0];
        }

        if ($request->wantsJson() || $request->ajax()) {
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username ? $user->username : '',
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'wallet_balance' => (float) $user->wallet_balance,
                'wallet_balance_formatted' => 'Rp ' . number_format((float) $user->wallet_balance, 0, ',', '.'),
                'referral_balance' => (float) $user->referral_balance,
                'referral_balance_formatted' => 'Rp ' . number_format((float) $user->referral_balance, 0, ',', '.'),
                'total_earnings' => (float) $user->total_earnings,
                'total_earnings_formatted' => 'Rp ' . number_format((float) $user->total_earnings, 0, ',', '.'),
                'max_daily_earnings' => (float) $user->max_daily_earnings,
                'max_daily_earnings_formatted' => 'Rp ' . number_format((float) $user->max_daily_earnings, 0, ',', '.'),
                'is_suspended' => $user->is_suspended,
                'suspended_reason' => $user->suspended_reason ?? '-',
                'joined_date' => $user->created_at ? $user->created_at->format('d M Y') : '-',
                'created_at_formatted' => $user->created_at ? $user->created_at->format('d M Y, H:i') : '-',
                'time_joined' => $user->time_joined,
                'last_active_formatted' => $user->last_active_at ? $user->last_active_at->diffForHumans() : __('users.never_active'),
            ];

            return response()->json([
                'success' => true,
                'user' => $userData,
                'data' => $userData,
                'chart' => $chartData,
                'withdrawals' => $withdrawals,
                'campaigns' => $campaigns,
            ]);
        }

        return view('admin.users.show', compact('user', 'withdrawals', 'campaigns', 'chartData'));
    }

    /**
     * Perbarui data pengguna & saldo.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:60', 'alpha_dash', 'unique:users,username,'.$user->id],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'string', 'in:superadmin,admin,user'],
            'wallet_balance' => ['nullable', 'numeric', 'min:0'],
            'referral_balance' => ['nullable', 'numeric', 'min:0'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'username' => $validated['username'] ?? null,
            'email' => $validated['email'],
            'role' => $validated['role'],
            'wallet_balance' => $validated['wallet_balance'] ?? $user->wallet_balance,
            'referral_balance' => $validated['referral_balance'] ?? $user->referral_balance,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return redirect()->route('admin.users.index')
            ->with('success', __('users.user_updated_success'));
    }

    /**
     * Batasi akun pengguna (Suspend).
     */
    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', __('users.cannot_modify_self'));
        }

        $user->update([
            'is_suspended' => true,
            'suspended_reason' => $request->input('reason', 'Pelanggaran syarat & ketentuan layanan'),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', __('users.user_suspended_success'));
    }

    /**
     * Cabut pembatasan akun (Unsuspend).
     */
    public function unsuspend(User $user): RedirectResponse
    {
        $user->update([
            'is_suspended' => false,
            'suspended_reason' => null,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', __('users.user_unsuspended_success'));
    }

    /**
     * Hapus permanen pengguna.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', __('users.cannot_modify_self'));
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', __('users.user_deleted_success'));
    }

    /**
     * Login Sebagai pengguna (Impersonasi Sesi Superadmin).
     */
    public function impersonate(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', __('users.cannot_modify_self'));
        }

        // Simpan ID admin di sesi
        session(['admin_impersonator_id' => Auth::id()]);

        // Login sebagai target
        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', __('users.impersonate_success', ['name' => $user->name]));
    }

    /**
     * Hentikan mode impersonasi & kembali ke sesi admin.
     */
    public function stopImpersonating(): RedirectResponse
    {
        $adminId = session('admin_impersonator_id');

        if ($adminId) {
            session()->forget('admin_impersonator_id');
            Auth::loginUsingId($adminId);

            return redirect()->route('admin.users.index')
                ->with('success', __('users.impersonate_ended'));
        }

        return redirect()->route('dashboard');
    }
}
