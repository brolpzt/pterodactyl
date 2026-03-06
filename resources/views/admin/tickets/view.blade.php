@extends('layouts.admin')

@section('title')
    View Ticket: {{ $ticket->subject }}
@endsection

@section('content-header')
    <h1>{{ $ticket->subject }}<small>{{ $ticket->ticketDepartment ? $ticket->ticketDepartment->name : $ticket->department }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.tickets') }}">Tickets</a></li>
        <li class="active">View #{{ $ticket->id }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        @foreach($ticket->messages as $message)
            <div class="box @if($message->user->root_admin) box-info @else box-default @endif">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $message->user->name_first }} {{ $message->user->name_last }}</h3>
                    <div class="box-tools pull-right">
                        @if($message->user->root_admin)
                            <span class="label label-info mr-2">Staff</span>
                        @endif
                        <span class="text-muted">{{ $message->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                <div class="box-body">
                    {!! nl2br(e($message->message)) !!}
                </div>
            </div>
        @endforeach

        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Reply</h3>
            </div>
            <form action="{{ route('admin.tickets.view', $ticket->id) }}" method="POST">
                <div class="box-body">
                    <div class="form-group">
                        <textarea name="message" class="form-control" rows="8" placeholder="Enter your reply here..." required></textarea>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-primary btn-sm pull-right">Send Reply</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Ticket Information</h3>
            </div>
            <div class="box-body">
                <p><strong>Status:</strong> 
                    @if($ticket->status === 'open')
                        <span class="label label-warning">Open</span>
                    @else
                        <span class="label label-success">Closed</span>
                    @endif
                </p>
                <p><strong>Department:</strong> {{ $ticket->ticketDepartment ? $ticket->ticketDepartment->name : $ticket->department }}</p>
                <p><strong>User:</strong> <a href="{{ route('admin.users.view', $ticket->user->id) }}">{{ $ticket->user->email }}</a></p>
                <p><strong>Server:</strong> 
                    @if($ticket->server)
                        <a href="{{ route('admin.servers.view', $ticket->server->id) }}">{{ $ticket->server->name }}</a>
                    @else
                        <span class="text-muted">None</span>
                    @endif
                </p>
                <p><strong>Created:</strong> {{ $ticket->created_at->toDayDateTimeString() }}</p>
                <p><strong>Last Activity:</strong> {{ $ticket->updated_at->diffForHumans() }}</p>
            </div>
            <div class="box-footer border-top">
                <form action="{{ route('admin.tickets.status', $ticket->id) }}" method="POST">
                    {!! csrf_field() !!}
                    @if($ticket->status === 'open')
                        <button type="submit" class="btn btn-danger btn-block btn-sm">Close Ticket</button>
                    @else
                        <button type="submit" class="btn btn-success btn-block btn-sm">Re-open Ticket</button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
