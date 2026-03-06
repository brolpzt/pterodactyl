@extends('layouts.admin')

@section('title')
    Support Ticket: #{{ $ticket->id }} - {{ $ticket->subject }}
@endsection

@section('content-header')
    <h1>{{ $ticket->subject }}<small>Manage this support ticket from here.</small></h1>
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
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label">Message <span class="field-required"></span></label>
                            <textarea name="message" class="form-control" rows="8" placeholder="Type your message here..." required></textarea>
                        </div>
                        <div class="form-group no-margin">
                            <label class="control-label">Optional Attachments</label>
                            <input type="file" name="attachments[]" class="form-control" multiple>
                            <p class="text-muted small">You can upload multiple files at once.</p>
                        </div>
                    </div>
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        <button type="submit" class="btn btn-primary btn-sm pull-right">Send Reply</button>
                    </div>
                </form>
            </div>
        @else
            <div class="alert alert-info">
                This ticket is currently marked as <strong>Resolved</strong>. You can re-open it from the management console if needed.
            </div>
        @endif

        @foreach($ticket->messages as $message)
            <div class="box {{ $message->user->root_admin ? 'box-info' : '' }}">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $message->user->username }} <small>{{ $message->created_at->format('M j, Y H:i') }} ({{ $message->created_at->diffForHumans() }})</small></h3>
                    <div class="box-tools">
                        @if($message->user->root_admin)
                            <span class="label label-default">STAFF</span>
                        @endif
                    </div>
                </div>
                <div class="box-body">
                    <p style="font-weight: normal; margin-bottom: 0;">{!! nl2br(e($message->message)) !!}</p>
                    
                    @if($message->attachments->count() > 0)
                        <hr style="margin: 15px 0;">
                        <p class="text-muted small uppercase font-bold" style="font-size: 10px; margin-bottom: 10px;">User Provided Files</p>
                        <div class="row">
                            @foreach($message->attachments as $attachment)
                                <div class="col-sm-4">
                                    <a href="{{ route('admin.tickets.attachment', $attachment->hash) }}" class="btn btn-default btn-xs btn-block text-left" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <i class="fa fa-download"></i> {{ $attachment->filename }} <span class="text-muted small pull-right">({{ round($attachment->size / 1024) }} KB)</span>
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
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Management Console</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <td>Status</td>
                            <td class="text-right">
                                @if($ticket->status === 'open')
                                    <span class="label label-warning">OPEN</span>
                                @else
                                    <span class="label label-success">RESOLVED</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td>Department</td>
                            <td class="text-right" style="font-weight:600;">{{ $ticket->ticketDepartment ? $ticket->ticketDepartment->name : $ticket->department }}</td>
                        </tr>
                        <tr>
                            <td>Customer</td>
                            <td class="text-right"><a href="{{ route('admin.users.view', $ticket->user->id) }}">{{ $ticket->user->email }}</a></td>
                        </tr>
                        <tr>
                            <td>Instance</td>
                            <td class="text-right">
                                @if($ticket->server)
                                    <a href="{{ route('admin.servers.view', $ticket->server->id) }}">{{ $ticket->server->name }}</a>
                                @else
                                    <span class="text-muted italic small">None</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td>Created</td>
                            <td class="text-right small text-muted">{{ $ticket->created_at->format('M j, Y') }}</td>
                        </tr>
                        <tr>
                            <td>Activity</td>
                            <td class="text-right small text-muted">{{ $ticket->updated_at->diffForHumans() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.tickets.status', $ticket->id) }}" method="POST">
                    {!! csrf_field() !!}
                    @if($ticket->status === 'open')
                        <button type="submit" class="btn btn-danger btn-block btn-xs">
                           Mark as Resolved
                        </button>
                    @else
                        <button type="submit" class="btn btn-success btn-block btn-xs">
                           Re-open Ticket
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
