@extends('layouts.admin')

@section('title')
    Manage Ticket Departments
@endsection

@section('content-header')
    <h1>Ticket Departments<small>Manage departments that users can submit tickets to.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Departments</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Department List</h3>
                <div class="box-tools">
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newDepartmentModal">Create New</button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Tickets</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($departments as $department)
                            <tr>
                                <td><code>{{ $department->id }}</code></td>
                                <td>{{ $department->name }}</td>
                                <td>{{ $department->description ?: 'No description provided' }}</td>
                                <td>{{ $department->tickets_count ?? '0' }}</td>
                                <td class="text-right">
                                    <form action="{{ route('admin.departments.delete', $department->id) }}" method="POST">
                                        {!! csrf_field() !!}
                                        {!! method_field('DELETE') !!}
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Are you sure you want to delete this department?');"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newDepartmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.departments') }}" method="POST">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Create New Department</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name" class="control-label">Name <span class="field-required"></span></label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Technical Support" required>
                    </div>
                    <div class="form-group">
                        <label for="description" class="control-label">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="A brief description of this department..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    {!! csrf_field() !!}
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Department</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
