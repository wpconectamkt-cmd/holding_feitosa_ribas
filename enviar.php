<?php
/* ============================================================
   FEITOSA RIBAS — Recebimento do formulário de contato
   enviar.php · PHP puro, sem bibliotecas externas

   ESTE ARQUIVO FICA NO SERVIDOR E NUNCA É EXIBIDO AO VISITANTE.
   É o único lugar seguro para guardar a senha do e-mail.
   ============================================================ */

/* ------------------------------------------------------------
   1. PREENCHA AQUI  (dados do e-mail da Hostinger)
   ------------------------------------------------------------ */
$CONFIG = [
    // Servidor de saída da Hostinger. Confira em:
    // hPanel > E-mails > Contas de e-mail > Definições de configuração
    'smtp_host'   => 'smtp.hostinger.com',
    'smtp_porta'  => 465,          // 465 = SSL (recomendado) | 587 = TLS
    'smtp_seguro' => 'ssl',        // 'ssl' para a porta 465, 'tls' para a 587

    // A conta de e-mail que ENVIA. Precisa ser um endereço do seu domínio,
    // criado no hPanel. Não use Gmail aqui: o servidor da Hostinger só
    // autentica contas dele.
    'smtp_usuario' => 'contato@feitosaribas.com.br',
    'smtp_senha'   => '',          // <<< PREENCHA AQUI, direto no servidor

    // Nome que aparece como remetente na sua caixa de entrada
    'remetente_nome' => 'Site Feitosa Ribas',

    // Para onde as mensagens do formulário devem chegar.
    // Pode ser o Gmail: feitosaribasadvocacia@gmail.com
    'destino' => 'feitosaribasadvocacia@gmail.com',
];

/* ------------------------------------------------------------
   2. Daqui para baixo não precisa mexer
   ------------------------------------------------------------ */

header('Content-Type: application/json; charset=utf-8');

function responder($ok, $mensagem = '')
{
    echo json_encode(['ok' => $ok, 'erro' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

// Só aceita POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    responder(false, 'Método não permitido.');
}

// Configuração incompleta
if ($CONFIG['smtp_usuario'] === '' || $CONFIG['smtp_senha'] === '' || $CONFIG['destino'] === '') {
    http_response_code(500);
    responder(false, 'O envio ainda não foi configurado no servidor.');
}

/* --- Armadilha anti-robô -------------------------------------
   O campo "empresa" é invisível para pessoas. Robôs preenchem
   tudo que encontram, então qualquer valor aqui denuncia spam.
   Respondemos "ok" para o robô não perceber que foi barrado.  */
if (trim($_POST['empresa'] ?? '') !== '') {
    responder(true);
}

/* --- Limite de envios por IP --------------------------------- */
$ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
$arquivoLimite = sys_get_temp_dir() . '/fr_form_' . md5($ip);
if (is_readable($arquivoLimite)) {
    $ultimo = (int) @file_get_contents($arquivoLimite);
    if (time() - $ultimo < 30) {
        http_response_code(429);
        responder(false, 'Aguarde alguns segundos antes de enviar novamente.');
    }
}
@file_put_contents($arquivoLimite, (string) time());

/* --- Leitura e validação dos campos --------------------------
   A validação do navegador serve para o visitante; esta aqui é
   a que realmente protege, porque não pode ser burlada.        */
function campo($nome, $limite = 200)
{
    $v = trim($_POST[$nome] ?? '');
    $v = str_replace(["\r", "\n"], ' ', $v);   // evita injeção de cabeçalho
    return mb_substr($v, 0, $limite);
}

$nome     = campo('nome', 120);
$email    = campo('email', 150);
$telefone = campo('telefone', 40);
$assunto  = campo('assunto', 80);
$consent  = trim($_POST['consentimento'] ?? '');

$erros = [];
if (mb_strlen($nome) < 3)                              $erros[] = 'nome';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $erros[] = 'e-mail';
if (strlen(preg_replace('/\D/', '', $telefone)) < 10)  $erros[] = 'telefone';
if ($consent === '')                                   $erros[] = 'consentimento';

if ($erros) {
    http_response_code(422);
    responder(false, 'Confira estes campos: ' . implode(', ', $erros) . '.');
}

if ($assunto === '') {
    $assunto = 'Não informado';
}

/* --- Montagem da mensagem ------------------------------------ */
$dataHora = date('d/m/Y \à\s H:i');

$corpo = "Nova solicitação pelo site\n"
       . "====================================\n\n"
       . "Nome......: {$nome}\n"
       . "E-mail....: {$email}\n"
       . "WhatsApp..: {$telefone}\n"
       . "Assunto...: {$assunto}\n\n"
       . "------------------------------------\n"
       . "Recebido em {$dataHora}\n"
       . "IP de origem: {$ip}\n"
       . "O visitante marcou o aceite da Política de Privacidade.\n";

$titulo = "Site — {$assunto} — {$nome}";

/* ------------------------------------------------------------
   3. Cliente SMTP
   ------------------------------------------------------------ */

function smtpLer($conexao)
{
    $resposta = '';
    while ($linha = fgets($conexao, 512)) {
        $resposta .= $linha;
        // Numa resposta de várias linhas, o 4º caractere é '-'.
        // A última linha traz um espaço nessa posição.
        if (isset($linha[3]) && $linha[3] === ' ') {
            break;
        }
    }
    return $resposta;
}

function smtpEnviar($conexao, $comando, $esperado)
{
    if ($comando !== null) {
        fwrite($conexao, $comando . "\r\n");
    }
    $resposta = smtpLer($conexao);
    $codigo = (int) substr($resposta, 0, 3);
    if (!in_array($codigo, (array) $esperado, true)) {
        throw new Exception('SMTP respondeu: ' . trim($resposta));
    }
    return $resposta;
}

try {
    $endereco = ($CONFIG['smtp_seguro'] === 'ssl' ? 'ssl://' : '') . $CONFIG['smtp_host'];
    $conexao = @fsockopen($endereco, $CONFIG['smtp_porta'], $errNo, $errStr, 20);
    if (!$conexao) {
        throw new Exception("Não foi possível conectar ao servidor de e-mail ({$errStr}).");
    }
    stream_set_timeout($conexao, 20);

    smtpEnviar($conexao, null, 220);                       // saudação do servidor
    smtpEnviar($conexao, 'EHLO ' . $CONFIG['smtp_host'], 250);

    if ($CONFIG['smtp_seguro'] === 'tls') {
        smtpEnviar($conexao, 'STARTTLS', 220);
        if (!stream_socket_enable_crypto($conexao, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception('Falha ao ativar a criptografia TLS.');
        }
        smtpEnviar($conexao, 'EHLO ' . $CONFIG['smtp_host'], 250);
    }

    smtpEnviar($conexao, 'AUTH LOGIN', 334);
    smtpEnviar($conexao, base64_encode($CONFIG['smtp_usuario']), 334);
    smtpEnviar($conexao, base64_encode($CONFIG['smtp_senha']), 235);

    smtpEnviar($conexao, 'MAIL FROM:<' . $CONFIG['smtp_usuario'] . '>', 250);
    smtpEnviar($conexao, 'RCPT TO:<' . $CONFIG['destino'] . '>', [250, 251]);
    smtpEnviar($conexao, 'DATA', 354);

    $de = '=?UTF-8?B?' . base64_encode($CONFIG['remetente_nome']) . '?= <' . $CONFIG['smtp_usuario'] . '>';

    $cabecalhos = [
        'From: ' . $de,
        'To: <' . $CONFIG['destino'] . '>',
        // Responder no seu e-mail vai direto para o visitante
        'Reply-To: =?UTF-8?B?' . base64_encode($nome) . '?= <' . $email . '>',
        'Subject: =?UTF-8?B?' . base64_encode($titulo) . '?=',
        'Date: ' . date('r'),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'X-Mailer: Site Feitosa Ribas',
    ];

    $mensagem = implode("\r\n", $cabecalhos) . "\r\n\r\n"
              . chunk_split(base64_encode($corpo), 76, "\r\n");

    // Uma linha contendo só um ponto encerra o DATA, então linhas do
    // corpo que comecem com ponto precisam ser duplicadas.
    $mensagem = preg_replace('/^\./m', '..', $mensagem);

    fwrite($conexao, $mensagem . "\r\n.\r\n");
    smtpEnviar($conexao, null, 250);
    smtpEnviar($conexao, 'QUIT', [221, 250]);
    fclose($conexao);

    responder(true);

} catch (Exception $e) {
    if (isset($conexao) && is_resource($conexao)) {
        @fclose($conexao);
    }
    // Registra o motivo real no log do servidor, mas não o mostra
    // ao visitante: a mensagem pode conter detalhes da configuração.
    error_log('[formulario] ' . $e->getMessage());
    http_response_code(500);
    responder(false, 'Não conseguimos enviar agora. Tente novamente em instantes ou fale pelo WhatsApp.');
}
