@extends('layouts.admin')

@section('title')
    New Addon Category
@endsection

@section('content-header')
    <h1>New Addon Category<small>Create a new category for server addons.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addon-categories') }}">Addon Categories</a></li>
        <li class="active">New</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <form action="{{ route('admin.addon-categories.new') }}" method="POST">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Category Details</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pName" class="control-label">Name</label>
                        <input type="text" name="name" id="pName" class="form-control" value="{{ old('name') }}" />
                    </div>
                    <div class="form-group">
                        <label for="pDescription" class="control-label">Description</label>
                        <textarea name="description" id="pDescription" class="form-control" rows="4">{{ old('description') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label for="pEggId" class="control-label">Associated Egg</label>
                        <select name="egg_id" id="pEggId" class="form-control">
                            @foreach($nests as $nest)
                                <optgroup label="{{ $nest->name }}">
                                    @foreach($nest->eggs as $egg)
                                        <option value="{{ $egg->id }}" {{ old('egg_id') != $egg->id ?: 'selected' }}>{{ $egg->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="text-muted small">This category will only be available for the selected egg.</p>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-primary btn-sm pull-right">Create Category</button>
                </div>
            </div>
        </div>
    </form>
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
