@extends('layouts.admin')

@section('title')
    FastDL Nodes
@endsection

@section('content-header')
    <h1>FastDL Nodes<small>Manage external web servers for fast downloads.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">FastDL Nodes</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">FastDL Node List</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.fastdl.new') }}"><button type="button" class="btn btn-sm btn-primary">Create New</button></a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Location</th>
                            <th>FQDN</th>
                            <th>Status</th>
                            <th class="text-right">Created</th>
                        </tr>
                        @foreach ($nodes as $node)
                            <tr>
                                <td><code>{{ $node->id }}</code></td>
                                <td><a href="{{ route('admin.fastdl.view', $node->id) }}">{{ $node->name }}</a></td>
                                <td>{{ $node->location->short }}</td>
                                <td><code>{{ $node->fqdn }}:{{ $node->port }}</code></td>
                                <td>
                                    @if($node->is_active)
                                        <span class="label label-success">Active</span>
                                    @else
                                        <span class="label label-danger">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ $node->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
