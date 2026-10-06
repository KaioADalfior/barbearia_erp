<?php
/**
 * includes/CatalogoService.php
 *
 * "Catálogo" = a vitrine pública da barbearia (/c/agendar). Guarda só o que
 * é APRESENTAÇÃO — identidade visual, contato, horário de atendimento,
 * descrição e ordem dos serviços, cargo dos profissionais. Nenhuma regra de
 * agendamento (disponibilidade, horários, bloqueios) passa por aqui: isso
 * continua em HorarioService / Publico/scripts/*.
 *
 * Tabelas (criadas sozinhas na primeira vez; ver também
 * scriptBD/atualizacao_catalogo.sql):
 *   - CatalogoConfig   (1 linha, id = 1): configurações gerais da vitrine
 *   - CatalogoServico  (por serviço): descrição, categoria, visibilidade, ordem
 *   - CatalogoBarbeiro (por barbeiro): cargo, visibilidade, ordem
 *
 * Se o usuário do banco não puder criar tabelas, tudo cai em valores padrão
 * (a página pública continua funcionando, só não dá pra personalizar).
 */

require_once __DIR__ . '/ServicoFoto.php';

require_once __DIR__ . '/ImagemPersistente.php';

final class CatalogoService
{
    public const PASTA_IMAGENS = 'assets/uploads/catalogo/';

    public const FORMAS_PAGAMENTO = [
        'pix'      => 'Pix',
        'dinheiro' => 'Dinheiro',
        'credito'  => 'Cartão de crédito',
        'debito'   => 'Cartão de débito',
        'boleto'   => 'Boleto',
    ];

    public const COMODIDADES = [
        'wifi'         => 'Wi-Fi grátis',
        'estacionamento' => 'Estacionamento',
        'acessibilidade' => 'Acessibilidade',
        'criancas'     => 'Atende crianças',
        'ar'           => 'Ar-condicionado',
        'cafe'         => 'Café e bebidas',
        'tv'           => 'TV / Música ambiente',
        'cartao'       => 'Aceita cartão',
    ];

    /** Ordem de exibição dos dias (índice = date('w'): 0 domingo … 6 sábado). */
    public const DIAS_ORDEM = [1, 2, 3, 4, 5, 6, 0];
    public const DIAS_NOMES = [
        0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira',
        4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado',
    ];

    public const CORES_PRESET = [
        '#2f6fed' => 'Azul',
        '#c9a14a' => 'Dourado',
        '#22a06b' => 'Verde',
        '#e5484d' => 'Vermelho',
        '#7c5cff' => 'Roxo',
        '#f08a24' => 'Laranja',
    ];

    // --------------------------------------------------------------- tabelas

    /** true se as tabelas do catálogo existem (cria na primeira vez; false se não for possível). */
    public static function disponivel(PDO $pdo): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS CatalogoConfig (
                    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                    nome_exibicao VARCHAR(120) NULL,
                    slogan VARCHAR(160) NULL,
                    sobre TEXT NULL,
                    aviso VARCHAR(255) NULL,
                    logo VARCHAR(255) NULL,
                    capa VARCHAR(255) NULL,
                    cor_destaque VARCHAR(7) NOT NULL DEFAULT '#2f6fed',
                    tema VARCHAR(10) NOT NULL DEFAULT 'escuro',
                    endereco VARCHAR(255) NULL,
                    mapa_url VARCHAR(500) NULL,
                    whatsapp VARCHAR(30) NULL,
                    telefone VARCHAR(30) NULL,
                    instagram VARCHAR(80) NULL,
                    facebook VARCHAR(160) NULL,
                    formas_pagamento TEXT NULL,
                    comodidades TEXT NULL,
                    horarios TEXT NULL,
                    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS CatalogoServico (
                    idServico INT NOT NULL PRIMARY KEY,
                    descricao VARCHAR(255) NULL,
                    categoria VARCHAR(60) NULL,
                    visivel TINYINT(1) NOT NULL DEFAULT 1,
                    ordem INT NOT NULL DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS CatalogoBarbeiro (
                    id_barbeiro INT NOT NULL PRIMARY KEY,
                    cargo VARCHAR(60) NULL,
                    visivel TINYINT(1) NOT NULL DEFAULT 1,
                    ordem INT NOT NULL DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $cache = true;
        } catch (Throwable $e) {
            error_log('CatalogoService: tabelas indisponíveis: ' . $e->getMessage());
            $cache = false;
        }

        return $cache;
    }

    // ---------------------------------------------------------------- config

    public static function padroes(): array
    {
        return [
            'nome_exibicao'    => '',
            'slogan'           => '',
            'sobre'            => '',
            'aviso'            => '',
            'logo'             => null,
            'capa'             => null,
            'cor_destaque'     => '#2f6fed',
            'tema'             => 'escuro',
            'endereco'         => '',
            'mapa_url'         => '',
            'whatsapp'         => '',
            'telefone'         => '',
            'instagram'        => '',
            'facebook'         => '',
            'formas_pagamento' => [],
            'comodidades'      => [],
            'horarios'         => [],
        ];
    }

    /** Configuração atual já mesclada com os padrões (campos JSON decodificados). */
    public static function config(PDO $pdo): array
    {
        $config = self::padroes();
        if (!self::disponivel($pdo)) {
            return $config;
        }

        try {
            $linha = $pdo->query('SELECT * FROM CatalogoConfig WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return $config;
        }
        if (!$linha) {
            return $config;
        }

        foreach ($config as $chave => $padrao) {
            if (!array_key_exists($chave, $linha) || $linha[$chave] === null) {
                continue;
            }
            if (in_array($chave, ['formas_pagamento', 'comodidades', 'horarios'], true)) {
                $decodificado = json_decode((string) $linha[$chave], true);
                $config[$chave] = is_array($decodificado) ? $decodificado : [];
            } else {
                $config[$chave] = $linha[$chave];
            }
        }

        return $config;
    }

    /**
     * Valida/normaliza os dados vindos do formulário e grava. Imagens (logo e
     * capa) NÃO passam por aqui — têm endpoint próprio (salvarImagem).
     *
     * @return array{ok:bool, erro?:string}
     */
    public static function salvarConfig(PDO $pdo, array $post): array
    {
        if (!self::disponivel($pdo)) {
            return ['ok' => false, 'erro' => 'O banco não permitiu criar as tabelas do catálogo. Execute scriptBD/atualizacao_catalogo.sql.'];
        }

        $texto = static fn(string $k, int $max): string => mb_substr(trim((string) ($post[$k] ?? '')), 0, $max);

        $cor = strtolower(trim((string) ($post['cor_destaque'] ?? '')));
        if (!preg_match('/^#[0-9a-f]{6}$/', $cor)) {
            $cor = '#2f6fed';
        }
        $tema = ($post['tema'] ?? 'escuro') === 'claro' ? 'claro' : 'escuro';

        $mapa = trim((string) ($post['mapa_url'] ?? ''));
        if ($mapa !== '' && !preg_match('#^https?://#i', $mapa)) {
            return ['ok' => false, 'erro' => 'O link do mapa precisa começar com http:// ou https://.'];
        }
        $facebook = trim((string) ($post['facebook'] ?? ''));
        if ($facebook !== '' && !preg_match('#^https?://#i', $facebook)) {
            return ['ok' => false, 'erro' => 'O link do Facebook precisa começar com http:// ou https://.'];
        }

        // Instagram: aceita @usuario, usuario ou URL completa — guarda só o usuário.
        $instagram = trim((string) ($post['instagram'] ?? ''));
        $instagram = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $instagram);
        $instagram = ltrim(trim($instagram, "/ \t"), '@');
        if ($instagram !== '' && !preg_match('/^[A-Za-z0-9._]{1,30}$/', $instagram)) {
            return ['ok' => false, 'erro' => 'Instagram inválido. Informe só o @usuário.'];
        }

        $whatsapp = preg_replace('/\D+/', '', (string) ($post['whatsapp'] ?? ''));
        if ($whatsapp !== '' && (strlen($whatsapp) < 10 || strlen($whatsapp) > 13)) {
            return ['ok' => false, 'erro' => 'WhatsApp inválido. Informe com DDD (ex.: 27 99999-9999).'];
        }

        $formas = array_values(array_intersect(array_keys(self::FORMAS_PAGAMENTO), (array) ($post['formas_pagamento'] ?? [])));
        $comodidades = array_values(array_intersect(array_keys(self::COMODIDADES), (array) ($post['comodidades'] ?? [])));

        // Horário de atendimento: até 2 turnos por dia (ex.: manhã e tarde).
        $horarios = [];
        $horasPost = (array) ($post['horarios'] ?? []);
        $horaOk = static fn($h) => is_string($h) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h) === 1;
        foreach (array_keys(self::DIAS_NOMES) as $dia) {
            $d = (array) ($horasPost[$dia] ?? []);
            $aberto = !empty($d['aberto']);
            $turnos = [];
            foreach ([1, 2] as $n) {
                $ini = $d["t{$n}_ini"] ?? '';
                $fim = $d["t{$n}_fim"] ?? '';
                if ($ini === '' && $fim === '') {
                    continue;
                }
                if (!$horaOk($ini) || !$horaOk($fim) || $fim <= $ini) {
                    if ($aberto) {
                        return ['ok' => false, 'erro' => 'Horário inválido em ' . self::DIAS_NOMES[$dia] . ': o fim deve ser depois do início.'];
                    }
                    continue;
                }
                $turnos[] = [$ini, $fim];
            }
            if ($aberto && !$turnos) {
                return ['ok' => false, 'erro' => 'Informe o horário de ' . self::DIAS_NOMES[$dia] . ' ou marque o dia como fechado.'];
            }
            $horarios[(string) $dia] = ['aberto' => $aberto, 'turnos' => $aberto ? $turnos : []];
        }
        // Se nenhum dia foi marcado como aberto, trata como "não informado".
        if (!array_filter($horarios, static fn($h) => $h['aberto'])) {
            $horarios = [];
        }

        $pdo->prepare(
            'INSERT INTO CatalogoConfig
                (id, nome_exibicao, slogan, sobre, aviso, cor_destaque, tema, endereco, mapa_url, whatsapp, telefone,
                 instagram, facebook, formas_pagamento, comodidades, horarios)
             VALUES (1, :nome, :slogan, :sobre, :aviso, :cor, :tema, :endereco, :mapa, :whatsapp, :telefone,
                 :instagram, :facebook, :formas, :comodidades, :horarios)
             ON DUPLICATE KEY UPDATE
                nome_exibicao = VALUES(nome_exibicao), slogan = VALUES(slogan), sobre = VALUES(sobre), aviso = VALUES(aviso),
                cor_destaque = VALUES(cor_destaque), tema = VALUES(tema), endereco = VALUES(endereco), mapa_url = VALUES(mapa_url),
                whatsapp = VALUES(whatsapp), telefone = VALUES(telefone), instagram = VALUES(instagram), facebook = VALUES(facebook),
                formas_pagamento = VALUES(formas_pagamento), comodidades = VALUES(comodidades), horarios = VALUES(horarios)'
        )->execute([
            'nome'        => $texto('nome_exibicao', 120),
            'slogan'      => $texto('slogan', 160),
            'sobre'       => $texto('sobre', 1500),
            'aviso'       => $texto('aviso', 255),
            'cor'         => $cor,
            'tema'        => $tema,
            'endereco'    => $texto('endereco', 255),
            'mapa'        => $mapa,
            'whatsapp'    => $whatsapp,
            'telefone'    => $texto('telefone', 30),
            'instagram'   => $instagram,
            'facebook'    => $facebook,
            'formas'      => json_encode($formas),
            'comodidades' => json_encode($comodidades),
            'horarios'    => json_encode($horarios),
        ]);

        // ----- Serviços: descrição, categoria, visibilidade e ordem -----
        $stmtServico = $pdo->prepare(
            'INSERT INTO CatalogoServico (idServico, descricao, categoria, visivel, ordem)
             VALUES (:id, :descricao, :categoria, :visivel, :ordem)
             ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), categoria = VALUES(categoria),
                visivel = VALUES(visivel), ordem = VALUES(ordem)'
        );
        $idsValidos = array_map('intval', $pdo->query('SELECT idServico FROM Servico')->fetchAll(PDO::FETCH_COLUMN));
        foreach ((array) ($post['servicos'] ?? []) as $id => $s) {
            $id = (int) $id;
            if (!in_array($id, $idsValidos, true) || !is_array($s)) {
                continue;
            }
            $stmtServico->execute([
                'id'        => $id,
                'descricao' => mb_substr(trim((string) ($s['descricao'] ?? '')), 0, 255),
                'categoria' => mb_substr(trim((string) ($s['categoria'] ?? '')), 0, 60),
                'visivel'   => !empty($s['visivel']) ? 1 : 0,
                'ordem'     => max(0, min(9999, (int) ($s['ordem'] ?? 0))),
            ]);
        }

        // ----- Profissionais: cargo, visibilidade e ordem -----
        $stmtBarbeiro = $pdo->prepare(
            'INSERT INTO CatalogoBarbeiro (id_barbeiro, cargo, visivel, ordem)
             VALUES (:id, :cargo, :visivel, :ordem)
             ON DUPLICATE KEY UPDATE cargo = VALUES(cargo), visivel = VALUES(visivel), ordem = VALUES(ordem)'
        );
        $idsBarbeiros = array_map('intval', $pdo->query('SELECT id_barbeiro FROM Barbeiro')->fetchAll(PDO::FETCH_COLUMN));
        foreach ((array) ($post['barbeiros'] ?? []) as $id => $b) {
            $id = (int) $id;
            if (!in_array($id, $idsBarbeiros, true) || !is_array($b)) {
                continue;
            }
            $stmtBarbeiro->execute([
                'id'      => $id,
                'cargo'   => mb_substr(trim((string) ($b['cargo'] ?? '')), 0, 60),
                'visivel' => !empty($b['visivel']) ? 1 : 0,
                'ordem'   => max(0, min(9999, (int) ($b['ordem'] ?? 0))),
            ]);
        }

        return ['ok' => true];
    }

    // ------------------------------------------------------------- imagens

    public static function pastaImagens(): string
    {
        return __DIR__ . '/../' . self::PASTA_IMAGENS;
    }

    public static function imagemUrl(?string $nome): ?string
    {
        if (!self::nomeImagemValido($nome) || !is_file(self::pastaImagens() . $nome)) {
            return null;
        }
        return '/' . self::PASTA_IMAGENS . rawurlencode($nome);
    }

    public static function nomeImagemValido(?string $nome): bool
    {
        return is_string($nome) && preg_match('/^(logo|capa)_[a-f0-9]+\.(jpg|png|webp)$/', $nome) === 1;
    }

    /** Grava/remove a imagem ('logo' ou 'capa') e atualiza a config. */
    public static function salvarImagem(PDO $pdo, string $tipo, ?array $arquivo): array
    {
        if (!in_array($tipo, ['logo', 'capa'], true)) {
            return ['ok' => false, 'erro' => 'Tipo de imagem inválido.'];
        }
        if (!self::disponivel($pdo)) {
            return ['ok' => false, 'erro' => 'O banco ainda não tem as tabelas do catálogo (scriptBD/atualizacao_catalogo.sql).'];
        }

        $atual = self::config($pdo)[$tipo] ?? null;
        $novo = null;

        if ($arquivo !== null) {
            if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $grande = in_array($arquivo['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
                return ['ok' => false, 'erro' => $grande ? 'A imagem deve ter no máximo 4MB.' : 'Falha no envio da imagem. Tente novamente.'];
            }
            if ($arquivo['size'] > 4 * 1024 * 1024) {
                return ['ok' => false, 'erro' => 'A imagem deve ter no máximo 4MB.'];
            }
            $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = mime_content_type($arquivo['tmp_name']);
            if (!isset($tipos[$mime]) || @getimagesize($arquivo['tmp_name']) === false) {
                return ['ok' => false, 'erro' => 'Formato inválido. Envie uma imagem JPG, PNG ou WEBP.'];
            }

            $pasta = self::pastaImagens();
            if (!is_dir($pasta)) {
                @mkdir($pasta, 0755, true);
            }
            if (!is_dir($pasta) || !is_writable($pasta)) {
                error_log('CatalogoService: pasta sem permissão de escrita: ' . $pasta);
                return ['ok' => false, 'erro' => 'O servidor não conseguiu salvar a imagem (pasta sem permissão). Avise o suporte.'];
            }

            $novo = $tipo . '_' . bin2hex(random_bytes(8)) . '.' . $tipos[$mime];
            if (!move_uploaded_file($arquivo['tmp_name'], $pasta . $novo)) {
                return ['ok' => false, 'erro' => 'Não foi possível salvar a imagem no servidor.'];
            }
            // Cópia no banco: a pasta de uploads some a cada redeploy (ver ImagemPersistente).
            ImagemPersistente::guardar('catalogo/' . $novo);
        }

        // Garante a linha única de configuração antes de atualizar a coluna.
        $pdo->exec('INSERT IGNORE INTO CatalogoConfig (id) VALUES (1)');
        $col = $tipo === 'logo' ? 'logo' : 'capa';
        $pdo->prepare("UPDATE CatalogoConfig SET {$col} = :v WHERE id = 1")->execute(['v' => $novo]);

        if ($atual && self::nomeImagemValido($atual)) {
            if (is_file(self::pastaImagens() . $atual)) {
                @unlink(self::pastaImagens() . $atual);
            }
            ImagemPersistente::remover('catalogo/' . $atual);
        }

        return ['ok' => true, 'nome' => $novo, 'url' => self::imagemUrl($novo)];
    }

    // ---------------------------------------------------- serviços / barbeiros

    /**
     * Serviços para a vitrine (ativos + visíveis) ou para a tela de
     * configuração ($todos = true: inclui ocultos; inativos continuam fora).
     */
    public static function servicos(PDO $pdo, bool $todos = false): array
    {
        $temFoto  = ServicoFoto::colunaExiste($pdo);
        $temCat   = self::disponivel($pdo);
        $colFoto  = $temFoto ? ', s.foto' : '';
        $join     = $temCat ? 'LEFT JOIN CatalogoServico c ON c.idServico = s.idServico' : '';
        $colsCat  = $temCat ? ', c.descricao, c.categoria, COALESCE(c.visivel, 1) AS visivel, COALESCE(c.ordem, 0) AS ordem' : '';

        $sql = "SELECT s.idServico, s.nome, s.duracao_minutos, s.valor{$colFoto}{$colsCat}
                FROM Servico s {$join}
                WHERE s.ativo = 1" . (($temCat && !$todos) ? ' AND COALESCE(c.visivel, 1) = 1' : '') . '
                ORDER BY ' . ($temCat ? 'COALESCE(c.ordem, 0) ASC, ' : '') . 's.nome ASC';

        $servicos = [];
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $servicos[] = [
                'id'        => (int) $l['idServico'],
                'nome'      => $l['nome'],
                'duracao'   => (int) $l['duracao_minutos'],
                'valor'     => (float) $l['valor'],
                'foto'      => $temFoto ? ServicoFoto::url($l['foto'] ?? null) : null,
                'descricao' => (string) ($l['descricao'] ?? ''),
                'categoria' => (string) ($l['categoria'] ?? ''),
                'visivel'   => (int) ($l['visivel'] ?? 1) === 1,
                'ordem'     => (int) ($l['ordem'] ?? 0),
            ];
        }
        return $servicos;
    }

    /** Barbeiros com cargo/visibilidade. $todos = true inclui os ocultos (tela de configuração). */
    public static function barbeiros(PDO $pdo, bool $todos = false): array
    {
        $temCat = self::disponivel($pdo);
        $join   = $temCat ? 'LEFT JOIN CatalogoBarbeiro c ON c.id_barbeiro = b.id_barbeiro' : '';
        $cols   = $temCat ? ', c.cargo, COALESCE(c.visivel, 1) AS visivel, COALESCE(c.ordem, 0) AS ordem' : '';

        $sql = "SELECT b.id_barbeiro, b.nome, b.foto, b.link_publico{$cols}
                FROM Barbeiro b {$join}"
             . (($temCat && !$todos) ? ' WHERE COALESCE(c.visivel, 1) = 1' : '')
             . ' ORDER BY ' . ($temCat ? 'COALESCE(c.ordem, 0) ASC, ' : '') . 'b.nome ASC';

        $lista = [];
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $foto = null;
            if (!empty($l['foto']) && is_file(__DIR__ . '/../assets/uploads/perfil/' . $l['foto'])) {
                $foto = '/assets/uploads/perfil/' . rawurlencode($l['foto']);
            }
            $lista[] = [
                'id'      => (int) $l['id_barbeiro'],
                'nome'    => $l['nome'],
                'foto'    => $foto,
                'link'    => $l['link_publico'] ?? null,
                'cargo'   => (string) ($l['cargo'] ?? ''),
                'visivel' => (int) ($l['visivel'] ?? 1) === 1,
                'ordem'   => (int) ($l['ordem'] ?? 0),
            ];
        }
        return $lista;
    }

    // ------------------------------------------- regra pública (horário de atendimento)

    /**
     * O dia da semana de $data está aberto no horário de atendimento do catálogo?
     * Sem horário configurado (array vazio) não há restrição.
     */
    public static function diaAbertoPublico(array $horarios, string $data): bool
    {
        if (!$horarios) {
            return true;
        }
        $dia = (string) date('w', strtotime($data));
        return !empty($horarios[$dia]['aberto']);
    }

    /**
     * O horário "HH:MM" cabe em algum turno do dia? O 2º valor do turno é o
     * último horário agendável (mesma semântica da grade de agendar.php).
     */
    public static function horaAbertaPublico(array $horarios, string $data, string $hora): bool
    {
        if (!$horarios) {
            return true;
        }
        if (!self::diaAbertoPublico($horarios, $data)) {
            return false;
        }
        $dia = (string) date('w', strtotime($data));
        $hora = substr($hora, 0, 5);
        foreach ($horarios[$dia]['turnos'] ?? [] as $t) {
            if ($hora >= $t[0] && $hora <= $t[1]) {
                return true;
            }
        }
        return false;
    }

    // --------------------------------------------------------------- helpers

    /** Hex "#rrggbb" -> [r, g, b]. */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** Cor legível (preto/branco) sobre um fundo de destaque. */
    public static function corTextoSobre(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#10131a' : '#ffffff';
    }

    /** Ícone SVG (stroke) de uma comodidade. */
    public static function iconeComodidade(string $chave): string
    {
        $p = [
            'wifi' => '<path d="M5 12.5a10 10 0 0 1 14 0M8.2 15.6a5.6 5.6 0 0 1 7.6 0"/><circle cx="12" cy="19" r="1" fill="currentColor"/>',
            'estacionamento' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M10 16V8h3a2.5 2.5 0 0 1 0 5h-3"/>',
            'acessibilidade' => '<circle cx="12" cy="5" r="1.6"/><path d="M12 8v5h4l2 5M12 13l-2 6M8 9h8"/>',
            'criancas' => '<circle cx="12" cy="10" r="6"/><path d="M9.5 10h.01M14.5 10h.01M10 13c.6.7 1.2 1 2 1s1.4-.3 2-1"/>',
            'ar' => '<path d="M12 3v18M5 7l14 10M19 7L5 17"/>',
            'cafe' => '<path d="M5 9h11v5a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V9ZM16 10h1.5a2 2 0 0 1 0 4H16M8 4v2M12 4v2"/>',
            'tv' => '<rect x="3.5" y="5" width="17" height="11" rx="2"/><path d="M9 20h6M12 16v4"/>',
            'cartao' => '<rect x="3.5" y="6" width="17" height="12" rx="2"/><path d="M3.5 10h17M7 15h3"/>',
        ];
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . ($p[$chave] ?? '<circle cx="12" cy="12" r="8"/>') . '</svg>';
    }
}
