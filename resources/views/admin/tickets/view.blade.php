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
            <div class="box box-primary shadow-sm" style="border-radius: 8px;">
                <div class="box-header with-border" style="background: #f9f9f9; border-radius: 8px 8px 0 0;">
                    <h3 class="box-title uppercase small font-bold tracking-wider" style="font-size: 11px;"><i class="fa fa-reply text-primary"></i> Post a Quick Reply</h3>
                </div>
                <form action="{{ route('admin.tickets.view', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                    {!! csrf_field() !!}
                    <div class="box-body" style="padding: 15px;">
                        <div class="form-group">
                            <textarea name="message" class="form-control" rows="4" placeholder="Write your professional response here..." required style="resize: vertical; border-radius: 6px; padding: 12px; border: 1px solid #ddd;"></textarea>
                        </div>
                        <div class="form-group no-margin">
                            <label class="text-muted uppercase font-bold" style="font-size: 10px;">Optional Attachments</label>
                            <input type="file" name="attachments[]" class="form-control" multiple style="border-radius: 4px; border-style: dashed; font-size: 12px;">
                        </div>
                    </div>
                    <div class="box-footer" style="background: white; border-radius: 0 0 8px 8px; padding: 10px 15px;">
                        <button type="submit" class="btn btn-primary btn-sm btn-flat pull-right rounded-pill px-4 font-bold">
                            <i class="fa fa-paper-plane"></i> Send Reply
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="alert alert-info shadow-sm" style="border-radius: 8px; background-color: #f7fbff !important; border-left: 5px solid #3c8dbc !important; color: #31708f !important;">
                <h4 style="font-size: 14px;"><i class="fa fa-lock"></i> Resolved Conversation</h4>
                <p style="font-size: 12px;">This ticket is currently marked as <strong>Closed</strong>. You can re-open it from the sidebar if needed.</p>
            </div>
        @endif

        <div style="margin-top: 25px;">
            @foreach($ticket->messages as $message)
                <div class="box {{ $message->user->root_admin ? 'box-info' : 'box-default' }} shadow-sm" style="border-radius: 8px; margin-bottom: 20px;">
                    <div class="box-header with-border" style="background: #fafafa; border-radius: 8px 8px 0 0; padding: 10px 15px;">
                        <h3 class="box-title" style="display: flex; align-items: center; width: 100%;">
                            <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim($message->user->email))) }}?s=160" class="img-circle" style="width: 24px; height: 24px; margin-right: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <span class="text-bold text-dark" style="font-size: 13px;">{{ $message->user->name_first }} {{ $message->user->name_last }}</span>
                            @if($message->user->root_admin)
                                <span class="label label-info" style="margin-left: 8px; font-weight: 800; font-size: 8px; text-transform: uppercase; padding: 2px 5px;">Staff</span>
                            @endif
                            <span class="pull-right text-muted" style="margin-left: auto; font-weight: normal; font-size: 11px;">
                                <i class="fa fa-clock-o"></i> {{ $message->created_at->format('M j, Y H:i') }} ({{ $message->created_at->diffForHumans() }})
                            </span>
                        </h3>
                    </div>
                    <div class="box-body" style="padding: 15px; font-size: 13px; line-height: 1.5; color: #333; background: #fff;">
                        {!! nl2br(e($message->message)) !!}
                        
                        @if($message->attachments->count() > 0)
                            <div class="attachment-info" style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #f0f0f0;">
                                <p class="text-muted uppercase font-bold" style="margin-bottom: 8px; font-size: 10px;"><i class="fa fa-paperclip"></i> Files Provided</p>
                                <div class="row">
                                    @foreach($message->attachments as $attachment)
                                        <div class="col-sm-4">
                                            <a href="{{ route('admin.tickets.attachment', $attachment->hash) }}" class="btn btn-default btn-xs btn-block" style="text-overflow: ellipsis; white-space: nowrap; overflow: hidden; padding: 6px 10px; border-radius: 4px; text-align: left; background: #fafafa; border: 1px solid #eee; font-size: 11px;">
                                                <i class="fa fa-cloud-download text-primary"></i> {{ $attachment->filename }} <span class="text-muted pull-right">({{ round($attachment->size / 1024) }} KB)</span>
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="box box-primary shadow-sm" style="border-radius: 8px;">
            <div class="box-header with-border" style="background: #f9f9f9; border-radius: 8px 8px 0 0;">
                <h3 class="box-title uppercase small font-bold tracking-wider" style="font-size: 11px;">Management Console</h3>
            </div>
            <div class="box-body no-padding" style="background: #fafafa;">
                <table class="table table-hover table-striped" style="margin-bottom: 0;">
                    <tbody>
                        <tr>
                            <td class="text-muted font-bold uppercase" style="width: 40%; vertical-align: middle; padding: 10px 15px; font-size: 10px;">Status</td>
                            <td style="padding: 10px 15px; text-align: right;">
                                @if($ticket->status === 'open')
                                    <span class="label label-warning" style="padding: px 6px; font-weight: 800; text-transform: uppercase; font-size: 9px;">Open</span>
                                @else
                                    <span class="label label-success" style="padding: 3px 6px; font-weight: 800; text-transform: uppercase; font-size: 9px;">Resolved</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold uppercase" style="vertical-align: middle; padding: 10px 15px; font-size: 10px;">Department</td>
                            <td style="padding: 10px 15px; text-align: right; font-weight: 600; font-size: 12px;">{{ $ticket->ticketDepartment ? $ticket->ticketDepartment->name : $ticket->department }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold uppercase" style="vertical-align: middle; padding: 10px 15px; font-size: 10px;">Customer</td>
                            <td style="padding: 10px 15px; text-align: right; font-size: 11px;"><a href="{{ route('admin.users.view', $ticket->user->id) }}" style="font-weight: 600;">{{ $ticket->user->email }}</a></td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold uppercase" style="vertical-align: middle; padding: 10px 15px; font-size: 10px;">Instance</td>
                            <td style="padding: 10px 15px; text-align: right;">
                                @if($ticket->server)
                                    <a href="{{ route('admin.servers.view', $ticket->server->id) }}" style="font-weight: 600; font-size: 11px;">{{ $ticket->server->name }}</a>
                                @else
                                    <span class="text-muted italic small">None</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold uppercase" style="vertical-align: middle; padding: 10px 15px; font-size: 10px;">Created</td>
                            <td style="padding: 10px 15px; text-align: right; font-size: 11px;">{{ $ticket->created_at->format('M j, Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted font-bold uppercase" style="vertical-align: middle; padding: 10px 15px; font-size: 10px;">Activity</td>
                            <td style="padding: 10px 15px; text-align: right; font-size: 11px;">{{ $ticket->updated_at->diffForHumans() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="box-footer" style="background: #fdfdfd; padding: 15px; border-radius: 0 0 8px 8px;">
                <form action="{{ route('admin.tickets.status', $ticket->id) }}" method="POST">
                    {!! csrf_field() !!}
                    @if($ticket->status === 'open')
                        <button type="submit" class="btn btn-danger btn-block btn-xs btn-flat font-bold" style="border-radius: 4px; padding: 6px;">
                            <i class="fa fa-check-circle"></i> Mark as Resolved
                        </button>
                    @else
                        <button type="submit" class="btn btn-success btn-block btn-xs btn-flat font-bold" style="border-radius: 4px; padding: 6px;">
                            <i class="fa fa-undo"></i> Re-open Ticket
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .shadow-sm { box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important; }
    .rounded-pill { border-radius: 50px !important; }
    .font-bold { font-weight: 700 !important; }
    .uppercase { text-transform: uppercase !important; }
</style>
@endsection
