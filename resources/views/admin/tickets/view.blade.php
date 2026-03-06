@extends('layouts.admin')

@section('title')
    Support Ticket: #{{ $ticket->id }} - {{ $ticket->subject }}
@endsection

@section('content-header')
    <h1>{{ $ticket->subject }} <small>#{{ $ticket->id }}</small></h1>
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
            <div class="box box-success shadow-lg">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-reply text-success"></i> Post a Quick Reply</h3>
                </div>
                <form action="{{ route('admin.tickets.view', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                    {!! csrf_field() !!}
                    <div class="box-body">
                        <div class="form-group">
                            <textarea name="message" class="form-control" rows="6" placeholder="Write your professional response here..." required style="resize: vertical; border-radius: 8px; font-size: 14px; padding: 12px;"></textarea>
                        </div>
                        <div class="form-group no-margin">
                            <label class="text-muted small uppercase">Optional Attachments</label>
                            <input type="file" name="attachments[]" class="form-control" multiple style="border-radius: 8px;">
                            <p class="help-block no-margin small">Support images, logs, and configuration files.</p>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-success btn-flat pull-right rounded-pill px-4 font-bold">
                            <i class="fa fa-paper-plane"></i> Send Reply
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="callout callout-info shadow-sm" style="border-radius: 8px;">
                <h4><i class="fa fa-lock"></i> Resolved Conversation</h4>
                <p>This ticket is currently marked as <strong>Closed</strong>. You can re-open it from the sidebar if needed.</p>
            </div>
        @endif

        <div class="timeline" style="margin-top: 30px;">
            @foreach($ticket->messages as $message)
                <div style="margin-bottom: 25px;">
                    <i class="fa {{ $message->user->root_admin ? 'fa-shield bg-blue' : 'fa-user bg-gray' }}"></i>
                    <div class="timeline-item shadow-sm" style="border-radius: 12px; border: 1px solid #eee; background: white;">
                        <span class="time text-muted"><i class="fa fa-clock-o"></i> {{ $message->created_at->format('M j, Y H:i') }} ({{ $message->created_at->diffForHumans() }})</span>
                        <h3 class="timeline-header no-border" style="background: #fdfdfd; border-radius: 12px 12px 0 0; padding: 12px 15px;">
                            <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim($message->user->email))) }}?s=160" class="img-circle" style="width: 28px; height: 28px; margin-right: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <a href="{{ route('admin.users.view', $message->user->id) }}" class="text-bold text-dark">{{ $message->user->name_first }} {{ $message->user->name_last }}</a>
                            @if($message->user->root_admin)
                                <span class="label label-info border-radius-sm" style="margin-left: 8px; font-weight: 800; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px;">Staff</span>
                            @endif
                        </h3>
                        <div class="timeline-body" style="padding: 20px; font-size: 15px; line-height: 1.6; color: #444;">
                            {!! nl2br(e($message->message)) !!}
                            
                            @if($message->attachments->count() > 0)
                                <div class="attachment-block clearfix" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 8px; border: 1px solid #f0f0f0;">
                                    <h4 class="attachment-heading text-muted small uppercase font-bold" style="margin-bottom: 15px;"><i class="fa fa-paperclip"></i> Files Provided</h4>
                                    <div class="row">
                                        @foreach($message->attachments as $attachment)
                                            <div class="col-sm-4">
                                                <a href="{{ route('admin.tickets.attachment', $attachment->hash) }}" class="btn btn-default btn-xs btn-block" style="text-overflow: ellipsis; white-space: nowrap; overflow: hidden; padding: 8px 10px; border-radius: 6px; text-align: left;">
                                                    <i class="fa fa-cloud-download text-primary"></i> {{ $attachment->filename }} <span class="text-muted pull-right">({{ round($attachment->size / 1024) }} KB)</span>
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
            <li><i class="fa fa-circle-o bg-gray text-muted"></i></li>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="box box-primary shadow-lg border-radius-lg overflow-hidden">
            <div class="box-header with-border bg-gray-light">
                <h3 class="box-title text-bold uppercase small tracking-wider">Management Console</h3>
            </div>
            <div class="box-body no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <td class="text-muted font-bold small uppercase" style="width: 40%; vertical-align: middle; padding: 15px;">Status</td>
                            <td style="padding: 15px;">
                                @if($ticket->status === 'open')
                                    <span class="label label-warning" style="padding: 5px 10px; font-weight: 800; text-transform: uppercase;">Open Case</span>
                                @else
                                    <span class="label label-success" style="padding: 5px 10px; font-weight: 800; text-transform: uppercase;">Resolved</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold small uppercase" style="vertical-align: middle; padding: 15px;">Department</td>
                            <td style="padding: 15px; font-weight: 600;">{{ $ticket->ticketDepartment ? $ticket->ticketDepartment->name : $ticket->department }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold small uppercase" style="vertical-align: middle; padding: 15px;">Customer</td>
                            <td style="padding: 15px;"><a href="{{ route('admin.users.view', $ticket->user->id) }}" style="font-weight: 600;">{{ $ticket->user->email }}</a></td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold small uppercase" style="vertical-align: middle; padding: 15px;">Instance</td>
                            <td style="padding: 15px;">
                                @if($ticket->server)
                                    <a href="{{ route('admin.servers.view', $ticket->server->id) }}" style="font-weight: 600;">{{ $ticket->server->name }}</a>
                                @else
                                    <span class="text-muted italic">No linked server</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold small uppercase" style="vertical-align: middle; padding: 15px;">Created</td>
                            <td style="padding: 15px; font-size: 13px;">{{ $ticket->created_at->format('M j, Y H:i') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="box-footer" style="background: #fdfdfd; padding: 20px;">
                <form action="{{ route('admin.tickets.status', $ticket->id) }}" method="POST">
                    {!! csrf_field() !!}
                    @if($ticket->status === 'open')
                        <button type="submit" class="btn btn-danger btn-block btn-flat font-bold shadow-sm" style="border-radius: 6px; padding: 10px;">
                            <i class="fa fa-check-circle"></i> Resolve & Close
                        </button>
                    @else
                        <button type="submit" class="btn btn-success btn-block btn-flat font-bold shadow-sm" style="border-radius: 6px; padding: 10px;">
                            <i class="fa fa-undo"></i> Re-open Ticket
                        </button>
                    @endif
                </form>
            </div>
        </div>

        <div class="alert alert-info border-none shadow-sm" style="background-color: #f7fbff !important; border-left: 4px solid #3c8dbc !important; color: #31708f !important; border-radius: 8px;">
            <h4 style="font-size: 14px; font-weight: 800; text-transform: uppercase;"><i class="fa fa-lightbulb-o"></i> Admin Note</h4>
            <p style="font-size: 12px; margin-bottom: 0;">Replying to this ticket will automatically set the status back to <strong>Open</strong> if it was closed.</p>
        </div>
    </div>
</div>

<style>
    .timeline::before {
        background: #eee;
    }
    .timeline-item {
        margin-top: 0 !important;
    }
    .shadow-lg {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
    }
    .border-radius-lg { border-radius: 12px !important; }
    .rounded-pill { border-radius: 50px !important; }
    .font-bold { font-weight: 700 !important; }
</style>
@endsection
