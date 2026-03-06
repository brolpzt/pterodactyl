@extends('layouts.admin')

@section('title')
    Deploy Plan &rarr; {{ $plan->name }}
@endsection

@section('content-header')
    <h1>{{ $plan->name }}<small>Deploy plan for {{ $plan->egg->name }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.deploy_plans') }}">Deploy Plans</a></li>
        <li class="active">{{ $plan->name }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="box box-primary">
            <form action="{{ route('admin.deploy_plans.view', $plan->id) }}" method="POST">
                <div class="box-header with-border">
                    <h3 class="box-title">Plan Details</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="egg_id" class="form-label">Egg</label>
                        <select name="egg_id" id="egg_id" class="form-control" required>
                            @foreach ($eggs as $egg)
                                <option value="{{ $egg->id }}" {{ $plan->egg_id == $egg->id ? 'selected' : '' }}>{{ $egg->nest->name ?? 'N/A' }} &rarr; {{ $egg->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="name" class="form-label">Plan Name</label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ $plan->name }}" required />
                    </div>
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="2" placeholder="e.g. Recomendado para 12 jogadores">{{ $plan->description }}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="memory" class="form-label">Memory (MB)</label>
                                <input type="number" name="memory" id="memory" class="form-control" min="128" value="{{ $plan->memory }}" required />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="disk" class="form-label">Disk (MB)</label>
                                <input type="number" name="disk" id="disk" class="form-control" min="512" value="{{ $plan->disk }}" required />
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cpu" class="form-label">CPU (%)</label>
                                <input type="number" name="cpu" id="cpu" class="form-control" min="0" value="{{ $plan->cpu }}" required />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="swap" class="form-label">Swap (MB)</label>
                                <input type="number" name="swap" id="swap" class="form-control" min="0" value="{{ $plan->swap }}" />
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="io" class="form-label">I/O Weight</label>
                        <input type="number" name="io" id="io" class="form-control" min="10" value="{{ $plan->io }}" />
                    </div>
                    <div class="form-group">
                        <label for="hourly_rate" class="form-label">Hourly Rate ($)</label>
                        <input type="number" name="hourly_rate" id="hourly_rate" class="form-control" step="0.000001" min="0" value="{{ $plan->hourly_rate }}" required />
                    </div>
                </div>
                <div class="box-header with-border">
                    <h3 class="box-title">Variable Overrides</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted small">Valores padrão usados na criação do servidor. Variáveis <em>user_editable</em> ainda podem ser alteradas pelo usuário.</p>
                    @forelse ($plan->egg->variables ?? [] as $variable)
                        @php
                            $override = $plan->variableOverrides->firstWhere('egg_variable_id', $variable->id);
                            $displayValue = $override?->value ?? $variable->default_value ?? '';
                        @endphp
                        <div class="form-group">
                            <label for="var_{{ $variable->id }}" class="form-label">
                                {{ $variable->name }}
                                <code class="text-muted small">({{ $variable->env_variable }})</code>
                                @if($variable->user_editable)
                                    <span class="label label-info">user_editable</span>
                                @endif
                            </label>
                            <input type="text" name="variable_overrides[{{ $variable->id }}]" id="var_{{ $variable->id }}" class="form-control" value="{{ old('variable_overrides.'.$variable->id, $displayValue) }}" placeholder="{{ $variable->default_value }}" />
                            @if($variable->description)
                                <p class="help-block">{{ $variable->description }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted">Este egg não possui variáveis.</p>
                    @endforelse
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    {!! method_field('PATCH') !!}
                    <button name="action" value="delete" class="btn btn-sm btn-danger pull-left muted muted-hover"><i class="fa fa-trash-o"></i> Delete</button>
                    <button name="action" value="edit" class="btn btn-sm btn-primary pull-right">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
