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
        @foreach($ticket->messages as $message)
            <div class="box @if($message->user->root_admin) box-info @else box-primary @endif">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim($message->user->email))) }}?s=160" class="img-circle" style="width: 25px; height: 25px; margin-right: 10px;">
                        {{ $message->user->name_first }} {{ $message->user->name_last }}
                        @if($message->user->root_admin)
                            <span class="label label-info">Staff</span>
                        @endif
                    </h3>
                    <div class="box-tools pull-right">
                        <span class="text-muted">{{ $message->created_at->format('M j, Y H:i') }} ({{ $message->created_at->diffForHumans() }})</span>
                    </div>
                </div>
                <div class="box-body">
                    {!! nl2br(e($message->message)) !!}
                    
                    @if($message->attachments->count() > 0)
                        <hr>
                        <p class="text-muted small uppercase">Attachments</p>
                        <div class="row">
                            @foreach($message->attachments as $attachment)
                                <div class="col-sm-4">
                                    <a href="{{ route('admin.tickets.attachment', $attachment->hash) }}" class="btn btn-default btn-xs btn-block overflow-hidden" style="text-overflow: ellipsis; white-space: nowrap;">
                                        <i class="fa fa-download"></i> {{ $attachment->filename }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Reply to Ticket</h3>
            </div>
            <form action="{{ route('admin.tickets.view', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                {!! csrf_field() !!}
                <div class="box-body">
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea name="message" class="form-control" rows="8" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="attachments">Attachments</label>
                        <input type="file" name="attachments[]" class="form-control" multiple>
                        <p class="help-block">You can select multiple files.</p>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-success pull-right">Send Reply</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-3">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Ticket Information</h3>
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
                            <td><a href="{{ route('admin.users.view', $ticket->user->id) }}">{{ $ticket->user->email }}</a></td>
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
                            <td>{{ $ticket->created_at->format('M j, Y H:i') }}</td>
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
