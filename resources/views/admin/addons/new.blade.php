@extends('layouts.admin')

@section('title')
    New Addon
@endsection

@section('content-header')
    <h1>New Addon<small>Create a new addon for servers.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.addons') }}">Addons</a></li>
        <li class="active">New</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <form action="{{ route('admin.addons.new') }}" method="POST">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Addon Details</h3>
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
                        <p class="text-muted small">This addon will only be available for servers using this egg.</p>
                    </div>
                    <div class="form-group">
                        <label for="pIsActive" class="control-label">Status</label>
                        <select name="is_active" id="pIsActive" class="form-control">
                            <option value="1" {{ old('is_active') !== '0' ?: 'selected' }}>Active</option>
                            <option value="0" {{ old('is_active') !== '0' ?: 'selected' }}>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-secondary">
                <div class="box-header with-border">
                    <h3 class="box-title">Installation Script</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pContainerImage" class="control-label">Container Image</label>
                        <input type="text" name="container_image" id="pContainerImage" class="form-control" value="{{ old('container_image', 'alpine:latest') }}" />
                        <p class="text-muted small">The docker image to use for running the installation script.</p>
                    </div>
                    <div class="form-group">
                        <label for="pScript" class="control-label">Install Script</label>
                        <div id="editor_script" style="height:300px">{{ old('script') }}</div>
                        <p class="text-muted small">The bash script that will be executed in the container. Server files are mounted at <code>/home/container</code> (or <code>/mnt/server</code> depending on version).</p>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <textarea name="script" class="hidden"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm pull-right">Create Addon</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('footer-scripts')
    @parent
    {!! Theme::js('vendor/ace/ace.js') !!}
    {!! Theme::js('vendor/ace/ext-modelist.js') !!}
    <script>
        $(document).ready(function () {
            $('#pEggId').select2();

            const ScriptEditor = ace.edit('editor_script');
            ScriptEditor.setTheme('ace/theme/chrome');
            ScriptEditor.getSession().setMode('ace/mode/sh');
            ScriptEditor.getSession().setUseWrapMode(true);
            ScriptEditor.setShowPrintMargin(false);

            $('form').on('submit', function (e) {
                $('textarea[name="script"]').val(ScriptEditor.getValue());
            });
        });
    </script>
@endsection
