@extends('layouts.admin')

@section('title')
    Addon Category: {{ $category->name }}
@endsection

@section('content-header')
    <h1>{{ $category->name }}<small>Edit category details.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addon-categories') }}">Addon Categories</a></li>
        <li class="active">{{ $category->name }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <form action="{{ route('admin.addon-categories.view', $category->id) }}" method="POST">
        @method('PATCH')
        @csrf
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Category Details</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pName" class="control-label">Name</label>
                        <input type="text" name="name" id="pName" class="form-control" value="{{ old('name', $category->name) }}" />
                    </div>
                    <div class="form-group">
                        <label for="pDescription" class="control-label">Description</label>
                        <textarea name="description" id="pDescription" class="form-control" rows="4">{{ old('description', $category->description) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label for="pEggId" class="control-label">Associated Egg</label>
                        <select name="egg_id" id="pEggId" class="form-control">
                            @foreach($nests as $nest)
                                <optgroup label="{{ $nest->name }}">
                                    @foreach($nest->eggs as $egg)
                                        <option value="{{ $egg->id }}" {{ old('egg_id', $category->egg_id) != $egg->id ?: 'selected' }}>{{ $egg->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="text-muted small">This category will only be available for the selected egg.</p>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary btn-sm pull-right">Save Changes</button>
                    <button type="button" class="btn btn-danger btn-sm pull-left" data-toggle="modal" data-target="#deleteModal">Delete Category</button>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.addon-categories.delete', $category->id) }}" method="POST">
                @method('DELETE')
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Delete Addon Category</h4>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this category? Any addons associated with this category will become un-categorized.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $(document).ready(function () {
            $('#pEggId').select2();
        });
    </script>
@endsection
