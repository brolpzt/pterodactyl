@extends('layouts.admin')

@section('title')
    Wallet: {{ $user->username }}
@endsection

@section('content-header')
    <h1>{{ $user->username }}<small>Wallet management</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.billing') }}">Billing</a></li>
        <li class="active">{{ $user->username }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Current Balance</h3>
            </div>
            <div class="box-body text-center">
                <p class="text-muted no-margin">Available Credits</p>
                <h2 class="no-margin">${{ number_format((float) $wallet->balance, 2) }}</h2>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Add Credit</h3>
            </div>
            <form action="{{ route('admin.billing.add_credit', $user->id) }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label for="add_amount">Amount ($)</label>
                        <input type="number" name="amount" id="add_amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="add_description">Description (optional)</label>
                        <input type="text" name="description" id="add_description" class="form-control" placeholder="e.g. Manual top-up">
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add Credit</button>
                </div>
            </form>
        </div>

        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">Deduct Credit</h3>
            </div>
            <form action="{{ route('admin.billing.deduct_credit', $user->id) }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label for="deduct_amount">Amount ($)</label>
                        <input type="number" name="amount" id="deduct_amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="deduct_description">Description (optional)</label>
                        <input type="text" name="description" id="deduct_description" class="form-control" placeholder="e.g. Refund reversal">
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-danger"><i class="fa fa-minus"></i> Deduct Credit</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Recent Transactions</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.users.view', $user->id) }}" class="btn btn-xs btn-default">View User</a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Balance After</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $tx)
                            <tr>
                                <td>{{ $tx->created_at->format('M d, Y H:i') }}</td>
                                <td>
                                    <span class="label label-{{ $tx->type === 'deposit' ? 'success' : ($tx->type === 'charge' ? 'danger' : 'default') }}">
                                        {{ ucfirst($tx->type) }}
                                    </span>
                                </td>
                                <td>{{ $tx->description ?? '-' }}</td>
                                <td class="text-right {{ $tx->amount >= 0 ? 'text-green' : 'text-red' }}">
                                    {{ $tx->amount >= 0 ? '+' : '' }}${{ number_format((float) $tx->amount, 2) }}
                                </td>
                                <td class="text-right">${{ $tx->balance_after ? number_format((float) $tx->balance_after, 2) : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No transactions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
