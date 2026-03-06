@extends('layouts.admin')

@section('title')
    Support Ticket: #{{ $ticket->id }} - {{ $ticket->subject }}
@endsection

@section('content-header')
    <h1>{{ $ticket->subject }} <small>Support Ticket #{{ $ticket->id }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.tickets') }}">Tickets</a></li>
        <li class="active">#{{ $ticket->id }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-9">
        @if($ticket->status === 'open')
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Post a Reply</h3>
                </div>
                <form action="{{ route('admin.tickets.view', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                    {!! csrf_field() !!}
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label">Message</label>
                            <textarea name="message" class="form-control" rows="6" placeholder="Write your professional response here..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Attachments</label>
                            <input type="file" name="attachments[]" class="form-control" multiple>
                            <p class="help-block no-margin small text-muted">You can upload multiple files at once.</p>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary btn-sm pull-right">
                            <i class="fa fa-paper-plane"></i> Send Reply
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="alert alert-info">
                This ticket is currently marked as <strong>Closed</strong>. You can re-open it from the management console if needed.
            </div>
        @endif

        @foreach($ticket->messages as $message)
            <div class="box {{ $message->user->root_admin ? 'box-info' : '' }}">
                <div class="box-header with-border">
                    <div class="user-block">
                        <img class="img-circle img-bordered-sm" src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim($message->user->email))) }}?s=160" alt="user image">
                        <span class="username">
                            <a href="{{ route('admin.users.view', $message->user->id) }}">{{ $message->user->name_first }} {{ $message->user->name_last }}</a>
                            @if($message->user->root_admin)
                                <span class="label label-info border-radius-sm" style="margin-left:8px;">STAFF</span>
                            @endif
                        </span>
                        <span class="description">{{ $message->created_at->format('M j, Y H:i') }} ({{ $message->created_at->diffForHumans() }})</span>
                    </div>
                </div>
                <div class="box-body">
                    <p>{!! nl2br(e($message->message)) !!}</p>
                    
                    @if($message->attachments->count() > 0)
                        <hr style="margin: 10px 0;">
                        <label class="text-muted small uppercase font-bold" style="font-size: 10px; display: block; margin-bottom: 8px;">Attachments</label>
                        <div class="row">
                            @foreach($message->attachments as $attachment)
                                <div class="col-sm-4">
                                    <a href="{{ route('admin.tickets.attachment', $attachment->hash) }}" class="btn btn-default btn-xs btn-block text-left" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <i class="fa fa-download text-primary"></i> {{ $attachment->filename }} <span class="text-muted small pull-right">({{ round($attachment->size / 1024) }} KB)</span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    
    <div class="col-md-3">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Management Console</h3>
            </div>
            <div class="box-body no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <td class="text-muted font-bold small uppercase">Status</td>
                            <td class="text-right">
                                @if($ticket->status === 'open')
                                    <span class="label label-warning">OPEN</span>
                                @else
                                    <span class="label label-success">RESOLVED</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold small uppercase">Department</td>
                            <td class="text-right">{{ $ticket->ticketDepartment ? $ticket->ticketDepartment->name : $ticket->department }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold small uppercase">Customer</td>
                            <td class="text-right"><a href="{{ route('admin.users.view', $ticket->user->id) }}">{{ $ticket->user->username }}</a></td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold small uppercase">Server</td>
                            <td class="text-right">
                                @if($ticket->server)
                                    <a href="{{ route('admin.servers.view', $ticket->server->id) }}">{{ $ticket->server->name }}</a>
                                @else
                                    <span class="text-muted italic">None</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold small uppercase">Created</td>
                            <td class="text-right small text-muted">{{ $ticket->created_at->format('M j, Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold small uppercase">Activity</td>
                            <td class="text-right small text-muted">{{ $ticket->updated_at->diffForHumans() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.tickets.status', $ticket->id) }}" method="POST">
                    {!! csrf_field() !!}
                    @if($ticket->status === 'open')
                        <button type="submit" class="btn btn-danger btn-block btn-sm">
                            <i class="fa fa-times-circle"></i> Mark as Resolved
                        </button>
                    @else
                        <button type="submit" class="btn btn-success btn-block btn-sm">
                            <i class="fa fa-undo"></i> Re-open Ticket
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <style>
        .username .label {
            font-size: 9px;
            letter-spacing: 0.5px;
            font-weight: 800;
        }
        .user-block .username {
            font-size: 14px;
        }
    </style>
@endsection
