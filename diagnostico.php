<?php
/* ============================================================
   DIAGNÓSTICO DO ENVIO DE E-MAIL
   Abra no navegador:  https://SEUSITE/diagnostico.php?executar=1

   APAGUE ESTE ARQUIVO depois que o formulário estiver funcionando.
   Ele não mostra a senha, mas revela detalhes do servidor.
   ============================================================ */

if (($_GET['executar'] ?? '') !== '1') {
    exit('Acrescente ?executar=1 ao endereço para rodar o diagnóstico.');
}

header('Content-Type: text/html; charset=utf-8');

/* Lê a configuração de dentro do enviar.php, sem executá-lo,
   para não disparar nenhum envio por engano. */
function lerConfig($arquivo)
{
    if (!is_readable($arquivo)) {
        return null;
    }
    $fonte = file_get_contents($arquivo);
    if (!preg_match('/\$CONFIG\s*=\s*\[(.*?)\n\];/s', $fonte, $m)) {
        return null;
    }
    $config = [];
    if (preg_match_all("/'([a-z_]+)'\s*=>\s*('([^']*)'|(\d+))/", $m[1], $itens, PREG_SET_ORDER)) {
        foreach ($itens as $i) {
            $config[$i[1]] = isset($i[3]) && $i[2][0] === "'" ? $i[3] : $i[4];
        }
    }
    return $config;
}

$linhas = [];
function passo($titulo, $ok, $detalhe = '')
{
    global $linhas;
    $linhas[] = [$titulo, $ok, $detalhe];
}

/* --- 1. PHP está rodando --- */
passo('PHP em execução', true, 'versão ' . PHP_VERSION);

/* --- 2. Extensão de criptografia --- */
$temSsl = extension_loaded('openssl');
passo('Extensão OpenSSL disponível', $temSsl, $temSsl ? '' : 'Sem ela a porta 465 não funciona.');

/* --- 3. Configuração preenchida --- */
$cfg = lerConfig(__DIR__ . '/enviar.php');
if (!$cfg) {
    passo('Ler configuração do enviar.php', false, 'Arquivo não encontrado ou formato inesperado.');
} else {
    passo('Ler configuração do enviar.php', true, '');
    passo('Servidor SMTP definido', !empty($cfg['smtp_host']), $cfg['smtp_host'] ?? '');
    passo('Porta definida', !empty($cfg['smtp_porta']), ($cfg['smtp_porta'] ?? '') . ' (' . ($cfg['smtp_seguro'] ?? '') . ')');
    passo('Conta de envio definida', !empty($cfg['smtp_usuario']), $cfg['smtp_usuario'] ?? '');
    passo('SENHA preenchida', !empty($cfg['smtp_senha']), empty($cfg['smtp_senha'])
        ? 'Está em branco. Edite enviar.php e preencha smtp_senha.'
        : 'preenchida (' . strlen($cfg['smtp_senha']) . ' caracteres)');
    passo('Destino definido', !empty($cfg['destino']), $cfg['destino'] ?? '');
}

/* --- 4. Conexão e autenticação --- */
if ($cfg && !empty($cfg['smtp_senha'])) {

    function ler($c)
    {
        $r = '';
        while ($l = fgets($c, 512)) {
            $r .= $l;
            if (isset($l[3]) && $l[3] === ' ') break;
        }
        return $r;
    }

    $host = ($cfg['smtp_seguro'] === 'ssl' ? 'ssl://' : '') . $cfg['smtp_host'];
    $conexao = @fsockopen($host, (int) $cfg['smtp_porta'], $errNo, $errStr, 15);

    if (!$conexao) {
        passo('Conectar ao servidor de e-mail', false,
            "Erro {$errNo}: {$errStr}. Se for bloqueio de porta, troque para 587 / tls no enviar.php.");
    } else {
        stream_set_timeout($conexao, 15);
        $saudacao = ler($conexao);
        passo('Conectar ao servidor de e-mail', substr($saudacao, 0, 3) === '220', trim($saudacao));

        fwrite($conexao, 'EHLO ' . $cfg['smtp_host'] . "\r\n");
        $ehlo = ler($conexao);
        passo('Apresentação (EHLO)', substr($ehlo, 0, 3) === '250', '');

        if ($cfg['smtp_seguro'] === 'tls') {
            fwrite($conexao, "STARTTLS\r\n");
            $st = ler($conexao);
            $okTls = substr($st, 0, 3) === '220'
                && @stream_socket_enable_crypto($conexao, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            passo('Ativar criptografia TLS', $okTls, trim($st));
            if ($okTls) {
                fwrite($conexao, 'EHLO ' . $cfg['smtp_host'] . "\r\n");
                ler($conexao);
            }
        }

        fwrite($conexao, "AUTH LOGIN\r\n");
        $a1 = ler($conexao);
        if (substr($a1, 0, 3) === '334') {
            fwrite($conexao, base64_encode($cfg['smtp_usuario']) . "\r\n");
            ler($conexao);
            fwrite($conexao, base64_encode($cfg['smtp_senha']) . "\r\n");
            $auth = ler($conexao);
            $okAuth = substr($auth, 0, 3) === '235';
            passo('Autenticar (usuário e senha)', $okAuth, $okAuth
                ? 'Aceito pelo servidor.'
                : trim($auth) . '  <-- confira a conta e a senha no hPanel');
        } else {
            passo('Autenticar (usuário e senha)', false, trim($a1));
        }

        fwrite($conexao, "QUIT\r\n");
        fclose($conexao);
    }
}

/* --- Saída --- */
$tudoOk = true;
foreach ($linhas as $l) { if (!$l[1]) { $tudoOk = false; break; } }
?>
<!doctype html>
<meta charset="utf-8">
<title>Diagnóstico do envio</title>
<style>
  body { font: 15px/1.6 system-ui, sans-serif; max-width: 780px; margin: 40px auto; padding: 0 20px; color: #1B2532; }
  h1 { font-size: 1.4rem; }
  table { border-collapse: collapse; width: 100%; margin-top: 20px; }
  td { padding: 10px 12px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
  td:first-child { width: 34px; font-size: 1.1rem; }
  .det { color: #5A6472; font-size: 0.86rem; font-family: ui-monospace, monospace; word-break: break-word; }
  .aviso { background: #FFF6E5; border-left: 4px solid #B08A46; padding: 14px 16px; margin-top: 28px; font-size: 0.9rem; }
  .bom { background: #EAF6EE; border-left-color: #3C6B52; }
</style>
<h1>Diagnóstico do envio de e-mail</h1>
<table>
<?php foreach ($linhas as $l): ?>
  <tr>
    <td><?= $l[1] ? '✅' : '❌' ?></td>
    <td>
      <?= htmlspecialchars($l[0]) ?>
      <?php if ($l[2] !== ''): ?><br><span class="det"><?= htmlspecialchars($l[2]) ?></span><?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
</table>

<div class="aviso <?= $tudoOk ? 'bom' : '' ?>">
  <?php if ($tudoOk): ?>
    Todas as etapas passaram. O formulário do site deve funcionar.
    <strong>Apague este arquivo (diagnostico.php) do servidor agora.</strong>
  <?php else: ?>
    Corrija o primeiro item marcado com ❌ e recarregue esta página.
    Quando tudo estiver certo, <strong>apague este arquivo do servidor</strong>.
  <?php endif; ?>
</div>
