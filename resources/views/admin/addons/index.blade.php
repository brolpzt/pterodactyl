@extends('layouts.admin')

@section('title')
    Addons
@endsection

@section('content-header')
    <h1>Addons<small>Manage all available addons for servers.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Addons</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Addon List</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.addons.new') }}" class="btn btn-sm btn-primary">Create New</a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Egg</th>
                            <th>Nest</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th></th>
                        </tr>
                        @foreach ($addons as $addon)
                            <tr>
                                <td><code>{{ $addon->id }}</code></td>
                                <td><a href="{{ route('admin.addons.view', $addon->id) }}">{{ $addon->name }}</a></td>
                                <td><a href="{{ route('admin.nests.egg.view', $addon->egg_id) }}">{{ $addon->egg->name }}</a></td>
                                <td><a href="{{ route('admin.nests.view', $addon->egg->nest_id) }}">{{ $addon->egg->nest->name }}</a></td>
                                <td>
                                    @if($addon->is_active)
                                        <span class="label label-success">Active</span>
                                    @else
                                        <span class="label label-danger">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ $addon->created_at->diffForHumans() }}</td>
                                <td class="text-center">
                                    <a href="{{ route('admin.addons.view', $addon->id) }}" class="btn btn-xs btn-default"><i class="fa fa-pencil"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
