@extends('layouts.admin')

@section('title')
    Billing & Wallets
@endsection

@section('content-header')
    <h1>Billing & Wallets<small>Manage user wallet balances.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Billing</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">User Wallets</h3>
                <div class="box-tools">
                    <form action="{{ route('admin.billing') }}" method="GET">
                        <div class="input-group input-group-sm">
                            <input type="text" name="filter[email]" class="form-control pull-right" value="{{ request()->input('filter.email') }}" placeholder="Search by email">
                            <div class="input-group-btn">
                                <button type="submit" class="btn btn-default"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th class="text-right">Balance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td><code>{{ $user->id }}</code></td>
                                <td>
                                    <a href="{{ route('admin.billing.view_user', $user->id) }}">{{ $user->username }}</a>
                                    @if($user->root_admin)<i class="fa fa-star text-yellow"></i>@endif
                                </td>
                                <td>{{ $user->email }}</td>
                                <td class="text-right">
                                    <strong>${{ number_format((float) ($user->wallet_balance ?? 0), 2) }}</strong>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.billing.view_user', $user->id) }}" class="btn btn-xs btn-primary">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="box-footer with-border">
                    <div class="col-md-12 text-center">{!! $users->appends(request()->query())->render() !!}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
