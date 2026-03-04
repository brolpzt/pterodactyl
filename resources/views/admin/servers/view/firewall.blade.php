@extends('layouts.admin')

@section('title')
    Server — {{ $server->name }}: Firewall
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>Manage server IP bans (firewall).</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.servers') }}">Servers</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">Firewall</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')
<div class="row">
    <div class="col-sm-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Active IP Bans</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr>
                        <th>IP Address</th>
                        <th>Reason</th>
                        <th>Banned At</th>
                        <th></th>
                    </tr>
                    @foreach($server->firewallRules as $rule)
                        <tr>
                            <td><code>{{ $rule->ip }}</code></td>
                            <td>{{ $rule->reason ?? '—' }}</td>
                            <td>{{ $rule->created_at->toDayDateTimeString() }}</td>
                            <td class="text-center">
                                <form action="{{ route('admin.servers.view.firewall.delete', [$server->id, $rule->id]) }}" method="POST">
                                    {!! csrf_field() !!}
                                    {!! method_field('DELETE') !!}
                                    <button type="submit" class="btn btn-xs btn-danger" data-action="remove"><i class="fa fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    @if(count($server->firewallRules) === 0)
                        <tr>
                            <td colspan="4" class="text-center text-muted">No banned IPs for this server.</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Create New Ban</h3>
            </div>
            <form action="{{ route('admin.servers.view.firewall.new', $server->id) }}" method="POST">
                <div class="box-body">
                    <div class="form-group">
                        <label for="pIP" class="control-label">IP Address</label>
                        <input id="pIP" type="text" name="ip" class="form-control" placeholder="192.168.1.100" value="{{ old('ip') }}" />
                        <p class="text-muted small">The IP address you wish to block from connecting to this server.</p>
                    </div>
                    <div class="form-group">
                        <label for="pReason" class="control-label">Reason</label>
                        <input id="pReason" type="text" name="reason" class="form-control" placeholder="Optional reason" value="{{ old('reason') }}" />
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <input type="submit" class="btn btn-sm btn-success pull-right" value="Ban IP Address" />
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    $('[data-action="remove"]').click(function (event) {
        var self = $(this);
        event.preventDefault();
        swal({
            title: 'Remove IP Ban?',
            type: 'warning',
            text: 'Are you sure you want to remove the ban for this IP? It will be immediately allowed to connect to the server again.',
            showCancelButton: true,
            confirmButtonText: 'Remove',
            confirmButtonColor: '#d9534f',
            closeOnConfirm: false,
            showLoaderOnConfirm: true,
        }, function () {
            self.parent().submit();
        });
    });
    </script>
@endsection
