@extends('layouts.admin')

@section('title')
    Create Deploy Plan
@endsection

@section('content-header')
    <h1>Create Deploy Plan<small>Create a new plan and link it to an egg.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.deploy_plans') }}">Deploy Plans</a></li>
        <li class="active">New</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="box box-primary">
            <form action="{{ route('admin.deploy_plans') }}" method="POST">
                <div class="box-header with-border">
                    <h3 class="box-title">Plan Details</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="egg_id" class="form-label">Egg</label>
                        <select name="egg_id" id="egg_id" class="form-control" required>
                            <option value="">Select an egg</option>
                            @foreach ($eggs as $egg)
                                <option value="{{ $egg->id }}">{{ $egg->nest->name ?? 'N/A' }} &rarr; {{ $egg->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="name" class="form-label">Plan Name</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Basic 4GB" required />
                    </div>
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="2" placeholder="e.g. Recomendado para 12 jogadores"></textarea>
                        <p class="text-muted small">Optional. Shown to users when selecting the plan.</p>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="memory" class="form-label">Memory (MB)</label>
                                <input type="number" name="memory" id="memory" class="form-control" min="128" value="1024" required />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="disk" class="form-label">Disk (MB)</label>
                                <input type="number" name="disk" id="disk" class="form-control" min="512" value="10240" required />
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cpu" class="form-label">CPU (%)</label>
                                <input type="number" name="cpu" id="cpu" class="form-control" min="0" value="100" required />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="swap" class="form-label">Swap (MB)</label>
                                <input type="number" name="swap" id="swap" class="form-control" min="0" value="0" />
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="io" class="form-label">I/O Weight</label>
                        <input type="number" name="io" id="io" class="form-control" min="10" value="500" />
                    </div>
                    <div class="form-group">
                        <label for="hourly_rate" class="form-label">Hourly Rate ($)</label>
                        <input type="number" name="hourly_rate" id="hourly_rate" class="form-control" step="0.000001" min="0" value="0.015" required />
                        <p class="help-block">Usado para cobrança por hora.</p>
                    </div>
                    <div class="form-group">
                        <label for="monthly_rate" class="form-label">Monthly Rate ($)</label>
                        <input type="number" name="monthly_rate" id="monthly_rate" class="form-control" step="0.01" min="0" value="" placeholder="Opcional" />
                        <p class="help-block">Usado para cobrança mensal/trimestral/etc. Se vazio, usa hourly_rate × 720.</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Allowed Billing Cycles</label>
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input type="checkbox" id="pEnableHourly" name="enable_hourly" value="1" checked />
                            <label for="pEnableHourly" class="strong">Enable Hourly</label>
                        </div>
                        <div class="checkbox checkbox-primary no-margin-bottom" style="margin-top:8px;">
                            <input type="checkbox" id="pEnableMonthly" name="enable_monthly" value="1" checked />
                            <label for="pEnableMonthly" class="strong">Enable Monthly (includes monthly/quarterly/semi-annually/annually)</label>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <a href="{{ route('admin.deploy_plans') }}" class="btn btn-default btn-sm">Cancel</a>
                    <button type="submit" class="btn btn-success btn-sm pull-right">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
