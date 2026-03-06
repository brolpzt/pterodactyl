<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Wallet;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Spatie\QueryBuilder\QueryBuilder;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\WalletService;

class BillingController extends Controller
{
    public function __construct(
        protected AlertsMessageBag $alert,
        protected WalletService $walletService,
    ) {
    }

    /**
     * Display billing overview - users with wallet balances.
     */
    public function index(Request $request): View
    {
        $users = QueryBuilder::for(
            User::query()
                ->select('users.*')
                ->leftJoin('wallets', 'wallets.user_id', '=', 'users.id')
                ->selectRaw('COALESCE(wallets.balance, 0) as wallet_balance')
        )
            ->allowedFilters(['username', 'email', 'uuid'])
            ->defaultSort('-wallet_balance')
            ->allowedSorts(['id', 'username', 'email', 'wallet_balance'])
            ->paginate(50);

        return view('admin.billing.index', ['users' => $users]);
    }

    /**
     * Display user wallet management.
     */
    public function viewUser(User $user): View
    {
        $wallet = $this->walletService->getOrCreateWallet($user);
        $transactions = $wallet->transactions()->limit(50)->get();

        return view('admin.billing.view_user', [
            'user' => $user,
            'wallet' => $wallet,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Add credit to user's wallet.
     */
    public function addCredit(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) $request->input('amount');
        $description = $request->input('description') ?: 'Admin credit adjustment';

        $this->walletService->adminAddCredit($user, $amount, $description);
        $this->alert->success("Added \${$amount} to {$user->username}'s wallet.")->flash();

        return redirect()->route('admin.billing.view_user', $user->id);
    }

    /**
     * Deduct credit from user's wallet.
     */
    public function deductCredit(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) $request->input('amount');
        $description = $request->input('description') ?: 'Admin deduction';

        try {
            $this->walletService->adminDeductCredit($user, $amount, $description);
            $this->alert->success("Deducted \${$amount} from {$user->username}'s wallet.")->flash();
        } catch (\RuntimeException $e) {
            $this->alert->danger($e->getMessage())->flash();
        }

        return redirect()->route('admin.billing.view_user', $user->id);
    }
}
