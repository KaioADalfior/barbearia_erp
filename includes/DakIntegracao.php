<?php
/**
 * includes/DakIntegracao.php
 *
 * Integração do BarbERP com o catálogo central DAK Barber (projeto separado).
 *
 * PRINCÍPIOS
 *  - O catálogo NUNCA acessa o banco desta instalação. Ele só lê, por HTTPS,
 *    o perfil PÚBLICO publicado em /api/catalogo/v1/perfil (o mesmo que já é
 *    público em /c/agendar) e só enquanto o proprietário autorizou ("Participar
 *    do catálogo DAK").
 *  - Nada é publicado sem autorização: participa = 0 → a API responde 404.
 *  - Não há segredo compartilhado. Cada instalação gera um par de chaves
 *    Ed25519 (libsodium) e ASSINA o que envia: perfil, registro, webhooks e
 *    convites de avaliação. O catálogo guarda só a chave PÚBLICA — se o
 *    catálogo for comprometido, ninguém consegue forjar atestados desta barbearia.
 *  - Nada financeiro nem dado pessoal de cliente sai daqui. Um convite de
 *    avaliação carrega apenas: instalação, id opaco do atendimento, nome do
 *    serviço, data, primeiro nome do profissional e validade.
 *
 * TABELAS (aditivas; criadas sozinhas na primeira utilização)
 *  - DakIntegracao  (id = 1): consentimento, localização, chaves, estado do registro.
 *  - DakConvite     : convites de avaliação emitidos (1 por atendimento concluído).
 *  - DakApiLimite   : contador de requisições da API (limite por IP/minuto).
 */

require_once __DIR__ . '/CatalogoService.php';

final class DakIntegracao
{
    public const VERSAO_API      = 1;
    public const VALIDADE_CONVITE_DIAS = 30;
    private const LIMITE_API_POR_MINUTO = 60;
    private const TIMEOUT_SEG    = 4;

    private static bool $estruturaOk = false;

    // ------------------------------------------------------------ estrutura

    public static function suportado(): bool
    {
        return function_exists('sodium_crypto_sign_detached');
    }

    public static function estrutura(PDO $pdo): void
    {
        if (self::$estruturaOk) {
            return;
        }
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS DakIntegracao (
                    id                 TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                    participa          TINYINT(1) NOT NULL DEFAULT 0,
                    divulgar_contato   TINYINT(1) NOT NULL DEFAULT 0,
                    consentimento_em   DATETIME NULL,
                    consentimento_por  INT NULL,
                    revogado_em        DATETIME NULL,
                    instalacao_id      CHAR(32) NULL,
                    chave_publica      VARCHAR(64) NULL,
                    chave_privada      VARCHAR(128) NULL,
                    base_url           VARCHAR(190) NULL,
                    cidade             VARCHAR(80) NULL,
                    uf                 CHAR(2) NULL,
                    bairro             VARCHAR(80) NULL,
                    latitude           DECIMAL(9,6) NULL,
                    longitude          DECIMAL(9,6) NULL,
                    central_status     VARCHAR(20) NULL,
                    registro_em        DATETIME NULL,
                    registro_msg       VARCHAR(190) NULL,
                    atualizado_em      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS DakConvite (
                    idConvite      INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    idAgendamento  INT NOT NULL,
                    token_id       CHAR(32) NOT NULL,
                    atendimento_id CHAR(32) NOT NULL,
                    emitido_em     DATETIME NOT NULL,
                    expira_em      DATETIME NOT NULL,
                    UNIQUE KEY uk_dak_convite_ag (idAgendamento),
                    UNIQUE KEY uk_dak_convite_tid (token_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS DakApiLimite (
                    chave   CHAR(64) NOT NULL PRIMARY KEY,
                    janela  INT NOT NULL,
                    qtd     INT NOT NULL DEFAULT 0,
                    INDEX idx_dak_limite_janela (janela)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            self::$estruturaOk = true;
        } catch (Throwable $e) {
            error_log('DakIntegracao::estrutura: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------ configuração

    /** @return array<string,mixed> Estado da integração (sem a chave privada). */
    public static function config(PDO $pdo): array
    {
        self::estrutura($pdo);
        $padrao = [
            'participa' => false, 'divulgar_contato' => false, 'consentimento_em' => null,
            'instalacao_id' => null, 'chave_publica' => null, 'base_url' => null,
            'cidade' => '', 'uf' => '', 'bairro' => '', 'latitude' => null, 'longitude' => null,
            'central_status' => null, 'registro_em' => null, 'registro_msg' => null,
        ];
        try {
            $l = $pdo->query('SELECT * FROM DakIntegracao WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return $padrao;
        }
        if (!$l) {
            return $padrao;
        }
        return [
            'participa'        => (int) $l['participa'] === 1,
            'divulgar_contato' => (int) $l['divulgar_contato'] === 1,
            'consentimento_em' => $l['consentimento_em'],
            'instalacao_id'    => $l['instalacao_id'],
            'chave_publica'    => $l['chave_publica'],
            'base_url'         => $l['base_url'],
            'cidade'           => (string) $l['cidade'],
            'uf'               => (string) $l['uf'],
            'bairro'           => (string) $l['bairro'],
            'latitude'         => $l['latitude'] !== null ? (float) $l['latitude'] : null,
            'longitude'        => $l['longitude'] !== null ? (float) $l['longitude'] : null,
            'central_status'   => $l['central_status'],
            'registro_em'      => $l['registro_em'],
            'registro_msg'     => $l['registro_msg'],
        ];
    }

    public static function centralUrl(): string
    {
        $u = rtrim((string) (getenv('DAK_CENTRAL_URL') ?: ''), '/');
        return preg_match('#^https?://[^/\s]+$#i', $u) ? $u : '';
    }

    /** Origem desta instalação (https://host), a partir da requisição do proprietário ou de APP_URL. */
    public static function origemAtual(): string
    {
        $env = rtrim((string) (getenv('APP_URL') ?: ''), '/');
        if ($env !== '' && preg_match('#^https?://[A-Za-z0-9.\-]+(:\d{1,5})?$#', $env)) {
            return $env;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
            return '';
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        return ($https ? 'https://' : 'http://') . $host;
    }

    /**
     * Salva consentimento e localização. Na 1ª ativação gera instalação e chaves.
     *
     * @return array{ok:bool, erro?:string}
     */
    public static function salvar(PDO $pdo, array $post, int $idBarbeiro): array
    {
        if (!self::suportado()) {
            return ['ok' => false, 'erro' => 'A extensão libsodium do PHP não está disponível neste servidor.'];
        }
        self::estrutura($pdo);

        $participa = !empty($post['dak_participa']);
        $contato   = !empty($post['dak_divulgar_contato']);
        $cidade    = self::texto($post['dak_cidade'] ?? '', 80);
        $uf        = strtoupper(trim((string) ($post['dak_uf'] ?? '')));
        $bairro    = self::texto($post['dak_bairro'] ?? '', 80);
        $lat       = trim((string) ($post['dak_latitude'] ?? ''));
        $lng       = trim((string) ($post['dak_longitude'] ?? ''));

        if ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf)) {
            return ['ok' => false, 'erro' => 'UF inválida.'];
        }
        if ($participa && ($cidade === '' || $uf === '')) {
            return ['ok' => false, 'erro' => 'Informe cidade e UF para participar do catálogo.'];
        }
        $latV = $lngV = null;
        if ($lat !== '' || $lng !== '') {
            if (!is_numeric($lat) || !is_numeric($lng) || (float) $lat < -90 || (float) $lat > 90 || (float) $lng < -180 || (float) $lng > 180) {
                return ['ok' => false, 'erro' => 'Latitude/longitude inválidas.'];
            }
            $latV = round((float) $lat, 4);   // ~11 m: localização aproximada, nunca o ponto exato
            $lngV = round((float) $lng, 4);
        }

        $atual = $pdo->query('SELECT * FROM DakIntegracao WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: null;
        $iid   = $atual['instalacao_id'] ?? null;
        $pub   = $atual['chave_publica'] ?? null;
        $priv  = $atual['chave_privada'] ?? null;
        if ($participa && (!$iid || !$pub || !$priv)) {
            $kp   = sodium_crypto_sign_keypair();
            $iid  = bin2hex(random_bytes(16));
            $pub  = self::b64(sodium_crypto_sign_publickey($kp));
            $priv = self::b64(sodium_crypto_sign_secretkey($kp));
        }
        $base = $atual['base_url'] ?? null;
        if ($participa) {
            $origem = self::origemAtual();
            if ($origem === '') {
                return ['ok' => false, 'erro' => 'Não foi possível identificar o endereço desta instalação.'];
            }
            $base = $origem;
        }
        $jaParticipava = $atual && (int) $atual['participa'] === 1;
        $agora = date('Y-m-d H:i:s');

        $pdo->prepare(
            'INSERT INTO DakIntegracao (id, participa, divulgar_contato, consentimento_em, consentimento_por, revogado_em,
                                        instalacao_id, chave_publica, chave_privada, base_url, cidade, uf, bairro, latitude, longitude)
             VALUES (1, :p, :dc, :ce, :cp, :rv, :iid, :pub, :priv, :base, :cid, :uf, :bai, :lat, :lng)
             ON DUPLICATE KEY UPDATE participa = VALUES(participa), divulgar_contato = VALUES(divulgar_contato),
                 consentimento_em = VALUES(consentimento_em), consentimento_por = VALUES(consentimento_por),
                 revogado_em = VALUES(revogado_em), instalacao_id = VALUES(instalacao_id), chave_publica = VALUES(chave_publica),
                 chave_privada = VALUES(chave_privada), base_url = VALUES(base_url), cidade = VALUES(cidade), uf = VALUES(uf),
                 bairro = VALUES(bairro), latitude = VALUES(latitude), longitude = VALUES(longitude)'
        )->execute([
            'p'    => $participa ? 1 : 0,
            'dc'   => $participa && $contato ? 1 : 0,
            'ce'   => $participa ? ($jaParticipava ? ($atual['consentimento_em'] ?? $agora) : $agora) : ($atual['consentimento_em'] ?? null),
            'cp'   => $participa ? ($jaParticipava ? ($atual['consentimento_por'] ?? $idBarbeiro) : $idBarbeiro) : ($atual['consentimento_por'] ?? null),
            'rv'   => $participa ? null : ($jaParticipava ? $agora : ($atual['revogado_em'] ?? null)),
            'iid'  => $iid, 'pub' => $pub, 'priv' => $priv, 'base' => $base,
            'cid'  => $cidade !== '' ? $cidade : null, 'uf' => $uf !== '' ? $uf : null, 'bai' => $bairro !== '' ? $bairro : null,
            'lat'  => $latV, 'lng' => $lngV,
        ]);

        return ['ok' => true];
    }

    // ----------------------------------------------------------------- perfil

    /**
     * Perfil público para o catálogo. Só contém o que já é público em /c/agendar
     * (+ cidade/UF/bairro e contatos SE autorizados). Nunca dados de clientes ou financeiros.
     *
     * @return array<string,mixed>|null null quando não participa
     */
    public static function perfil(PDO $pdo): ?array
    {
        $dak = self::config($pdo);
        if (!$dak['participa'] || !$dak['instalacao_id'] || !$dak['base_url']) {
            return null;
        }
        $base = (string) $dak['base_url'];
        $cfg  = CatalogoService::config($pdo);

        $abs = static function (?string $caminho) use ($base): ?string {
            return $caminho ? $base . $caminho : null;
        };

        $galeria = [];
        $servicos = [];
        foreach (CatalogoService::servicos($pdo) as $s) {
            $servicos[] = [
                'nome'      => (string) $s['nome'],
                'descricao' => (string) $s['descricao'],
                'categoria' => (string) $s['categoria'],
                'valor'     => round((float) $s['valor'], 2),
                'duracao'   => (int) $s['duracao'],
            ];
            if (!empty($s['foto']) && count($galeria) < 12) {
                $galeria[] = ['url' => $abs($s['foto']), 'legenda' => (string) $s['nome']];
            }
        }
        $profissionais = [];
        foreach (CatalogoService::barbeiros($pdo) as $b) {
            $profissionais[] = ['nome' => (string) $b['nome'], 'cargo' => (string) $b['cargo'], 'foto' => $abs($b['foto'])];
        }

        $estab = [
            'nome'             => (string) $cfg['nome_exibicao'],
            'slogan'           => (string) $cfg['slogan'],
            'sobre'            => (string) $cfg['sobre'],
            'endereco'         => (string) $cfg['endereco'],
            'bairro'           => (string) $dak['bairro'],
            'cidade'           => (string) $dak['cidade'],
            'uf'               => (string) $dak['uf'],
            'latitude'         => $dak['latitude'],
            'longitude'        => $dak['longitude'],
            'telefone'         => $dak['divulgar_contato'] ? (string) $cfg['telefone'] : '',
            'whatsapp'         => $dak['divulgar_contato'] ? (string) $cfg['whatsapp'] : '',
            'instagram'        => (string) $cfg['instagram'],
            'formas_pagamento' => array_values(array_map('strval', (array) $cfg['formas_pagamento'])),
            'comodidades'      => array_values(array_map('strval', (array) $cfg['comodidades'])),
            'horarios'         => (object) (array) $cfg['horarios'],
            'cor_destaque'     => (string) $cfg['cor_destaque'],
        ];
        $conteudo = [
            'estabelecimento'  => $estab,
            'imagens'          => ['logo' => $abs(CatalogoService::imagemUrl($cfg['logo'] ?? null)), 'capa' => $abs(CatalogoService::imagemUrl($cfg['capa'] ?? null)), 'galeria' => $galeria],
            'servicos'         => $servicos,
            'profissionais'    => $profissionais,
            'agendamento_url'  => $base . '/c/agendar',
        ];

        return [
            'v'              => self::VERSAO_API,
            'instalacao_id'  => $dak['instalacao_id'],
            'versao'         => hash('sha256', json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'gerado_em'      => gmdate('c'),
        ] + $conteudo;
    }

    // ------------------------------------------------------------- assinatura

    public static function assinar(PDO $pdo, string $mensagem): ?string
    {
        $priv = $pdo->query('SELECT chave_privada FROM DakIntegracao WHERE id = 1')->fetchColumn();
        if (!is_string($priv) || $priv === '' || !self::suportado()) {
            return null;
        }
        $sk = self::deb64($priv);
        return $sk === null ? null : self::b64(sodium_crypto_sign_detached($mensagem, $sk));
    }

    // -------------------------------------------------- limite de requisições

    /** true = pode atender; false = excedeu o limite desta janela de 1 minuto. */
    public static function dentroDoLimite(PDO $pdo, string $ip): bool
    {
        self::estrutura($pdo);
        $janela = intdiv(time(), 60);
        $chave  = hash('sha256', 'perfil|' . $ip . '|' . $janela);
        try {
            $pdo->prepare('INSERT INTO DakApiLimite (chave, janela, qtd) VALUES (:c, :j, 1) ON DUPLICATE KEY UPDATE qtd = qtd + 1')
                ->execute(['c' => $chave, 'j' => $janela]);
            $st = $pdo->prepare('SELECT qtd FROM DakApiLimite WHERE chave = :c');
            $st->execute(['c' => $chave]);
            if (random_int(1, 50) === 1) {
                $pdo->prepare('DELETE FROM DakApiLimite WHERE janela < :j')->execute(['j' => $janela - 5]);
            }
            return (int) $st->fetchColumn() <= self::LIMITE_API_POR_MINUTO;
        } catch (Throwable $e) {
            return true; // falha no contador nunca derruba a API
        }
    }

    // ------------------------------------------------- comunicação com o central

    /**
     * Envia mensagem assinada ao central. Nunca lança: devolve [status HTTP, corpo].
     *
     * @return array{0:int,1:string}
     */
    private static function postarAoCentral(PDO $pdo, string $caminho, array $corpo): array
    {
        $central = self::centralUrl();
        if ($central === '' || !function_exists('curl_init')) {
            return [0, 'central não configurado'];
        }
        $json = json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sig  = self::assinar($pdo, $json);
        if ($sig === null) {
            return [0, 'sem chave'];
        }
        try {
            $ch = curl_init($central . $caminho);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-DAK-Assinatura: ' . $sig, 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT_SEG,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $resp = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return [$http, is_string($resp) ? $resp : ''];
        } catch (Throwable $e) {
            return [0, 'falha de rede'];
        }
    }

    /** Pede ao central o registro (ou reconfirma) desta instalação. @return array{ok:bool,msg:string} */
    public static function registrar(PDO $pdo): array
    {
        $dak = self::config($pdo);
        if (!$dak['participa'] || !$dak['instalacao_id']) {
            return ['ok' => false, 'msg' => 'A participação não está ativa.'];
        }
        if (self::centralUrl() === '') {
            return ['ok' => false, 'msg' => 'Endereço do catálogo (DAK_CENTRAL_URL) não configurado neste servidor.'];
        }
        [$http, $corpo] = self::postarAoCentral($pdo, '/api/v1/registro', [
            'v' => self::VERSAO_API, 'evento' => 'registro', 'instalacao_id' => $dak['instalacao_id'],
            'chave_publica' => $dak['chave_publica'], 'base_url' => $dak['base_url'],
            'ts' => time(), 'nonce' => bin2hex(random_bytes(12)),
        ]);
        $r = json_decode($corpo, true);
        $ok = $http >= 200 && $http < 300 && is_array($r) && !empty($r['ok']);
        $status = $ok ? (string) ($r['status'] ?? 'pendente') : null;
        $msg = $ok ? ($status === 'ativa' ? 'Registro confirmado: sua barbearia está aprovada pela DAK.' : 'Registrado no catálogo. Aguarde a aprovação da DAK.') : (is_array($r) && !empty($r['erro']) ? (string) $r['erro'] : 'Não foi possível contatar o catálogo (HTTP ' . $http . ').');
        $pdo->prepare('UPDATE DakIntegracao SET central_status = :s, registro_em = NOW(), registro_msg = :m WHERE id = 1')
            ->execute(['s' => $status, 'm' => mb_substr($msg, 0, 190)]);
        return ['ok' => $ok, 'msg' => $msg];
    }

    /** Avisa o catálogo que o perfil mudou (best-effort; o catálogo também sincroniza periodicamente). */
    public static function notificarMudanca(PDO $pdo, string $evento = 'perfil_atualizado'): void
    {
        try {
            $dak = self::config($pdo);
            if (!$dak['instalacao_id'] || self::centralUrl() === '' || ($evento === 'perfil_atualizado' && !$dak['participa'])) {
                return;
            }
            $perfil = self::perfil($pdo);
            self::postarAoCentral($pdo, '/api/v1/webhook', [
                'v' => self::VERSAO_API, 'evento' => $evento, 'instalacao_id' => $dak['instalacao_id'],
                'versao' => $perfil['versao'] ?? null, 'ts' => time(), 'nonce' => bin2hex(random_bytes(12)),
            ]);
        } catch (Throwable $e) {
            error_log('DakIntegracao::notificarMudanca: ' . $e->getMessage());
        }
    }

    // ----------------------------------------------- convite de avaliação

    /**
     * Gera (ou reaproveita) o convite de avaliação de um atendimento CONCLUÍDO.
     * O token é assinado e carrega só: instalação, id opaco do atendimento, serviço,
     * data, primeiro nome do profissional e validade. Uso único é garantido no central.
     *
     * @return array{ok:bool, erro?:string, url?:string, whatsapp?:string, expira_em?:string}
     */
    public static function emitirConvite(PDO $pdo, int $idAgendamento, int $idBarbeiroSessao, bool $proprietario): array
    {
        $dak = self::config($pdo);
        if (!$dak['participa'] || !$dak['instalacao_id'] || self::centralUrl() === '') {
            return ['ok' => false, 'erro' => 'Ative a participação no catálogo DAK (Catálogo > Configurar) para convidar clientes a avaliar.'];
        }
        $st = $pdo->prepare(
            "SELECT a.idAgendamento, a.Status, a.Data, c.telefone, s.nome AS servico, b.nome AS profissional, h.id_barbeiro
             FROM Agendamentos a
             INNER JOIN Horario h ON h.idHorario = a.idHorario
             INNER JOIN Barbeiro b ON b.id_barbeiro = h.id_barbeiro
             INNER JOIN Cliente c ON c.idCliente = a.idCliente
             INNER JOIN Servico s ON s.idServico = a.idServico
             WHERE a.idAgendamento = :id"
        );
        $st->execute(['id' => $idAgendamento]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a || (!$proprietario && (int) $a['id_barbeiro'] !== $idBarbeiroSessao)) {
            return ['ok' => false, 'erro' => 'Atendimento não encontrado.'];
        }
        if ($a['Status'] !== 'concluido') {
            return ['ok' => false, 'erro' => 'Só atendimentos concluídos podem ser avaliados.'];
        }
        if (strtotime((string) $a['Data']) < strtotime('-' . self::VALIDADE_CONVITE_DIAS . ' days')) {
            return ['ok' => false, 'erro' => 'Este atendimento é antigo demais para receber convite de avaliação.'];
        }

        $priv = (string) $pdo->query('SELECT chave_privada FROM DakIntegracao WHERE id = 1')->fetchColumn();
        $agora = new DateTimeImmutable('now');
        $tid = null; $expira = null;

        $ex = $pdo->prepare('SELECT token_id, atendimento_id, expira_em FROM DakConvite WHERE idAgendamento = :id');
        $ex->execute(['id' => $idAgendamento]);
        $c = $ex->fetch(PDO::FETCH_ASSOC);
        $aid = hash_hmac('sha256', $dak['instalacao_id'] . '|' . $idAgendamento, $priv);
        $aid = substr($aid, 0, 32);

        if ($c && strtotime((string) $c['expira_em']) > time()) {
            $tid = $c['token_id']; $expira = new DateTimeImmutable((string) $c['expira_em']);
        } else {
            $tid = bin2hex(random_bytes(16));
            $expira = $agora->modify('+' . self::VALIDADE_CONVITE_DIAS . ' days');
            $pdo->prepare(
                'INSERT INTO DakConvite (idAgendamento, token_id, atendimento_id, emitido_em, expira_em)
                 VALUES (:ag, :t, :a, :e, :x)
                 ON DUPLICATE KEY UPDATE token_id = VALUES(token_id), atendimento_id = VALUES(atendimento_id), emitido_em = VALUES(emitido_em), expira_em = VALUES(expira_em)'
            )->execute(['ag' => $idAgendamento, 't' => $tid, 'a' => $aid, 'e' => $agora->format('Y-m-d H:i:s'), 'x' => $expira->format('Y-m-d H:i:s')]);
        }

        $primeiro = explode(' ', trim((string) $a['profissional']))[0] ?? '';
        $payload = json_encode([
            'iid' => $dak['instalacao_id'], 'tid' => $tid, 'aid' => $aid,
            'srv' => mb_substr((string) $a['servico'], 0, 80), 'dia' => (string) $a['Data'],
            'prof' => mb_substr($primeiro, 0, 40), 'iat' => $agora->getTimestamp(), 'exp' => $expira->getTimestamp(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sig = self::assinar($pdo, $payload);
        if ($sig === null) {
            return ['ok' => false, 'erro' => 'Não foi possível assinar o convite.'];
        }
        $token = 'v1.' . self::b64($payload) . '.' . $sig;
        $url = self::centralUrl() . '/avaliar#t=' . $token;

        $nome = (string) (CatalogoService::config($pdo)['nome_exibicao'] ?? '');
        $msg = 'Olá! Obrigado pela visita' . ($nome !== '' ? ' à ' . $nome : '') . '. Conte como foi seu atendimento: ' . $url;
        $fone = preg_replace('/\D+/', '', (string) $a['telefone']) ?? '';
        if ($fone !== '' && strlen($fone) <= 11) {
            $fone = '55' . $fone;
        }
        $wa = $fone !== '' ? 'https://wa.me/' . $fone . '?text=' . rawurlencode($msg) : 'https://wa.me/?text=' . rawurlencode($msg);

        return ['ok' => true, 'url' => $url, 'whatsapp' => $wa, 'expira_em' => $expira->format('d/m/Y')];
    }

    // --------------------------------------------------------------- helpers

    public static function b64(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function deb64(string $s): ?string
    {
        $r = base64_decode(strtr($s, '-_', '+/'), true);
        return $r === false ? null : $r;
    }

    private static function texto(mixed $v, int $max): string
    {
        $v = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v)) ?? '');
        return mb_substr($v, 0, $max, 'UTF-8');
    }
}
