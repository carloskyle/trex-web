<?php
declare(strict_types=1);

/**
 * TRex Controle via Console - Edição de Perfis (PHP 8.3 compatível)
 * - Sanitização básica de inputs
 * - Correção de warnings do PHP 8.x (Undefined array key)
 * - Redução de risco de injeção de comando ao enviar comandos ao trex-console
 */

$trex_dir    = '/opt/trex/v3.06';
$trex_bin    = $trex_dir . '/t-rex-64';
$console_bin = $trex_dir . '/trex-console';

$allowed_dirs = ['cap2', 'stl', 'astf', 'avl'];
$allowed_actions = [
    'load_profile',
    'save_original',
    'start_server',
    'check_server',
    'start_test',
    'start_test2',
    'stop',
    'stats',
    'clear',
];

function post_string(string $key, string $default = ''): string {
    $v = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
    if ($v === null || $v === false) return $default;
    if (!is_string($v)) return $default;
    return $v;
}

function post_bool(string $key): bool {
    return filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW) !== null;
}

function html(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_valid_profile_filename(string $file): bool {
    // Apenas nome de arquivo (sem path), extensão yaml/py, caracteres seguros
    if ($file === '') return false;
    if (basename($file) !== $file) return false;
    if (!preg_match('/^[A-Za-z0-9._-]+\.(yaml|py)$/', $file)) return false;
    return true;
}

function post_textarea(string $key, string $default = ''): string {
    $v = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW); // NÃO usar STRIP_LOW
    if ($v === null || $v === false) return $default;
    return is_string($v) ? $v : $default;
}



function validate_multiplier(string $m): string {
    $m = trim($m);
    // aceita: número, ou número+unidade (pps/kpps/mpps/gbps/mbps/kbps/bps) dependendo do seu uso no TRex
    // ex: 1, 10, 0.5, 10gbps
    if ($m === '') return '1';
    if (!preg_match('/^\d+(\.\d+)?([a-zA-Z]+)?$/', $m)) return '1';
    return $m;
}

function validate_duration(string $d): string {
    $d = trim($d);
    if ($d === '') return '30';
    // aceita inteiro/float simples
    if (!preg_match('/^\d+(\.\d+)?$/', $d)) return '30';
    return $d;
}

function validate_ports(string $p): string {
    $p = trim($p);
    if ($p === '') return '0 1';
    // aceita: "0 1" ou "0" ou "0,1" (vamos normalizar vírgula -> espaço)
    $p = str_replace(',', ' ', $p);
    $p = preg_replace('/\s+/', ' ', $p) ?? $p;

    if (!preg_match('/^\d+( \d+)*$/', $p)) return '0 1';
    return $p;
}

/**
 * Executa trex-console lendo comandos de um arquivo temporário (evita pipeline com echo).
 */
function run_trex_console(string $console_bin, array $console_lines, array &$output, int &$return_var): string {
    $tmp = tempnam(sys_get_temp_dir(), 'trexcmd_');
    if ($tmp === false) {
        $return_var = 1;
        $output = ['Falha ao criar arquivo temporário.'];
        return '';
    }

    // Conteúdo exatamente como o console espera
    $content = implode("\n", $console_lines) . "\n";
    file_put_contents($tmp, $content);

    // Rodar como root via sudo (console_bin já é caminho absoluto)
    $cmd = 'sudo ' . escapeshellarg($console_bin) . ' < ' . escapeshellarg($tmp) . ' 2>&1';

    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);

    @unlink($tmp);
    return $cmd;
}

/* -------- Inputs -------- */

$action = post_string('action', '');
if ($action !== '' && !in_array($action, $allowed_actions, true)) {
    $action = '';
}

$dir = post_string('dir', 'cap2');
if (!in_array($dir, $allowed_dirs, true)) {
    $dir = 'cap2';
}

$profile = post_string('profile', '');
if ($profile !== '' && !is_valid_profile_filename($profile)) {
    $profile = '';
}

$edited_content = post_textarea('edited_content', '');

$multiplier = validate_multiplier(post_string('multiplier', '1'));
$duration   = validate_duration(post_string('duration', '30'));
$ports      = validate_ports(post_string('ports', '0 1'));
$force      = post_bool('force') ? ' --force' : '';

$profiles_path = $trex_dir . '/' . $dir;
$profile_path  = ($profile !== '') ? ($trex_dir . '/' . $dir . '/' . $profile) : '';
$relative_path = ($profile !== '') ? ($dir . '/' . $profile) : '';

/* -------- Listagem de perfis -------- */

$profiles = [];
if (is_dir($profiles_path)) {
    $files = scandir($profiles_path);
    if (is_array($files)) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (!is_valid_profile_filename($file)) continue;
            $profiles[] = $file;
        }
    }
}
sort($profiles);

/* -------- Conteúdo do textarea -------- */

$profile_content = "# Selecione um perfil e clique em 'Carregar Perfil para Edição'";
if ($action !== 'load_profile' && $edited_content !== '') {
    $profile_content = $edited_content;
} elseif ($action === 'load_profile') {
    if ($profile !== '' && $profile_path !== '' && is_file($profile_path)) {
        $read = file_get_contents($profile_path);
        $profile_content = ($read !== false) ? $read : "# Falha ao ler o arquivo.";
    } else {
        $profile_content = "# Nenhum perfil selecionado ou arquivo não encontrado.";
    }
}

/* -------- Execução -------- */

$cmd = '';
$output = [];
$return_var = 0;
$messages = [];

if (is_dir($trex_dir)) {
    chdir($trex_dir);
} else {
    $messages[] = '<p style="color:#f44;">Diretório do TRex não existe: ' . html($trex_dir) . '</p>';
}

if ($action !== '') {

    if ($action === 'save_original') {
        if (trim($edited_content) === '') {
            $messages[] = '<p style="color:#f44;">Conteúdo vazio, nada salvo.</p>';
        } elseif ($profile === '' || $profile_path === '' || !is_file($profile_path)) {
            $messages[] = '<p style="color:#f44;">Perfil não selecionado ou não encontrado.</p>';
        } else {
            $ok = (file_put_contents($profile_path, $edited_content) !== false);
            if ($ok) {
                $messages[] = '<p class="cmd">Salvo com sucesso em: ' . html($profile_path) . '</p>';
            } else {
                $messages[] = '<p style="color:#f44;">Falha ao salvar (verifique permissões do arquivo).</p>';
            }
        }
    }

    elseif ($action === 'start_server') {
        // (mantive exatamente o que você tinha)
        $cmd = 'sudo /usr/local/bin/start-trex-nohup.sh 2>&1';
        exec($cmd, $output, $return_var);
    }

    elseif ($action === 'check_server') {
        $cmd = 'if pgrep -f t-rex-64 > /dev/null; then ' .
               '  echo "Server rodando (PIDs: $(pgrep -f t-rex-64))"; ' .
               'else ' .
               '  echo "Server NÃO está rodando"; ' .
               'fi';
        exec($cmd, $output, $return_var);
    }

    elseif ($action === 'start_test' || $action === 'start_test2') {

        if ($profile === '' || $profile_path === '' || !is_file($profile_path)) {
            $messages[] = '<p style="color:#f44;">Selecione um perfil válido primeiro.</p>';
        } else {
            // Sobe/garante server
            $server_cmd = ($action === 'start_test')
                ? 'sudo /usr/local/bin/start-trex-nohup_bkp.sh 2>&1'
                : 'sudo /usr/local/bin/start-trex-nohup_bkp2.sh 2>&1';

            exec($server_cmd, $output, $return_var);

            // Monta o comando do console com parâmetros já validados
            $start_cmd = "start -f {$relative_path} -m {$multiplier}";
            if (trim($duration) !== '') $start_cmd .= " -d {$duration}";
            if ($action === 'start_test' && trim($ports) !== '') $start_cmd .= " --port {$ports}";
            $start_cmd .= $force;

            // Envia comandos ao trex-console via arquivo temporário
            $console_lines = [
                $start_cmd,
                'quit'
            ];

            $output = [];
            $return_var = 0;
            $cmd = run_trex_console($console_bin, $console_lines, $output, $return_var);
        }
    }

    elseif ($action === 'stop' || $action === 'stats' || $action === 'clear') {
        $console_cmd = match ($action) {
            'stop'  => 'stop -a',
            'stats' => 'stats',
            'clear' => 'clear stats',
            default => 'stats',
        };

        $console_lines = [
            $console_cmd,
            'quit'
        ];
        $cmd = run_trex_console($console_bin, $console_lines, $output, $return_var);
    }

    // load_profile: só recarrega conteúdo (já tratado acima)
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>TRex Controle via Console - Edição de Perfis</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<img src="trex1-e64bbceeee4b2233597057940e70879c.png" width="600" height="200" alt="TRex">
<p class="warning">Atenção: alterações salvas modificam arquivos reais. Use com cuidado!</p>

<form method="post" action="" id="trexForm">

    <h2>1. Selecionar e Editar Perfil</h2>

    <label>Diretório:</label>
    <select name="dir">
        <?php foreach ($allowed_dirs as $d): ?>
            <option value="<?= html($d) ?>"<?= ($d === $dir) ? ' selected' : '' ?>><?= html($d) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Perfil:</label>
    <select name="profile">
        <?php if (!empty($profiles)): ?>
            <?php foreach ($profiles as $file): ?>
                <option value="<?= html($file) ?>"<?= ($file === $profile) ? ' selected' : '' ?>><?= html($file) ?></option>
            <?php endforeach; ?>
        <?php else: ?>
            <option value="">-- Nenhum perfil encontrado em <?= html($dir) ?> --</option>
        <?php endif; ?>
    </select>

    <button type="submit" name="action" value="load_profile">Carregar Perfil para Edição</button>

    <br><br>

    <label>Editar Perfil (altere e clique em Salvar Alterações):</label><br>
    <textarea name="edited_content"><?=
        html($profile_content)
    ?></textarea>

    <br><br>
    <button type="submit" name="action" value="save_original">Salvar Alterações no Arquivo Original</button>

    <h2>2. Parâmetros do Teste</h2>

    <label>Multiplicador (-m):</label>
    <input type="text" name="multiplier" value="<?= html($multiplier) ?>">

    <label>Duração (-d seg):</label>
    <input type="text" name="duration" value="<?= html($duration) ?>">

    <label>Portas (--port):</label>
    <input type="text" name="ports" value="<?= html($ports) ?>">

    <label>Forçar ínicio</label>
    <input type="checkbox" name="force" <?= post_bool('force') ? 'checked' : '' ?>>

    <br><br>

    <h2>3. Comandos no Console</h2>
    <button type="submit" name="action" value="start_server">Parar processo</button>
    <button type="submit" name="action" value="start_test">Iniciar teste de tráfego stateless</button>
    <button type="submit" name="action" value="start_test2">Iniciar teste de tráfego stateful</button>
</form>

<div id="output">
    <?php
    // Mensagens (ex: salvar perfil)
    foreach ($messages as $m) {
        echo $m;
    }

    if ($cmd !== '') {
        echo '<p class="cmd">Comando executado:</p><pre>' . html($cmd) . '</pre>';
        echo '<p class="cmd">Saída:</p><pre>' . html(implode("\n", $output)) . '</pre>';
        if ($return_var !== 0) {
            echo '<p style="color:#f44;">Código de erro: ' . (int)$return_var . '</p>';
        }
    }
    ?>
</div>

<h2>5. Estastísticas (Aperte Enter e escreva o comando "tui")</h2>

<iframe
    src="http://192.168.10.11:7681"
    style="width:120%; height:700px; border:1px solid #0f0; background:#000;"
    sandbox="allow-scripts allow-same-origin"></iframe>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dirSelect = document.querySelector('select[name="dir"]');
    if (dirSelect) {
        dirSelect.addEventListener('change', function() {
            document.body.style.cursor = 'wait';
            document.getElementById('trexForm').submit();
        });
    }
});
</script>

</body>
</html>

