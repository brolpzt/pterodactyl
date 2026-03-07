@extends('layouts.admin')

@section('title')
    Deploy Plans
@endsection

@section('content-header')
    <h1>Deploy Plans<small>Plans for client-side server creation. Link plans to eggs and set resources.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Deploy Plans</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Plan List</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.deploy_plans.new') }}" class="btn btn-sm btn-primary">Create New</a>
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
                            <th>Memory</th>
                            <th>Disk</th>
                            <th>CPU</th>
                            <th>Hourly</th>
                            <th>Monthly</th>
                            <th style="width:120px">Actions</th>
                        </tr>
                        @foreach ($plans as $plan)
                            <tr>
                                <td><code>{{ $plan->id }}</code></td>
                                <td><a href="{{ route('admin.deploy_plans.view', $plan->id) }}">{{ $plan->name }}</a></td>
                                <td><span class="text-muted" style="max-width:200px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $plan->description }}">{{ $plan->description ?? '—' }}</span></td>
                                <td>
                                    <a href="{{ route('admin.nests.egg.view', $plan->egg_id) }}">{{ $plan->egg->name }}</a>
                                    <span class="text-muted">({{ $plan->egg->nest->name ?? 'N/A' }})</span>
                                </td>
                                <td>{{ number_format($plan->memory) }} MB</td>
                                <td>{{ number_format($plan->disk) }} MB</td>
                                <td>{{ $plan->cpu }}%</td>
                                <td>${{ number_format($plan->hourly_rate, 2) }}/hr</td>
                                <td>{{ $plan->monthly_rate !== null ? '$'.number_format($plan->monthly_rate, 2).'/mo' : '—' }}</td>
                                <td>
                                    <div style="white-space:nowrap">
                                        <a href="{{ route('admin.deploy_plans.clone', $plan->id) }}" class="btn btn-xs btn-default" title="Clone plan"><i class="fa fa-copy"></i> Clone</a>
                                        <form action="{{ route('admin.deploy_plans.destroy', $plan->id) }}" method="POST" style="display:inline-block;margin-left:6px;vertical-align:middle" onsubmit="return confirm('Delete this deploy plan?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-danger" title="Delete"><i class="fa fa-trash-o"></i> Delete</button>
                                        </form>
                                    </div>
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
