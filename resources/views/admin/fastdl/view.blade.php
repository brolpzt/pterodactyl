@extends('layouts.admin')

@section('title')
    FastDL Node: {{ $node->name }}
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>Update node configuration.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.fastdl') }}">FastDL Nodes</a></li>
        <li class="active">{{ $node->name }}</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.fastdl.view', $node->id) }}" method="POST">
    @method('PATCH')
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Node Details</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pName" class="control-label">Node Name</label>
                        <input type="text" name="name" id="pName" class="form-control" value="{{ old('name', $node->name) }}" />
                    </div>
                    <div class="form-group">
                        <label for="pLocationId" class="control-label">Location</label>
                        <select name="location_id" id="pLocationId" class="form-control">
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ old('location_id', $node->location_id) == $location->id ? 'selected' : '' }}>{{ $location->short }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pFQDN" class="control-label">FQDN</label>
                        <input type="text" name="fqdn" id="pFQDN" class="form-control" value="{{ old('fqdn', $node->fqdn) }}" />
                    </div>
                    <div class="form-group">
                        <label for="pPort" class="control-label">SFTP Port</label>
                        <input type="number" name="port" id="pPort" class="form-control" value="{{ old('port', $node->port) }}" />
                    </div>
                    <div class="form-group">
                        <label for="pRemotePath" class="control-label">Remote Path</label>
                        <input type="text" name="remote_path" id="pRemotePath" class="form-control" value="{{ old('remote_path', $node->remote_path) }}" />
                    </div>
                    <div class="form-group">
                        <label for="pIsActive" class="control-label">Is Active</label>
                        <select name="is_active" id="pIsActive" class="form-control">
                            <option value="1" {{ $node->is_active ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ !$node->is_active ? 'selected' : '' }}>No</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Authentication</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pUsername" class="control-label">SFTP Username</label>
                        <input type="text" name="username" id="pUsername" class="form-control" value="{{ old('username', $node->username) }}" />
                    </div>
                    <div class="form-group">
                        <label for="pPassword" class="control-label">SFTP Password</label>
                        <input type="password" name="password" id="pPassword" class="form-control" />
                        <p class="text-muted small">Leave blank to keep current password/key.</p>
                    </div>
                    <div class="form-group">
                        <label for="pPrivateKey" class="control-label">Private Key</label>
                        <textarea name="private_key" id="pPrivateKey" class="form-control" rows="10">{{ old('private_key') }}</textarea>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-primary pull-right">Update Node</button>
                    <button type="button" class="btn btn-danger pull-left" data-toggle="modal" data-target="#deleteNodeModal">Delete Node</button>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="deleteNodeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Delete FastDL Node?</h4>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this FastDL node? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form action="{{ route('admin.fastdl.delete', $node->id) }}" method="POST">
                    {!! csrf_field() !!}
                    @method('DELETE')
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Node</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
