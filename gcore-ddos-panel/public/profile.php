<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require_auth();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('err', 'Perfil inválido.');
    redirect('index.php');
}

$client = gcore_client();
$error = null;

try {
    $profile = $client->getProfile($id);
} catch (Throwable $e) {
    flash('err', $e->getMessage());
    redirect('index.php');
}

$form = extract_profile_form_data($profile);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $form['rate'] = [
            'low' => max(1, min(50, (int) ($_POST['rate_low'] ?? 50))),
            'medium' => max(50, min(150, (int) ($_POST['rate_medium'] ?? 150))),
            'high' => max(150, min(300, (int) ($_POST['rate_high'] ?? 300))),
            'geo' => max(1, min(300, (int) ($_POST['rate_geo'] ?? 300))),
        ];
        $geoRaw = trim((string) ($_POST['geoip_list'] ?? ''));
        $form['geoip'] = $geoRaw === '' ? [] : preg_split('/[\s,;]+/', strtoupper($geoRaw));
        $form['geoip'] = array_values(array_filter($form['geoip'] ?? []));
        $form['acl'] = acl_from_post($_POST);

        $payload = $client->buildUpdatePayload($profile, $form['rate'], $form['geoip'], $form['acl']);
        $updated = $client->updateProfile($id, $payload);
        $profile = $updated;
        $form = extract_profile_form_data($profile);
        flash('ok', 'Enviado à Gcore · ' . ($updated['status']['status'] ?? 'OK') . ' (propagação ~2–5 min)');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$policies = GcoreClient::POLICIES;
$aclJson = json_encode($form['acl'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$ruleCount = count($form['acl']);
$st = (string) ($profile['status']['status'] ?? '—');
$stClass = str_contains(strtolower($st), 'pending') ? 'status-pending' : 'status-updated';

ob_start();
?>
<div class="meta-row">
    <a href="index.php">← Perfis</a>
    <span class="chip"><strong>ID</strong> <?= $id ?></span>
    <span class="chip"><strong>IP</strong> <?= h($profile['ip_address'] ?? '') ?></span>
    <span class="chip"><strong>POP</strong> <?= h($profile['site'] ?? '') ?></span>
    <span class="chip"><strong>Tmpl</strong> <?= h($profile['profile_template']['name'] ?? '') ?></span>
    <span class="chip"><strong>Plano</strong> <?= h($profile['plan'] ?? '') ?></span>
    <span class="chip <?= h($stClass) ?>"><?= h($st) ?></span>
</div>

<?php if ($error): ?><div class="flash flash-err" style="border-radius:6px;margin-bottom:8px"><?= h($error) ?></div><?php endif; ?>

<form method="post" id="profile-form">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="toolbar">
        <button type="submit" class="btn btn-primary">Salvar na Gcore</button>
        <button type="button" class="btn" id="add-rule">+ Regra</button>
        <button type="button" class="btn btn-ghost" id="preset-theisle">Preset The Isle</button>
        <button type="button" class="btn btn-ghost" id="preset-geo">Preset GEO block</button>
        <span class="toolbar-spacer"></span>
        <span class="toolbar-hint"><strong id="rule-count"><?= $ruleCount ?></strong> regras · ordem = prioridade · <code>dport</code> = porta no servidor</span>
    </div>

    <div class="layout-edit">
        <aside>
            <div class="panel" style="margin-bottom:10px">
                <div class="panel-head">Rate limiters (kpps)</div>
                <div class="panel-body field-grid">
                    <div class="field">
                        <label>Low 1–50</label>
                        <input type="number" name="rate_low" min="1" max="50" value="<?= (int) $form['rate']['low'] ?>">
                    </div>
                    <div class="field">
                        <label>Med 50–150</label>
                        <input type="number" name="rate_medium" min="50" max="150" value="<?= (int) $form['rate']['medium'] ?>">
                    </div>
                    <div class="field">
                        <label>High 150–300</label>
                        <input type="number" name="rate_high" min="150" max="300" value="<?= (int) $form['rate']['high'] ?>">
                    </div>
                    <div class="field">
                        <label>Geo 1–300</label>
                        <input type="number" name="rate_geo" min="1" max="300" value="<?= (int) $form['rate']['geo'] ?>">
                    </div>
                </div>
            </div>
            <div class="panel">
                <div class="panel-head">GEOIP block</div>
                <div class="panel-body">
                    <div class="field field-full">
                        <label>ISO (CN, US…)</label>
                        <textarea name="geoip_list" placeholder="vazio = off"><?= h(implode(', ', $form['geoip'])) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="panel json-panel" style="margin-top:10px">
                <div class="panel-head">JSON ACL (preview)</div>
                <pre><code id="json-preview"><?= h($aclJson) ?></code></pre>
            </div>
        </aside>

        <div class="acl-wrap">
            <table class="data" id="acl-table">
                <thead>
                <tr>
                    <th class="col-idx">#</th>
                    <th class="col-ops">Ordem</th>
                    <th class="col-policy">Política</th>
                    <th class="col-proto">Proto</th>
                    <th class="col-ports">dport</th>
                    <th class="col-ports">sport</th>
                    <th class="col-sip">sip</th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="acl-body">
                <?php foreach ($form['acl'] as $i => $rule): ?>
                    <tr class="acl-row">
                        <td class="col-idx row-idx"><?= $i + 1 ?></td>
                        <td class="col-ops">
                            <button type="button" class="btn btn-icon btn-ghost move-up" title="Subir">↑</button>
                            <button type="button" class="btn btn-icon btn-ghost move-down" title="Descer">↓</button>
                        </td>
                        <td class="col-policy">
                            <select name="acl[<?= $i ?>][policy]" required>
                                <?php foreach ($policies as $p): ?>
                                    <option value="<?= h($p) ?>" <?= ($rule['policy'] ?? '') === $p ? 'selected' : '' ?>><?= h($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input name="acl[<?= $i ?>][proto_list]" value="<?= h(implode(',', $rule['proto_list'] ?? [])) ?>" placeholder="tcp,udp"></td>
                        <td><input name="acl[<?= $i ?>][dport_list]" value="<?= h(implode(',', $rule['dport_list'] ?? [])) ?>" placeholder="7777"></td>
                        <td><input name="acl[<?= $i ?>][sport_list]" value="<?= h(implode(',', $rule['sport_list'] ?? [])) ?>" placeholder="53"></td>
                        <td><input name="acl[<?= $i ?>][sip_list]" value="<?= h(implode(',', $rule['sip_list'] ?? [])) ?>" placeholder="IP/CIDR"></td>
                        <td><button type="button" class="btn btn-icon del-rule" title="Remover">×</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<template id="rule-row-template">
    <tr class="acl-row">
        <td class="col-idx row-idx">0</td>
        <td class="col-ops">
            <button type="button" class="btn btn-icon btn-ghost move-up">↑</button>
            <button type="button" class="btn btn-icon btn-ghost move-down">↓</button>
        </td>
        <td class="col-policy">
            <select name="acl[__IDX__][policy]" required>
                <?php foreach ($policies as $p): ?>
                    <option value="<?= h($p) ?>"><?= h($p) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input name="acl[__IDX__][proto_list]" placeholder="tcp,udp"></td>
        <td><input name="acl[__IDX__][dport_list]" placeholder="7777"></td>
        <td><input name="acl[__IDX__][sport_list]" placeholder=""></td>
        <td><input name="acl[__IDX__][sip_list]" placeholder=""></td>
        <td><button type="button" class="btn btn-icon del-rule">×</button></td>
    </tr>
</template>

<script>
(function () {
    const body = document.getElementById('acl-body');
    const tpl = document.getElementById('rule-row-template').innerHTML;
    const countEl = document.getElementById('rule-count');
    const jsonEl = document.getElementById('json-preview');

    function refreshMeta() {
        const rows = body.querySelectorAll('.acl-row');
        rows.forEach((row, idx) => {
            const el = row.querySelector('.row-idx');
            if (el) el.textContent = String(idx + 1);
        });
        if (countEl) countEl.textContent = String(rows.length);
        if (jsonEl) {
            const rules = [];
            rows.forEach(row => {
                const g = n => row.querySelector('[name$="[' + n + ']"]')?.value?.trim() || '';
                const split = s => s ? s.split(/[\s,;]+/).filter(Boolean) : [];
                const policy = g('policy');
                if (!policy) return;
                rules.push({
                    policy,
                    sip_list: split(g('sip_list')),
                    dport_list: split(g('dport_list')).map(Number).filter(n => n >= 1 && n <= 65535),
                    proto_list: split(g('proto_list')),
                    sport_list: split(g('sport_list')).map(Number).filter(n => n >= 1 && n <= 65535),
                });
            });
            jsonEl.textContent = JSON.stringify(rules, null, 2);
        }
    }

    function reindex() {
        body.querySelectorAll('.acl-row').forEach((row, idx) => {
            row.querySelectorAll('[name^="acl["]').forEach(el => {
                el.name = el.name.replace(/acl\[\d+\]/, 'acl[' + idx + ']');
            });
        });
        refreshMeta();
    }

    function bindRow(row) {
        row.querySelector('.del-rule')?.addEventListener('click', () => { row.remove(); reindex(); });
        row.querySelector('.move-up')?.addEventListener('click', () => {
            const prev = row.previousElementSibling;
            if (prev) { body.insertBefore(row, prev); reindex(); }
        });
        row.querySelector('.move-down')?.addEventListener('click', () => {
            const next = row.nextElementSibling;
            if (next) { body.insertBefore(next, row); reindex(); }
        });
        row.querySelectorAll('input,select').forEach(el => el.addEventListener('input', refreshMeta));
    }

    body.querySelectorAll('.acl-row').forEach(bindRow);
    refreshMeta();

    document.getElementById('add-rule').addEventListener('click', () => {
        const idx = body.querySelectorAll('.acl-row').length;
        const html = tpl.replace(/__IDX__/g, String(idx));
        body.insertAdjacentHTML('beforeend', html);
        bindRow(body.lastElementChild);
        reindex();
        body.closest('.acl-wrap')?.scrollTo({ top: 1e6, behavior: 'smooth' });
    });

    const GEO_ISO = 'RU,CN,TR,BD,PK,IN,NP,RO,AF,IS,EE,SY,IR,SD,CU,NG,IQ';

    function applyPreset(preset, geoField) {
        body.innerHTML = '';
        preset.forEach((r, idx) => {
            const html = tpl.replace(/__IDX__/g, String(idx));
            body.insertAdjacentHTML('beforeend', html);
            const row = body.lastElementChild;
            row.querySelector('[name$="[policy]"]').value = r.policy;
            row.querySelector('[name$="[proto_list]"]').value = r.proto_list;
            row.querySelector('[name$="[dport_list]"]').value = r.dport_list;
            row.querySelector('[name$="[sport_list]"]').value = r.sport_list;
            row.querySelector('[name$="[sip_list]"]').value = r.sip_list;
            bindRow(row);
        });
        if (geoField) {
            const geo = document.querySelector('[name="geoip_list"]');
            if (geo) geo.value = GEO_ISO;
        }
        reindex();
    }

    document.getElementById('preset-theisle')?.addEventListener('click', () => {
        if (!confirm('Substituir ACL + GEOIP pelo preset The Isle (com GEO block)?')) return;
        applyPreset([
            { policy: 'allowlist', proto_list: 'any', dport_list: '', sport_list: '', sip_list: '187.94.104.229' },
            { policy: 'geo', proto_list: 'any', dport_list: '', sport_list: '', sip_list: '' },
            { policy: 'tcp-server', proto_list: 'tcp', dport_list: '2022', sport_list: '', sip_list: '' },
            { policy: 'tcp-server', proto_list: 'tcp', dport_list: '8080', sport_list: '', sip_list: '' },
            { policy: 'ratelimiter-medium', proto_list: 'udp', dport_list: '7707,7777,7778,7779', sport_list: '', sip_list: '' },
            { policy: 'ratelimiter-medium', proto_list: 'tcp', dport_list: '7707,7777,7778,7779,17707', sport_list: '', sip_list: '' },
            { policy: 'DROP', proto_list: 'icmp', dport_list: '', sport_list: '', sip_list: '' },
            { policy: 'DROP', proto_list: 'tcp', dport_list: '22', sport_list: '', sip_list: '' },
            { policy: 'dns-trust', proto_list: 'udp,tcp', dport_list: '', sport_list: '53', sip_list: '' },
            { policy: 'ratelimiter-high', proto_list: 'udp', dport_list: '', sport_list: '', sip_list: '' },
            { policy: 'ratelimiter-high', proto_list: 'tcp', dport_list: '', sport_list: '', sip_list: '' },
        ], true);
    });

    document.getElementById('preset-geo')?.addEventListener('click', () => {
        if (!confirm('Inserir allowlist + geo no topo e preencher GEOIP? (demais regras mantidas)')) return;
        document.querySelector('[name="geoip_list"]').value = GEO_ISO;
        const rows = [...body.querySelectorAll('.acl-row')];
        const filtered = rows.filter(row => row.querySelector('[name$="[policy]"]')?.value !== 'geo');
        body.innerHTML = '';
        [
            { policy: 'allowlist', proto_list: 'any', dport_list: '', sport_list: '', sip_list: '187.94.104.229' },
            { policy: 'geo', proto_list: 'any', dport_list: '', sport_list: '', sip_list: '' },
        ].forEach((r, idx) => {
            const html = tpl.replace(/__IDX__/g, String(idx));
            body.insertAdjacentHTML('beforeend', html);
            const row = body.lastElementChild;
            row.querySelector('[name$="[policy]"]').value = r.policy;
            row.querySelector('[name$="[proto_list]"]').value = r.proto_list;
            row.querySelector('[name$="[dport_list]"]').value = r.dport_list;
            row.querySelector('[name$="[sport_list]"]').value = r.sport_list;
            row.querySelector('[name$="[sip_list]"]').value = r.sip_list;
            bindRow(row);
        });
        filtered.forEach((row, i) => {
            body.appendChild(row);
            row.querySelectorAll('[name^="acl["]').forEach(el => {
                el.name = el.name.replace(/acl\[\d+\]/, 'acl[' + (i + 2) + ']');
            });
            bindRow(row);
        });
        reindex();
    });
})();
</script>
<?php
$content = ob_get_clean();
$title = 'Editar ' . ($profile['ip_address'] ?? '');
require dirname(__DIR__) . '/templates/layout.php';
