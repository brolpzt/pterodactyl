@extends('layouts.admin')

@section('title')
    New FastDL Node
@endsection

@section('content-header')
    <h1>New FastDL Node<small>Add a new external synchronization target.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.fastdl') }}">FastDL Nodes</a></li>
        <li class="active">New</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.fastdl.new') }}" method="POST">
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Node Details</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pName" class="control-label">Node Name</label>
                        <input type="text" name="name" id="pName" class="form-control" value="{{ old('name') }}" />
                    </div>
                    <div class="form-group">
                        <label for="pLocationId" class="control-label">Location</label>
                        <select name="location_id" id="pLocationId" class="form-control">
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>{{ $location->short }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pFQDN" class="control-label">FQDN</label>
                        <input type="text" name="fqdn" id="pFQDN" class="form-control" value="{{ old('fqdn') }}" placeholder="fastdl.example.com" />
                    </div>
                    <div class="form-group">
                        <label for="pPort" class="control-label">SFTP Port</label>
                        <input type="number" name="port" id="pPort" class="form-control" value="{{ old('port', 22) }}" />
                    </div>
                    <div class="form-group">
                        <label for="pRemotePath" class="control-label">Remote Path</label>
                        <input type="text" name="remote_path" id="pRemotePath" class="form-control" value="{{ old('remote_path', '/var/www/fastdl') }}" />
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
                        <input type="text" name="username" id="pUsername" class="form-control" value="{{ old('username') }}" />
                    </div>
                    <div class="form-group">
                        <label for="pPassword" class="control-label">SFTP Password</label>
                        <input type="password" name="password" id="pPassword" class="form-control" />
                        <p class="text-muted small">Leave blank to use a Private Key instead.</p>
                    </div>
                    <div class="form-group">
                        <label for="pPrivateKey" class="control-label">Private Key</label>
                        <textarea name="private_key" id="pPrivateKey" class="form-control" rows="10">{{ old('private_key') }}</textarea>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-primary pull-right">Create Node</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
