@extends('layouts.admin')

@section('title')
    Addon Categories
@endsection

@section('content-header')
    <h1>Addon Categories<small>Manage categories for server addons.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Addon Categories</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Category List</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.addon-categories.new') }}" class="btn btn-sm btn-primary">Create New</a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Egg</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        @foreach ($categories as $category)
                            <tr>
                                <td><code>{{ $category->id }}</code></td>
                                <td><a href="{{ route('admin.addon-categories.view', $category->id) }}">{{ $category->name }}</a></td>
                                <td>{{ str_limit($category->description, 50) }}</td>
                                <td><a href="{{ route('admin.nests.egg.view', $category->egg_id) }}">{{ $category->egg->name }}</a></td>
                                <td class="text-right">
                                    <a href="{{ route('admin.addon-categories.view', $category->id) }}" class="btn btn-xs btn-default"><i class="fa fa-pencil"></i></a>
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
