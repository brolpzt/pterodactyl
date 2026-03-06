@extends('layouts.admin')

@section('title')
    View Ticket: {{ $ticket->subject }}
@endsection

@section('content-header')
    <h1>{{ $ticket->subject }}<small>{{ $ticket->department }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.tickets') }}">Tickets</a></li>
        <li class="active">View #{{ $ticket->id }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
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
                        None
                    @endif
                </p>
                <p><strong>Created:</strong> {{ $ticket->created_at->format('Y-m-d H:i:s') }}</p>
            </div>
        </div>

        @foreach($ticket->messages as $message)
            <div class="box @if($message->user->root_admin) box-info @else box-default @endif">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $message->user->name_first }} {{ $message->user->name_last }}</h3>
                    <div class="box-tools pull-right">
                        <span class="text-muted">{{ $message->created_at->format('Y-m-d H:i:s') }}</span>
                    </div>
                </div>
                <div class="box-body">
                    {!! nl2br(e($message->message)) !!}
                </div>
            </div>
        @endforeach
    </div>
    
    <div class="col-md-4">
        <!-- Any additional actions for the ticket could go here (e.g., closing the ticket, deleting it, etc.) -->
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Actions</h3>
            </div>
            <div class="box-body">
                <p>Action implementation here...</p>
            </div>
        </div>
    </div>
</div>
@endsection
