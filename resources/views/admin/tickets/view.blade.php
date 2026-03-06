@extends('layouts.admin')

@section('title')
    View Ticket: {{ $ticket->subject }}
@endsection

@section('content-header')
    <h1>{{ $ticket->subject }}<small>#{{ $ticket->id }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.tickets') }}">Tickets</a></li>
        <li class="active">View #{{ $ticket->id }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Ticket Conversation</h3>
            </div>
            <div class="box-body chat" id="chat-box" style="display: flex; flex-direction: column; gap: 15px;">
                @foreach($ticket->messages as $message)
                    <div class="item" style="border-bottom: 1px solid #f4f4f4; padding-bottom: 10px;">
                        <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim($message->user->email))) }}?s=160" alt="user image" class="@if($message->user->root_admin) online @else offline @endif" style="width: 40px; height: 40px; border-radius: 50%; float: left; margin-right: 15px;">
                        <p class="message" style="margin-left: 55px;">
                            <a href="{{ route('admin.users.view', $message->user->id) }}" class="name">
                                <small class="text-muted pull-right"><i class="fa fa-clock-o"></i> {{ $message->created_at->diffForHumans() }}</small>
                                {{ $message->user->name_first }} {{ $message->user->name_last }}
                                @if($message->user->root_admin)
                                    <span class="label label-info ml-2">Staff</span>
                                @endif
                            </a>
                            <div style="margin-left: 55px; color: #444; margin-top: 5px;">
                                {!! nl2br(e($message->message)) !!}
                            </div>
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Post a Reply</h3>
            </div>
            <form action="{{ route('admin.tickets.view', $ticket->id) }}" method="POST">
                <div class="box-body">
                    <div class="form-group">
                        <textarea name="message" class="form-control" rows="6" placeholder="Type your reply to the user..." required></textarea>
                    </div>
                </div>
                <div class="box-footer text-right">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success"><i class="fa fa-paper-plane"></i> Send Reply</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Ticket Details</h3>
            </div>
            <div class="box-body no-padding">
                <table class="table table-striped">
                    <tbody>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>
                                @if($ticket->status === 'open')
                                    <span class="label label-warning">Open</span>
                                @else
                                    <span class="label label-success">Closed</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Department</strong></td>
                            <td>{{ $ticket->ticketDepartment ? $ticket->ticketDepartment->name : $ticket->department }}</td>
                        </tr>
                        <tr>
                            <td><strong>User</strong></td>
                            <td><a href="{{ route('admin.users.view', $ticket->user->id) }}">{{ $ticket->user->name_last }}</a></td>
                        </tr>
                        <tr>
                            <td><strong>Server</strong></td>
                            <td>
                                @if($ticket->server)
                                    <a href="{{ route('admin.servers.view', $ticket->server->id) }}">{{ $ticket->server->name }}</a>
                                @else
                                    <span class="text-muted">None</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Created</strong></td>
                            <td><small>{{ $ticket->created_at->format('M j, Y H:i') }}</small></td>
                        </tr>
                        <tr>
                            <td><strong>Updated</strong></td>
                            <td><small>{{ $ticket->updated_at->diffForHumans() }}</small></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
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
