@extends('layouts.admin')

@section('title')
    List Tickets
@endsection

@section('content-header')
    <h1>Tickets<small>All support tickets on the system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Tickets</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Ticket List</h3>
                <div class="box-tools search01">
                    <form action="{{ route('admin.tickets') }}" method="GET">
                        <div class="input-group input-group-sm" style="width: 450px;">
                            <select name="status" class="form-control" style="width: 150px; float: left;">
                                <option value="">All Statuses</option>
                                <option value="open" @if($filters['status'] === 'open') selected @endif>Open</option>
                                <option value="closed" @if($filters['status'] === 'closed') selected @endif>Closed</option>
                            </select>
                            <select name="department" class="form-control" style="width: 250px; float: left;">
                                <option value="">All Departments</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" @if($filters['department'] == $department->id) selected @endif>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <div class="input-group-btn">
                                <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> Filter</button>
                                <a href="{{ route('admin.tickets') }}" class="btn btn-default">Clear</a>
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
                            <th>Subject</th>
                            <th>User</th>
                            <th>Server</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr class="align-middle">
                                <td><code>{{ $ticket->id }}</code></td>
                                <td><a href="{{ route('admin.tickets.view', $ticket->id) }}">{{ $ticket->subject }}</a></td>
                                <td><a href="{{ route('admin.users.view', $ticket->user->id) }}">{{ $ticket->user->email }}</a></td>
                                <td>
                                    @if($ticket->server)
                                        <a href="{{ route('admin.servers.view', $ticket->server->id) }}">{{ $ticket->server->name }}</a>
                                    @else
                                        <span class="text-muted">None</span>
                                    @endif
                                </td>
                                <td>{{ $ticket->ticketDepartment ? $ticket->ticketDepartment->name : $ticket->department }}</td>
                                <td>
                                    @if($ticket->status === 'open')
                                        <span class="label label-warning">Open</span>
                                    @else
                                        <span class="label label-success">Closed</span>
                                    @endif
                                </td>
                                <td>{{ $ticket->created_at->diffForHumans() }}</td>
                                <td class="text-center">
                                    <a class="btn btn-xs btn-primary" href="{{ route('admin.tickets.view', $ticket->id) }}"><i class="fa fa-eye"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($tickets->hasPages())
                <div class="box-footer with-border">
                    <div class="col-md-12 text-center">{!! $tickets->appends(['status' => $filters['status'], 'department' => $filters['department']])->render() !!}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
