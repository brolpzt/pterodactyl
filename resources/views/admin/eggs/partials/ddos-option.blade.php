@php
    $types = \Pterodactyl\Models\PanelAlert::TYPES;
    $policies = \Pterodactyl\Services\Gcore\GcoreClient::POLICIES;
    $name = 'options[' . $i . ']';
@endphp
<div class="row ddos-option">
    <div class="col-xs-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">{{ $row['label'] ?? 'Nova opção' }}</h3>
                @if($count > 0)
                    <span class="label label-primary" style="margin-left: 6px;">{{ $count }} servidor(es) usando</span>
                @endif
                <div class="box-tools">
                    <button type="button" class="btn btn-xs btn-default" data-action="remove"><i class="fa fa-trash-o"></i> Remover</button>
                </div>
            </div>
            <div class="box-body">
                <input type="hidden" data-role="id" name="{{ $name }}[id]" value="{{ $row['id'] ?? '' }}">
                <input type="hidden" data-role="delete" name="{{ $name }}[delete]" value="0">
                <div class="row">
                    <div class="form-group col-sm-4">
                        <label class="control-label">Perfil da Gcore</label>
                        <select name="{{ $name }}[policy]" class="form-control">
                            <option value="">Selecione…</option>
                            @foreach($policies as $policy)
                                <option value="{{ $policy }}" {{ ($row['policy'] ?? '') === $policy ? 'selected' : '' }}>{{ $policy }}</option>
                            @endforeach
                        </select>
                        <p class="text-muted small">Só o admin vê este nome.</p>
                    </div>
                    <div class="form-group col-sm-4">
                        <label class="control-label">Nome para o cliente</label>
                        <input type="text" maxlength="64" name="{{ $name }}[label]" class="form-control" value="{{ $row['label'] ?? '' }}" placeholder="Ex.: Proteção CS 1.6 (recomendada)">
                    </div>
                    <div class="form-group col-sm-4">
                        <label class="control-label">Tipo do quadro</label>
                        <select name="{{ $name }}[alert_type]" class="form-control">
                            @foreach($types as $value => $typeLabel)
                                <option value="{{ $value }}" {{ ($row['alert_type'] ?? 'info') === $value ? 'selected' : '' }}>{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-sm-4">
                        <label class="control-label">Título do quadro <span class="field-optional"></span></label>
                        <input type="text" maxlength="191" name="{{ $name }}[alert_title]" class="form-control" value="{{ $row['alert_title'] ?? '' }}">
                    </div>
                    <div class="form-group col-sm-8">
                        <label class="control-label">Mensagem do quadro <span class="field-optional"></span></label>
                        <textarea name="{{ $name }}[alert_message]" rows="3" maxlength="2000" class="form-control" placeholder="Explique ao cliente quando usar este perfil.">{{ $row['alert_message'] ?? '' }}</textarea>
                        <p class="text-muted small">Mesmo formato dos alertas do painel: <code>**negrito**</code>, <code>[link](https://...)</code>, linha em branco separa parágrafos.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
