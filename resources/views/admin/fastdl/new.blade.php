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
                        <label for="pStorageType" class="control-label">Storage Type</label>
                        <select name="storage_type" id="pStorageType" class="form-control">
                            <option value="ssh" {{ old('storage_type', 'ssh') === 'ssh' ? 'selected' : '' }}>SSH / SFTP (rsync)</option>
                            <option value="s3" {{ old('storage_type') === 's3' ? 'selected' : '' }}>S3-compatible (Cloudflare R2, etc.)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pFQDN" class="control-label"><span id="fqdn-label">FQDN</span></label>
                        <input type="text" name="fqdn" id="pFQDN" class="form-control" value="{{ old('fqdn') }}" placeholder="fastdl.example.com" />
                        <p class="text-muted small" id="fqdn-help"></p>
                    </div>
                    <div id="ssh-fields">
                        <div class="form-group">
                            <label for="pPort" class="control-label">SFTP Port</label>
                            <input type="number" name="port" id="pPort" class="form-control" value="{{ old('port', 22) }}" />
                        </div>
                    </div>
                    <div id="s3-public-fields" style="display: none;">
                        <div class="form-group">
                            <label for="pPublicUrl" class="control-label">Public URL (optional)</label>
                            <input type="text" name="public_url" id="pPublicUrl" class="form-control" value="{{ old('public_url') }}" placeholder="https://fastdl.example.com" />
                            <p class="text-muted small">Full base URL if different from <code>http://{host}</code>. Include scheme (<code>https://</code>).</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pRemotePath" class="control-label"><span id="remote-path-label">Remote Path</span></label>
                        <input type="text" name="remote_path" id="pRemotePath" class="form-control" value="{{ old('remote_path', '/var/www/fastdl') }}" />
                        <p class="text-muted small" id="remote-path-help">Absolute path on the remote server.</p>
                    </div>
                    <div class="form-group">
                        <label for="pSyncPatterns" class="control-label">Sync Patterns</label>
                        <textarea name="sync_patterns" id="pSyncPatterns" class="form-control" rows="3">{{ old('sync_patterns', "*.bsp, *.nav, *.wad, *.tga, *.vmt, *.vtf, *.mdl, *.ani, *.vtx, *.vvd, *.phy, *.pcf, *.spr, *.wav, *.mp3, *.ogg") }}</textarea>
                        <p class="text-muted small">Comma-separated list of file extensions or glob patterns to synchronize. Example: <code>*.bsp, maps/*.nav, sound/*</code></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary" id="ssh-auth-box">
                <div class="box-header with-border">
                    <h3 class="box-title">SSH Authentication</h3>
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
            </div>
            <div class="box box-primary" id="s3-auth-box" style="display: none;">
                <div class="box-header with-border">
                    <h3 class="box-title">S3 / R2 Configuration</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pBucket" class="control-label">Bucket</label>
                        <input type="text" name="bucket" id="pBucket" class="form-control" value="{{ old('bucket') }}" />
                    </div>
                    <div class="form-group">
                        <label for="pEndpoint" class="control-label">S3 Endpoint</label>
                        <input type="text" name="endpoint" id="pEndpoint" class="form-control" value="{{ old('endpoint') }}" placeholder="https://&lt;account_id&gt;.r2.cloudflarestorage.com" />
                    </div>
                    <div class="form-group">
                        <label for="pRegion" class="control-label">Region</label>
                        <input type="text" name="region" id="pRegion" class="form-control" value="{{ old('region', 'auto') }}" />
                        <p class="text-muted small">Use <code>auto</code> for Cloudflare R2.</p>
                    </div>
                    <div class="form-group">
                        <label for="pAccessKey" class="control-label">Access Key ID</label>
                        <input type="text" name="access_key" id="pAccessKey" class="form-control" value="{{ old('access_key') }}" autocomplete="off" />
                    </div>
                    <div class="form-group">
                        <label for="pSecretKey" class="control-label">Secret Access Key</label>
                        <input type="password" name="secret_key" id="pSecretKey" class="form-control" autocomplete="new-password" />
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="hidden" name="use_path_style_endpoint" value="0" />
                            <input type="checkbox" name="use_path_style_endpoint" value="1" {{ old('use_path_style_endpoint') ? 'checked' : '' }} />
                            Use path-style endpoint
                        </label>
                        <p class="text-muted small">Enable for most S3-compatible providers including Cloudflare R2.</p>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                {!! csrf_field() !!}
                <button type="submit" class="btn btn-primary pull-right">Create Node</button>
            </div>
        </div>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var storageType = document.getElementById('pStorageType');
            var sshFields = document.getElementById('ssh-fields');
            var s3PublicFields = document.getElementById('s3-public-fields');
            var sshAuthBox = document.getElementById('ssh-auth-box');
            var s3AuthBox = document.getElementById('s3-auth-box');
            var fqdnLabel = document.getElementById('fqdn-label');
            var fqdnHelp = document.getElementById('fqdn-help');
            var remotePath = document.getElementById('pRemotePath');
            var remotePathLabel = document.getElementById('remote-path-label');
            var remotePathHelp = document.getElementById('remote-path-help');

            function toggleStorageType() {
                var isS3 = storageType.value === 's3';
                sshFields.style.display = isS3 ? 'none' : 'block';
                s3PublicFields.style.display = isS3 ? 'block' : 'none';
                sshAuthBox.style.display = isS3 ? 'none' : 'block';
                s3AuthBox.style.display = isS3 ? 'block' : 'none';

                fqdnLabel.textContent = isS3 ? 'Public Host / Domain' : 'FQDN';
                fqdnHelp.textContent = isS3
                    ? 'Hostname used in the FastDL URL shown to users (custom domain on R2).'
                    : '';

                remotePathLabel.textContent = isS3 ? 'Key Prefix' : 'Remote Path';
                remotePathHelp.textContent = isS3
                    ? 'Object key prefix inside the bucket (e.g. fastdl). Files sync to {prefix}/{server-short-id}/.'
                    : 'Absolute path on the remote server.';
                if (isS3 && remotePath.value === '/var/www/fastdl') {
                    remotePath.value = 'fastdl';
                }
            }

            storageType.addEventListener('change', toggleStorageType);

            toggleStorageType();
        })();
    </script>
@endsection
