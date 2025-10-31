<?php
require_once __DIR__ . '/config.php';

// Gate de autenticação FORTE para admin
// APENAS sessão ativa é permitida (sem fallback de cookie)
if (empty($_SESSION['auth_admin']) || empty($_SESSION['auth_admin']['id'])) {
    // Redireciona imediatamente para login de admin
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Location: /login?admin=1', true, 302);
    exit;
}

header('Cache-Control: no-store, private');
header('Pragma: no-cache');

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Estrela D'Alva</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --red:#D9251A; --yellow:#F7C700; --bg:#F1F2F2; --text:#111; --muted:#6b7280; }
        body { background:var(--bg); color:var(--text); font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
        .top { background:var(--red); color:#fff; padding:18px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:8px solid var(--yellow); }
        .top .brand { display:flex; align-items:center; gap:12px; font-weight:900; font-size:1.2rem; }
        .wrap { max-width:1200px; margin:20px auto; padding:0 20px; }
        .section-title { font-weight:900; font-size:1.1rem; margin:0 0 10px; color:#1f2937; }
        .kpi-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:16px; }
        .card { background:#fff; border-radius:14px; padding:18px; box-shadow:0 8px 20px rgba(17,24,39,.06); }
        .card + .card { margin-top:16px; }
        .kpi { display:flex; flex-direction:column; gap:6px; }
        .kpi .label { color:var(--muted); font-weight:700; }
        .kpi .value { font-size:2rem; font-weight:900; }
        .grid-2 { display:grid; grid-template-columns: 1.5fr .9fr; gap:16px; margin-top:16px; }
        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
        table { width:100%; border-collapse:separate; border-spacing:0; overflow:hidden; border-radius:10px; }
        thead th { background:#fafafa; font-weight:800; font-size:.95rem; color:#374151; padding:12px; border-bottom:1px solid #eef2f7; }
        tbody td { padding:12px; border-bottom:1px solid #f2f4f8; }
        tbody tr:nth-child(odd) { background:#fcfcfd; }
        .btn { background:var(--yellow); color:var(--red); padding:10px 14px; border:none; border-radius:10px; font-weight:800; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,.08); }
        .btn:hover { background:var(--red); color:var(--yellow); }
        .muted { color:var(--muted); }
        .code-badge { align-self:center; font-weight:900; color:var(--red); background:#fff3f3; padding:6px 10px; border-radius:8px; }
        .charts-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:16px; margin-top:16px; }
    </style>
    <script>
      if (window.top !== window.self) { window.top.location = window.location; }
    </script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body>
    <div class="top">
        <div class="brand"><i class="fas fa-shield-alt"></i> Painel Administrativo</div>
        <div class="muted" style="margin-right:auto; margin-left:16px;">Bem-vindo, <?php echo htmlspecialchars($_SESSION['auth_admin']['usuario']); ?></div>
        <form method="post" action="/auth/logout" style="margin:0;">
            <button class="btn" type="submit"><i class="fas fa-sign-out-alt"></i> Sair</button>
        </form>
    </div>
    <div class="wrap">
        <div class="section-title">Indicadores</div>
        <div class="kpi-grid">
            <?php
            // KPIs
            try {
                $pdo = conectarBanco();
                $totalParticipantes = (int)$pdo->query('SELECT COUNT(*) FROM participantes')->fetchColumn();
                $totalAbastecimentos = (int)$pdo->query('SELECT COUNT(*) FROM abastecimentos')->fetchColumn();
                $mediaAbastec = 0;
                if ($totalParticipantes > 0) {
                    $mediaAbastec = round($totalAbastecimentos / $totalParticipantes, 2);
                }
                $totalValorAbastecimentos = (float)$pdo->query('SELECT COALESCE(SUM(valor), 0) FROM abastecimentos')->fetchColumn();
                $codigosAtivos = (int)$pdo->query('SELECT COUNT(*) FROM codigos WHERE usado = 0')->fetchColumn();

                // Série últimos 7 dias (contagem e soma de valor)
                $labelsDias = [];
                $datesKeys = [];
                for ($i = 6; $i >= 0; $i--) {
                    $d = new DateTime('-' . $i . ' day');
                    $datesKeys[] = $d->format('Y-m-d');
                    $labelsDias[] = $d->format('d/m');
                }
                $res = $pdo->query("SELECT DATE(data_criacao) d, COUNT(*) c, SUM(valor) s FROM abastecimentos WHERE data_criacao >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY) GROUP BY d")->fetchAll();
                $map = [];
                foreach ($res as $r) { $map[$r['d']] = ['c'=>(int)$r['c'], 's'=>(float)$r['s']]; }
                $serieCount = []; $serieValor = [];
                foreach ($datesKeys as $k) { $serieCount[] = $map[$k]['c'] ?? 0; $serieValor[] = isset($map[$k]['s']) ? (float)$map[$k]['s'] : 0; }

                // Faixas de valor
                $faixas = $pdo->query("SELECT 
                    SUM(CASE WHEN valor < 50 THEN 1 ELSE 0 END) f1,
                    SUM(CASE WHEN valor >= 50 AND valor < 100 THEN 1 ELSE 0 END) f2,
                    SUM(CASE WHEN valor >= 100 THEN 1 ELSE 0 END) f3
                FROM abastecimentos")->fetch();
                $faixa1 = (int)($faixas['f1'] ?? 0); $faixa2 = (int)($faixas['f2'] ?? 0); $faixa3 = (int)($faixas['f3'] ?? 0);

                // Códigos usados x ativos
                $usados = (int)$pdo->query('SELECT COUNT(*) FROM codigos WHERE usado = 1')->fetchColumn();
                $ativos = $codigosAtivos;

                // Top 5 participantes por quantidade de abastecimentos
                $topParticipantes = $pdo->query("SELECT p.nome, COUNT(*) AS total, SUM(a.valor) AS soma
                    FROM abastecimentos a
                    JOIN participantes p ON p.id = a.participante_id
                    GROUP BY p.id, p.nome
                    ORDER BY total DESC
                    LIMIT 5")->fetchAll();
            } catch (Throwable $e) {
                $totalParticipantes = $totalAbastecimentos = $codigosAtivos = $mediaAbastec = $totalValorAbastecimentos = 0;
                $labelsDias = $serieCount = $serieValor = []; $faixa1 = $faixa2 = $faixa3 = $usados = $ativos = 0;
                $topParticipantes = [];
            }
            ?>
            <div class="card kpi"><div class="label">Participantes</div><div class="value"><?php echo $totalParticipantes; ?></div></div>
            <div class="card kpi"><div class="label">Abastecimentos</div><div class="value"><?php echo $totalAbastecimentos; ?></div></div>
            <div class="card kpi"><div class="label">Total em R$ de Abastecimentos</div><div class="value">R$ <?php echo number_format($totalValorAbastecimentos, 2, ',', '.'); ?></div></div>
            <div class="card kpi"><div class="label">Média por Participante</div><div class="value"><?php echo number_format($mediaAbastec, 2, ',', '.'); ?></div></div>
        </div>
        
        <div class="charts-grid">
            <div class="card"><div class="section-title">Atividade (7 dias)</div><canvas id="chartAbast"></canvas></div>
            <div class="card">
                <div class="section-title">Top Participantes</div>
                <?php if (empty($topParticipantes)): ?>
                    <div class="muted">Sem dados suficientes.</div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Participante</th>
                            <th>Abastecimentos</th>
                            <th>Total (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topParticipantes as $tp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tp['nome']); ?></td>
                            <td><?php echo (int)$tp['total']; ?></td>
                            <td>R$ <?php echo number_format((float)$tp['soma'], 2, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div class="card"><div class="section-title">Distribuição por Valor</div><canvas id="chartFaixa"></canvas></div>
        </div>
        
        <div class="grid-2">
        <div class="card table-card">
            <div class="section-title">Últimos Abastecimentos</div>
            <?php
            try {
                $pdo = conectarBanco();
                $stmt = $pdo->query("SELECT a.id, p.nome, a.cupom, a.valor, a.data_criacao 
                                      FROM abastecimentos a 
                                      JOIN participantes p ON p.id = a.participante_id 
                                      ORDER BY a.data_criacao DESC 
                                      LIMIT 50");
                $rows = $stmt->fetchAll();
            } catch (Throwable $e) { $rows = []; }
            ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                        <th>Participante</th>
                        <th>Cupom</th>
                        <th>Valor</th>
                            <th>Data</th>
                        <th>Foto</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" style="color:#6b7280;">Nenhum registro.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo (int)$r['id']; ?></td>
                            <td><?php echo htmlspecialchars($r['nome']); ?></td>
                            <td><?php echo htmlspecialchars($r['cupom']); ?></td>
                            <td>R$ <?php echo number_format((float)$r['valor'], 2, ',', '.'); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($r['data_criacao'])); ?></td>
                            <td><a href="api/foto-cupom?id=<?php echo (int)$r['id']; ?>" target="_blank">Ver</a></td>
                            </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
        </div>

        <div class="card sorteio-card">
            <div class="section-title">Sorteio</div>
            <p class="muted">Abra o modal e clique em Iniciar Sorteio para selecionar um ganhador aleatoriamente.</p>
            <button class="btn" id="btn-sorteio"><i class="fas fa-random"></i> Abrir Modal</button>
        </div>
        </div>
    </div>

    <script>
document.getElementById('btn-sorteio')?.addEventListener('click', async function(){
    const { isConfirmed } = await Swal.fire({
        title: 'Sorteio',
        html: '<div class="muted">Clique no botão abaixo para iniciar o sorteio.</div>',
        showCancelButton: true,
        confirmButtonText: 'Iniciar Sorteio',
        cancelButtonText: 'Fechar',
        preConfirm: async () => {
            const r = await fetch('api/sorteio', { method:'POST', headers:{'Accept':'application/json'} });
            const j = await r.json();
            if (!j.success) throw new Error(j.message || 'Falha no sorteio');
            const d = j.data;
            const html = `
                <div style="display:grid; grid-template-columns: 1fr auto; gap:12px; margin-top:10px;">
                    <div>
                        <div style=\"font-weight:900; color:#111;\">${d.participante}</div>
                        <div class=\"muted\">Data: ${d.data}</div>
                        <div class=\"muted\">Valor: R$ ${d.valor.toFixed ? d.valor.toFixed(2) : d.valor}</div>
                        <div class=\"muted\">Cupom: ${d.cupom}</div>
                        <div class=\"muted\">WhatsApp: ${d.whatsapp || '-'}</div>
                        <div class=\"muted\">E-mail: ${d.email || '-'}</div>
                    </div>
                    <div class=\"code-badge\" title=\"Código sorteado\">${d.codigo}</div>
                </div>`;
            const modalRes = await Swal.fire({ 
                icon:'success', 
                title:'Ganhador', 
                html, 
                width:720,
                showCancelButton:true,
                cancelButtonText:'Fechar',
                confirmButtonText:'WhatsApp',
                showDenyButton: d.tem_foto,
                denyButtonText:'<i class="fas fa-image"></i> Ver Foto',
                allowOutsideClick:false
            });
            if (modalRes.isDenied) {
                window.open('api/foto-cupom?id=' + d.abastecimento_id, '_blank');
            } else if (modalRes.isConfirmed) {
                const raw = (d.whatsapp || '').toString();
                const digits = raw.replace(/\D/g,'');
                const phone = digits.startsWith('55') ? digits : ('55' + digits);
                if (digits.length === 0) {
                    Swal.fire({ icon:'warning', title:'Sem WhatsApp', text:'O participante não possui WhatsApp cadastrado.' });
                    return;
                }
                const msg = `Parabéns! Você é o ganhador do sorteio do Auto Posto Estrela D'Alva. Código: ${d.codigo}.`;
                const url = `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`;
                window.open(url, '_blank');
            }
        }
    });
});
</script>
<script>
// Dados dos gráficos
const labelsDias = <?php echo json_encode($labelsDias, JSON_UNESCAPED_UNICODE); ?>;
const serieCount = <?php echo json_encode($serieCount, JSON_UNESCAPED_UNICODE); ?>;
const serieValor = <?php echo json_encode($serieValor, JSON_UNESCAPED_UNICODE); ?>;
const codAtivos = <?php echo (int)$ativos; ?>;
const codUsados = <?php echo (int)$usados; ?>;
const faixas = <?php echo json_encode([$faixa1,$faixa2,$faixa3]); ?>;

// Gráfico linha (abastecimentos e valor)
const ctx1 = document.getElementById('chartAbast');
if (ctx1) new Chart(ctx1, {
    type: 'line',
    data: { labels: labelsDias, datasets: [
        { label: 'Abastecimentos', data: serieCount, borderColor: '#D9251A', backgroundColor: 'rgba(217,37,26,.2)', tension:.3, fill:true },
        { label: 'Valor Total (R$)', data: serieValor, borderColor: '#F7C700', backgroundColor: 'rgba(247,199,0,.2)', tension:.3, yAxisID: 'y1' }
    ]},
    options: { scales: { y: { beginAtZero:true }, y1:{ beginAtZero:true, position:'right', grid:{ drawOnChartArea:false } } } }
});

// Gráfico pizza de códigos ativos x usados
const ctx2 = document.getElementById('chartCod');
if (ctx2) new Chart(ctx2, { type:'doughnut', data: {
    labels:['Ativos','Usados'],
    datasets:[{ data:[codAtivos, codUsados], backgroundColor:['#F7C700','#D9251A'] }]
}});

// Gráfico pizza por faixas de valor
const ctx3 = document.getElementById('chartFaixa');
if (ctx3) new Chart(ctx3, { type:'doughnut', data: {
    labels:['Até R$50','R$50 a R$99','R$100+'],
    datasets:[{ data: faixas, backgroundColor:['#86efac','#fde68a','#fca5a5'] }]
}});
    </script>
</body>
</html>
