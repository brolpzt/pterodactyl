@extends('layouts.admin')

@section('title')
    {{ $node->name }}: Allocations
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>Control allocations available for servers on this node.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nodes') }}">Nodes</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">Allocations</li>
    </ol>
@endsection

@section('content')
@php
    $gcoreIpSet = $gcoreIpSet ?? [];
@endphp
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nodes.view', $node->id) }}">About</a></li>
                <li><a href="{{ route('admin.nodes.view.settings', $node->id) }}">Settings</a></li>
                <li><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">Configuration</a></li>
                <li class="active"><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">Allocation</a></li>
                <li><a href="{{ route('admin.nodes.view.servers', $node->id) }}">Servers</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Existing Allocations</h3>
                @if($node->gcore_enabled)
                    <form action="{{ route('admin.nodes.view.allocation.gcoreSync', $node->id) }}" method="POST" class="pull-right" style="margin-top:-3px">
                        {!! csrf_field() !!}
                        <button type="submit" class="btn btn-xs btn-info">
                            <i class="fa fa-shield"></i> Sync portas Gcore
                        </button>
                    </form>
                @endif
            </div>
            @if($node->gcore_enabled)
                <div class="box-body" style="padding-bottom:8px">
                    <p class="text-muted small" style="margin:0 0 10px">
                        1) Marque o <strong>IP</strong> que tem perfil no Gcore.
                        2) Marque as <strong>portas</strong> que devem abrir no ACL.
                        Política vem do Egg do servidor.
                    </p>
                    @if($allocations->isNotEmpty())
                        <div class="table-responsive" style="margin-bottom:8px">
                            <table class="table table-condensed" style="margin-bottom:0;background:#fafafa;border:1px solid #eee">
                                <thead>
                                <tr>
                                    <th style="width:40px">IP Gcore</th>
                                    <th>Endereço</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($allocations as $ipRow)
                                    <tr>
                                        <td class="middle">
                                            <input type="checkbox"
                                                   data-action="set-gcore-ip"
                                                   data-ip="{{ $ipRow->ip }}"
                                                   {{ isset($gcoreIpSet[$ipRow->ip]) ? 'checked' : '' }}
                                                   title="Este IP tem perfil no Gcore DDoS" />
                                        </td>
                                        <td class="middle">
                                            <code>{{ $ipRow->ip }}</code>
                                            @if(isset($gcoreIpSet[$ipRow->ip]))
                                                <span class="label label-info" style="margin-left:6px">protegido</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
            <div class="box-body table-responsive no-padding" style="overflow-x: visible">
                <table class="table table-hover" style="margin-bottom:0;">
                    <tr>
                        <th>
                            <input type="checkbox" class="select-all-files hidden-xs" data-action="selectAll">
                        </th>
                        <th>IP Address <i class="fa fa-fw fa-minus-square" style="font-weight:normal;color:#d9534f;cursor:pointer;" data-toggle="modal" data-target="#allocationModal"></i></th>
                        <th>IP Alias</th>
                        <th>Port</th>
                        @if($node->gcore_enabled)
                            <th>Abrir porta</th>
                        @endif
                        <th>Assigned To</th>
                        <th>
                            <div class="btn-group hidden-xs">
                                <button type="button" id="mass_actions" class="btn btn-sm btn-default dropdown-toggle disabled"
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Mass Actions <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu dropdown-massactions">
                                    <li><a href="#" id="selective-deletion" data-action="selective-deletion">Delete <i class="fa fa-fw fa-trash-o"></i></a></li>
                                </ul>
                            </div>
                        </th>
                    </tr>
                    @foreach($node->allocations as $allocation)
                        <tr>
                            <td class="middle min-size" data-identifier="type">
                                @if(is_null($allocation->server_id))
                                <input type="checkbox" class="select-file hidden-xs" data-action="addSelection">
                                @else
                                <input disabled="disabled" type="checkbox" class="select-file hidden-xs" data-action="addSelection">
                                @endif
                            </td>
                            <td class="col-sm-3 middle" data-identifier="ip">
                                {{ $allocation->ip }}
                                @if($node->gcore_enabled && isset($gcoreIpSet[$allocation->ip]))
                                    <span class="label label-info" style="margin-left:4px">Gcore</span>
                                @endif
                            </td>
                            <td class="col-sm-3 middle">
                                <input class="form-control input-sm" type="text" value="{{ $allocation->ip_alias }}" data-action="set-alias" data-id="{{ $allocation->id }}" placeholder="none" />
                                <span class="input-loader"><i class="fa fa-refresh fa-spin fa-fw"></i></span>
                            </td>
                            <td class="col-sm-2 middle" data-identifier="port">{{ $allocation->port }}</td>
                            @if($node->gcore_enabled)
                                <td class="middle">
                                    <input type="checkbox"
                                           data-action="set-gcore"
                                           data-id="{{ $allocation->id }}"
                                           data-ip="{{ $allocation->ip }}"
                                           {{ $allocation->gcore_protected ? 'checked' : '' }}
                                           {{ isset($gcoreIpSet[$allocation->ip]) ? '' : 'disabled' }}
                                           title="{{ isset($gcoreIpSet[$allocation->ip]) ? 'Abrir esta porta no ACL Gcore' : 'Marque o IP como Gcore primeiro' }}" />
                                    @if($allocation->gcore_protected && $allocation->server && $allocation->server->egg && $allocation->server->egg->gcore_policy)
                                        <div class="text-muted" style="font-size:11px;margin-top:2px">{{ $allocation->server->egg->gcore_policy }}</div>
                                    @endif
                                </td>
                            @endif
                            <td class="col-sm-3 middle">
                                @if(! is_null($allocation->server))
                                    <a href="{{ route('admin.servers.view', $allocation->server_id) }}">{{ $allocation->server->name }}</a>
                                @endif
                            </td>
                            <td class="col-sm-1 middle">
                                @if(is_null($allocation->server_id))
                                    <button data-action="deallocate" data-id="{{ $allocation->id }}" class="btn btn-sm btn-danger"><i class="fa fa-trash-o"></i></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
            @if($node->allocations->hasPages())
                <div class="box-footer text-center">
                    {{ $node->allocations->render() }}
                </div>
            @endif
        </div>
    </div>
    <div class="col-sm-4">
        <form action="{{ route('admin.nodes.view.allocation', $node->id) }}" method="POST">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">Assign New Allocations</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pAllocationIP" class="control-label">IP Address</label>
                        <div>
                            <select class="form-control" name="allocation_ip" id="pAllocationIP" multiple>
                                @foreach($allocations as $allocation)
                                    <option value="{{ $allocation->ip }}">{{ $allocation->ip }}</option>
                                @endforeach
                            </select>
                            <p class="text-muted small">Enter an IP address to assign ports to here.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pAllocationIP" class="control-label">IP Alias</label>
                        <div>
                            <input type="text" id="pAllocationAlias" class="form-control" name="allocation_alias" placeholder="alias" />
                            <p class="text-muted small">If you would like to assign a default alias to these allocations enter it here.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pAllocationPorts" class="control-label">Ports</label>
                        <div>
                            <select class="form-control" name="allocation_ports[]" id="pAllocationPorts" multiple></select>
                            <p class="text-muted small">Enter individual ports or port ranges here separated by commas or spaces.</p>
                        </div>
                    </div>
                    @if($node->gcore_enabled)
                        <div class="form-group">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="gcore_ip" value="1" id="pGcoreIp">
                                    Este IP está no Gcore (tem perfil DDoS)
                                </label>
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="gcore_protected" value="1" id="pGcorePorts">
                                    Abrir estas portas no ACL do DDoS
                                </label>
                            </div>
                            <p class="text-muted small">Só IPs com perfil Gcore. Portas usam a política ACL do Egg do servidor.</p>
                        </div>
                    @endif
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success btn-sm pull-right">Submit</button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="allocationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Delete Allocations for IP Block</h4>
            </div>
            <form action="{{ route('admin.nodes.view.allocation.removeBlock', $node->id) }}" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <select class="form-control" name="ip">
                                @foreach($allocations as $allocation)
                                    <option value="{{ $allocation->ip }}">{{ $allocation->ip }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    {{{ csrf_field() }}}
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Delete Allocations</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    $('[data-action="addSelection"]').on('click', function () {
        updateMassActions();
    });

    $('[data-action="selectAll"]').on('click', function () {
        $('input.select-file').not(':disabled').prop('checked', function (i, val) {
            return !val;
        });

        updateMassActions();
    });

    $('[data-action="selective-deletion"]').on('mousedown', function () {
        deleteSelected();
    });

    $('#pAllocationIP').select2({
        tags: true,
        maximumSelectionLength: 1,
        selectOnClose: true,
        tokenSeparators: [',', ' '],
    });

    $('#pAllocationPorts').select2({
        tags: true,
        selectOnClose: true,
        tokenSeparators: [',', ' '],
    });

    $('button[data-action="deallocate"]').click(function (event) {
        event.preventDefault();
        var element = $(this);
        var allocation = $(this).data('id');
        swal({
            title: '',
            text: 'Are you sure you want to delete this allocation?',
            type: 'warning',
            showCancelButton: true,
            allowOutsideClick: true,
            closeOnConfirm: false,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#d9534f',
            showLoaderOnConfirm: true
        }, function () {
            $.ajax({
                method: 'DELETE',
                url: '/admin/nodes/view/' + {{ $node->id }} + '/allocation/remove/' + allocation,
                headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') },
            }).done(function (data) {
                element.parent().parent().addClass('warning').delay(100).fadeOut();
                swal({ type: 'success', title: 'Port Deleted!' });
            }).fail(function (jqXHR) {
                console.error(jqXHR);
                swal({
                    title: 'Whoops!',
                    text: jqXHR.responseJSON.error,
                    type: 'error'
                });
            });
        });
    });

    var typingTimer;
    $('input[data-action="set-alias"]').keyup(function () {
        clearTimeout(typingTimer);
        $(this).parent().removeClass('has-error has-success');
        typingTimer = setTimeout(sendAlias, 250, $(this));
    });

    $('input[data-action="set-gcore-ip"]').on('change', function () {
        var $el = $(this);
        var ip = $el.data('ip');
        var enabled = $el.is(':checked') ? 1 : 0;
        $el.prop('disabled', true);
        $.ajax({
            method: 'POST',
            url: '/admin/nodes/view/' + {{ $node->id }} + '/allocation/gcore-ip',
            headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') },
            data: {
                ip: ip,
                gcore_ip: enabled,
            }
        }).done(function (data) {
            var msg = (data && data.message) ? data.message : 'IP Gcore atualizado.';
            swal({
                type: 'success',
                title: 'Gcore IP',
                text: msg
            }, function () {
                window.location.reload();
            });
        }).fail(function (jqXHR) {
            $el.prop('checked', !enabled);
            var err = (jqXHR.responseJSON && jqXHR.responseJSON.error)
                ? jqXHR.responseJSON.error
                : 'Falha ao marcar IP no Gcore.';
            swal({ type: 'error', title: 'Gcore IP', text: err });
        }).always(function () {
            $el.prop('disabled', false);
        });
    });

    $('input[data-action="set-gcore"]').on('change', function () {
        var $el = $(this);
        var protectedFlag = $el.is(':checked') ? 1 : 0;
        $el.prop('disabled', true);
        $.ajax({
            method: 'POST',
            url: '/admin/nodes/view/' + {{ $node->id }} + '/allocation/gcore',
            headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') },
            data: {
                allocation_id: $el.data('id'),
                gcore_protected: protectedFlag,
            }
        }).done(function (data) {
            var msg = (data && data.message) ? data.message : 'Gcore atualizado.';
            swal({ type: 'success', title: 'Gcore', text: msg });
        }).fail(function (jqXHR) {
            $el.prop('checked', !protectedFlag);
            var err = (jqXHR.responseJSON && jqXHR.responseJSON.error)
                ? jqXHR.responseJSON.error
                : 'Falha ao sincronizar com a Gcore.';
            swal({ type: 'error', title: 'Gcore', text: err });
        }).always(function () {
            $el.prop('disabled', false);
        });
    });

    var fadeTimers = [];
    function sendAlias(element) {
        element.parent().find('.input-loader').show();
        clearTimeout(fadeTimers[element.data('id')]);
        $.ajax({
            method: 'POST',
            url: '/admin/nodes/view/' + {{ $node->id }} + '/allocation/alias',
            headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') },
            data: {
                alias: element.val(),
                allocation_id: element.data('id'),
            }
        }).done(function () {
            element.parent().addClass('has-success');
        }).fail(function (jqXHR) {
            console.error(jqXHR);
            element.parent().addClass('has-error');
        }).always(function () {
            element.parent().find('.input-loader').hide();
            fadeTimers[element.data('id')] = setTimeout(clearHighlight, 2500, element);
        });
    }

    function clearHighlight(element) {
        element.parent().removeClass('has-error has-success');
    }

    function updateMassActions() {
        if ($('input.select-file:checked').length > 0) {
            $('#mass_actions').removeClass('disabled');
        } else {
            $('#mass_actions').addClass('disabled');
        }
    }

    function deleteSelected() {
        var selectedIds = [];
        var selectedItems = [];
        var selectedItemsElements = [];

        $('input.select-file:checked').each(function () {
            var $parent = $($(this).closest('tr'));
            var id = $parent.find('[data-action="deallocate"]').data('id');
            var $ip = $parent.find('td[data-identifier="ip"]');
            var $port = $parent.find('td[data-identifier="port"]');
            var block = `${$ip.text()}:${$port.text()}`;

            selectedIds.push({
                id: id
            });
            selectedItems.push(block);
            selectedItemsElements.push($parent);
        });

        if (selectedItems.length !== 0) {
            var formattedItems = "";
            var i = 0;
            $.each(selectedItems, function (key, value) {
                formattedItems += ("<code>" + value + "</code>, ");
                i++;
                return i < 5;
            });

            formattedItems = formattedItems.slice(0, -2);
            if (selectedItems.length > 5) {
                formattedItems += ', and ' + (selectedItems.length - 5) + ' other(s)';
            }

            swal({
                type: 'warning',
                title: '',
                text: 'Are you sure you want to delete the following allocations: ' + formattedItems + '?',
                html: true,
                showCancelButton: true,
                showConfirmButton: true,
                closeOnConfirm: false,
                showLoaderOnConfirm: true
            }, function () {
                $.ajax({
                    method: 'DELETE',
                    url: '/admin/nodes/view/' + {{ $node->id }} + '/allocations',
                    headers: {'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')},
                    data: JSON.stringify({
                        allocations: selectedIds
                    }),
                    contentType: 'application/json',
                    processData: false
                }).done(function () {
                    $('#file_listing input:checked').each(function () {
                        $(this).prop('checked', false);
                    });

                    $.each(selectedItemsElements, function () {
                        $(this).addClass('warning').delay(200).fadeOut();
                    });

                    swal({
                        type: 'success',
                        title: 'Allocations Deleted'
                    });
                }).fail(function (jqXHR) {
                    console.error(jqXHR);
                    swal({
                        type: 'error',
                        title: 'Whoops!',
                        html: true,
                        text: 'An error occurred while attempting to delete these allocations. Please try again.',
                    });
                });
            });
        } else {
            swal({
                type: 'warning',
                title: '',
                text: 'Please select allocation(s) to delete.',
            });
        }
    }
    </script>
@endsection
